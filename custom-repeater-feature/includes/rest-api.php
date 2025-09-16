<?php
function crf_register_repeater_field_rest() {
    foreach (['post', 'page'] as $type) {
        register_rest_field( $type, 'custom_repeater', [
            'get_callback'    => function( $post ) {
                $enabled = get_post_meta( $post['id'], '_crf_repeater_enabled', true );
                if ( $enabled === '1' ) {
                    return get_post_meta( $post['id'], '_crf_repeater_data', true );
                }
                return null;
            },
            'update_callback' => function( $value, $post ) {
                if ( is_array( $value ) ) {
                    $sanitized = array_map( function( $item ) {
                        return [
                            'heading'   => isset($item['heading']) ? sanitize_text_field($item['heading']) : '',
                            'paragraph' => isset($item['paragraph']) ? sanitize_textarea_field($item['paragraph']) : '',
                            'image'     => isset($item['image']) ? absint($item['image']) : '',
                            'text'      => isset($item['text']) ? sanitize_text_field($item['text']) : '',
                            'url'       => isset($item['url']) ? esc_url_raw($item['url']) : '',
                        ];
                    }, $value );
                    update_post_meta( $post->ID, '_crf_repeater_data', $sanitized );
                }
            },
            'schema'          => [
                'description' => __( 'Custom repeater fields', 'custom-repeater-plugin' ),
                'type'        => 'array',
                'items'       => [
                    'type'       => 'object',
                    'properties' => [
                        'heading'   => [ 'type' => 'string' ],
                        'paragraph' => [ 'type' => 'string' ],
                        'image'     => [ 'type' => 'integer' ],
                        'text'      => [ 'type' => 'string' ],
                        'url'       => [ 'type' => 'string' ],
                    ],
                ],
            ],
        ]);
        // Expose enabled state
        register_rest_field( $type, '_crf_repeater_enabled', [
            'get_callback'    => function( $post ) {
                return get_post_meta( $post['id'], '_crf_repeater_enabled', true );
            },
            'update_callback' => function( $value, $post ) {
                return update_post_meta( $post->ID, '_crf_repeater_enabled', sanitize_text_field( $value ) );
            },
            'schema'          => [
                'description' => __( 'Custom repeater enabled', 'custom-repeater-plugin' ),
                'type'        => 'string',
                'context'     => [ 'view', 'edit' ],
            ],
        ] );
    }
}
add_action( 'rest_api_init', 'crf_register_repeater_field_rest' );

add_action('rest_api_init', function() {
    foreach (['post', 'page'] as $type) {
        register_rest_field($type, 'plugin_data', [
            'get_callback' => function($object) {
                $data = get_post_meta($object['id'], '_mad_crf_data', true);
                // Convert image IDs to URLs
                if (is_array($data)) {
                    foreach ($data as &$item) {
                        if (isset($item['image']) && $item['image']) {
                            $item['image'] = wp_get_attachment_url($item['image']);
                        }
                    }
                }
                return [
                    'mad_custom_card_repeater' => $data
                ];
            },
            'schema' => [
                'description' => __('Plugin data for MAD Custom Card Repeater', 'custom-repeater-feature'),
                'type' => 'object',
            ],
        ]);
    }
});
