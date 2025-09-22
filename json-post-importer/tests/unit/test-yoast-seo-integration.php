<?php
/**
 * Test Yoast SEO Integration
 */

class Test_Yoast_SEO_Integration extends WP_UnitTestCase {

    private $yoast_seo;

    public function setUp(): void {
        parent::setUp();
        
        // Include the Yoast SEO class
        require_once dirname(dirname(__DIR__)) . '/includes/class-json-post-importer-logger.php';
        require_once dirname(dirname(__DIR__)) . '/includes/class-json-post-importer-yoast-seo.php';
        
        $this->yoast_seo = new JSON_Post_Importer_Yoast_SEO();
    }

    /**
     * Test Yoast SEO class initialization
     */
    public function test_yoast_seo_initialization() {
        $this->assertInstanceOf('JSON_Post_Importer_Yoast_SEO', $this->yoast_seo);
    }

    /**
     * Test getting Yoast SEO fields
     */
    public function test_get_yoast_fields() {
        $fields = $this->yoast_seo->get_yoast_fields();
        
        $this->assertIsArray($fields);
        $this->assertArrayHasKey('_yoast_wpseo_title', $fields);
        $this->assertArrayHasKey('_yoast_wpseo_metadesc', $fields);
        $this->assertArrayHasKey('_yoast_wpseo_focuskw', $fields);
    }

    /**
     * Test field patterns
     */
    public function test_get_field_patterns() {
        $patterns = $this->yoast_seo->get_field_patterns();
        
        $this->assertIsArray($patterns);
        $this->assertArrayHasKey('_yoast_wpseo_title', $patterns);
        $this->assertContains('seo_title', $patterns['_yoast_wpseo_title']);
        $this->assertContains('meta_title', $patterns['_yoast_wpseo_title']);
    }

    /**
     * Test auto-detection of Yoast fields
     */
    public function test_auto_detect_yoast_fields() {
        $sample_data = array(
            array(
                'content' => array(
                    'title' => 'Test Post',
                    'seo_title' => 'SEO Optimized Title',
                    'meta_description' => 'This is a meta description',
                    'focus_keyword' => 'test keyword',
                    'og_title' => 'Facebook Title'
                )
            )
        );

        $detected = $this->yoast_seo->auto_detect_yoast_fields($sample_data, 'content');
        
        $this->assertIsArray($detected);
        $this->assertArrayHasKey('_yoast_wpseo_title', $detected);
        $this->assertEquals('seo_title', $detected['_yoast_wpseo_title']);
        $this->assertArrayHasKey('_yoast_wpseo_metadesc', $detected);
        $this->assertEquals('meta_description', $detected['_yoast_wpseo_metadesc']);
    }

    /**
     * Test Yoast field validation
     */
    public function test_validate_yoast_fields() {
        $yoast_data = array(
            '_yoast_wpseo_title' => 'Valid SEO Title',
            '_yoast_wpseo_metadesc' => 'This is a valid meta description that is not too long',
            '_yoast_wpseo_focuskw' => 'test keyword'
        );

        $validation = $this->yoast_seo->validate_yoast_fields($yoast_data);
        
        $this->assertIsArray($validation);
        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['errors']);
        $this->assertArrayHasKey('processed_data', $validation);
    }

    /**
     * Test Yoast field validation with errors
     */
    public function test_validate_yoast_fields_with_errors() {
        $yoast_data = array(
            '_yoast_wpseo_title' => str_repeat('A', 100), // Too long
            '_yoast_wpseo_metadesc' => str_repeat('B', 200), // Too long
            '_yoast_wpseo_canonical' => 'invalid-url' // Invalid URL
        );

        $validation = $this->yoast_seo->validate_yoast_fields($yoast_data);
        
        $this->assertIsArray($validation);
        $this->assertFalse($validation['valid']);
        $this->assertNotEmpty($validation['errors']);
    }

    /**
     * Test Yoast SEO preview generation
     */
    public function test_generate_yoast_preview() {
        $yoast_data = array(
            '_yoast_wpseo_title' => 'SEO Title',
            '_yoast_wpseo_metadesc' => 'Meta description',
            '_yoast_wpseo_opengraph-title' => 'Facebook Title'
        );

        $post_data = array(
            'post_title' => 'Post Title',
            'post_content' => 'Post content here'
        );

        $preview = $this->yoast_seo->generate_yoast_preview($yoast_data, $post_data);
        
        $this->assertIsArray($preview);
        $this->assertArrayHasKey('seo_title', $preview);
        $this->assertArrayHasKey('meta_description', $preview);
        $this->assertArrayHasKey('social_preview', $preview);
        $this->assertEquals('SEO Title', $preview['seo_title']);
    }

    /**
     * Test SEO score calculation
     */
    public function test_calculate_seo_score() {
        $yoast_data = array(
            '_yoast_wpseo_title' => 'Good SEO Title That Is Proper Length',
            '_yoast_wpseo_metadesc' => 'This is a good meta description that is the right length and contains useful information for search engines.',
            '_yoast_wpseo_focuskw' => 'test keyword'
        );

        $post_data = array(
            'post_title' => 'Post Title',
            'post_content' => 'Post content with test keyword mentioned'
        );

        $score = $this->yoast_seo->calculate_seo_score($yoast_data, $post_data);
        
        $this->assertIsArray($score);
        $this->assertArrayHasKey('score', $score);
        $this->assertArrayHasKey('percentage', $score);
        $this->assertArrayHasKey('status', $score);
        $this->assertArrayHasKey('recommendations', $score);
        $this->assertIsInt($score['score']);
        $this->assertGreaterThan(0, $score['percentage']);
    }

    /**
     * Test processing Yoast fields for a post
     */
    public function test_process_yoast_fields() {
        $post_id = $this->factory->post->create();
        
        $yoast_data = array(
            '_yoast_wpseo_title' => 'Test SEO Title',
            '_yoast_wpseo_metadesc' => 'Test meta description',
            '_yoast_wpseo_focuskw' => 'test'
        );

        $results = $this->yoast_seo->process_yoast_fields($post_id, $yoast_data);
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('processed', $results);
        $this->assertArrayHasKey('skipped', $results);
        $this->assertArrayHasKey('errors', $results);
        $this->assertEquals(3, $results['processed']);
        $this->assertEquals(0, $results['skipped']);
        
        // Verify meta was saved
        $saved_title = get_post_meta($post_id, '_yoast_wpseo_title', true);
        $this->assertEquals('Test SEO Title', $saved_title);
    }

    /**
     * Test Yoast SEO active detection
     */
    public function test_is_yoast_seo_active() {
        $is_active = $this->yoast_seo->is_yoast_seo_active();
        $this->assertIsBool($is_active);
    }
}