<?php
namespace Amin\Fatal_Flow\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Utility functions for FatalFlow.
 *
 * @package Fatal_Flow
 * @since 1.0.0
 * @author Al Amin <hmalaminmb4@gmail.com>
 */
class Utils {

	/**
	 * Sanitizes a hex color string to exactly #RRGGBB.
	 *
	 * @param  string $color Raw input.
	 * @return string        Sanitized color or default.
	 */
	public static function sanitize_hex_color( string $color ): string {
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

	/**
	 * Locates wp-config.php — handles the case where it lives one level
	 * above ABSPATH (standard hardened setup).
	 *
	 * @return string|null Absolute path, or null if not found / not readable.
	 */
	public static function find_wp_config(): ?string {
		$candidates = array(
			ABSPATH . 'wp-config.php',
			dirname( ABSPATH ) . '/wp-config.php',
		);
		foreach ( $candidates as $candidate ) {
			if ( file_exists( $candidate ) && is_readable( $candidate ) ) {
				return $candidate;
			}
		}
		return null;
	}
}
