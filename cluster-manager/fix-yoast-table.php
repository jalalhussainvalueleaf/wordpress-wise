<?php
/**
 * Fix for missing wp_yoast_primary_term table
 *
 * Add this code to your theme's functions.php or create a custom plugin
 */

// Hook into WordPress init to create the missing table
add_action('init', 'create_yoast_primary_term_table');

function create_yoast_primary_term_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'yoast_primary_term';

    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            post_id int(11) NOT NULL,
            taxonomy varchar(32) NOT NULL,
            primary_term_id int(11) NOT NULL,
            created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_taxonomy (post_id, taxonomy)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Log success
        error_log('Created wp_yoast_primary_term table');
    }
}

// Disable the problematic Yoast queries for cluster_category taxonomy
add_filter('wpseo_primary_term_taxonomies', 'disable_primary_term_for_cluster_category', 10, 2);

function disable_primary_term_for_cluster_category($taxonomies, $post_id) {
    // Remove cluster_category from primary term taxonomies
    if (isset($taxonomies['cluster_category'])) {
        unset($taxonomies['cluster_category']);
    }
    return $taxonomies;
}
?>
