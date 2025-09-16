<?php
// Add a custom meta box for the repeater field.
function crf_add_meta_box($post_type) {
    if (!use_block_editor_for_post_type($post_type)) {
        add_meta_box(
            'custom_repeater_meta_box',
            'Custom Repeater Fields',
            'crf_render_meta_box',
            $post_type,
            'normal',
            'high'
        );
    }
}
add_action('add_meta_boxes', 'crf_add_meta_box');

// Render the meta box content.
function crf_render_meta_box( $post ) {
    wp_nonce_field( 'crf_save_repeater_fields', 'crf_repeater_nonce' );
    $repeater_data = get_post_meta( $post->ID, '_crf_repeater_data', true );
    $repeater_enabled = get_post_meta( $post->ID, '_crf_repeater_enabled', true );
    ?>
<div id="crf-repeater-wrapper" class="crf-repeater-wrapper">
    <h3 class="crf-repeater-title"><span class="dashicons dashicons-editor-table"></span> Custom Repeater Fields</h3>
    <label class="crf-repeater-enable-label">
        <input type="checkbox" id="crf-repeater-enable" name="crf_repeater_enable" value="1"
            <?php checked( $repeater_enabled, '1' ); ?> />
        Enable Custom Repeater
    </label>
    <div id="crf-repeater-container" style="display:<?php echo $repeater_enabled === '1' ? 'block' : 'none'; ?>;">
        <template id="crf-repeater-template">
            <div class="crf-repeater-item">
                <div class="crf-repeater-fields">
                    <label>Heading</label>
                    <input type="text" name="crf_repeater[new][heading]" placeholder="Enter heading" />
                    <label>Paragraph</label>
                    <textarea name="crf_repeater[new][paragraph]" placeholder="Enter paragraph"></textarea>
                    <label>Image</label>
                    <div class="crf-image-upload">
                        <input type="hidden" name="crf_repeater[new][image]" class="crf-image-id" />
                        <img src="" class="crf-image-preview" style="max-width:100px;display:none;" />
                        <button type="button" class="button crf-upload-image">Select Image</button>
                        <button type="button" class="button crf-remove-image" style="display:none;">Remove</button>
                    </div>
                    <label>Text</label>
                    <input type="text" name="crf_repeater[new][text]" placeholder="Enter text" />
                    <label>URL</label>
                    <input type="url" name="crf_repeater[new][url]" placeholder="Enter URL" />
                </div>
                <button type="button" class="crf-remove-item" title="Remove Item"><span
                        class="dashicons dashicons-no-alt"></span></button>
            </div>
        </template>
        <?php if ( ! empty( $repeater_data ) ) : ?>
        <?php foreach ( $repeater_data as $index => $item ) : ?>
        <div class="crf-repeater-item">
            <div class="crf-repeater-fields">
                <label>Heading</label>
                <input type="text" name="crf_repeater[<?php echo esc_attr($index); ?>][heading]"
                    value="<?php echo esc_attr( isset($item['heading']) ? $item['heading'] : '' ); ?>" placeholder="Enter heading" />
                <label>Paragraph</label>
                <textarea name="crf_repeater[<?php echo esc_attr($index); ?>][paragraph]" placeholder="Enter paragraph"><?php echo esc_textarea( isset($item['paragraph']) ? $item['paragraph'] : '' ); ?></textarea>
                <label>Image</label>
                <div class="crf-image-upload">
                    <input type="hidden" name="crf_repeater[<?php echo esc_attr($index); ?>][image]" class="crf-image-id" value="<?php echo esc_attr( isset($item['image']) ? $item['image'] : '' ); ?>" />
                    <img src="<?php echo isset($item['image']) && $item['image'] ? esc_url(wp_get_attachment_url($item['image'])) : ''; ?>" class="crf-image-preview" style="max-width:100px;<?php echo (isset($item['image']) && $item['image']) ? '' : 'display:none;'; ?>" />
                    <button type="button" class="button crf-upload-image"><?php echo (isset($item['image']) && $item['image']) ? 'Change Image' : 'Select Image'; ?></button>
                    <button type="button" class="button crf-remove-image" style="<?php echo (isset($item['image']) && $item['image']) ? '' : 'display:none;'; ?>">Remove</button>
                </div>
                <label>Text</label>
                <input type="text" name="crf_repeater[<?php echo esc_attr($index); ?>][text]"
                    value="<?php echo esc_attr( $item['text'] ); ?>" placeholder="Enter text" />
                <label>URL</label>
                <input type="url" name="crf_repeater[<?php echo esc_attr($index); ?>][url]"
                    value="<?php echo esc_url( $item['url'] ); ?>" placeholder="Enter URL" />
            </div>
            <button type="button" class="crf-remove-item" title="Remove Item"><span
                    class="dashicons dashicons-no-alt"></span></button>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        <button type="button" id="crf-add-item" class="button button-primary"><span
                class="dashicons dashicons-plus-alt2"></span> Add Item</button>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const enableCheckbox = document.getElementById('crf-repeater-enable');
    const fields = document.getElementById('crf-repeater-container');
    enableCheckbox.addEventListener('change', function() {
        fields.style.display = this.checked ? 'block' : 'none';
    });
    const template = document.getElementById('crf-repeater-template').content;
    document.getElementById('crf-add-item').addEventListener('click', () => {
        let maxIndex = -1;
        fields.querySelectorAll('.crf-repeater-item input[name^="crf_repeater["]').forEach(input => {
            const match = input.name.match(/^crf_repeater\[(\d+)\]/);
            if (match && parseInt(match[1]) > maxIndex) {
                maxIndex = parseInt(match[1]);
            }
        });
        const nextIndex = maxIndex + 1;
        const clone = document.importNode(template, true);
        // Update all field names for the new index
        clone.querySelectorAll('[name^="crf_repeater[new]"]').forEach(function(input) {
            input.name = input.name.replace('new', nextIndex);
        });
        fields.insertBefore(clone, fields.querySelector('#crf-add-item'));
    });
    fields.addEventListener('click', function(e) {
        if (e.target.closest && e.target.closest('button.crf-remove-item')) {
            e.target.closest('.crf-repeater-item').remove();
        }
    });
    // Media uploader logic
    let mediaUploader;
    fields.addEventListener('click', function(e) {
        if (e.target.classList.contains('crf-upload-image')) {
            e.preventDefault();
            const container = e.target.closest('.crf-image-upload');
            if (mediaUploader) {
                mediaUploader.open();
                mediaUploader.container = container;
                return;
            }
            mediaUploader = wp.media({
                title: 'Select Image',
                button: { text: 'Use this image' },
                multiple: false
            });
            mediaUploader.on('select', function() {
                const attachment = mediaUploader.state().get('selection').first().toJSON();
                const img = mediaUploader.container.querySelector('.crf-image-preview');
                const input = mediaUploader.container.querySelector('.crf-image-id');
                img.src = attachment.url;
                img.style.display = '';
                input.value = attachment.id;
                mediaUploader.container.querySelector('.crf-remove-image').style.display = '';
                mediaUploader.container.querySelector('.crf-upload-image').textContent = 'Change Image';
            });
            mediaUploader.container = container;
            mediaUploader.open();
        }
        if (e.target.classList.contains('crf-remove-image')) {
            e.preventDefault();
            const container = e.target.closest('.crf-image-upload');
            container.querySelector('.crf-image-preview').src = '';
            container.querySelector('.crf-image-preview').style.display = 'none';
            container.querySelector('.crf-image-id').value = '';
            container.querySelector('.crf-remove-image').style.display = 'none';
            container.querySelector('.crf-upload-image').textContent = 'Select Image';
        }
    });
});
</script>
<style>
.crf-repeater-wrapper {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 18px 18px 12px 18px;
    border: 1px solid #e5e5e5;
    margin-bottom: 0;
}

.crf-repeater-title {
    font-size: 1.2em;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.crf-repeater-enable-label {
    display: flex;
    align-items: center;
    font-weight: 500;
    gap: 8px;
    margin-bottom: 16px;
}

.crf-repeater-item {
    background: #fff;
    border-radius: 6px;
    box-shadow: 0 1px 2px rgba(30, 40, 90, 0.04);
    border: 1px solid #e0e0e0;
    margin-bottom: 18px;
    padding: 16px 16px 10px 16px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    position: relative;
}

.crf-repeater-fields {
    flex: 1;
}

.crf-repeater-fields label {
    font-weight: 500;
    margin-bottom: 4px;
    display: block;
    color: #23282d;
}

.crf-repeater-item input {
    width: 100%;
    margin-bottom: 10px;
    border-radius: 4px;
    border: 1px solid #ccd0d4;
    padding: 7px 10px;
    font-size: 14px;
    background: #f6f7f7;
    transition: border-color 0.2s;
}

.crf-repeater-item input:focus {
    border-color: #2271b1;
    background: #fff;
    outline: none;
}

.crf-repeater-item textarea {
    width: 100%;
    margin-bottom: 10px;
    border-radius: 4px;
    border: 1px solid #ccd0d4;
    padding: 7px 10px;
    font-size: 14px;
    background: #f6f7f7;
    transition: border-color 0.2s;
    min-height: 50px;
}

.crf-repeater-item textarea:focus {
    border-color: #2271b1;
    background: #fff;
    outline: none;
}

.crf-image-upload {
    margin-bottom: 10px;
}

.crf-image-upload img {
    max-width: 100%;
    height: auto;
    border-radius: 4px;
    border: 1px solid #ccd0d4;
    margin-bottom: 5px;
}

.crf-image-upload .button {
    margin-top: 5px;
}

.crf-remove-item {
    background: none;
    border: none;
    color: #b32d2e;
    font-size: 18px;
    cursor: pointer;
    margin-left: 8px;
    margin-top: 2px;
    padding: 4px 6px;
    border-radius: 50%;
    transition: background 0.2s;
}

.crf-remove-item:hover {
    background: #fbeaea;
}

#crf-add-item {
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 500;
    font-size: 15px;
}
</style>
<?php
}

// Save repeater field data.
function crf_save_repeater_fields( $post_id ) {
    $nonce = isset( $_POST['crf_repeater_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['crf_repeater_nonce'] ) ) : '';
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'crf_save_repeater_fields' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    $repeater_data = [];
    $crf_repeater = isset( $_POST['crf_repeater'] ) ? wp_unslash( $_POST['crf_repeater'] ) : [];
    foreach ( $crf_repeater as $item ) {
        if ( empty( $item['text'] ) && empty( $item['url'] ) && empty( $item['heading'] ) && empty( $item['paragraph'] ) && empty( $item['image'] ) ) {
            continue;
        }
        $repeater_data[] = [
            'heading'   => isset($item['heading']) ? sanitize_text_field($item['heading']) : '',
            'paragraph' => isset($item['paragraph']) ? sanitize_textarea_field($item['paragraph']) : '',
            'image'     => isset($item['image']) ? absint($item['image']) : '',
            'text'      => isset($item['text']) ? sanitize_text_field($item['text']) : '',
            'url'       => isset($item['url']) ? esc_url_raw($item['url']) : '',
        ];
    }
    update_post_meta( $post_id, '_crf_repeater_data', $repeater_data );
    update_post_meta( $post_id, '_crf_repeater_enabled', isset( $_POST['crf_repeater_enable'] ) ? '1' : '' );
}
add_action( 'save_post', 'crf_save_repeater_fields' );