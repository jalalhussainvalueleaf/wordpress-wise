<?php
function crp_register_repeater_field_rest() {
    foreach (['post', 'page'] as $type) {
        register_rest_field( $type, 'custom_repeater', [
            'get_callback'    => function( $post ) {
                $enabled = get_post_meta( $post['id'], '_crp_repeater_enabled', true );
                if ( $enabled === '1' ) {
                    return get_post_meta( $post['id'], '_crp_repeater_data', true );
                }
                return null;
            },
            'update_callback' => function( $value, $post ) {
                if ( is_array( $value ) ) {
                    $sanitized = array_map( function( $item ) {
                        return [
                            'text' => sanitize_text_field( $item['text'] ),
                            'url'  => esc_url_raw( $item['url'] ),
                        ];
                    }, $value );
                    update_post_meta( $post->ID, '_crp_repeater_data', $sanitized );
                }
            },
            'schema'          => [
                'description' => __( 'Custom repeater fields', 'custom-repeater-plugin' ),
                'type'        => 'array',
                'items'       => [
                    'type'       => 'object',
                    'properties' => [
                        'text' => [ 'type' => 'string' ],
                        'url'  => [ 'type' => 'string' ],
                    ],
                ],
            ],
        ]);
        // Expose enabled state
        register_rest_field( $type, '_crp_repeater_enabled', [
            'get_callback'    => function( $post ) {
                return get_post_meta( $post['id'], '_crp_repeater_enabled', true );
            },
            'update_callback' => function( $value, $post ) {
                return update_post_meta( $post->ID, '_crp_repeater_enabled', sanitize_text_field( $value ) );
            },
            'schema'          => [
                'description' => __( 'Custom repeater enabled', 'custom-repeater-plugin' ),
                'type'        => 'string',
                'context'     => [ 'view', 'edit' ],
            ],
        ] );
    }
}
add_action( 'rest_api_init', 'crp_register_repeater_field_rest' );
