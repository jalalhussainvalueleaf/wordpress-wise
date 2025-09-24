<?php
/**
 * Yoast SEO Integration for JSON Post Importer
 *
 * Handles complete Yoast SEO meta field mapping, validation, and processing
 * with fallback handling when Yoast SEO plugin is not active.
 *
 * @package    JSON_Post_Importer
 * @subpackage JSON_Post_Importer/includes
 * @author     Your Name
 */

class JSON_Post_Importer_Yoast_SEO {

    /**
     * The logger instance
     *
     * @var JSON_Post_Importer_Logger
     */
    private $logger;

    /**
     * Whether Yoast SEO plugin is active
     *
     * @var bool
     */
    private $yoast_active;

    /**
     * Complete Yoast SEO meta fields mapping
     *
     * @var array
     */
    const YOAST_META_FIELDS = [
        // Basic SEO
        '_yoast_wpseo_title' => [
            'label' => 'SEO Title',
            'max_length' => 60,
            'type' => 'text',
            'required' => false
        ],
        '_yoast_wpseo_metadesc' => [
            'label' => 'Meta Description',
            'max_length' => 160,
            'type' => 'textarea',
            'required' => false
        ],
        '_yoast_wpseo_focuskw' => [
            'label' => 'Focus Keyword',
            'max_length' => 100,
            'type' => 'text',
            'required' => false
        ],
        '_yoast_wpseo_keywordsynonyms' => [
            'label' => 'Keyword Synonyms',
            'max_length' => 1000,
            'type' => 'array',
            'required' => false
        ],
        
        // Advanced SEO
        '_yoast_wpseo_canonical' => [
            'label' => 'Canonical URL',
            'max_length' => 255,
            'type' => 'url',
            'required' => false
        ],
        '_yoast_wpseo_meta-robots-noindex' => [
            'label' => 'Meta Robots NoIndex',
            'type' => 'select',
            'options' => ['0', '1', '2'],
            'required' => false
        ],
        '_yoast_wpseo_meta-robots-nofollow' => [
            'label' => 'Meta Robots NoFollow',
            'type' => 'select',
            'options' => ['0', '1'],
            'required' => false
        ],
        '_yoast_wpseo_meta-robots-adv' => [
            'label' => 'Meta Robots Advanced',
            'type' => 'text',
            'required' => false
        ],
        
        // Social Media - Open Graph
        '_yoast_wpseo_opengraph-title' => [
            'label' => 'Facebook Title',
            'max_length' => 95,
            'type' => 'text',
            'required' => false
        ],
        '_yoast_wpseo_opengraph-description' => [
            'label' => 'Facebook Description',
            'max_length' => 300,
            'type' => 'textarea',
            'required' => false
        ],
        '_yoast_wpseo_opengraph-image' => [
            'label' => 'Facebook Image',
            'type' => 'url',
            'required' => false
        ],
        '_yoast_wpseo_opengraph-image-id' => [
            'label' => 'Facebook Image ID',
            'type' => 'number',
            'required' => false
        ],
        
        // Social Media - Twitter
        '_yoast_wpseo_twitter-title' => [
            'label' => 'Twitter Title',
            'max_length' => 70,
            'type' => 'text',
            'required' => false
        ],
        '_yoast_wpseo_twitter-description' => [
            'label' => 'Twitter Description',
            'max_length' => 200,
            'type' => 'textarea',
            'required' => false
        ],
        '_yoast_wpseo_twitter-image' => [
            'label' => 'Twitter Image',
            'type' => 'url',
            'required' => false
        ],
        '_yoast_wpseo_twitter-image-id' => [
            'label' => 'Twitter Image ID',
            'type' => 'number',
            'required' => false
        ],
        
        // Schema.org
        '_yoast_wpseo_schema_page_type' => [
            'label' => 'Schema Page Type',
            'type' => 'select',
            'options' => ['WebPage', 'ItemPage', 'AboutPage', 'CheckoutPage', 'CollectionPage', 'ContactPage', 'FAQPage', 'ProfilePage', 'QAPage', 'RealEstateListing', 'SearchResultsPage'],
            'required' => false
        ],
        '_yoast_wpseo_schema_article_type' => [
            'label' => 'Schema Article Type',
            'type' => 'select',
            'options' => ['Article', 'BlogPosting', 'NewsArticle', 'OpinionPiece', 'Report', 'ScholarlyArticle', 'TechArticle'],
            'required' => false
        ]
    ];

    /**
     * Common JSON field patterns for Yoast SEO mapping
     *
     * @var array
     */
    const FIELD_PATTERNS = [
        '_yoast_wpseo_title' => [
            'seo_title', 'meta_title', 'page_title', 'browser_title', 'title_tag',
            'seo.title', 'meta.title', 'yoast_seo_title', 'yoast.title'
        ],
        '_yoast_wpseo_metadesc' => [
            'seo_description', 'meta_description', 'description', 'meta_desc',
            'seo.description', 'meta.description', 'yoast_seo_description', 'yoast.description'
        ],
        '_yoast_wpseo_focuskw' => [
            'focus_keyword', 'keyword', 'main_keyword', 'primary_keyword',
            'seo.keyword', 'yoast_focus_keyword', 'yoast.keyword'
        ],
        '_yoast_wpseo_keywordsynonyms' => [
            'keyword_synonyms', 'keywords', 'related_keywords', 'secondary_keywords',
            'seo.keywords', 'yoast_keywords', 'yoast.synonyms'
        ],
        '_yoast_wpseo_canonical' => [
            'canonical', 'canonical_url', 'seo.canonical', 'yoast.canonical'
        ],
        '_yoast_wpseo_opengraph-title' => [
            'og_title', 'facebook_title', 'social_title', 'social.facebook.title',
            'open_graph.title', 'yoast.og_title'
        ],
        '_yoast_wpseo_opengraph-description' => [
            'og_description', 'facebook_description', 'social_description',
            'social.facebook.description', 'open_graph.description', 'yoast.og_description'
        ],
        '_yoast_wpseo_opengraph-image' => [
            'og_image', 'facebook_image', 'social_image', 'social.facebook.image',
            'open_graph.image', 'yoast.og_image'
        ],
        '_yoast_wpseo_twitter-title' => [
            'twitter_title', 'social.twitter.title', 'yoast.twitter_title'
        ],
        '_yoast_wpseo_twitter-description' => [
            'twitter_description', 'social.twitter.description', 'yoast.twitter_description'
        ],
        '_yoast_wpseo_twitter-image' => [
            'twitter_image', 'social.twitter.image', 'yoast.twitter_image'
        ]
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = new JSON_Post_Importer_Logger();
        $this->yoast_active = $this->is_yoast_seo_active();
        
        $this->logger->debug('Yoast SEO integration initialized', [
            'yoast_active' => $this->yoast_active
        ]);
    }

    /**
     * Check if Yoast SEO plugin is active
     *
     * @return bool
     */
    public function is_yoast_seo_active() {
        return function_exists('YoastSEO') || class_exists('WPSEO_Options');
    }

    /**
     * Get all available Yoast SEO fields
     *
     * @return array
     */
    public function get_yoast_fields() {
        return self::YOAST_META_FIELDS;
    }

    /**
     * Get field patterns for auto-detection
     *
     * @return array
     */
    public function get_field_patterns() {
        return self::FIELD_PATTERNS;
    }

    /**
     * Auto-detect Yoast SEO fields from JSON data
     *
     * @param array $json_data JSON data to analyze
     * @param string $root_path Root path for nested data (default: 'content')
     * @return array Detected field mappings
     */
    public function auto_detect_yoast_fields($json_data, $root_path = 'content') {
        $this->logger->debug('Auto-detecting Yoast SEO fields', [
            'root_path' => $root_path,
            'data_keys' => array_keys($json_data)
        ]);

        $detected_mappings = [];
        
        // Get sample data for analysis
        $sample_item = is_array($json_data) && !empty($json_data) ? $json_data[0] : $json_data;
        
        // Extract content data if using nested structure
        $content_data = $sample_item;
        if (!empty($root_path) && isset($sample_item[$root_path])) {
            $content_data = $sample_item[$root_path];
        }
        
        // Flatten the data for easier pattern matching
        $flattened_data = $this->flatten_array($content_data);
        $available_fields = array_keys($flattened_data);
        
        $this->logger->debug('Available fields for detection', [
            'fields' => $available_fields
        ]);

        // Match patterns against available fields
        foreach (self::FIELD_PATTERNS as $yoast_field => $patterns) {
            foreach ($patterns as $pattern) {
                // Direct match
                if (in_array($pattern, $available_fields)) {
                    $detected_mappings[$yoast_field] = $pattern;
                    $this->logger->debug("Detected Yoast field mapping", [
                        'yoast_field' => $yoast_field,
                        'json_field' => $pattern
                    ]);
                    break;
                }
                
                // Partial match (case-insensitive)
                foreach ($available_fields as $field) {
                    if (stripos($field, $pattern) !== false || stripos($pattern, $field) !== false) {
                        $detected_mappings[$yoast_field] = $field;
                        $this->logger->debug("Detected Yoast field mapping (partial)", [
                            'yoast_field' => $yoast_field,
                            'json_field' => $field,
                            'pattern' => $pattern
                        ]);
                        break 2;
                    }
                }
            }
        }

        return $detected_mappings;
    }

    /**
     * Validate Yoast SEO field values
     *
     * @param array $yoast_data Yoast SEO field data
     * @return array Validation results with errors and warnings
     */
    public function validate_yoast_fields($yoast_data) {
        $validation_results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'processed_data' => []
        ];

        foreach ($yoast_data as $field => $value) {
            if (!isset(self::YOAST_META_FIELDS[$field])) {
                $validation_results['warnings'][] = "Unknown Yoast field: {$field}";
                continue;
            }

            $field_config = self::YOAST_META_FIELDS[$field];
            $processed_value = $this->validate_field_value($field, $value, $field_config);

            if (is_wp_error($processed_value)) {
                $validation_results['valid'] = false;
                $validation_results['errors'][] = $processed_value->get_error_message();
            } else {
                $validation_results['processed_data'][$field] = $processed_value;
                
                // Check for warnings
                $warnings = $this->check_field_warnings($field, $processed_value, $field_config);
                if (!empty($warnings)) {
                    $validation_results['warnings'] = array_merge($validation_results['warnings'], $warnings);
                }
            }
        }

        $this->logger->debug('Yoast field validation completed', [
            'valid' => $validation_results['valid'],
            'error_count' => count($validation_results['errors']),
            'warning_count' => count($validation_results['warnings'])
        ]);

        return $validation_results;
    }

    /**
     * Validate individual field value
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param array $config Field configuration
     * @return mixed|WP_Error Processed value or error
     */
    private function validate_field_value($field, $value, $config) {
        // Handle empty values
        if (empty($value) && $value !== '0') {
            if (!empty($config['required'])) {
                return new WP_Error('required_field', "Required field {$field} is empty");
            }
            return '';
        }

        // Type-specific validation
        switch ($config['type']) {
            case 'text':
                $value = sanitize_text_field($value);
                break;
                
            case 'textarea':
                $value = sanitize_textarea_field($value);
                break;
                
            case 'url':
                $value = esc_url_raw($value);
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    return new WP_Error('invalid_url', "Invalid URL for field {$field}: {$value}");
                }
                break;
                
            case 'number':
                if (!is_numeric($value)) {
                    return new WP_Error('invalid_number', "Invalid number for field {$field}: {$value}");
                }
                $value = intval($value);
                break;
                
            case 'select':
                if (!empty($config['options']) && !in_array($value, $config['options'])) {
                    return new WP_Error('invalid_option', "Invalid option for field {$field}: {$value}");
                }
                break;
                
            case 'array':
                if (is_string($value)) {
                    // Convert comma-separated string to array
                    $value = array_map('trim', explode(',', $value));
                }
                if (!is_array($value)) {
                    return new WP_Error('invalid_array', "Field {$field} must be an array");
                }
                // Sanitize array values
                $value = array_map('sanitize_text_field', $value);
                // Convert back to comma-separated string for Yoast
                $value = implode(',', $value);
                break;
        }

        // Length validation
        if (!empty($config['max_length']) && strlen($value) > $config['max_length']) {
            return new WP_Error('field_too_long', 
                "Field {$field} exceeds maximum length of {$config['max_length']} characters"
            );
        }

        return $value;
    }

    /**
     * Check for field warnings
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param array $config Field configuration
     * @return array Warnings
     */
    private function check_field_warnings($field, $value, $config) {
        $warnings = [];

        // Length warnings
        if (!empty($config['max_length'])) {
            $length = strlen($value);
            $max_length = $config['max_length'];
            
            // Warn if approaching limit (80% of max)
            if ($length > ($max_length * 0.8)) {
                $warnings[] = "Field {$field} is approaching character limit ({$length}/{$max_length})";
            }
        }

        // SEO-specific warnings
        switch ($field) {
            case '_yoast_wpseo_title':
                if (strlen($value) < 30) {
                    $warnings[] = "SEO title is quite short (recommended: 30-60 characters)";
                }
                break;
                
            case '_yoast_wpseo_metadesc':
                if (strlen($value) < 120) {
                    $warnings[] = "Meta description is quite short (recommended: 120-160 characters)";
                }
                break;
                
            case '_yoast_wpseo_focuskw':
                if (empty($value)) {
                    $warnings[] = "No focus keyword set - this may impact SEO analysis";
                }
                break;
        }

        return $warnings;
    }

    /**
     * Process Yoast SEO fields for import
     *
     * @param int $post_id Post ID
     * @param array $yoast_data Yoast SEO field data
     * @param array $options Processing options
     * @return array Processing results
     */
    public function process_yoast_fields($post_id, $yoast_data, $options = []) {
        $this->logger->debug('Processing Yoast SEO fields', [
            'post_id' => $post_id,
            'field_count' => count($yoast_data),
            'yoast_active' => $this->yoast_active,
            'fields' => array_keys($yoast_data)
        ]);

        $results = [
            'processed' => 0,
            'skipped' => 0,
            'errors' => []
        ];

        // Validate fields first
        $validation = $this->validate_yoast_fields($yoast_data);
        
        if (!$validation['valid']) {
            $results['errors'] = $validation['errors'];
            return $results;
        }

        // Process each field
        foreach ($validation['processed_data'] as $field => $value) {
            try {
                // Always store the original Yoast field, regardless of plugin status
                // This ensures compatibility when Yoast SEO is activated later
                $meta_result = update_post_meta($post_id, $field, $value);
                
                if (!$this->yoast_active) {
                    // Also store as custom meta with fallback prefix for easy identification
                    $fallback_key = 'jpi_yoast_' . str_replace('_yoast_wpseo_', '', $field);
                    update_post_meta($post_id, $fallback_key, $value);
                }
                
                // For certain fields, we need to ensure Yoast SEO recognizes them
                // by setting additional meta fields that Yoast uses internally
                $this->set_yoast_internal_meta($post_id, $field, $value);
                
                $results['processed']++;
                
                $this->logger->debug("Processed Yoast field", [
                    'post_id' => $post_id,
                    'field' => $field,
                    'value_length' => strlen($value),
                    'meta_result' => $meta_result
                ]);
                
            } catch (Exception $e) {
                $results['errors'][] = "Error processing field {$field}: " . $e->getMessage();
                $results['skipped']++;
                
                $this->logger->error("Error processing Yoast field", [
                    'post_id' => $post_id,
                    'field' => $field,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Log warnings
        if (!empty($validation['warnings'])) {
            foreach ($validation['warnings'] as $warning) {
                $this->logger->warning($warning, ['post_id' => $post_id]);
            }
        }

        // Force Yoast SEO to refresh its data for this post
        if ($results['processed'] > 0) {
            $this->refresh_yoast_data($post_id);
            
            // Verify the fields were saved correctly
            $verification_results = $this->verify_saved_fields($post_id, $validation['processed_data']);
            $results['verification'] = $verification_results;
        }

        return $results;
    }

    /**
     * Generate Yoast SEO preview data
     *
     * @param array $yoast_data Yoast SEO field data
     * @param array $post_data Post data for fallbacks
     * @return array Preview data
     */
    public function generate_yoast_preview($yoast_data, $post_data = []) {
        $preview = [
            'seo_title' => '',
            'meta_description' => '',
            'focus_keyword' => '',
            'url_preview' => '',
            'social_preview' => [
                'facebook' => [],
                'twitter' => []
            ]
        ];

        // SEO Title
        $preview['seo_title'] = $yoast_data['_yoast_wpseo_title'] ?? $post_data['post_title'] ?? '';
        
        // Meta Description
        $preview['meta_description'] = $yoast_data['_yoast_wpseo_metadesc'] ?? 
                                     wp_trim_words($post_data['post_excerpt'] ?? $post_data['post_content'] ?? '', 25);
        
        // Focus Keyword
        $preview['focus_keyword'] = $yoast_data['_yoast_wpseo_focuskw'] ?? '';
        
        // URL Preview (simplified)
        $site_url = get_site_url();
        $post_slug = $post_data['post_name'] ?? sanitize_title($post_data['post_title'] ?? '');
        $preview['url_preview'] = $site_url . '/' . $post_slug . '/';
        
        // Social Media Previews
        $preview['social_preview']['facebook'] = [
            'title' => $yoast_data['_yoast_wpseo_opengraph-title'] ?? $preview['seo_title'],
            'description' => $yoast_data['_yoast_wpseo_opengraph-description'] ?? $preview['meta_description'],
            'image' => $yoast_data['_yoast_wpseo_opengraph-image'] ?? ''
        ];
        
        $preview['social_preview']['twitter'] = [
            'title' => $yoast_data['_yoast_wpseo_twitter-title'] ?? $preview['seo_title'],
            'description' => $yoast_data['_yoast_wpseo_twitter-description'] ?? $preview['meta_description'],
            'image' => $yoast_data['_yoast_wpseo_twitter-image'] ?? ''
        ];

        return $preview;
    }

    /**
     * Calculate basic SEO score
     *
     * @param array $yoast_data Yoast SEO field data
     * @param array $post_data Post data
     * @return array SEO score and recommendations
     */
    public function calculate_seo_score($yoast_data, $post_data = []) {
        $score = 0;
        $max_score = 100;
        $recommendations = [];

        // SEO Title (20 points)
        $seo_title = $yoast_data['_yoast_wpseo_title'] ?? '';
        if (!empty($seo_title)) {
            $title_length = strlen($seo_title);
            if ($title_length >= 30 && $title_length <= 60) {
                $score += 20;
            } elseif ($title_length > 0) {
                $score += 10;
                $recommendations[] = 'SEO title should be 30-60 characters long';
            }
        } else {
            $recommendations[] = 'Add an SEO title';
        }

        // Meta Description (20 points)
        $meta_desc = $yoast_data['_yoast_wpseo_metadesc'] ?? '';
        if (!empty($meta_desc)) {
            $desc_length = strlen($meta_desc);
            if ($desc_length >= 120 && $desc_length <= 160) {
                $score += 20;
            } elseif ($desc_length > 0) {
                $score += 10;
                $recommendations[] = 'Meta description should be 120-160 characters long';
            }
        } else {
            $recommendations[] = 'Add a meta description';
        }

        // Focus Keyword (15 points)
        $focus_keyword = $yoast_data['_yoast_wpseo_focuskw'] ?? '';
        if (!empty($focus_keyword)) {
            $score += 15;
            
            // Check if keyword appears in title
            if (!empty($seo_title) && stripos($seo_title, $focus_keyword) !== false) {
                $score += 10;
            } else {
                $recommendations[] = 'Include focus keyword in SEO title';
            }
            
            // Check if keyword appears in meta description
            if (!empty($meta_desc) && stripos($meta_desc, $focus_keyword) !== false) {
                $score += 10;
            } else {
                $recommendations[] = 'Include focus keyword in meta description';
            }
        } else {
            $recommendations[] = 'Set a focus keyword';
        }

        // Social Media (15 points)
        $has_og_title = !empty($yoast_data['_yoast_wpseo_opengraph-title']);
        $has_og_desc = !empty($yoast_data['_yoast_wpseo_opengraph-description']);
        $has_og_image = !empty($yoast_data['_yoast_wpseo_opengraph-image']);
        
        if ($has_og_title && $has_og_desc && $has_og_image) {
            $score += 15;
        } elseif ($has_og_title || $has_og_desc || $has_og_image) {
            $score += 8;
            $recommendations[] = 'Complete social media settings (title, description, image)';
        } else {
            $recommendations[] = 'Add social media settings';
        }

        // Canonical URL (10 points)
        if (!empty($yoast_data['_yoast_wpseo_canonical'])) {
            $score += 10;
        }

        // Calculate percentage
        $percentage = round(($score / $max_score) * 100);
        
        // Determine status
        $status = 'poor';
        if ($percentage >= 80) {
            $status = 'good';
        } elseif ($percentage >= 60) {
            $status = 'ok';
        }

        return [
            'score' => $score,
            'max_score' => $max_score,
            'percentage' => $percentage,
            'status' => $status,
            'recommendations' => $recommendations
        ];
    }

    /**
     * Migrate Yoast SEO data when plugin becomes active
     *
     * @param int $post_id Post ID
     * @return array Migration results
     */
    public function migrate_yoast_data($post_id) {
        if (!$this->yoast_active) {
            return ['migrated' => 0, 'message' => 'Yoast SEO not active'];
        }

        $migrated = 0;
        $fallback_prefix = 'jpi_yoast_';

        foreach (array_keys(self::YOAST_META_FIELDS) as $yoast_field) {
            $fallback_key = $fallback_prefix . str_replace('_yoast_wpseo_', '', $yoast_field);
            $fallback_value = get_post_meta($post_id, $fallback_key, true);
            
            if (!empty($fallback_value)) {
                // Check if Yoast field already exists
                $existing_value = get_post_meta($post_id, $yoast_field, true);
                
                if (empty($existing_value)) {
                    update_post_meta($post_id, $yoast_field, $fallback_value);
                    delete_post_meta($post_id, $fallback_key);
                    $migrated++;
                }
            }
        }

        return [
            'migrated' => $migrated,
            'message' => "Migrated {$migrated} Yoast SEO fields"
        ];
    }

    /**
     * Verify that Yoast fields were saved correctly
     *
     * @param int $post_id Post ID
     * @param array $expected_data Expected field values
     * @return array Verification results
     */
    private function verify_saved_fields($post_id, $expected_data) {
        $verification = [
            'verified' => 0,
            'failed' => 0,
            'details' => []
        ];

        foreach ($expected_data as $field => $expected_value) {
            $saved_value = get_post_meta($post_id, $field, true);
            $matches = ($saved_value === $expected_value);
            
            $verification['details'][$field] = [
                'expected' => $expected_value,
                'saved' => $saved_value,
                'matches' => $matches
            ];
            
            if ($matches) {
                $verification['verified']++;
            } else {
                $verification['failed']++;
                $this->logger->warning("Yoast field verification failed", [
                    'post_id' => $post_id,
                    'field' => $field,
                    'expected' => $expected_value,
                    'saved' => $saved_value
                ]);
            }
        }

        $this->logger->debug("Yoast field verification completed", [
            'post_id' => $post_id,
            'verified' => $verification['verified'],
            'failed' => $verification['failed']
        ]);

        return $verification;
    }

    /**
     * Force Yoast SEO to refresh its data for a post
     *
     * @param int $post_id Post ID
     */
    private function refresh_yoast_data($post_id) {
        if (!$this->yoast_active) {
            return;
        }

        try {
            // Clear Yoast SEO caches
            delete_post_meta($post_id, '_yoast_wpseo_content_score');
            delete_post_meta($post_id, '_yoast_wpseo_keyword_analysis');
            delete_post_meta($post_id, '_yoast_wpseo_readability_score');
            
            // Trigger Yoast SEO hooks if available
            if (function_exists('YoastSEO')) {
                // Try to trigger Yoast's post save hooks to recalculate scores
                do_action('save_post', $post_id);
                do_action('wp_insert_post', $post_id);
            }
            
            $this->logger->debug("Refreshed Yoast SEO data for post", [
                'post_id' => $post_id
            ]);
            
        } catch (Exception $e) {
            $this->logger->warning("Could not refresh Yoast SEO data", [
                'post_id' => $post_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Set additional internal meta fields that Yoast SEO might need
     *
     * @param int $post_id Post ID
     * @param string $field Yoast field name
     * @param mixed $value Field value
     */
    private function set_yoast_internal_meta($post_id, $field, $value) {
        // Handle specific fields that might need additional processing
        switch ($field) {
            case '_yoast_wpseo_focuskw':
                // Yoast SEO sometimes uses additional fields for keyword analysis
                if (!empty($value)) {
                    // Set keyword analysis score (default to needs improvement)
                    update_post_meta($post_id, '_yoast_wpseo_focuskw_text_input', $value);
                    update_post_meta($post_id, '_yoast_wpseo_linkdex', '41'); // Default score
                }
                break;
                
            case '_yoast_wpseo_title':
                // Ensure title template is not overriding
                if (!empty($value)) {
                    update_post_meta($post_id, '_yoast_wpseo_title_template', '');
                }
                break;
                
            case '_yoast_wpseo_metadesc':
                // Ensure meta description template is not overriding
                if (!empty($value)) {
                    update_post_meta($post_id, '_yoast_wpseo_metadesc_template', '');
                }
                break;
                
            case '_yoast_wpseo_meta-robots-noindex':
                // Ensure the value is properly formatted for Yoast
                if ($value === '1' || $value === 1) {
                    update_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', '1');
                } elseif ($value === '2' || $value === 2) {
                    update_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', '2');
                } else {
                    update_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', '0');
                }
                break;
                
            case '_yoast_wpseo_meta-robots-nofollow':
                // Ensure the value is properly formatted
                update_post_meta($post_id, '_yoast_wpseo_meta-robots-nofollow', $value === '1' || $value === 1 ? '1' : '0');
                break;
        }
        
        // If this is the first time setting Yoast data for this post,
        // ensure Yoast SEO recognizes it by setting a flag
        $yoast_data_set = get_post_meta($post_id, '_jpi_yoast_data_imported', true);
        if (!$yoast_data_set) {
            $timestamp = function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s');
            update_post_meta($post_id, '_jpi_yoast_data_imported', $timestamp);
            
            // Trigger Yoast SEO to recalculate scores if plugin is active
            if ($this->yoast_active && function_exists('YoastSEO')) {
                // Clear any cached analysis
                delete_post_meta($post_id, '_yoast_wpseo_content_score');
                delete_post_meta($post_id, '_yoast_wpseo_keyword_analysis');
            }
        }
    }

    /**
     * Flatten nested array for field detection
     *
     * @param array $array Array to flatten
     * @param string $prefix Prefix for keys
     * @return array Flattened array
     */
    private function flatten_array($array, $prefix = '') {
        $result = [];
        
        foreach ($array as $key => $value) {
            $new_key = $prefix === '' ? $key : $prefix . '.' . $key;
            
            if (is_array($value)) {
                $result = array_merge($result, $this->flatten_array($value, $new_key));
            } else {
                $result[$new_key] = $value;
            }
        }
        
        return $result;
    }
}