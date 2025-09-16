<?php
/**
 * The API functionality of the plugin.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class JSON_Post_Importer_API {
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        register_rest_route('json-post-importer/v1', '/import', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_import_request'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
    }

    public function check_permissions($request) {
        // Check if user is logged in and has the right permissions
        if (!current_user_can('edit_posts')) {
            return new WP_Error(
                'rest_forbidden',
                esc_html__('You cannot create posts.'),
                array('status' => rest_authorization_required_code())
            );
        }
        
        return true;
    }

    public function handle_import_request($request) {
        $json_data = $request->get_json_params();
        
        if (empty($json_data)) {
            return new WP_Error(
                'no_data', 
                esc_html__('No JSON data provided', 'json-post-importer'), 
                array('status' => 400)
            );
        }

        // Validate JSON structure
        $validation = $this->validate_json_structure($json_data);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Process the data
        $result = $this->process_json_data($json_data);
        
        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response(array(
            'success' => true,
            'data' => $result
        ), 200);
    }

    /**
     * Process JSON data and create/update posts
     *
     * @param array $json_data The JSON data to process
     * @return array|WP_Error Result of the operation
     */
    public function process_json_data($json_data) {
        if (!is_array($json_data)) {
            return new WP_Error(
                'invalid_data',
                esc_html__('Invalid data format. Expected an array of items.', 'json-post-importer'),
                array('status' => 400)
            );
        }

        $results = array(
            'total' => count($json_data),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => array()
        );

        $post_creator = new JSON_Post_Creator();

        foreach ($json_data as $index => $item) {
            try {
                // Set default post type if not specified
                if (!isset($item['post_type'])) {
                    $item['post_type'] = 'post';
                }
                
                // Process the item
                $result = $post_creator->create_or_update_post($item);
                
                if (is_wp_error($result)) {
                    $results['errors'][$index] = array(
                        'item' => $item,
                        'error' => $result->get_error_message()
                    );
                    $results['skipped']++;
                    continue;
                }

                if (isset($result['updated']) && $result['updated']) {
                    $results['updated']++;
                } else {
                    $results['created']++;
                }
            } catch (Exception $e) {
                $results['errors'][$index] = array(
                    'item' => $item,
                    'error' => $e->getMessage()
                );
                $results['skipped']++;
            }
        }

        return $results;
    }

    /**
     * Validate JSON structure
     *
     * @param array $json_data The JSON data to validate
     * @return bool|WP_Error True if valid, WP_Error otherwise
     */
    private function validate_json_structure($json_data) {
        if (!is_array($json_data)) {
            return new WP_Error(
                'invalid_format',
                esc_html__('Invalid data format. Expected an array of items.', 'json-post-importer'),
                array('status' => 400)
            );
        }

        if (empty($json_data)) {
            return new WP_Error(
                'empty_data',
                esc_html__('No data to import.', 'json-post-importer'),
                array('status' => 400)
            );
        }

        return true;
    }

    /**
     * Set featured image for a post
     * 
     * @param int $post_id Post ID
     * @param string $image_url URL of the image to set as featured
     * @return int|WP_Error Attachment ID on success, WP_Error on failure
     */
    private function set_featured_image($post_id, $image_url) {
        if (empty($image_url)) {
            return new WP_Error('empty_url', 'No image URL provided');
        }

        // Use the post creator's method to handle the featured image
        $post_creator = new JSON_Post_Creator();
        $result = $post_creator->process_featured_image($post_id, $image_url, []);
        
        return is_wp_error($result) ? $result : true;
    }
    
    /**
     * Upload media from URL to the media library
     * 
     * @deprecated 1.0.0 Use JSON_Post_Creator::upload_media() instead
     */
    private function upload_media($image_url, $post_id = 0) {
        _deprecated_function(__METHOD__, '1.0.0', 'JSON_Post_Creator::upload_media()');
        $post_creator = new JSON_Post_Creator();
        return $post_creator->upload_media($image_url, ['post_parent' => $post_id]);
    }
    
    /**
     * Get attachment ID by URL
     * 
     * @deprecated 1.0.0 Use JSON_Post_Creator::get_attachment_id_by_url() instead
     */
    private function get_attachment_id_by_url($url) {
        _deprecated_function(__METHOD__, '1.0.0', 'JSON_Post_Creator::get_attachment_id_by_url()');
        $post_creator = new JSON_Post_Creator();
        return $post_creator->get_attachment_id_by_url($url);
    }
}
