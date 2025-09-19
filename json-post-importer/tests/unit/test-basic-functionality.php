<?php
/**
 * Basic functionality tests to verify test setup
 */
class Test_Basic_Functionality extends WP_UnitTestCase {
    
    public function setUp(): void {
        parent::setUp();
    }
    
    public function tearDown(): void {
        parent::tearDown();
    }
    
    /**
     * Test that WordPress is loaded
     */
    public function test_wordpress_loaded() {
        $this->assertTrue(function_exists('wp_insert_post'));
        $this->assertTrue(function_exists('get_post'));
        $this->assertTrue(defined('ABSPATH'));
    }
    
    /**
     * Test that plugin classes are loaded
     */
    public function test_plugin_classes_loaded() {
        $this->assertTrue(class_exists('JSON_Post_Creator'));
        $this->assertTrue(class_exists('JSON_Post_Importer_API'));
        $this->assertTrue(class_exists('JSON_Post_Importer_Admin'));
        $this->assertTrue(class_exists('JSON_Post_Importer_Logger'));
    }
    
    /**
     * Test that test utilities are working
     */
    public function test_utilities_working() {
        $this->assertTrue(class_exists('Test_Data_Factory'));
        $this->assertTrue(class_exists('Test_Helpers'));
        
        // Test data factory
        $sample_data = Test_Data_Factory::create_sample_json_data();
        $this->assertIsArray($sample_data);
        $this->assertCount(2, $sample_data);
        
        // Test field mappings
        $mappings = Test_Data_Factory::create_field_mappings();
        $this->assertIsArray($mappings);
        $this->assertArrayHasKey('basic_mapping', $mappings);
    }
    
    /**
     * Test basic post creation
     */
    public function test_basic_post_creation() {
        $post_id = wp_insert_post(array(
            'post_title' => 'Test Post',
            'post_content' => 'Test content',
            'post_status' => 'publish'
        ));
        
        $this->assertIsInt($post_id);
        $this->assertGreaterThan(0, $post_id);
        
        $post = get_post($post_id);
        $this->assertEquals('Test Post', $post->post_title);
        $this->assertEquals('Test content', $post->post_content);
        
        // Clean up
        wp_delete_post($post_id, true);
    }
    
    /**
     * Test JSON Post Creator instantiation
     */
    public function test_json_post_creator_instantiation() {
        $creator = new JSON_Post_Creator();
        $this->assertInstanceOf('JSON_Post_Creator', $creator);
    }
    
    /**
     * Test API instantiation
     */
    public function test_api_instantiation() {
        $api = new JSON_Post_Importer_API();
        $this->assertInstanceOf('JSON_Post_Importer_API', $api);
    }
    
    /**
     * Test admin instantiation
     */
    public function test_admin_instantiation() {
        $admin = new JSON_Post_Importer_Admin('json-post-importer', '1.0.0');
        $this->assertInstanceOf('JSON_Post_Importer_Admin', $admin);
    }
}