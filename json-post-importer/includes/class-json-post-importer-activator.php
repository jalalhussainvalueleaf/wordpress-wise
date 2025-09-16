<?php
/**
 * Fired during plugin activation
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
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 */
class JSON_Post_Importer_Activator {

    /**
     * Activate the plugin.
     *
     * Creates necessary database tables and sets default options.
     *
     * @since    1.0.0
     */
    public static function activate() {
        // Create necessary database tables
        self::create_tables();
        
        // Set default options
        self::set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set a transient to show activation notice
        set_transient('jpi_activation_notice', true, 5);
    }
    
    /**
     * Create necessary database tables.
     *
     * @since    1.0.0
     */
    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table for import logs
        $table_name = $wpdb->prefix . 'jpi_import_logs';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            import_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            user_id bigint(20) NOT NULL,
            status varchar(20) NOT NULL,
            total_items int(11) DEFAULT 0,
            imported_items int(11) DEFAULT 0,
            skipped_items int(11) DEFAULT 0,
            error_count int(11) DEFAULT 0,
            file_name varchar(255) DEFAULT '',
            file_size int(11) DEFAULT 0,
            options longtext,
            errors longtext,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY import_date (import_date),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Set default plugin options.
     *
     * @since    1.0.0
     */
    private static function set_default_options() {
        $default_options = array(
            'version' => JPI_VERSION,
            'default_post_type' => 'post',
            'default_post_status' => 'draft',
            'default_author' => get_current_user_id(),
            'max_upload_size' => wp_max_upload_size(),
            'allowed_mime_types' => array('application/json'),
            'enable_logging' => true,
            'keep_logs_days' => 30,
            'enable_api' => true,
            'api_key' => wp_generate_password(32, false, false),
        );
        
        update_option('jpi_settings', $default_options);
    }
}
