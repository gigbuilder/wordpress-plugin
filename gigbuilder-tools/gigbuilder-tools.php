<?php
/**
 * Plugin Name: Gigbuilder Tools
 * Plugin URI: https://gigbuilder.com
 * Description: Widgets and tools for connecting WordPress sites to Gigbuilder CRM.
 * Version: 1.0.0
 * Author: Gigbuilder
 * License: GPL v2 or later
 * Text Domain: gigbuilder-tools
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'GIGBUILDER_TOOLS_VERSION', '1.2.0' );
define( 'GIGBUILDER_TOOLS_PATH', plugin_dir_path( __FILE__ ) );
define( 'GIGBUILDER_TOOLS_URL', plugin_dir_url( __FILE__ ) );

// Core includes
require_once GIGBUILDER_TOOLS_PATH . 'includes/class-settings.php';
require_once GIGBUILDER_TOOLS_PATH . 'includes/class-api-client.php';
require_once GIGBUILDER_TOOLS_PATH . 'includes/class-form-renderer.php';

// Widgets
require_once GIGBUILDER_TOOLS_PATH . 'widgets/availability/class-availability.php';

// Initialize
add_action( 'plugins_loaded', function() {
    Gigbuilder_Settings::init();
    Gigbuilder_Availability::init();
});
