<?php
/**
 * Fired during plugin deactivation
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    JSON_Post_Importer
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 */
class JSON_Post_Importer_Deactivator {

    /**
     * Deactivate the plugin.
     *
     * Clean up any resources used by the plugin.
     *
     * @since    1.0.0
     */
    public static function deactivate() {
        // Clear any scheduled hooks
        self::clear_scheduled_hooks();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Clear transients
        self::clear_transients();
    }
    
    /**
     * Clear scheduled hooks.
     *
     * @since    1.0.0
     */
    private static function clear_scheduled_hooks() {
        // Example: Clear any scheduled events
        if (wp_next_scheduled('jpi_scheduled_import')) {
            wp_clear_scheduled_hook('jpi_scheduled_import');
        }
        
        // Clear any other scheduled hooks here
    }
    
    /**
     * Clear transients.
     *
     * @since    1.0.0
     */
    private static function clear_transients() {
        global $wpdb;
        
        // Delete all transients with our prefix
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_jpi_%',
                '_transient_timeout_jpi_%'
            )
        );
        
        // Delete specific transients
        delete_transient('jpi_activation_notice');
    }
}
