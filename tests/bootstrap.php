<?php
// Load Composer Autoloader
define( 'CPL_PLUGIN_ROOT', dirname( __DIR__ ) );
require_once CPL_PLUGIN_ROOT . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', CPL_PLUGIN_ROOT . '/temp_abs/' );
}

// Mock WordPress Constants for Testing
define('ABSPATH', '/var/www/html/');
define('WPMU_PLUGIN_DIR', '/var/www/html/wp-content/mu-plugins');
define('WP_CONTENT_DIR', '/var/www/html/wp-content');
define('CPL_COREGUARD_VERSION', '1.0.0');
define('CPL_COREGUARD_MU_FILE', 'cpl-coreguard-logic.php');
define('CPL_COREGUARD_CFG_FILE', 'cpl-coreguard-config.php');

// Add these to your existing bootstrap.php
function sanitize_textarea_field($text) { return $text; }
function sanitize_text_field($text) { return $text; }
function esc_url_raw($url) { return $url; }
// function addslashes($text) { return \addslashes($text); } // PHP native, but good to be explicit
function get_locale() { return 'en_US'; }

// Mock a few WP functions if not using a framework like BrainMonkey
function get_option($key, $default = false) { return $default; }
function get_bloginfo($key) { return 'Test Site'; }
function __($text, $domain) { return $text; }
function trailingslashit($path) { return rtrim($path, '/') . '/'; }