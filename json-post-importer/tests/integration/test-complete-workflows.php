<?php
/**
 * Integration tests for complete import workflows
 */
class Test_Complete_Workflows extends WP_UnitTestCase {
    
    private $admin;
    private $api;
    private $post_creator;
    private $test_user_id;
    
    public function setUp(): void {
        parent::setUp();
        
        $this->admin = new JSON_Post_Importer_Admin('json-post-importer', '1.0.0');
        $this->api = new JSON_Post_Importer_API();
        $this->post_creator = new JSON_Post_Creator();
        $this->test_user_id = Test_Helpers::get_admin_user();
        
        Test_Helpers::set_current_user($this->test_user_id);
        
        // Register REST routes
        do_action('rest_api_init');
    }
    
    public function tearDown(): void {
        Test_Helpers::cleanup_test_data();
        parent::tearDown();
    }
    
    /**
     * Test complete file upload to import workflow
     */
    public function test_complete_file_upload_workflow() {
        // Create test JSON file
        $json_data = Test_Data_Factory::create_sample_json_data();
        $temp_file = Test_Helpers::create_temp_json_file($json_data);
        
        // Simulate file upload
        $_FILES['json_file'] = array(
            'name' => 'test-import.json',
            'type' => 'application/json',
            'tmp_name' => $temp_file,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($temp_file)
        );
        
        // Test file validation
        $validation_result = $this->admin->validate_json_data(file_get_contents($temp_file));
        $this->assertIsArray($validation_result);
        $this->assertCount(2, $validation_result);
        
        // Test field mapping
        $field_mappings = Test_Data_Factory::create_field_mappings()['basic_mapping'];
        $import_settings = Test_Data_Factory::create_import_options()['default_options'];
        
        // Process each item
        $results = array(
            'total' => count($validation_result),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => array()
        );
        
        foreach ($validation_result as $index => $item) {
            $result = $this->post_creator->create_or_update_post($item, $import_settings);
            
            if (is_wp_error($result)) {
                $results['errors'][$index] = $result->get_error_message();
                $results['skipped']++;
            } else {
                if ($result['updated']) {
                    $results['updated']++;
                } else {
                    $results['created']++;
                }
                Test_Helpers::mark_as_test_post($result['id']);
            }
        }
        
        // Verify results
        $this->assertEquals(2, $results['total']);
        $this->assertEquals(2, $results['created']);
        $this->assertEquals(0, $results['updated']);
        $this->assertEquals(0, $results['skipped']);
        $this->assertEmpty($results['errors']);
        
        // Verify posts were created correctly
        $posts = get_posts(array(
            'post_title' => array('Test Post 1', 'Test Post 2'),
            'post_status' => 'any',
            'numberposts' => -1
        ));
        
        $this->assertCount(2, $posts);
        
        // Clean up
        unlink($temp_file);
    }
    
    /**
     * Test complete API import workflow
     */
    public function test_complete_api_workflow() {
        // Prepare JSON data
        $json_data = Test_Data_Factory::create_sample_json_data();
        
        // Create API request
        $request = new WP_REST_Request('POST', '/json-post-importer/v1/import');
        $request->set_header('content-type', 'application/json');
        $request->set_body(json_encode($json_data));
        
        // Test permission check
        $permission_result = $this->api->check_permissions($request);
        $this->assertTrue($permission_result);
        
        // Process import request
        $response = $this->api->handle_import_request($request);
        
        // Verify response
        $this->assertInstanceOf('WP_REST_Response', $response);
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals(2, $data['data']['total']);
        $this->assertEquals(2, $data['data']['created']);
        
        // Verify posts were created
        $posts = get_posts(array(
            'post_title' => array('Test Post 1', 'Test Post 2'),
            'post_status' => 'any',
            'numberposts' => -1
        ));
        
        $this->assertCount(2, $posts);
        
        // Verify post content
        foreach ($posts as $post) {
            Test_Helpers::mark_as_test_post($post->ID);
            
            if ($post->post_title === 'Test Post 1') {
                $this->assertEquals('This is the content for test post 1.', $post->post_content);
                $this->assertEquals('Test excerpt 1', $post->post_excerpt);
                $this->assertEquals('publish', $post->post_status);
                
                // Check taxonomies
                Test_Helpers::assert_post_terms($post->ID, 'category', array('Test Category'));
                Test_Helpers::assert_post_terms($post->ID, 'post_tag', array('test', 'sample'));
                
                // Check meta fields
                Test_Helpers::assert_post_meta($post->ID, array(
                    'custom_field_1' => 'Custom value 1',
                    'custom_field_2' => 'Custom value 2'
                ));
            }
        }
    }
    
    /**
     * Test batch import workflow with large dataset
     */
    public function test_batch_import_workflow() {
        // Create large dataset
        $large_dataset = array();
        for ($i = 1; $i <= 50; $i++) {
            $large_dataset[] = array(
                'title' => "Batch Post {$i}",
                'content' => "Content for batch post {$i}",
                'status' => 'publish',
                'meta' => array(
                    'batch_number' => $i,
                    'batch_test' => 'true'
                )
            );
        }
        
        // Process in batches
        $batch_size = 10;
        $batches = array_chunk($large_dataset, $batch_size);
        $total_results = array(
            'total' => count($large_dataset),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => array()
        );
        
        foreach ($batches as $batch_index => $batch) {
            foreach ($batch as $item_index => $item) {
                $result = $this->post_creator->create_or_update_post($item);
                
                if (is_wp_error($result)) {
                    $total_results['errors'][] = $result->get_error_message();
                    $total_results['skipped']++;
                } else {
                    if ($result['updated']) {
                        $total_results['updated']++;
                    } else {
                        $total_results['created']++;
                    }
                    Test_Helpers::mark_as_test_post($result['id']);
                }
            }
        }
        
        // Verify results
        $this->assertEquals(50, $total_results['total']);
        $this->assertEquals(50, $total_results['created']);
        $this->assertEquals(0, $total_results['updated']);
        $this->assertEquals(0, $total_results['skipped']);
        $this->assertEmpty($total_results['errors']);
        
        // Verify all posts were created
        $created_posts = get_posts(array(
            'meta_key' => 'batch_test',
            'meta_value' => 'true',
            'post_status' => 'any',
            'numberposts' => -1
        ));
        
        $this->assertCount(50, $created_posts);
    }
    
    /**
     * Test import workflow with media handling
     */
    public function test_import_workflow_with_media() {
        // Mock HTTP response for image download
        Test_Helpers::mock_http_response('https://example.com/test-image.jpg', array(
            'response' => array('code' => 200),
            'body' => file_get_contents(ABSPATH . 'wp-admin/images/wordpress-logo.svg')
        ));
        
        $json_data = array(
            array(
                'title' => 'Post with Media',
                'content' => 'Content with featured image',
                'featured_image' => 'https://example.com/test-image.jpg',
                'status' => 'publish'
            )
        );
        
        $options = array(
            'skip_thumbnail' => false,
            'import_attachments' => true
        );
        
        $result = $this->post_creator->create_or_update_post($json_data[0], $options);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        
        Test_Helpers::mark_as_test_post($result['id']);
        
        // Verify post was created
        $post = get_post($result['id']);
        $this->assertEquals('Post with Media', $post->post_title);
        
        // Note: Featured image testing would require more complex setup
        // This test verifies the workflow completes without errors
    }
    
    /**
     * Test import workflow with custom field mapping
     */
    public function test_import_workflow_custom_mapping() {
        $json_data = array(
            array(
                'custom_title' => 'Mapped Title',
                'body_text' => 'Mapped content',
                'summary' => 'Mapped excerpt',
                'publish_state' => 'publish',
                'custom_data' => array(
                    'field1' => 'value1',
                    'field2' => 'value2'
                )
            )
        );
        
        // Create custom mapping
        $mapped_item = array(
            'title' => $json_data[0]['custom_title'],
            'content' => $json_data[0]['body_text'],
            'excerpt' => $json_data[0]['summary'],
            'status' => $json_data[0]['publish_state'],
            'meta' => $json_data[0]['custom_data']
        );
        
        $result = $this->post_creator->create_or_update_post($mapped_item);
        
        $this->assertIsArray($result);
        Test_Helpers::mark_as_test_post($result['id']);
        
        // Verify mapped data
        $post = get_post($result['id']);
        $this->assertEquals('Mapped Title', $post->post_title);
        $this->assertEquals('Mapped content', $post->post_content);
        $this->assertEquals('Mapped excerpt', $post->post_excerpt);
        $this->assertEquals('publish', $post->post_status);
        
        // Verify custom fields
        Test_Helpers::assert_post_meta($result['id'], array(
            'field1' => 'value1',
            'field2' => 'value2'
        ));
    }
    
    /**
     * Test error handling workflow
     */
    public function test_error_handling_workflow() {
        $problematic_data = array(
            array(
                'title' => 'Valid Post',
                'content' => 'Valid content'
            ),
            array(
                // Missing title
                'content' => 'Content without title'
            ),
            array(
                'title' => 'Another Valid Post',
                'content' => 'Another valid content',
                'status' => 'invalid_status' // Invalid status
            )
        );
        
        $results = array(
            'total' => count($problematic_data),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => array()
        );
        
        foreach ($problematic_data as $index => $item) {
            $result = $this->post_creator->create_or_update_post($item);
            
            if (is_wp_error($result)) {
                $results['errors'][$index] = $result->get_error_message();
                $results['skipped']++;
            } else {
                if ($result['updated']) {
                    $results['updated']++;
                } else {
                    $results['created']++;
                }
                Test_Helpers::mark_as_test_post($result['id']);
            }
        }
        
        // Should have created 2 posts (items 0 and 2), item 1 should have been handled gracefully
        $this->assertEquals(3, $results['total']);
        $this->assertEquals(3, $results['created']); // All should be created with fallbacks
        $this->assertEquals(0, $results['skipped']);
        
        // Verify posts were created with fallbacks
        $posts = get_posts(array(
            'post_title' => array('Valid Post', 'Another Valid Post'),
            'post_status' => 'any',
            'numberposts' => -1
        ));
        
        $this->assertCount(2, $posts);
        
        // Check for the post with missing title (should have fallback title)
        $untitled_posts = get_posts(array(
            'meta_key' => '_test_post',
            'meta_value' => '1',
            'post_status' => 'any',
            'numberposts' => -1
        ));
        
        $found_untitled = false;
        foreach ($untitled_posts as $post) {
            if (strpos($post->post_title, 'Untitled') !== false) {
                $found_untitled = true;
                break;
            }
        }
        $this->assertTrue($found_untitled, 'Should have created post with fallback title');
    }
    
    /**
     * Test update existing posts workflow
     */
    public function test_update_existing_workflow() {
        // Create initial posts
        $initial_post_id = wp_insert_post(array(
            'post_title' => 'Original Title',
            'post_content' => 'Original content',
            'post_status' => 'draft',
            'post_name' => 'original-slug'
        ));
        
        Test_Helpers::mark_as_test_post($initial_post_id);
        
        // Update data
        $update_data = array(
            array(
                'title' => 'Updated Title',
                'content' => 'Updated content',
                'status' => 'publish',
                'slug' => 'original-slug'
            )
        );
        
        $options = array('update_existing' => true);
        $result = $this->post_creator->create_or_update_post($update_data[0], $options);
        
        $this->assertIsArray($result);
        $this->assertTrue($result['updated']);
        $this->assertEquals($initial_post_id, $result['id']);
        
        // Verify update
        $updated_post = get_post($initial_post_id);
        $this->assertEquals('Updated Title', $updated_post->post_title);
        $this->assertEquals('Updated content', $updated_post->post_content);
        $this->assertEquals('publish', $updated_post->post_status);
    }
}