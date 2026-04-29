<?php

/**
 * File_System Class
 *
 * @package CPL_CoreGuard
 */

namespace Amin\CPL_CoreGuard\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class File_System {

	/**
	 * Writes content to a file.
	 *
	 * @param string $path    The absolute path to the file.
	 * @param string $content The text to write.
	 * @param int    $flags   Optional. PHP file_put_contents flags.
	 * @return bool Success or failure.
	 */
	public function put_contents( string $path, string $content, int $flags = LOCK_EX ): bool {
		if ( strpos( $path, 'vfs://' ) === 0 ) {
			$flags &= ~LOCK_EX;
		}

		// You can swap this for the WP_Filesystem API later if permissions are an issue.
		return false !== file_put_contents( $path, $content, $flags );
	}

	/**
	 * Ensures the mu-plugins directory exists, creating it if necessary.
	 * Also adds an index.php for good measure.
	 */
	public function maybe_create_mu_dir(): bool {
		if ( is_dir( WPMU_PLUGIN_DIR ) ) {
			return true;
		}

		if ( wp_mkdir_p( WPMU_PLUGIN_DIR ) ) {
			return $this->put_contents(
				trailingslashit( WPMU_PLUGIN_DIR ) . 'index.php',
				"<?php // Silence is golden.\n"
			);
		}
		return false;
	}

	/**
	 * Deploys the self-contained logic file to the mu-plugins directory.
	 */
	public function deploy_mu_plugin(): bool {
		$source = CPL_COREGUARD_DIR . 'src/shield-logic.php';
		$dest   = trailingslashit( WPMU_PLUGIN_DIR ) . CPL_COREGUARD_MU_FILE;

		if ( ! file_exists( $source ) ) {
			return false;
		}

		return copy( $source, $dest );
	}

	/**
	 * Generic wrapper to delete files, with optional content check.
	 * If $marker is provided, the file will only be deleted if it contains that string.
	 *
	 * @param string $path the path of file.
	 * @param string $marker to check its own file or not.
	 * @return bool
	 */
	public function remove_file( string $path, string $marker = '' ): bool {
		if ( ! file_exists( $path ) ) {
			return true;
		}

		// If a marker is provided, only delete if the file contains it.
		if ( $marker ) {
			$contents = file_get_contents( $path );
			if ( false === $contents || ! str_contains( $contents, $marker ) ) {
				return false;
			}
		}

		return wp_delete_file( $path );
	}

	/**
	 * Removes the MU plugin logic file from the mu-plugins directory.
	 * * @return bool True if deleted or never existed.
	 */
	public function remove_mu_plugin(): bool {
		$path = trailingslashit( WPMU_PLUGIN_DIR ) . CPL_COREGUARD_MU_FILE;

		if ( ! file_exists( $path ) ) {
			return true;
		}

		return wp_delete_file( $path );
	}

	/**
	 * Removes the generated configuration file from the mu-plugins directory.
	 * * @return bool True if deleted or never existed.
	 */
	public function remove_config_file(): bool {
		$path = trailingslashit( WPMU_PLUGIN_DIR ) . CPL_COREGUARD_CFG_FILE;

		if ( ! file_exists( $path ) ) {
			return true;
		}

		return wp_delete_file( $path );
	}
}
