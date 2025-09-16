<?php
/**
 * Plugin Name: JSON Post Importer
 * Plugin URI: https://example.com/plugins/json-post-importer/
 * Description: A powerful WordPress plugin to import posts, pages, and custom post types from JSON data via file upload or API.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com/
 * Text Domain: json-post-importer
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package JSON_Post_Importer
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('JPI_VERSION', '1.0.0');
define('JPI_PLUGIN_FILE', __FILE__);
define('JPI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('JPI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('JPI_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * The code that runs during plugin activation.
 */
function activate_json_post_importer() {
    require_once JPI_PLUGIN_DIR . 'includes/class-json-post-importer-activator.php';
    JSON_Post_Importer_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_json_post_importer() {
    require_once JPI_PLUGIN_DIR . 'includes/class-json-post-importer-deactivator.php';
    JSON_Post_Importer_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_json_post_importer');
register_deactivation_hook(__FILE__, 'deactivate_json_post_importer');

/**
 * Load the plugin text domain for translation.
 */
function json_post_importer_load_textdomain() {
    load_plugin_textdomain(
        'json-post-importer',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}
add_action('plugins_loaded', 'json_post_importer_load_textdomain');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require JPI_PLUGIN_DIR . 'includes/class-json-post-importer.php';

/**
 * Begins execution of the plugin.
 */
function run_json_post_importer() {
    $plugin = new JSON_Post_Importer();
    $plugin->run();
}
run_json_post_importer();

// Include required files
require_once JPI_PLUGIN_DIR . 'includes/class-json-post-creator.php';
require_once JPI_PLUGIN_DIR . 'includes/class-json-post-importer-loader.php';
require_once JPI_PLUGIN_DIR . 'includes/class-json-post-importer-i18n.php';
require_once JPI_PLUGIN_DIR . 'includes/class-json-post-importer-admin.php';
require_once JPI_PLUGIN_DIR . 'public/class-json-post-importer-public.php';
require_once JPI_PLUGIN_DIR . 'includes/class-json-post-importer-api.php';
