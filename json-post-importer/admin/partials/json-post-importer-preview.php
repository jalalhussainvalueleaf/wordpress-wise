<?php
/**
 * Preview template for JSON Post Importer
 *
 * @package JSON_Post_Importer
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>

<div id="jpi-preview-section" style="display: none;">
    <div class="jpi-preview-container">
        <div id="jpi-preview-loading" class="jpi-loading" style="display: none;">
            <span class="spinner is-active"></span>
            <span><?php esc_html_e('Loading preview...', 'json-post-importer'); ?></span>
        </div>
        
        <div id="jpi-preview-error" class="jpi-error" style="display: none;">
            <div class="notice notice-error">
                <p id="jpi-preview-error-message"></p>
            </div>
        </div>
        
        <div id="jpi-preview-content">
            <!-- Preview content will be loaded here -->
        </div>
    </div>
</div>