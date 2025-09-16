<?php
/**
 * Plugin Name: Content Migration API
 * Description: Custom API endpoints for content migration
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL-2.0+
 */

// Security check
defined('ABSPATH') || exit;

class Content_Migration_API {
    private $namespace = 'content-migration/v1';
    private $route = '/migrate-post';

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        register_rest_route($this->namespace, $this->route, array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_migration'),
            'permission_callback' => array($this, 'verify_request'),
        ));
    }

    public function verify_request($request) {
        // Get authorization header
        $auth_header = $request->get_header('authorization');
        
        // Verify the token (you should use a more secure method in production)
        $valid_token = 'OXl6gPX17QBOxK/CArKl8jP/ZRPOt1VJZ4R9OjtRyCw='; // Replace with a secure token
        $bearer_token = str_replace('Bearer ', '', $auth_header);
        
        return $bearer_token === $valid_token;
    }

   public function handle_migration($request) {
    $params = $request->get_json_params();
    
    // Validate required fields
    if (empty($params['title']) || empty($params['content'])) {
        return new WP_Error('missing_fields', 'Title and content are required', array('status' => 400));
    }

    // Prepare post data
    $post_data = array(
        'post_title'    => sanitize_text_field($params['title']),
        'post_name'     => sanitize_text_field($params['slug']), // Use post_name for slug
        'post_content'  => wp_kses_post($params['content']),
        'post_status'   => isset($params['status']) ? $params['status'] : 'draft',
        'post_type'     => isset($params['type']) ? $params['type'] : 'page',
    );

    // Insert the post
    $post_id = wp_insert_post($post_data, true);

    if (is_wp_error($post_id)) {
        return new WP_Error('post_creation_failed', $post_id->get_error_message(), array('status' => 500));
    }

    // Add meta fields if provided
    if (!empty($params['meta'])) {
        foreach ($params['meta'] as $key => $value) {
            update_post_meta($post_id, sanitize_key($key), sanitize_meta($key, $value, 'page'));
        }
    }

    // Add custom fields if provided
    if (!empty($params['custom_fields'])) {
        foreach ($params['custom_fields'] as $field) {
            if (isset($field['key']) && isset($field['value'])) {
                update_post_meta($post_id, sanitize_key($field['key']), sanitize_text_field($field['value']));
            }
        }
    }

    // Handle FAQ data if provided
    if (!empty($params['faq_data'])) {
        $faq_data = $params['faq_data'];
        if (isset($faq_data['enabled'])) {
            update_post_meta($post_id, '_faq_manager_enabled', $faq_data['enabled'] ? '1' : '0');
        }
        if (!empty($faq_data['items']) && is_array($faq_data['items'])) {
            $faq_items = array_map(function($item) {
                return array(
                    'question' => sanitize_text_field($item['question']),
                    'answer'   => wp_kses_post($item['answer']),
                );
            }, $faq_data['items']);
            update_post_meta($post_id, '_faq_manager_data', $faq_items);
        }
    }

    // Return success response
    return new WP_REST_Response(array(
        'success' => true,
        'post_id' => $post_id,
        'edit_link' => get_edit_post_link($post_id, '')
    ), 200);
}
}

// Initialize the plugin
new Content_Migration_API();