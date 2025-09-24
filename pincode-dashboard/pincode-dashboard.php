<?php

/**
 * Plugin Name:       Pincode Dashboard
 * Description:       Manage Indian pincode/post office data with CSV upload, filters, and REST API.
 * Version:           1.1.0
 * Author:            Amresh Halinge
 * Plugin URI:        https://yourdomain.com/pincode-dashboard
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */
if (!defined('ABSPATH')) exit;
require_once plugin_dir_path(__FILE__) . 'inc/rest-api.php';
define('PINCODE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PINCODE_PLUGIN_URL', plugin_dir_url(__FILE__));

register_activation_hook(__FILE__, 'pincode_create_table');
function pincode_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pincode_data';
    $charset_collate = $wpdb->get_charset_collate();

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $sql = "CREATE TABLE $table_name (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        circlename VARCHAR(100),
        regionname VARCHAR(100),
        divisionname VARCHAR(100),
        officename VARCHAR(255),
        pincode VARCHAR(10),
        officetype VARCHAR(100),
        delivery VARCHAR(20),
        district VARCHAR(100),
        statename VARCHAR(100),
        latitude VARCHAR(50),
        longitude VARCHAR(50),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX(statename),
        INDEX(district),
        INDEX(officename)
    ) $charset_collate;";
    dbDelta($sql);
}

require_once plugin_dir_path(__FILE__) . 'inc/ajax-handlers.php';

add_action('admin_menu', function () {
    add_submenu_page(
        'ifsc-dashboard',
        'Pincode Dashboard',
        'Pincode Dashboard',
        'manage_options',
        'pincode-dashboard',
        'pincode_render_dashboard_page'
    );
});

function pincode_render_dashboard_page() {
    include PINCODE_PLUGIN_DIR . 'templates/dashboard.php';
}

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'ifsc-dashboard_page_pincode-dashboard') return;

    wp_enqueue_style('pincode-style', PINCODE_PLUGIN_URL . 'css/style.css', [], '1.1');
    wp_enqueue_script('pincode-script', PINCODE_PLUGIN_URL . 'js/script.js', ['jquery'], '1.1', true);

    wp_localize_script('pincode-script', 'pincode_ajax_obj', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pincode-dashboard-nonce')
    ]);
});
