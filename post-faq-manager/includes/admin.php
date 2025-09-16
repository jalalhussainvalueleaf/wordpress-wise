<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Add meta box for managing FAQs.
function faq_manager_add_meta_box() {
    add_meta_box(
        'faq_manager_meta_box',
        'FAQ Manager',
        'faq_manager_render_meta_box',
        ['post', 'page'], // Enable for both posts and pages
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'faq_manager_add_meta_box' );

// Render the meta box content.
function faq_manager_render_meta_box( $post ) {
    wp_nonce_field( 'faq_manager_save_fields', 'faq_manager_nonce' );

    $faq_data = get_post_meta( $post->ID, '_faq_manager_data', true );
    $faq_enabled = get_post_meta( $post->ID, '_faq_manager_enabled', true );
    ?>
<div id="faq-manager-container" class="faq-manager-wrapper">
    <label style="display:flex;align-items:center;margin-bottom:16px;font-weight:500;gap:8px;">
        <input type="checkbox" id="faq-manager-enable" name="faq_manager_enable" value="1"
            <?php checked( $faq_enabled, '1' ); ?> />
        Enable FAQ Options
    </label>
    <div id="faq-manager-fields" style="display:<?php echo $faq_enabled === '1' ? 'block' : 'none'; ?>;">
        <h3 class="faq-manager-title"><span class="dashicons dashicons-editor-help"></span> Frequently Asked Questions
        </h3>
        <template id="faq-manager-template">
            <div class="faq-manager-item">
                <div class="faq-manager-fields">
                    <label>Question</label>
                    <input type="text" name="faq_manager[new][question]" placeholder="Enter question" />
                    <label>Answer</label>
                    <textarea name="faq_manager[new][answer]" placeholder="Enter answer"></textarea>
                </div>
                <button type="button" class="faq-manager-remove-item" title="Remove FAQ"><span
                        class="dashicons dashicons-no-alt"></span></button>
            </div>
        </template>

        <?php if ( ! empty( $faq_data ) ) : ?>
        <?php foreach ( $faq_data as $index => $item ) : ?>
        <div class="faq-manager-item">
            <div class="faq-manager-fields">
                <label>Question</label>
                <input type="text" name="faq_manager[<?php echo esc_attr( $index ); ?>][question]"
                    value="<?php echo esc_attr( $item['question'] ); ?>" placeholder="Enter question" />
                <label>Answer</label>
                <textarea name="faq_manager[<?php echo esc_attr( $index ); ?>][answer]" rows="5"
                    placeholder="Enter answer"><?php echo esc_textarea( $item['answer'] ); ?></textarea>
            </div>
            <button type="button" class="faq-manager-remove-item" title="Remove FAQ"><span
                    class="dashicons dashicons-no-alt"></span></button>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <button type="button" id="faq-manager-add-item" class="button button-primary"><span
                class="dashicons dashicons-plus-alt2"></span> Add FAQ</button>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const enableCheckbox = document.getElementById('faq-manager-enable');
    const fields = document.getElementById('faq-manager-fields');
    enableCheckbox.addEventListener('change', function() {
        fields.style.display = this.checked ? 'block' : 'none';
    });
    const template = document.getElementById('faq-manager-template').content;
    document.getElementById('faq-manager-add-item').addEventListener('click', () => {
        // Find the next available index
        let maxIndex = -1;
        fields.querySelectorAll('.faq-manager-item input[name^="faq_manager["]').forEach(input => {
            const match = input.name.match(/^faq_manager\[(\d+)\]/);
            if (match && parseInt(match[1]) > maxIndex) {
                maxIndex = parseInt(match[1]);
            }
        });
        const nextIndex = maxIndex + 1;
        // Clone and update names
        const clone = document.importNode(template, true);
        const q = clone.querySelector('input[name="faq_manager[new][question]"]');
        const a = clone.querySelector('textarea[name="faq_manager[new][answer]"]');
        if (q) q.name = `faq_manager[${nextIndex}][question]`;
        if (a) a.name = `faq_manager[${nextIndex}][answer]`;
        fields.insertBefore(clone, fields.querySelector('#faq-manager-add-item'));
    });
    fields.addEventListener('click', function(e) {
        if (e.target.closest && e.target.closest('button.faq-manager-remove-item')) {
            e.target.closest('.faq-manager-item').remove();
        }
    });
});
</script>
<style>
.faq-manager-wrapper {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 18px 18px 12px 18px;
    border: 1px solid #e5e5e5;
    margin-bottom: 0;
}

.faq-manager-title {
    font-size: 1.2em;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.faq-manager-item {
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

.faq-manager-fields {
    flex: 1;
}

.faq-manager-fields label {
    font-weight: 500;
    margin-bottom: 4px;
    display: block;
    color: #23282d;
}

.faq-manager-item input,
.faq-manager-item textarea {
    width: 100%;
    margin-bottom: 10px;
    border-radius: 4px;
    border: 1px solid #ccd0d4;
    padding: 7px 10px;
    font-size: 14px;
    background: #f6f7f7;
    transition: border-color 0.2s;
}

.faq-manager-item input:focus,
.faq-manager-item textarea:focus {
    border-color: #2271b1;
    background: #fff;
    outline: none;
}

.faq-manager-remove-item {
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

.faq-manager-remove-item:hover {
    background: #fbeaea;
}

#faq-manager-add-item {
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 500;
    font-size: 15px;
}

.faq-manager-wrapper .quicktags-toolbar {
    display: flex !important;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 6px;
}

.faq-manager-wrapper .quicktags-toolbar input[type="button"] {
    display: inline-block;
    width: auto;
    min-width: 36px;
    padding: 2px 10px;
    margin: 0;
    font-size: 13px;
    border-radius: 3px;
    border: 1px solid #ccd0d4;
    background: #f6f7f7;
    color: #2271b1;
    transition: background 0.2s, border-color 0.2s;
}

.faq-manager-wrapper .quicktags-toolbar input[type="button"]:hover {
    background: #eaf6fb;
    border-color: #2271b1;
    color: #135e96;
}
</style>
<?php
}

// Helper function to recursively sanitize all values in an array
function faq_manager_recursive_sanitize_text_field($array) {
    foreach ($array as $key => &$value) {
        if (is_array($value)) {
            $value = faq_manager_recursive_sanitize_text_field($value);
        } else {
            $value = sanitize_text_field($value);
        }
    }
    return $array;
}

// Save FAQ data.
function faq_manager_save_fields( $post_id ) {
    $nonce = isset( $_POST['faq_manager_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['faq_manager_nonce'] ) ) : '';
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'faq_manager_save_fields' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $faq_data = [];
    $faq_manager = isset( $_POST['faq_manager'] ) ? wp_unslash( $_POST['faq_manager'] ) : [];
    // Re-index 'new' items
    $indexed = [];
    foreach ( $faq_manager as $key => $item ) {
        if ($key === 'new' || !is_numeric($key)) {
            $indexed[] = $item;
        } else {
            $indexed[$key] = $item;
        }
    }
    $faq_manager = $indexed;
    if ( is_array( $faq_manager ) ) {
        $faq_manager = faq_manager_recursive_sanitize_text_field($faq_manager);
        foreach ( $faq_manager as $item ) {
            $question = isset($item['question']) ? $item['question'] : '';
            $answer = isset($item['answer']) ? wp_kses_post($item['answer']) : '';
            if ( empty( $question ) && empty( $answer ) ) {
                continue;
            }
            $faq_data[] = [
                'question' => $question,
                'answer'   => $answer,
            ];
        }
    }

    if ( ! empty( $faq_data ) ) {
        update_post_meta( $post_id, '_faq_manager_data', $faq_data );
    } else {
        delete_post_meta( $post_id, '_faq_manager_data' );
    }

    // Save enable checkbox
    if ( isset( $_POST['faq_manager_enable'] ) ) {
        update_post_meta( $post_id, '_faq_manager_enabled', '1' );
    } else {
        update_post_meta( $post_id, '_faq_manager_enabled', '0' );
    }
}
add_action( 'save_post', 'faq_manager_save_fields' );