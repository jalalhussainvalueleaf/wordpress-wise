<?php
/**
 * Admin display template for JSON Post Importer
 *
 * @package JSON_Post_Importer
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get plugin settings
$max_upload_size = wp_max_upload_size();
$max_upload_size_mb = size_format($max_upload_size, 2);

// Get any existing field mappings
$field_mappings = get_option('jpi_field_mappings', array());

// Check for messages
$message = isset($_GET['jpi_message']) ? sanitize_text_field($_GET['jpi_message']) : '';
$message_class = '';
$message_text = '';

switch ($message) {
    case 'import_success':
        $message_class = 'notice-success';
        $message_text = __('Posts imported successfully!', 'json-post-importer');
        break;
    case 'import_error':
        $message_class = 'notice-error';
        $message_text = isset($_GET['error']) ? urldecode($_GET['error']) : __('An error occurred during import.', 'json-post-importer');
        break;
}

// Display admin notices
function display_admin_notice() {
    if (isset($_GET['jpi_message'])) {
        $message = '';
        $class = 'notice notice-error';
        
        switch ($_GET['jpi_message']) {
            case 'no_file':
                $message = __('Please select a file to upload.', 'json-post-importer');
                break;
            case 'upload_error':
                $message = __('There was an error uploading your file.', 'json-post-importer');
                break;
            case 'invalid_file_type':
                $message = __('Invalid file type. Please upload a JSON file.', 'json-post-importer');
                break;
            case 'invalid_json':
                $message = __('The file you uploaded is not valid JSON.', 'json-post-importer');
                break;
            case 'import_success':
                $message = __('Posts imported successfully!', 'json-post-importer');
                $class = 'notice notice-success';
                break;
            case 'import_error':
                $message = __('There was an error importing the posts.', 'json-post-importer');
                break;
            default:
                $message = '';
        }
        
        if (!empty($message)) {
            echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($message) . '</p></div>';
        }
    }
}
?>

<div class="wrap">
    <!-- <h1><?php echo esc_html__('JSON Post Importer', 'json-post-importer'); ?></h1> -->
    
    <?php display_admin_notice(); ?>
    
    <div class="wrap" id="json-post-importer">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php if ($message_text) : ?>
        <div class="notice <?php echo esc_attr($message_class); ?> is-dismissible">
            <p><?php echo esc_html($message_text); ?></p>
            <button type="button" class="notice-dismiss">
                <span class="screen-reader-text"><?php esc_html_e('Dismiss this notice.', 'json-post-importer'); ?></span>
            </button>
        </div>
    <?php endif; ?>
    
    <div class="jpi-card">
        <h2><?php esc_html_e('Upload JSON File', 'json-post-importer'); ?></h2>
        <p><?php esc_html_e('Upload a JSON file containing your posts data or drag and drop it below.', 'json-post-importer'); ?></p>
        
        <form id="jpi-upload-form" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('jpi_upload_nonce', 'jpi_nonce'); ?>
            
            <div id="jpi-drop-zone" class="jpi-drop-zone">
                <div class="jpi-drop-zone-content">
                    <span class="dashicons dashicons-upload"></span>
                    <p class="jpi-upload-instructions">
                        <?php esc_html_e('Drag & drop your JSON file here or', 'json-post-importer'); ?>
                        <button type="button" class="button button-secondary" id="jpi-browse-files">
                            <?php esc_html_e('Browse Files', 'json-post-importer'); ?>
                        </button>
                    </p>
                    <input type="file" 
                           id="json-file" 
                           name="json-file"
                           class="jpi-file-input" 
                           accept=".json,application/json" 
                           required
                           aria-required="true">
                    <p class="jpi-file-info" id="jpi-file-info">
                        <?php 
                        printf(
                            /* translators: %s: Maximum upload size */
                            esc_html__('Maximum file size: %s', 'json-post-importer'),
                            esc_html(size_format(wp_max_upload_size()))
                        );
                        ?>
                    </p>
                    
                    <div class="jpi-actions" style="margin-top: 15px;">
                        <button type="button" id="jpi-preview-btn" class="button button-secondary" disabled>
                            <?php esc_html_e('Preview', 'json-post-importer'); ?>
                        </button>
                        <button type="submit" id="jpi-submit" class="button button-primary" disabled>
                            <?php esc_html_e('Import', 'json-post-importer'); ?>
                        </button>
                        <span class="spinner" id="jpi-upload-spinner" style="float: none; margin-top: 0;"></span>
                    </div>
                </div>
                
                <div id="jpi-preview-section" class="jpi-preview-section" style="display: none;">
                    <h3><?php esc_html_e('Preview', 'json-post-importer'); ?></h3>
                    <div id="jpi-preview-loading" class="jpi-loading" style="display: none;">
                        <div class="jpi-progress-container">
                            <div class="jpi-progress-bar">
                                <div class="jpi-progress-bar-fill" style="width: 0%"></div>
                            </div>
                            <div class="jpi-progress-text"><?php esc_html_e('Processing file...', 'json-post-importer'); ?></div>
                            <div class="jpi-progress-details">
                                <span class="jpi-progress-percentage">0%</span>
                                <span class="jpi-progress-status"><?php esc_html_e('Reading file...', 'json-post-importer'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div id="jpi-preview-error" class="jpi-error" style="display: none;"></div>
                    <div id="jpi-preview-content"></div>
                    
                    <!-- Import Options -->
                    <div id="jpi-import-options" class="jpi-import-options" style="display: none; margin: 20px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                        <h3><?php esc_html_e('Import Options', 'json-post-importer'); ?></h3>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Post Type', 'json-post-importer'); ?></th>
                                <td>
                                    <select id="jpi-post-type" name="jpi_post_type" class="regular-text">
                                        <?php
                                        $post_types = get_post_types(array('public' => true), 'objects');
                                        foreach ($post_types as $post_type) {
                                            echo sprintf(
                                                '<option value="%s"%s>%s</option>',
                                                esc_attr($post_type->name),
                                                selected('post', $post_type->name, false),
                                                esc_html($post_type->labels->singular_name)
                                            );
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Post Status', 'json-post-importer'); ?></th>
                                <td>
                                    <select id="jpi-post-status" name="jpi_post_status" class="regular-text">
                                        <?php
                                        $statuses = get_post_statuses();
                                        foreach ($statuses as $status => $label) {
                                            echo sprintf(
                                                '<option value="%s"%s>%s</option>',
                                                esc_attr($status),
                                                selected('publish', $status, false),
                                                esc_html($label)
                                            );
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Options', 'json-post-importer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="jpi-update-existing" name="jpi_update_existing" value="1">
                                        <?php esc_html_e('Update existing posts', 'json-post-importer'); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e('Check this to update posts that already exist (matched by title or custom field).', 'json-post-importer'); ?></p>
                                    
                                    <label style="display: block; margin-top: 10px;">
                                        <input type="checkbox" id="jpi-import-images" name="jpi_import_images" value="1" checked>
                                        <?php esc_html_e('Import featured images', 'json-post-importer'); ?>
                                    </label>
                                    
                                    <label style="display: block; margin-top: 10px;">
                                        <input type="checkbox" id="jpi-create-terms" name="jpi_create_terms" value="1" checked>
                                        <?php esc_html_e('Create new categories/tags', 'json-post-importer'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Field Mappings -->
                    <div id="jpi-field-mappings" class="jpi-field-mappings" style="display: none; margin: 20px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                        <h3><?php esc_html_e('Field Mappings', 'json-post-importer'); ?></h3>
                        <p class="description"><?php esc_html_e('Map your JSON fields to WordPress post fields.', 'json-post-importer'); ?></p>
                        <div id="jpi-field-mappings-container"></div>
                    </div>
                    
                    <div class="jpi-preview-actions">
                        <button type="button" id="jpi-cancel-preview" class="button">
                            <?php esc_html_e('Cancel', 'json-post-importer'); ?>
                        </button>
                        <button type="button" id="jpi-confirm-import" class="button button-primary">
                            <?php esc_html_e('Import', 'json-post-importer'); ?>
                        </button>
                        <span id="jpi-upload-spinner" class="spinner" style="float: none; display: none;"></span>
                    </div>
                    
                    <!-- Error Details -->
                    <div id="jpi-error-details" class="jpi-error-details" style="display: none; margin-top: 20px; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;">
                        <!-- Error details will be inserted here by JavaScript -->
                    </div>
                </div>
                
                <span class="spinner" id="jpi-upload-spinner"></span>
            </div>
        </form>
       
    </div>
    
    <!-- Preview Section -->
    <?php include_once plugin_dir_path(__FILE__) . 'json-post-importer-preview.php'; ?>
    
    <div class="card" style="margin-top: 20px;">
        <h2><?php echo esc_html__('API Endpoint', 'json-post-importer'); ?></h2>
        <p><?php echo esc_html__('You can also import posts programmatically using the following REST API endpoint:', 'json-post-importer'); ?></p>
        
        <div class="code-block">
            <code>POST <?php echo esc_url(rest_url('json-post-importer/v1/import')); ?></code>
        </div>
        
        <h3><?php echo esc_html__('Authentication', 'json-post-importer'); ?></h3>
        <p><?php echo esc_html__('You need to be authenticated with the proper capabilities to use this endpoint.', 'json-post-importer'); ?></p>
        
        <h3><?php echo esc_html__('Example Request', 'json-post-importer'); ?></h3>
        <div class="code-block">
            <pre><code>curl -X POST \
  <?php echo esc_url(rest_url('json-post-importer/v1/import')); ?> \
  -H 'Authorization: Bearer YOUR_AUTH_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
    "content": {
      "heading": "<?php echo esc_js(__('Post Title', 'json-post-importer')); ?>",
      "content": "<?php echo esc_js(__('Post content here...', 'json-post-importer')); ?>",
      "status": "publish"
    }
  }'</code></pre>
        </div>
    </div>
</div>
