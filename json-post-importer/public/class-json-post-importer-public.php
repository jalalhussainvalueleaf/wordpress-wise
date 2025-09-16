<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @since      1.0.0
 * @package    JSON_Post_Importer
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * The public-facing functionality of the plugin.
 */
class JSON_Post_Importer_Public {
    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name       The name of the plugin.
     * @param    string    $version           The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        // Enqueue public styles if needed
        // wp_enqueue_style(
        //     $this->plugin_name,
        //     plugin_dir_url(__FILE__) . 'css/json-post-importer-public.css',
        //     array(),
        //     $this->version,
        //     'all'
        // );
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        // Enqueue public scripts if needed
        // wp_enqueue_script(
        //     $this->plugin_name,
        //     plugin_dir_url(__FILE__) . 'js/json-post-importer-public.js',
        //     array('jquery'),
        //     $this->version,
        //     false
        // );
    }
}
