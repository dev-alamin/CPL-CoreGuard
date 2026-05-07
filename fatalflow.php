<?php
/**
 * Plugin Name:       FatalFlow
 * Plugin URI:        https://github.com/dev-alamin/fatalflow
 * Description:       The ultimate safety net for professional WordPress websites.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Al Amin
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       fatalflow
 * Domain Path:       /languages
 */

// Abort if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'FATALFLOW_VERSION', '1.0.0' );
define( 'FATALFLOW_FILE', __FILE__ );
define( 'FATALFLOW_DIR', plugin_dir_path( __FILE__ ) );
define( 'FATALFLOW_URL', plugin_dir_url( __FILE__ ) );
define( 'FATALFLOW_MU_FILE', 'fatalflow-logic.php' );
define( 'FATALFLOW_CFG_FILE', 'fatalflow-config.php' );

require_once __DIR__ . '/vendor/autoload.php';

use Amin\Fatal_Flow\Core\File_System;
use Amin\Fatal_Flow\Core\Config_Generator;
use Amin\Fatal_Flow\Admin\Settings;

/**
 * Main plugin class — singleton.
 */
final class Fatal_Flow {

	/** @var Fatal_Flow|null */
	private static $instance = null;

	/** @var File_System */
	private $fs;

	private $conf;

	/**
	 * Returns (and lazily creates) the single instance.
	 *
	 * @return Fatal_Flow
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self( new File_System(), new Config_Generator() );
		}
		return self::$instance;
	}

	/** Private constructor — use ::instance(). */
	private function __construct( File_System $fs, Config_Generator $conf ) {
		$this->fs   = $fs;
		$this->conf = $conf;

		register_activation_hook( FATALFLOW_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( FATALFLOW_FILE, array( $this, 'deactivate' ) );

		if ( is_admin() ) {
			new Settings( $this->fs, $this->conf ); // Initialize admin settings page.
			add_action( 'admin_init', array( $this, 'handle_preview_request' ) ); // Handle preview requests.
		}
	}

	// -------------------------------------------------------------------------
	// Activation / Deactivation
	// -------------------------------------------------------------------------

	/**
	 * Runs on plugin activation.
	 * * Orchestrates the deployment by fetching generated strings and
	 * passing them to the filesystem service.
	 */
	public function activate(): void {

		// 1. Ensure the mu-plugins directory exists.
		$this->fs->maybe_create_mu_dir();

		// 2. Generate and write the CPL config file.
		$config_content = $this->conf->get_config_file_contents();
		$this->fs->put_contents(
			trailingslashit( WPMU_PLUGIN_DIR ) . FATALFLOW_CFG_FILE,
			$config_content
		);

		// 3. Deploy the self-contained logic file (static copy).
		$this->fs->deploy_mu_plugin();

		// 4. Generate and write the db-error drop-in.
		$dropin_content = $this->conf->get_db_error_dropin_contents();
		$this->fs->put_contents(
			trailingslashit( WP_CONTENT_DIR ) . 'db-error.php',
			$dropin_content
		);

		// 5. Handle wp-config.php injection.
		$config_path = \Amin\Fatal_Flow\Utils\Utils::find_wp_config();
		if ( $config_path && is_readable( $config_path ) ) {
			$current_val = file_get_contents( $config_path );
			$new_val     = $this->conf->get_wp_config_modified_content( $current_val );

			// Only write if the generator actually modified the string.
			if ( $new_val && $new_val !== $current_val ) {
				$this->fs->put_contents( $config_path, $new_val );
			}
		}

		flush_rewrite_rules();
	}

	/**
	 * Runs on plugin deactivation.
	 *
	 * Clean up the deployed files. We use specific markers to ensure we only
	 * delete files created by this plugin.
	 */
	public function deactivate(): void {
		$this->fs->remove_mu_plugin();
		$this->fs->remove_config_file();

		// 3. Remove the db-error.php drop-in (Only if it's ours).
		$dropin_path = trailingslashit( WP_CONTENT_DIR ) . 'db-error.php';
		$this->fs->remove_file( $dropin_path, 'FatalFlow' );

		flush_rewrite_rules();
	}

	public function handle_preview_request() {

		// 1. Basic checks
		if ( ! isset( $_GET['fatalflow_preview'] ) || '1' !== $_GET['fatalflow_preview'] ) {
			return;
		}

		// 2. Permission check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'fatalflow' ) );
		}

		// 3. Security Nonce check
		if (
		! isset( $_GET['_wpnonce'] ) ||
		! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ),
			'fatalflow_preview_action'
		)
		) {
			wp_die( esc_html__( 'Security check failed. Please refresh the settings page.', 'fatalflow' ) );
		}

		// 4. Force a clean buffer
		if ( ob_get_level() ) {
			ob_end_clean();
		}

		// 5. Load the Logic and RENDER
		$mu_path = WPMU_PLUGIN_DIR . '/' . FATALFLOW_MU_FILE;

		if ( file_exists( $mu_path ) ) {
			require_once $mu_path;
			if ( function_exists( 'fatalflow_render_ui' ) ) {
				fatalflow_render_ui( true );
				exit;
			}
		} else {
			wp_die( esc_html__( 'CoreGuard Logic file not found. Please deactivate and reactivate the plugin.', 'fatalflow' ) );
		}
	}
}

// Kick off — after plugins_loaded is too late for hooks like admin_menu,
// so we init immediately (class handles its own timing internally).
Fatal_Flow::instance();
