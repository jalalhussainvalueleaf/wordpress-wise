<?php
/**
 * Plugin Name: Cluster Manager
 * Description: A comprehensive plugin for managing cluster posts with ACF support and shortcode generation
 * Version: 1.0.0
 * Author: Your Name
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cluster-manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Plugin Class
 */
class Cluster_Manager_Plugin {

    /**
     * Plugin version
     */
    const VERSION = '1.0.0';

    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->init_components();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('wp_ajax_get_clusters_list', array($this, 'get_clusters_list'));
        add_action('wp_ajax_refresh_cluster_preview', array($this, 'refresh_cluster_preview'));
    }

    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Load required files
        $this->load_dependencies();

        // Include fix for Yoast SEO primary term table issue
        if (file_exists(plugin_dir_path(__FILE__) . 'fix-yoast-table.php')) {
            require_once plugin_dir_path(__FILE__) . 'fix-yoast-table.php';
        }

        // Initialize components
        try {
            new Cluster_Manager_CPT();
            new Cluster_Manager_ACF();
            new Cluster_Manager_Shortcode();
            new Cluster_Manager_Admin();
        } catch (Exception $e) {
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p><strong>Cluster Manager Error:</strong> ' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Include component files
        require_once plugin_dir_path(__FILE__) . 'includes/class-cluster-manager-cpt.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-cluster-manager-acf.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-cluster-manager-shortcode.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-cluster-manager-admin.php';
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        $domain = 'cluster-manager';
        $locale = apply_filters('plugin_locale', get_locale(), $domain);

        load_textdomain($domain, WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale . '.mo');
        load_plugin_textdomain($domain, false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Flush rewrite rules
        flush_rewrite_rules();

        // Schedule ACF field group creation for after all plugins are loaded
        add_action('init', array($this, 'create_field_groups_on_activation'), 999);
    }

    /**
     * Create field groups after activation
     */
    public function create_field_groups_on_activation() {
        // ACF field groups are no longer used - using standard WordPress fields only
        update_option('cluster_manager_field_groups_created', true);
        return;
    }

    /**
     * Plugin initialization
     */
    public function init() {
        // Plugin initialization code here
        // Add debug info to help troubleshoot
        if (is_admin() && current_user_can('manage_options')) {
            add_action('admin_notices', array($this, 'admin_notices'));
        }
    }

    /**
     * Display admin notices
     */
    public function admin_notices() {
        // Check if ACF is active for enhanced features
        if (!function_exists('acf_add_local_field_group')) {
            echo '<div class="notice notice-info"><p><strong>Cluster Manager:</strong> ' . __('Advanced Custom Fields (ACF) plugin is not active. The plugin will work with basic WordPress fields only. Install ACF for additional field options.', 'cluster-manager') . '</p></div>';
        }
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    public function get_clusters_list() {
        check_ajax_referer('cluster_manager_nonce', 'nonce');

        $clusters = get_posts(array(
            'post_type' => 'cluster',
            'numberposts' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ));

        $response = array();
        foreach ($clusters as $cluster) {
            $response[] = array(
                'id' => $cluster->ID,
                'title' => $cluster->post_title,
            );
        }

        wp_send_json_success($response);
    }

    public function refresh_cluster_preview() {
        check_ajax_referer('cluster_manager_nonce', 'nonce');

        $post_id = intval($_POST['post_id']);
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }

        $cluster = get_post($post_id);
        if (!$cluster || 'cluster' !== $cluster->post_type) {
            wp_send_json_error('Invalid cluster post');
        }

        // Generate preview HTML
        $preview_html = do_shortcode('[cluster id="' . $post_id . '" show_content="false"]');

        wp_send_json_success($preview_html);
    }

    /**
     * Display ACF missing notice
     */
    public function acf_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><strong>Cluster Manager:</strong> <?php _e('Advanced Custom Fields (ACF) plugin is required but not active. Please install and activate ACF to use all plugin features.', 'cluster-manager'); ?></p>
        </div>
        <?php
    }

    /**
     * Debug function to check if plugin is loading
     */
    public function debug_plugin_status() {
        $debug_info = array(
            'plugin_loaded' => true,
            'acf_active' => function_exists('acf_add_local_field_group'),
            'field_groups_created' => get_option('cluster_manager_field_groups_created'),
            'cpt_registered' => post_type_exists('cluster'),
        );

        return $debug_info;
    }
}

// Initialize the plugin
Cluster_Manager_Plugin::get_instance();
