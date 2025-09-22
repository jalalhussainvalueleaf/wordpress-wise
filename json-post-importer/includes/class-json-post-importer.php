<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @since      1.0.0
 * @package    JSON_Post_Importer
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class JSON_Post_Importer {
    /**
     * The loader that's responsible for maintaining and registering all hooks.
     *
     * @since    1.0.0
     * @access   protected
     * @var      JSON_Post_Importer_Loader    $loader    Maintains and registers all hooks.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->version = defined('JPI_VERSION') ? JPI_VERSION : '1.0.0';
        $this->plugin_name = 'json-post-importer';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_api_hooks();
        $this->init_integrations();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        // Load the logger class first as other classes depend on it
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-json-post-importer-logger.php';
        
        // Load the JSON Post Creator first as other classes depend on it
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-json-post-creator.php';
        
        // Load the WordPress Formatter class
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-json-post-importer-wordpress-formatter.php';
        
        // Load the Nested Handler class
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-json-post-importer-nested-handler.php';
        
        // Load the Yoast SEO Integration class
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-json-post-importer-yoast-seo.php';
        
        // Load the FAQ Integration class
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-json-post-importer-faq-integration.php';
        
        // The class responsible for orchestrating the actions and filters of the core plugin.
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-json-post-importer-loader.php';
        
        // The class responsible for defining internationalization functionality.
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-json-post-importer-i18n.php';

        // The class responsible for defining all actions that occur in the admin area.
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-json-post-importer-admin.php';

        // The class responsible for defining all actions that occur in the public-facing side.
        require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-json-post-importer-public.php';
        
        // The class responsible for defining all API-related functionality
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-json-post-importer-api.php';

        // Initialize the loader
        $this->loader = new JSON_Post_Importer_Loader();
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale() {
        $plugin_i18n = new JSON_Post_Importer_i18n();
        $plugin_i18n->set_domain($this->get_plugin_name());
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        $plugin_admin = new JSON_Post_Importer_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');
        $this->loader->add_action('admin_init', $plugin_admin, 'register_settings');
        
        // AJAX handlers
        $this->loader->add_action('wp_ajax_jpi_handle_upload', $plugin_admin, 'handle_ajax_upload');
        $this->loader->add_action('wp_ajax_jpi_preview_json', $plugin_admin, 'handle_preview_json');
        $this->loader->add_action('wp_ajax_jpi_import_content', $plugin_admin, 'handle_import_content');
        $this->loader->add_action('admin_post_jpi_handle_upload', $plugin_admin, 'handle_file_upload');
    }

    /**
     * Register all of the hooks related to the public-facing functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks() {
        $plugin_public = new JSON_Post_Importer_Public($this->get_plugin_name(), $this->get_version());
        // Add any public hooks here if needed
    }

    /**
     * Register all of the hooks related to the REST API functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_api_hooks() {
        $plugin_api = new JSON_Post_Importer_API();
        
        // Register REST API routes
        add_action('rest_api_init', array($plugin_api, 'register_routes'));
    }

    /**
     * Initialize plugin integrations.
     *
     * @since    1.0.0
     * @access   private
     */
    private function init_integrations() {
        // Initialize FAQ integration
        JSON_Post_Importer_FAQ_Integration::init();
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    JSON_Post_Importer_Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Activation hook.
     *
     * @since    1.0.0
     */
    public static function activate() {
        // Create upload directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $jpi_dir = $upload_dir['basedir'] . '/json-post-importer';
        
        if (!file_exists($jpi_dir)) {
            wp_mkdir_p($jpi_dir);
            // Add index.php to prevent directory listing
            file_put_contents($jpi_dir . '/index.php', '<?php // Silence is golden');
        }
        
        // Add a transient to display admin notice on activation
        set_transient('jpi_activation_notice', true, 5);
    }

    /**
     * Deactivation hook.
     *
     * @since    1.0.0
     */
    public static function deactivate() {
        // Clean up any transients or options if needed
        delete_transient('jpi_activation_notice');
    }
}
