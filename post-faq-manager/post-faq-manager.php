<?php
/**
 * Plugin Name: Post FAQ Manager
 * Description: A plugin to manage FAQs with an admin UI and REST API.
 * Version: 1.0
 * Author: Jalal Hussain
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants for plugin paths.
define( 'POST_FAQ_MANAGER_PATH', plugin_dir_path( __FILE__ ) );
define( 'POST_FAQ_MANAGER_URL', plugin_dir_url( __FILE__ ) );

// Check if required files exist before including them.
if ( file_exists( POST_FAQ_MANAGER_PATH . 'includes/admin.php' ) ) {
    require_once POST_FAQ_MANAGER_PATH . 'includes/admin.php';
}

if ( file_exists( POST_FAQ_MANAGER_PATH . 'includes/rest-api.php' ) ) {
    require_once POST_FAQ_MANAGER_PATH . 'includes/rest-api.php';
}