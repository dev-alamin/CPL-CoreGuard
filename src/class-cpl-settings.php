<?php
/**
 * CPL CoreGuard — Admin Settings Page.
 *
 * @package CplCoreGuard
 */

// Abort if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the CPL CoreGuard settings page.
 */
final class CPL_CoreGuard_Settings {

	/** @var CPL_CoreGuard_Settings|null */
	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	// -------------------------------------------------------------------------
	// WordPress hooks
	// -------------------------------------------------------------------------

	/** Register the settings sub-page under Settings. */
	public function add_menu(): void {
		add_options_page(
			__( 'CPL CoreGuard', 'cpl-coreguard' ),
			__( 'CPL CoreGuard', 'cpl-coreguard' ),
			'manage_options',
			'cpl-coreguard',
			array( $this, 'render_page' )
		);
	}

	/** Register settings, sections, and fields via the Settings API. */
	public function register_settings(): void {
		// Register options with sanitize callbacks.
		register_setting(
			'cpl_coreguard_group',
			'cpl_site_name',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
		register_setting(
			'cpl_coreguard_group',
			'cpl_brand_color',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_hex_color' ),
				'default'           => '#38bdf8',
			)
		);
		register_setting(
			'cpl_coreguard_group',
			'cpl_maint_msg',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => '',
			)
		);
		register_setting(
			'cpl_coreguard_group',
			'cpl_site_icon_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'default'           => '',
			)
		);

		// Sync the static config file whenever any option changes.
		foreach ( array( 'cpl_site_name', 'cpl_brand_color', 'cpl_maint_msg', 'cpl_site_icon_url' ) as $opt ) {
			add_action( "update_option_{$opt}", array( $this, 'sync_config' ) );
			add_action( "add_option_{$opt}", array( $this, 'sync_config' ) );
		}

		// Section + fields.
		add_settings_section(
			'cpl_main_section',
			'',   // No visible heading needed — we render our own UI.
			'__return_false',
			'cpl-coreguard'
		);

		add_settings_field(
			'cpl_site_name',
			__( 'Site / Brand Name', 'cpl-coreguard' ),
			array( $this, 'field_site_name' ),
			'cpl-coreguard',
			'cpl_main_section'
		);

		add_settings_field(
			'cpl_brand_color',
			__( 'Brand Color', 'cpl-coreguard' ),
			array( $this, 'field_brand_color' ),
			'cpl-coreguard',
			'cpl_main_section'
		);

		add_settings_field(
			'cpl_site_icon_url',
			__( 'Site Icon URL', 'cpl-coreguard' ),
			array( $this, 'field_site_icon' ),
			'cpl-coreguard',
			'cpl_main_section'
		);

		add_settings_field(
			'cpl_maint_msg',
			__( 'Maintenance Message', 'cpl-coreguard' ),
			array( $this, 'field_maint_msg' ),
			'cpl-coreguard',
			'cpl_main_section'
		);
	}

	/** Enqueue a tiny bit of inline CSS for the settings page only. */
	public function enqueue_assets( string $hook ): void {
		if ( 'settings_page_cpl-coreguard' !== $hook ) {
			return;
		}
		// Minimal admin styles inline — no external files required.
		$css = '
		.cpl-admin-wrap { max-width: 720px; }
		.cpl-admin-wrap .form-table th { width: 200px; }
		.cpl-admin-wrap .description { color: #646970; font-style: italic; }
		.cpl-color-preview {
			display: inline-block; width: 28px; height: 28px;
			border-radius: 6px; vertical-align: middle; margin-left: 8px;
			border: 1px solid rgba(0,0,0,.15); transition: background .2s;
		}
		';
		wp_register_style( 'cpl-admin', false, array(), CPL_COREGUARD_VERSION );
		wp_enqueue_style( 'cpl-admin' );
		wp_add_inline_style( 'cpl-admin', $css );

		// Tiny JS to live-preview the color swatch.
		$js = '
		document.addEventListener("DOMContentLoaded", function () {
			var picker  = document.getElementById("cpl_brand_color");
			var preview = document.getElementById("cpl_color_preview");
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

	public function field_site_name(): void {
		printf(
			'<input type="text" id="cpl_site_name" name="cpl_site_name" class="regular-text" value="%s" />',
			esc_attr( get_option( 'cpl_site_name', get_bloginfo( 'name' ) ) )
		);
		echo '<p class="description">' . esc_html__( 'Displayed on the maintenance screen. Defaults to your site title.', 'cpl-coreguard' ) . '</p>';
	}

	public function field_brand_color(): void {
		$color = esc_attr( get_option( 'cpl_brand_color', '#38bdf8' ) );
		printf(
			'<input type="color" id="cpl_brand_color" name="cpl_brand_color" value="%s" />',
			$color // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
		printf(
			'<span id="cpl_color_preview" class="cpl-color-preview" style="background:%s;" aria-hidden="true"></span>',
			$color // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
		echo '<p class="description">' . esc_html__( 'Accent color used in the recovery UI.', 'cpl-coreguard' ) . '</p>';
	}

	public function field_site_icon(): void {
		$url = esc_url( get_option( 'cpl_site_icon_url', '' ) );
		printf(
			'<input type="url" id="cpl_site_icon_url" name="cpl_site_icon_url" class="regular-text" value="%s" placeholder="https://example.com/icon.png" />',
			$url // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
		echo '<p class="description">' . esc_html__( 'Full URL to a PNG/SVG icon (recommended: 512×512 px). Shown on the recovery screen. Leave blank to use the shield icon.', 'cpl-coreguard' ) . '</p>';
	}

	public function field_maint_msg(): void {
		printf(
			'<textarea id="cpl_maint_msg" name="cpl_maint_msg" class="large-text" rows="3">%s</textarea>',
			esc_textarea( get_option( 'cpl_maint_msg', __( 'Brief technical update in progress.', 'cpl-coreguard' ) ) )
		);
		echo '<p class="description">' . esc_html__( 'Short message shown beneath the heading on the recovery screen.', 'cpl-coreguard' ) . '</p>';
	}

	// -------------------------------------------------------------------------
	// Page renderer
	// -------------------------------------------------------------------------

	public function render_page(): void {
		// Generate the secure preview URL
		$preview_url = wp_nonce_url(
			admin_url( 'options-general.php?page=cpl-coreguard&cpl_preview=1' ),
			'cpl_preview_action'
		);

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'cpl-coreguard' ) );
		}
		?>
		<div class="wrap cpl-admin-wrap">
			<h1>
				<?php
				echo '<svg style="vertical-align:middle;margin-right:8px;" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>';
				esc_html_e( 'CPL CoreGuard', 'cpl-coreguard' );
				?>
			</h1>
			<p><?php esc_html_e( 'Configure the brand and messaging shown on the recovery screen during fatal errors or database failures.', 'cpl-coreguard' ); ?></p>

			<?php settings_errors( 'cpl_coreguard_group' ); ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'cpl_coreguard_group' );
				do_settings_sections( 'cpl-coreguard' );
				submit_button( __( 'Save &amp; Sync Configuration', 'cpl-coreguard' ) );
				?>
			</form>
			<a href="<?php echo esc_url( $preview_url ); ?>" 
			target="_blank" 
			class="button button-secondary">
				<?php esc_html_e( 'Live Preview Template', 'cpl-coreguard' ); ?>
			</a>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Callbacks
	// -------------------------------------------------------------------------

	/**
	 * Called whenever a CPL CoreGuard option changes — rewrites the static config.
	 */
	public function sync_config(): void {
		CPL_CoreGuard::instance()->write_config_file();
	}

	/**
	 * Sanitizes a hex color. Rejects invalid input silently, returning default.
	 *
	 * @param  mixed $raw
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