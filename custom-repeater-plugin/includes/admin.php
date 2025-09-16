<?php
// Add a custom meta box for the repeater field.
function crp_add_meta_box() {
    add_meta_box(
        'custom_repeater_meta_box',
        'Custom Repeater Fields',
        'crp_render_meta_box',
        ['post', 'page'], // Enable for both posts and pages
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'crp_add_meta_box' );

// Render the meta box content.
function crp_render_meta_box( $post ) {
    wp_nonce_field( 'crp_save_repeater_fields', 'crp_repeater_nonce' );
    $repeater_data = get_post_meta( $post->ID, '_crp_repeater_data', true );
    $repeater_enabled = get_post_meta( $post->ID, '_crp_repeater_enabled', true );
    ?>
<div id="crp-repeater-wrapper" class="crp-repeater-wrapper">
    <h3 class="crp-repeater-title"><span class="dashicons dashicons-editor-table"></span> Custom Repeater Fields</h3>
    <label class="crp-repeater-enable-label">
        <input type="checkbox" id="crp-repeater-enable" name="crp_repeater_enable" value="1"
            <?php checked( $repeater_enabled, '1' ); ?> />
        Enable Custom Repeater
    </label>
    <div id="crp-repeater-container" style="display:<?php echo $repeater_enabled === '1' ? 'block' : 'none'; ?>;">
        <template id="crp-repeater-template">
            <div class="crp-repeater-item">
                <div class="crp-repeater-fields">
                    <label>Text</label>
                    <input type="text" name="crp_repeater[new][text]" placeholder="Enter text" />
                    <label>URL</label>
                    <input type="url" name="crp_repeater[new][url]" placeholder="Enter URL" />
                </div>
                <button type="button" class="crp-remove-item" title="Remove Item"><span
                        class="dashicons dashicons-no-alt"></span></button>
            </div>
        </template>
        <?php if ( ! empty( $repeater_data ) ) : ?>
        <?php foreach ( $repeater_data as $index => $item ) : ?>
        <div class="crp-repeater-item">
            <div class="crp-repeater-fields">
                <label>Text</label>
                <input type="text" name="crp_repeater[<?php echo esc_attr($index); ?>][text]"
                    value="<?php echo esc_attr( $item['text'] ); ?>" placeholder="Enter text" />
                <label>URL</label>
                <input type="url" name="crp_repeater[<?php echo esc_attr($index); ?>][url]"
                    value="<?php echo esc_url( $item['url'] ); ?>" placeholder="Enter URL" />
            </div>
            <button type="button" class="crp-remove-item" title="Remove Item"><span
                    class="dashicons dashicons-no-alt"></span></button>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        <button type="button" id="crp-add-item" class="button button-primary"><span
                class="dashicons dashicons-plus-alt2"></span> Add Item</button>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const enableCheckbox = document.getElementById('crp-repeater-enable');
    const fields = document.getElementById('crp-repeater-container');
    enableCheckbox.addEventListener('change', function() {
        fields.style.display = this.checked ? 'block' : 'none';
    });
    const template = document.getElementById('crp-repeater-template').content;
    document.getElementById('crp-add-item').addEventListener('click', () => {
        let maxIndex = -1;
        fields.querySelectorAll('.crp-repeater-item input[name^="crp_repeater["]').forEach(input => {
            const match = input.name.match(/^crp_repeater\[(\d+)\]/);
            if (match && parseInt(match[1]) > maxIndex) {
                maxIndex = parseInt(match[1]);
            }
        });
        const nextIndex = maxIndex + 1;
        const clone = document.importNode(template, true);
        const t = clone.querySelector('input[name="crp_repeater[new][text]"]');
        const u = clone.querySelector('input[name="crp_repeater[new][url]"]');
        if (t) t.name = `crp_repeater[${nextIndex}][text]`;
        if (u) u.name = `crp_repeater[${nextIndex}][url]`;
        fields.insertBefore(clone, fields.querySelector('#crp-add-item'));
    });
    fields.addEventListener('click', function(e) {
        if (e.target.closest && e.target.closest('button.crp-remove-item')) {
            e.target.closest('.crp-repeater-item').remove();
        }
    });
});
</script>
<style>
.crp-repeater-wrapper {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 18px 18px 12px 18px;
    border: 1px solid #e5e5e5;
    margin-bottom: 0;
}

.crp-repeater-title {
    font-size: 1.2em;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.crp-repeater-enable-label {
    display: flex;
    align-items: center;
    font-weight: 500;
    gap: 8px;
    margin-bottom: 16px;
}

.crp-repeater-item {
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

.crp-repeater-fields {
    flex: 1;
}

.crp-repeater-fields label {
    font-weight: 500;
    margin-bottom: 4px;
    display: block;
    color: #23282d;
}

.crp-repeater-item input {
    width: 100%;
    margin-bottom: 10px;
    border-radius: 4px;
    border: 1px solid #ccd0d4;
    padding: 7px 10px;
    font-size: 14px;
    background: #f6f7f7;
    transition: border-color 0.2s;
}

.crp-repeater-item input:focus {
    border-color: #2271b1;
    background: #fff;
    outline: none;
}

.crp-remove-item {
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

.crp-remove-item:hover {
    background: #fbeaea;
}

#crp-add-item {
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
function crp_save_repeater_fields( $post_id ) {
    $nonce = isset( $_POST['crp_repeater_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['crp_repeater_nonce'] ) ) : '';
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'crp_save_repeater_fields' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    $repeater_data = [];
    $crp_repeater = isset( $_POST['crp_repeater'] ) ? wp_unslash( $_POST['crp_repeater'] ) : [];
    // Re-index 'new' items
    $indexed = [];
    foreach ( $crp_repeater as $key => $item ) {
        if ($key === 'new' || !is_numeric($key)) {
            $indexed[] = $item;
        } else {
            $indexed[$key] = $item;
        }
    }
    $crp_repeater = $indexed;
    if ( is_array( $crp_repeater ) ) {
        foreach ( $crp_repeater as $item ) {
            $text = isset($item['text']) ? sanitize_text_field($item['text']) : '';
            $url = isset($item['url']) ? esc_url_raw($item['url']) : '';
            if ( empty( $text ) && empty( $url ) ) {
                continue;
            }
            $repeater_data[] = [
                'text' => $text,
                'url'  => $url,
            ];
        }
    }
    if ( ! empty( $repeater_data ) ) {
        update_post_meta( $post_id, '_crp_repeater_data', $repeater_data );
    } else {
        delete_post_meta( $post_id, '_crp_repeater_data' );
    }
    // Save enable checkbox
    if ( isset( $_POST['crp_repeater_enable'] ) ) {
        update_post_meta( $post_id, '_crp_repeater_enabled', '1' );
    } else {
        update_post_meta( $post_id, '_crp_repeater_enabled', '0' );
    }
}
add_action( 'save_post', 'crp_save_repeater_fields' );