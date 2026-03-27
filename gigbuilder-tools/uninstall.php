<?php
/**
 * Gigbuilder Tools — Uninstall
 *
 * Fired when the plugin is deleted via the WordPress admin.
 * Cleans up stored options.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'gigbuilder_tools_settings' );
