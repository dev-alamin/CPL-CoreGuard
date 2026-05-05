<?php
namespace Amin\Fatal_Flow\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FatalFlow — Admin Settings Page.
 *
 * @package Fatal_Flow
 */


use Amin\Fatal_Flow\Core\File_System;
use Amin\Fatal_Flow\Core\Config_Generator;

/**
 * Admin Settings Page handler for FatalFlow.
 *
 * Responsible for:
 * - Registering plugin settings via the WordPress Settings API.
 * - Rendering the admin settings UI.
 * - Syncing configuration to a static file on option updates.
 * - Enqueueing minimal admin assets for UX enhancements.
 *
 * @package Fatal_Flow
 */
final class Settings {

	/**
	 * Filesystem handler instance.
	 *
	 * Used to write the generated configuration file.
	 *
	 * @var File_System
	 */
	private $fs;

	/**
	 * Configuration generator instance.
	 *
	 * Produces the static config file contents based on options.
	 *
	 * @var Config_Generator
	 */
	private $conf;

	/**
	 * Constructor.
	 *
	 * Wires dependencies and registers WordPress hooks.
	 *
	 * @param File_System      $fs   Filesystem abstraction instance.
	 * @param Config_Generator $conf Configuration generator instance.
	 */
	public function __construct( File_System $fs, Config_Generator $conf ) {
		$this->fs   = $fs;
		$this->conf = $conf;

		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	// -------------------------------------------------------------------------
	// WordPress hooks
	// -------------------------------------------------------------------------

	/**
	 * Registers the plugin settings page under "Settings".
	 *
	 * @return void
	 */
	public function add_menu(): void {
		add_options_page(
			__( 'FatalFlow', 'fatalflow' ),
			__( 'FatalFlow', 'fatalflow' ),
			'manage_options',
			'fatalflow',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Registers plugin settings, sections, and fields using the Settings API.
	 *
	 * Also attaches hooks to sync configuration whenever options change.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		// Register options with sanitize callbacks.
		register_setting(
			'fatalflow_coreguard_group',
			'fatalflow_site_name',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
		register_setting(
			'fatalflow_coreguard_group',
			'fatalflow_brand_color',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_hex_color' ),
				'default'           => '#38bdf8',
			)
		);
		register_setting(
			'fatalflow_coreguard_group',
			'fatalflow_maint_msg',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => '',
			)
		);
		register_setting(
			'fatalflow_coreguard_group',
			'fatalflow_site_icon_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'default'           => '',
			)
		);

		// Sync the static config file whenever any option changes.
		foreach ( array( 'fatalflow_site_name', 'fatalflow_brand_color', 'fatalflow_maint_msg', 'fatalflow_site_icon_url' ) as $opt ) {
			add_action( "update_option_{$opt}", array( $this, 'sync_config' ) );
			add_action( "add_option_{$opt}", array( $this, 'sync_config' ) );
		}

		// Section + fields.
		add_settings_section(
			'fatalflow_main_section',
			'',   // No visible heading needed — we render our own UI.
			'__return_false',
			'fatalflow'
		);

		add_settings_field(
			'fatalflow_site_name',
			__( 'Site / Brand Name', 'fatalflow' ),
			array( $this, 'field_site_name' ),
			'fatalflow',
			'fatalflow_main_section'
		);

		add_settings_field(
			'fatalflow_brand_color',
			__( 'Brand Color', 'fatalflow' ),
			array( $this, 'field_brand_color' ),
			'fatalflow',
			'fatalflow_main_section'
		);

		add_settings_field(
			'fatalflow_site_icon_url',
			__( 'Site Icon URL', 'fatalflow' ),
			array( $this, 'field_site_icon' ),
			'fatalflow',
			'fatalflow_main_section'
		);

		add_settings_field(
			'fatalflow_maint_msg',
			__( 'Maintenance Message', 'fatalflow' ),
			array( $this, 'field_maint_msg' ),
			'fatalflow',
			'fatalflow_main_section'
		);
	}

		/**
		 * Enqueues inline admin styles and scripts for the settings page.
		 *
		 * @param string $hook Current admin page hook suffix.
		 * @return void
		 */
	public function enqueue_assets( string $hook ): void {
		if ( 'settings_page_fatalflow' !== $hook ) {
			return;
		}
		// Minimal admin styles inline — no external files required.
		$css = '
		.fatalflow-admin-wrap { max-width: 720px; }
		.fatalflow-admin-wrap .form-table th { width: 200px; }
		.fatalflow-admin-wrap .description { color: #646970; font-style: italic; }
		.fatalflow-color-preview {
			display: inline-block; width: 28px; height: 28px;
			border-radius: 6px; vertical-align: middle; margin-left: 8px;
			border: 1px solid rgba(0,0,0,.15); transition: background .2s;
		}
		';
		wp_register_style( 'fatalflow-admin', false, array(), FATALFLOW_VERSION );
		wp_enqueue_style( 'fatalflow-admin' );
		wp_add_inline_style( 'fatalflow-admin', $css );

		// Tiny JS to live-preview the color swatch.
		$js = '
		document.addEventListener("DOMContentLoaded", function () {
			var picker  = document.getElementById("fatalflow_brand_color");
			var preview = document.getElementById("fatalflow_color_preview");
			if ( picker && preview ) {
				preview.style.background = picker.value;
				picker.addEventListener("input", function () {
					preview.style.background = this.value;
				});
			}
		});
		';
		wp_add_inline_script( 'jquery', $js );
	}

	// -------------------------------------------------------------------------
	// Field renderers
	// -------------------------------------------------------------------------

		/**
		 * Renders the Site Name field.
		 *
		 * @return void
		 */
	public function field_site_name(): void {
		printf(
			'<input type="text" id="fatalflow_site_name" name="fatalflow_site_name" class="regular-text" value="%s" />',
			esc_attr( get_option( 'fatalflow_site_name', get_bloginfo( 'name' ) ) )
		);
		echo '<p class="description">' . esc_html__( 'Displayed on the maintenance screen. Defaults to your site title.', 'fatalflow' ) . '</p>';
	}

			/**
			 * Renders the Brand Color picker field.
			 *
			 * Includes a live preview swatch.
			 *
			 * @return void
			 */
	public function field_brand_color(): void {
		$color = esc_attr( get_option( 'fatalflow_brand_color', '#38bdf8' ) );
		printf(
			'<input type="color" id="fatalflow_brand_color" name="fatalflow_brand_color" value="%s" />',
			$color // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
		printf(
			'<span id="fatalflow_color_preview" class="fatalflow-color-preview" style="background:%s;" aria-hidden="true"></span>',
			$color // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
		echo '<p class="description">' . esc_html__( 'Accent color used in the recovery UI.', 'fatalflow' ) . '</p>';
	}

		/**
		 * Renders the Site Icon URL field.
		 *
		 * @return void
		 */
	public function field_site_icon(): void {
		$url = esc_url( get_option( 'fatalflow_site_icon_url', '' ) );
		printf(
			'<input type="url" id="fatalflow_site_icon_url" name="fatalflow_site_icon_url" class="regular-text" value="%s" placeholder="https://example.com/icon.png" />',
			$url // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
		echo '<p class="description">' . esc_html__( 'Full URL to a PNG/SVG icon (recommended: 512×512 px). Shown on the recovery screen. Leave blank to use the shield icon.', 'fatalflow' ) . '</p>';
	}

		/**
		 * Renders the Maintenance Message textarea field.
		 *
		 * @return void
		 */
	public function field_maint_msg(): void {
		printf(
			'<textarea id="fatalflow_maint_msg" name="fatalflow_maint_msg" class="large-text" rows="3">%s</textarea>',
			esc_textarea( get_option( 'fatalflow_maint_msg', __( 'Brief technical update in progress.', 'fatalflow' ) ) )
		);
		echo '<p class="description">' . esc_html__( 'Short message shown beneath the heading on the recovery screen.', 'fatalflow' ) . '</p>';
	}

	// -------------------------------------------------------------------------
	// Page renderer
	// -------------------------------------------------------------------------

		/**
		 * Outputs the settings page HTML.
		 *
		 * Handles:
		 * - Capability checks
		 * - Settings form rendering
		 * - Preview URL generation
		 *
		 * @return void
		 */
	public function render_page(): void {
		// Generate the secure preview URL.
		$preview_url = wp_nonce_url(
			admin_url( 'options-general.php?page=fatalflow&fatalflow_preview=1' ),
			'fatalflow_preview_action'
		);

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'fatalflow' ) );
		}
		?>
		<div class="wrap fatalflow-admin-wrap">
			<h1>
				<?php
				echo '<svg style="vertical-align:middle;margin-right:8px;" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>';
				esc_html_e( 'FatalFlow', 'fatalflow' );
				?>
			</h1>
			<p><?php esc_html_e( 'Configure the brand and messaging shown on the recovery screen during fatal errors or database failures.', 'fatalflow' ); ?></p>

			<?php settings_errors( 'fatalflow_coreguard_group' ); ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'fatalflow_coreguard_group' );
				do_settings_sections( 'fatalflow' );
				submit_button( __( 'Save &amp; Sync Configuration', 'fatalflow' ) );
				?>
			</form>
			<a href="<?php echo esc_url( $preview_url ); ?>" 
			target="_blank" 
			class="button button-secondary">
				<?php esc_html_e( 'Live Preview Template', 'fatalflow' ); ?>
			</a>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Callbacks
	// -------------------------------------------------------------------------

	/**
	 * Synchronizes plugin options into a static configuration file.
	 *
	 * Triggered on option create/update hooks.
	 *
	 * @return void
	 */
	public function sync_config(): void {
		$this->fs->put_contents(
			trailingslashit( WPMU_PLUGIN_DIR ) . FATALFLOW_CFG_FILE,
			$this->conf->get_config_file_contents()
		);
	}

	/**
	 * Sanitizes a hex color. Rejects invalid input silently, returning default.
	 *
	 * @param  mixed $raw raw color code.
	 * @return string
	 */
	public function sanitize_hex_color( $raw ): string {
		$color = trim( (string) $raw );
		if ( preg_match( '/^#([0-9a-fA-F]{6})$/', $color ) ) {
			return $color;
		}
		if ( preg_match( '/^#([0-9a-fA-F])([0-9a-fA-F])([0-9a-fA-F])$/', $color, $m ) ) {
			return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
		}
		return '#38bdf8';
	}
}