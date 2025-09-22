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

<div class="wrap" id="json-post-importer">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- Add nonce field for AJAX requests -->
    <input type="hidden" id="jpi-preview-nonce" value="<?php echo wp_create_nonce('jpi_preview_nonce'); ?>" />
    
    <?php display_admin_notice(); ?>
    
    <!-- Navigation Tabs -->
    <nav class="nav-tab-wrapper">
        <a href="#import" class="nav-tab nav-tab-active" id="import-tab"><?php esc_html_e('Import', 'json-post-importer'); ?></a>
        <a href="#logs" class="nav-tab" id="logs-tab"><?php esc_html_e('Logs & Debug', 'json-post-importer'); ?></a>
    </nav>
    
    <!-- Tab Content -->
    <div class="tab-content">
        <!-- Import Tab -->
        <div id="import-content" class="tab-pane active">
            <!-- Main Upload Section -->
            <div class="jpi-main-content">
        <!-- File Upload Section -->
        <div class="jpi-upload-section">
            <div class="jpi-card">
                <h3><?php esc_html_e('Upload JSON File', 'json-post-importer'); ?></h3>
                <p><?php esc_html_e('Upload a JSON file containing your posts data or drag and drop it below.', 'json-post-importer'); ?></p>
                
                <form id="jpi-upload-form" method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('jpi_upload_nonce', 'jpi_upload_nonce'); ?>
                    <input type="hidden" name="import_type" value="file">
                    
                    <div id="jpi-drop-zone" class="jpi-drop-zone">
                        <div class="jpi-drop-zone-content">
                            <span class="spinner" id="jpi-upload-spinner" style="float: none; margin-top: 0; display: none;"></span>
                            <input type="file" id="jpi-json-file" name="jpi_json_file" accept=".json,application/json" style="display: none;">
                            <p class="jpi-drop-instructions">
                                <?php esc_html_e('Drag & drop your JSON file here or', 'json-post-importer'); ?> 
                                <a href="#" class="jpi-browse-files"><?php esc_html_e('browse files', 'json-post-importer'); ?></a>
                            </p>
                            <p id="jpi-file-info" class="jpi-file-info" style="display: none;"></p>
                        </div>
                    </div>
                    
                    <p class="submit">
                        <button type="button" id="jpi-preview-btn" class="button button-primary" disabled>
                            <?php esc_html_e('Preview & Map Fields', 'json-post-importer'); ?>
                        </button>
                    </p>
                    
                    <p class="description">
                        <?php 
                            printf(
                                /* translators: %s: Maximum upload file size */
                                esc_html__('Maximum upload file size: %s', 'json-post-importer'),
                                esc_html($max_upload_size_mb)
                            );
                        ?>
                    </p>
                </form>
            </div>
        </div>
            </div>
        </div>
        
        <!-- Logs Tab -->
        <div id="logs-content" class="tab-pane" style="display: none;">
            <div class="jpi-logs-section">
                <div class="jpi-card">
                    <div class="jpi-logs-header">
                        <h3><?php esc_html_e('Plugin Logs & Debug', 'json-post-importer'); ?></h3>
                        <div class="jpi-logs-controls">
                            <label class="jpi-debug-toggle">
                                <input type="checkbox" id="jpi-debug-mode" />
                                <?php esc_html_e('Debug Mode', 'json-post-importer'); ?>
                            </label>
                            <button type="button" id="jpi-refresh-logs" class="button">
                                <?php esc_html_e('Refresh', 'json-post-importer'); ?>
                            </button>
                            <button type="button" id="jpi-clear-logs" class="button">
                                <?php esc_html_e('Clear Logs', 'json-post-importer'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <div class="jpi-logs-stats">
                        <div class="jpi-stat-item">
                            <span class="jpi-stat-label"><?php esc_html_e('Total Files:', 'json-post-importer'); ?></span>
                            <span id="jpi-stat-files" class="jpi-stat-value">-</span>
                        </div>
                        <div class="jpi-stat-item">
                            <span class="jpi-stat-label"><?php esc_html_e('Total Size:', 'json-post-importer'); ?></span>
                            <span id="jpi-stat-size" class="jpi-stat-value">-</span>
                        </div>
                        <div class="jpi-stat-item">
                            <span class="jpi-stat-label"><?php esc_html_e('Debug Mode:', 'json-post-importer'); ?></span>
                            <span id="jpi-debug-mode-status" class="jpi-stat-value">-</span>
                        </div>
                    </div>
                    
                    <!-- Log Statistics -->
                    <div class="jpi-log-stats">
                        <div class="jpi-stat-card files">
                            <h4><?php esc_html_e('Log Files', 'json-post-importer'); ?></h4>
                            <p class="jpi-stat-value" id="jpi-log-files-count">0</p>
                        </div>
                        <div class="jpi-stat-card size">
                            <h4><?php esc_html_e('Total Size', 'json-post-importer'); ?></h4>
                            <p class="jpi-stat-value" id="jpi-log-total-size">0 B</p>
                        </div>
                        <div class="jpi-stat-card debug">
                            <h4><?php esc_html_e('Debug Mode', 'json-post-importer'); ?></h4>
                            <p class="jpi-stat-value" id="jpi-debug-mode-status">Disabled</p>
                        </div>
                    </div>
                    
                    <!-- Log Files List -->
                    <div class="jpi-log-files">
                        <h4><?php esc_html_e('Available Log Files', 'json-post-importer'); ?></h4>
                        <div id="jpi-log-files-list">
                            <div class="jpi-logs-loading">
                                <?php esc_html_e('Loading log files...', 'json-post-importer'); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Log Viewer -->
                    <div class="jpi-log-viewer" style="display: none;">
                        <div class="jpi-log-viewer-header">
                            <h4 class="jpi-log-viewer-title"><?php esc_html_e('Log Viewer', 'json-post-importer'); ?></h4>
                            <div class="jpi-log-viewer-controls">
                                <label for="jpi-log-lines"><?php esc_html_e('Show:', 'json-post-importer'); ?></label>
                                <select id="jpi-log-lines">
                                    <option value="50">50 lines</option>
                                    <option value="100" selected>100 lines</option>
                                    <option value="500">500 lines</option>
                                    <option value="0"><?php esc_html_e('All', 'json-post-importer'); ?></option>
                                </select>
                                <label>
                                    <input type="checkbox" id="jpi-auto-scroll" checked>
                                    <?php esc_html_e('Auto-scroll', 'json-post-importer'); ?>
                                </label>
                                <span class="jpi-log-count"></span>
                            </div>
                        </div>
                        
                        <!-- Log Filters -->
                        <div class="jpi-log-filters">
                            <div class="jpi-log-filter-group">
                                <label for="jpi-log-level-filter"><?php esc_html_e('Level:', 'json-post-importer'); ?></label>
                                <select id="jpi-log-level-filter">
                                    <option value="all"><?php esc_html_e('All Levels', 'json-post-importer'); ?></option>
                                    <option value="error"><?php esc_html_e('Error', 'json-post-importer'); ?></option>
                                    <option value="warning"><?php esc_html_e('Warning', 'json-post-importer'); ?></option>
                                    <option value="info"><?php esc_html_e('Info', 'json-post-importer'); ?></option>
                                    <option value="debug"><?php esc_html_e('Debug', 'json-post-importer'); ?></option>
                                </select>
                            </div>
                            
                            <div class="jpi-log-search">
                                <input type="text" id="jpi-log-search" placeholder="<?php esc_attr_e('Search logs...', 'json-post-importer'); ?>">
                                <span class="jpi-log-search-icon dashicons dashicons-search"></span>
                            </div>
                        </div>
                        
                        <div class="jpi-log-content">
                            <div class="jpi-logs-empty">
                                <?php esc_html_e('Select a log file to view its contents.', 'json-post-importer'); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Log Actions -->
                    <div class="jpi-log-actions">
                        <button type="button" id="jpi-clear-all-logs" class="button button-secondary clear">
                            <?php esc_html_e('Clear All Logs', 'json-post-importer'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Modal Backdrop -->
    <div id="jpi-modal-backdrop" class="jpi-modal-backdrop" style="display: none;"></div>

    <!-- Modal Wrapper -->
    <div id="jpi-modal-wrap" class="jpi-modal-wrap" role="dialog" aria-labelledby="jpi-modal-title" aria-describedby="jpi-modal-description" tabindex="-1" style="display: none;">
        <div class="jpi-modal-content">
            <div class="jpi-modal-header">
                <h2 id="jpi-modal-title"><?php esc_html_e('Preview & Field Mapping', 'json-post-importer'); ?></h2>
                <button type="button" id="jpi-modal-close" class="jpi-modal-close" aria-label="<?php esc_attr_e('Close', 'json-post-importer'); ?>">
                    <span class="screen-reader-text"><?php esc_html_e('Close', 'json-post-importer'); ?></span>
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            
            <div class="jpi-modal-body">
                <div id="jpi-preview-loading" class="jpi-loading" style="display: none;">
                    <span class="spinner is-active"></span>
                    <span><?php esc_html_e('Generating preview...', 'json-post-importer'); ?></span>
                </div>
                
                <div id="jpi-preview-error" class="jpi-error" style="display: none;">
                    <div class="notice notice-error">
                        <p id="jpi-preview-error-message"></p>
                    </div>
                </div>
                
                <!-- Tabs Navigation -->
                <nav class="jpi-tab-nav">
                    <ul class="jpi-tab-links">
                        <li><a href="#jpi-tab-preview" class="jpi-tab-link active"><?php esc_html_e('Preview', 'json-post-importer'); ?></a></li>
                        <li><a href="#jpi-tab-mapping" class="jpi-tab-link"><?php esc_html_e('Field Mapping', 'json-post-importer'); ?></a></li>
                    </ul>
                </nav>
                
                <!-- Tab Content -->
                <div class="jpi-tab-content">
                    <div id="jpi-tab-preview" class="jpi-tab-pane active">
                        <div id="jpi-json-preview" class="jpi-json-preview">
                            <pre><code id="jpi-json-content"></code></pre>
                        </div>
                    </div>
                    
                    <div id="jpi-tab-mapping" class="jpi-tab-pane">
                        <div id="jpi-field-mapping-container">
                            <div class="jpi-mapping-placeholder">
                                <p><?php esc_html_e('After uploading a JSON file, you can map the fields to WordPress post fields here.', 'json-post-importer'); ?></p>
                            </div>
                        </div>
                        
                        <!-- Nested Field Mapping Section -->
                        <div id="jpi-nested-field-mapping" class="jpi-nested-field-mapping-section" style="display: none;">
                            <div class="jpi-nested-mapping-placeholder">
                                <p><?php esc_html_e('Enhanced nested field mapping will appear here after uploading a JSON file with nested structure.', 'json-post-importer'); ?></p>
                            </div>
                        </div>
                        
                        <!-- Import Options Section -->
                        <div id="jpi-import-options" class="jpi-import-options-section" style="display: none;">
                            <h3><?php esc_html_e('Import Options', 'json-post-importer'); ?></h3>
                            <div class="jpi-options-grid">
                                <div class="jpi-option-group">
                                    <label for="jpi-post-type"><?php esc_html_e('Post Type', 'json-post-importer'); ?></label>
                                    <select id="jpi-post-type" name="jpi_post_type" class="regular-text">
                                        <option value="post"><?php esc_html_e('Post', 'json-post-importer'); ?></option>
                                        <option value="page"><?php esc_html_e('Page', 'json-post-importer'); ?></option>
                                        <?php
                                        $post_types = get_post_types(array('public' => true, '_builtin' => false), 'objects');
                                        foreach ($post_types as $post_type) {
                                            echo '<option value="' . esc_attr($post_type->name) . '">' . esc_html($post_type->label) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <p class="description"><?php esc_html_e('Select the post type for imported content.', 'json-post-importer'); ?></p>
                                </div>
                                
                                <div class="jpi-option-group">
                                    <label for="jpi-post-status"><?php esc_html_e('Default Post Status', 'json-post-importer'); ?></label>
                                    <select id="jpi-post-status" name="jpi_post_status" class="regular-text">
                                        <option value="draft"><?php esc_html_e('Draft', 'json-post-importer'); ?></option>
                                        <option value="publish"><?php esc_html_e('Published', 'json-post-importer'); ?></option>
                                        <option value="pending"><?php esc_html_e('Pending Review', 'json-post-importer'); ?></option>
                                        <option value="private"><?php esc_html_e('Private', 'json-post-importer'); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e('Default status for imported posts (can be overridden by field mapping).', 'json-post-importer'); ?></p>
                                </div>
                                
                                <div class="jpi-option-group">
                                    <label for="jpi-batch-size"><?php esc_html_e('Batch Size', 'json-post-importer'); ?></label>
                                    <input type="number" id="jpi-batch-size" name="jpi_batch_size" value="10" min="1" max="100" class="small-text">
                                    <p class="description"><?php esc_html_e('Number of posts to process in each batch. Lower numbers are safer for large imports.', 'json-post-importer'); ?></p>
                                </div>
                                
                                <div class="jpi-option-group">
                                    <label for="jpi-default-author"><?php esc_html_e('Default Author', 'json-post-importer'); ?></label>
                                    <?php
                                    wp_dropdown_users(array(
                                        'name' => 'jpi_default_author',
                                        'id' => 'jpi-default-author',
                                        'selected' => get_current_user_id(),
                                        'capability' => 'edit_posts',
                                        'class' => 'regular-text'
                                    ));
                                    ?>
                                    <p class="description"><?php esc_html_e('Default author for imported posts (can be overridden by field mapping).', 'json-post-importer'); ?></p>
                                </div>
                            </div>
                            
                            <div class="jpi-checkbox-options">
                                <label>
                                    <input type="checkbox" id="jpi-update-existing" name="jpi_update_existing" checked>
                                    <?php esc_html_e('Update existing posts', 'json-post-importer'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('If a post with the same title or slug exists, update it instead of creating a duplicate.', 'json-post-importer'); ?></p>
                                
                                <label>
                                    <input type="checkbox" id="jpi-import-images" name="jpi_import_images" checked>
                                    <?php esc_html_e('Import featured images', 'json-post-importer'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Download and import featured images from URLs in the JSON data.', 'json-post-importer'); ?></p>
                                
                                <label>
                                    <input type="checkbox" id="jpi-create-terms" name="jpi_create_terms" checked>
                                    <?php esc_html_e('Create missing taxonomy terms', 'json-post-importer'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Automatically create categories, tags, and other taxonomy terms if they don\'t exist.', 'json-post-importer'); ?></p>
                                
                                <label>
                                    <input type="checkbox" id="jpi-preserve-ids" name="jpi_preserve_ids">
                                    <?php esc_html_e('Preserve post IDs', 'json-post-importer'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Attempt to preserve post IDs from the JSON data (may cause conflicts).', 'json-post-importer'); ?></p>
                                
                                <label>
                                    <input type="checkbox" id="jpi-import-meta" name="jpi_import_meta" checked>
                                    <?php esc_html_e('Import custom fields', 'json-post-importer'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Import custom fields and meta data from the JSON.', 'json-post-importer'); ?></p>
                                
                                <label>
                                    <input type="checkbox" id="jpi-dry-run" name="jpi_dry_run">
                                    <?php esc_html_e('Dry run (preview only)', 'json-post-importer'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Test the import without actually creating posts. Useful for validation.', 'json-post-importer'); ?></p>
                            </div>
                            
                            <!-- Advanced Options -->
                            <details class="jpi-advanced-options">
                                <summary><?php esc_html_e('Advanced Options', 'json-post-importer'); ?></summary>
                                <div class="jpi-advanced-content">
                                    <div class="jpi-option-group">
                                        <label for="jpi-date-format"><?php esc_html_e('Date Format', 'json-post-importer'); ?></label>
                                        <input type="text" id="jpi-date-format" name="jpi_date_format" value="Y-m-d H:i:s" class="regular-text">
                                        <p class="description"><?php esc_html_e('PHP date format for parsing dates from JSON. Default: Y-m-d H:i:s', 'json-post-importer'); ?></p>
                                    </div>
                                    
                                    <div class="jpi-option-group">
                                        <label for="jpi-timeout"><?php esc_html_e('Request Timeout (seconds)', 'json-post-importer'); ?></label>
                                        <input type="number" id="jpi-timeout" name="jpi_timeout" value="30" min="10" max="300" class="small-text">
                                        <p class="description"><?php esc_html_e('Maximum time to wait for each batch to process.', 'json-post-importer'); ?></p>
                                    </div>
                                    
                                    <div class="jpi-option-group">
                                        <label for="jpi-memory-limit"><?php esc_html_e('Memory Limit Override', 'json-post-importer'); ?></label>
                                        <input type="text" id="jpi-memory-limit" name="jpi_memory_limit" placeholder="512M" class="regular-text">
                                        <p class="description"><?php esc_html_e('Override PHP memory limit for large imports (e.g., 512M, 1G).', 'json-post-importer'); ?></p>
                                    </div>
                                    
                                    <label>
                                        <input type="checkbox" id="jpi-skip-duplicates" name="jpi_skip_duplicates">
                                        <?php esc_html_e('Skip duplicate content', 'json-post-importer'); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e('Skip posts with identical content to existing posts.', 'json-post-importer'); ?></p>
                                    
                                    <label>
                                        <input type="checkbox" id="jpi-enable-revisions" name="jpi_enable_revisions">
                                        <?php esc_html_e('Enable post revisions', 'json-post-importer'); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e('Create post revisions during import (may slow down the process).', 'json-post-importer'); ?></p>
                                </div>
                            </details>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="jpi-modal-footer">
                <button type="button" id="jpi-cancel-preview" class="button">
                    <?php esc_html_e('Cancel', 'json-post-importer'); ?>
                </button>
                <button type="button" class="jpi-import-button button button-primary" disabled>
                    <?php esc_html_e('Import Selected Items', 'json-post-importer'); ?>
                </button>
            </div>
        </div>
    </div>

    
    <!-- Import History Section -->
    <div class="jpi-card" style="margin-top: 20px;">
        <div class="jpi-section-header">
            <h2><?php echo esc_html__('Import History', 'json-post-importer'); ?></h2>
            <div class="jpi-section-actions">
                <button type="button" id="jpi-refresh-history" class="button">
                    <?php echo esc_html__('Refresh', 'json-post-importer'); ?>
                </button>
                <button type="button" id="jpi-view-logs" class="button">
                    <?php echo esc_html__('View Logs', 'json-post-importer'); ?>
                </button>
            </div>
        </div>
        
        <div id="jpi-history-container">
            <div class="jpi-loading-history">
                <span class="spinner is-active"></span>
                <span><?php echo esc_html__('Loading import history...', 'json-post-importer'); ?></span>
            </div>
        </div>
    </div>

    <!-- API Information Section -->
    <div class="jpi-card" style="margin-top: 20px;">
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

    <!-- Logs Modal -->
    <div id="jpi-logs-modal-backdrop" class="jpi-modal-backdrop" style="display: none;"></div>
    <div id="jpi-logs-modal-wrap" class="jpi-modal-wrap" role="dialog" aria-labelledby="jpi-logs-modal-title" tabindex="-1" style="display: none;">
        <div class="jpi-modal-content">
            <div class="jpi-modal-header">
                <h2 id="jpi-logs-modal-title"><?php esc_html_e('Import Logs', 'json-post-importer'); ?></h2>
                <button type="button" id="jpi-logs-modal-close" class="jpi-modal-close" aria-label="<?php esc_attr_e('Close', 'json-post-importer'); ?>">
                    <span class="screen-reader-text"><?php esc_html_e('Close', 'json-post-importer'); ?></span>
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            
            <div class="jpi-modal-body">
                <div class="jpi-logs-filters">
                    <select id="jpi-logs-level-filter">
                        <option value=""><?php esc_html_e('All Levels', 'json-post-importer'); ?></option>
                        <option value="info"><?php esc_html_e('Info', 'json-post-importer'); ?></option>
                        <option value="warning"><?php esc_html_e('Warning', 'json-post-importer'); ?></option>
                        <option value="error"><?php esc_html_e('Error', 'json-post-importer'); ?></option>
                    </select>
                    <input type="text" id="jpi-logs-search" placeholder="<?php esc_attr_e('Search logs...', 'json-post-importer'); ?>" />
                </div>
                
                <div id="jpi-logs-container">
                    <div class="jpi-loading">
                        <span class="spinner is-active"></span>
                        <span><?php esc_html_e('Loading logs...', 'json-post-importer'); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="jpi-modal-footer">
                <button type="button" id="jpi-clear-logs" class="button button-secondary">
                    <?php esc_html_e('Clear Logs', 'json-post-importer'); ?>
                </button>
                <button type="button" id="jpi-close-logs" class="button button-primary">
                    <?php esc_html_e('Close', 'json-post-importer'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
