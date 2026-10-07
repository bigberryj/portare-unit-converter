<?php
/**
 * Plugin Name: Portare Unit Converter
 * Description: Display-only inches to centimetres toggle without changing authored content.
 * Version: 0.1.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: MIT
 * Text Domain: portare-unit-converter
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
define( 'PUC_VERSION', '0.1.1' );
define( 'PUC_PLUGIN_FILE', __FILE__ );
define( 'PUC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PUC_OPTION_KEY', 'puc_settings' );
require_once PUC_PLUGIN_DIR . 'includes/class-puc-settings.php';
require_once PUC_PLUGIN_DIR . 'includes/class-puc-plugin.php';
register_activation_hook( PUC_PLUGIN_FILE, array( 'PUC_Plugin', 'activate' ) );
add_action( 'plugins_loaded', static function () {
    $settings = new PUC_Settings();
    $settings->init();
    $plugin = new PUC_Plugin( $settings );
    $plugin->init();
} );
