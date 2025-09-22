<?php
/**
 * Cluster Manager Debug Functions
 *
 * Add this code to your theme's functions.php or create a debug page
 * to check if the plugin is loading properly
 */

function cluster_manager_debug_info() {
    $debug_info = array(
        'plugin_file_exists' => file_exists(WP_PLUGIN_DIR . '/cluster-manager/cluster-manager.php'),
        'plugin_active' => is_plugin_active('cluster-manager/cluster-manager.php'),
        'acf_active' => function_exists('acf_add_local_field_group'),
        'cpt_registered' => post_type_exists('cluster'),
        'taxonomies_registered' => array(
            'cluster_category' => taxonomy_exists('cluster_category'),
            'cluster_tag' => taxonomy_exists('cluster_tag'),
        ),
        'options' => array(
            'field_groups_created' => get_option('cluster_manager_field_groups_created'),
        ),
        'plugin_version' => get_plugin_data(WP_PLUGIN_DIR . '/cluster-manager/cluster-manager.php')['Version'],
    );

    return $debug_info;
}

// Display debug info (uncomment to use)
// add_action('admin_notices', function() {
//     $debug = cluster_manager_debug_info();
//     echo '<div class="notice notice-info"><h3>Cluster Manager Debug Info:</h3><pre>' . print_r($debug, true) . '</pre></div>';
// });
