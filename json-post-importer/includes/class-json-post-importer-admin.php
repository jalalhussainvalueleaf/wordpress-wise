<?php
/**
 * The admin-specific functionality of the plugin.
 */
class JSON_Post_Importer_Admin {
    /**
     * The JSON_Post_Creator instance
     *
     * @var JSON_Post_Creator
     */
    private $post_creator;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->post_creator = new JSON_Post_Creator();
        
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Register AJAX handlers
        add_action('wp_ajax_jpi_handle_upload', array($this, 'handle_ajax_upload'));
        add_action('wp_ajax_jpi_preview_json', array($this, 'handle_preview_json'));
        add_action('wp_ajax_jpi_import_content', array($this, 'handle_import_content'));
        add_action('admin_post_jpi_handle_upload', array($this, 'handle_file_upload'));
        
        // Localize script with nonce for AJAX requests
        add_action('admin_enqueue_scripts', array($this, 'localize_script'));
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
        
        include_once JPI_PLUGIN_DIR . 'admin/partials/json-post-importer-admin-display.php';
    }

    public function enqueue_styles() {
        wp_enqueue_style(
            'json-post-importer-admin',
            JPI_PLUGIN_URL . 'admin/css/json-post-importer-admin.css',
            array(),
            JPI_VERSION,
            'all'
        );
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook) {
        if ('toplevel_page_json-post-importer' !== $hook) {
            return;
        }
        
        // Enqueue WordPress media scripts for file uploads
        wp_enqueue_media();
        
        // Enqueue our custom script with dependencies
        wp_enqueue_script(
            'json-post-importer-admin',
            JPI_PLUGIN_URL . 'admin/js/json-post-importer-admin.js',
            array('jquery', 'wp-util', 'jquery-ui-dialog'),
            JPI_VERSION,
            true
        );
        
        // Enqueue jQuery UI styles for dialogs
        wp_enqueue_style('wp-jquery-ui-dialog');
        
        // Localize the script with our data
        $this->localize_script();
    }
    
    /**
     * Localize script with translations and URLs
     * 
     * @since 1.0.0
     */
    public function localize_script() {
        // Only localize on our plugin page
        $screen = get_current_screen();
        if ($screen->id !== 'toplevel_page_json-post-importer') {
            return;
        }
        
        // Get post types for mapping
        $post_types = get_post_types(array('public' => true), 'objects');
        $post_type_options = array();
        
        foreach ($post_types as $post_type) {
            $post_type_options[$post_type->name] = $post_type->labels->singular_name;
        }
        
        // Get taxonomies for mapping
        $taxonomies = get_taxonomies(array('public' => true), 'objects');
        $taxonomy_options = array();
        
        foreach ($taxonomies as $taxonomy) {
            $taxonomy_options[$taxonomy->name] = $taxonomy->labels->singular_name;
        }
        
        // Get post statuses
        $post_statuses = get_post_statuses();
        
        // Get upload size limits
        $max_upload = wp_max_upload_size();
        $max_upload_size = size_format($max_upload);
        
        wp_localize_script(
            'json-post-importer-admin',
            'jpi_vars',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('jpi_ajax_nonce'),
                'upload_nonce' => wp_create_nonce('jpi_upload_nonce'),
                'import_nonce' => wp_create_nonce('jpi_import_content'),
                'max_upload_size' => $max_upload_size,
                'max_upload_size_bytes' => $max_upload,
                'post_types' => $post_type_options,
                'taxonomies' => $taxonomy_options,
                'post_statuses' => $post_statuses,
                'default_post_type' => 'post',
                'default_post_status' => 'publish',
                'i18n' => array(
                    'preview_error' => __('An error occurred while generating the preview.', 'json-post-importer'),
                    'import_error' => __('An error occurred during the import process.', 'json-post-importer'),
                    'import_success' => __('Import completed successfully!', 'json-post-importer'),
                    'file_too_large' => __('The selected file is too large. Maximum upload size is %s.', 'json-post-importer'),
                    'invalid_file_type' => __('Invalid file type. Please upload a valid JSON file.', 'json-post-importer'),
                    'importing' => __('Importing...', 'json-post-importer'),
                    'import_complete' => __('Import complete!', 'json-post-importer'),
                    'confirm_import' => __('Are you sure you want to import this content?', 'json-post-importer'),
                    'uploading' => __('Uploading...', 'json-post-importer'),
                    'upload_complete' => __('Upload complete!', 'json-post-importer'),
                    'upload_failed' => __('Upload failed. Please try again.', 'json-post-importer'),
                    'invalid_file' => __('Please select a valid JSON file.', 'json-post-importer'),
                    'file_too_large' => __('The selected file is too large. Please choose a smaller file.', 'json-post-importer'),
                    'invalid_json' => __('Invalid JSON:', 'json-post-importer'),
                    'file_read_error' => __('Error reading file. Please try again.', 'json-post-importer'),
                    'choose_file' => __('Choose a file or drag it here', 'json-post-importer'),
                    'dismiss' => __('Dismiss this notice', 'json-post-importer'),
                    'no_file_selected' => __('Please select a file to upload.', 'json-post-importer'),
                    'upload_failed' => __('Upload failed. Please try again.', 'json-post-importer'),
                    'invalid_file' => __('Invalid file. Please upload a valid JSON file.', 'json-post-importer'),
                    'processing' => __('Processing...', 'json-post-importer'),
                    'preview' => __('Preview', 'json-post-importer'),
                    'import' => __('Import', 'json-post-importer'),
                    'importing' => __('Importing...', 'json-post-importer'),
                    'import_complete' => __('Import complete!', 'json-post-importer'),
                    'error' => __('Error', 'json-post-importer'),
                    'close' => __('Close', 'json-post-importer'),
                    'choose_file' => __('Choose a file or drag it here', 'json-post-importer'),
                    'dismiss' => __('Dismiss this notice', 'json-post-importer'),
                    'no_file_selected' => __('Please select a file to upload.', 'json-post-importer'),
                    'select_field' => __('-- Select Field --', 'json-post-importer'),
                    'map_fields' => __('Map Fields', 'json-post-importer'),
                    'import_options' => __('Import Options', 'json-post-importer'),
                    'start_import' => __('Start Import', 'json-post-importer'),
                    'cancel' => __('Cancel', 'json-post-importer'),
                    'import_complete' => __('Import Complete', 'json-post-importer'),
                    'items_imported' => __('items imported successfully', 'json-post-importer'),
                    'items_skipped' => __('items skipped', 'json-post-importer'),
                    'errors_occurred' => __('errors occurred', 'json-post-importer'),
                    'view_details' => __('View Details', 'json-post-importer'),
                    'hide_details' => __('Hide Details', 'json-post-importer'),
                    'required_field' => __('This field is required', 'json-post-importer'),
                    'invalid_json' => __('Invalid JSON data', 'json-post-importer'),
                    'no_items_found' => __('No items found to import', 'json-post-importer'),
                    'confirm_cancel' => __('Are you sure you want to cancel the import?', 'json-post-importer')
                )
            )
        );
    }

    /**
     * Handle JSON preview request
     * 
     * @since 1.0.0
     */
    public function handle_preview_json() {
        // Start output buffering to catch any unexpected output
        ob_start();
        
        try {
            // Enable error reporting for debugging
            error_reporting(E_ALL);
            
            // Don't output errors to browser - we'll handle them
            ini_set('display_errors', 0);
            
            // Check if this is an AJAX request
            if (!wp_doing_ajax()) {
                throw new Exception('This endpoint can only be accessed via AJAX.');
            }
            
            // Check if user is logged in and has proper permissions
            if (!is_user_logged_in() || !current_user_can('upload_files')) {
                throw new Exception(__('You do not have permission to preview files.', 'json-post-importer'));
            }
            
            // Verify nonce
            if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'jpi_ajax_nonce')) {
                throw new Exception(__('Security check failed. Please refresh the page and try again.', 'json-post-importer'));
            }
            
            // Check for JSON data
            if (empty($_POST['json_data'])) {
                throw new Exception(__('No JSON data provided.', 'json-post-importer'));
            }
            
            // Get and decode the JSON data
            $json_data = stripslashes($_POST['json_data']);
            $data = json_decode($json_data, true);
            
            // Check for JSON decode errors
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception(sprintf(
                    __('Invalid JSON: %s', 'json-post-importer'),
                    json_last_error_msg()
                ));
            }
            
            // Generate the preview HTML
            $preview_html = $this->generate_preview_html($data);
            
            // Send success response with preview HTML
            wp_send_json_success(array(
                'preview_html' => $preview_html,
                'item_count' => is_array($data) ? count($data) : 1,
                'debug' => array(
                    'json_keys' => is_array($data) && !empty($data) ? array_keys($data[0] ?? []) : [],
                    'sample_item' => is_array($data) ? ($data[0] ?? null) : $data
                )
            ));
            
        } catch (Exception $e) {
            // Get any buffered output
            $buffer = ob_get_clean();
            
            // Log the error
            error_log('JSON Preview Error: ' . $e->getMessage());
            if (!empty($buffer)) {
                error_log('Unexpected output: ' . $buffer);
            }
            
            // Send error response
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'buffer' => $buffer
            ));
        }
        
        // Make sure we don't continue processing
        wp_die();
        
        // Check for JSON data
        if (empty($_POST['json_data'])) {
            wp_send_json_error(array(
                'message' => __('No JSON data provided.', 'json-post-importer'),
                'post_data' => array_keys($_POST)
            ));
            wp_die();
        }
        
        // Get and decode the JSON data
        $json_data = stripslashes($_POST['json_data']);
        $data = json_decode($json_data, true);
        
        // Check for JSON decode errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array(
                'message' => sprintf(
                    __('Invalid JSON: %s', 'json-post-importer'),
                    $this->get_json_error_message(json_last_error())
                ),
                'json_error' => json_last_error_msg(),
                'json_error_code' => json_last_error(),
                'data_type' => gettype($data)
            ));
            wp_die();
        }
        
        // Validate the JSON structure
        $validation = $this->validate_json_structure($data);
        if (is_wp_error($validation)) {
            wp_send_json_error(array(
                'message' => $validation->get_error_message(),
                'error_data' => $validation->get_error_data()
            ));
            wp_die();
        }
        
        try {
            // Generate the preview HTML
            $preview_html = $this->generate_preview_html($data);
            
            // Send success response with preview HTML
            wp_send_json_success(array(
                'preview_html' => $preview_html,
                'item_count' => is_array($data) ? count($data) : 1,
                'has_nested' => $this->has_nested_data($data),
                'debug' => array(
                    'json_keys' => is_array($data) && !empty($data) ? array_keys($data[0] ?? []) : [],
                    'sample_item' => is_array($data) ? ($data[0] ?? null) : $data
                )
            ));
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => __('Error generating preview: ', 'json-post-importer') . $e->getMessage(),
                'exception' => get_class($e),
                'error' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
        }
        
        wp_die();
    }
    
    /**
     * Display fields recursively for the preview
     *
     * @param array $fields The fields to display
     * @param string $prefix Field name prefix for nested fields
     */
    private function display_fields_recursive($fields, $prefix = '') {
        foreach ($fields as $field => $value) {
            $field_name = $prefix . $field;
            $field_id = str_replace(['[', ']', '.'], '_', $field_name);
            
            if (is_array($value) || is_object($value)) {
                // For nested arrays/objects, display a row with a collapsible section
                echo '<tr class="nested-field">';
                echo '<td colspan="3"><strong>' . esc_html($field) . '</strong> (' . __('nested data', 'json-post-importer') . ')';
                echo '<div class="nested-fields" style="display:none; margin-top:10px; padding-left:20px; border-left:2px solid #ddd;">';
                echo '<table class="nested-table" style="width:100%;">';
                $this->display_fields_recursive((array)$value, $field_name . '[');
                echo '</table>';
                echo '</div>';
                echo '</td>';
                echo '</tr>';
            } else {
                // For regular fields, display the field mapping row
                echo '<tr>';
                echo '<td><code>' . esc_html($field_name) . '</code></td>';
                echo '<td>';
                echo '<select class="jpi-field-select" data-field="' . esc_attr($field_name) . '" id="field_' . esc_attr($field_id) . '">';
                echo '<option value="">' . __('-- Select Field --', 'json-post-importer') . '</option>';
                
                // Suggest field mappings based on field name
                $suggested_field = '';
                $field_lower = strtolower($field);
                
                if (strpos($field_lower, 'title') !== false) {
                    $suggested_field = 'post_title';
                } elseif (strpos($field_lower, 'content') !== false) {
                    $suggested_field = 'post_content';
                } elseif (strpos($field_lower, 'excerpt') !== false || strpos($field_lower, 'summary') !== false) {
                    $suggested_field = 'post_excerpt';
                } elseif (strpos($field_lower, 'date') !== false) {
                    $suggested_field = 'post_date';
                } elseif (strpos($field_lower, 'status') !== false) {
                    $suggested_field = 'post_status';
                } elseif (strpos($field_lower, 'image') !== false || strpos($field_lower, 'picture') !== false) {
                    $suggested_field = 'featured_image';
                } elseif (strpos($field_lower, 'tag') !== false) {
                    $suggested_field = 'post_tag';
                } elseif (strpos($field_lower, 'category') !== false) {
                    $suggested_field = 'category';
                }
                
                $field_options = [
                    'post_title' => __('Post Title', 'json-post-importer'),
                    'post_content' => __('Post Content', 'json-post-importer'),
                    'post_excerpt' => __('Post Excerpt', 'json-post-importer'),
                    'post_status' => __('Post Status', 'json-post-importer'),
                    'post_date' => __('Post Date', 'json-post-importer'),
                    'post_author' => __('Post Author', 'json-post-importer'),
                    'post_name' => __('Post Slug', 'json-post-importer'),
                    'post_password' => __('Post Password', 'json-post-importer'),
                    'comment_status' => __('Comment Status', 'json-post-importer'),
                    'ping_status' => __('Ping Status', 'json-post-importer'),
                    'featured_image' => __('Featured Image', 'json-post-importer'),
                    'post_format' => __('Post Format', 'json-post-importer'),
                    'post_category' => __('Post Categories', 'json-post-importer'),
                    'tags_input' => __('Post Tags', 'json-post-importer'),
                    'meta' => __('Custom Field', 'json-post-importer'),
                ];
                
                foreach ($field_options as $key => $label) {
                    $selected = ($key === $suggested_field) ? ' selected="selected"' : '';
                    echo '<option value="' . esc_attr($key) . '"' . $selected . '>' . esc_html($label) . '</option>';
                }
                
                echo '</select>';
                
                // Add custom field name input for meta fields
                echo '<div class="custom-meta-field" style="display:none; margin-top:5px;">';
                echo '<input type="text" class="regular-text meta-field-name" placeholder="' . esc_attr__('Custom field name', 'json-post-importer') . '" />';
                echo '</div>';
                
                echo '</td>';
                echo '<td>';
                
                // Display sample value (truncate if too long)
                $sample = is_scalar($value) ? $value : json_encode($value);
                echo '<span class="sample-value" title="' . esc_attr($sample) . '">';
                if (strlen($sample) > 50) {
                    echo esc_html(substr($sample, 0, 47)) . '...';
                } else {
                    echo esc_html($sample);
                }
                echo '</span>';
                
                echo '</td>';
                echo '</tr>';
            }
        }
    }

    /**
     * Generate HTML for JSON preview
     */
    private function generate_preview_html($data) {
        if (!is_array($data) || empty($data)) {
            return '<div class="notice notice-warning"><p>' . __('No valid data found in the JSON file.', 'json-post-importer') . '</p></div>';
        }
        
        // Handle both direct content and nested content structure
        if (isset($data['content']) && is_array($data['content'])) {
            // If data has a 'content' key, use that as the main data
            $sample_item = $data['content'];
            $is_nested = true;
        } else {
            // Otherwise, assume it's a direct array of items
            $sample_item = is_array($data[0] ?? null) ? $data[0] : $data;
            $is_nested = false;
        }
        
        ob_start();
        ?>
        <div class="jpi-preview-container">
            <h3><?php _e('Data Preview', 'json-post-importer'); ?></h3>
            <div class="jpi-preview-stats">
                <p><?php 
                    if ($is_nested) {
                        _e('Found content with nested structure', 'json-post-importer');
                    } else {
                        echo sprintf(
                            _n('Found %d item', 'Found %d items', count($data), 'json-post-importer'), 
                            count($data)
                        );
                    }
                ?></p>
            </div>
            
            <div class="jpi-field-mapping">
                <h4><?php _e('Field Mapping', 'json-post-importer'); ?></h4>
                <p class="description"><?php _e('Map JSON fields to WordPress post fields:', 'json-post-importer'); ?></p>
                
                <table class="widefat fixed" cellspacing="0">
                    <thead>
                        <tr>
                            <th><?php _e('JSON Field', 'json-post-importer'); ?></th>
                            <th><?php _e('WordPress Field', 'json-post-importer'); ?></th>
                            <th><?php _e('Sample Value', 'json-post-importer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Start displaying fields
                        $this->display_fields_recursive((array)$sample_item);
                        ?>
                    </tbody>
                </table>
                
                <div class="jpi-import-options" style="margin-top: 20px;">
                    <h4><?php _e('Import Options', 'json-post-importer'); ?></h4>
                    <p>
                        <label>
                            <input type="checkbox" name="import_images" value="1" checked>
                            <?php _e('Download and import images', 'json-post-importer'); ?>
                        </label>
                    </p>
                    <p>
                        <label>
                            <input type="checkbox" name="create_terms" value="1" checked>
                            <?php _e('Create categories and tags if they don\'t exist', 'json-post-importer'); ?>
                        </label>
                    </p>
                    <p>
                        <label>
                            <input type="checkbox" name="update_existing" value="1" checked>
                            <?php _e('Update existing posts', 'json-post-importer'); ?>
                        </label>
                    </p>
                </div>
                
                <div class="jpi-actions" style="margin-top: 20px;">
                    <button type="button" class="button button-primary jpi-import-button">
                        <?php _e('Start Import', 'json-post-importer'); ?>
                    </button>
                    <span class="spinner" style="float: none; margin-top: 0;"></span>
                </div>
                
                <input type="hidden" name="jpi_field_mappings" id="jpi-field-mappings" value="">
                <input type="hidden" name="jpi_import_options" id="jpi-import-options" value="">
                
                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Toggle custom field name input
                    $(document).on('change', '.jpi-field-select', function() {
                        var $this = $(this);
                        var $customField = $this.siblings('.custom-meta-field');
                        
                        if ($this.val() === 'meta') {
                            $customField.show();
                        } else {
                            $customField.hide();
                        }
                        
                        updateFieldMappings();
                    });
                    
                    // Toggle nested fields
                    $(document).on('click', '.nested-field > td', function(e) {
                        if (!$(e.target).is('select, input')) {
                            $(this).find('.nested-fields').slideToggle();
                        }
                    });
                    
                    // Handle import button click
                    $('.jpi-import-button').on('click', function() {
                        if (confirm('<?php echo esc_js(__('Are you sure you want to import this content?', 'json-post-importer')); ?>')) {
                            startImport();
                        }
                    });
                    
                    // Update field mappings
                    function updateFieldMappings() {
                        var mappings = {};
                        
                        $('.jpi-field-select').each(function() {
                            var $this = $(this);
                            var field = $this.data('field');
                            var value = $this.val();
                            
                            if (value) {
                                if (value === 'meta') {
                                    var metaKey = $this.siblings('.custom-meta-field').find('input').val().trim();
                                    if (metaKey) {
                                        mappings[field] = {
                                            type: 'meta',
                                            key: metaKey
                                        };
                                    }
                                } else {
                                    mappings[field] = {
                                        type: 'post_field',
                                        field: value
                                    };
                                }
                            }
                        });
                        
                        $('#jpi-field-mappings').val(JSON.stringify(mappings));
                        
                        // Update import options
                        var options = {
                            import_images: $('input[name="import_images"]').is(':checked'),
                            create_terms: $('input[name="create_terms"]').is(':checked'),
                            update_existing: $('input[name="update_existing"]').is(':checked')
                        };
                        $('#jpi-import-options').val(JSON.stringify(options));
                    }
                    
                    // Start import process
                    function startImport() {
                        var $button = $('.jpi-import-button');
                        var $spinner = $button.siblings('.spinner');
                        
                        $button.prop('disabled', true);
                        $spinner.addClass('is-active');
                        
                        var data = {
                            action: 'jpi_import_content',
                            security: '<?php echo wp_create_nonce('jpi_import_content'); ?>',
                            field_mappings: $('#jpi-field-mappings').val(),
                            import_options: $('#jpi-import-options').val(),
                            json_data: '<?php echo addslashes(json_encode($data)); ?>'
                        };
                        
                        $.post(ajaxurl, data, function(response) {
                            if (response.success) {
                                alert('<?php echo esc_js(__('Import completed successfully!', 'json-post-importer')); ?>');
                                if (response.data && response.data.redirect) {
                                    window.location.href = response.data.redirect;
                                }
                            } else {
                                var message = response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('An error occurred during import.', 'json-post-importer')); ?>';
                                alert(message);
                            }
                            
                            $button.prop('disabled', false);
                            $spinner.removeClass('is-active');
                        }).fail(function() {
                            alert('<?php echo esc_js(__('Failed to communicate with the server. Please try again.', 'json-post-importer')); ?>');
                            $button.prop('disabled', false);
                            $spinner.removeClass('is-active');
                        });
                    }
                });
                </script>
                
                <div class="jpi-preview-sample" style="margin-top: 30px;">
                    <h4><?php _e('Sample Data', 'json-post-importer'); ?></h4>
                    <div class="jpi-sample-data" style="max-height: 300px; overflow: auto; background: #f5f5f5; padding: 10px; border: 1px solid #ddd;">
                        <pre style="white-space: pre-wrap; word-wrap: break-word; margin: 0;"><?php 
                        echo esc_html(print_r($sample_item, true)); 
                        ?></pre>
                    </div>
                </div>
                
                <style type="text/css">
                    .jpi-preview-container { margin: 20px 0; }
                    .jpi-field-mapping { margin: 20px 0; }
                    .jpi-field-mapping table { margin: 10px 0; }
                    .jpi-field-mapping th { font-weight: 600; }
                    .jpi-field-mapping td { vertical-align: top; padding: 8px; }
                    .jpi-field-mapping select { width: 100%; max-width: 250px; }
                    .nested-field { cursor: pointer; }
                    .nested-field > td { font-weight: bold; }
                    .nested-fields { display: none; }
                    .nested-table { background: #f9f9f9; }
                    .sample-value { display: inline-block; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
                    .jpi-actions { margin: 20px 0; }
                    .jpi-import-options { background: #f9f9f9; padding: 15px; border: 1px solid #ddd; margin: 20px 0; }
                    .jpi-import-options h4 { margin-top: 0; }
                    .jpi-import-options p { margin: 10px 0; }
                    .jpi-import-options label { display: flex; align-items: center; }
                    .jpi-import-options input[type="checkbox"] { margin-right: 8px; }
                </style>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle file upload via AJAX
     */
    public function handle_ajax_upload() {
        check_ajax_referer('jpi_upload_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You do not have permission to upload files.', 'json-post-importer')));
            return;
        }
        
        if (empty($_FILES['json_file'])) {
            wp_send_json_error(array('message' => __('No file was uploaded.', 'json-post-importer')));
            return;
        }
        
        // Get field mappings
        $field_mapping = isset($_POST['field_mapping']) ? (array) $_POST['field_mapping'] : array();
        
        $file = $_FILES['json_file'];
        $result = $this->process_uploaded_file($file, $field_mapping);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        wp_send_json_success(array(
            'message' => sprintf(
                _n('%d post imported successfully!', '%d posts imported successfully!', $result['imported'], 'json-post-importer'),
                $result['imported']
            ),
            'data' => $result
        ));
    }

    /**
     * Handle file upload via form submission (fallback)
     */
    public function handle_file_upload() {
        // Verify nonce
        if (!isset($_POST['jpi_upload_nonce']) || !wp_verify_nonce($_POST['jpi_upload_nonce'], 'jpi_upload_nonce')) {
            wp_die(__('Security check failed.', 'json-post-importer'));
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'json-post-importer'));
        }

        // Check if file was uploaded
        if (empty($_FILES['json_file'])) {
            wp_redirect(add_query_arg('jpi_message', 'no_file', admin_url('admin.php?page=json-post-importer')));
            exit;
        }

        $file = $_FILES['json_file'];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_redirect(add_query_arg('jpi_message', 'upload_error', admin_url('admin.php?page=json-post-importer')));
            exit;
        }

        // Check file type
        $filetype = wp_check_filetype($file['name'], array('json' => 'application/json'));
        if ($filetype['ext'] !== 'json') {
            wp_redirect(add_query_arg('jpi_message', 'invalid_file_type', admin_url('admin.php?page=json-post-importer')));
            exit;
        }

        // Process the file
        $json_content = file_get_contents($file['tmp_name']);
        $data = json_decode($json_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_redirect(add_query_arg('jpi_message', 'invalid_json', admin_url('admin.php?page=json-post-importer')));
            exit;
        }

        // Process the JSON data and create posts
        $result = $this->process_json_data($data);
        
        if (is_wp_error($result)) {
            wp_redirect(add_query_arg('jpi_message', 'import_error', admin_url('admin.php?page=json-post-importer')));
            exit;
        }

        // Redirect back to the plugin page with success message
        wp_redirect(add_query_arg('jpi_message', 'import_success', admin_url('admin.php?page=json-post-importer')));
        exit;
    }

    /**
     * Process uploaded JSON file
     */
    private function process_uploaded_file($file, $field_mapping = array()) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', $this->get_upload_error_message($file['error']));
        }
        
        // Check file type
        $filetype = wp_check_filetype($file['name'], array('json' => 'application/json'));
        if ($filetype['ext'] !== 'json') {
            return new WP_Error('invalid_file_type', __('Invalid file type. Please upload a JSON file.', 'json-post-importer'));
        }
        
        // Check file size (in case it's larger than the server's post_max_size)
        $file_size = filesize($file['tmp_name']);
        if ($file_size > wp_max_upload_size()) {
            return new WP_Error(
                'file_too_large',
                sprintf(
                    __('The uploaded file is too large. Maximum file size: %s', 'json-post-importer'),
                    size_format(wp_max_upload_size())
                )
            );
        }
        
        // Read the file contents
        $json_content = file_get_contents($file['tmp_name']);
        if ($json_content === false) {
            return new WP_Error('file_read_error', __('Could not read the uploaded file.', 'json-post-importer'));
        }
        
        // Decode the JSON
        $data = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'invalid_json', 
                sprintf(__('Invalid JSON: %s', 'json-post-importer'), json_last_error_msg())
            );
        }
        
        // Process the data with field mappings
        return $this->process_json_data($data, $field_mapping);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Process the JSON data
        return $this->process_json_data($data);
    }
    
    /**
     * Get user-friendly JSON error message
     */
    private function get_json_error_message($error_code) {
        $messages = array(
            JSON_ERROR_DEPTH => __('Maximum stack depth exceeded', 'json-post-importer'),
            JSON_ERROR_STATE_MISMATCH => __('Underflow or the modes mismatch', 'json-post-importer'),
            JSON_ERROR_CTRL_CHAR => __('Unexpected control character found', 'json-post-importer'),
            JSON_ERROR_SYNTAX => __('Syntax error, malformed JSON', 'json-post-importer'),
            JSON_ERROR_UTF8 => __('Malformed UTF-8 characters, possibly incorrectly encoded', 'json-post-importer'),
            JSON_ERROR_RECURSION => __('Recursive reference was found', 'json-post-importer'),
            JSON_ERROR_INF_OR_NAN => __('NAN or INF was found', 'json-post-importer'),
            JSON_ERROR_UNSUPPORTED_TYPE => __('Unsupported value type', 'json-post-importer'),
        );
        
        return isset($messages[$error_code]) 
            ? $messages[$error_code] 
            : __('Unknown JSON error', 'json-post-importer');
    }
    
    /**
     * Validate JSON structure
     */
    private function validate_json_structure($data) {
        if (!is_array($data)) {
            return new WP_Error('invalid_data', __('Invalid data format. Expected an array or object.', 'json-post-importer'));
        }
        
        if (!isset($data['content'])) {
            return new WP_Error('missing_content', __('The JSON is missing required "content" field.', 'json-post-importer'));
        }
        
        if (!is_array($data['content'])) {
            return new WP_Error('invalid_content', __('The "content" field must be an object.', 'json-post-importer'));
        }
        
        // Add more validation as needed for your specific JSON structure
        
        return true;
    }
    
    /**
     * Process JSON data and create/update posts
     * 
     * @param array $data The JSON data to import
     * @param array $field_mappings Field mappings from JSON to WordPress fields
     * @param array $import_options Import configuration options
     * @return array Result of the import process
     */
    private function process_json_data($data, $field_mappings = array(), $import_options = array()) {
        // Initialize results
        $results = array(
            'imported' => array(),
            'updated' => array(),
            'skipped' => array(),
            'errors' => array()
        );

        // Default import options
        $default_options = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'update_existing' => false,
            'import_images' => true,
            'create_terms' => true,
            'import_faq' => true,
            'import_seo' => true
        );
        
        $import_options = wp_parse_args($import_options, $default_options);

        // Check if data is a single item or an array of items
        $items = isset($data[0]) ? $data : array($data);
        
        foreach ($items as $index => $item) {
            try {
                // Skip if item is not an array
                if (!is_array($item)) {
                    $results['skipped'][] = array(
                        'item' => $item,
                        'message' => 'Skipped: Item is not in a valid format',
                        'index' => $index
                    );
                    continue;
                }

                // Check if this is an update to an existing post
                $existing_post_id = null;
                if ($import_options['update_existing'] && !empty($item['ID'])) {
                    $existing_post = get_post($item['ID']);
                    if ($existing_post) {
                        $existing_post_id = $existing_post->ID;
                    }
                }

                // Map the JSON data to the expected format for JSON_Post_Creator
                $post_data = $this->map_json_to_post_data($item, $import_options);

                // Check for required fields
                if (empty($post_data['content']['browser_title']) && empty($post_data['content']['heading'])) {
                    throw new Exception('Missing required field: browser_title or heading');
                }

                // If we're updating an existing post, add the ID
                if ($existing_post_id) {
                    $post_data['ID'] = $existing_post_id;
                }

                // Create or update the post using our post creator
                $post_id = $this->post_creator->create_post_from_json($post_data);

                if (is_wp_error($post_id)) {
                    throw new Exception($post_id->get_error_message());
                }

                // Add to results
                $action = $existing_post_id ? 'updated' : 'imported';
                $results[$action][] = array(
                    'id' => $post_id,
                    'title' => get_the_title($post_id),
                    'edit_link' => get_edit_post_link($post_id, 'url')
                );

            } catch (Exception $e) {
                $results['errors'][] = array(
                    'item' => $item,
                    'message' => $e->getMessage(),
                    'index' => $index,
                    'trace' => WP_DEBUG ? $e->getTraceAsString() : null
                );
                continue;
            }
        }

        return $results;
    }

    /**
     * Sanitize field based on its type
     */
    /**
     * Map JSON data to the format expected by JSON_Post_Creator
     *
     * @param array $item Raw JSON data item
     * @param array $import_options Import configuration options
     * @return array Mapped post data
     */
    private function map_json_to_post_data($item, $import_options) {
        // Sanitize input data
        $item = $this->sanitize_input($item);
        
        $post_data = array(
            'content' => array(
                'browser_title' => sanitize_text_field($item['browser_title'] ?? $item['title'] ?? ''),
                'heading' => sanitize_text_field($item['heading'] ?? $item['title'] ?? ''),
                'description' => sanitize_textarea_field($item['description'] ?? $item['excerpt'] ?? ''),
                'content' => wp_kses_post($item['content'] ?? $item['body'] ?? ''),
                'featured_image' => esc_url_raw($item['featured_image'] ?? $item['image'] ?? ''),
                'created_datetime' => $this->sanitize_datetime($item['created_at'] ?? $item['date'] ?? current_time('mysql')),
                'updated_datetime' => $this->sanitize_datetime($item['updated_at'] ?? $item['modified'] ?? current_time('mysql')),
                'nonce' => wp_create_nonce('jpi_import_content')
            ),
            'meta_input' => array()
        );

        // Add tags if they exist
        if (!empty($item['tags'])) {
            $post_data['content']['tags'] = is_array($item['tags']) ? $item['tags'] : explode(',', $item['tags']);
        }

        // Add FAQ if it exists and import_faq is enabled
        if ($import_options['import_faq'] && !empty($item['faq'])) {
            $post_data['content']['faq'] = $item['faq'];
        }

        // Add SEO data if it exists and import_seo is enabled
        if ($import_options['import_seo']) {
            $post_data['content']['focus_keyword'] = $item['focus_keyword'] ?? $item['seo_keyword'] ?? '';
            $post_data['content']['keywords'] = $item['keywords'] ?? $item['meta_keywords'] ?? '';
            
            // Add meta description if not already in content
            if (empty($post_data['content']['description']) && !empty($item['meta_description'])) {
                $post_data['content']['description'] = $item['meta_description'];
            }
        }

        // Add any additional fields to meta_input
        $meta_fields = array_diff_key($item, array_flip([
            'browser_title', 'title', 'heading', 'description', 'excerpt', 'content', 'body',
            'featured_image', 'image', 'created_at', 'updated_at', 'date', 'modified',
            'tags', 'faq', 'focus_keyword', 'seo_keyword', 'keywords', 'meta_keywords', 'meta_description'
        ]));

        foreach ($meta_fields as $key => $value) {
            $post_data['meta_input'][$key] = $value;
        }

        return $post_data;
    }

    /**
     * Sanitize field based on its type
     */
    /**
     * Sanitize input data recursively
     */
    private function sanitize_input($input) {
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                $input[sanitize_key($key)] = $this->sanitize_input($value);
            }
            return $input;
        } elseif (is_string($input)) {
            return wp_kses_post($input);
        }
        return $input;
    }

    /**
     * Sanitize datetime string
     */
    private function sanitize_datetime($datetime) {
        if (empty($datetime)) {
            return current_time('mysql');
        }
        
        // Try to create a DateTime object
        $dt = date_create($datetime);
        if ($dt === false) {
            return current_time('mysql');
        }
        
        return $dt->format('Y-m-d H:i:s');
    }

    /**
     * Sanitize field based on its type
     */
    private function sanitize_field($value, $field_type) {
        switch ($field_type) {
            case 'post_field':
                return wp_kses_post($value);
            case 'meta':
                return is_array($value) ? array_map('sanitize_text_field', $value) : sanitize_text_field($value);
            case 'url':
                return esc_url_raw($value);
            case 'email':
                return sanitize_email($value);
            case 'textarea':
                return sanitize_textarea_field($value);
            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Process taxonomy terms
     */
    private function process_taxonomy_terms($terms, $taxonomy, $create_terms = true) {
        if (!is_array($terms)) {
            $terms = array_map('trim', explode(',', $terms));
        }

        $term_ids = array();
        
        foreach ($terms as $term) {
            if (empty($term)) continue;
            
            if ($create_terms) {
                $result = term_exists($term, $taxonomy);
                if (!$result) {
                    $result = wp_insert_term($term, $taxonomy);
                    if (!is_wp_error($result)) {
                        $term_ids[] = $result['term_id'];
                    }
                } else {
                    $term_ids[] = $result['term_id'];
                }
            } else {
                $term_obj = get_term_by('name', $term, $taxonomy);
                if ($term_obj) {
                    $term_ids[] = $term_obj->term_id;
                }
            }
        }
        
        return $term_ids;
    }

    /**
     * Find existing post by meta field
     */
    private function find_existing_post($value, $meta_key, $post_type) {
        $query = new WP_Query(array(
            'post_type' => $post_type,
            'meta_key' => $meta_key,
            'meta_value' => $value,
            'posts_per_page' => 1,
            'fields' => 'ids'
        ));
        
        return $query->posts ? $query->posts[0] : null;
    }

    /**
     * Set featured image for a post from a URL
     */
    private function set_featured_image($post_id, $image_url) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Check if image already exists in media library
        $existing_attachment_id = $this->get_attachment_id_by_url($image_url);
        
        if ($existing_attachment_id) {
            set_post_thumbnail($post_id, $existing_attachment_id);
            return true;
        }

        // Download image to the WordPress media library
        $attachment_id = media_sideload_image($image_url, $post_id, get_the_title($post_id), 'id');
        
        if (!is_wp_error($attachment_id)) {
            set_post_thumbnail($post_id, $attachment_id);
            return true;
        }

        return false;
    }

    /**
     * Upload media from URL to the media library
     */
    private function upload_media($image_url, $post_id = 0) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Check if image already exists in media library
        $existing_attachment_id = $this->get_attachment_id_by_url($image_url);
        
        if ($existing_attachment_id) {
            return $existing_attachment_id;
        }

        // Download image to the WordPress media library
        $attachment_id = media_sideload_image($image_url, $post_id, '', 'id');
        
        if (!is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        return false;
    }

    /**
     * Check if data contains nested arrays
     * 
     * @since 1.0.0
     * @param mixed $data The data to check
     * @return bool True if data contains nested arrays, false otherwise
     */
    private function has_nested_data($data) {
        if (!is_array($data)) {
            return false;
        }
        
        // If data is a sequential array, check its first item
        if (array_values($data) === $data) {
            $data = reset($data);
            if (!is_array($data)) {
                return false;
            }
        }
        
        // Check if any value is an array
        foreach ($data as $value) {
            if (is_array($value)) {
                return true;
            }
        }
        
        return false;
    }
    /**
     * Get attachment ID by URL
     *
     * @param string $url The attachment URL
     * @return int|false Attachment ID or false if not found
     */
    private function get_attachment_id_by_url($url) {
        global $wpdb;
        
        // Remove any query parameters from the URL
        $url = preg_replace('/\?.*/', '', $url);
        
        $attachment = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid LIKE '%%%s' AND post_type = 'attachment'", basename($url)));
        
        if (!empty($attachment)) {
            return $attachment[0];
        }
        
        return false;
    }
    
    /**
     * Handle the import content AJAX request
     * 
     * @since 1.0.0
     */
    /**
     * Handle the import content AJAX request
     *
     * @since 1.0.0
     */
    public function handle_import_content() {
        // Set content type header
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        // Verify nonce
        if (!check_ajax_referer('jpi_import_content', 'security', false)) {
            wp_send_json_error(array('message' => 'Security check failed'));
            wp_die();
        }
        
        // Check user capabilities
        if (!current_user_can('upload_files') || !current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            wp_die();
        }
        
        // Check if user is logged in and has proper permissions
        if (!is_user_logged_in() || !current_user_can('upload_files')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to import content.', 'json-post-importer'),
                'logged_in' => is_user_logged_in(),
                'has_cap' => current_user_can('upload_files')
            ));
            wp_die();
        }
        
        // Verify nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'jpi_import_content')) {
            wp_send_json_error(array(
                'message' => 'Security check failed. Please refresh the page and try again.',
                'nonce_valid' => false
            ));
            wp_die();
        }
        
        // Check for required data
        if (empty($_POST['json_data'])) {
            wp_send_json_error(array(
                'message' => __('No JSON data provided for import.', 'json-post-importer'),
                'post_data' => array_keys($_POST)
            ));
            wp_die();
        }
        
        try {
            // Decode and validate input data
            $json_data = json_decode(stripslashes($_POST['json_data']), true);
            $import_options = !empty($_POST['import_options']) ? json_decode(stripslashes($_POST['import_options']), true) : array();
            
            // Validate JSON data
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception(sprintf(
                    __('Invalid JSON data: %s', 'json-post-importer'),
                    $this->get_json_error_message(json_last_error())
                ));
            }
            
            if (!is_array($json_data) || empty($json_data)) {
                throw new Exception(__('No valid data found to import.', 'json-post-importer'));
            }
            
            // Set default import options
            $default_import_options = array(
                'update_existing' => false,
                'import_images' => true,
                'create_terms' => true,
                'post_status' => 'publish',
                'post_type' => 'post',
                'import_faq' => true,
                'import_seo' => true
            );
            
            $import_options = wp_parse_args($import_options, $default_import_options);
            
            $import_options = wp_parse_args($import_options, $default_import_options);
            
            // Process the import
            $result = $this->process_json_data($json_data, $field_mappings, $import_options);
            
            // Prepare response
            $response = array(
                'message' => sprintf(
                    _n(
                        'Successfully imported %d item.',
                        'Successfully imported %d items.',
                        count($result['imported']),
                        'json-post-importer'
                    ),
                    count($result['imported'])
                ),
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors'],
                'stats' => array(
                    'total' => count($json_data),
                    'imported' => count($result['imported']),
                    'skipped' => count($result['skipped']),
                    'errors' => count($result['errors'])
                )
            );
            
            // Add warning if there were any errors
            if (!empty($result['errors'])) {
                $response['warning'] = sprintf(
                    _n(
                        'There was %d error during import.',
                        'There were %d errors during import.',
                        count($result['errors']),
                        'json-post-importer'
                    ),
                    count($result['errors'])
                );
            }
            
            wp_send_json_success($response);
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'trace' => WP_DEBUG ? $e->getTraceAsString() : null,
                'file' => WP_DEBUG ? $e->getFile() : null,
                'line' => WP_DEBUG ? $e->getLine() : null
            ));
        }
        
        wp_die();
    }
}
