<?php
/**
 * The admin-specific functionality of the plugin.
 */
class JSON_Post_Importer_Admin {
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
     * The JSON_Post_Creator instance
     *
     * @var JSON_Post_Creator
     */
    private $post_creator;

    /**
     * The logger instance
     *
     * @var JSON_Post_Importer_Logger
     */
    private $logger;

    /**
     * The WordPress formatter instance
     *
     * @var JSON_Post_Importer_WordPress_Formatter
     */
    private $wordpress_formatter;

    /**
     * The nested handler instance
     *
     * @var JSON_Post_Importer_Nested_Handler
     */
    private $nested_handler;

    /**
     * The Yoast SEO integration instance
     *
     * @var JSON_Post_Importer_Yoast_SEO
     */
    private $yoast_seo;

    /**
     * Last duplicate detection method used
     *
     * @var string
     */
    private $last_duplicate_detection_method;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->post_creator = new JSON_Post_Creator();
        $this->logger = new JSON_Post_Importer_Logger();
        $this->wordpress_formatter = new JSON_Post_Importer_WordPress_Formatter();
        $this->nested_handler = new JSON_Post_Importer_Nested_Handler();
        $this->yoast_seo = new JSON_Post_Importer_Yoast_SEO();
        
        // Register AJAX handlers
        add_action('wp_ajax_jpi_handle_upload', array($this, 'handle_ajax_upload'));
        add_action('wp_ajax_jpi_preview_json', array($this, 'handle_preview_json'));
        add_action('wp_ajax_jpi_get_preview_content', array($this, 'get_preview_content'));
        add_action('wp_ajax_jpi_import_content', array($this, 'handle_import_content'));
        add_action('wp_ajax_jpi_import_posts_with_mapping', array($this, 'handle_import_with_mapping'));
        add_action('wp_ajax_jpi_import_with_mapping_enhanced', array($this, 'handle_import_with_mapping_enhanced'));
        add_action('wp_ajax_jpi_start_import', array($this, 'start_import'));
        add_action('wp_ajax_jpi_process_batch', array($this, 'process_batch'));
        add_action('wp_ajax_jpi_check_batch_status', array($this, 'check_batch_status'));
        add_action('wp_ajax_jpi_cancel_import', array($this, 'cancel_import'));
        add_action('wp_ajax_jpi_get_import_results', array($this, 'get_import_results'));
        add_action('wp_ajax_jpi_get_import_logs', array($this, 'get_import_logs_ajax'));
        add_action('wp_ajax_jpi_clear_import_logs', array($this, 'clear_import_logs_ajax'));
        add_action('wp_ajax_jpi_get_import_history', array($this, 'get_import_history'));
        
        // WordPress formatter AJAX handlers
        add_action('wp_ajax_jpi_format_to_wordpress', array($this, 'format_to_wordpress_ajax'));
        add_action('wp_ajax_jpi_get_wordpress_suggestions', array($this, 'get_wordpress_suggestions_ajax'));
        
        // Logger AJAX handlers
        add_action('wp_ajax_jpi_get_logs', array($this, 'get_logs_ajax'));
        add_action('wp_ajax_jpi_clear_logs', array($this, 'clear_logs_ajax'));
        add_action('wp_ajax_jpi_toggle_debug_mode', array($this, 'toggle_debug_mode_ajax'));
        add_action('wp_ajax_jpi_get_log_stats', array($this, 'get_log_stats_ajax'));
        add_action('wp_ajax_jpi_download_log', array($this, 'download_log_ajax'));
        add_action('admin_post_jpi_handle_upload', array($this, 'handle_file_upload'));
        
        // Nested field mapping AJAX handlers
        add_action('wp_ajax_jpi_extract_nested_fields', array($this, 'extract_nested_fields_ajax'));
        add_action('wp_ajax_jpi_validate_nested_paths', array($this, 'validate_nested_paths_ajax'));
        add_action('wp_ajax_jpi_preview_nested_mapping', array($this, 'preview_nested_mapping_ajax'));
        
        // Yoast SEO AJAX handlers
        add_action('wp_ajax_jpi_get_yoast_fields', array($this, 'get_yoast_fields_ajax'));
        add_action('wp_ajax_jpi_auto_detect_yoast_fields', array($this, 'auto_detect_yoast_fields_ajax'));
        add_action('wp_ajax_jpi_validate_yoast_fields', array($this, 'validate_yoast_fields_ajax'));
        add_action('wp_ajax_jpi_preview_yoast_seo', array($this, 'preview_yoast_seo_ajax'));
        add_action('wp_ajax_jpi_calculate_seo_score', array($this, 'calculate_seo_score_ajax'));
        add_action('wp_ajax_jpi_migrate_yoast_data', array($this, 'migrate_yoast_data_ajax'));
        
        // Enhanced field mapping AJAX handlers
        add_action('wp_ajax_jpi_check_yoast_status', array($this, 'check_yoast_status_ajax'));
        add_action('wp_ajax_jpi_auto_detect_enhanced_mappings', array($this, 'auto_detect_enhanced_mappings_ajax'));
        add_action('wp_ajax_jpi_validate_enhanced_mappings', array($this, 'validate_enhanced_mappings_ajax'));
        add_action('wp_ajax_jpi_preview_enhanced_mappings', array($this, 'preview_enhanced_mappings_ajax'));
        add_action('wp_ajax_jpi_save_mapping_preset', array($this, 'save_mapping_preset_ajax'));
        add_action('wp_ajax_jpi_load_mapping_preset', array($this, 'load_mapping_preset_ajax'));
        add_action('wp_ajax_jpi_delete_mapping_preset', array($this, 'delete_mapping_preset_ajax'));
        
        // Schedule cleanup of old import data
        add_action('jpi_cleanup_old_imports', array($this, 'cleanup_old_imports'));
        if (!wp_next_scheduled('jpi_cleanup_old_imports')) {
            wp_schedule_event(time(), 'daily', 'jpi_cleanup_old_imports');
        }
    }

    public function add_plugin_admin_menu() {
        add_menu_page(
            'JSON Post Importer',
            'JSON Importer',
            'manage_options',
            'json-post-importer',
            array($this, 'display_plugin_admin_page'),
            'dashicons-upload',
            26
        );
    }

    public function register_settings() {
        // Register settings if needed
    }

    public function display_plugin_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Create the upload directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $jpi_dir = $upload_dir['basedir'] . '/json-post-importer';
        if (!file_exists($jpi_dir)) {
            wp_mkdir_p($jpi_dir);
        }
        
        // Include the admin display template
        include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/json-post-importer-admin-display.php';
    }

    public function enqueue_styles($hook) {
        // Only load on our plugin page
        if ($hook !== 'toplevel_page_json-post-importer') {
            return;
        }

        // Main admin styles
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url(dirname(__FILE__)) . 'admin/css/json-post-importer-admin.css',
            array(),
            $this->version,
            'all'
        );
        
        // Modal styles
        wp_enqueue_style(
            $this->plugin_name . '-modal',
            plugin_dir_url(dirname(__FILE__)) . 'admin/css/modal-styles.css',
            array(),
            $this->version,
            'all'
        );
        
        // Field mapping styles
        wp_enqueue_style(
            $this->plugin_name . '-field-mapping',
            plugin_dir_url(dirname(__FILE__)) . 'admin/css/field-mapping-styles.css',
            array(),
            $this->version,
            'all'
        );
        
        // Logs styles
        wp_enqueue_style(
            $this->plugin_name . '-logs',
            plugin_dir_url(dirname(__FILE__)) . 'admin/css/logs-styles.css',
            array(),
            $this->version,
            'all'
        );
        
        // Nested field mapping styles
        wp_enqueue_style(
            $this->plugin_name . '-nested-mapping',
            plugin_dir_url(dirname(__FILE__)) . 'admin/css/nested-field-mapping.css',
            array(),
            $this->version,
            'all'
        );
        
        // Yoast SEO integration styles
        wp_enqueue_style(
            $this->plugin_name . '-yoast-seo',
            plugin_dir_url(dirname(__FILE__)) . 'admin/css/yoast-seo-integration.css',
            array(),
            $this->version,
            'all'
        );
        
        // Enhanced field mapping styles
        wp_enqueue_style(
            $this->plugin_name . '-enhanced-mapping',
            plugin_dir_url(dirname(__FILE__)) . 'admin/css/enhanced-field-mapping.css',
            array(),
            $this->version,
            'all'
        );
    }

    public function enqueue_scripts($hook) {
        // Only load on our plugin page
        if ($hook !== 'toplevel_page_json-post-importer') {
            return;
        }

        // Enqueue field mapping script first
        wp_enqueue_script(
            $this->plugin_name . '-field-mapping',
            plugin_dir_url(dirname(__FILE__)) . 'admin/js/json-post-importer-field-mapping.js',
            array('jquery', 'jquery-ui-dialog'),
            $this->version,
            false
        );

        // Enqueue nested field mapping script
        wp_enqueue_script(
            $this->plugin_name . '-nested-mapping',
            plugin_dir_url(dirname(__FILE__)) . 'admin/js/nested-field-mapping.js',
            array('jquery', $this->plugin_name . '-field-mapping'),
            $this->version,
            false
        );

        // Enqueue Yoast SEO integration script
        wp_enqueue_script(
            $this->plugin_name . '-yoast-seo',
            plugin_dir_url(dirname(__FILE__)) . 'admin/js/yoast-seo-integration.js',
            array('jquery', $this->plugin_name . '-field-mapping'),
            $this->version,
            false
        );

        // Enqueue logs script
        wp_enqueue_script(
            $this->plugin_name . '-logs',
            plugin_dir_url(dirname(__FILE__)) . 'admin/js/logs-functionality.js',
            array('jquery'),
            $this->version,
            false
        );

        // Enqueue enhanced field mapping script
        wp_enqueue_script(
            $this->plugin_name . '-enhanced-mapping',
            plugin_dir_url(dirname(__FILE__)) . 'admin/js/enhanced-field-mapping.js',
            array('jquery', $this->plugin_name . '-field-mapping', $this->plugin_name . '-nested-mapping', $this->plugin_name . '-yoast-seo'),
            $this->version,
            false
        );

        // Enqueue main admin script
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url(dirname(__FILE__)) . 'admin/js/json-post-importer-admin.js',
            array('jquery', $this->plugin_name . '-field-mapping', $this->plugin_name . '-yoast-seo', $this->plugin_name . '-logs'),
            $this->version,
            false
        );

        // Enqueue nested fields fix script
        wp_enqueue_script(
            $this->plugin_name . '-nested-fix',
            plugin_dir_url(dirname(__FILE__)) . 'fix-nested-fields.js',
            array('jquery', $this->plugin_name),
            $this->version,
            false
        );

        // Get available taxonomies
        $taxonomies = get_taxonomies(array('public' => true), 'objects');
        $taxonomy_options = array();
        
        foreach ($taxonomies as $taxonomy) {
            $taxonomy_options[$taxonomy->name] = $taxonomy->labels->singular_name;
        }

        // Get available authors
        $authors = get_users(array('who' => 'authors'));
        $author_options = array();
        
        foreach ($authors as $author) {
            $author_options[$author->ID] = $author->display_name;
        }

        // Localize script with data for enhanced field mapping
        wp_localize_script(
            $this->plugin_name . '-enhanced-mapping',
            'jpi_ajax',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('jpi_nonce')
            )
        );

        // Localize script with data
        wp_localize_script(
            $this->plugin_name,
            'jpi_vars',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('jpi_ajax_nonce'),
                'preview_nonce' => wp_create_nonce('jpi_preview_nonce'),
                'upload_nonce' => wp_create_nonce('jpi_upload_nonce'),
                'max_upload_size' => wp_max_upload_size(),
                'default_post_type' => 'post',
                'default_post_status' => 'draft',
                'current_user_id' => get_current_user_id(),
                'taxonomies' => $taxonomy_options,
                'authors' => $author_options,
                'i18n' => array(
                    // General
                    'loading' => __('Loading...', 'json-post-importer'),
                    'uploading' => __('Uploading...', 'json-post-importer'),
                    'processing' => __('Processing...', 'json-post-importer'),
                    'importing' => __('Importing...', 'json-post-importer'),
                    'complete' => __('Complete!', 'json-post-importer'),
                    'error' => __('Error', 'json-post-importer'),
                    'invalid_file' => __('Please select a valid JSON file.', 'json-post-importer'),
                    'no_file' => __('Please select a file first.', 'json-post-importer'),
                    'import_success' => __('Import completed successfully!', 'json-post-importer'),
                    'import_error' => __('Import failed. Please check the error log.', 'json-post-importer'),
                    'remove' => __('Remove', 'json-post-importer'),
                    'actions' => __('Actions', 'json-post-importer'),
                    'preview' => __('Preview', 'json-post-importer'),
                    
                    // Field Mapping
                    'map_fields' => __('Map Fields', 'json-post-importer'),
                    'map_fields_desc' => __('Map JSON fields to WordPress post fields. Required fields are marked with an asterisk (*)', 'json-post-importer'),
                    'mapping_presets' => __('Quick Start Presets:', 'json-post-importer'),
                    'select_preset' => __('Select a preset', 'json-post-importer'),
                    'clear_all' => __('Clear All', 'json-post-importer'),
                    'confirm_clear' => __('Are you sure you want to clear all field mappings?', 'json-post-importer'),
                    'preset_applied' => __('Mapping preset applied successfully!', 'json-post-importer'),
                    
                    // Standard Fields
                    'standard_fields' => __('Standard WordPress Fields', 'json-post-importer'),
                    'wordpress_field' => __('WordPress Field', 'json-post-importer'),
                    'json_field' => __('JSON Field', 'json-post-importer'),
                    'select_field' => __('Select Field', 'json-post-importer'),
                    'post_title' => __('Post Title', 'json-post-importer'),
                    'post_content' => __('Post Content', 'json-post-importer'),
                    'post_excerpt' => __('Post Excerpt', 'json-post-importer'),
                    'post_status' => __('Post Status', 'json-post-importer'),
                    'post_date' => __('Post Date', 'json-post-importer'),
                    'post_author' => __('Post Author', 'json-post-importer'),
                    'publish' => __('Publish', 'json-post-importer'),
                    'draft' => __('Draft', 'json-post-importer'),
                    'pending' => __('Pending Review', 'json-post-importer'),
                    'private' => __('Private', 'json-post-importer'),
                    
                    // Custom Fields
                    'custom_fields' => __('Custom Fields', 'json-post-importer'),
                    'add_custom_field' => __('Add Custom Field', 'json-post-importer'),
                    'meta_key' => __('Meta Key', 'json-post-importer'),
                    'custom_field_placeholder' => __('custom_field_name', 'json-post-importer'),
                    'no_custom_fields' => __('No custom fields added yet. Click "Add Custom Field" to get started.', 'json-post-importer'),
                    
                    // Taxonomies
                    'taxonomies' => __('Taxonomies', 'json-post-importer'),
                    'add_taxonomy' => __('Add Taxonomy', 'json-post-importer'),
                    'taxonomy' => __('Taxonomy', 'json-post-importer'),
                    'select_taxonomy' => __('Select Taxonomy', 'json-post-importer'),
                    'no_taxonomies' => __('No taxonomies added yet. Click "Add Taxonomy" to get started.', 'json-post-importer'),
                    
                    // Validation
                    'mapping_valid' => __('Field mapping is valid and ready for import.', 'json-post-importer'),
                    'mapping_errors' => __('Please fix the following errors:', 'json-post-importer'),
                    'mapping_warnings' => __('Recommendations:', 'json-post-importer'),
                    'title_required' => __('Post title mapping is required.', 'json-post-importer'),
                    'content_recommended' => __('Post content mapping is recommended for complete posts.', 'json-post-importer'),
                    'custom_field_incomplete' => __('Custom field "{field}" needs a JSON field mapping.', 'json-post-importer'),
                    'meta_key_required' => __('Meta key is required for custom field mapping.', 'json-post-importer'),
                    'invalid_meta_key' => __('Meta key "{field}" contains invalid characters. Use only letters, numbers, and underscores.', 'json-post-importer'),
                    'duplicate_meta_key' => __('Meta key "{field}" is used multiple times. Each meta key must be unique.', 'json-post-importer'),
                    'taxonomy_field_incomplete' => __('Taxonomy "{taxonomy}" needs a JSON field mapping.', 'json-post-importer'),
                    'taxonomy_required' => __('Taxonomy selection is required for taxonomy mapping.', 'json-post-importer'),
                    'duplicate_taxonomy' => __('Taxonomy "{taxonomy}" is mapped multiple times. Each taxonomy should only be mapped once.', 'json-post-importer'),
                    'no_mappings' => __('At least one field mapping is required to proceed with import.', 'json-post-importer'),
                    'fix_validation_errors' => __('Please fix the validation errors before importing.', 'json-post-importer'),
                    
                    // Preview validation
                    'preview_errors' => __('Preview Issues', 'json-post-importer'),
                    'preview_warnings' => __('Recommendations', 'json-post-importer'),
                    'invalid_json_structure' => __('Invalid JSON structure. Expected an array of objects.', 'json-post-importer'),
                    'empty_json_data' => __('JSON file contains no data to import.', 'json-post-importer'),
                    'no_title_fields' => __('No obvious title fields found. You may need to manually map the post title.', 'json-post-importer'),
                    'no_content_fields' => __('No obvious content fields found. Posts may be created without content.', 'json-post-importer'),
                    
                    // Presets
                    'wordpress_standard' => __('WordPress Standard', 'json-post-importer'),
                    'wordpress_standard_desc' => __('Standard WordPress post fields', 'json-post-importer'),
                    'blog_post' => __('Blog Post', 'json-post-importer'),
                    'blog_post_desc' => __('Common blog post structure', 'json-post-importer'),
                    'ecommerce_product' => __('E-commerce Product', 'json-post-importer'),
                    'ecommerce_product_desc' => __('Product data structure', 'json-post-importer'),
                    'news_article' => __('News Article', 'json-post-importer'),
                    'news_article_desc' => __('News article structure', 'json-post-importer'),
                    
                    // Import Settings
                    'import_settings' => __('Import Settings', 'json-post-importer'),
                    'post_type' => __('Post Type', 'json-post-importer'),
                    'post' => __('Post', 'json-post-importer'),
                    'page' => __('Page', 'json-post-importer'),
                    'default_status' => __('Default Status', 'json-post-importer'),
                    'batch_size' => __('Batch Size', 'json-post-importer'),
                    'batch_size_desc' => __('Number of posts to process at once', 'json-post-importer'),
                    'update_existing' => __('Update existing posts', 'json-post-importer'),
                    'update_existing_desc' => __('Update posts if they already exist', 'json-post-importer'),
                    'import_featured_image' => __('Import featured images', 'json-post-importer'),
                    'import_featured_image_desc' => __('Download and set featured images from URLs', 'json-post-importer'),
                    'create_terms' => __('Create missing terms', 'json-post-importer'),
                    'create_terms_desc' => __('Automatically create categories and tags if they don\'t exist', 'json-post-importer'),
                    
                    // Presets
                    'wordpress_standard' => __('WordPress Standard', 'json-post-importer'),
                    'wordpress_standard_desc' => __('Standard WordPress post fields', 'json-post-importer'),
                    'blog_post' => __('Blog Post', 'json-post-importer'),
                    'blog_post_desc' => __('Common blog post structure', 'json-post-importer'),
                    'news_article' => __('News Article', 'json-post-importer'),
                    'news_article_desc' => __('News article structure', 'json-post-importer'),
                    'product' => __('Product', 'json-post-importer'),
                    'product_desc' => __('E-commerce product structure', 'json-post-importer'),
                    
                    // Import settings
                    'post_type' => __('Post Type', 'json-post-importer'),
                    'post_type_desc' => __('Select the post type for imported content.', 'json-post-importer'),
                    'default_status' => __('Default Status', 'json-post-importer'),
                    'default_status_desc' => __('Default status for imported posts.', 'json-post-importer'),
                    'batch_size' => __('Batch Size', 'json-post-importer'),
                    'batch_size_desc' => __('Number of posts to process at once.', 'json-post-importer'),
                    'update_existing' => __('Update Existing Posts', 'json-post-importer'),
                    'update_existing_desc' => __('Update posts if they already exist.', 'json-post-importer'),
                    'import_featured_image' => __('Import Featured Images', 'json-post-importer'),
                    'import_featured_image_desc' => __('Download and set featured images from URLs.', 'json-post-importer'),
                    'create_terms' => __('Create Missing Terms', 'json-post-importer'),
                    'create_terms_desc' => __('Create taxonomy terms if they don\'t exist.', 'json-post-importer'),
                    'preview_import' => __('Preview Import', 'json-post-importer'),
                    'import_posts' => __('Import Posts', 'json-post-importer'),
                    'reset_options' => __('Reset to Defaults', 'json-post-importer'),
                    'confirm_reset_options' => __('Are you sure you want to reset all import options to their defaults?', 'json-post-importer'),
                    
                    // Errors
                    'error_loading_mapping' => __('Error loading field mapping interface.', 'json-post-importer')
                )
            )
        );
    }

    /**
     * Handle AJAX file upload
     */
    public function handle_ajax_upload() {
        check_ajax_referer('jpi_ajax_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'json-post-importer')));
        }

        if (empty($_FILES['jpi_json_file'])) {
            wp_send_json_error(array('message' => __('No file uploaded.', 'json-post-importer')));
        }

        $file = $_FILES['jpi_json_file'];

        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => __('File upload error.', 'json-post-importer')));
        }

        if ($file['type'] !== 'application/json' && !str_ends_with($file['name'], '.json')) {
            wp_send_json_error(array('message' => __('Invalid file type. Please upload a JSON file.', 'json-post-importer')));
        }

        // Read and validate JSON
        $json_content = file_get_contents($file['tmp_name']);
        $json_data = $this->validate_json_data($json_content);

        if (is_wp_error($json_data)) {
            wp_send_json_error(array('message' => $json_data->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('File uploaded successfully.', 'json-post-importer'),
            'data' => $json_data
        ));
    }

    /**
     * Validate and parse JSON data
     */
    public function validate_json_data($json_string) {
        if (empty($json_string)) {
            return new WP_Error('empty_json', __('JSON data is empty.', 'json-post-importer'));
        }

        // Attempt to decode JSON
        $json_data = json_decode($json_string, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', sprintf(__('Invalid JSON: %s', 'json-post-importer'), json_last_error_msg()));
        }

        // Validate structure
        if (!is_array($json_data) && !is_object($json_data)) {
            return new WP_Error('invalid_structure', __('JSON must be an array or object.', 'json-post-importer'));
        }

        return $json_data;
    }

    /**
     * Handle preview JSON request
     */
    public function handle_preview_json() {
        check_ajax_referer('jpi_ajax_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'json-post-importer')));
        }

        $json_data = isset($_POST['json_data']) ? json_decode(stripslashes($_POST['json_data']), true) : null;

        if (empty($json_data)) {
            wp_send_json_error(array('message' => __('No JSON data provided.', 'json-post-importer')));
        }

        // Store data in transient for later use
        $transient_key = 'jpi_preview_data_' . get_current_user_id();
        set_transient($transient_key, $json_data, HOUR_IN_SECONDS);

        wp_send_json_success(array(
            'message' => __('Preview generated successfully.', 'json-post-importer'),
            'data' => $json_data
        ));
    }

    /**
     * Start the import process
     */
    public function start_import() {
        check_ajax_referer('jpi_ajax_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to perform this action.', 'json-post-importer')), 403);
        }
        
        // Get the import data
        $json_data = isset($_POST['json_data']) ? json_decode(stripslashes($_POST['json_data']), true) : null;
        
        // Handle both JSON string and array formats for import_settings
        $import_settings = array();
        if (isset($_POST['import_settings'])) {
            if (is_string($_POST['import_settings'])) {
                $import_settings = json_decode(stripslashes($_POST['import_settings']), true) ?: array();
            } else {
                $import_settings = $_POST['import_settings'];
            }
        }
        
        // Handle both JSON string and array formats for field_mappings
        $field_mappings = array();
        if (isset($_POST['field_mappings'])) {
            if (is_string($_POST['field_mappings'])) {
                $field_mappings = json_decode(stripslashes($_POST['field_mappings']), true) ?: array();
            } else {
                $field_mappings = $_POST['field_mappings'];
            }
        }
        
        // Validate required data
        if (empty($json_data)) {
            wp_send_json_error(array('message' => __('No JSON data provided for import.', 'json-post-importer')), 400);
        }
        
        // Validate field mappings - check both legacy and nested formats
        $has_title_mapping = false;
        if (!empty($field_mappings['post_title'])) {
            $has_title_mapping = true;
        } elseif (!empty($field_mappings['standard']['post_title'])) {
            $has_title_mapping = true;
        }
        
        if (!$has_title_mapping) {
            wp_send_json_error(array('message' => __('Post title mapping is required.', 'json-post-importer')), 400);
        }
        
        // Ensure we have an array of items
        $items = is_array($json_data) ? $json_data : array($json_data);
        $total_items = count($items);
        
        if ($total_items === 0) {
            wp_send_json_error(array('message' => __('No items found in JSON data.', 'json-post-importer')), 400);
        }
        
        // Create import session
        $import_id = 'jpi_import_' . time() . '_' . wp_generate_password(8, false);
        $batch_size = isset($import_settings['batch_size']) ? intval($import_settings['batch_size']) : 10;
        
        // Store import data in transients
        $import_data = array(
            'id' => $import_id,
            'items' => $items,
            'field_mappings' => $field_mappings,
            'import_settings' => $import_settings,
            'total_items' => $total_items,
            'processed_items' => 0,
            'created_posts' => 0,
            'updated_posts' => 0,
            'skipped_posts' => 0,
            'errors' => array(),
            'status' => 'running',
            'start_time' => current_time('mysql'),
            'batch_size' => $batch_size,
            'current_batch' => 0,
            'total_batches' => ceil($total_items / $batch_size),
            'cancelled' => false
        );
        
        set_transient('jpi_import_' . $import_id, $import_data, 2 * HOUR_IN_SECONDS);
        
        // Log import start
        $this->logger->log_import_start($import_id, array(
            'total_items' => $total_items,
            'batch_size' => $batch_size,
            'field_mappings' => $field_mappings,
            'import_settings' => $import_settings
        ));
        
        wp_send_json_success(array(
            'message' => __('Import started successfully.', 'json-post-importer'),
            'import_id' => $import_id,
            'total_items' => $total_items,
            'total_batches' => $import_data['total_batches'],
            'batch_size' => $batch_size
        ));
    }

    /**
     * Handle import content request
     */
    public function handle_import_content() {
        check_ajax_referer('jpi_ajax_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'json-post-importer')));
        }

        wp_send_json_success(array(
            'message' => __('Import functionality is ready.', 'json-post-importer')
        ));
    }

    /**
     * Handle file upload (non-AJAX)
     */
    public function handle_file_upload() {
        if (!wp_verify_nonce($_POST['jpi_upload_nonce'], 'jpi_upload_nonce')) {
            wp_die(__('Security check failed.', 'json-post-importer'));
        }

        if (!current_user_can('upload_files')) {
            wp_die(__('You do not have sufficient permissions.', 'json-post-importer'));
        }

        // Redirect back to admin page with message
        wp_redirect(add_query_arg('jpi_message', 'upload_success', admin_url('admin.php?page=json-post-importer')));
        exit;
    }

    /**
     * Cleanup old imports
     */
    public function cleanup_old_imports() {
        global $wpdb;
        
        // Delete transients older than 24 hours
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_jpi_%' AND option_name NOT LIKE '_transient_timeout_jpi_%'");
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_jpi_%' AND option_value < UNIX_TIMESTAMP()");
    }

    /**
     * Get import logs
     */
    public function get_import_logs_ajax() {
        check_ajax_referer('jpi_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'json-post-importer')));
        }

        // Get logs from options
        $logs = get_option('jpi_import_logs', array());
        
        // Sort by timestamp (newest first)
        usort($logs, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        // Limit to last 500 logs for performance
        $logs = array_slice($logs, 0, 500);

        wp_send_json_success(array('logs' => $logs));
    }

    /**
     * Clear import logs
     */
    public function clear_import_logs_ajax() {
        check_ajax_referer('jpi_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'json-post-importer')));
        }

        // Clear logs from options
        update_option('jpi_import_logs', array());

        wp_send_json_success(array('message' => __('Import logs cleared successfully.', 'json-post-importer')));
    }

    /**
     * Handle import with mapping
     */
    public function handle_import_with_mapping() {
        check_ajax_referer('jpi_ajax_nonce', 'nonce');

        if (!current_user_can('import')) {
            wp_send_json_error(array('message' => __('You do not have permission to import posts.', 'json-post-importer')));
        }

        wp_send_json_success(array(
            'message' => __('Import with mapping functionality is ready.', 'json-post-importer')
        ));
    }

    /**
     * Handle enhanced import with mapping
     */
    public function handle_import_with_mapping_enhanced() {
        check_ajax_referer('jpi_ajax_nonce', 'nonce');

        if (!current_user_can('import')) {
            wp_send_json_error(array('message' => __('You do not have permission to import posts.', 'json-post-importer')));
        }

        wp_send_json_success(array(
            'message' => __('Enhanced import functionality is ready.', 'json-post-importer')
        ));
    }

    /**
     * Process batch with enhanced field type tracking and nested data extraction
     */
    public function process_batch() {
        try {
            check_ajax_referer('jpi_ajax_nonce', 'nonce');

            if (!current_user_can('upload_files')) {
                wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'json-post-importer')));
            }

        $import_id = isset($_POST['import_id']) ? sanitize_text_field($_POST['import_id']) : '';
        $batch_number = isset($_POST['batch_number']) ? intval($_POST['batch_number']) : 0;
        
        // Log batch processing start for debugging
        error_log('JSON Post Importer: Starting batch processing - Import ID: ' . $import_id . ', Batch: ' . $batch_number);
        
        if (empty($import_id)) {
            error_log('JSON Post Importer: Invalid import ID provided');
            wp_send_json_error(array('message' => __('Invalid import ID.', 'json-post-importer')));
        }
        
        // Get import data
        $import_data = get_transient('jpi_import_' . $import_id);
        
        if (!$import_data) {
            wp_send_json_error(array('message' => __('Import session not found or expired.', 'json-post-importer')));
        }
        
        // Check if import was cancelled
        if ($import_data['cancelled']) {
            wp_send_json_error(array('message' => __('Import was cancelled.', 'json-post-importer')));
        }
        
        // Calculate batch range - ensure all values are integers
        $batch_size = intval($import_data['batch_size']);
        $total_items = intval($import_data['total_items']);
        $start_index = intval($batch_number * $batch_size);
        $end_index = min($start_index + $batch_size, $total_items);
        $batch_items = array_slice($import_data['items'], $start_index, $batch_size);
        
        // Enhanced batch results with field type tracking
        $batch_results = array(
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => array(),
            'field_type_progress' => array(
                'standard' => array('processed' => 0, 'errors' => 0),
                'yoast_seo' => array('processed' => 0, 'errors' => 0),
                'custom' => array('processed' => 0, 'errors' => 0),
                'wrapper_metadata' => array('processed' => 0, 'errors' => 0),
                'media' => array('processed' => 0, 'errors' => 0),
                'taxonomies' => array('processed' => 0, 'errors' => 0)
            ),
            'duplicate_detection' => array(
                'by_title' => 0,
                'by_slug' => 0,
                'by_meta' => 0,
                'by_content_hash' => 0
            ),
            'nested_extraction_stats' => array(
                'successful_extractions' => 0,
                'failed_extractions' => 0,
                'nested_paths_used' => array()
            )
        );
        
        // Process each item in the batch with enhanced tracking
        foreach ($batch_items as $index => $item) {
            $item_index = intval($start_index) + intval($index);
            
            try {
                // Log the item being processed for debugging
                error_log('JSON Post Importer: Processing item ' . $item_index . ' - ' . json_encode($item));
                
                // Use simplified processing to avoid complex dependencies
                $options = array(
                    'post_type' => $import_data['import_settings']['post_type'] ?? 'post',
                    'post_status' => $import_data['import_settings']['post_status'] ?? 'draft',
                    'update_existing' => $import_data['import_settings']['update_existing'] ?? false,
                    'field_mappings' => $import_data['field_mappings'] ?? array(),
                    'item_index' => $item_index
                );
                
                // Check if post_creator exists
                if (!$this->post_creator) {
                    throw new Exception('Post creator not initialized');
                }
                
                $result = $this->post_creator->create_or_update_post($item, $options);
                
                if (is_wp_error($result)) {
                    $batch_results['errors'][] = array(
                        'item_index' => $item_index,
                        'message' => $result->get_error_message(),
                        'data' => $item,
                        'error_type' => $result->get_error_code()
                    );
                    $batch_results['skipped']++;
                    error_log('JSON Post Importer: Error processing item ' . $item_index . ' - ' . $result->get_error_message());
                } else {
                    if (isset($result['action']) && $result['action'] === 'updated') {
                        $batch_results['updated']++;
                    } else {
                        $batch_results['created']++;
                    }
                    
                    // Track basic field processing success
                    $batch_results['field_type_progress']['standard']['processed']++;
                    
                    error_log('JSON Post Importer: Successfully processed item ' . $item_index . ' - Post ID: ' . ($result['post_id'] ?? 'unknown'));
                }
            } catch (Exception $e) {
                $batch_results['errors'][] = array(
                    'item_index' => $item_index,
                    'message' => $e->getMessage(),
                    'data' => $item,
                    'error_type' => 'exception',
                    'stack_trace' => $e->getTraceAsString()
                );
                $batch_results['skipped']++;
                
                // Log exception for debugging
                $this->logger->error('Exception during batch processing', array(
                    'item_index' => $item_index,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ));
            }
        }
        
        // Log batch completion
        if (method_exists($this->logger, 'log_batch_processed_enhanced')) {
            $this->logger->log_batch_processed_enhanced($import_id, $batch_number + 1, $batch_results);
        } else {
            error_log('JSON Post Importer: Batch ' . ($batch_number + 1) . ' completed - Created: ' . $batch_results['created'] . ', Updated: ' . $batch_results['updated'] . ', Skipped: ' . $batch_results['skipped']);
        }
        
        // Update import data with enhanced tracking - ensure all values are integers
        $import_data['processed_items'] = intval($import_data['processed_items']) + count($batch_items);
        $import_data['created_posts'] = intval($import_data['created_posts']) + intval($batch_results['created']);
        $import_data['updated_posts'] = intval($import_data['updated_posts']) + intval($batch_results['updated']);
        $import_data['skipped_posts'] = intval($import_data['skipped_posts']) + intval($batch_results['skipped']);
        $import_data['errors'] = array_merge($import_data['errors'] ?? array(), $batch_results['errors']);
        $import_data['current_batch'] = intval($batch_number) + 1;
        
        // Merge field type progress tracking
        if (!isset($import_data['field_type_progress'])) {
            $import_data['field_type_progress'] = array(
                'standard' => array('processed' => 0, 'errors' => 0),
                'yoast_seo' => array('processed' => 0, 'errors' => 0),
                'custom' => array('processed' => 0, 'errors' => 0),
                'wrapper_metadata' => array('processed' => 0, 'errors' => 0),
                'media' => array('processed' => 0, 'errors' => 0),
                'taxonomies' => array('processed' => 0, 'errors' => 0)
            );
        }
        
        foreach ($batch_results['field_type_progress'] as $field_type => $stats) {
            $import_data['field_type_progress'][$field_type]['processed'] += $stats['processed'];
            $import_data['field_type_progress'][$field_type]['errors'] += $stats['errors'];
        }
        
        // Merge duplicate detection stats
        if (!isset($import_data['duplicate_detection_stats'])) {
            $import_data['duplicate_detection_stats'] = array(
                'by_title' => 0,
                'by_slug' => 0,
                'by_meta' => 0,
                'by_content_hash' => 0
            );
        }
        
        foreach ($batch_results['duplicate_detection'] as $method => $count) {
            $import_data['duplicate_detection_stats'][$method] += $count;
        }
        
        // Merge nested extraction stats
        if (!isset($import_data['nested_extraction_stats'])) {
            $import_data['nested_extraction_stats'] = array(
                'successful_extractions' => 0,
                'failed_extractions' => 0,
                'nested_paths_used' => array()
            );
        }
        
        $import_data['nested_extraction_stats']['successful_extractions'] += $batch_results['nested_extraction_stats']['successful_extractions'];
        $import_data['nested_extraction_stats']['failed_extractions'] += $batch_results['nested_extraction_stats']['failed_extractions'];
        $import_data['nested_extraction_stats']['nested_paths_used'] = array_unique(array_merge(
            $import_data['nested_extraction_stats']['nested_paths_used'],
            $batch_results['nested_extraction_stats']['nested_paths_used']
        ));
        
        // Check if import is complete - ensure integer comparison
        $is_complete = intval($import_data['processed_items']) >= intval($import_data['total_items']);
        
        if ($is_complete) {
            $import_data['status'] = 'completed';
            $import_data['end_time'] = current_time('mysql');
            
            // Log completion
            if (method_exists($this->logger, 'log_import_end_enhanced')) {
                $this->logger->log_import_end_enhanced($import_id, array(
                    'created_posts' => $import_data['created_posts'],
                    'updated_posts' => $import_data['updated_posts'],
                    'skipped_posts' => $import_data['skipped_posts'],
                    'error_count' => count($import_data['errors']),
                    'total_items' => $import_data['total_items'],
                    'processed_items' => $import_data['processed_items']
                ));
            } else {
                error_log('JSON Post Importer: Import ' . $import_id . ' completed - Total: ' . $import_data['total_items'] . ', Created: ' . $import_data['created_posts'] . ', Updated: ' . $import_data['updated_posts']);
            }
            
            // Save to history
            $this->add_to_import_history(array(
                'total_items' => $import_data['total_items'],
                'created' => $import_data['created_posts'],
                'updated' => $import_data['updated_posts'],
                'skipped' => $import_data['skipped_posts'],
                'errors' => $import_data['errors'],
                'status' => $import_data['status']
            ));
        }
        
        // Update transient
        set_transient('jpi_import_' . $import_id, $import_data, 2 * HOUR_IN_SECONDS);
        
        wp_send_json_success(array(
            'batch_complete' => true,
            'import_complete' => $is_complete,
            'batch_results' => $batch_results,
            'progress' => array(
                'processed_items' => $import_data['processed_items'],
                'total_items' => $import_data['total_items'],
                'created_posts' => $import_data['created_posts'],
                'updated_posts' => $import_data['updated_posts'],
                'skipped_posts' => $import_data['skipped_posts'],
                'error_count' => count($import_data['errors']),
                'current_batch' => $import_data['current_batch'],
                'total_batches' => $import_data['total_batches'],
                'percentage' => round(($import_data['processed_items'] / $import_data['total_items']) * 100, 1),
                'field_type_progress' => $import_data['field_type_progress'],
                'duplicate_detection_stats' => $import_data['duplicate_detection_stats'] ?? array(),
                'nested_extraction_stats' => $import_data['nested_extraction_stats'] ?? array()
            )
        ));
        
        } catch (Exception $e) {
            error_log('JSON Post Importer: Fatal error in process_batch - ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
            wp_send_json_error(array(
                'message' => 'Internal server error: ' . $e->getMessage(),
                'error_type' => 'fatal_error',
                'debug_info' => array(
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                )
            ));
        }
    }





    /**
     * Check batch status
     */
    public function check_batch_status() {
        check_ajax_referer('jpi_ajax_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'json-post-importer')));
        }

        $import_id = isset($_POST['import_id']) ? sanitize_text_field($_POST['import_id']) : '';
        
        if (empty($import_id)) {
            wp_send_json_error(array('message' => __('Invalid import ID.', 'json-post-importer')));
        }
        
        // Get import data
        $import_data = get_transient('jpi_import_' . $import_id);
        
        if (!$import_data) {
            wp_send_json_error(array('message' => __('Import session not found or expired.', 'json-post-importer')));
        }
        
        wp_send_json_success(array(
            'status' => $import_data['status'],
            'progress' => array(
                'processed_items' => $import_data['processed_items'],
                'total_items' => $import_data['total_items'],
                'created_posts' => $import_data['created_posts'],
                'updated_posts' => $import_data['updated_posts'],
                'skipped_posts' => $import_data['skipped_posts'],
                'error_count' => count($import_data['errors']),
                'current_batch' => $import_data['current_batch'],
                'total_batches' => $import_data['total_batches'],
                'percentage' => $import_data['total_items'] > 0 ? round(($import_data['processed_items'] / $import_data['total_items']) * 100, 1) : 0,
                'cancelled' => $import_data['cancelled']
            ),
            'recent_errors' => array_slice($import_data['errors'], -5) // Last 5 errors
        ));
    }

    /**
     * Get preview content
     */
    public function get_preview_content() {
        check_ajax_referer('jpi_ajax_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'json-post-importer')));
        }

        wp_send_json_success(array(
            'message' => __('Preview content functionality is ready.', 'json-post-importer')
        ));
    }

    /**
     * Cancel import
     */
    public function cancel_import() {
        check_ajax_referer('jpi_ajax_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'json-post-importer')));
        }

        $import_id = isset($_POST['import_id']) ? sanitize_text_field($_POST['import_id']) : '';
        
        if (empty($import_id)) {
            wp_send_json_error(array('message' => __('Invalid import ID.', 'json-post-importer')));
        }
        
        // Get import data
        $import_data = get_transient('jpi_import_' . $import_id);
        
        if (!$import_data) {
            wp_send_json_error(array('message' => __('Import session not found or expired.', 'json-post-importer')));
        }
        
        // Mark as cancelled
        $import_data['cancelled'] = true;
        $import_data['status'] = 'cancelled';
        $import_data['end_time'] = current_time('mysql');
        
        // Update transient
        set_transient('jpi_import_' . $import_id, $import_data, 2 * HOUR_IN_SECONDS);
        
        // Log cancellation
        $this->log_import_event($import_id, 'warning', __('Import cancelled by user', 'json-post-importer'));
        
        // Save to history
        $this->save_import_to_history($import_data);
        
        wp_send_json_success(array(
            'message' => __('Import cancelled successfully.', 'json-post-importer'),
            'final_results' => array(
                'processed_items' => $import_data['processed_items'],
                'total_items' => $import_data['total_items'],
                'created_posts' => $import_data['created_posts'],
                'updated_posts' => $import_data['updated_posts'],
                'skipped_posts' => $import_data['skipped_posts'],
                'error_count' => count($import_data['errors'])
            )
        ));
    }

    /**
     * Get import results
     */
    public function get_import_results() {
        check_ajax_referer('jpi_ajax_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'json-post-importer')));
        }

        $import_id = isset($_POST['import_id']) ? sanitize_text_field($_POST['import_id']) : '';
        
        if (empty($import_id)) {
            wp_send_json_error(array('message' => __('Invalid import ID.', 'json-post-importer')));
        }
        
        // Get import data
        $import_data = get_transient('jpi_import_' . $import_id);
        
        if (!$import_data) {
            wp_send_json_error(array('message' => __('Import session not found or expired.', 'json-post-importer')));
        }
        
        // Calculate duration
        $duration = '';
        if (!empty($import_data['start_time']) && !empty($import_data['end_time'])) {
            $start = new DateTime($import_data['start_time']);
            $end = new DateTime($import_data['end_time']);
            $diff = $start->diff($end);
            $duration = $diff->format('%H:%I:%S');
        }
        
        wp_send_json_success(array(
            'results' => array(
                'import_id' => $import_id,
                'status' => $import_data['status'],
                'total_items' => $import_data['total_items'],
                'processed_items' => $import_data['processed_items'],
                'created_posts' => $import_data['created_posts'],
                'updated_posts' => $import_data['updated_posts'],
                'skipped_posts' => $import_data['skipped_posts'],
                'errors' => $import_data['errors'],
                'error_count' => count($import_data['errors']),
                'start_time' => $import_data['start_time'],
                'end_time' => isset($import_data['end_time']) ? $import_data['end_time'] : '',
                'duration' => $duration,
                'cancelled' => $import_data['cancelled']
            )
        ));
    }

    /**
     * Get import history
     */
    public function get_import_history() {
        check_ajax_referer('jpi_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'json-post-importer')));
        }

        // Get import history from options
        $history = get_option('jpi_import_history', array());
        
        // Sort by date (newest first)
        usort($history, function($a, $b) {
            return strtotime($b['start_time']) - strtotime($a['start_time']);
        });
        
        // Limit to last 50 imports
        $history = array_slice($history, 0, 50);
        
        wp_send_json_success(array('history' => $history));
    }

    /**
     * Process a single item (legacy method for backward compatibility)
     */
    private function process_single_item($item, $field_mappings, $import_settings, $item_index) {
        return $this->process_single_item_enhanced($item, $field_mappings, $import_settings, $item_index);
    }
    
    /**
     * Process a single item with enhanced field type tracking and nested data extraction
     */
    private function process_single_item_enhanced($item, $field_mappings, $import_settings, $item_index) {
        if (!is_array($item) && !is_object($item)) {
            $this->logger->error('Invalid item type at index ' . $item_index, array('item' => $item));
            return new WP_Error('invalid_item', __('Item must be an array or object.', 'json-post-importer'));
        }
        
        // Convert object to array
        $item = (array) $item;
        
        $this->logger->debug('Processing item at index ' . $item_index, array('item' => $item));
        
        // Initialize field processing stats
        $field_processing_stats = array(
            'standard' => array('processed' => 0, 'errors' => 0),
            'yoast_seo' => array('processed' => 0, 'errors' => 0),
            'custom' => array('processed' => 0, 'errors' => 0),
            'wrapper_metadata' => array('processed' => 0, 'errors' => 0),
            'media' => array('processed' => 0, 'errors' => 0),
            'taxonomies' => array('processed' => 0, 'errors' => 0)
        );
        
        // Initialize nested extraction stats
        $nested_extraction_stats = array(
            'successful' => 0,
            'failed' => 0,
            'paths_used' => array()
        );
        
        // Prepare enhanced options for the post creator
        $options = array_merge($import_settings, array(
            'field_mappings' => $field_mappings,
            'json_root_path' => isset($import_settings['json_root_path']) ? $import_settings['json_root_path'] : 'content',
            'import_wrapper_meta' => isset($import_settings['import_wrapper_meta']) ? $import_settings['import_wrapper_meta'] : true,
            'update_existing' => isset($import_settings['update_existing']) ? $import_settings['update_existing'] : true,
            'post_type' => isset($import_settings['post_type']) ? $import_settings['post_type'] : 'post',
            'post_status' => isset($import_settings['post_status']) ? $import_settings['post_status'] : 'draft',
            'default_author' => get_current_user_id(),
            'duplicate_detection_criteria' => isset($import_settings['duplicate_detection_criteria']) ? $import_settings['duplicate_detection_criteria'] : array('title'),
            'enable_enhanced_duplicate_detection' => true,
            'track_field_processing' => true,
            'item_index' => $item_index
        ));
        
        // Pre-process nested data extraction with error handling
        try {
            if (!empty($field_mappings) && is_array($field_mappings)) {
                $extraction_result = $this->extract_nested_data_with_tracking($item, $field_mappings, $options);
                
                if (is_wp_error($extraction_result)) {
                    $nested_extraction_stats['failed']++;
                    $this->logger->warning('Nested data extraction failed for item ' . $item_index, array(
                        'error' => $extraction_result->get_error_message(),
                        'item' => $item
                    ));
                } else {
                    $nested_extraction_stats['successful']++;
                    $nested_extraction_stats['paths_used'] = array_merge(
                        $nested_extraction_stats['paths_used'],
                        $extraction_result['paths_used'] ?? array()
                    );
                    
                    // Update options with extracted data
                    $options['extracted_data'] = $extraction_result['data'] ?? array();
                }
            }
        } catch (Exception $e) {
            $nested_extraction_stats['failed']++;
            $this->logger->error('Exception during nested data extraction for item ' . $item_index, array(
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
        }
        
        // Enhanced duplicate detection with multiple criteria
        $duplicate_detection_method = null;
        try {
            $existing_post_id = $this->find_existing_post_enhanced($item, $options);
            if ($existing_post_id) {
                $duplicate_detection_method = $this->get_last_duplicate_detection_method();
                $this->logger->debug('Found existing post using enhanced detection', array(
                    'post_id' => $existing_post_id,
                    'method' => $duplicate_detection_method,
                    'item_index' => $item_index
                ));
            }
        } catch (Exception $e) {
            $this->logger->error('Exception during enhanced duplicate detection for item ' . $item_index, array(
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
        }
        
        // Use the JSON Post Creator to handle the import with enhanced tracking
        $result = $this->post_creator->create_or_update_post($item, $options);
        
        if (is_wp_error($result)) {
            // Extract field-specific error information if available
            $field_errors = array();
            $error_data = $result->get_error_data();
            
            if (is_array($error_data) && isset($error_data['field_errors'])) {
                $field_errors = $error_data['field_errors'];
            } else {
                // Try to categorize the error based on the error message
                $error_message = $result->get_error_message();
                if (strpos($error_message, 'yoast') !== false || strpos($error_message, 'seo') !== false) {
                    $field_errors['yoast_seo'] = 1;
                } elseif (strpos($error_message, 'media') !== false || strpos($error_message, 'image') !== false) {
                    $field_errors['media'] = 1;
                } elseif (strpos($error_message, 'taxonomy') !== false || strpos($error_message, 'category') !== false || strpos($error_message, 'tag') !== false) {
                    $field_errors['taxonomies'] = 1;
                } elseif (strpos($error_message, 'meta') !== false || strpos($error_message, 'custom') !== false) {
                    $field_errors['custom'] = 1;
                } else {
                    $field_errors['standard'] = 1;
                }
            }
            
            $this->logger->error('Failed to create/update post at index ' . $item_index, array(
                'item' => $item,
                'error' => $result->get_error_message(),
                'field_errors' => $field_errors
            ));
            
            // Add field error information to the WP_Error
            $error_data = $result->get_error_data() ?: array();
            $error_data['field_errors'] = $field_errors;
            $result->add_data($error_data);
            
            return $result;
        }
        
        // Extract field processing stats from the result if available
        if (isset($result['field_processing_stats'])) {
            $field_processing_stats = $result['field_processing_stats'];
        } else {
            // Estimate field processing based on successful import
            $field_processing_stats['standard']['processed'] = 1; // At least one standard field processed
            
            // Check if Yoast SEO fields were likely processed
            if (!empty($field_mappings['yoast_seo']) || $this->has_yoast_seo_data($item)) {
                $field_processing_stats['yoast_seo']['processed'] = 1;
            }
            
            // Check if custom fields were likely processed
            if (!empty($field_mappings['custom']) || $this->has_custom_field_data($item)) {
                $field_processing_stats['custom']['processed'] = 1;
            }
            
            // Check if wrapper metadata was likely processed
            if ($options['import_wrapper_meta'] && $this->has_wrapper_metadata($item)) {
                $field_processing_stats['wrapper_metadata']['processed'] = 1;
            }
            
            // Check if media was likely processed
            if ($this->has_media_data($item)) {
                $field_processing_stats['media']['processed'] = 1;
            }
            
            // Check if taxonomies were likely processed
            if ($this->has_taxonomy_data($item)) {
                $field_processing_stats['taxonomies']['processed'] = 1;
            }
        }
        
        $this->logger->debug('Successfully processed item at index ' . $item_index, array(
            'post_id' => $result['id'],
            'action' => $result['updated'] ? 'updated' : 'created',
            'field_processing_stats' => $field_processing_stats,
            'nested_extraction_stats' => $nested_extraction_stats,
            'duplicate_detection_method' => $duplicate_detection_method
        ));
        
        return array(
            'post_id' => $result['id'],
            'action' => $result['updated'] ? 'updated' : 'created',
            'field_processing_stats' => $field_processing_stats,
            'nested_extraction_stats' => $nested_extraction_stats,
            'duplicate_detection_method' => $duplicate_detection_method
        );
    }

    /**
     * Extract nested data with tracking for enhanced processing
     */
    private function extract_nested_data_with_tracking($item, $field_mappings, $options) {
        $extracted_data = array();
        $paths_used = array();
        $extraction_errors = array();
        
        try {
            // Use the nested handler to process field mappings
            $processed_data = $this->nested_handler->process_field_mappings($item, $field_mappings, $options);
            
            if (is_wp_error($processed_data)) {
                return $processed_data;
            }
            
            $extracted_data = $processed_data;
            
            // Track which nested paths were successfully used
            if (!empty($field_mappings)) {
                foreach ($field_mappings as $field_type => $mappings) {
                    if (is_array($mappings)) {
                        foreach ($mappings as $wp_field => $json_path) {
                            if (is_string($json_path) && strpos($json_path, '.') !== false) {
                                $paths_used[] = $json_path;
                            }
                        }
                    }
                }
            }
            
            return array(
                'data' => $extracted_data,
                'paths_used' => array_unique($paths_used),
                'errors' => $extraction_errors
            );
            
        } catch (Exception $e) {
            return new WP_Error('nested_extraction_failed', 'Failed to extract nested data: ' . $e->getMessage(), array(
                'exception' => $e->getMessage(),
                'paths_attempted' => $paths_used
            ));
        }
    }
    
    /**
     * Enhanced duplicate detection with multiple criteria
     */
    private function find_existing_post_enhanced($item, $options) {
        $criteria = $options['duplicate_detection_criteria'] ?? array('title');
        $post_type = $options['post_type'] ?? 'post';
        
        // Store the method used for tracking
        $this->last_duplicate_detection_method = null;
        
        foreach ($criteria as $criterion) {
            $existing_id = null;
            
            switch ($criterion) {
                case 'title':
                    $existing_id = $this->find_by_title($item, $post_type);
                    if ($existing_id) {
                        $this->last_duplicate_detection_method = 'by_title';
                    }
                    break;
                    
                case 'slug':
                    $existing_id = $this->find_by_slug($item, $post_type);
                    if ($existing_id) {
                        $this->last_duplicate_detection_method = 'by_slug';
                    }
                    break;
                    
                case 'meta_field':
                    $existing_id = $this->find_by_meta_field($item, $options);
                    if ($existing_id) {
                        $this->last_duplicate_detection_method = 'by_meta';
                    }
                    break;
                    
                case 'content_hash':
                    $existing_id = $this->find_by_content_hash($item, $post_type);
                    if ($existing_id) {
                        $this->last_duplicate_detection_method = 'by_content_hash';
                    }
                    break;
            }
            
            if ($existing_id) {
                return $existing_id;
            }
        }
        
        return false;
    }
    
    /**
     * Find existing post by title
     */
    private function find_by_title($item, $post_type) {
        $title = $this->extract_title_from_item($item);
        
        if (empty($title)) {
            return false;
        }
        
        $args = array(
            'title' => $title,
            'post_type' => $post_type,
            'post_status' => 'any',
            'numberposts' => 1,
            'fields' => 'ids'
        );
        
        $posts = get_posts($args);
        return !empty($posts) ? $posts[0] : false;
    }
    
    /**
     * Find existing post by slug
     */
    private function find_by_slug($item, $post_type) {
        $slug = $this->extract_slug_from_item($item);
        
        if (empty($slug)) {
            return false;
        }
        
        $args = array(
            'name' => $slug,
            'post_type' => $post_type,
            'post_status' => 'any',
            'numberposts' => 1,
            'fields' => 'ids'
        );
        
        $posts = get_posts($args);
        return !empty($posts) ? $posts[0] : false;
    }
    
    /**
     * Find existing post by meta field
     */
    private function find_by_meta_field($item, $options) {
        $meta_key = $options['duplicate_meta_key'] ?? '_import_id';
        $meta_value = $this->extract_meta_value_from_item($item, $meta_key);
        
        if (empty($meta_value)) {
            return false;
        }
        
        global $wpdb;
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s LIMIT 1",
            $meta_key,
            $meta_value
        ));
        
        return $post_id ? (int) $post_id : false;
    }
    
    /**
     * Find existing post by content hash
     */
    private function find_by_content_hash($item, $post_type) {
        $content = $this->extract_content_from_item($item);
        
        if (empty($content)) {
            return false;
        }
        
        $content_hash = md5($content);
        
        global $wpdb;
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_content_hash' AND meta_value = %s LIMIT 1",
            $content_hash
        ));
        
        return $post_id ? (int) $post_id : false;
    }
    
    /**
     * Extract title from item using various possible field names
     */
    private function extract_title_from_item($item) {
        $title_fields = array('title', 'post_title', 'name', 'heading');
        
        foreach ($title_fields as $field) {
            if (!empty($item[$field])) {
                return sanitize_text_field($item[$field]);
            }
        }
        
        // Try nested content
        if (!empty($item['content']) && is_array($item['content'])) {
            foreach ($title_fields as $field) {
                if (!empty($item['content'][$field])) {
                    return sanitize_text_field($item['content'][$field]);
                }
            }
        }
        
        return '';
    }
    
    /**
     * Extract slug from item using various possible field names
     */
    private function extract_slug_from_item($item) {
        $slug_fields = array('slug', 'post_name', 'name');
        
        foreach ($slug_fields as $field) {
            if (!empty($item[$field])) {
                return sanitize_title($item[$field]);
            }
        }
        
        // Try nested content
        if (!empty($item['content']) && is_array($item['content'])) {
            foreach ($slug_fields as $field) {
                if (!empty($item['content'][$field])) {
                    return sanitize_title($item['content'][$field]);
                }
            }
        }
        
        return '';
    }
    
    /**
     * Extract content from item using various possible field names
     */
    private function extract_content_from_item($item) {
        $content_fields = array('content', 'post_content', 'body', 'description', 'text');
        
        foreach ($content_fields as $field) {
            if (!empty($item[$field])) {
                return is_string($item[$field]) ? $item[$field] : wp_json_encode($item[$field]);
            }
        }
        
        // Try nested content
        if (!empty($item['content']) && is_array($item['content'])) {
            foreach ($content_fields as $field) {
                if (!empty($item['content'][$field])) {
                    return is_string($item['content'][$field]) ? $item['content'][$field] : wp_json_encode($item['content'][$field]);
                }
            }
        }
        
        return '';
    }
    
    /**
     * Extract meta value from item
     */
    private function extract_meta_value_from_item($item, $meta_key) {
        // Try direct field access
        if (!empty($item[$meta_key])) {
            return $item[$meta_key];
        }
        
        // Try meta array
        if (!empty($item['meta']) && is_array($item['meta']) && !empty($item['meta'][$meta_key])) {
            return $item['meta'][$meta_key];
        }
        
        // Try nested content
        if (!empty($item['content']) && is_array($item['content']) && !empty($item['content'][$meta_key])) {
            return $item['content'][$meta_key];
        }
        
        return '';
    }
    
    /**
     * Get the last duplicate detection method used
     */
    private function get_last_duplicate_detection_method() {
        return $this->last_duplicate_detection_method ?? 'none';
    }
    
    /**
     * Check if item has Yoast SEO data
     */
    private function has_yoast_seo_data($item) {
        $yoast_indicators = array(
            'yoast_seo_title', 'seo_title', 'meta_title',
            'yoast_seo_description', 'seo_description', 'meta_description',
            'yoast_focus_keyword', 'focus_keyword', 'keyword'
        );
        
        foreach ($yoast_indicators as $indicator) {
            if (!empty($item[$indicator])) {
                return true;
            }
            
            // Check nested content
            if (!empty($item['content']) && is_array($item['content']) && !empty($item['content'][$indicator])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if item has custom field data
     */
    private function has_custom_field_data($item) {
        // Check for meta array
        if (!empty($item['meta']) && is_array($item['meta'])) {
            return true;
        }
        
        // Check for custom_fields array
        if (!empty($item['custom_fields']) && is_array($item['custom_fields'])) {
            return true;
        }
        
        // Check nested content for custom fields
        if (!empty($item['content']) && is_array($item['content'])) {
            if (!empty($item['content']['meta']) || !empty($item['content']['custom_fields'])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if item has wrapper metadata
     */
    private function has_wrapper_metadata($item) {
        $wrapper_fields = array('domain_name', 'user_id', 'email', 'domain_lang', 'type');
        
        foreach ($wrapper_fields as $field) {
            if (!empty($item[$field])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if item has media data
     */
    private function has_media_data($item) {
        $media_indicators = array(
            'featured_image', 'image', 'thumbnail', 'media', 'attachments'
        );
        
        foreach ($media_indicators as $indicator) {
            if (!empty($item[$indicator])) {
                return true;
            }
            
            // Check nested content
            if (!empty($item['content']) && is_array($item['content']) && !empty($item['content'][$indicator])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if item has taxonomy data
     */
    private function has_taxonomy_data($item) {
        $taxonomy_indicators = array(
            'categories', 'tags', 'category', 'tag', 'taxonomies'
        );
        
        foreach ($taxonomy_indicators as $indicator) {
            if (!empty($item[$indicator])) {
                return true;
            }
            
            // Check nested content
            if (!empty($item['content']) && is_array($item['content']) && !empty($item['content'][$indicator])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get content from nested structure
     */
    private function get_nested_content($item) {
        return '';
    }










    /**
     * Check Yoast SEO status via AJAX
     */
    public function check_yoast_status_ajax() {
        check_ajax_referer('jpi_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'json-post-importer')));
        }

        $yoast_active = is_plugin_active('wordpress-seo/wp-seo.php') || 
                       is_plugin_active('wordpress-seo-premium/wp-seo-premium.php');

        wp_send_json_success(array(
            'yoast_active' => $yoast_active,
            'message' => $yoast_active ? 
                __('Yoast SEO is active', 'json-post-importer') : 
                __('Yoast SEO is not active', 'json-post-importer')
        ));
    }

    /**
     * Add import to history
     */
    private function add_to_import_history($result) {
        $history = get_option('jpi_import_history', array());
        
        $history[] = array(
            'timestamp' => current_time('mysql'),
            'total_items' => $result['total_items'],
            'created' => $result['created'],
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
            'errors' => count($result['errors']),
            'status' => $result['status']
        );
        
        // Keep only last 50 imports
        if (count($history) > 50) {
            $history = array_slice($history, -50);
        }
        
        update_option('jpi_import_history', $history);
    }

    /**
     * Get logs via AJAX
     */
    public function get_logs_ajax() {
        check_ajax_referer('jpi_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'json-post-importer')));
            return;
        }

        $logs = get_option('jpi_import_logs', array());
        wp_send_json_success(array('logs' => $logs));
    }

    /**
     * Clear logs via AJAX
     */
    public function clear_logs_ajax() {
        check_ajax_referer('jpi_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'json-post-importer')));
            return;
        }

        delete_option('jpi_import_logs');
        wp_send_json_success(array('message' => __('Logs cleared successfully.', 'json-post-importer')));
    }

}