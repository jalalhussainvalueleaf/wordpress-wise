<?php
function clusters_register_repeater_field_rest() {
    foreach (['post', 'page'] as $type) {
        register_rest_field( $type, 'custom_repeater', [
            'get_callback'    => function( $post ) {
                $enabled = get_post_meta( $post['id'], '_clusters_repeater_enabled', true );
                if ( $enabled === '1' ) {
                    $data = get_post_meta( $post['id'], '_clusters_repeater_data', true );
                    // Only keep heading and paragraph
                    if (is_array($data)) {
                        foreach ($data as &$item) {
                            $item = [
                                'heading' => isset($item['heading']) ? $item['heading'] : '',
                                'paragraph' => isset($item['paragraph']) ? $item['paragraph'] : '',
                            ];
                        }
                    }
                    return $data;
                }
                return null;
            },
            'update_callback' => function( $value, $post ) {
                if ( is_array( $value ) ) {
                    $sanitized = array_map( function( $item ) {
                        return [
                            'heading'   => isset($item['heading']) ? sanitize_text_field($item['heading']) : '',
                            'paragraph' => isset($item['paragraph']) ? sanitize_textarea_field($item['paragraph']) : '',
                        ];
                    }, $value );
                    update_post_meta( $post->ID, '_clusters_repeater_data', $sanitized );
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
                    ],
                ],
            ],
        ]);
        // Expose enabled state
        register_rest_field( $type, '_clusters_repeater_enabled', [
            'get_callback'    => function( $post ) {
                return get_post_meta( $post['id'], '_clusters_repeater_enabled', true );
            },
            'update_callback' => function( $value, $post ) {
                return update_post_meta( $post->ID, '_clusters_repeater_enabled', sanitize_text_field( $value ) );
            },
            'schema'          => [
                'description' => __( 'Custom repeater enabled', 'custom-repeater-plugin' ),
                'type'        => 'string',
                'context'     => [ 'view', 'edit' ],
            ],
        ] );
    }
}
add_action( 'rest_api_init', 'clusters_register_repeater_field_rest' );
