<?php
/**
 * Unit tests for API endpoints and responses
 */
class Test_API_Endpoints extends WP_UnitTestCase {
    
    private $api;
    private $test_user_id;
    private $admin_user_id;
    
    public function setUp(): void {
        parent::setUp();
        
        $this->api = new JSON_Post_Importer_API();
        $this->admin_user_id = Test_Helpers::get_admin_user();
        $this->test_user_id = Test_Data_Factory::create_test_user();
        
        // Register REST routes
        do_action('rest_api_init');
    }
    
    public function tearDown(): void {
        Test_Helpers::cleanup_test_data();
        parent::tearDown();
    }
    
    /**
     * Test API route registration
     */
    public function test_api_routes_registered() {
        $routes = rest_get_server()->get_routes();
        $this->assertArrayHasKey('/json-post-importer/v1/import', $routes);
    }
    
    /**
     * Test permission checking for authenticated admin user
     */
    public function test_permission_check_admin_user() {
        Test_Helpers::set_current_user($this->admin_user_id);
        
        $request = new WP_REST_Request('POST', '/json-post-importer/v1/import');
        $result = $this->api->check_permissions($request);
        
        $this->assertTrue($result);
    }
    
    /**
     * Test permission checking for non-admin user
     */
    public function test_permission_check_non_admin_user() {
        Test_Helpers::set_current_user($this->test_user_id);
        
        $request = new WP_REST_Request('POST', '/json-post-importer/v1/import');
        $result = $this->api->check_permissions($request);
        
        $this->assertTrue($result); // Editor should have edit_posts capability
    }
    
    /**
     * Test permission checking for unauthenticated user
     */
    public function test_permission_check_unauthenticated() {
        wp_set_current_user(0); // No user
        
        $request = new WP_REST_Request('POST', '/json-post-importer/v1/import');
        $result = $this->api->check_permissions($request);
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('rest_forbidden', $result->get_error_code());
    }
    
    /**
     * Test successful import request
     */
    public function test_successful_import_request() {
        Test_Helpers::set_current_user($this->admin_user_id);
        
        $json_data = Test_Data_Factory::create_sample_json_data();
        
        $request = new WP_REST_Request('POST', '/json-post-importer/v1/import');
        $request->set_header('content-type', 'application/json');
        $request->set_body(json_encode($json_data));
        
        $response = $this->api->handle_import_request($request);
        
        $this->assertInstanceOf('WP_REST_Response', $response);
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('total', $data['data']);
        $this->assertArrayHasKey('created', $data['data']);
        $this->assertEquals(2, $data['data']['total']);
        $this->assertEquals(2, $data['data']['created']);
        
        // Clean up created posts
        $posts = get_posts(array(
            'post_title' => array('Test Post 1', 'Test Post 2'),
            'post_status' => 'any',
            'numberposts' => -1
        ));
        
        foreach ($posts as $post) {
            Test_Helpers::mark_as_test_post($post->ID);
        }
    }
    
    /**
     * Test import request with empty data
     */
    public function test_import_request_empty_data() {
        Test_Helpers::set_current_user($this->admin_user_id);
        
        $request = new WP_REST_Request('POST', '/json-post-importer/v1/import');
        $request->set_header('content-type', 'application/json');
        $request->set_body('');
        
        $response = $this->api->handle_import_request($request);
        
        $this->assertInstanceOf('WP_Error', $response);
        $this->assertEquals('no_data', $response->get_error_code());
    }
    
    /**
     * Test import request with invalid JSON
     */
    public function test_import_request_invalid_json() {
        Test_Helpers::set_current_user($this->admin_user_id);
        
        $request = new WP_REST_Request('POST', '/json-post-importer/v1/import');
        $request->set_header('content-type', 'application/json');
        $request->set_body('invalid json');
        
        $response = $this->api->handle_import_request($request);
        
        $this->assertInstanceOf('WP_Error', $response);
    }
    
    /**
     * Test processing JSON data with valid structure
     */
    public function test_process_json_data_valid() {
        $json_data = Test_Data_Factory::create_sample_json_data();
        
        $result = $this->api->process_json_data($json_data);
        
        $this->assertIsArray($result);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals(2, $result['created']);
        $this->assertEquals(0, $result['updated']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEmpty($result['errors']);
        
        // Clean up created posts
        $posts = get_posts(array(
            'post_title' => array('Test Post 1', 'Test Post 2'),
            'post_status' => 'any',
            'numberposts' => -1
        ));
        
        foreach ($posts as $post) {
            Test_Helpers::mark_as_test_post($post->ID);
        }
    }
    
    /**
     * Test processing JSON data with invalid structure
     */
    public function test_process_json_data_invalid_structure() {
        $invalid_data = 'not an array';
        
        $result = $this->api->process_json_data($invalid_data);
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('invalid_data', $result->get_error_code());
    }
    
    /**
     * Test processing JSON data with mixed valid and invalid items
     */
    public function test_process_json_data_mixed_validity() {
        $mixed_data = array(
            array(
                'title' => 'Valid Post 1',
                'content' => 'Valid content 1'
            ),
            array(
                // Missing title - should cause error
                'content' => 'Content without title'
            ),
            array(
                'title' => 'Valid Post 2',
                'content' => 'Valid content 2'
            )
        );
        
        $result = $this->api->process_json_data($mixed_data);
        
        $this->assertIsArray($result);
        $this->assertEquals(3, $result['total']);
        $this->assertEquals(2, $result['created']);
        $this->assertEquals(0, $result['updated']);
        $this->assertEquals(1, $result['skipped']);
        $this->assertCount(1, $result['errors']);
        
        // Clean up created posts
        $posts = get_posts(array(
            'post_title' => array('Valid Post 1', 'Valid Post 2'),
            'post_status' => 'any',
            'numberposts' => -1
        ));
        
        foreach ($posts as $post) {
            Test_Helpers::mark_as_test_post($post->ID);
        }
    }
    
    /**
     * Test JSON structure validation with valid array
     */
    public function test_validate_json_structure_valid_array() {
        $valid_data = Test_Data_Factory::create_sample_json_data();
        
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->api);
        $method = $reflection->getMethod('validate_json_structure');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->api, $valid_data);
        
        $this->assertTrue($result);
    }
    
    /**
     * Test JSON structure validation with invalid format
     */
    public function test_validate_json_structure_invalid_format() {
        $invalid_data = 'not an array';
        
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->api);
        $method = $reflection->getMethod('validate_json_structure');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->api, $invalid_data);
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('invalid_format', $result->get_error_code());
    }
    
    /**
     * Test JSON structure validation with empty data
     */
    public function test_validate_json_structure_empty_data() {
        $empty_data = array();
        
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->api);
        $method = $reflection->getMethod('validate_json_structure');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->api, $empty_data);
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('empty_data', $result->get_error_code());
    }
    
    /**
     * Test API response format
     */
    public function test_api_response_format() {
        Test_Helpers::set_current_user($this->admin_user_id);
        
        $json_data = array(
            array(
                'title' => 'API Test Post',
                'content' => 'API test content'
            )
        );
        
        $request = new WP_REST_Request('POST', '/json-post-importer/v1/import');
        $request->set_header('content-type', 'application/json');
        $request->set_body(json_encode($json_data));
        
        $response = $this->api->handle_import_request($request);
        
        $this->assertInstanceOf('WP_REST_Response', $response);
        
        $data = $response->get_data();
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('data', $data);
        
        $result_data = $data['data'];
        $this->assertArrayHasKey('total', $result_data);
        $this->assertArrayHasKey('created', $result_data);
        $this->assertArrayHasKey('updated', $result_data);
        $this->assertArrayHasKey('skipped', $result_data);
        $this->assertArrayHasKey('errors', $result_data);
        
        // Clean up
        $posts = get_posts(array(
            'post_title' => 'API Test Post',
            'post_status' => 'any',
            'numberposts' => -1
        ));
        
        foreach ($posts as $post) {
            Test_Helpers::mark_as_test_post($post->ID);
        }
    }
    
    /**
     * Test API error response format
     */
    public function test_api_error_response_format() {
        Test_Helpers::set_current_user($this->admin_user_id);
        
        $request = new WP_REST_Request('POST', '/json-post-importer/v1/import');
        $request->set_header('content-type', 'application/json');
        $request->set_body(''); // Empty body should cause error
        
        $response = $this->api->handle_import_request($request);
        
        $this->assertInstanceOf('WP_Error', $response);
        $this->assertNotEmpty($response->get_error_message());
        $this->assertIsArray($response->get_error_data());
        $this->assertArrayHasKey('status', $response->get_error_data());
    }
    
    /**
     * Test processing with custom post type
     */
    public function test_process_custom_post_type() {
        // Register a custom post type for testing
        register_post_type('test_post_type', array(
            'public' => true,
            'supports' => array('title', 'editor', 'custom-fields')
        ));
        
        $json_data = array(
            array(
                'title' => 'Custom Post Type Test',
                'content' => 'Custom post type content',
                'post_type' => 'test_post_type'
            )
        );
        
        $result = $this->api->process_json_data($json_data);
        
        $this->assertIsArray($result);
        $this->assertEquals(1, $result['created']);
        
        // Verify post was created with correct post type
        $posts = get_posts(array(
            'post_type' => 'test_post_type',
            'post_title' => 'Custom Post Type Test',
            'numberposts' => 1
        ));
        
        $this->assertCount(1, $posts);
        $this->assertEquals('test_post_type', $posts[0]->post_type);
        
        Test_Helpers::mark_as_test_post($posts[0]->ID);
    }
}