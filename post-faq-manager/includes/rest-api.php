<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register REST API routes.
function faq_manager_register_rest_routes() {
    register_rest_route( 'faq-manager/v1', '/faqs', [
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'faq_manager_get_faqs',
        'permission_callback' => '__return_true',
    ] );

    register_rest_route( 'faq-manager/v1', '/faqs', [
        'methods'  => WP_REST_Server::CREATABLE,
        'callback' => 'faq_manager_save_faqs',
        'permission_callback' => 'faq_manager_check_permissions',
    ] );
}
add_action( 'rest_api_init', 'faq_manager_register_rest_routes' );

// Register FAQ meta field for posts REST API
function faq_manager_register_faq_meta_rest_field() {
    foreach (['post', 'page'] as $type) {
        // Register the enabled meta field
        register_rest_field( $type, '_faq_manager_enabled', [
            'get_callback'    => function( $post_arr ) {
                return get_post_meta( $post_arr['id'], '_faq_manager_enabled', true );
            },
            'update_callback' => function( $value, $post ) {
                return update_post_meta( $post->ID, '_faq_manager_enabled', $value );
            },
            'schema'          => [
                'description' => __( 'FAQ Manager Enabled', 'post-faq-manager' ),
                'type'        => 'string',
                'context'     => [ 'view', 'edit' ],
            ],
        ] );
        // Register the FAQ data meta field, but only show if enabled
        register_rest_field( $type, '_faq_manager_data', [
            'get_callback'    => function( $post_arr ) {
                $enabled = get_post_meta( $post_arr['id'], '_faq_manager_enabled', true );
                if ( $enabled === '1' ) {
                    return get_post_meta( $post_arr['id'], '_faq_manager_data', true );
                }
                return null;
            },
            'update_callback' => function( $value, $post ) {
                return update_post_meta( $post->ID, '_faq_manager_data', $value );
            },
            'schema'          => [
                'description' => __( 'FAQ Manager Data', 'post-faq-manager' ),
                'type'        => 'array',
                'context'     => [ 'view', 'edit' ],
            ],
        ] );
    }
}
add_action( 'rest_api_init', 'faq_manager_register_faq_meta_rest_field' );

// Fetch FAQs via REST API.
function faq_manager_get_faqs( WP_REST_Request $request ) {
    $posts = get_posts( [
        'post_type'      => 'post',
        'posts_per_page' => -1,
        'meta_key'       => '_faq_manager_enabled',
        'meta_value'     => '1',
    ] );

    $faqs = [];
    foreach ( $posts as $post ) {
        $faqs[] = [
            'id'      => $post->ID,
            'title'   => $post->post_title,
            'faqs'    => get_post_meta( $post->ID, '_faq_manager_data', true ),
        ];
    }

    return rest_ensure_response( $faqs );
}

// Save FAQs via REST API.
function faq_manager_save_faqs( WP_REST_Request $request ) {
    $post_id = $request->get_param( 'post_id' );
    $faqs    = $request->get_param( 'faqs' );

    if ( empty( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
        return new WP_Error( 'rest_forbidden', 'You do not have permission to edit this post.', [ 'status' => 403 ] );
    }

    update_post_meta( $post_id, '_faq_manager_data', $faqs );
    return rest_ensure_response( [ 'success' => true ] );
}

// Check permissions for saving via REST API.
function faq_manager_check_permissions( WP_REST_Request $request ) {
    $post_id = $request->get_param( 'post_id' );
    return current_user_can( 'edit_post', $post_id );
}