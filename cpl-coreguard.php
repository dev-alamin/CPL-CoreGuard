<?php
/**
 * Plugin Name:       CPL CoreGuard
 * Plugin URI:        https://github.com/alamin/cpl-coreguard
 * Description:       Enterprise-grade instant recovery UI for WordPress fatal errors and database failures. Deploys a glassmorphism maintenance screen via MU-plugin and db-error drop-in.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Al Amin
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cpl-coreguard
 * Domain Path:       /languages
 */

// Abort if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevent double-loading (e.g. if somehow symlinked into mu-plugins).
if ( defined( 'CPL_COREGUARD_LOADED' ) ) {
	return;
}
define( 'CPL_COREGUARD_LOADED', true );

// Plugin constants.
define( 'CPL_COREGUARD_VERSION',   '1.1.0' );
define( 'CPL_COREGUARD_FILE',      __FILE__ );
define( 'CPL_COREGUARD_DIR',       plugin_dir_path( __FILE__ ) );
define( 'CPL_COREGUARD_URL',       plugin_dir_url( __FILE__ ) );
define( 'CPL_COREGUARD_MU_FILE',   'cpl-coreguard-logic.php' );
define( 'CPL_COREGUARD_CFG_FILE',  'cpl-coreguard-config.php' );

/**
 * Main plugin class — singleton.
 */
final class CPL_CoreGuard {

	/** @var CPL_CoreGuard|null */
	private static $instance = null;

	/** Marker string used to identify our wp-config.php injection. */
	private const WP_CONFIG_MARKER = '/* CPL CoreGuard v1 */';

	// -------------------------------------------------------------------------
	// Bootstrap
	// -------------------------------------------------------------------------

	/**
	 * Returns (and lazily creates) the single instance.
	 *
	 * @return CPL_CoreGuard
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Private constructor — use ::instance(). */
	private function __construct() {
		register_activation_hook( CPL_COREGUARD_FILE,   [ $this, 'activate' ] );
		register_deactivation_hook( CPL_COREGUARD_FILE, [ $this, 'deactivate' ] );

		if ( is_admin() ) {
			require_once CPL_COREGUARD_DIR . 'src/class-cpl-settings.php';
			CPL_CoreGuard_Settings::instance();
		}
	}

	// -------------------------------------------------------------------------
	// Activation / Deactivation
	// -------------------------------------------------------------------------

	/**
	 * Runs on plugin activation.
	 *
	 * Hooked via register_activation_hook() so it receives the correct
	 * filesystem context. We keep each step independent so partial
	 * failures don't corrupt the environment.
	 */
	public function activate(): void {
		$this->maybe_create_mu_dir();
		$this->write_config_file();
		$this->deploy_mu_plugin();
		$this->deploy_db_error_dropin();
		$this->inject_wp_config();

		// Flush rewrite rules just in case.
		flush_rewrite_rules();
	}

	/**
	 * Runs on plugin deactivation.
	 *
	 * We intentionally leave the wp-config.php injection in place —
	 * the snippet is a single file_exists() guard with no runtime cost
	 * when the MU plugin is absent. This mirrors the approach used by
	 * Wordfence, WP Rocket, and other mainstream plugins. Removing
	 * wp-config.php during deactivation is error-prone on cached or
	 * permission-restricted environments; on re-activation the marker
	 * check prevents duplicate injection.
	 */
	public function deactivate(): void {
		$this->remove_mu_plugin();
		$this->remove_db_error_dropin();
		$this->remove_config_file();

		flush_rewrite_rules();
	}

	// -------------------------------------------------------------------------
	// Filesystem helpers
	// -------------------------------------------------------------------------

	/**
	 * Creates the mu-plugins directory (and an index.php silence file)
	 * if it doesn't exist yet.
	 */
	private function maybe_create_mu_dir(): void {
		if ( is_dir( WPMU_PLUGIN_DIR ) ) {
			return;
		}

		// wp_mkdir_p returns true on success or if directory already exists.
		if ( wp_mkdir_p( WPMU_PLUGIN_DIR ) ) {
			// Silence directory listing.
			file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				trailingslashit( WPMU_PLUGIN_DIR ) . 'index.php',
				"<?php // Silence is golden.\n"
			);
		}
	}

	/**
	 * Generates and writes the static config file into mu-plugins.
	 * Called both on activation and whenever settings are saved.
	 *
	 * @return bool True on success.
	 */
	public function write_config_file(): bool {
		$path = trailingslashit( WPMU_PLUGIN_DIR ) . CPL_COREGUARD_CFG_FILE;

		$name    = get_option( 'cpl_site_name',  get_bloginfo( 'name' ) );
		$color   = get_option( 'cpl_brand_color', '#38bdf8' );
		$message = get_option( 'cpl_maint_msg',   __( 'Brief technical update in progress.', 'cpl-coreguard' ) );
		$locale  = get_locale();

		// Strict validation.
		$name    = sanitize_text_field( $name );
		$color   = $this->sanitize_hex_color( $color );
		$message = sanitize_textarea_field( $message );
		$locale  = preg_replace( '/[^a-zA-Z_-]/', '', $locale );

		$icon_url = esc_url_raw( get_option( 'cpl_site_icon_url', '' ) );

		$php  = "<?php\n";
		$php .= "/** Generated by CPL CoreGuard " . CPL_COREGUARD_VERSION . " — do not edit manually. */\n";
		$php .= "defined( 'ABSPATH' ) || exit;\n\n";
		$php .= "define( 'CPL_SITE_NAME',    '" . addslashes( $name )    . "' );\n";
		$php .= "define( 'CPL_BRAND_COLOR',  '" . addslashes( $color )   . "' );\n";
		$php .= "define( 'CPL_MAINT_MSG',    '" . addslashes( $message ) . "' );\n";
		$php .= "define( 'CPL_LOCALE',       '" . addslashes( $locale )  . "' );\n";
		$php .= "define( 'CPL_SITE_ICON_URL','" . addslashes( $icon_url ). "' );\n";

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return false !== file_put_contents( $path, $php, LOCK_EX );
	}

	/**
	 * Copies the self-contained logic file into mu-plugins.
	 *
	 * @return bool
	 */
	private function deploy_mu_plugin(): bool {
		$source = CPL_COREGUARD_DIR . 'src/shield-logic.php';
		$dest   = trailingslashit( WPMU_PLUGIN_DIR ) . CPL_COREGUARD_MU_FILE;

		if ( ! file_exists( $source ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
		return copy( $source, $dest );
	}

	/**
	 * Writes the db-error.php drop-in.
	 * Uses __DIR__ paths so it works without ABSPATH being defined at
	 * the time db-error.php is loaded (i.e. during a DB failure).
	 *
	 * @return bool
	 */
	private function deploy_db_error_dropin(): bool {
		$dest    = trailingslashit( WP_CONTENT_DIR ) . 'db-error.php';
		$mu_path = trailingslashit( WPMU_PLUGIN_DIR ) . CPL_COREGUARD_MU_FILE;

		// Escape single quotes for use inside the heredoc string literal.
		$mu_path_esc = addslashes( $mu_path );

		$code  = "<?php\n";
		$code .= "/**\n";
		$code .= " * WordPress DB Error drop-in.\n";
		$code .= " * Generated by CPL CoreGuard " . CPL_COREGUARD_VERSION . ".\n";
		$code .= " * Do not edit manually — deactivate/reactivate the plugin to regenerate.\n";
		$code .= " */\n\n";
		$code .= "if ( file_exists( '{$mu_path_esc}' ) ) {\n";
		$code .= "    require_once '{$mu_path_esc}';\n";
		$code .= "    if ( function_exists( 'cpl_render_ui' ) ) {\n";
		$code .= "        cpl_render_ui();\n";
		$code .= "    }\n";
		$code .= "}\n";

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return false !== file_put_contents( $dest, $code, LOCK_EX );
	}

	/**
	 * Injects a minimal require_once block into wp-config.php so the
	 * MU plugin catches PHP fatal errors that occur before MU plugins
	 * are normally loaded.
	 *
	 * Injection is idempotent — safe to call multiple times.
	 */
	private function inject_wp_config(): void {
		$config_path = $this->find_wp_config();
		if ( ! $config_path || ! wp_is_writable( $config_path ) ) {
			return;
		}

		$content = file_get_contents( $config_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $content ) {
			return;
		}

		// Already injected?
		if ( str_contains( $content, self::WP_CONFIG_MARKER ) ) {
			return;
		}

		$mu_file_esc = addslashes( trailingslashit( WPMU_PLUGIN_DIR ) . CPL_COREGUARD_MU_FILE );

		$snippet  = "\n/* CPL CoreGuard v1 */\n";
		$snippet .= "if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {\n";
		$snippet .= "    if ( is_readable( __DIR__ . '/wp-content/mu-plugins/" . CPL_COREGUARD_MU_FILE . "' ) ) {\n";
		$snippet .= "        require_once __DIR__ . '/wp-content/mu-plugins/" . CPL_COREGUARD_MU_FILE . "';\n";
		$snippet .= "    }\n";
		$snippet .= "}\n";

		// Insert immediately after the opening <?php tag.
		$new_content = preg_replace( '/^(<\?php\s)/i', '$1' . $snippet, $content, 1 );
		if ( null === $new_content || $new_content === $content ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $config_path, $new_content, LOCK_EX );
	}

	/**
	 * Removes the MU plugin file.
	 */
	private function remove_mu_plugin(): void {
		$path = trailingslashit( WPMU_PLUGIN_DIR ) . CPL_COREGUARD_MU_FILE;
		if ( file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Removes the db-error.php drop-in only if we wrote it.
	 */
	private function remove_db_error_dropin(): void {
		$path = trailingslashit( WP_CONTENT_DIR ) . 'db-error.php';
		if ( ! file_exists( $path ) ) {
			return;
		}
		// Only delete it if our marker is present.
		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false !== $contents && str_contains( $contents, 'CPL CoreGuard' ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Strips the injected block from wp-config.php.
	 */
	private function clean_wp_config(): void {
		$config_path = $this->find_wp_config();
		if ( ! $config_path || ! wp_is_writable( $config_path ) ) {
			return;
		}

		$content = file_get_contents( $config_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $content || ! str_contains( $content, self::WP_CONFIG_MARKER ) ) {
			return;
		}

		// Remove marker line through the closing brace + newline.
		$pattern     = '/\n' . preg_quote( self::WP_CONFIG_MARKER, '/' ) . '.*?}\n/s';
		$new_content = preg_replace( $pattern, '', $content );
		if ( null === $new_content ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $config_path, $new_content, LOCK_EX );
	}

	/**
	 * Removes the generated config file.
	 */
	private function remove_config_file(): void {
		$path = trailingslashit( WPMU_PLUGIN_DIR ) . CPL_COREGUARD_CFG_FILE;
		if ( file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	// -------------------------------------------------------------------------
	// Utilities
	// -------------------------------------------------------------------------

	/**
	 * Locates wp-config.php — handles the case where it lives one level
	 * above ABSPATH (standard hardened setup).
	 *
	 * @return string|null Absolute path, or null if not found / not readable.
	 */
	private function find_wp_config(): ?string {
		$candidates = [
			ABSPATH . 'wp-config.php',
			dirname( ABSPATH ) . '/wp-config.php',
		];
		foreach ( $candidates as $candidate ) {
			if ( file_exists( $candidate ) && is_readable( $candidate ) ) {
				return $candidate;
			}
		}
		return null;
	}

	/**
	 * Sanitizes a hex color string to exactly #RRGGBB.
	 *
	 * @param  string $color Raw input.
	 * @return string        Sanitized color or default.
	 */
	private function sanitize_hex_color( string $color ): string {
		$color = trim( $color );
		if ( preg_match( '/^#([0-9a-fA-F]{6})$/', $color ) ) {
			return $color;
		}
		// Expand #RGB shorthand.
		if ( preg_match( '/^#([0-9a-fA-F])([0-9a-fA-F])([0-9a-fA-F])$/', $color, $m ) ) {
			return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
		}
		return '#38bdf8';
	}

}

// Kick off — after plugins_loaded is too late for hooks like admin_menu,
// so we init immediately (class handles its own timing internally).
CPL_CoreGuard::instance();