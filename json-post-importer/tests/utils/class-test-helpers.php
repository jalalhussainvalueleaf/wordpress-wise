<?php
/**
 * Test helper utilities
 */
class Test_Helpers {
    
    /**
     * Clean up test data after tests
     */
    public static function cleanup_test_data() {
        // Remove test posts
        $posts = get_posts(array(
            'post_type' => array('post', 'page', 'custom_post'),
            'post_status' => 'any',
            'numberposts' => -1,
            'meta_query' => array(
                array(
                    'key' => '_test_post',
                    'value' => '1',
                    'compare' => '='
                )
            )
        ));
        
        foreach ($posts as $post) {
            wp_delete_post($post->ID, true);
        }
        
        // Remove test categories and tags
        $terms = get_terms(array(
            'taxonomy' => array('category', 'post_tag'),
            'hide_empty' => false,
            'meta_query' => array(
                array(
                    'key' => '_test_term',
                    'value' => '1',
                    'compare' => '='
                )
            )
        ));
        
        foreach ($terms as $term) {
            wp_delete_term($term->term_id, $term->taxonomy);
        }
        
        // Clean up transients
        delete_transient('jpi_import_*');
        
        // Clean up options
        delete_option('jpi_test_*');
    }
    
    /**
     * Mark post as test data
     *
     * @param int $post_id Post ID
     */
    public static function mark_as_test_post($post_id) {
        update_post_meta($post_id, '_test_post', '1');
    }
    
    /**
     * Mark term as test data
     *
     * @param int $term_id Term ID
     */
    public static function mark_as_test_term($term_id) {
        update_term_meta($term_id, '_test_term', '1');
    }
    
    /**
     * Create temporary JSON file for testing
     *
     * @param array $data JSON data
     * @return string File path
     */
    public static function create_temp_json_file($data) {
        $temp_file = wp_tempnam('test_json_');
        file_put_contents($temp_file, json_encode($data));
        return $temp_file;
    }
    
    /**
     * Mock WordPress HTTP API response
     *
     * @param string $url URL to mock
     * @param array $response Response data
     */
    public static function mock_http_response($url, $response) {
        add_filter('pre_http_request', function($preempt, $args, $request_url) use ($url, $response) {
            if ($request_url === $url) {
                return $response;
            }
            return $preempt;
        }, 10, 3);
    }
    
    /**
     * Assert that a post has specific meta values
     *
     * @param int $post_id Post ID
     * @param array $expected_meta Expected meta key-value pairs
     */
    public static function assert_post_meta($post_id, $expected_meta) {
        foreach ($expected_meta as $key => $expected_value) {
            $actual_value = get_post_meta($post_id, $key, true);
            if ($actual_value !== $expected_value) {
                throw new Exception("Meta key '{$key}' expected '{$expected_value}' but got '{$actual_value}'");
            }
        }
    }
    
    /**
     * Assert that a post has specific taxonomy terms
     *
     * @param int $post_id Post ID
     * @param string $taxonomy Taxonomy name
     * @param array $expected_terms Expected term names or IDs
     */
    public static function assert_post_terms($post_id, $taxonomy, $expected_terms) {
        $actual_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'names'));
        
        if (is_wp_error($actual_terms)) {
            throw new Exception("Error getting terms for post {$post_id}: " . $actual_terms->get_error_message());
        }
        
        sort($expected_terms);
        sort($actual_terms);
        
        if ($expected_terms !== $actual_terms) {
            throw new Exception("Expected terms: " . implode(', ', $expected_terms) . " but got: " . implode(', ', $actual_terms));
        }
    }
    
    /**
     * Get current user with admin capabilities
     *
     * @return int User ID
     */
    public static function get_admin_user() {
        $user = get_user_by('login', 'admin');
        if (!$user) {
            $user_id = wp_insert_user(array(
                'user_login' => 'admin',
                'user_email' => 'admin@example.com',
                'user_pass' => 'password',
                'role' => 'administrator'
            ));
            return $user_id;
        }
        return $user->ID;
    }
    
    /**
     * Set current user for testing
     *
     * @param int $user_id User ID
     */
    public static function set_current_user($user_id) {
        wp_set_current_user($user_id);
    }
}