<?php
/**
 * Unit tests for field mapping and validation logic
 */
class Test_Field_Mapping_Validation extends WP_UnitTestCase {
    
    private $admin;
    private $test_user_id;
    
    public function setUp(): void {
        parent::setUp();
        
        $this->admin = new JSON_Post_Importer_Admin('json-post-importer', '1.0.0');
        $this->test_user_id = Test_Helpers::get_admin_user();
        Test_Helpers::set_current_user($this->test_user_id);
    }
    
    public function tearDown(): void {
        Test_Helpers::cleanup_test_data();
        parent::tearDown();
    }
    
    /**
     * Test JSON validation with valid data
     */
    public function test_validate_valid_json() {
        $valid_json = json_encode(Test_Data_Factory::create_sample_json_data());
        $result = $this->admin->validate_json_data($valid_json);
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('Test Post 1', $result[0]['title']);
    }
    
    /**
     * Test JSON validation with empty data
     */
    public function test_validate_empty_json() {
        $result = $this->admin->validate_json_data('');
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('empty_json', $result->get_error_code());
    }
    
    /**
     * Test JSON validation with malformed JSON
     */
    public function test_validate_malformed_json() {
        $malformed_json = '{"title": "Test", "content": "Content"'; // Missing closing brace
        $result = $this->admin->validate_json_data($malformed_json);
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('invalid_json', $result->get_error_code());
    }
    
    /**
     * Test JSON validation with non-array data
     */
    public function test_validate_non_array_json() {
        $non_array_json = json_encode('just a string');
        $result = $this->admin->validate_json_data($non_array_json);
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('invalid_structure', $result->get_error_code());
    }
    
    /**
     * Test field mapping validation with valid mapping
     */
    public function test_validate_field_mapping_valid() {
        $field_mappings = Test_Data_Factory::create_field_mappings()['basic_mapping'];
        
        // Simulate the validation that would happen in the admin
        $this->assertArrayHasKey('post_title', $field_mappings);
        $this->assertNotEmpty($field_mappings['post_title']);
        
        // Test that all required fields are present
        $required_fields = array('post_title');
        foreach ($required_fields as $field) {
            $this->assertArrayHasKey($field, $field_mappings);
            $this->assertNotEmpty($field_mappings[$field]);
        }
    }
    
    /**
     * Test field mapping validation with missing required fields
     */
    public function test_validate_field_mapping_missing_required() {
        $field_mappings = Test_Data_Factory::create_field_mappings()['incomplete_mapping'];
        
        // Should fail validation because post_title is missing
        $this->assertArrayNotHasKey('post_title', $field_mappings);
    }
    
    /**
     * Test processing single item with field mappings
     */
    public function test_process_single_item_with_mapping() {
        $item = array(
            'custom_title' => 'Test Title',
            'body_text' => 'Test Content',
            'summary' => 'Test Excerpt',
            'publish_status' => 'publish'
        );
        
        $field_mappings = array(
            'post_title' => 'custom_title',
            'post_content' => 'body_text',
            'post_excerpt' => 'summary',
            'post_status' => 'publish_status'
        );
        
        $import_settings = array(
            'post_type' => 'post',
            'default_author' => $this->test_user_id
        );
        
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->admin);
        $method = $reflection->getMethod('process_single_item');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->admin, $item, $field_mappings, $import_settings, 0);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        
        $post = get_post($result['id']);
        $this->assertEquals('Test Title', $post->post_title);
        $this->assertEquals('Test Content', $post->post_content);
        $this->assertEquals('Test Excerpt', $post->post_excerpt);
        $this->assertEquals('publish', $post->post_status);
        
        Test_Helpers::mark_as_test_post($result['id']);
    }
    
    /**
     * Test processing item with custom fields mapping
     */
    public function test_process_item_with_custom_fields() {
        $item = array(
            'title' => 'Post with Custom Fields',
            'content' => 'Content',
            'cf1' => 'Custom Value 1',
            'cf2' => 'Custom Value 2'
        );
        
        $field_mappings = array(
            'post_title' => 'title',
            'post_content' => 'content'
        );
        
        $import_settings = array(
            'post_type' => 'post',
            'default_author' => $this->test_user_id,
            'custom_fields' => array(
                'custom_field_1' => 'cf1',
                'custom_field_2' => 'cf2'
            )
        );
        
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->admin);
        $method = $reflection->getMethod('process_single_item');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->admin, $item, $field_mappings, $import_settings, 0);
        Test_Helpers::mark_as_test_post($result['id']);
        
        // Check custom fields were set
        $this->assertEquals('Custom Value 1', get_post_meta($result['id'], 'custom_field_1', true));
        $this->assertEquals('Custom Value 2', get_post_meta($result['id'], 'custom_field_2', true));
    }
    
    /**
     * Test processing item with taxonomy mapping
     */
    public function test_process_item_with_taxonomy_mapping() {
        // Create test category
        $category_id = wp_insert_term('Test Category', 'category');
        Test_Helpers::mark_as_test_term($category_id['term_id']);
        
        $item = array(
            'title' => 'Post with Taxonomies',
            'content' => 'Content',
            'categories' => 'Test Category',
            'tags' => 'tag1,tag2'
        );
        
        $field_mappings = array(
            'post_title' => 'title',
            'post_content' => 'content'
        );
        
        $import_settings = array(
            'post_type' => 'post',
            'default_author' => $this->test_user_id,
            'taxonomies' => array(
                'category' => 'categories',
                'post_tag' => 'tags'
            )
        );
        
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->admin);
        $method = $reflection->getMethod('process_single_item');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->admin, $item, $field_mappings, $import_settings, 0);
        Test_Helpers::mark_as_test_post($result['id']);
        
        // Check taxonomies were set
        Test_Helpers::assert_post_terms($result['id'], 'category', array('Test Category'));
        Test_Helpers::assert_post_terms($result['id'], 'post_tag', array('tag1', 'tag2'));
    }
    
    /**
     * Test validation of nested JSON structures
     */
    public function test_validate_nested_json_structure() {
        $nested_json = json_encode(array(
            array(
                'title' => 'Post 1',
                'content' => 'Content 1',
                'meta' => array(
                    'field1' => 'value1',
                    'field2' => array(
                        'subfield1' => 'subvalue1',
                        'subfield2' => 'subvalue2'
                    )
                ),
                'taxonomies' => array(
                    'category' => array('Cat1', 'Cat2'),
                    'post_tag' => array('tag1', 'tag2')
                )
            )
        ));
        
        $result = $this->admin->validate_json_data($nested_json);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('meta', $result[0]);
        $this->assertArrayHasKey('taxonomies', $result[0]);
        $this->assertIsArray($result[0]['meta']['field2']);
    }
    
    /**
     * Test handling of different data types in JSON
     */
    public function test_handle_different_data_types() {
        $mixed_data = array(
            'title' => 'Mixed Data Post',
            'content' => 'Content',
            'number_field' => 123,
            'boolean_field' => true,
            'null_field' => null,
            'array_field' => array('item1', 'item2'),
            'object_field' => array('key' => 'value')
        );
        
        $json_string = json_encode(array($mixed_data));
        $result = $this->admin->validate_json_data($json_string);
        
        $this->assertIsArray($result);
        $this->assertEquals(123, $result[0]['number_field']);
        $this->assertTrue($result[0]['boolean_field']);
        $this->assertNull($result[0]['null_field']);
        $this->assertIsArray($result[0]['array_field']);
        $this->assertIsArray($result[0]['object_field']);
    }
    
    /**
     * Test field mapping with empty or null values
     */
    public function test_field_mapping_with_empty_values() {
        $item = array(
            'title' => 'Test Post',
            'content' => '',
            'excerpt' => null,
            'status' => 'publish'
        );
        
        $field_mappings = array(
            'post_title' => 'title',
            'post_content' => 'content',
            'post_excerpt' => 'excerpt',
            'post_status' => 'status'
        );
        
        $import_settings = array(
            'post_type' => 'post',
            'default_author' => $this->test_user_id
        );
        
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->admin);
        $method = $reflection->getMethod('process_single_item');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->admin, $item, $field_mappings, $import_settings, 0);
        Test_Helpers::mark_as_test_post($result['id']);
        
        $post = get_post($result['id']);
        $this->assertEquals('Test Post', $post->post_title);
        $this->assertEquals('', $post->post_content);
        $this->assertEquals('', $post->post_excerpt);
    }
    
    /**
     * Test validation of field mapping configuration
     */
    public function test_validate_mapping_configuration() {
        $valid_mappings = Test_Data_Factory::create_field_mappings();
        
        // Test basic mapping validation
        $basic = $valid_mappings['basic_mapping'];
        $this->assertArrayHasKey('post_title', $basic);
        $this->assertNotEmpty($basic['post_title']);
        
        // Test incomplete mapping detection
        $incomplete = $valid_mappings['incomplete_mapping'];
        $this->assertArrayNotHasKey('post_title', $incomplete);
        
        // Test mapping with taxonomies
        $with_taxonomies = $valid_mappings['with_taxonomies'];
        $this->assertArrayHasKey('taxonomies', $with_taxonomies);
        $this->assertIsArray($with_taxonomies['taxonomies']);
        
        // Test mapping with meta fields
        $with_meta = $valid_mappings['with_meta'];
        $this->assertArrayHasKey('meta', $with_meta);
        $this->assertIsArray($with_meta['meta']);
    }
}