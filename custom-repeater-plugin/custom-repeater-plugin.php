<?php
/**
 * Plugin Name: MAD Custom Repeater
 * Description: Adds a repeater field functionality to the WordPress editor.
 * Version: 1.0
 * Author: Jalal Hussain
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Include plugin files.
require_once plugin_dir_path( __FILE__ ) . 'includes/admin.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/rest-api.php';