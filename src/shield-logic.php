<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * CPL CoreGuard — MU-Plugin Logic.
 *
 * This file is intentionally self-contained and dependency-free.
 * It must work even when WordPress core has not fully loaded
 * (e.g. when called from db-error.php or via the wp-config.php
 * shutdown handler, before ABSPATH is defined).
 *
 * Deployed to: wp-content/mu-plugins/cpl-coreguard-logic.php
 * Config at:   wp-content/mu-plugins/cpl-coreguard-config.php
 *
 * @package CplCoreGuard
 */

// Prevent direct HTTP access. CLI / db-error / shutdown contexts
// won't have a web server REQUEST_METHOD, so this is safe.
if ( isset( $_SERVER['REQUEST_METHOD'] ) && ! defined( 'ABSPATH' ) && ! defined( 'CPL_COREGUARD_LOGIC_LOADED' ) ) {
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

// Hard guard against double-loading.
if ( defined( 'CPL_COREGUARD_LOGIC_LOADED' ) ) {
	return;
}
define( 'CPL_COREGUARD_LOGIC_LOADED', true );

// ─────────────────────────────────────────────────────────────────────────────
// 1. Load static config (written by the plugin on activation / settings save).
// ─────────────────────────────────────────────────────────────────────────────
$_cpl_cfg = __DIR__ . '/cpl-coreguard-config.php';
if ( file_exists( $_cpl_cfg ) && is_readable( $_cpl_cfg ) ) {
	include_once $_cpl_cfg;
}
unset( $_cpl_cfg );

// ─────────────────────────────────────────────────────────────────────────────
// 2. Register the shutdown handler for PHP fatal errors.
// Runs only when invoked from wp-config.php (i.e. very early in the
// request lifecycle, before normal MU-plugin loading).
// ─────────────────────────────────────────────────────────────────────────────
if ( ! function_exists( 'cpl_register_fatal_handler' ) ) {

	function cpl_register_fatal_handler(): void {
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
						'[CPL CoreGuard] Fatal intercepted — %s in %s on line %d',
						$error['message'],
						$error['file'],
						$error['line']
					);
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( $log );
				}

				cpl_send_headers( 503 );
				cpl_render_ui();
			}
		);
	}

	cpl_register_fatal_handler();
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. Send safe HTTP headers.
// ─────────────────────────────────────────────────────────────────────────────
if ( ! function_exists( 'cpl_send_headers' ) ) {

	/**
	 * Emits appropriate HTTP headers.
	 *
	 * @param int $status HTTP status code (503 or 500).
	 */
	function cpl_send_headers( int $status = 503 ): void {
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

// ─────────────────────────────────────────────────────────────────────────────
// 4. Main UI renderer — called by fatal handler AND by db-error.php drop-in.
// ─────────────────────────────────────────────────────────────────────────────
if ( ! function_exists( 'cpl_render_ui' ) ) {

	/**
	 * Outputs a full, standalone HTML maintenance/error page and exits.
	 * Zero WordPress dependencies — works when WP core is completely broken.
	 *
	 * @param bool $is_preview Whether this is a manual preview or a real error.
	 */
	function cpl_render_ui( $is_preview = false ): void {
		// Resolve config with safe fallbacks.
		$site_name = defined( 'CPL_SITE_NAME' ) ? CPL_SITE_NAME : 'Our Services';
		$color     = defined( 'CPL_BRAND_COLOR' ) ? CPL_BRAND_COLOR : '#38bdf8';
		$message   = defined( 'CPL_MAINT_MSG' ) ? CPL_MAINT_MSG : 'Brief technical update in progress.';
		$lang      = defined( 'CPL_LOCALE' ) ? CPL_LOCALE : 'en';
		$icon_url  = defined( 'CPL_SITE_ICON_URL' ) ? CPL_SITE_ICON_URL : '';

		// Strip locale region for <html lang> (e.g. "en_US" → "en").
		$html_lang = strtolower( str_replace( '_', '-', substr( $lang, 0, 5 ) ) );

		// Escape all dynamic values — we have no WP functions, so use native PHP.
		$esc_name = htmlspecialchars( $site_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
		$esc_msg  = htmlspecialchars( $message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
		$esc_lang = htmlspecialchars( $html_lang, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );

		// Validate icon URL — must be http/https or empty.
		$esc_icon = '';
		if ( '' !== $icon_url && preg_match( '/^https?:\/\//i', $icon_url ) ) {
			$esc_icon = htmlspecialchars( $icon_url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
		}

		// Color is already validated as #RRGGBB by the plugin sanitizer.
		// We re-validate here defensively before embedding in CSS.
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
			cpl_send_headers( 503 );
		}

		// phpcs:disable
		?>
<!DOCTYPE html>
<html lang="<?php echo $esc_lang; ?>" dir="ltr">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo $esc_name; ?> &mdash; Maintenance</title>
	<?php if ( '' !== $esc_icon ) : ?>
	<link rel="icon" type="image/png" href="<?php echo $esc_icon; ?>">
	<?php endif; ?>
	<style>
		/* ── Reset ────────────────────────────────── */
		*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

		/* ── Tokens ───────────────────────────────── */
		:root {
			--brand:       <?php echo $color; ?>;
			--brand-dark:  <?php echo $dark_color; ?>;
			--surface:     #0b1120;
			--surface-2:   #111827;
			--glass:       rgba(255,255,255,.04);
			--glass-border:rgba(255,255,255,.10);
			--text-1:      #f0f4ff;
			--text-2:      #8a9abf;
			--radius-card: 2rem;
			--radius-pill: 100px;
		}

		/* ── Base ─────────────────────────────────── */
		html, body {
			min-height: 100%;
			background-color: var(--surface);
		}

		body {
			font-family: 'DM Sans', 'Helvetica Neue', Arial, sans-serif;
			display: flex;
			align-items: center;
			justify-content: center;
			min-height: 100svh;
			padding: 1.5rem;
			color: var(--text-1);
			/* Layered noise + radial glows for depth */
			background-image:
				radial-gradient(ellipse 80% 60% at 50% -10%, <?php echo $color; ?>28, transparent),
				radial-gradient(ellipse 60% 40% at 80%  90%, <?php echo $dark_color; ?>18, transparent),
				url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='4' stitchTiles='stitch'/%3E%3CfeColorMatrix type='saturate' values='0'/%3E%3C/filter%3E%3Crect width='200' height='200' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
			background-attachment: fixed;
			-webkit-font-smoothing: antialiased;
		}

		/* ── Card ─────────────────────────────────── */
		.zs-card {
			position: relative;
			width: 100%;
			max-width: 520px;
			padding: clamp(2.5rem, 8vw, 4rem);
			background: var(--glass);
			border: 1px solid var(--glass-border);
			border-radius: var(--radius-card);
			text-align: center;
			overflow: hidden;
			/* Layered shadows for card lift */
			box-shadow:
				0 0 0 1px rgba(255,255,255,.04) inset,
				0 2px 4px rgba(0,0,0,.4),
				0 20px 60px rgba(0,0,0,.5);
			animation: zs-rise .6s cubic-bezier(.22,.61,.36,1) both;
		}

		/* Top-edge light strip */
		.zs-card::before {
			content: '';
			position: absolute;
			inset: 0; bottom: auto;
			height: 1px;
			background: linear-gradient(90deg,
				transparent 0%,
				<?php echo $color; ?>55 30%,
				<?php echo $color; ?>cc 50%,
				<?php echo $color; ?>55 70%,
				transparent 100%);
		}

		/* ── Badge ────────────────────────────────── */
		.zs-badge {
			display: inline-flex;
			align-items: center;
			gap: .4rem;
			font-size: .65rem;
			font-weight: 700;
			letter-spacing: .15em;
			text-transform: uppercase;
			color: var(--brand);
			background: <?php echo $color; ?>18;
			border: 1px solid <?php echo $color; ?>33;
			padding: .35rem 1rem;
			border-radius: var(--radius-pill);
			margin-bottom: 2.25rem;
		}
		.zs-badge-dot {
			width: 6px; height: 6px;
			border-radius: 50%;
			background: var(--brand);
			animation: zs-pulse 2s ease-in-out infinite;
		}

		/* ── Icon ─────────────────────────────────── */
		.zs-icon-wrap {
			width: 72px; height: 72px;
			margin: 0 auto 2rem;
			border-radius: 1.25rem;
			display: flex;
			align-items: center;
			justify-content: center;
			color: var(--brand);
			background: <?php echo $color; ?>16;
			border: 1px solid <?php echo $color; ?>30;
			box-shadow: 0 0 30px <?php echo $color; ?>28;
		}
		@media (prefers-reduced-motion: no-preference) {
			.zs-icon-wrap svg { animation: zs-float 4s ease-in-out infinite; }
		}

		/* ── Typography ───────────────────────────── */
		.zs-heading {
			font-family: 'DM Serif Display', Georgia, serif;
			font-size: clamp(1.6rem, 5vw, 2.2rem);
			font-weight: 400;
			line-height: 1.15;
			letter-spacing: -.03em;
			color: var(--text-1);
			margin-bottom: .9rem;
		}
		.zs-sub {
			font-size: 1rem;
			line-height: 1.75;
			color: var(--text-2);
			max-width: 38ch;
			margin: 0 auto 2.5rem;
		}
		.zs-sub strong { color: var(--text-1); font-weight: 500; }

		/* ── Progress bar ─────────────────────────── */
		.zs-progress {
			width: 100%;
			height: 3px;
			background: rgba(255,255,255,.07);
			border-radius: 4px;
			overflow: hidden;
			margin-bottom: 2.5rem;
		}
		.zs-progress-bar {
			height: 100%;
			width: 0%;
			border-radius: 4px;
			background: linear-gradient(90deg, var(--brand), var(--brand-dark));
			animation: zs-progress 8s ease-in-out infinite;
		}

		/* ── CTA button ───────────────────────────── */
		.zs-btn {
			display: inline-flex;
			align-items: center;
			gap: .5rem;
			background: var(--brand);
			color: #fff;
			font-family: inherit;
			font-size: .95rem;
			font-weight: 600;
			letter-spacing: .01em;
			padding: .85rem 2rem;
			border: none;
			border-radius: .85rem;
			cursor: pointer;
			text-decoration: none;
			transition: opacity .2s, transform .2s, box-shadow .2s;
			box-shadow: 0 4px 20px <?php echo $color; ?>55;
		}
		.zs-btn:hover  { opacity: .9; transform: translateY(-1px); box-shadow: 0 8px 28px <?php echo $color; ?>66; }
		.zs-btn:active { transform: translateY(0); opacity: 1; }
		.zs-btn:focus-visible { outline: 2px solid var(--brand); outline-offset: 4px; }

		/* ── Status line ──────────────────────────── */
		.zs-status {
			margin-top: 2rem;
			font-size: .75rem;
			color: rgba(255,255,255,.2);
			letter-spacing: .04em;
		}

		/* ── Keyframes ────────────────────────────── */
		@keyframes zs-rise {
			from { opacity: 0; transform: translateY(20px) scale(.98); }
			to   { opacity: 1; transform: translateY(0)   scale(1);    }
		}
		@keyframes zs-float {
			0%, 100% { transform: translateY(0px);  }
			50%       { transform: translateY(-6px); }
		}
		@keyframes zs-pulse {
			0%, 100% { opacity: 1; }
			50%       { opacity: .3; }
		}
		@keyframes zs-progress {
			0%   { width: 0%;   }
			40%  { width: 65%;  }
			80%  { width: 88%;  }
			100% { width: 100%; }
		}

		/* ── Responsive ───────────────────────────── */
		@media (max-width: 420px) {
			.zs-card { border-radius: 1.5rem; }
		}
	</style>
	<!--
		Google Fonts — gracefully absent if offline; fonts fall back to system stack.
		Loaded with display=swap to prevent FOIT.
	-->
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
</head>
<body>

<main class="zs-card" role="main" aria-labelledby="zs-heading">
	<?php
	if ($is_preview) {
			echo '<div style="position:fixed; top:10px; right:10px; background:#ef4444; color:white; padding:5px 15px; border-radius:20px; font-size:12px; z-index:9999;">Preview Mode</div>';
    }
	?>
	<div class="zs-badge" aria-label="Status: Maintenance in progress">
		<span class="zs-badge-dot" aria-hidden="true"></span>
		<?php echo $esc_name; ?>
	</div>

	<div class="zs-icon-wrap" aria-hidden="true">
		<?php if ( '' !== $esc_icon ) : ?>
		<img src="<?php echo $esc_icon; ?>" alt="" width="40" height="40" style="border-radius:.5rem;object-fit:contain;">
		<?php else : ?>
		<svg width="34" height="34" viewBox="0 0 24 24" fill="none"
			stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
			<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
		</svg>
		<?php endif; ?>
	</div>

	<h1 id="zs-heading" class="zs-heading">Systems stabilizing</h1>

	<p class="zs-sub">
		<strong><?php echo $esc_name; ?></strong> is undergoing a brief technical update.
		<?php echo $esc_msg; ?>
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
		Check Again
	</button>

	<p class="zs-status" aria-live="polite">503 &mdash; Temporary outage</p>

</main>

</body>
</html>
		<?php
		// phpcs:enable
		exit;
	}
}