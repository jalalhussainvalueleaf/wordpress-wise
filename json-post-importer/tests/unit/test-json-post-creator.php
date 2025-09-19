<?php
/**
 * Unit tests for JSON_Post_Creator class
 */
class Test_JSON_Post_Creator extends WP_UnitTestCase {
    
    private $post_creator;
    private $test_user_id;
    
    public function setUp(): void {
        parent::setUp();
        
        $this->post_creator = new JSON_Post_Creator();
        $this->test_user_id = Test_Helpers::get_admin_user();
        Test_Helpers::set_current_user($this->test_user_id);
    }
    
    public function tearDown(): void {
        Test_Helpers::cleanup_test_data();
        parent::tearDown();
    }
    
    /**
     * Test creating a basic post from JSON data
     */
    public function test_create_basic_post() {
        $item = array(
            'title' => 'Test Post Title',
            'content' => 'Test post content',
            'excerpt' => 'Test excerpt',
            'status' => 'publish'
        );
        
        $result = $this->post_creator->create_or_update_post($item);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('updated', $result);
        $this->assertFalse($result['updated']); // Should be a new post
        
        $post = get_post($result['id']);
        $this->assertEquals('Test Post Title', $post->post_title);
        $this->assertEquals('Test post content', $post->post_content);
        $this->assertEquals('Test excerpt', $post->post_excerpt);
        $this->assertEquals('publish', $post->post_status);
        
        Test_Helpers::mark_as_test_post($result['id']);
    }
    
    /**
     * Test creating post with custom options
     */
    public function test_create_post_with_options() {
        $item = array(
            'title' => 'Custom Post',
            'content' => 'Custom content'
        );
        
        $options = array(
            'post_type' => 'page',
            'post_status' => 'draft',
            'default_author' => $this->test_user_id
        );
        
        $result = $this->post_creator->create_or_update_post($item, $options);
        
        $this->assertIsArray($result);
        $post = get_post($result['id']);
        $this->assertEquals('page', $post->post_type);
        $this->assertEquals('draft', $post->post_status);
        $this->assertEquals($this->test_user_id, $post->post_author);
        
        Test_Helpers::mark_as_test_post($result['id']);
    }
    
    /**
     * Test updating existing post
     */
    public function test_update_existing_post() {
        // Create initial post
        $post_id = wp_insert_post(array(
            'post_title' => 'Original Title',
            'post_content' => 'Original content',
            'post_status' => 'publish',
            'post_name' => 'original-slug'
        ));
        
        Test_Helpers::mark_as_test_post($post_id);
        
        // Update with JSON data
        $item = array(
            'title' => 'Updated Title',
            'content' => 'Updated content',
            'slug' => 'original-slug'
        );
        
        $options = array('update_existing' => true);
        $result = $this->post_creator->create_or_update_post($item, $options);
        
        $this->assertIsArray($result);
        $this->assertTrue($result['updated']);
        $this->assertEquals($post_id, $result['id']);
        
        $updated_post = get_post($post_id);
        $this->assertEquals('Updated Title', $updated_post->post_title);
        $this->assertEquals('Updated content', $updated_post->post_content);
    }
    
    /**
     * Test skipping existing post when update is disabled
     */
    public function test_skip_existing_post() {
        // Create initial post
        $post_id = wp_insert_post(array(
            'post_title' => 'Existing Post',
            'post_content' => 'Existing content',
            'post_status' => 'publish',
            'post_name' => 'existing-slug'
        ));
        
        Test_Helpers::mark_as_test_post($post_id);
        
        // Try to update with update disabled
        $item = array(
            'title' => 'New Title',
            'content' => 'New content',
            'slug' => 'existing-slug'
        );
        
        $options = array('update_existing' => false);
        $result = $this->post_creator->create_or_update_post($item, $options);
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('post_exists', $result->get_error_code());
        
        // Verify original post unchanged
        $post = get_post($post_id);
        $this->assertEquals('Existing Post', $post->post_title);
    }
    
    /**
     * Test processing taxonomies
     */
    public function test_process_taxonomies() {
        // Create test category
        $category_id = wp_insert_term('Test Category', 'category');
        Test_Helpers::mark_as_test_term($category_id['term_id']);
        
        $item = array(
            'title' => 'Post with Taxonomies',
            'content' => 'Content',
            'taxonomies' => array(
                'category' => array('Test Category'),
                'post_tag' => array('tag1', 'tag2')
            )
        );
        
        $result = $this->post_creator->create_or_update_post($item);
        Test_Helpers::mark_as_test_post($result['id']);
        
        // Check categories
        $categories = wp_get_post_categories($result['id'], array('fields' => 'names'));
        $this->assertContains('Test Category', $categories);
        
        // Check tags
        $tags = wp_get_post_tags($result['id'], array('fields' => 'names'));
        $tag_names = wp_list_pluck($tags, 'name');
        $this->assertContains('tag1', $tag_names);
        $this->assertContains('tag2', $tag_names);
    }
    
    /**
     * Test processing meta fields
     */
    public function test_process_meta_fields() {
        $item = array(
            'title' => 'Post with Meta',
            'content' => 'Content',
            'meta' => array(
                'custom_field_1' => 'value1',
                'custom_field_2' => 'value2',
                '_protected_field' => 'protected_value' // Should be skipped
            )
        );
        
        $result = $this->post_creator->create_or_update_post($item);
        Test_Helpers::mark_as_test_post($result['id']);
        
        $this->assertEquals('value1', get_post_meta($result['id'], 'custom_field_1', true));
        $this->assertEquals('value2', get_post_meta($result['id'], 'custom_field_2', true));
        
        // Protected meta should not be set
        $protected_value = get_post_meta($result['id'], '_protected_field', true);
        $this->assertEmpty($protected_value);
    }
    
    /**
     * Test date parsing
     */
    public function test_date_parsing() {
        $item = array(
            'title' => 'Post with Date',
            'content' => 'Content',
            'date' => '2023-01-15 14:30:00',
            'modified' => '2023-01-16 10:00:00'
        );
        
        $result = $this->post_creator->create_or_update_post($item);
        Test_Helpers::mark_as_test_post($result['id']);
        
        $post = get_post($result['id']);
        $this->assertEquals('2023-01-15 14:30:00', $post->post_date);
        $this->assertEquals('2023-01-16 10:00:00', $post->post_modified);
    }
    
    /**
     * Test invalid date handling
     */
    public function test_invalid_date_handling() {
        $item = array(
            'title' => 'Post with Invalid Date',
            'content' => 'Content',
            'date' => 'not a valid date'
        );
        
        $result = $this->post_creator->create_or_update_post($item);
        Test_Helpers::mark_as_test_post($result['id']);
        
        $post = get_post($result['id']);
        // Should fall back to current time
        $this->assertNotEmpty($post->post_date);
        $this->assertNotEquals('not a valid date', $post->post_date);
    }
    
    /**
     * Test field sanitization
     */
    public function test_field_sanitization() {
        $item = array(
            'title' => '<script>alert("xss")</script>Clean Title',
            'content' => '<p>Allowed HTML</p><script>alert("xss")</script>',
            'excerpt' => '<strong>Bold text</strong><script>alert("xss")</script>',
            'status' => 'invalid_status'
        );
        
        $result = $this->post_creator->create_or_update_post($item);
        Test_Helpers::mark_as_test_post($result['id']);
        
        $post = get_post($result['id']);
        
        // Title should be sanitized
        $this->assertEquals('Clean Title', $post->post_title);
        $this->assertStringNotContainsString('<script>', $post->post_title);
        
        // Content should allow some HTML but not scripts
        $this->assertStringContainsString('<p>Allowed HTML</p>', $post->post_content);
        $this->assertStringNotContainsString('<script>', $post->post_content);
        
        // Invalid status should fall back to default
        $this->assertEquals('draft', $post->post_status); // Default status
    }
    
    /**
     * Test error handling for invalid data
     */
    public function test_invalid_data_handling() {
        // Test empty data
        $result = $this->post_creator->create_or_update_post(array());
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('invalid_data', $result->get_error_code());
        
        // Test null data
        $result = $this->post_creator->create_or_update_post(null);
        $this->assertInstanceOf('WP_Error', $result);
        
        // Test string data
        $result = $this->post_creator->create_or_update_post('not an array');
        $this->assertInstanceOf('WP_Error', $result);
    }
    
    /**
     * Test finding existing posts by different criteria
     */
    public function test_find_existing_post() {
        // Create test post
        $post_id = wp_insert_post(array(
            'post_title' => 'Findable Post',
            'post_content' => 'Content',
            'post_status' => 'publish',
            'post_name' => 'findable-post'
        ));
        
        Test_Helpers::mark_as_test_post($post_id);
        
        // Test finding by ID
        $item = array(
            'ID' => $post_id,
            'title' => 'Updated Title'
        );
        
        $result = $this->post_creator->create_or_update_post($item);
        $this->assertTrue($result['updated']);
        $this->assertEquals($post_id, $result['id']);
        
        // Test finding by slug
        $item = array(
            'slug' => 'findable-post',
            'title' => 'Updated Again'
        );
        
        $result = $this->post_creator->create_or_update_post($item);
        $this->assertTrue($result['updated']);
        $this->assertEquals($post_id, $result['id']);
    }
    
    /**
     * Test post with missing required title
     */
    public function test_missing_title_fallback() {
        $item = array(
            'content' => 'Content without title',
            'status' => 'publish'
        );
        
        $result = $this->post_creator->create_or_update_post($item);
        Test_Helpers::mark_as_test_post($result['id']);
        
        $post = get_post($result['id']);
        $this->assertStringContainsString('Untitled', $post->post_title);
        $this->assertNotEmpty($post->post_title);
    }
}