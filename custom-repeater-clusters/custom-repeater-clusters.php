<?php
/**
 * Plugin Name: MAD Custom Clusters
 * Description: Adds a repeater field meta box (heading, paragraph) to posts and pages in the Classic Editor only.
 * Version: 2.0
 * Author: Jalal Hussain
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action('add_meta_boxes', function($post_type) {
    if (in_array($post_type, ['post', 'page','loan-clusters']) && !use_block_editor_for_post_type($post_type)) {
        add_meta_box(
            'mad_custom_clusters',
            'Custom Clusters',
            'mad_cluster_render_meta_box',
            $post_type,
            'normal',
            'high'
        );
    }
});

function mad_cluster_render_meta_box($post) {
    wp_nonce_field('mad_clusters_save', 'mad_clusters_nonce');
    $data = get_post_meta($post->ID, '_mad_clusters_data', true);
    if (!is_array($data)) $data = [];
    ?>
    <div id="mad-clusters-repeater-wrapper">
        <div id="mad-clusters-list">
            <?php foreach ($data as $i => $item): ?>
                <?php mad_clusters_render_item($i, $item); ?>
            <?php endforeach; ?>
        </div>
        <button type="button" class="button button-secondary" id="mad-clusters-add" style="margin-top:10px;"><span class="dashicons dashicons-plus-alt2"></span> Add Card</button>
        <template id="mad-clusters-template">
            <?php mad_clusters_render_item('__i__', []); ?>
        </template>
    </div>
    <style>
        #mad-clusters-repeater-wrapper {background:#f8f9fa;border-radius:8px;padding:20px 20px 12px 20px;border:1px solid #e5e5e5;max-width:800px;}
        #mad-clusters-list .mad-clusters-card {background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px 24px 12px 24px;margin-bottom:18px;position:relative;box-shadow:0 1px 2px rgba(30,40,90,0.04);transition:box-shadow 0.2s;}
        #mad-clusters-list .mad-clusters-card:hover {box-shadow:0 2px 8px rgba(30,40,90,0.10);}
        #mad-clusters-list .mad-clusters-fields label {font-weight:600;display:block;margin-bottom:4px;color:#23282d;}
        #mad-clusters-list input, #mad-clusters-list textarea {width:100%;margin-bottom:14px;border-radius:4px;border:1px solid #ccd0d4;padding:8px 12px;font-size:15px;background:#f6f7f7;transition:border-color 0.2s;}
        #mad-clusters-list input:focus, #mad-clusters-list textarea:focus {border-color:#2271b1;background:#fff;outline:none;}
        .mad-clusters-remove {position:absolute;top:12px;right:12px;background:#fbeaea;color:#b32d2e;border:none;border-radius:50%;width:32px;height:32px;cursor:pointer;font-size:20px;display:flex;align-items:center;justify-content:center;transition:background 0.2s;}
        .mad-clusters-remove:hover {background:#f8d7da;}
        .mad-clusters-fields {margin-bottom:8px;}
        .mad-clusters-fields label:not(:first-child) {margin-top:8px;}
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const addBtn = document.getElementById('mad-clusters-add');
        const list = document.getElementById('mad-clusters-list');
        const template = document.getElementById('mad-clusters-template').content;
        let index = list.children.length;
        addBtn.addEventListener('click', function() {
            const clone = document.importNode(template, true);
            let html = clone.firstElementChild.outerHTML.replace(/__i__/g, index);
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            const newCard = tempDiv.firstElementChild;
            // Update textarea to have unique id for TinyMCE
            const textarea = newCard.querySelector('textarea.mad-wysiwyg');
            if (textarea) {
                textarea.id = 'mad_clusters_data_' + index + '_paragraph';
            }
            list.appendChild(newCard);
            // Initialize TinyMCE for the new textarea
            setTimeout(function() {
                if (textarea && typeof tinymce !== 'undefined') {
                    tinymce.init({
                        selector: '#' + textarea.id,
                        menubar: true,
                        toolbar: 'formatselect | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | blockquote | link unlink image media | forecolor backcolor | removeformat | code',
                        toolbar2: 'undo redo | styleselect | subscript superscript | charmap | ltr rtl | wp_help',
                        plugins: 'lists link image media code fullscreen wordpress wpautoresize paste charmap textcolor colorpicker',
                        branding: false,
                        height: 250,
                    });
                }
            }, 100);
            index++;
        });
        list.addEventListener('click', function(e) {
            if (e.target.classList.contains('mad-clusters-remove')) {
                e.target.closest('.mad-clusters-card').remove();
            }
        });
    });
    </script>
    <?php
}
function mad_clusters_render_item($i, $item) {
    $heading = isset($item['heading']) ? esc_attr($item['heading']) : '';
    $paragraph = isset($item['paragraph']) ? $item['paragraph'] : '';
    ?>
    <div class="mad-clusters-card">
        <button type="button" class="mad-clusters-remove">&times;</button>
        <div class="mad-clusters-fields">
            <label>Heading</label>
            <input type="text" name="mad_clusters_data[<?php echo $i; ?>][heading]" value="<?php echo $heading; ?>" />
            <label>Paragraph</label>
            <?php
            $editor_id = 'mad_clusters_data_' . $i . '_paragraph';
            $editor_settings = array(
                'textarea_name' => 'mad_clusters_data[' . $i . '][paragraph]',
                'textarea_rows' => 8,
                'editor_class' => 'mad-wysiwyg',
                'media_buttons' => true,
                'teeny' => false,
                'tinymce' => array(
                    'toolbar1' => 'formatselect | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | blockquote | link unlink image media | forecolor backcolor | removeformat | code',
                    'toolbar2' => 'undo redo | styleselect | subscript superscript | charmap | ltr rtl | wp_help',
                    'menubar' => true,
                    'plugins' => 'lists,link,image,media,code,fullscreen,wordpress,wpautoresize,paste,charmap,textcolor,colorpicker',
                ),
            );
            if ($i === '__i__') {
                // For template, just output a textarea for JS to convert
                echo '<textarea class="mad-wysiwyg" name="mad_clusters_data[' . $i . '][paragraph]"></textarea>';
            } else {
                wp_editor($paragraph, $editor_id, $editor_settings);
            }
            ?>
        </div>
    </div>
    <?php
}
add_action('save_post', function($post_id) {
    if (!isset($_POST['mad_clusters_nonce']) || !wp_verify_nonce($_POST['mad_clusters_nonce'], 'mad_clusters_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    $data = isset($_POST['mad_clusters_data']) ? $_POST['mad_clusters_data'] : [];
    $clean = [];
    foreach ($data as $item) {
        if (empty($item['heading']) && empty($item['paragraph'])) continue;
        $clean[] = [
            'heading' => sanitize_text_field($item['heading']),
            'paragraph' => wp_kses_post($item['paragraph']),
        ];
    }
    update_post_meta($post_id, '_mad_clusters_data', $clean);
});

// Filter for API output: only heading and paragraph for _mad_clusters_data
add_filter('get_post_metadata', function($value, $object_id, $meta_key, $single) {
    if ($meta_key === '_mad_clusters_data' && $single && is_array($value) && !empty($value)) {
        $new = [];
        foreach ($value[0] as $item) {
            $new[] = [
                'heading' => isset($item['heading']) ? $item['heading'] : '',
                'paragraph' => isset($item['paragraph']) ? $item['paragraph'] : '',
            ];
        }
        return [$new];
    }
    return null;
}, 10, 4);