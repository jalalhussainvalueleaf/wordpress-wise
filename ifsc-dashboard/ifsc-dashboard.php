<?php
 /**
 * Plugin Name: IFSC Code Dashboard          
 * Plugin URI: https://yourwebsite.com       
 * Description: Search and manage IFSC code data with CSV upload, edit, delete, and dashboard filtering.
 * Version: 1.0.0                             
 * Author URI: https://profiles.wordpress.org/amreshhalinge97/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ifsc-code-dashboard           
 ***/

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

define('IFSC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IFSC_PLUGIN_URL', plugin_dir_url(__FILE__));

// Create/update table on plugin activation
register_activation_hook(__FILE__, 'ifsc_create_table');
function ifsc_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ifsc_codes';
    $charset_collate = $wpdb->get_charset_collate();
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Users may need to deactivate/reactivate the plugin for this schema change to apply.
    $sql = "CREATE TABLE $table_name (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        bank_name VARCHAR(100),
        state VARCHAR(100),
        district VARCHAR(100),
        branch VARCHAR(255),
        ifsc VARCHAR(20) UNIQUE,
        micr VARCHAR(20),
        address TEXT,
        city VARCHAR(100),
        contact_number VARCHAR(20),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX(bank_name),
        INDEX(state),
        INDEX(district),
        INDEX(branch)
    ) $charset_collate;";

    dbDelta($sql);
}

// Include plugin logic files
require_once IFSC_PLUGIN_DIR . 'inc/ajax-handlers.php';
require_once IFSC_PLUGIN_DIR . 'inc/csv-upload.php';
require_once IFSC_PLUGIN_DIR . 'inc/rest-api.php';

// Register the admin menu page
add_action('admin_menu', 'ifsc_register_admin_page');
function ifsc_register_admin_page() {
    add_menu_page(
        'IFSC Dashboard',      // Page title
        'IFSC Dashboard',      // Menu title
        'manage_options',      // Capability
        'ifsc-dashboard',      // Menu slug
        'ifsc_render_dashboard_page', // Callback function
        'dashicons-bank'       // Icon
    );
}

// Render the dashboard page content by including the template file
function ifsc_render_dashboard_page() {
    include IFSC_PLUGIN_DIR . 'templates/dashboard.php';
}

// Enqueue scripts and styles for the admin page
add_action('admin_enqueue_scripts', 'ifsc_enqueue_admin_assets');
function ifsc_enqueue_admin_assets($hook) {
    // Only load assets on our specific plugin page to avoid conflicts
    if ('toplevel_page_ifsc-dashboard' !== $hook) {
        return;
    }

    // Enqueue a dedicated CSS file for styling
    wp_enqueue_style('ifsc-style', IFSC_PLUGIN_URL . 'css/style.css', [], '2.0');

    // Enqueue the main JavaScript file
    wp_enqueue_script('ifsc-script', IFSC_PLUGIN_URL . 'js/script.js', ['jquery'], '2.0', true);

    // Pass data to JavaScript, including a nonce for security
    wp_localize_script('ifsc-script', 'ifsc_ajax_obj', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('ifsc-dashboard-nonce'),
    ]);
}