<?php
/**
 * Plugin Name: MAD Custom Card Repeater
 * Description: Adds a repeater field meta box (heading, paragraph, image, text, url) to posts and pages in the Classic Editor only.
 * Version: 2.0
 * Author: Jalal Hussain
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Main plugin logic is now in a single file for clarity.
add_action('add_meta_boxes', function($post_type) {
    if (in_array($post_type, ['post', 'page']) && !use_block_editor_for_post_type($post_type)) {
        add_meta_box(
            'mad_custom_card_repeater',
            'Custom Card Repeater',
            'mad_crf_render_meta_box',
            $post_type,
            'normal',
            'high'
        );
    }
});

function mad_crf_render_meta_box($post) {
    wp_nonce_field('mad_crf_save', 'mad_crf_nonce');
    $data = get_post_meta($post->ID, '_mad_crf_data', true);
    if (!is_array($data)) $data = [];
    ?>
    <div id="mad-crf-repeater-wrapper">
        <div id="mad-crf-list">
            <?php foreach ($data as $i => $item): ?>
                <?php mad_crf_render_item($i, $item); ?>
            <?php endforeach; ?>
        </div>
        <button type="button" class="button button-secondary" id="mad-crf-add" style="margin-top:10px;"><span class="dashicons dashicons-plus-alt2"></span> Add Card</button>
        <template id="mad-crf-template">
            <?php mad_crf_render_item('__i__', []); ?>
        </template>
    </div>
    <style>
        #mad-crf-repeater-wrapper {background:#f8f9fa;border-radius:8px;padding:20px 20px 12px 20px;border:1px solid #e5e5e5;max-width:800px;}
        #mad-crf-list .mad-crf-card {background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px 24px 12px 24px;margin-bottom:18px;position:relative;box-shadow:0 1px 2px rgba(30,40,90,0.04);transition:box-shadow 0.2s;}
        #mad-crf-list .mad-crf-card:hover {box-shadow:0 2px 8px rgba(30,40,90,0.10);}
        #mad-crf-list .mad-crf-fields label {font-weight:600;display:block;margin-bottom:4px;color:#23282d;}
        #mad-crf-list input, #mad-crf-list textarea {width:100%;margin-bottom:14px;border-radius:4px;border:1px solid #ccd0d4;padding:8px 12px;font-size:15px;background:#f6f7f7;transition:border-color 0.2s;}
        #mad-crf-list input:focus, #mad-crf-list textarea:focus {border-color:#2271b1;background:#fff;outline:none;}
        .mad-crf-remove {position:absolute;top:12px;right:12px;background:#fbeaea;color:#b32d2e;border:none;border-radius:50%;width:32px;height:32px;cursor:pointer;font-size:20px;display:flex;align-items:center;justify-content:center;transition:background 0.2s;}
        .mad-crf-remove:hover {background:#f8d7da;}
        .mad-crf-image-preview {max-width:120px;display:block;margin-bottom:8px;border-radius:4px;border:1px solid #ccd0d4;}
        .mad-crf-fields {margin-bottom:8px;}
        .mad-crf-fields label:not(:first-child) {margin-top:8px;}
        .mad-crf-upload-image {margin-bottom:10px;}
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const addBtn = document.getElementById('mad-crf-add');
        const list = document.getElementById('mad-crf-list');
        const template = document.getElementById('mad-crf-template').content;
        let index = list.children.length;
        addBtn.addEventListener('click', function() {
            const clone = document.importNode(template, true);
            let html = clone.firstElementChild.outerHTML.replace(/__i__/g, index);
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            list.appendChild(tempDiv.firstElementChild);
            index++;
        });
        list.addEventListener('click', function(e) {
            if (e.target.classList.contains('mad-crf-remove')) {
                e.target.closest('.mad-crf-card').remove();
            }
            if (e.target.classList.contains('mad-crf-upload-image')) {
                e.preventDefault();
                const input = e.target.previousElementSibling;
                const preview = e.target.nextElementSibling;
                let frame = wp.media({title:'Select Image',button:{text:'Use this image'},multiple:false});
                frame.on('select',function(){
                    let att = frame.state().get('selection').first().toJSON();
                    input.value = att.id;
                    preview.src = att.url;
                    preview.style.display = '';
                });
                frame.open();
            }
        });
    });
    </script>
    <?php
}
function mad_crf_render_item($i, $item) {
    $heading = isset($item['heading']) ? esc_attr($item['heading']) : '';
    $paragraph = isset($item['paragraph']) ? esc_textarea($item['paragraph']) : '';
    $image = isset($item['image']) ? absint($item['image']) : '';
    $text = isset($item['text']) ? esc_attr($item['text']) : '';
    $url = isset($item['url']) ? esc_url($item['url']) : '';
    $img_url = $image ? esc_url(wp_get_attachment_url($image)) : '';
    ?>
    <div class="mad-crf-card">
        <button type="button" class="mad-crf-remove">&times;</button>
        <div class="mad-crf-fields">
            <label>Heading</label>
            <input type="text" name="mad_crf_data[<?php echo $i; ?>][heading]" value="<?php echo $heading; ?>" />
            <label>Paragraph</label>
            <textarea name="mad_crf_data[<?php echo $i; ?>][paragraph]"><?php echo $paragraph; ?></textarea>
            <label>Image</label>
            <input type="hidden" name="mad_crf_data[<?php echo $i; ?>][image]" value="<?php echo $image; ?>" />
            <button type="button" class="button mad-crf-upload-image">Select Image</button>
            <img src="<?php echo $img_url; ?>" class="mad-crf-image-preview" style="<?php echo $img_url ? '' : 'display:none;'; ?>" />
            <label>Text</label>
            <input type="text" name="mad_crf_data[<?php echo $i; ?>][text]" value="<?php echo $text; ?>" />
            <label>URL</label>
            <input type="url" name="mad_crf_data[<?php echo $i; ?>][url]" value="<?php echo $url; ?>" />
        </div>
    </div>
    <?php
}
add_action('save_post', function($post_id) {
    if (!isset($_POST['mad_crf_nonce']) || !wp_verify_nonce($_POST['mad_crf_nonce'], 'mad_crf_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    $data = isset($_POST['mad_crf_data']) ? $_POST['mad_crf_data'] : [];
    $clean = [];
    foreach ($data as $item) {
        if (empty($item['heading']) && empty($item['paragraph']) && empty($item['image']) && empty($item['text']) && empty($item['url'])) continue;
        $clean[] = [
            'heading' => sanitize_text_field($item['heading']),
            'paragraph' => sanitize_textarea_field($item['paragraph']),
            // Store image as ID, but add a filter for API output
            'image' => absint($item['image']),
            'text' => sanitize_text_field($item['text']),
            'url' => esc_url_raw($item['url']),
        ];
    }
    update_post_meta($post_id, '_mad_crf_data', $clean);
});

// Filter for API output: convert image ID to URL
add_filter('get_post_metadata', function($value, $object_id, $meta_key, $single) {
    if ($meta_key === '_mad_crf_data' && $single && is_array($value) && !empty($value)) {
        $new = [];
        foreach ($value[0] as $item) {
            if (isset($item['image']) && $item['image']) {
                $item['image'] = wp_get_attachment_url($item['image']);
            }
            $new[] = $item;
        }
        return [$new];
    }
    return null;
}, 10, 4);