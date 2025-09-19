<?php
/**
 * Test data factory for creating test JSON data and WordPress objects
 */
class Test_Data_Factory {
    
    /**
     * Create sample JSON data for testing
     *
     * @param array $overrides Override default values
     * @return array Sample JSON data
     */
    public static function create_sample_json_data($overrides = array()) {
        $default_data = array(
            array(
                'title' => 'Test Post 1',
                'content' => 'This is the content for test post 1.',
                'excerpt' => 'Test excerpt 1',
                'status' => 'publish',
                'date' => '2023-01-01 12:00:00',
                'author' => 1,
                'slug' => 'test-post-1',
                'type' => 'post',
                'taxonomies' => array(
                    'category' => array('Test Category'),
                    'post_tag' => array('test', 'sample')
                ),
                'meta' => array(
                    'custom_field_1' => 'Custom value 1',
                    'custom_field_2' => 'Custom value 2'
                ),
                'featured_image' => 'https://example.com/image1.jpg'
            ),
            array(
                'title' => 'Test Post 2',
                'content' => 'This is the content for test post 2.',
                'excerpt' => 'Test excerpt 2',
                'status' => 'draft',
                'date' => '2023-01-02 12:00:00',
                'author' => 1,
                'slug' => 'test-post-2',
                'type' => 'post',
                'taxonomies' => array(
                    'category' => array('Another Category'),
                    'post_tag' => array('test', 'example')
                ),
                'meta' => array(
                    'custom_field_1' => 'Custom value 3',
                    'custom_field_3' => 'Custom value 4'
                )
            )
        );
        
        return wp_parse_args($overrides, $default_data);
    }
    
    /**
     * Create malformed JSON data for error testing
     *
     * @return array Various malformed data structures
     */
    public static function create_malformed_json_data() {
        return array(
            'empty_array' => array(),
            'null_data' => null,
            'string_data' => 'not an array',
            'missing_title' => array(
                array(
                    'content' => 'Content without title',
                    'status' => 'publish'
                )
            ),
            'invalid_status' => array(
                array(
                    'title' => 'Test Post',
                    'content' => 'Test content',
                    'status' => 'invalid_status'
                )
            ),
            'invalid_date' => array(
                array(
                    'title' => 'Test Post',
                    'content' => 'Test content',
                    'date' => 'not a valid date'
                )
            )
        );
    }
    
    /**
     * Create field mapping configurations for testing
     *
     * @return array Various field mapping configurations
     */
    public static function create_field_mappings() {
        return array(
            'basic_mapping' => array(
                'post_title' => 'title',
                'post_content' => 'content',
                'post_excerpt' => 'excerpt',
                'post_status' => 'status',
                'post_date' => 'date'
            ),
            'custom_mapping' => array(
                'post_title' => 'custom_title_field',
                'post_content' => 'body_text',
                'post_excerpt' => 'summary',
                'post_status' => 'publish_status',
                'post_date' => 'created_at'
            ),
            'incomplete_mapping' => array(
                'post_content' => 'content',
                'post_excerpt' => 'excerpt'
                // Missing required post_title mapping
            ),
            'with_taxonomies' => array(
                'post_title' => 'title',
                'post_content' => 'content',
                'taxonomies' => array(
                    'category' => 'categories',
                    'post_tag' => 'tags'
                )
            ),
            'with_meta' => array(
                'post_title' => 'title',
                'post_content' => 'content',
                'meta' => array(
                    'custom_field_1' => 'cf1',
                    'custom_field_2' => 'cf2'
                )
            )
        );
    }
    
    /**
     * Create import options for testing
     *
     * @return array Various import option configurations
     */
    public static function create_import_options() {
        return array(
            'default_options' => array(
                'post_type' => 'post',
                'post_status' => 'draft',
                'update_existing' => true,
                'skip_thumbnail' => false,
                'import_attachments' => true,
                'default_author' => 1
            ),
            'custom_post_type' => array(
                'post_type' => 'custom_post',
                'post_status' => 'publish',
                'update_existing' => false,
                'skip_thumbnail' => true,
                'import_attachments' => false,
                'default_author' => 2
            ),
            'minimal_options' => array(
                'post_type' => 'post'
            )
        );
    }
    
    /**
     * Create test WordPress posts
     *
     * @param int $count Number of posts to create
     * @return array Array of post IDs
     */
    public static function create_test_posts($count = 3) {
        $post_ids = array();
        
        for ($i = 1; $i <= $count; $i++) {
            $post_id = wp_insert_post(array(
                'post_title' => "Test Post {$i}",
                'post_content' => "Content for test post {$i}",
                'post_status' => 'publish',
                'post_type' => 'post'
            ));
            
            if (!is_wp_error($post_id)) {
                $post_ids[] = $post_id;
            }
        }
        
        return $post_ids;
    }
    
    /**
     * Create test categories and tags
     *
     * @return array Array with category and tag IDs
     */
    public static function create_test_taxonomies() {
        $category_id = wp_insert_term('Test Category', 'category');
        $tag_id = wp_insert_term('test-tag', 'post_tag');
        
        return array(
            'category' => is_wp_error($category_id) ? null : $category_id['term_id'],
            'tag' => is_wp_error($tag_id) ? null : $tag_id['term_id']
        );
    }
    
    /**
     * Create test user
     *
     * @return int User ID
     */
    public static function create_test_user() {
        return wp_insert_user(array(
            'user_login' => 'testuser',
            'user_email' => 'test@example.com',
            'user_pass' => 'password',
            'role' => 'editor'
        ));
    }
}