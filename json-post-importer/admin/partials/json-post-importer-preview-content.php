<?php
/**
 * Preview content template for JSON Post Importer
 *
 * @package JSON_Post_Importer
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get JSON data from global variable or transient
$jsonData = null;
if (isset($GLOBALS['jsonData'])) {
    $jsonData = $GLOBALS['jsonData'];
} else {
    $transient_key = 'jpi_import_data_' . get_current_user_id();
    $jsonData = get_transient($transient_key);
}

if (empty($jsonData)) {
    echo '<div class="notice notice-error"><p>' . __('No preview data available.', 'json-post-importer') . '</p></div>';
    return;
}

// Ensure we have an array to work with
$items = is_array($jsonData) ? $jsonData : array($jsonData);
$total_items = count($items);
$preview_items = array_slice($items, 0, 5); // Show first 5 items for preview

?>

<div id="jpi-preview-section" class="jpi-preview-section">
    <div class="jpi-preview-header">
        <h3><?php esc_html_e('JSON Data Preview', 'json-post-importer'); ?></h3>
        <p class="description">
            <?php 
            printf(
                /* translators: %1$d: number of items shown, %2$d: total number of items */
                esc_html__('Showing %1$d of %2$d items from your JSON file.', 'json-post-importer'),
                count($preview_items),
                $total_items
            );
            ?>
        </p>
    </div>

    <div class="jpi-preview-content">
        <?php foreach ($preview_items as $index => $item) : ?>
            <div class="jpi-preview-item" data-index="<?php echo esc_attr($index); ?>">
                <h4><?php printf(__('Item #%d', 'json-post-importer'), $index + 1); ?></h4>
                
                <div class="jpi-item-preview">
                    <table class="jpi-preview-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Field', 'json-post-importer'); ?></th>
                                <th><?php esc_html_e('Value', 'json-post-importer'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (is_array($item)) {
                                foreach ($item as $key => $value) {
                                    echo '<tr>';
                                    echo '<td><code>' . esc_html($key) . '</code></td>';
                                    echo '<td>' . esc_html(format_preview_value($value)) . '</td>';
                                    echo '</tr>';
                                }
                            } else {
                                echo '<tr>';
                                echo '<td colspan="2">' . esc_html__('Invalid item format', 'json-post-importer') . '</td>';
                                echo '</tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($total_items > 5) : ?>
        <div class="jpi-preview-footer">
            <p class="description">
                <?php 
                printf(
                    /* translators: %d: number of additional items */
                    esc_html__('... and %d more items', 'json-post-importer'),
                    $total_items - 5
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- Import Options Section -->
    <div class="jpi-import-options-section">
        <h4><?php esc_html_e('Import Options', 'json-post-importer'); ?></h4>
        
        <div class="jpi-import-options-grid">
            <div class="jpi-import-option">
                <label for="jpi-post-type"><?php esc_html_e('Post Type', 'json-post-importer'); ?></label>
                <select id="jpi-post-type" name="jpi_post_type">
                    <?php
                    $post_types = get_post_types(array('public' => true), 'objects');
                    foreach ($post_types as $post_type) {
                        printf(
                            '<option value="%s"%s>%s</option>',
                            esc_attr($post_type->name),
                            selected('post', $post_type->name, false),
                            esc_html($post_type->labels->singular_name)
                        );
                    }
                    ?>
                </select>
            </div>

            <div class="jpi-import-option">
                <label for="jpi-post-status"><?php esc_html_e('Post Status', 'json-post-importer'); ?></label>
                <select id="jpi-post-status" name="jpi_post_status">
                    <?php
                    $statuses = get_post_statuses();
                    foreach ($statuses as $status => $label) {
                        printf(
                            '<option value="%s"%s>%s</option>',
                            esc_attr($status),
                            selected('draft', $status, false),
                            esc_html($label)
                        );
                    }
                    ?>
                </select>
            </div>

            <div class="jpi-import-option">
                <label for="jpi-post-author"><?php esc_html_e('Default Author', 'json-post-importer'); ?></label>
                <?php
                wp_dropdown_users(array(
                    'id' => 'jpi-post-author',
                    'name' => 'jpi_post_author',
                    'selected' => get_current_user_id(),
                    'show_option_none' => __('Current User', 'json-post-importer'),
                    'option_none_value' => get_current_user_id()
                ));
                ?>
            </div>
        </div>

        <div class="jpi-import-checkboxes">
            <label>
                <input type="checkbox" id="jpi-update-existing" name="jpi_update_existing" value="1">
                <?php esc_html_e('Update existing posts', 'json-post-importer'); ?>
            </label>
            <p class="description"><?php esc_html_e('Check this to update posts that already exist (matched by title).', 'json-post-importer'); ?></p>

            <label>
                <input type="checkbox" id="jpi-import-images" name="jpi_import_images" value="1" checked>
                <?php esc_html_e('Import featured images', 'json-post-importer'); ?>
            </label>
            <p class="description"><?php esc_html_e('Download and import images from URLs in the JSON data.', 'json-post-importer'); ?></p>

            <label>
                <input type="checkbox" id="jpi-create-terms" name="jpi_create_terms" value="1" checked>
                <?php esc_html_e('Create new categories/tags', 'json-post-importer'); ?>
            </label>
            <p class="description"><?php esc_html_e('Automatically create categories and tags that don\'t exist.', 'json-post-importer'); ?></p>
        </div>
    </div>

    <!-- Field Mapping Section -->
    <div class="jpi-field-mapping-container">
        <h4><?php esc_html_e('Field Mapping', 'json-post-importer'); ?></h4>
        <p class="description"><?php esc_html_e('Map your JSON fields to WordPress post fields.', 'json-post-importer'); ?></p>
        
        <div class="jpi-field-mapping-section">
            <h5><?php esc_html_e('Standard Fields', 'json-post-importer'); ?></h5>
            
            <table class="jpi-mapping-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('WordPress Field', 'json-post-importer'); ?></th>
                        <th><?php esc_html_e('JSON Field', 'json-post-importer'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $standard_fields = array(
                        'post_title' => array('label' => __('Post Title', 'json-post-importer'), 'required' => true),
                        'post_content' => array('label' => __('Post Content', 'json-post-importer'), 'required' => false),
                        'post_excerpt' => array('label' => __('Post Excerpt', 'json-post-importer'), 'required' => false),
                        'post_status' => array('label' => __('Post Status', 'json-post-importer'), 'required' => false),
                        'post_date' => array('label' => __('Post Date', 'json-post-importer'), 'required' => false),
                        'post_author' => array('label' => __('Post Author', 'json-post-importer'), 'required' => false),
                    );

                    // Get available JSON fields from the first item
                    $available_fields = array();
                    if (!empty($items[0]) && is_array($items[0])) {
                        $available_fields = array_keys($items[0]);
                    }

                    foreach ($standard_fields as $field_key => $field_info) :
                    ?>
                        <tr>
                            <td>
                                <label for="mapping_<?php echo esc_attr($field_key); ?>" class="<?php echo $field_info['required'] ? 'required' : ''; ?>">
                                    <?php echo esc_html($field_info['label']); ?>
                                </label>
                            </td>
                            <td>
                                <select name="field_mapping[standard][<?php echo esc_attr($field_key); ?>]" id="mapping_<?php echo esc_attr($field_key); ?>">
                                    <option value=""><?php esc_html_e('-- Select Field --', 'json-post-importer'); ?></option>
                                    <?php foreach ($available_fields as $json_field) : ?>
                                        <?php
                                        // Auto-select likely matches
                                        $selected = '';
                                        $field_lower = strtolower(str_replace('post_', '', $field_key));
                                        $json_lower = strtolower($json_field);
                                        
                                        if ($json_lower === $field_lower || 
                                            $json_lower === $field_info['label'] ||
                                            ($field_key === 'post_title' && in_array($json_lower, array('title', 'name', 'heading'))) ||
                                            ($field_key === 'post_content' && in_array($json_lower, array('content', 'body', 'text', 'description'))) ||
                                            ($field_key === 'post_excerpt' && in_array($json_lower, array('excerpt', 'summary', 'abstract')))) {
                                            $selected = ' selected';
                                        }
                                        ?>
                                        <option value="<?php echo esc_attr($json_field); ?>"<?php echo $selected; ?>>
                                            <?php echo esc_html($json_field); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Custom Fields Section -->
        <div class="jpi-field-mapping-section">
            <h5><?php esc_html_e('Custom Fields', 'json-post-importer'); ?></h5>
            <div class="jpi-custom-fields">
                <div id="jpi-custom-fields-container">
                    <!-- Custom field mappings will be added here dynamically -->
                </div>
                <button type="button" class="button jpi-add-custom-field">
                    <?php esc_html_e('Add Custom Field Mapping', 'json-post-importer'); ?>
                </button>
            </div>
        </div>

        <!-- Taxonomy Mapping Section -->
        <div class="jpi-field-mapping-section">
            <h5><?php esc_html_e('Taxonomies', 'json-post-importer'); ?></h5>
            <div class="jpi-taxonomy-fields">
                <div id="jpi-taxonomy-fields-container">
                    <!-- Taxonomy mappings will be added here dynamically -->
                </div>
                <button type="button" class="button jpi-add-taxonomy-mapping">
                    <?php esc_html_e('Add Taxonomy Mapping', 'json-post-importer'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Import Actions -->
    <div class="jpi-preview-actions">
        <button type="button" id="jpi-cancel-preview" class="button">
            <?php esc_html_e('Cancel', 'json-post-importer'); ?>
        </button>
        <button type="button" class="jpi-import-button button button-primary">
            <?php esc_html_e('Start Import', 'json-post-importer'); ?>
        </button>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Store available JSON fields for dynamic field mapping
    var availableFields = <?php echo json_encode($available_fields); ?>;
    var availableTaxonomies = <?php echo json_encode(array_keys(get_taxonomies(array('public' => true), 'names'))); ?>;
    
    // Add custom field mapping
    $('.jpi-add-custom-field').on('click', function() {
        var container = $('#jpi-custom-fields-container');
        var index = container.children().length;
        
        var html = '<div class="jpi-custom-field-row">';
        html += '<label>Meta Key:</label>';
        html += '<input type="text" name="field_mapping[custom][' + index + '][meta_key]" placeholder="custom_field_name" />';
        html += '<label>JSON Field:</label>';
        html += '<select name="field_mapping[custom][' + index + '][field]">';
        html += '<option value="">-- Select Field --</option>';
        
        availableFields.forEach(function(field) {
            html += '<option value="' + field + '">' + field + '</option>';
        });
        
        html += '</select>';
        html += '<button type="button" class="button jpi-remove-field">Remove</button>';
        html += '</div>';
        
        container.append(html);
    });
    
    // Add taxonomy mapping
    $('.jpi-add-taxonomy-mapping').on('click', function() {
        var container = $('#jpi-taxonomy-fields-container');
        var index = container.children().length;
        
        var html = '<div class="jpi-taxonomy-row">';
        html += '<label>Taxonomy:</label>';
        html += '<select name="field_mapping[taxonomies][' + index + '][taxonomy]">';
        html += '<option value="">-- Select Taxonomy --</option>';
        
        availableTaxonomies.forEach(function(taxonomy) {
            html += '<option value="' + taxonomy + '">' + taxonomy + '</option>';
        });
        
        html += '</select>';
        html += '<label>JSON Field:</label>';
        html += '<select name="field_mapping[taxonomies][' + index + '][field]">';
        html += '<option value="">-- Select Field --</option>';
        
        availableFields.forEach(function(field) {
            html += '<option value="' + field + '">' + field + '</option>';
        });
        
        html += '</select>';
        html += '<button type="button" class="button jpi-remove-field">Remove</button>';
        html += '</div>';
        
        container.append(html);
    });
    
    // Remove field mapping
    $(document).on('click', '.jpi-remove-field', function() {
        $(this).closest('.jpi-custom-field-row, .jpi-taxonomy-row').remove();
    });
});
</script>

<?php
// Helper method to format preview values
if (!function_exists('format_preview_value')) {
    function format_preview_value($value) {
        if (is_null($value)) {
            return '<em>null</em>';
        }
        
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        
        if (is_array($value)) {
            if (count($value) > 3) {
                return sprintf('[Array with %d items]', count($value));
            }
            return '[' . implode(', ', array_map('format_preview_value', $value)) . ']';
        }
        
        if (is_object($value)) {
            return '[Object]';
        }
        
        $str_value = (string) $value;
        if (strlen($str_value) > 100) {
            return substr($str_value, 0, 100) . '...';
        }
        
        return $str_value;
    }
}
?>