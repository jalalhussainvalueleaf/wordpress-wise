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

<div id="jpi-preview-section" class="jpi-preview-section" style="display: none;">
    <div class="jpi-card">
        <h2><?php esc_html_e('Preview & Field Mapping', 'json-post-importer'); ?></h2>
        
        <div id="jpi-preview-loading" class="jpi-loading" style="display: none;">
            <span class="spinner is-active"></span>
            <span><?php esc_html_e('Generating preview...', 'json-post-importer'); ?></span>
        </div>
        
        <div id="jpi-preview-error" class="jpi-error" style="display: none;">
            <div class="notice notice-error">
                <p id="jpi-preview-error-message"></p>
            </div>
        </div>
        
        <div id="jpi-preview-content"></div>
        
        <div class="jpi-actions">
            <button type="button" id="jpi-cancel-preview" class="button">
                <?php esc_html_e('Cancel', 'json-post-importer'); ?>
            </button>
            <button type="submit" id="jpi-confirm-import" class="button button-primary" disabled>
                <?php esc_html_e('Import Selected Items', 'json-post-importer'); ?>
            </button>
        </div>
    </div>
</div>
