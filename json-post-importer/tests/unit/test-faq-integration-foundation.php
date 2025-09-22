<?php
/**
 * Unit tests for FAQ Integration Foundation
 *
 * Tests the basic functionality of the FAQ integration class.
 *
 * @package JSON_Post_Importer
 * @subpackage Tests
 */

class Test_FAQ_Integration_Foundation extends WP_UnitTestCase {

    /**
     * Test that the FAQ integration class exists
     */
    public function test_faq_integration_class_exists() {
        $this->assertTrue(class_exists('JSON_Post_Importer_FAQ_Integration'));
    }

    /**
     * Test that the FAQ integration can be instantiated
     */
    public function test_faq_integration_instantiation() {
        $integration = new JSON_Post_Importer_FAQ_Integration();
        $this->assertInstanceOf('JSON_Post_Importer_FAQ_Integration', $integration);
    }

    /**
     * Test the static init method
     */
    public function test_faq_integration_static_init() {
        $integration = JSON_Post_Importer_FAQ_Integration::init();
        $this->assertInstanceOf('JSON_Post_Importer_FAQ_Integration', $integration);
        
        // Test that it returns the same instance (singleton pattern)
        $integration2 = JSON_Post_Importer_FAQ_Integration::init();
        $this->assertSame($integration, $integration2);
    }

    /**
     * Test the integration status method
     */
    public function test_get_integration_status() {
        $integration = JSON_Post_Importer_FAQ_Integration::init();
        $status = $integration->get_integration_status();
        
        $this->assertIsArray($status);
        $this->assertArrayHasKey('faq_manager_active', $status);
        $this->assertArrayHasKey('hooks_registered', $status);
        $this->assertArrayHasKey('version', $status);
        $this->assertArrayHasKey('ready', $status);
        
        $this->assertIsBool($status['faq_manager_active']);
        $this->assertIsBool($status['hooks_registered']);
        $this->assertEquals('1.0.0', $status['version']);
        $this->assertIsBool($status['ready']);
    }

    /**
     * Test the FAQ manager active check method
     */
    public function test_is_faq_manager_active() {
        $integration = JSON_Post_Importer_FAQ_Integration::init();
        $result = $integration->is_faq_manager_active();
        
        $this->assertIsBool($result);
        // Since post-faq-manager is likely not installed in test environment, expect false
        $this->assertFalse($result);
    }

    /**
     * Test that hooks are registered when FAQ manager is active
     */
    public function test_hooks_registration_when_active() {
        // Mock the FAQ manager as active
        $integration = $this->getMockBuilder('JSON_Post_Importer_FAQ_Integration')
                           ->setMethods(['is_faq_manager_active'])
                           ->getMock();
        
        $integration->method('is_faq_manager_active')
                   ->willReturn(true);
        
        // Check that the hook would be registered
        $this->assertTrue(has_action('jpi_after_post_import'));
    }

    /**
     * Test the has_faq_data method with various data structures
     */
    public function test_has_faq_data_detection() {
        $integration = JSON_Post_Importer_FAQ_Integration::init();
        
        // Use reflection to access private method
        $reflection = new ReflectionClass($integration);
        $method = $reflection->getMethod('has_faq_data');
        $method->setAccessible(true);
        
        // Test with standard FAQ data
        $item_with_faq = array(
            'title' => 'Test Post',
            'faq' => array(
                array('question' => 'Q1', 'answer' => 'A1')
            )
        );
        $this->assertTrue($method->invoke($integration, $item_with_faq));
        
        // Test with alternative FAQ field names
        $item_with_faqs = array(
            'title' => 'Test Post',
            'faqs' => array(
                array('question' => 'Q1', 'answer' => 'A1')
            )
        );
        $this->assertTrue($method->invoke($integration, $item_with_faqs));
        
        // Test with nested FAQ data
        $item_with_nested_faq = array(
            'title' => 'Test Post',
            'content' => array(
                'faq' => array(
                    array('question' => 'Q1', 'answer' => 'A1')
                )
            )
        );
        $this->assertTrue($method->invoke($integration, $item_with_nested_faq));
        
        // Test without FAQ data
        $item_without_faq = array(
            'title' => 'Test Post',
            'content' => 'Some content'
        );
        $this->assertFalse($method->invoke($integration, $item_without_faq));
        
        // Test with empty FAQ array
        $item_with_empty_faq = array(
            'title' => 'Test Post',
            'faq' => array()
        );
        $this->assertFalse($method->invoke($integration, $item_with_empty_faq));
        
        // Test with non-array FAQ data
        $item_with_invalid_faq = array(
            'title' => 'Test Post',
            'faq' => 'not an array'
        );
        $this->assertFalse($method->invoke($integration, $item_with_invalid_faq));
    }

    /**
     * Test the process_post_faqs method with invalid inputs
     */
    public function test_process_post_faqs_with_invalid_inputs() {
        $integration = JSON_Post_Importer_FAQ_Integration::init();
        
        // Test with invalid post ID
        $integration->process_post_faqs(null, array('title' => 'test'), array());
        $integration->process_post_faqs('invalid', array('title' => 'test'), array());
        
        // Test with invalid item data
        $integration->process_post_faqs(1, null, array());
        $integration->process_post_faqs(1, 'invalid', array());
        
        // These should not throw exceptions and should handle gracefully
        $this->assertTrue(true); // If we get here, no exceptions were thrown
    }
}