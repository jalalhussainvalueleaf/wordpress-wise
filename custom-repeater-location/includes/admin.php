<?php
// Add a custom meta box for the repeater field.
function crfl_add_meta_box($post_type) {
    if (!use_block_editor_for_post_type($post_type)) {
        add_meta_box(
            'custom_repeater_meta_box',
            'Custom Repeater Fields',
            'crfl_render_meta_box',
            $post_type,
            'normal',
            'high'
        );
    }
}
add_action('add_meta_boxes', 'crfl_add_meta_box');

// Render the meta box content.
function crfl_render_meta_box( $post ) {
    wp_nonce_field( 'crfl_save_repeater_fields', 'crfl_repeater_nonce' );
    $repeater_data = get_post_meta( $post->ID, '_crfl_repeater_data', true );
    $repeater_enabled = get_post_meta( $post->ID, '_crfl_repeater_enabled', true );
    ?>
<div id="crfl-repeater-wrapper" class="crfl-repeater-wrapper">
    <h3 class="crfl-repeater-title"><span class="dashicons dashicons-editor-table"></span> Custom Repeater Fields-1</h3>
    <label class="crfl-repeater-enable-label">
        <input type="checkbox" id="crfl-repeater-enable" name="crfl_repeater_enable" value="1"
            <?php checked( $repeater_enabled, '1' ); ?> />
        Enable Custom Repeater
    </label>
    <div id="crfl-repeater-container" style="display:<?php echo $repeater_enabled === '1' ? 'block' : 'none'; ?>;">
        <template id="crfl-repeater-template">
            <div class="crfl-repeater-item">
                <div class="crfl-repeater-fields">
                    <label>Heading</label>
                    <input type="text" name="crfl_repeater[new][heading]" placeholder="Enter heading" />
                    <label>Paragraph</label>
                    <textarea name="crfl_repeater[new][paragraph]" placeholder="Enter paragraph"></textarea>
                    <label>Image</label>
                    <div class="crfl-image-upload">
                        <input type="hidden" name="crfl_repeater[new][image]" class="crfl-image-id" />
                        <img src="" class="crfl-image-preview" style="max-width:100px;display:none;" />
                        <button type="button" class="button crfl-upload-image">Select Image</button>
                        <button type="button" class="button crfl-remove-image" style="display:none;">Remove</button>
                    </div>
                    <label>Text</label>
                    <input type="text" name="crfl_repeater[new][text]" placeholder="Enter text" />
                    <label>URL</label>
                    <input type="url" name="crfl_repeater[new][url]" placeholder="Enter URL" />
                </div>
                <button type="button" class="crfl-remove-item" title="Remove Item"><span
                        class="dashicons dashicons-no-alt"></span></button>
            </div>
        </template>
        <?php if ( ! empty( $repeater_data ) ) : ?>
        <?php foreach ( $repeater_data as $index => $item ) : ?>
        <div class="crfl-repeater-item">
            <div class="crfl-repeater-fields">
                <label>Heading</label>
                <input type="text" name="crfl_repeater[<?php echo esc_attr($index); ?>][heading]"
                    value="<?php echo esc_attr( isset($item['heading']) ? $item['heading'] : '' ); ?>" placeholder="Enter heading" />
                <label>Paragraph</label>
                <textarea name="crfl_repeater[<?php echo esc_attr($index); ?>][paragraph]" placeholder="Enter paragraph"><?php echo esc_textarea( isset($item['paragraph']) ? $item['paragraph'] : '' ); ?></textarea>
                <label>Image</label>
                <div class="crfl-image-upload">
                    <input type="hidden" name="crfl_repeater[<?php echo esc_attr($index); ?>][image]" class="crfl-image-id" value="<?php echo esc_attr( isset($item['image']) ? $item['image'] : '' ); ?>" />
                    <img src="<?php echo isset($item['image']) && $item['image'] ? esc_url(wp_get_attachment_url($item['image'])) : ''; ?>" class="crfl-image-preview" style="max-width:100px;<?php echo (isset($item['image']) && $item['image']) ? '' : 'display:none;'; ?>" />
                    <button type="button" class="button crfl-upload-image"><?php echo (isset($item['image']) && $item['image']) ? 'Change Image' : 'Select Image'; ?></button>
                    <button type="button" class="button crfl-remove-image" style="<?php echo (isset($item['image']) && $item['image']) ? '' : 'display:none;'; ?>">Remove</button>
                </div>
                <label>Text</label>
                <input type="text" name="crfl_repeater[<?php echo esc_attr($index); ?>][text]"
                    value="<?php echo esc_attr( $item['text'] ); ?>" placeholder="Enter text" />
                <label>URL</label>
                <input type="url" name="crfl_repeater[<?php echo esc_attr($index); ?>][url]"
                    value="<?php echo esc_url( $item['url'] ); ?>" placeholder="Enter URL" />
            </div>
            <button type="button" class="crfl-remove-item" title="Remove Item"><span
                    class="dashicons dashicons-no-alt"></span></button>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        <button type="button" id="crfl-add-item" class="button button-primary"><span
                class="dashicons dashicons-plus-alt2"></span> Add Item</button>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const enableCheckbox = document.getElementById('crfl-repeater-enable');
    const fields = document.getElementById('crfl-repeater-container');
    enableCheckbox.addEventListener('change', function() {
        fields.style.display = this.checked ? 'block' : 'none';
    });
    const template = document.getElementById('crfl-repeater-template').content;
    document.getElementById('crfl-add-item').addEventListener('click', () => {
        let maxIndex = -1;
        fields.querySelectorAll('.crfl-repeater-item input[name^="crfl_repeater["]').forEach(input => {
            const match = input.name.match(/^crfl_repeater\[(\d+)\]/);
            if (match && parseInt(match[1]) > maxIndex) {
                maxIndex = parseInt(match[1]);
            }
        });
        const nextIndex = maxIndex + 1;
        const clone = document.importNode(template, true);
        // Update all field names for the new index
        clone.querySelectorAll('[name^="crfl_repeater[new]"]').forEach(function(input) {
            input.name = input.name.replace('new', nextIndex);
        });
        fields.insertBefore(clone, fields.querySelector('#crfl-add-item'));
    });
    fields.addEventListener('click', function(e) {
        if (e.target.closest && e.target.closest('button.crfl-remove-item')) {
            e.target.closest('.crfl-repeater-item').remove();
        }
    });
    // Media uploader logic
    let mediaUploader;
    fields.addEventListener('click', function(e) {
        if (e.target.classList.contains('crfl-upload-image')) {
            e.preventDefault();
            const container = e.target.closest('.crfl-image-upload');
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
                const img = mediaUploader.container.querySelector('.crfl-image-preview');
                const input = mediaUploader.container.querySelector('.crfl-image-id');
                img.src = attachment.url;
                img.style.display = '';
                input.value = attachment.id;
                mediaUploader.container.querySelector('.crfl-remove-image').style.display = '';
                mediaUploader.container.querySelector('.crfl-upload-image').textContent = 'Change Image';
            });
            mediaUploader.container = container;
            mediaUploader.open();
        }
        if (e.target.classList.contains('crfl-remove-image')) {
            e.preventDefault();
            const container = e.target.closest('.crfl-image-upload');
            container.querySelector('.crfl-image-preview').src = '';
            container.querySelector('.crfl-image-preview').style.display = 'none';
            container.querySelector('.crfl-image-id').value = '';
            container.querySelector('.crfl-remove-image').style.display = 'none';
            container.querySelector('.crfl-upload-image').textContent = 'Select Image';
        }
    });
});
</script>
<style>
.crfl-repeater-wrapper {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 18px 18px 12px 18px;
    border: 1px solid #e5e5e5;
    margin-bottom: 0;
}

.crfl-repeater-title {
    font-size: 1.2em;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.crfl-repeater-enable-label {
    display: flex;
    align-items: center;
    font-weight: 500;
    gap: 8px;
    margin-bottom: 16px;
}

.crfl-repeater-item {
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

.crfl-repeater-fields {
    flex: 1;
}

.crfl-repeater-fields label {
    font-weight: 500;
    margin-bottom: 4px;
    display: block;
    color: #23282d;
}

.crfl-repeater-item input {
    width: 100%;
    margin-bottom: 10px;
    border-radius: 4px;
    border: 1px solid #ccd0d4;
    padding: 7px 10px;
    font-size: 14px;
    background: #f6f7f7;
    transition: border-color 0.2s;
}

.crfl-repeater-item input:focus {
    border-color: #2271b1;
    background: #fff;
    outline: none;
}

.crfl-repeater-item textarea {
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

.crfl-repeater-item textarea:focus {
    border-color: #2271b1;
    background: #fff;
    outline: none;
}

.crfl-image-upload {
    margin-bottom: 10px;
}

.crfl-image-upload img {
    max-width: 100%;
    height: auto;
    border-radius: 4px;
    border: 1px solid #ccd0d4;
    margin-bottom: 5px;
}

.crfl-image-upload .button {
    margin-top: 5px;
}

.crfl-remove-item {
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

.crfl-remove-item:hover {
    background: #fbeaea;
}

#crfl-add-item {
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
function crfl_save_repeater_fields( $post_id ) {
    $nonce = isset( $_POST['crfl_repeater_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['crfl_repeater_nonce'] ) ) : '';
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'crfl_save_repeater_fields' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    $repeater_data = [];
    $crfl_repeater = isset( $_POST['crfl_repeater'] ) ? wp_unslash( $_POST['crfl_repeater'] ) : [];
    foreach ( $crfl_repeater as $item ) {
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
    update_post_meta( $post_id, '_crfl_repeater_data', $repeater_data );
    update_post_meta( $post_id, '_crfl_repeater_enabled', isset( $_POST['crfl_repeater_enable'] ) ? '1' : '' );
}
add_action( 'save_post', 'crfl_save_repeater_fields' );