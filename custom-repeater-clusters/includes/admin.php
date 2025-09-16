<?php
// Add a custom meta box for the repeater field.
function clusters_add_meta_box($post_type) {
    if (!use_block_editor_for_post_type($post_type)) {
        add_meta_box(
            'custom_repeater_meta_box',
            'Custom Repeater Fields',
            'clusters_render_meta_box',
            $post_type,
            'normal',
            'high'
        );
    }
}
add_action('add_meta_boxes', 'clusters_add_meta_box');

// Render the meta box content.
function clusters_render_meta_box( $post ) {
    wp_nonce_field( 'clusters_save_repeater_fields', 'clusters_repeater_nonce' );
    $repeater_data = get_post_meta( $post->ID, '_clusters_repeater_data', true );
    ?>
<div id="clusters-repeater-wrapper" class="clusters-repeater-wrapper">
    <h3 class="clusters-repeater-title"><span class="dashicons dashicons-editor-table"></span> Custom Repeater Fields</h3>
    <div id="clusters-repeater-container">
        <template id="clusters-repeater-template">
            <div class="clusters-repeater-item">
                <div class="clusters-repeater-fields">
                    <label>Heading</label>
                    <input type="text" name="clusters_repeater[new][heading]" placeholder="Enter heading" />
                    <label>Paragraph</label>
                    <textarea class="clusters-wysiwyg" name="clusters_repeater[new][paragraph]" placeholder="Enter paragraph"></textarea>
                </div>
                <button type="button" class="clusters-remove-item" title="Remove Item"><span
                        class="dashicons dashicons-no-alt"></span></button>
            </div>
        </template>
        <?php if ( ! empty( $repeater_data ) ) : ?>
        <?php foreach ( $repeater_data as $index => $item ) : ?>
        <div class="clusters-repeater-item">
            <div class="clusters-repeater-fields">
                <label>Heading</label>
                <input type="text" name="clusters_repeater[<?php echo esc_attr($index); ?>][heading]"
                    value="<?php echo esc_attr( isset($item['heading']) ? $item['heading'] : '' ); ?>" placeholder="Enter heading" />
                <label>Paragraph</label>
                <?php
                $editor_id = 'clusters_repeater_' . esc_attr($index) . '_paragraph';
                $editor_settings = array(
                    'textarea_name' => 'clusters_repeater[' . esc_attr($index) . '][paragraph]',
                    'textarea_rows' => 5,
                    'editor_class' => 'clusters-wysiwyg',
                    'media_buttons' => false,
                    'teeny' => true,
                );
                wp_editor( isset($item['paragraph']) ? $item['paragraph'] : '', $editor_id, $editor_settings );
                ?>
            </div>
            <button type="button" class="clusters-remove-item" title="Remove Item"><span
                    class="dashicons dashicons-no-alt"></span></button>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        <button type="button" id="clusters-add-item" class="button button-primary"><span
                class="dashicons dashicons-plus-alt2"></span> Add Item</button>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const fields = document.getElementById('clusters-repeater-container');
    const template = document.getElementById('clusters-repeater-template').content;
    document.getElementById('clusters-add-item').addEventListener('click', () => {
        let maxIndex = -1;
        fields.querySelectorAll('.clusters-repeater-item input[name^="clusters_repeater["]').forEach(input => {
            const match = input.name.match(/^clusters_repeater\[(\d+)\]/);
            if (match && parseInt(match[1]) > maxIndex) {
                maxIndex = parseInt(match[1]);
            }
        });
        const nextIndex = maxIndex + 1;
        const clone = document.importNode(template, true);
        // Update all field names for the new index
        clone.querySelectorAll('[name^="clusters_repeater[new]"]').forEach(function(input) {
            input.name = input.name.replace('new', nextIndex);
        });
        // Also update the textarea name and id for WYSIWYG
        clone.querySelectorAll('textarea.clusters-wysiwyg').forEach(function(textarea) {
            textarea.name = textarea.name.replace('new', nextIndex);
            textarea.id = 'clusters_repeater_' + nextIndex + '_paragraph';
        });
        fields.insertBefore(clone, fields.querySelector('#clusters-add-item'));
        // Initialize TinyMCE for the new textarea
        setTimeout(function() {
            const newId = 'clusters_repeater_' + nextIndex + '_paragraph';
            if (typeof tinymce !== 'undefined') {
                tinymce.init({
                    selector: '#' + newId,
                    menubar: false,
                    toolbar: 'bold italic underline | bullist numlist | link | undo redo',
                    branding: false,
                    height: 120,
                });
            }
        }, 100);
    });
    fields.addEventListener('click', function(e) {
        if (e.target.closest && e.target.closest('button.clusters-remove-item')) {
            e.target.closest('.clusters-repeater-item').remove();
        }
    });
});
</script>
<style>
.clusters-repeater-wrapper {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 18px 18px 12px 18px;
    border: 1px solid #e5e5e5;
    margin-bottom: 0;
}

.clusters-repeater-title {
    font-size: 1.2em;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.clusters-repeater-item {
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

.clusters-repeater-fields {
    flex: 1;
}

.clusters-repeater-fields label {
    font-weight: 500;
    margin-bottom: 4px;
    display: block;
    color: #23282d;
}

.clusters-repeater-item input {
    width: 100%;
    margin-bottom: 10px;
    border-radius: 4px;
    border: 1px solid #ccd0d4;
    padding: 7px 10px;
    font-size: 14px;
    background: #f6f7f7;
    transition: border-color 0.2s;
}

.clusters-repeater-item input:focus {
    border-color: #2271b1;
    background: #fff;
    outline: none;
}

.clusters-repeater-item textarea {
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

.clusters-repeater-item textarea:focus {
    border-color: #2271b1;
    background: #fff;
    outline: none;
}

.clusters-remove-item {
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

.clusters-remove-item:hover {
    background: #fbeaea;
}

#clusters-add-item {
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
function clusters_save_repeater_fields( $post_id ) {
    $nonce = isset( $_POST['clusters_repeater_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['clusters_repeater_nonce'] ) ) : '';
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'clusters_save_repeater_fields' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    $repeater_data = [];
    $clusters_repeater = isset( $_POST['clusters_repeater'] ) ? wp_unslash( $_POST['clusters_repeater'] ) : [];
    foreach ( $clusters_repeater as $item ) {
        if ( empty( $item['heading'] ) && empty( $item['paragraph'] ) ) {
            continue;
        }
        $repeater_data[] = [
            'heading'   => isset($item['heading']) ? sanitize_text_field($item['heading']) : '',
            'paragraph' => isset($item['paragraph']) ? sanitize_textarea_field($item['paragraph']) : '',
        ];
    }
    update_post_meta( $post_id, '_clusters_repeater_data', $repeater_data );
}
add_action( 'save_post', 'clusters_save_repeater_fields' );