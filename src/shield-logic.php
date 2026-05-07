<?php
/**
 * FatalFlow — MU-Plugin Logic.
 *
 * This file is intentionally self-contained and dependency-free.
 * It must work even when WordPress core has not fully loaded
 * (e.g. when called from db-error.php or via the wp-config.php
 * shutdown handler, before ABSPATH is defined).
 *
 * Deployed to: wp-content/mu-plugins/fatalflow-logic.php
 * Config at:   wp-content/mu-plugins/fatalflow-config.php
 *
 * @package Fatal_Flow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevent direct HTTP access. CLI / db-error / shutdown contexts
// won't have a web server REQUEST_METHOD, so this is safe.
if ( isset( $_SERVER['REQUEST_METHOD'] ) && ! defined( 'ABSPATH' ) && ! defined( 'FATALFLOW_LOADED' ) ) {
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

// Prevent double-loading (e.g. if somehow symlinked into mu-plugins).
if ( defined( 'FATALFLOW_LOADED' ) ) {
	return;
}
define( 'FATALFLOW_LOADED', true );

// ─────────────────────────────────────────────────────────────────────────────
// 1. Load static config (written by the plugin on activation / settings save).
// ─────────────────────────────────────────────────────────────────────────────
$_fatalflow_cfg = __DIR__ . '/fatalflow-config.php'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
if ( file_exists( $_fatalflow_cfg ) && is_readable( $_fatalflow_cfg ) ) {
	include_once $_fatalflow_cfg;
}
unset( $_fatalflow_cfg );

// ─────────────────────────────────────────────────────────────────────────────
// 2. Register the shutdown handler for PHP fatal errors.
// Runs only when invoked from wp-config.php (i.e. very early in the
// request lifecycle, before normal MU-plugin loading).
// ─────────────────────────────────────────────────────────────────────────────


if ( ! function_exists( 'fatalflow_register_fatal_handler' ) ) {
	/**
	 * The main function that will catch and handle fatal error.
	 *
	 * The PHP built in func to handle fatal error.
	 * It is not top level but lower level function to handle
	 * UI for brand and SEO.
	 *
	 * @return void
	 */
	function fatalflow_register_fatal_handler(): void { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		// Start output buffering so we can discard any partial output.
		if ( ob_get_level() === 0 ) {
			ob_start();
		}

		register_shutdown_function(
			static function (): void {
				$error = error_get_last();

				// Only intercept true fatals — ignore notices, warnings, etc.
				$fatal_types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR );

				if ( ! $error || ! in_array( $error['type'], $fatal_types, true ) ) {
					return;
				}

				// Clear any output already sent (notices, partial markup, etc.).
				while ( ob_get_level() > 0 ) {
					ob_end_clean();
				}

				// WP debug log integration — write the error before we override output.
				if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					$log = sprintf(
						'[FatalFlow] Fatal intercepted — %s in %s on line %d',
						$error['message'],
						$error['file'],
						$error['line']
					);
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( $log );
				}

				fatalflow_send_headers( 503 );
				fatalflow_render_ui();
			}
		);
	}

	fatalflow_register_fatal_handler();
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. Send safe HTTP headers.
// ─────────────────────────────────────────────────────────────────────────────
if ( ! function_exists( 'fatalflow_send_headers' ) ) {

	/**
	 * Emits appropriate HTTP headers.
	 *
	 * @param int $status HTTP status code (503 or 500).
	 */
	function fatalflow_send_headers( int $status = 503 ): void { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		if ( headers_sent() ) {
			return;
		}

		$messages = array(
			500 => 'Internal Server Error',
			503 => 'Service Temporarily Unavailable',
		);
		$text     = $messages[ $status ] ?? 'Service Temporarily Unavailable';

		header( "HTTP/1.1 {$status} {$text}", true, $status );
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'Retry-After: 3600' );
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Cache-Control: no-store, no-cache, must-revalidate', true );
	}
}

/**
 * Late Escaping Fallbacks for Fatal Error States
 */
if ( ! function_exists( 'fatalflow_esc_attr' ) ) {
	function fatalflow_esc_attr( $value ) {
		return function_exists( 'esc_attr' ) ? esc_attr( $value ) : htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( 'fatalflow_esc_html' ) ) {
	function fatalflow_esc_html( $value ) {
		return function_exists( 'esc_html' ) ? esc_html( $value ) : htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( 'fatalflow_esc_url' ) ) {
	function fatalflow_esc_url( $value ) {

		if ( function_exists( 'esc_url' ) ) {
			return esc_url( $value );
		}

		$value = filter_var( (string) $value, FILTER_SANITIZE_URL );

		if ( ! preg_match( '/^https?:\/\//i', $value ) ) {
			return '';
		}

		return htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( 'fatalflow_strip_tags' ) ) {
	function fatalflow_strip_tags( $string ) {
		if ( function_exists( 'wp_strip_all_tags' ) ) {
			return wp_strip_all_tags( $string );
		}
		// PHP Native fallback: remove tags and trim
		return trim( strip_tags( $string ) );
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. Main UI renderer — called by fatal handler AND by db-error.php drop-in.
// ─────────────────────────────────────────────────────────────────────────────
if ( ! function_exists( 'fatalflow_render_ui' ) ) {

	/**
	 * Outputs a full, standalone HTML maintenance/error page and exits.
	 * Zero WordPress dependencies — works when WP core is completely broken.
	 *
	 * @param bool $is_preview Whether this is a manual preview or a real error.
	 */
	function fatalflow_render_ui( $is_preview = false ): void { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		// Resolve config with safe fallbacks.
		$site_name = defined( 'FATALFLOW_SITE_NAME' ) ? FATALFLOW_SITE_NAME : 'Our Services';
		$color     = defined( 'FATALFLOW_BRAND_COLOR' ) ? FATALFLOW_BRAND_COLOR : '#38bdf8';
		$message   = defined( 'FATALFLOW_MAINT_MSG' ) ? FATALFLOW_MAINT_MSG : 'Brief technical update in progress.';
		$lang      = defined( 'FATALFLOW_LOCALE' ) ? FATALFLOW_LOCALE : 'en';
		$icon_url  = defined( 'FATALFLOW_SITE_ICON_URL' ) ? FATALFLOW_SITE_ICON_URL : '';

		// Normalize Lang
		$html_lang = strtolower( str_replace( '_', '-', substr( $lang, 0, 5 ) ) );

		if ( ! preg_match( '/^#[0-9a-fA-F]{6}$/', $color ) ) {
			$color = '#38bdf8';
		}
		// Derive a slightly darker shade for the gradient (simple hex math).
		list( $r, $g, $b ) = sscanf( $color, '#%02x%02x%02x' );
		$dark_color        = sprintf(
			'#%02x%02x%02x',
			max( 0, (int) $r - 40 ),
			max( 0, (int) $g - 40 ),
			max( 0, (int) $b - 40 )
		);

		if ( ! $is_preview && ! headers_sent() ) {
			fatalflow_send_headers( 503 );
		}

		// phpcs:disable
		?>
<!DOCTYPE html>
<html lang="<?php echo fatalflow_esc_attr( $html_lang ); ?>" dir="ltr">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo fatalflow_esc_html( $site_name ); ?> &mdash; Maintenance</title>
	<?php if ( ! empty( $icon_url ) ) : ?>
    <link rel="icon" type="image/png" href="<?php echo fatalflow_esc_url( $icon_url ); ?>">
    <?php endif; ?>
	<?php
	// 1. Store CSS in a variable and apply late escaping to dynamic values
	$fatalflow_css = "
		*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
		:root {
			--brand:       " . fatalflow_esc_attr( $color ) . ";
			--brand-dark:  " . fatalflow_esc_attr( $dark_color ) . ";
			--surface:     #0b1120;
			--surface-2:   #111827;
			--glass:       rgba(255,255,255,.04);
			--glass-border:rgba(255,255,255,.10);
			--text-1:      #f0f4ff;
			--text-2:      #8a9abf;
			--radius-card: 2rem;
			--radius-pill: 100px;
		}
		html, body { min-height: 100%; background-color: var(--surface); }
		body {
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
			display: flex; align-items: center; justify-content: center; min-height: 100svh; padding: 1.5rem; color: var(--text-1);
			background-image:
				radial-gradient(ellipse 80% 60% at 50% -10%, " . fatalflow_esc_attr( $color ) . "28, transparent),
				radial-gradient(ellipse 60% 40% at 80%  90%, " . fatalflow_esc_attr( $dark_color ) . "18, transparent),
				url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='4' stitchTiles='stitch'/%3E%3CfeColorMatrix type='saturate' values='0'/%3E%3C/filter%3E%3Crect width='200' height='200' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E\");
			background-attachment: fixed; -webkit-font-smoothing: antialiased;
		}
		.zs-card {
			position: relative; width: 100%; max-width: 520px; padding: clamp(2.5rem, 8vw, 4rem);
			background: var(--glass); border: 1px solid var(--glass-border); border-radius: var(--radius-card);
			text-align: center; overflow: hidden; animation: zs-rise .6s cubic-bezier(.22,.61,.36,1) both;
			box-shadow: 0 0 0 1px rgba(255,255,255,.04) inset, 0 2px 4px rgba(0,0,0,.4), 0 20px 60px rgba(0,0,0,.5);
		}
		.zs-card::before {
			content: ''; position: absolute; inset: 0; bottom: auto; height: 1px;
			background: linear-gradient(90deg, transparent 0%, " . fatalflow_esc_attr( $color ) . "55 30%, " . fatalflow_esc_attr( $color ) . "cc 50%, " . fatalflow_esc_attr( $color ) . "55 70%, transparent 100%);
		}
		.zs-badge {
			display: inline-flex; align-items: center; gap: .4rem; font-size: .65rem; font-weight: 700; letter-spacing: .15em;
			text-transform: uppercase; color: var(--brand); background: " . fatalflow_esc_attr( $color ) . "18;
			border: 1px solid " . fatalflow_esc_attr( $color ) . "33; padding: .35rem 1rem; border-radius: var(--radius-pill); margin-bottom: 2.25rem;
		}
		.zs-preview-badge { 
			position: fixed; top: 10px; right: 10px; background: #ef4444; 
			color: white; padding: 5px 15px; 
			border-radius: 20px; font-size: 12px; z-index: 9999;
		}
		.zs-badge-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--brand); animation: zs-pulse 2s ease-in-out infinite; }
		.zs-icon-wrap {
			width: 72px; height: 72px; margin: 0 auto 2rem; border-radius: 1.25rem; display: flex; align-items: center; justify-content: center;
			color: var(--brand); background: " . fatalflow_esc_attr( $color ) . "16; border: 1px solid " . fatalflow_esc_attr( $color ) . "30;
			box-shadow: 0 0 30px " . fatalflow_esc_attr( $color ) . "28;
		}
		.zs-heading { font-family: Georgia, 'Times New Roman', serif; font-size: clamp(1.6rem, 5vw, 2.2rem); font-weight: 400; line-height: 1.15; color: var(--text-1); margin-bottom: .9rem; }
		.zs-sub { font-size: 1rem; line-height: 1.75; color: var(--text-2); max-width: 38ch; margin: 0 auto 2.5rem; }
		.zs-progress { width: 100%; height: 3px; background: rgba(255,255,255,.07); border-radius: 4px; overflow: hidden; margin-bottom: 2.5rem; }
		.zs-progress-bar { height: 100%; width: 0%; border-radius: 4px; background: linear-gradient(90deg, var(--brand), var(--brand-dark)); animation: zs-progress 8s ease-in-out infinite; }
		.zs-btn {
			display: inline-flex; align-items: center; gap: .5rem; background: var(--brand); color: #fff; font-size: .95rem; font-weight: 600;
			padding: .85rem 2rem; border: none; border-radius: .85rem; cursor: pointer; text-decoration: none;
			box-shadow: 0 4px 20px " . fatalflow_esc_attr( $color ) . "55;
		}
		.zs-btn:hover { opacity: .9; transform: translateY(-1px); box-shadow: 0 8px 28px " . fatalflow_esc_attr( $color ) . "66; }
		.zs-status { margin-top: 2rem; font-size: .75rem; color: rgba(255,255,255,.2); }
		@keyframes zs-rise { from { opacity: 0; transform: translateY(20px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
		@keyframes zs-pulse { 0%, 100% { opacity: 1; } 50% { opacity: .3; } }
		@keyframes zs-progress { 0% { width: 0%; } 100% { width: 100%; } }
	";

	// 1. Determine the context
	$is_preview = isset( $_GET['fatalflow_preview'] );

	/**
	 * We only use the "WP way" if:
	 * 1. The functions exist.
	 * 2. It's NOT a preview.
	 * 3. We are currently in or after the 'wp_enqueue_scripts' hook.
	 */
	$can_enqueue = function_exists( 'wp_add_inline_style' ) && 
				! $is_preview && 
				did_action( 'wp_enqueue_scripts' );

	// 2. Handle output
	if ( $can_enqueue ) {
		wp_register_style( 'fatalflow-logic', false );
		wp_enqueue_style( 'fatalflow-logic' );
		wp_add_inline_style( 'fatalflow-logic', $fatalflow_css );
	} else {
		// This will catch Fatal Errors, Previews, and Early Loads
		$style_id = $is_preview ? ' id="fatalflow-preview-css"' : '';
		echo '<style' . $style_id . '>' . fatalflow_strip_tags( $fatalflow_css ) . '</style>';
	}
	?>
</head>
<body>

<main class="zs-card" role="main" aria-labelledby="zs-heading">
	<?php
	if ( $is_preview ) {
		echo '<div class="zs-preview-badge">' . fatalflow_esc_html( 'Preview Mode', 'fatalflow' ) . '</div>';
	}
	?>
	<div class="zs-badge" aria-label="Status: Maintenance in progress">
		<span class="zs-badge-dot" aria-hidden="true"></span>
		<?php echo fatalflow_esc_html( $site_name ); ?>
	</div>

	<div class="zs-icon-wrap" aria-hidden="true">
		<?php if ( '' !== $icon_url ) : ?>
		<img src="<?php echo fatalflow_esc_url( $icon_url ); ?>" alt="" width="40" height="40" style="border-radius:.5rem;object-fit:contain;">
		<?php else : ?>
		<svg width="34" height="34" viewBox="0 0 24 24" fill="none"
			stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
			<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
		</svg>
		<?php endif; ?>
	</div>

	<h1 id="zs-heading" class="zs-heading"> <?php fatalflow_esc_html( 'Systems stabilizing' ); ?></h1>

	<p class="zs-sub">
		<strong><?php echo fatalflow_esc_html( $site_name . ' is undergoing a brief technical update.'); ?></strong>
		<?php echo fatalflow_esc_html( $message ); ?>
	</p>

	<div class="zs-progress" role="progressbar" aria-label="Recovery in progress" aria-valuemin="0" aria-valuemax="100">
		<div class="zs-progress-bar"></div>
	</div>

	<button
		type="button"
		class="zs-btn"
		onclick="this.disabled=true;this.textContent='Checking\u2026';window.location.reload();"
		aria-label="Reload page to check if the site is back online">
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
			stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
			<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/>
			<path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
		</svg>
		<?php echo fatalflow_esc_html( 'Check Again' ); ?>
	</button>

	<p class="zs-status" aria-live="polite"><?php echo fatalflow_esc_html( '503 — Temporary outage' ); ?></p>

</main>

</body>
</html>
		<?php
		// phpcs:enable
		exit;
	}
}