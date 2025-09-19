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

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->post_creator = new JSON_Post_Creator();
        $this->logger = new JSON_Post_Importer_Logger();
        
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
        
        // Logger AJAX handlers
        add_action('wp_ajax_jpi_get_logs', array($this, 'get_logs_ajax'));
        add_action('wp_ajax_jpi_clear_logs', array($this, 'clear_logs_ajax'));
        add_action('wp_ajax_jpi_toggle_debug_mode', array($this, 'toggle_debug_mode_ajax'));
        add_action('wp_ajax_jpi_get_log_stats', array($this, 'get_log_stats_ajax'));
        add_action('wp_ajax_jpi_download_log', array($this, 'download_log_ajax'));
        add_action('admin_post_jpi_handle_upload', array($this, 'handle_file_upload'));
        
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

        // Enqueue logs script
        wp_enqueue_script(
            $this->plugin_name . '-logs',
            plugin_dir_url(dirname(__FILE__)) . 'admin/js/logs-functionality.js',
            array('jquery'),
            $this->version,
            false
        );

        // Enqueue main admin script
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url(dirname(__FILE__)) . 'admin/js/json-post-importer-admin.js',
            array('jquery', $this->plugin_name . '-field-mapping', $this->plugin_name . '-logs'),
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
        $import_settings = isset($_POST['import_settings']) ? $_POST['import_settings'] : array();
        $field_mappings = isset($_POST['field_mappings']) ? $_POST['field_mappings'] : array();
        
        // Validate required data
        if (empty($json_data)) {
            wp_send_json_error(array('message' => __('No JSON data provided for import.', 'json-post-importer')), 400);
        }
        
        if (empty($field_mappings['post_title'])) {
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
     * Process batch
     */
    public function process_batch() {
        check_ajax_referer('jpi_ajax_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'json-post-importer')));
        }

        $import_id = isset($_POST['import_id']) ? sanitize_text_field($_POST['import_id']) : '';
        $batch_number = isset($_POST['batch_number']) ? intval($_POST['batch_number']) : 0;
        
        if (empty($import_id)) {
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
        
        // Calculate batch range
        $batch_size = $import_data['batch_size'];
        $start_index = $batch_number * $batch_size;
        $end_index = min($start_index + $batch_size, $import_data['total_items']);
        $batch_items = array_slice($import_data['items'], $start_index, $batch_size);
        
        $batch_results = array(
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => array()
        );
        
        // Process each item in the batch
        foreach ($batch_items as $index => $item) {
            $item_index = $start_index + $index;
            
            try {
                $result = $this->process_single_item($item, $import_data['field_mappings'], $import_data['import_settings'], $item_index);
                
                if (is_wp_error($result)) {
                    $batch_results['errors'][] = array(
                        'item_index' => $item_index,
                        'message' => $result->get_error_message(),
                        'data' => $item
                    );
                    $batch_results['skipped']++;
                } else {
                    if ($result['action'] === 'created') {
                        $batch_results['created']++;
                    } else {
                        $batch_results['updated']++;
                    }
                }
            } catch (Exception $e) {
                $batch_results['errors'][] = array(
                    'item_index' => $item_index,
                    'message' => $e->getMessage(),
                    'data' => $item
                );
                $batch_results['skipped']++;
            }
        }
        
        // Log batch completion
        $this->logger->log_batch_processed($import_id, $batch_number + 1, $batch_results);
        
        // Update import data
        $import_data['processed_items'] += count($batch_items);
        $import_data['created_posts'] += $batch_results['created'];
        $import_data['updated_posts'] += $batch_results['updated'];
        $import_data['skipped_posts'] += $batch_results['skipped'];
        $import_data['errors'] = array_merge($import_data['errors'], $batch_results['errors']);
        $import_data['current_batch'] = $batch_number + 1;
        
        // Check if import is complete
        $is_complete = $import_data['processed_items'] >= $import_data['total_items'];
        
        if ($is_complete) {
            $import_data['status'] = 'completed';
            $import_data['end_time'] = current_time('mysql');
            
            // Log completion
            $this->logger->log_import_end($import_id, array(
                'created_posts' => $import_data['created_posts'],
                'updated_posts' => $import_data['updated_posts'],
                'skipped_posts' => $import_data['skipped_posts'],
                'error_count' => count($import_data['errors']),
                'total_items' => $import_data['total_items'],
                'processed_items' => $import_data['processed_items']
            ));
            
            // Save to history
            $this->save_import_to_history($import_data);
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
                'percentage' => round(($import_data['processed_items'] / $import_data['total_items']) * 100, 1)
            )
        ));
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
     * Process a single item
     */
    private function process_single_item($item, $field_mappings, $import_settings, $item_index) {
        if (!is_array($item) && !is_object($item)) {
            $this->logger->error('Invalid item type at index ' . $item_index, array('item' => $item));
            return new WP_Error('invalid_item', __('Item must be an array or object.', 'json-post-importer'));
        }
        
        // Convert object to array
        $item = (array) $item;
        
        $this->logger->debug('Processing item at index ' . $item_index, array('item' => $item));
        
        // Extract post data based on field mappings
        $post_data = array();
        
        // Map standard fields
        foreach ($field_mappings as $wp_field => $json_field) {
            if (!empty($json_field) && isset($item[$json_field])) {
                $post_data[$wp_field] = $item[$json_field];
            }
        }
        
        // Validate required fields
        if (empty($post_data['post_title'])) {
            $this->logger->warning('Missing post title at item index ' . $item_index, array('item' => $item));
            return new WP_Error('missing_title', __('Post title is required.', 'json-post-importer'));
        }
        
        // Set default values
        $post_data['post_type'] = isset($import_settings['post_type']) ? $import_settings['post_type'] : 'post';
        $post_data['post_status'] = isset($import_settings['post_status']) ? $import_settings['post_status'] : 'draft';
        $post_data['post_author'] = get_current_user_id();
        
        // Check for existing post if update is enabled
        $existing_post_id = null;
        if (!empty($import_settings['update_existing'])) {
            $existing_post = get_page_by_title($post_data['post_title'], OBJECT, $post_data['post_type']);
            if ($existing_post) {
                $existing_post_id = $existing_post->ID;
                $post_data['ID'] = $existing_post_id;
            }
        }
        
        // Insert or update post
        if ($existing_post_id) {
            $post_id = wp_update_post($post_data);
            $action = 'updated';
        } else {
            $post_id = wp_insert_post($post_data);
            $action = 'created';
        }
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        return array(
            'post_id' => $post_id,
            'action' => $action
        );
    }

    /**
     * Log import event using the new logger system
     */
    private function log_import_event($import_id, $level, $message, $context = array()) {
        $context['import_id'] = $import_id;
        
        switch ($level) {
            case 'error':
                $this->logger->error($message, $context);
                break;
            case 'warning':
                $this->logger->warning($message, $context);
                break;
            case 'debug':
                $this->logger->debug($message, $context);
                break;
            default:
                $this->logger->info($message, $context);
                break;
        }
    }

    /**
     * Save import to history
     */
    private function save_import_to_history($import_data) {
        $history_entry = array(
            'import_id' => $import_data['id'],
            'start_time' => $import_data['start_time'],
            'end_time' => isset($import_data['end_time']) ? $import_data['end_time'] : '',
            'status' => $import_data['status'],
            'total_items' => $import_data['total_items'],
            'processed_items' => $import_data['processed_items'],
            'created_posts' => $import_data['created_posts'],
            'updated_posts' => $import_data['updated_posts'],
            'skipped_posts' => $import_data['skipped_posts'],
            'error_count' => count($import_data['errors']),
            'cancelled' => $import_data['cancelled']
        );
        
        // Get existing history
        $history = get_option('jpi_import_history', array());
        
        // Add new entry
        $history[] = $history_entry;
        
        // Keep only last 100 imports
        if (count($history) > 100) {
            $history = array_slice($history, -100);
        }
        
        // Update history
        update_option('jpi_import_history', $history);
    }

    /**
     * Get logs via AJAX
     */
    public function get_logs_ajax() {
        check_ajax_referer('jpi_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'json-post-importer')));
        }

        $filename = isset($_POST['filename']) ? sanitize_text_field($_POST['filename']) : 'json-post-importer.log';
        $lines = isset($_POST['lines']) ? intval($_POST['lines']) : 100;

        $content = $this->logger->get_log_content($filename, $lines);
        
        if ($content === false) {
            wp_send_json_error(array('message' => __('Log file not found.', 'json-post-importer')));
        }

        $files = $this->logger->get_log_files();

        wp_send_json_success(array(
            'content' => $content,
            'files' => $files,
            'debug_mode' => $this->logger->is_debug_mode()
        ));
    }

    /**
     * Clear logs via AJAX
     */
    public function clear_logs_ajax() {
        check_ajax_referer('jpi_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'json-post-importer')));
        }

        $filename = isset($_POST['filename']) ? sanitize_text_field($_POST['filename']) : null;
        
        $success = $this->logger->clear_logs($filename);
        
        if ($success) {
            $this->logger->info('Log files cleared via admin interface');
            wp_send_json_success(array('message' => __('Logs cleared successfully.', 'json-post-importer')));
        } else {
            wp_send_json_error(array('message' => __('Failed to clear logs.', 'json-post-importer')));
        }
    }

    /**
     * Toggle debug mode via AJAX
     */
    public function toggle_debug_mode_ajax() {
        check_ajax_referer('jpi_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'json-post-importer')));
        }

        $enabled = isset($_POST['enabled']) ? (bool) $_POST['enabled'] : false;
        
        $this->logger->set_debug_mode($enabled);
        
        wp_send_json_success(array(
            'message' => $enabled ? __('Debug mode enabled.', 'json-post-importer') : __('Debug mode disabled.', 'json-post-importer'),
            'debug_mode' => $enabled
        ));
    }

    /**
     * Get log statistics via AJAX
     */
    public function get_log_stats_ajax() {
        check_ajax_referer('jpi_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'json-post-importer')));
        }

        $stats = $this->logger->get_log_stats();
        
        wp_send_json_success($stats);
    }

    /**
     * Download log file via AJAX
     */
    public function download_log_ajax() {
        check_ajax_referer('jpi_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'json-post-importer'));
        }

        $filename = isset($_GET['filename']) ? sanitize_text_field($_GET['filename']) : 'json-post-importer.log';
        
        $content = $this->logger->get_log_content($filename);
        
        if ($content === false) {
            wp_die(__('Log file not found.', 'json-post-importer'));
        }

        // Set headers for download
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        
        echo $content;
        exit;
    }


}