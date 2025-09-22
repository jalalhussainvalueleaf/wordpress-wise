<?php
/**
 * Handles creating and updating WordPress posts from JSON data
 */
class JSON_Post_Creator {
    
    /**
     * The logger instance
     *
     * @var JSON_Post_Importer_Logger
     */
    private $logger;
    
    /**
     * The nested handler instance
     *
     * @var JSON_Post_Importer_Nested_Handler
     */
    private $nested_handler;
    
    /**
     * The Yoast SEO integration instance
     *
     * @var JSON_Post_Importer_Yoast_SEO
     */
    private $yoast_seo;
    
    /**
     * Default post type for imported content
     *
     * @var string
     */
    private $default_post_type = 'post';
    
    /**
     * Default post status for imported content
     *
     * @var string
     */
    private $default_post_status = 'draft';
    
    /**
     * Field mappings for JSON to WordPress fields
     *
     * @var array
     */
    private $field_mappings = array(
        'title' => 'post_title',
        'content' => 'post_content',
        'excerpt' => 'post_excerpt',
        'status' => 'post_status',
        'date' => 'post_date',
        'modified' => 'post_modified',
        'author' => 'post_author',
        'slug' => 'post_name',
        'type' => 'post_type',
        'parent' => 'post_parent',
        'menu_order' => 'menu_order',
        'comment_status' => 'comment_status',
        'ping_status' => 'ping_status',
    );
    
    /**
     * Last duplicate detection method used
     *
     * @var string
     */
    private $last_duplicate_detection_method;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = new JSON_Post_Importer_Logger();
        $this->nested_handler = new JSON_Post_Importer_Nested_Handler();
        $this->yoast_seo = new JSON_Post_Importer_Yoast_SEO();
    }
    
    /**
     * Create or update a post from JSON data
     *
     * @param array $item The JSON data for a single post
     * @param array $options Import options
     * @return array|WP_Error Result array with post ID and status, or WP_Error on failure
     */
    public function create_or_update_post($item, $options = array()) {
        // Validate input
        if (empty($item) || !is_array($item)) {
            $this->logger->error('Invalid post data provided - empty or not an array');
            return new WP_Error('invalid_data', 'Invalid post data provided');
        }
        
        // Debug log the incoming item and options
        $this->logger->debug('Processing post item', array('item' => $item, 'options' => $options));
        
        // Merge with defaults
        $options = wp_parse_args($options, array(
            'post_type' => $this->default_post_type,
            'post_status' => $this->default_post_status,
            'update_existing' => true,
            'skip_thumbnail' => false,
            'import_attachments' => true,
            'default_author' => get_current_user_id(),
            'json_root_path' => 'content',
            'import_wrapper_meta' => true,
            'field_mappings' => array(),
        ));
        
        // Process field mappings if provided
        $processed_data = array();
        if (!empty($options['field_mappings'])) {
            // Simple field mapping processing
            $processed_data = $this->process_simple_field_mappings($item, $options['field_mappings']);
        }
        
        // Prepare post data
        $post_data = $this->prepare_post_data($item, $options, $processed_data);
        if (is_wp_error($post_data)) {
            return $post_data;
        }
        
        // Check for existing post using enhanced detection if enabled
        if (!empty($options['enable_enhanced_duplicate_detection'])) {
            $existing_id = $this->find_existing_post_enhanced($item, $options);
        } else {
            $existing_id = $this->find_existing_post($item, $options);
        }
        $is_update = false;
        
        if ($existing_id) {
            $this->logger->debug('Found existing post', array('post_id' => $existing_id));
            if (!$options['update_existing']) {
                $this->logger->info('Update is disabled, skipping existing post', array('post_id' => $existing_id));
                return new WP_Error('post_exists', 'Post already exists and update is disabled', array('id' => $existing_id));
            }
            $post_data['ID'] = $existing_id;
            $is_update = true;
            $this->logger->debug('Will update existing post', array('post_id' => $existing_id));
        } else {
            $this->logger->debug('No existing post found, will create new post');
        }
        
        // Insert or update post
        $this->logger->debug('Attempting to ' . ($is_update ? 'update' : 'create') . ' post', array('post_data' => $post_data));
        
        $post_id = $is_update ? wp_update_post($post_data, true) : wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            $this->logger->error('Error ' . ($is_update ? 'updating' : 'creating') . ' post: ' . $post_id->get_error_message(), array('post_data' => $post_data));
            return $post_id;
        }
        
        $this->logger->log_post_action($is_update ? 'updated' : 'created', $post_id, $item);
        
        // Initialize field processing statistics
        $field_processing_stats = array(
            'standard' => array('processed' => 1, 'errors' => 0), // At least one standard field processed
            'yoast_seo' => array('processed' => 0, 'errors' => 0),
            'custom' => array('processed' => 0, 'errors' => 0),
            'wrapper_metadata' => array('processed' => 0, 'errors' => 0),
            'media' => array('processed' => 0, 'errors' => 0),
            'taxonomies' => array('processed' => 0, 'errors' => 0)
        );
        
        // Process taxonomies with tracking
        try {
            $taxonomy_stats = $this->process_taxonomies($post_id, $item, $options, $processed_data);
            if (is_array($taxonomy_stats)) {
                $field_processing_stats['taxonomies'] = $taxonomy_stats;
            } elseif (!empty($processed_data['taxonomies']) || $this->has_taxonomy_data($item)) {
                $field_processing_stats['taxonomies']['processed'] = 1;
            }
        } catch (Exception $e) {
            $field_processing_stats['taxonomies']['errors']++;
            $this->logger->error('Error processing taxonomies', array(
                'post_id' => $post_id,
                'error' => $e->getMessage()
            ));
        }
        
        // Process meta fields with tracking
        try {
            $meta_stats = $this->process_meta_fields($post_id, $item, $options, $processed_data);
            if (is_array($meta_stats)) {
                $field_processing_stats = array_merge_recursive($field_processing_stats, $meta_stats);
            }
        } catch (Exception $e) {
            $field_processing_stats['custom']['errors']++;
            $this->logger->error('Error processing meta fields', array(
                'post_id' => $post_id,
                'error' => $e->getMessage()
            ));
        }
        
        // Process featured image with tracking
        if (!$options['skip_thumbnail'] && !empty($item['featured_image'])) {
            try {
                $image_result = $this->process_featured_image($post_id, $item['featured_image'], $options);
                if (!is_wp_error($image_result)) {
                    $field_processing_stats['media']['processed']++;
                } else {
                    $field_processing_stats['media']['errors']++;
                }
            } catch (Exception $e) {
                $field_processing_stats['media']['errors']++;
                $this->logger->error('Error processing featured image', array(
                    'post_id' => $post_id,
                    'error' => $e->getMessage()
                ));
            }
        }
        
        // Process attachments with tracking
        if ($options['import_attachments'] && !empty($item['attachments'])) {
            try {
                $attachment_results = $this->process_attachments($post_id, $item['attachments'], $options);
                if (is_array($attachment_results)) {
                    $field_processing_stats['media']['processed'] += count($attachment_results);
                }
            } catch (Exception $e) {
                $field_processing_stats['media']['errors']++;
                $this->logger->error('Error processing attachments', array(
                    'post_id' => $post_id,
                    'error' => $e->getMessage()
                ));
            }
        }
        
        // Action after post is imported/updated
        do_action('jpi_after_post_import', $post_id, $item, $options);
        
        return array(
            'id' => $post_id,
            'updated' => $is_update,
            'message' => $is_update ? 'Post updated successfully' : 'Post created successfully',
            'field_processing_stats' => $field_processing_stats
        );
    }
    
    /**
     * Prepare post data from JSON item with enhanced field mapping support
     *
     * @param array $item JSON data item
     * @param array $options Import options
     * @param array $processed_data Processed field data from nested handler
     * @return array|WP_Error Prepared post data or error
     */
    private function prepare_post_data($item, $options, $processed_data = array()) {
        $post_data = array(
            'post_type' => $options['post_type'],
            'post_status' => $options['post_status'],
            'post_author' => $options['default_author'],
        );
        
        $this->logger->debug('Preparing post data', array(
            'options' => $options,
            'has_processed_data' => !empty($processed_data['standard'])
        ));
        
        // Use processed data if available, otherwise fall back to legacy mapping
        if (!empty($processed_data['standard'])) {
            foreach ($processed_data['standard'] as $wp_field => $value) {
                $sanitized_value = $this->sanitize_field($wp_field, $value, $item);
                if ($sanitized_value !== null && $sanitized_value !== '') {
                    $post_data[$wp_field] = $sanitized_value;
                }
            }
        } else {
            // Legacy field mapping for backward compatibility
            foreach ($this->field_mappings as $json_field => $wp_field) {
                if (isset($item[$json_field])) {
                    $sanitized_value = $this->sanitize_field($wp_field, $item[$json_field], $item);
                    if ($sanitized_value !== null && $sanitized_value !== '') {
                        $post_data[$wp_field] = $sanitized_value;
                    }
                }
            }
        }
        
        // Ensure required fields with enhanced validation
        $post_data = $this->ensure_required_fields($post_data, $item, $options);
        
        // Handle dates with enhanced parsing
        $post_data = $this->process_post_dates($post_data, $item, $processed_data);
        
        // Process content with enhanced handling
        $post_data = $this->process_post_content_data($post_data, $item, $processed_data);
        
        // Validate final post data
        $validation_result = $this->validate_post_data($post_data);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }
        
        $this->logger->debug('Prepared post data', array(
            'post_data_keys' => array_keys($post_data),
            'post_title' => $post_data['post_title'] ?? 'N/A'
        ));
        
        return $post_data;
    }
    
    /**
     * Ensure required fields are present and valid
     *
     * @param array $post_data Current post data
     * @param array $item Original JSON item
     * @param array $options Import options
     * @return array Updated post data
     */
    private function ensure_required_fields($post_data, $item, $options) {
        // Ensure post title
        if (empty($post_data['post_title'])) {
            // Try to generate from other fields
            $title = $this->generate_post_title($item);
            $post_data['post_title'] = !empty($title) ? $title : sprintf(__('Untitled %s', 'json-post-importer'), current_time('mysql'));
        }
        
        // Ensure post content
        if (empty($post_data['post_content'])) {
            $post_data['post_content'] = $this->generate_post_content($item);
        }
        
        // Ensure post status is valid
        if (empty($post_data['post_status']) || !in_array($post_data['post_status'], get_post_stati())) {
            $post_data['post_status'] = $options['post_status'] ?? $this->default_post_status;
        }
        
        // Ensure post type is valid
        if (empty($post_data['post_type']) || !post_type_exists($post_data['post_type'])) {
            $post_data['post_type'] = $options['post_type'] ?? $this->default_post_type;
        }
        
        // Ensure post author is valid
        if (empty($post_data['post_author']) || !get_user_by('id', $post_data['post_author'])) {
            $post_data['post_author'] = $options['default_author'] ?? get_current_user_id();
        }
        
        return $post_data;
    }
    
    /**
     * Process simple field mappings
     */
    private function process_simple_field_mappings($item, $field_mappings) {
        $processed_data = array();
        
        // Handle different field mapping formats
        if (isset($field_mappings['standard'])) {
            // Enhanced format with sections
            foreach ($field_mappings['standard'] as $wp_field => $json_path) {
                if (!empty($json_path)) {
                    $value = $this->nested_handler->extract_nested_value($item, $json_path);
                    if ($value !== null) {
                        $processed_data[$wp_field] = $value;
                    }
                }
            }
        } else {
            // Legacy format - direct mapping
            foreach ($field_mappings as $wp_field => $json_path) {
                if (!empty($json_path)) {
                    $value = $this->nested_handler->extract_nested_value($item, $json_path);
                    if ($value !== null) {
                        $processed_data[$wp_field] = $value;
                    }
                }
            }
        }
        
        return $processed_data;
    }
    
    /**
     * Generate post title from available data
     *
     * @param array $item JSON item data
     * @return string Generated title
     */
    private function generate_post_title($item) {
        // Try various title fields
        $title_fields = array('title', 'name', 'heading', 'subject', 'post_title');
        
        foreach ($title_fields as $field) {
            if (!empty($item[$field])) {
                return sanitize_text_field($item[$field]);
            }
        }
        
        // Try nested content
        if (!empty($item['content']) && is_array($item['content'])) {
            foreach ($title_fields as $field) {
                if (!empty($item['content'][$field])) {
                    return sanitize_text_field($item['content'][$field]);
                }
            }
        }
        
        return '';
    }
    
    /**
     * Generate post content from available data
     *
     * @param array $item JSON item data
     * @return string Generated content
     */
    private function generate_post_content($item) {
        // Try various content fields
        $content_fields = array('content', 'body', 'description', 'text', 'post_content');
        
        foreach ($content_fields as $field) {
            if (!empty($item[$field])) {
                return $this->sanitize_post_content($item[$field]);
            }
        }
        
        // Try nested content
        if (!empty($item['content']) && is_array($item['content'])) {
            foreach ($content_fields as $field) {
                if (!empty($item['content'][$field])) {
                    return $this->sanitize_post_content($item['content'][$field]);
                }
            }
        }
        
        return '';
    }
    
    /**
     * Process post dates with enhanced parsing
     *
     * @param array $post_data Current post data
     * @param array $item Original JSON item
     * @param array $processed_data Processed field data
     * @return array Updated post data
     */
    private function process_post_dates($post_data, $item, $processed_data) {
        // Handle post_date
        if (empty($post_data['post_date'])) {
            $date_sources = array('date', 'created', 'published', 'post_date', 'created_at', 'published_at');
            
            foreach ($date_sources as $source) {
                if (!empty($item[$source])) {
                    $parsed_date = $this->convert_date_format($item[$source]);
                    if ($parsed_date) {
                        $post_data['post_date'] = $parsed_date;
                        break;
                    }
                }
            }
            
            // Try nested content
            if (empty($post_data['post_date']) && !empty($item['content']) && is_array($item['content'])) {
                foreach ($date_sources as $source) {
                    if (!empty($item['content'][$source])) {
                        $parsed_date = $this->convert_date_format($item['content'][$source]);
                        if ($parsed_date) {
                            $post_data['post_date'] = $parsed_date;
                            break;
                        }
                    }
                }
            }
            
            // Fallback to current time
            if (empty($post_data['post_date'])) {
                $post_data['post_date'] = current_time('mysql');
            }
        }
        
        // Handle post_modified
        if (empty($post_data['post_modified'])) {
            $modified_sources = array('modified', 'updated', 'post_modified', 'updated_at', 'modified_at');
            
            foreach ($modified_sources as $source) {
                if (!empty($item[$source])) {
                    $parsed_date = $this->convert_date_format($item[$source]);
                    if ($parsed_date) {
                        $post_data['post_modified'] = $parsed_date;
                        break;
                    }
                }
            }
            
            // Try nested content
            if (empty($post_data['post_modified']) && !empty($item['content']) && is_array($item['content'])) {
                foreach ($modified_sources as $source) {
                    if (!empty($item['content'][$source])) {
                        $parsed_date = $this->convert_date_format($item['content'][$source]);
                        if ($parsed_date) {
                            $post_data['post_modified'] = $parsed_date;
                            break;
                        }
                    }
                }
            }
            
            // Fallback to post_date
            if (empty($post_data['post_modified'])) {
                $post_data['post_modified'] = $post_data['post_date'];
            }
        }
        
        return $post_data;
    }
    
    /**
     * Process post content data with enhanced handling
     *
     * @param array $post_data Current post data
     * @param array $item Original JSON item
     * @param array $processed_data Processed field data
     * @return array Updated post data
     */
    private function process_post_content_data($post_data, $item, $processed_data) {
        // Enhanced content processing
        if (!empty($post_data['post_content'])) {
            $post_data['post_content'] = $this->process_content($post_data['post_content'], $item);
        }
        
        return $post_data;
    }
    
    /**
     * Validate prepared post data
     *
     * @param array $post_data Post data to validate
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    private function validate_post_data($post_data) {
        // Check required fields
        if (empty($post_data['post_title'])) {
            return new WP_Error('missing_title', 'Post title is required');
        }
        
        if (empty($post_data['post_type']) || !post_type_exists($post_data['post_type'])) {
            return new WP_Error('invalid_post_type', 'Invalid post type: ' . ($post_data['post_type'] ?? 'empty'));
        }
        
        if (empty($post_data['post_status']) || !in_array($post_data['post_status'], get_post_stati())) {
            return new WP_Error('invalid_post_status', 'Invalid post status: ' . ($post_data['post_status'] ?? 'empty'));
        }
        
        if (empty($post_data['post_author']) || !get_user_by('id', $post_data['post_author'])) {
            return new WP_Error('invalid_post_author', 'Invalid post author: ' . ($post_data['post_author'] ?? 'empty'));
        }
        
        // Validate dates
        if (!empty($post_data['post_date']) && !$this->is_valid_mysql_date($post_data['post_date'])) {
            return new WP_Error('invalid_post_date', 'Invalid post date format: ' . $post_data['post_date']);
        }
        
        if (!empty($post_data['post_modified']) && !$this->is_valid_mysql_date($post_data['post_modified'])) {
            return new WP_Error('invalid_post_modified', 'Invalid post modified date format: ' . $post_data['post_modified']);
        }
        
        return true;
    }
    
    /**
     * Process post content
     *
     * @param string $content Raw content
     * @param array $item Full item data
     * @return string Processed content
     */
    private function process_content($content, $item) {
        // Process shortcodes
        $content = do_shortcode($content);
        
        // Process embeds
        global $wp_embed;
        if ($wp_embed instanceof WP_Embed) {
            $content = $wp_embed->autoembed($content);
        }
        
        // Process image placeholders
        if (preg_match_all('/\{image:([^\}]+)\}/', $content, $matches)) {
            foreach ($matches[1] as $image_name) {
                // Handle image replacement logic here
                // $image_html = $this->get_image_html($image_name, $item);
                // $content = str_replace("{image:$image_name}", $image_html, $content);
            }
        }
        
        // Allow filtering
        return apply_filters('jpi_process_content', $content, $item);
    }

    /**
     * Get MIME type of a file
     *
     * @param string $file Path to the file
     * @return string MIME type of the file
     */
    private function get_mime_type($file) {
        $filetype = wp_check_filetype($file);
        return $filetype['type'] ?: 'application/octet-stream';
    }
    
    /**
     * Find existing post by various fields
     *
     * @param array $item JSON data item
     * @param array $options Import options
     * @return int|false Post ID if found, false otherwise
     */
    private function find_existing_post($item, $options) {
        // First try to find by ID if provided
        if (!empty($item['ID'])) {
            $post = get_post($item['ID']);
            if ($post && $post->post_type === $options['post_type']) {
                return $post->ID;
            }
        }
        
        // Then try by slug/name
        if (!empty($item['slug'])) {
            $args = array(
                'name' => $item['slug'],
                'post_type' => $options['post_type'],
                'post_status' => 'any',
                'numberposts' => 1,
                'fields' => 'ids'
            );
            
            $posts = get_posts($args);
            if (!empty($posts)) {
                return $posts[0];
            }
        }
        
        // Then try by title
        if (!empty($item['title'])) {
            $args = array(
                'title' => $item['title'],
                'post_type' => $options['post_type'],
                'post_status' => 'any',
                'numberposts' => 1,
                'fields' => 'ids'
            );
            
            $posts = get_posts($args);
            if (!empty($posts)) {
                return $posts[0];
            }
        }
        
        // Try by custom identifier if available
        if (!empty($item['meta']['_import_id'])) {
            global $wpdb;
            $post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_import_id' AND meta_value = %s LIMIT 1",
                $item['meta']['_import_id']
            ));
            
            if ($post_id) {
                return (int) $post_id;
            }
        }
        
        return false;
    }
    
    /**
     * Enhanced duplicate detection with multiple criteria and nested data support
     *
     * @param array $item JSON data item
     * @param array $options Import options with enhanced duplicate detection settings
     * @return int|false Post ID if found, false otherwise
     */
    private function find_existing_post_enhanced($item, $options) {
        $criteria = $options['duplicate_detection_criteria'] ?? array('title');
        $post_type = $options['post_type'] ?? 'post';
        
        // Store the method used for tracking
        $this->last_duplicate_detection_method = null;
        
        foreach ($criteria as $criterion) {
            $existing_id = null;
            
            switch ($criterion) {
                case 'title':
                    $existing_id = $this->find_by_title_enhanced($item, $post_type, $options);
                    if ($existing_id) {
                        $this->last_duplicate_detection_method = 'by_title';
                    }
                    break;
                    
                case 'slug':
                    $existing_id = $this->find_by_slug_enhanced($item, $post_type, $options);
                    if ($existing_id) {
                        $this->last_duplicate_detection_method = 'by_slug';
                    }
                    break;
                    
                case 'meta_field':
                    $existing_id = $this->find_by_meta_field_enhanced($item, $options);
                    if ($existing_id) {
                        $this->last_duplicate_detection_method = 'by_meta';
                    }
                    break;
                    
                case 'content_hash':
                    $existing_id = $this->find_by_content_hash_enhanced($item, $post_type, $options);
                    if ($existing_id) {
                        $this->last_duplicate_detection_method = 'by_content_hash';
                    }
                    break;
            }
            
            if ($existing_id) {
                return $existing_id;
            }
        }
        
        return false;
    }
    
    /**
     * Find existing post by title with nested data support
     */
    private function find_by_title_enhanced($item, $post_type, $options) {
        $title = $this->extract_title_from_item_enhanced($item, $options);
        
        if (empty($title)) {
            return false;
        }
        
        $args = array(
            'title' => $title,
            'post_type' => $post_type,
            'post_status' => 'any',
            'numberposts' => 1,
            'fields' => 'ids'
        );
        
        $posts = get_posts($args);
        return !empty($posts) ? $posts[0] : false;
    }
    
    /**
     * Find existing post by slug with nested data support
     */
    private function find_by_slug_enhanced($item, $post_type, $options) {
        $slug = $this->extract_slug_from_item_enhanced($item, $options);
        
        if (empty($slug)) {
            return false;
        }
        
        $args = array(
            'name' => $slug,
            'post_type' => $post_type,
            'post_status' => 'any',
            'numberposts' => 1,
            'fields' => 'ids'
        );
        
        $posts = get_posts($args);
        return !empty($posts) ? $posts[0] : false;
    }
    
    /**
     * Find existing post by meta field with nested data support
     */
    private function find_by_meta_field_enhanced($item, $options) {
        $meta_key = $options['duplicate_meta_key'] ?? '_import_id';
        $meta_value = $this->extract_meta_value_from_item_enhanced($item, $meta_key, $options);
        
        if (empty($meta_value)) {
            return false;
        }
        
        global $wpdb;
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s LIMIT 1",
            $meta_key,
            $meta_value
        ));
        
        return $post_id ? (int) $post_id : false;
    }
    
    /**
     * Find existing post by content hash with nested data support
     */
    private function find_by_content_hash_enhanced($item, $post_type, $options) {
        $content = $this->extract_content_from_item_enhanced($item, $options);
        
        if (empty($content)) {
            return false;
        }
        
        $content_hash = md5($content);
        
        global $wpdb;
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_content_hash' AND meta_value = %s LIMIT 1",
            $content_hash
        ));
        
        return $post_id ? (int) $post_id : false;
    }
    
    /**
     * Extract title from item with nested data support
     */
    private function extract_title_from_item_enhanced($item, $options) {
        // Try using nested handler if available and field mappings exist
        if (!empty($options['field_mappings']['standard']['post_title'])) {
            $title_path = $options['field_mappings']['standard']['post_title'];
            $title = $this->nested_handler->extract_nested_value($item, $title_path);
            if (!empty($title)) {
                return sanitize_text_field($title);
            }
        }
        
        // Fallback to standard extraction
        return $this->generate_post_title($item);
    }
    
    /**
     * Extract slug from item with nested data support
     */
    private function extract_slug_from_item_enhanced($item, $options) {
        // Try using nested handler if available and field mappings exist
        if (!empty($options['field_mappings']['standard']['post_name'])) {
            $slug_path = $options['field_mappings']['standard']['post_name'];
            $slug = $this->nested_handler->extract_nested_value($item, $slug_path);
            if (!empty($slug)) {
                return sanitize_title($slug);
            }
        }
        
        // Try standard slug fields
        $slug_fields = array('slug', 'post_name', 'name');
        
        foreach ($slug_fields as $field) {
            if (!empty($item[$field])) {
                return sanitize_title($item[$field]);
            }
        }
        
        // Try nested content
        if (!empty($item['content']) && is_array($item['content'])) {
            foreach ($slug_fields as $field) {
                if (!empty($item['content'][$field])) {
                    return sanitize_title($item['content'][$field]);
                }
            }
        }
        
        return '';
    }
    
    /**
     * Extract content from item with nested data support
     */
    private function extract_content_from_item_enhanced($item, $options) {
        // Try using nested handler if available and field mappings exist
        if (!empty($options['field_mappings']['standard']['post_content'])) {
            $content_path = $options['field_mappings']['standard']['post_content'];
            $content = $this->nested_handler->extract_nested_value($item, $content_path);
            if (!empty($content)) {
                return is_string($content) ? $content : wp_json_encode($content);
            }
        }
        
        // Fallback to standard extraction
        return $this->generate_post_content($item);
    }
    
    /**
     * Extract meta value from item with nested data support
     */
    private function extract_meta_value_from_item_enhanced($item, $meta_key, $options) {
        // Try using nested handler if available and field mappings exist
        if (!empty($options['field_mappings']['custom'])) {
            foreach ($options['field_mappings']['custom'] as $mapping) {
                if (isset($mapping['meta_key']) && $mapping['meta_key'] === $meta_key && !empty($mapping['field'])) {
                    $value = $this->nested_handler->extract_nested_value($item, $mapping['field']);
                    if (!empty($value)) {
                        return $value;
                    }
                }
            }
        }
        
        // Try direct field access
        if (!empty($item[$meta_key])) {
            return $item[$meta_key];
        }
        
        // Try meta array
        if (!empty($item['meta']) && is_array($item['meta']) && !empty($item['meta'][$meta_key])) {
            return $item['meta'][$meta_key];
        }
        
        // Try nested content
        if (!empty($item['content']) && is_array($item['content']) && !empty($item['content'][$meta_key])) {
            return $item['content'][$meta_key];
        }
        
        return '';
    }
    
    /**
     * Get the last duplicate detection method used
     */
    public function get_last_duplicate_detection_method() {
        return $this->last_duplicate_detection_method ?? 'none';
    }
    
    /**
     * Check if item has taxonomy data
     */
    private function has_taxonomy_data($item) {
        $taxonomy_indicators = array(
            'categories', 'tags', 'category', 'tag', 'taxonomies'
        );
        
        foreach ($taxonomy_indicators as $indicator) {
            if (!empty($item[$indicator])) {
                return true;
            }
            
            // Check nested content
            if (!empty($item['content']) && is_array($item['content']) && !empty($item['content'][$indicator])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Sanitize field value based on field type with enhanced validation and type detection
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param array $item Full item data
     * @return mixed Sanitized value
     */
    private function sanitize_field($field, $value, $item) {
        // Handle null or empty values
        if ($value === null || $value === '') {
            return $this->get_default_field_value($field);
        }
        
        switch ($field) {
            case 'post_title':
                return $this->sanitize_post_title($value);
                
            case 'post_content':
                return $this->sanitize_post_content($value);
                
            case 'post_excerpt':
                return $this->sanitize_post_excerpt($value, $item);
                
            case 'post_status':
                return $this->sanitize_post_status($value);
                
            case 'post_type':
                return $this->sanitize_post_type($value);
                
            case 'post_author':
                return $this->sanitize_post_author($value);
                
            case 'post_date':
            case 'post_modified':
                return $this->convert_date_format($value);
                
            case 'post_name':
                return $this->sanitize_post_slug($value);
                
            case 'post_parent':
                return $this->sanitize_post_parent($value);
                
            case 'menu_order':
                return $this->sanitize_menu_order($value);
                
            case 'comment_status':
            case 'ping_status':
                return $this->sanitize_status_field($value);
                
            default:
                return $this->sanitize_custom_field($field, $value);
        }
    }
    
    /**
     * Get default value for a field
     *
     * @param string $field Field name
     * @return mixed Default value
     */
    private function get_default_field_value($field) {
        $defaults = array(
            'post_title' => '',
            'post_content' => '',
            'post_excerpt' => '',
            'post_status' => $this->default_post_status,
            'post_type' => $this->default_post_type,
            'post_author' => get_current_user_id(),
            'post_date' => current_time('mysql'),
            'post_modified' => current_time('mysql'),
            'post_name' => '',
            'post_parent' => 0,
            'menu_order' => 0,
            'comment_status' => get_default_comment_status(),
            'ping_status' => get_default_comment_status('pingback'),
        );
        
        return $defaults[$field] ?? '';
    }
    
    /**
     * Sanitize post title with length validation
     *
     * @param mixed $value Title value
     * @return string Sanitized title
     */
    private function sanitize_post_title($value) {
        $title = sanitize_text_field($value);
        
        // Ensure title is not too long (WordPress limit is 255 characters)
        if (strlen($title) > 255) {
            $title = substr($title, 0, 252) . '...';
            $this->logger->warning('Post title truncated due to length', array(
                'original_length' => strlen($value),
                'truncated_title' => $title
            ));
        }
        
        return $title;
    }
    
    /**
     * Sanitize post content with enhanced processing
     *
     * @param mixed $value Content value
     * @return string Sanitized content
     */
    private function sanitize_post_content($value) {
        if (is_array($value)) {
            // Handle structured content
            if (isset($value['rendered'])) {
                $content = $value['rendered'];
            } elseif (isset($value['raw'])) {
                $content = $value['raw'];
            } else {
                $content = wp_json_encode($value);
            }
        } else {
            $content = (string) $value;
        }
        
        // Apply WordPress content filters
        $content = wp_kses_post($content);
        
        // Convert line breaks to paragraphs if needed
        if (strpos($content, '<p>') === false && strpos($content, "\n") !== false) {
            $content = wpautop($content);
        }
        
        return $content;
    }
    
    /**
     * Sanitize post excerpt with auto-generation fallback
     *
     * @param mixed $value Excerpt value
     * @param array $item Full item data for fallback generation
     * @return string Sanitized excerpt
     */
    private function sanitize_post_excerpt($value, $item) {
        if (!empty($value)) {
            $excerpt = sanitize_textarea_field($value);
        } else {
            // Auto-generate excerpt from content if not provided
            $excerpt = $this->generate_excerpt_from_content($item);
        }
        
        // Ensure excerpt is not too long (WordPress recommendation is 155 characters)
        if (strlen($excerpt) > 155) {
            $excerpt = substr($excerpt, 0, 152) . '...';
        }
        
        return $excerpt;
    }
    
    /**
     * Generate excerpt from post content
     *
     * @param array $item Full item data
     * @return string Generated excerpt
     */
    private function generate_excerpt_from_content($item) {
        $content = $item['content'] ?? $item['post_content'] ?? '';
        
        if (empty($content)) {
            return '';
        }
        
        // Strip HTML tags and shortcodes
        $content = wp_strip_all_tags(strip_shortcodes($content));
        
        // Remove extra whitespace
        $content = preg_replace('/\s+/', ' ', trim($content));
        
        // Generate excerpt
        if (strlen($content) > 155) {
            $excerpt = substr($content, 0, 152) . '...';
        } else {
            $excerpt = $content;
        }
        
        return $excerpt;
    }
    
    /**
     * Sanitize and validate post status
     *
     * @param mixed $value Status value
     * @return string Valid post status
     */
    private function sanitize_post_status($value) {
        $allowed_statuses = get_post_stati();
        $status = sanitize_key($value);
        
        if (!array_key_exists($status, $allowed_statuses)) {
            $this->logger->warning('Invalid post status provided', array(
                'provided' => $value,
                'fallback' => $this->default_post_status
            ));
            return $this->default_post_status;
        }
        
        return $status;
    }
    
    /**
     * Sanitize and validate post type
     *
     * @param mixed $value Post type value
     * @return string Valid post type
     */
    private function sanitize_post_type($value) {
        $post_type = sanitize_key($value);
        
        if (!post_type_exists($post_type)) {
            $this->logger->warning('Invalid post type provided', array(
                'provided' => $value,
                'fallback' => $this->default_post_type
            ));
            return $this->default_post_type;
        }
        
        return $post_type;
    }
    
    /**
     * Sanitize and validate post author
     *
     * @param mixed $value Author value (ID, username, or email)
     * @return int Valid user ID
     */
    private function sanitize_post_author($value) {
        if (is_numeric($value)) {
            $user = get_user_by('id', (int) $value);
            if ($user) {
                return $user->ID;
            }
        } elseif (is_string($value)) {
            // Try to find user by username or email
            $user = get_user_by('login', $value);
            if (!$user) {
                $user = get_user_by('email', $value);
            }
            if ($user) {
                return $user->ID;
            }
        }
        
        $this->logger->warning('Invalid post author provided', array(
            'provided' => $value,
            'fallback' => get_current_user_id()
        ));
        
        return get_current_user_id();
    }
    
    /**
     * Sanitize post slug
     *
     * @param mixed $value Slug value
     * @return string Sanitized slug
     */
    private function sanitize_post_slug($value) {
        return sanitize_title($value);
    }
    
    /**
     * Sanitize post parent
     *
     * @param mixed $value Parent post ID
     * @return int Valid parent post ID
     */
    private function sanitize_post_parent($value) {
        $parent_id = (int) $value;
        
        if ($parent_id > 0) {
            $parent_post = get_post($parent_id);
            if (!$parent_post) {
                $this->logger->warning('Invalid parent post ID provided', array(
                    'provided' => $value,
                    'fallback' => 0
                ));
                return 0;
            }
        }
        
        return $parent_id;
    }
    
    /**
     * Sanitize menu order
     *
     * @param mixed $value Menu order value
     * @return int Valid menu order
     */
    private function sanitize_menu_order($value) {
        return (int) $value;
    }
    
    /**
     * Sanitize status fields (comment_status, ping_status)
     *
     * @param mixed $value Status value
     * @return string Valid status
     */
    private function sanitize_status_field($value) {
        $allowed_values = array('open', 'closed');
        $status = sanitize_key($value);
        
        return in_array($status, $allowed_values, true) ? $status : 'closed';
    }
    
    /**
     * Sanitize custom field with type detection
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @return mixed Sanitized value
     */
    private function sanitize_custom_field($field, $value) {
        // Detect field type and sanitize accordingly
        $field_type = $this->detect_field_type($value);
        
        switch ($field_type) {
            case 'email':
                return sanitize_email($value);
                
            case 'url':
                return esc_url_raw($value);
                
            case 'integer':
                return (int) $value;
                
            case 'float':
                return (float) $value;
                
            case 'boolean':
                return (bool) $value;
                
            case 'date':
                return $this->convert_date_format($value);
                
            case 'array':
                return is_array($value) ? $value : array();
                
            case 'json':
                return is_string($value) ? $value : wp_json_encode($value);
                
            case 'html':
                return wp_kses_post($value);
                
            case 'text':
            default:
                return is_string($value) ? sanitize_text_field($value) : $value;
        }
    }
    
    /**
     * Detect field type based on value
     *
     * @param mixed $value Field value
     * @return string Detected field type
     */
    private function detect_field_type($value) {
        if (is_bool($value)) {
            return 'boolean';
        }
        
        if (is_int($value)) {
            return 'integer';
        }
        
        if (is_float($value)) {
            return 'float';
        }
        
        if (is_array($value)) {
            return 'array';
        }
        
        if (!is_string($value)) {
            return 'text';
        }
        
        // String-based type detection
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }
        
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return 'url';
        }
        
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? 'float' : 'integer';
        }
        
        // Check for date patterns
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value) || strtotime($value) !== false) {
            return 'date';
        }
        
        // Check for JSON
        if (is_string($value) && (substr($value, 0, 1) === '{' || substr($value, 0, 1) === '[')) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return 'json';
            }
        }
        
        // Check for HTML
        if (strip_tags($value) !== $value) {
            return 'html';
        }
        
        return 'text';
    }
    
    /**
     * Parse date string into MySQL datetime format with robust format detection
     *
     * @param string $date_string Date string to parse
     * @param string $timezone Optional timezone to use
     * @return string Formatted date string
     */
    private function parse_date($date_string, $timezone = null) {
        if (empty($date_string)) {
            return current_time('mysql');
        }
        
        $this->logger->debug('Parsing date string', array('input' => $date_string, 'timezone' => $timezone));
        
        // Common date formats to try
        $formats = array(
            'Y-m-d H:i:s',           // MySQL datetime
            'Y-m-d\TH:i:s\Z',        // ISO 8601 UTC
            'Y-m-d\TH:i:sP',         // ISO 8601 with timezone
            'Y-m-d\TH:i:s.u\Z',      // ISO 8601 with microseconds
            'Y-m-d\TH:i:s',          // ISO 8601 basic
            'Y-m-d H:i:s.u',         // MySQL with microseconds
            'Y-m-d',                 // Date only
            'Y/m/d H:i:s',           // Alternative format
            'Y/m/d',                 // Alternative date only
            'm/d/Y H:i:s',           // US format with time
            'm/d/Y',                 // US date format
            'd/m/Y H:i:s',           // European format with time
            'd/m/Y',                 // European date format
            'd-m-Y H:i:s',           // European with dashes
            'd-m-Y',                 // European date with dashes
            'M j, Y H:i:s',          // Long format with time
            'M j, Y',                // Long date format
            'F j, Y H:i:s',          // Full month name with time
            'F j, Y',                // Full month name
            'j M Y H:i:s',           // Day month year with time
            'j M Y',                 // Day month year
            'U',                     // Unix timestamp
        );
        
        // First try to parse with DateTime for better timezone handling
        $parsed_date = null;
        
        // Try parsing with DateTime
        try {
            $date_obj = new DateTime($date_string);
            
            // Apply timezone if specified
            if ($timezone) {
                try {
                    $tz = new DateTimeZone($timezone);
                    $date_obj->setTimezone($tz);
                } catch (Exception $e) {
                    $this->logger->warning('Invalid timezone specified', array(
                        'timezone' => $timezone,
                        'error' => $e->getMessage()
                    ));
                }
            }
            
            $parsed_date = $date_obj->format('Y-m-d H:i:s');
            
        } catch (Exception $e) {
            $this->logger->debug('DateTime parsing failed, trying manual formats', array(
                'error' => $e->getMessage()
            ));
            
            // Try manual format parsing
            foreach ($formats as $format) {
                $date_obj = DateTime::createFromFormat($format, $date_string);
                if ($date_obj !== false) {
                    $parsed_date = $date_obj->format('Y-m-d H:i:s');
                    $this->logger->debug('Successfully parsed with format', array(
                        'format' => $format,
                        'result' => $parsed_date
                    ));
                    break;
                }
            }
        }
        
        // Fallback to strtotime
        if (!$parsed_date) {
            $timestamp = strtotime($date_string);
            if ($timestamp !== false) {
                $parsed_date = date('Y-m-d H:i:s', $timestamp);
                $this->logger->debug('Parsed with strtotime', array('result' => $parsed_date));
            }
        }
        
        // Final fallback to current time
        if (!$parsed_date) {
            $parsed_date = current_time('mysql');
            $this->logger->warning('Could not parse date, using current time', array(
                'input' => $date_string,
                'fallback' => $parsed_date
            ));
        }
        
        // Validate the parsed date
        if (!$this->is_valid_mysql_date($parsed_date)) {
            $parsed_date = current_time('mysql');
            $this->logger->warning('Parsed date is invalid, using current time', array(
                'invalid_date' => $parsed_date,
                'fallback' => $parsed_date
            ));
        }
        
        return $parsed_date;
    }
    
    /**
     * Validate MySQL datetime format
     *
     * @param string $date Date string to validate
     * @return bool Whether the date is valid
     */
    private function is_valid_mysql_date($date) {
        if (empty($date)) {
            return false;
        }
        
        $date_obj = DateTime::createFromFormat('Y-m-d H:i:s', $date);
        return $date_obj !== false && $date_obj->format('Y-m-d H:i:s') === $date;
    }
    
    /**
     * Detect and convert various date formats
     *
     * @param mixed $date_value Date value in various formats
     * @param array $options Parsing options
     * @return string|null Formatted date string or null if invalid
     */
    public function convert_date_format($date_value, $options = array()) {
        if (empty($date_value)) {
            return null;
        }
        
        $options = wp_parse_args($options, array(
            'timezone' => null,
            'default_time' => '00:00:00',
            'fallback_to_current' => false,
        ));
        
        // Handle different input types
        if (is_numeric($date_value)) {
            // Unix timestamp
            $timestamp = (int) $date_value;
            
            // Check if it's in milliseconds (JavaScript timestamp)
            if ($timestamp > 9999999999) {
                $timestamp = $timestamp / 1000;
            }
            
            return date('Y-m-d H:i:s', $timestamp);
        }
        
        if (is_string($date_value)) {
            return $this->parse_date($date_value, $options['timezone']);
        }
        
        if (is_array($date_value)) {
            // Handle array format like ['year' => 2023, 'month' => 1, 'day' => 15]
            $year = $date_value['year'] ?? $date_value['y'] ?? date('Y');
            $month = $date_value['month'] ?? $date_value['m'] ?? 1;
            $day = $date_value['day'] ?? $date_value['d'] ?? 1;
            $hour = $date_value['hour'] ?? $date_value['h'] ?? 0;
            $minute = $date_value['minute'] ?? $date_value['i'] ?? 0;
            $second = $date_value['second'] ?? $date_value['s'] ?? 0;
            
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
            }
        }
        
        return $options['fallback_to_current'] ? current_time('mysql') : null;
    }
    
    /**
     * Process taxonomies for a post with hierarchy support and term creation
     *
     * @param int $post_id Post ID
     * @param array $item JSON data item
     * @param array $options Import options
     * @param array $processed_data Processed field data from nested handler
     */
    private function process_taxonomies($post_id, $item, $options, $processed_data = array()) {
        $create_terms = $options['create_terms'] ?? true;
        $taxonomy_stats = array('processed' => 0, 'errors' => 0);
        
        // Use processed taxonomy data if available
        if (!empty($processed_data['taxonomies'])) {
            foreach ($processed_data['taxonomies'] as $taxonomy => $terms) {
                try {
                    $result = $this->process_single_taxonomy($post_id, $taxonomy, $terms, $create_terms);
                    if ($result !== false) {
                        $taxonomy_stats['processed']++;
                    } else {
                        $taxonomy_stats['errors']++;
                    }
                } catch (Exception $e) {
                    $taxonomy_stats['errors']++;
                    $this->logger->error('Error processing taxonomy', array(
                        'post_id' => $post_id,
                        'taxonomy' => $taxonomy,
                        'error' => $e->getMessage()
                    ));
                }
            }
        } else {
            // Legacy taxonomy processing for backward compatibility
            if (!empty($item['taxonomies']) && is_array($item['taxonomies'])) {
                foreach ($item['taxonomies'] as $taxonomy => $terms) {
                    try {
                        $result = $this->process_single_taxonomy($post_id, $taxonomy, $terms, $create_terms);
                        if ($result !== false) {
                            $taxonomy_stats['processed']++;
                        } else {
                            $taxonomy_stats['errors']++;
                        }
                    } catch (Exception $e) {
                        $taxonomy_stats['errors']++;
                        $this->logger->error('Error processing legacy taxonomy', array(
                            'post_id' => $post_id,
                            'taxonomy' => $taxonomy,
                            'error' => $e->getMessage()
                        ));
                    }
                }
            }
            
            // Also check for direct category and tag fields
            if (!empty($item['categories'])) {
                try {
                    $result = $this->process_single_taxonomy($post_id, 'category', $item['categories'], $create_terms);
                    if ($result !== false) {
                        $taxonomy_stats['processed']++;
                    } else {
                        $taxonomy_stats['errors']++;
                    }
                } catch (Exception $e) {
                    $taxonomy_stats['errors']++;
                    $this->logger->error('Error processing categories', array(
                        'post_id' => $post_id,
                        'error' => $e->getMessage()
                    ));
                }
            }
            
            if (!empty($item['tags'])) {
                try {
                    $result = $this->process_single_taxonomy($post_id, 'post_tag', $item['tags'], $create_terms);
                    if ($result !== false) {
                        $taxonomy_stats['processed']++;
                    } else {
                        $taxonomy_stats['errors']++;
                    }
                } catch (Exception $e) {
                    $taxonomy_stats['errors']++;
                    $this->logger->error('Error processing tags', array(
                        'post_id' => $post_id,
                        'error' => $e->getMessage()
                    ));
                }
            }
        }
        
        return $taxonomy_stats;
    }
    
    /**
     * Process a single taxonomy for a post
     *
     * @param int $post_id Post ID
     * @param string $taxonomy Taxonomy name
     * @param mixed $terms Terms data (string, array, or hierarchical array)
     * @param bool $create_terms Whether to create terms if they don't exist
     * @return bool Success status
     */
    private function process_single_taxonomy($post_id, $taxonomy, $terms, $create_terms = true) {
        if (!taxonomy_exists($taxonomy)) {
            $this->logger->warning("Taxonomy does not exist: {$taxonomy}");
            return false;
        }
        
        $this->logger->debug('Processing taxonomy', array(
            'post_id' => $post_id,
            'taxonomy' => $taxonomy,
            'terms' => $terms,
            'create_terms' => $create_terms
        ));
        
        // Normalize terms to array
        $term_list = $this->normalize_taxonomy_terms($terms);
        
        if (empty($term_list)) {
            $this->logger->debug('No terms to process for taxonomy', array('taxonomy' => $taxonomy));
            return true; // Not an error, just no terms to process
        }
        
        $term_ids = array();
        
        foreach ($term_list as $term_data) {
            $term_id = $this->process_taxonomy_term($taxonomy, $term_data, $create_terms);
            
            if ($term_id && !is_wp_error($term_id)) {
                $term_ids[] = $term_id;
            }
        }
        
        if (!empty($term_ids)) {
            $result = wp_set_object_terms($post_id, $term_ids, $taxonomy);
            
            if (is_wp_error($result)) {
                $this->logger->error("Failed to set taxonomy terms", array(
                    'post_id' => $post_id,
                    'taxonomy' => $taxonomy,
                    'term_ids' => $term_ids,
                    'error' => $result->get_error_message()
                ));
                return false;
            } else {
                $this->logger->debug("Successfully set taxonomy terms", array(
                    'post_id' => $post_id,
                    'taxonomy' => $taxonomy,
                    'term_ids' => $term_ids,
                    'assigned_terms' => $result
                ));
                return true;
            }
        }
        
        return true; // No terms to set, but not an error
    }
    
    /**
     * Normalize taxonomy terms to a consistent array format
     *
     * @param mixed $terms Terms in various formats
     * @return array Normalized terms array
     */
    private function normalize_taxonomy_terms($terms) {
        if (empty($terms)) {
            return array();
        }
        
        // Handle string format (comma-separated)
        if (is_string($terms)) {
            return array_map('trim', array_filter(explode(',', $terms)));
        }
        
        // Handle simple array
        if (is_array($terms) && !$this->is_hierarchical_term_array($terms)) {
            return array_map('trim', array_filter($terms));
        }
        
        // Handle hierarchical array format
        if (is_array($terms)) {
            return $this->flatten_hierarchical_terms($terms);
        }
        
        return array();
    }
    
    /**
     * Check if array contains hierarchical term data
     *
     * @param array $terms Terms array
     * @return bool Whether the array contains hierarchical data
     */
    private function is_hierarchical_term_array($terms) {
        if (!is_array($terms)) {
            return false;
        }
        
        foreach ($terms as $term) {
            if (is_array($term) && (isset($term['name']) || isset($term['children']))) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Flatten hierarchical terms array
     *
     * @param array $terms Hierarchical terms array
     * @param int $parent_id Parent term ID
     * @return array Flattened terms with hierarchy info
     */
    private function flatten_hierarchical_terms($terms, $parent_id = 0) {
        $flattened = array();
        
        foreach ($terms as $term) {
            if (is_string($term)) {
                $flattened[] = array(
                    'name' => trim($term),
                    'parent' => $parent_id
                );
            } elseif (is_array($term)) {
                $term_name = $term['name'] ?? $term['term'] ?? '';
                
                if (!empty($term_name)) {
                    $term_data = array(
                        'name' => trim($term_name),
                        'parent' => $parent_id,
                        'slug' => $term['slug'] ?? '',
                        'description' => $term['description'] ?? ''
                    );
                    
                    $flattened[] = $term_data;
                    
                    // Process children if they exist
                    if (!empty($term['children']) && is_array($term['children'])) {
                        // We'll need to create the parent first to get its ID
                        // This will be handled in process_taxonomy_term
                        $term_data['children'] = $term['children'];
                        $flattened[count($flattened) - 1] = $term_data;
                    }
                }
            }
        }
        
        return $flattened;
    }
    
    /**
     * Process a single taxonomy term with hierarchy support
     *
     * @param string $taxonomy Taxonomy name
     * @param array|string $term_data Term data
     * @param bool $create_terms Whether to create terms if they don't exist
     * @return int|WP_Error Term ID or error
     */
    private function process_taxonomy_term($taxonomy, $term_data, $create_terms = true) {
        // Handle simple string term
        if (is_string($term_data)) {
            $term_data = array('name' => trim($term_data));
        }
        
        if (empty($term_data['name'])) {
            return new WP_Error('empty_term_name', 'Term name cannot be empty');
        }
        
        $term_name = $term_data['name'];
        $parent_id = $term_data['parent'] ?? 0;
        $term_slug = $term_data['slug'] ?? '';
        $term_description = $term_data['description'] ?? '';
        
        // Check if term already exists
        $existing_term = get_term_by('name', $term_name, $taxonomy);
        
        if ($existing_term) {
            $this->logger->debug('Using existing term', array(
                'taxonomy' => $taxonomy,
                'term_name' => $term_name,
                'term_id' => $existing_term->term_id
            ));
            
            // Process children if they exist
            if (!empty($term_data['children'])) {
                $this->process_child_terms($taxonomy, $term_data['children'], $existing_term->term_id, $create_terms);
            }
            
            return $existing_term->term_id;
        }
        
        // Create new term if allowed
        if (!$create_terms) {
            $this->logger->warning('Term does not exist and creation is disabled', array(
                'taxonomy' => $taxonomy,
                'term_name' => $term_name
            ));
            return new WP_Error('term_not_found', "Term '{$term_name}' not found and creation is disabled");
        }
        
        // Prepare term arguments
        $term_args = array();
        
        if (!empty($term_slug)) {
            $term_args['slug'] = $term_slug;
        }
        
        if (!empty($term_description)) {
            $term_args['description'] = $term_description;
        }
        
        if ($parent_id > 0) {
            $term_args['parent'] = $parent_id;
        }
        
        // Create the term
        $result = wp_insert_term($term_name, $taxonomy, $term_args);
        
        if (is_wp_error($result)) {
            $this->logger->error('Failed to create term', array(
                'taxonomy' => $taxonomy,
                'term_name' => $term_name,
                'term_args' => $term_args,
                'error' => $result->get_error_message()
            ));
            return $result;
        }
        
        $term_id = $result['term_id'];
        
        $this->logger->debug('Created new term', array(
            'taxonomy' => $taxonomy,
            'term_name' => $term_name,
            'term_id' => $term_id,
            'parent_id' => $parent_id
        ));
        
        // Process children if they exist
        if (!empty($term_data['children'])) {
            $this->process_child_terms($taxonomy, $term_data['children'], $term_id, $create_terms);
        }
        
        return $term_id;
    }
    
    /**
     * Process child terms for hierarchical taxonomies
     *
     * @param string $taxonomy Taxonomy name
     * @param array $children Child terms data
     * @param int $parent_id Parent term ID
     * @param bool $create_terms Whether to create terms if they don't exist
     */
    private function process_child_terms($taxonomy, $children, $parent_id, $create_terms = true) {
        if (empty($children) || !is_array($children)) {
            return;
        }
        
        foreach ($children as $child_term) {
            if (is_string($child_term)) {
                $child_data = array(
                    'name' => $child_term,
                    'parent' => $parent_id
                );
            } elseif (is_array($child_term)) {
                $child_data = $child_term;
                $child_data['parent'] = $parent_id;
            } else {
                continue;
            }
            
            $this->process_taxonomy_term($taxonomy, $child_data, $create_terms);
        }
    }
    
    /**
     * Process meta fields for a post
     *
     * @param int $post_id Post ID
     * @param array $item JSON data item
     * @param array $options Import options
     * @param array $processed_data Processed field data from nested handler
     */
    private function process_meta_fields($post_id, $item, $options, $processed_data = array()) {
        $field_stats = array(
            'yoast_seo' => array('processed' => 0, 'errors' => 0),
            'custom' => array('processed' => 0, 'errors' => 0),
            'wrapper_metadata' => array('processed' => 0, 'errors' => 0)
        );
        
        // Process Yoast SEO meta fields using the enhanced integration
        if (!empty($processed_data['yoast_seo'])) {
            try {
                $yoast_results = $this->yoast_seo->process_yoast_fields($post_id, $processed_data['yoast_seo'], $options);
                
                $field_stats['yoast_seo']['processed'] = $yoast_results['processed'];
                $field_stats['yoast_seo']['errors'] = count($yoast_results['errors']);
                
                $this->logger->debug("Processed Yoast SEO fields", array(
                    'post_id' => $post_id,
                    'processed' => $yoast_results['processed'],
                    'skipped' => $yoast_results['skipped'],
                    'errors' => $yoast_results['errors']
                ));
                
                // Log any errors
                if (!empty($yoast_results['errors'])) {
                    foreach ($yoast_results['errors'] as $error) {
                        $this->logger->error("Yoast SEO processing error: " . $error, array('post_id' => $post_id));
                    }
                }
            } catch (Exception $e) {
                $field_stats['yoast_seo']['errors']++;
                $this->logger->error("Exception processing Yoast SEO fields", array(
                    'post_id' => $post_id,
                    'error' => $e->getMessage()
                ));
            }
        }
        
        // Process custom meta fields
        if (!empty($processed_data['custom'])) {
            foreach ($processed_data['custom'] as $key => $value) {
                try {
                    // Skip protected meta
                    if (is_protected_meta($key, 'post')) {
                        $this->logger->warning("Skipping protected meta key: {$key}");
                        continue;
                    }
                    
                    // Handle ACF fields if ACF is active
                    if (function_exists('update_field') && strpos($key, '_') !== 0) {
                        update_field($key, $value, $post_id);
                    } else {
                        update_post_meta($post_id, $key, $value);
                    }
                    
                    $field_stats['custom']['processed']++;
                    
                    $this->logger->debug("Set custom meta", array(
                        'post_id' => $post_id,
                        'key' => $key,
                        'value' => $value
                    ));
                } catch (Exception $e) {
                    $field_stats['custom']['errors']++;
                    $this->logger->error("Error processing custom meta field", array(
                        'post_id' => $post_id,
                        'key' => $key,
                        'error' => $e->getMessage()
                    ));
                }
            }
        }
        
        // Process wrapper metadata if enabled
        if (!empty($processed_data['wrapper_metadata'])) {
            foreach ($processed_data['wrapper_metadata'] as $key => $value) {
                try {
                    $meta_key = '_' . $key; // Prefix with underscore to make it private
                    update_post_meta($post_id, $meta_key, $value);
                    $field_stats['wrapper_metadata']['processed']++;
                    
                    $this->logger->debug("Set wrapper metadata", array(
                        'post_id' => $post_id,
                        'key' => $meta_key,
                        'value' => $value
                    ));
                } catch (Exception $e) {
                    $field_stats['wrapper_metadata']['errors']++;
                    $this->logger->error("Error processing wrapper metadata", array(
                        'post_id' => $post_id,
                        'key' => $key,
                        'error' => $e->getMessage()
                    ));
                }
            }
        }
        
        // Legacy meta processing for backward compatibility
        if (empty($processed_data) && !empty($item['meta']) && is_array($item['meta'])) {
            foreach ($item['meta'] as $key => $value) {
                try {
                    // Skip protected meta
                    if (is_protected_meta($key, 'post')) {
                        continue;
                    }
                    
                    // Handle ACF fields if ACF is active
                    if (function_exists('update_field') && strpos($key, '_') !== 0) {
                        update_field($key, $value, $post_id);
                    } else {
                        update_post_meta($post_id, $key, $value);
                    }
                    
                    $field_stats['custom']['processed']++;
                } catch (Exception $e) {
                    $field_stats['custom']['errors']++;
                    $this->logger->error("Error processing legacy meta field", array(
                        'post_id' => $post_id,
                        'key' => $key,
                        'error' => $e->getMessage()
                    ));
                }
            }
        }
        
        return $field_stats;
    }
    
    /**
     * Process featured image for a post with enhanced support for URLs and base64 data
     *
     * @param int $post_id Post ID
     * @param string|array $image_data Image URL, base64 data, or array with image data
     * @param array $options Import options
     * @return int|WP_Error Attachment ID or error
     */
    private function process_featured_image($post_id, $image_data, $options) {
        if (empty($image_data)) {
            return new WP_Error('empty_image_data', 'No image data provided');
        }
        
        $this->logger->debug('Processing featured image', array(
            'post_id' => $post_id,
            'image_data_type' => gettype($image_data)
        ));
        
        // Handle different image data formats
        if (is_string($image_data)) {
            // Check if it's base64 encoded data
            if ($this->is_base64_image($image_data)) {
                $attachment_id = $this->upload_base64_image($image_data, $post_id);
            } else {
                // Assume it's a URL
                $attachment_id = $this->upload_media_from_url($image_data, $post_id);
            }
        } elseif (is_array($image_data)) {
            // Handle array format with URL or base64 data
            $image_source = $image_data['url'] ?? $image_data['data'] ?? $image_data['src'] ?? '';
            
            if (empty($image_source)) {
                return new WP_Error('no_image_source', 'No image source found in image data array');
            }
            
            if ($this->is_base64_image($image_source)) {
                $attachment_id = $this->upload_base64_image($image_source, $post_id, $image_data);
            } else {
                $attachment_id = $this->upload_media_from_url($image_source, $post_id, $image_data);
            }
        } else {
            return new WP_Error('invalid_image_data', 'Invalid image data format');
        }
        
        if (is_wp_error($attachment_id)) {
            $this->logger->error('Failed to process featured image', array(
                'post_id' => $post_id,
                'error' => $attachment_id->get_error_message()
            ));
            return $attachment_id;
        }
        
        // Set as featured image
        $result = set_post_thumbnail($post_id, $attachment_id);
        
        if (!$result) {
            $this->logger->error('Failed to set featured image', array(
                'post_id' => $post_id,
                'attachment_id' => $attachment_id
            ));
            return new WP_Error('set_thumbnail_failed', 'Failed to set featured image');
        }
        
        $this->logger->debug('Successfully set featured image', array(
            'post_id' => $post_id,
            'attachment_id' => $attachment_id
        ));
        
        return $attachment_id;
    }
    
    /**
     * Check if a string is base64 encoded image data
     *
     * @param string $data The data to check
     * @return bool Whether the data is base64 encoded image
     */
    private function is_base64_image($data) {
        if (!is_string($data)) {
            return false;
        }
        
        // Check for data URI format
        if (preg_match('/^data:image\/[a-zA-Z]+;base64,/', $data)) {
            return true;
        }
        
        // Check for plain base64 that looks like image data
        if (base64_decode($data, true) !== false) {
            // Additional check to see if it's likely image data
            $decoded = base64_decode($data);
            $image_info = @getimagesizefromstring($decoded);
            return $image_info !== false;
        }
        
        return false;
    }
    
    /**
     * Upload base64 encoded image data
     *
     * @param string $base64_data Base64 encoded image data
     * @param int $post_id Post ID for attachment
     * @param array $metadata Optional metadata for the image
     * @return int|WP_Error Attachment ID or error
     */
    private function upload_base64_image($base64_data, $post_id = 0, $metadata = array()) {
        // Extract MIME type and data from data URI if present
        if (preg_match('/^data:image\/([a-zA-Z]+);base64,(.+)$/', $base64_data, $matches)) {
            $image_type = $matches[1];
            $image_data = base64_decode($matches[2]);
        } else {
            // Plain base64 data, try to detect type
            $image_data = base64_decode($base64_data);
            $image_info = @getimagesizefromstring($image_data);
            
            if ($image_info === false) {
                return new WP_Error('invalid_base64_image', 'Invalid base64 image data');
            }
            
            $image_type = str_replace('image/', '', $image_info['mime']);
        }
        
        if (empty($image_data)) {
            return new WP_Error('empty_image_data', 'Empty image data after base64 decode');
        }
        
        // Generate filename
        $filename = $metadata['filename'] ?? 'imported-image-' . time() . '.' . $image_type;
        
        // Ensure proper file extension
        if (!preg_match('/\.' . preg_quote($image_type, '/') . '$/i', $filename)) {
            $filename .= '.' . $image_type;
        }
        
        // Create temporary file
        $upload_dir = wp_upload_dir();
        $temp_file = $upload_dir['path'] . '/' . wp_unique_filename($upload_dir['path'], $filename);
        
        if (file_put_contents($temp_file, $image_data) === false) {
            return new WP_Error('file_write_failed', 'Failed to write image data to temporary file');
        }
        
        // Prepare attachment data
        $attachment_data = array(
            'post_mime_type' => 'image/' . $image_type,
            'post_title' => $metadata['title'] ?? preg_replace('/\.[^.]+$/', '', basename($filename)),
            'post_content' => $metadata['description'] ?? '',
            'post_excerpt' => $metadata['caption'] ?? '',
            'post_status' => 'inherit',
            'post_parent' => $post_id,
        );
        
        // Insert attachment
        $attachment_id = wp_insert_attachment($attachment_data, $temp_file, $post_id);
        
        if (is_wp_error($attachment_id)) {
            @unlink($temp_file);
            return $attachment_id;
        }
        
        // Generate attachment metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $temp_file);
        wp_update_attachment_metadata($attachment_id, $attachment_metadata);
        
        // Set alt text if provided
        if (!empty($metadata['alt'])) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($metadata['alt']));
        }
        
        $this->logger->debug('Successfully uploaded base64 image', array(
            'attachment_id' => $attachment_id,
            'filename' => $filename,
            'post_id' => $post_id
        ));
        
        return $attachment_id;
    }
    
    /**
     * Upload media from URL with enhanced error handling and metadata support
     *
     * @param string $url Image URL
     * @param int $post_id Post ID for attachment
     * @param array $metadata Optional metadata for the image
     * @return int|WP_Error Attachment ID or error
     */
    private function upload_media_from_url($url, $post_id = 0, $metadata = array()) {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', 'Invalid image URL provided');
        }
        
        $this->logger->debug('Uploading media from URL', array('url' => $url, 'post_id' => $post_id));
        
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // Download the file
        $temp_file = download_url($url);
        
        if (is_wp_error($temp_file)) {
            $this->logger->error('Failed to download image', array(
                'url' => $url,
                'error' => $temp_file->get_error_message()
            ));
            return $temp_file;
        }
        
        // Get file info
        $file_info = wp_check_filetype(basename($url));
        $filename = $metadata['filename'] ?? basename(parse_url($url, PHP_URL_PATH));
        
        // Ensure we have a valid filename
        if (empty($filename) || $filename === '/') {
            $filename = 'imported-image-' . time();
        }
        
        // Add extension if missing
        if (!empty($file_info['ext']) && !preg_match('/\.' . preg_quote($file_info['ext'], '/') . '$/i', $filename)) {
            $filename .= '.' . $file_info['ext'];
        }
        
        // Prepare file array for media_handle_sideload
        $file_array = array(
            'name' => $filename,
            'tmp_name' => $temp_file,
            'type' => $file_info['type'] ?: 'image/jpeg',
        );
        
        // Prepare attachment data
        $attachment_data = array(
            'post_title' => $metadata['title'] ?? preg_replace('/\.[^.]+$/', '', basename($filename)),
            'post_content' => $metadata['description'] ?? '',
            'post_excerpt' => $metadata['caption'] ?? '',
        );
        
        // Upload the file
        $attachment_id = media_handle_sideload($file_array, $post_id, null, $attachment_data);
        
        // Clean up temp file
        @unlink($temp_file);
        
        if (is_wp_error($attachment_id)) {
            $this->logger->error('Failed to upload media', array(
                'url' => $url,
                'error' => $attachment_id->get_error_message()
            ));
            return $attachment_id;
        }
        
        // Set alt text if provided
        if (!empty($metadata['alt'])) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($metadata['alt']));
        }
        
        $this->logger->debug('Successfully uploaded media from URL', array(
            'url' => $url,
            'attachment_id' => $attachment_id,
            'post_id' => $post_id
        ));
        
        return $attachment_id;
    }
    
    /**
     * Process attachments for a post with enhanced support
     *
     * @param int $post_id Post ID
     * @param array $attachments Array of attachment data
     * @param array $options Import options
     * @return array Results of attachment processing
     */
    private function process_attachments($post_id, $attachments, $options) {
        if (empty($attachments) || !is_array($attachments)) {
            return array();
        }
        
        $results = array();
        
        foreach ($attachments as $index => $attachment_data) {
            $this->logger->debug('Processing attachment', array(
                'post_id' => $post_id,
                'index' => $index,
                'attachment_data' => $attachment_data
            ));
            
            if (is_string($attachment_data)) {
                // Simple URL or base64 string
                $attachment_id = $this->process_single_attachment($post_id, $attachment_data);
            } elseif (is_array($attachment_data)) {
                // Structured attachment data
                $attachment_id = $this->process_single_attachment($post_id, $attachment_data);
            } else {
                $this->logger->warning('Invalid attachment data format', array(
                    'post_id' => $post_id,
                    'index' => $index,
                    'type' => gettype($attachment_data)
                ));
                continue;
            }
            
            $results[$index] = array(
                'attachment_id' => $attachment_id,
                'success' => !is_wp_error($attachment_id),
                'error' => is_wp_error($attachment_id) ? $attachment_id->get_error_message() : null
            );
        }
        
        return $results;
    }
    
    /**
     * Process a single attachment
     *
     * @param int $post_id Post ID
     * @param mixed $attachment_data Attachment data (URL, base64, or array)
     * @return int|WP_Error Attachment ID or error
     */
    private function process_single_attachment($post_id, $attachment_data) {
        if (is_string($attachment_data)) {
            if ($this->is_base64_image($attachment_data)) {
                return $this->upload_base64_image($attachment_data, $post_id);
            } else {
                return $this->upload_media_from_url($attachment_data, $post_id);
            }
        } elseif (is_array($attachment_data)) {
            $source = $attachment_data['url'] ?? $attachment_data['src'] ?? $attachment_data['data'] ?? '';
            
            if (empty($source)) {
                return new WP_Error('no_attachment_source', 'No attachment source found');
            }
            
            if ($this->is_base64_image($source)) {
                return $this->upload_base64_image($source, $post_id, $attachment_data);
            } else {
                return $this->upload_media_from_url($source, $post_id, $attachment_data);
            }
        }
        
        return new WP_Error('invalid_attachment_data', 'Invalid attachment data format');
    }

    /**
     * Get attachment ID by URL
     */
    private function get_attachment_id_by_url($url) {
        global $wpdb;
        
        // Get the upload directory
        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'];
        
        // Remove size suffixes if present
        $url = preg_replace('/-\d+x\d+(?=\.[a-z]{3,4}$)/i', '', $url);
        
        // Try to find the attachment
        $attachment = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s",
            '%' . basename($url) . '%'
        ));
        
        return !empty($attachment) ? $attachment[0] : false;
    }

    /**
     * Validate comprehensive field mappings before import
     *
     * @param array $field_mappings Field mapping configuration
     * @param array $sample_data Sample JSON data for validation
     * @return array|WP_Error Validation results or error
     */
    public function validate_field_mappings($field_mappings, $sample_data = array()) {
        $validation_results = array(
            'valid' => true,
            'errors' => array(),
            'warnings' => array(),
            'suggestions' => array()
        );
        
        // Validate standard field mappings
        if (!empty($field_mappings['standard'])) {
            foreach ($field_mappings['standard'] as $wp_field => $json_path) {
                $result = $this->validate_single_field_mapping($wp_field, $json_path, $sample_data, 'standard');
                if (!$result['valid']) {
                    $validation_results['valid'] = false;
                    $validation_results['errors'][] = $result['error'];
                }
                if (!empty($result['warning'])) {
                    $validation_results['warnings'][] = $result['warning'];
                }
            }
        }
        
        // Validate taxonomy mappings
        if (!empty($field_mappings['taxonomies'])) {
            foreach ($field_mappings['taxonomies'] as $taxonomy_mapping) {
                if (empty($taxonomy_mapping['taxonomy'])) {
                    $validation_results['valid'] = false;
                    $validation_results['errors'][] = 'Taxonomy mapping missing taxonomy name';
                    continue;
                }
                
                if (!taxonomy_exists($taxonomy_mapping['taxonomy'])) {
                    $validation_results['valid'] = false;
                    $validation_results['errors'][] = "Taxonomy does not exist: {$taxonomy_mapping['taxonomy']}";
                }
            }
        }
        
        // Validate custom field mappings
        if (!empty($field_mappings['custom'])) {
            foreach ($field_mappings['custom'] as $custom_mapping) {
                if (empty($custom_mapping['meta_key'])) {
                    $validation_results['valid'] = false;
                    $validation_results['errors'][] = 'Custom field mapping missing meta_key';
                }
                
                if (is_protected_meta($custom_mapping['meta_key'], 'post')) {
                    $validation_results['warnings'][] = "Meta key is protected: {$custom_mapping['meta_key']}";
                }
            }
        }
        
        // Check for required field mappings
        $required_fields = array('post_title');
        foreach ($required_fields as $required_field) {
            if (empty($field_mappings['standard'][$required_field])) {
                $validation_results['suggestions'][] = "Consider mapping a field to {$required_field} for better results";
            }
        }
        
        return $validation_results;
    }
    
    /**
     * Validate a single field mapping
     *
     * @param string $wp_field WordPress field name
     * @param string $json_path JSON path
     * @param array $sample_data Sample data for testing
     * @param string $mapping_type Type of mapping (standard, custom, etc.)
     * @return array Validation result
     */
    private function validate_single_field_mapping($wp_field, $json_path, $sample_data, $mapping_type) {
        $result = array(
            'valid' => true,
            'error' => null,
            'warning' => null
        );
        
        // Validate WordPress field
        if ($mapping_type === 'standard') {
            $valid_wp_fields = array_values($this->field_mappings);
            $valid_wp_fields[] = 'post_title';
            $valid_wp_fields[] = 'post_content';
            $valid_wp_fields[] = 'post_excerpt';
            $valid_wp_fields[] = 'post_date';
            $valid_wp_fields[] = 'post_modified';
            
            if (!in_array($wp_field, $valid_wp_fields)) {
                $result['valid'] = false;
                $result['error'] = "Invalid WordPress field: {$wp_field}";
                return $result;
            }
        }
        
        // Validate JSON path format
        if (!empty($json_path)) {
            $path_validation = $this->nested_handler->validate_field_path($json_path);
            if (is_wp_error($path_validation)) {
                $result['valid'] = false;
                $result['error'] = "Invalid JSON path '{$json_path}': " . $path_validation->get_error_message();
                return $result;
            }
            
            // Test path against sample data if available
            if (!empty($sample_data)) {
                $value = $this->nested_handler->extract_nested_value($sample_data, $json_path);
                if ($value === null) {
                    $result['warning'] = "JSON path '{$json_path}' not found in sample data";
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Get comprehensive field mapping suggestions based on JSON structure
     *
     * @param array $json_data Sample JSON data
     * @return array Suggested field mappings
     */
    public function suggest_field_mappings($json_data) {
        $suggestions = array(
            'standard' => array(),
            'yoast_seo' => array(),
            'custom' => array(),
            'taxonomies' => array()
        );
        
        // Extract all possible paths
        $all_paths = $this->nested_handler->extract_all_field_paths($json_data);
        
        // Suggest standard field mappings
        $standard_suggestions = array(
            'post_title' => array('title', 'name', 'heading', 'subject', 'content.title'),
            'post_content' => array('content', 'body', 'description', 'text', 'content.description', 'content.body'),
            'post_excerpt' => array('excerpt', 'summary', 'content.excerpt', 'content.summary'),
            'post_date' => array('date', 'created', 'published', 'content.date', 'content.created'),
            'post_status' => array('status', 'post_status', 'content.status'),
        );
        
        foreach ($standard_suggestions as $wp_field => $possible_paths) {
            foreach ($possible_paths as $path) {
                if (isset($all_paths[$path])) {
                    $suggestions['standard'][$wp_field] = $path;
                    break;
                }
            }
        }
        
        // Suggest Yoast SEO mappings
        $yoast_suggestions = array(
            '_yoast_wpseo_title' => array('seo_title', 'yoast_title', 'content.yoast_seo_title', 'content.seo_title'),
            '_yoast_wpseo_metadesc' => array('seo_description', 'meta_description', 'content.yoast_seo_description', 'content.meta_description'),
            '_yoast_wpseo_focuskw' => array('focus_keyword', 'keyword', 'content.yoast_focus_keyword', 'content.focus_keyword'),
        );
        
        foreach ($yoast_suggestions as $yoast_field => $possible_paths) {
            foreach ($possible_paths as $path) {
                if (isset($all_paths[$path])) {
                    $suggestions['yoast_seo'][$yoast_field] = $path;
                    break;
                }
            }
        }
        
        // Suggest taxonomy mappings
        $taxonomy_suggestions = array(
            'category' => array('categories', 'category', 'content.categories'),
            'post_tag' => array('tags', 'tag', 'content.tags'),
        );
        
        foreach ($taxonomy_suggestions as $taxonomy => $possible_paths) {
            foreach ($possible_paths as $path) {
                if (isset($all_paths[$path])) {
                    $suggestions['taxonomies'][] = array(
                        'taxonomy' => $taxonomy,
                        'field' => $path
                    );
                    break;
                }
            }
        }
        
        // Suggest custom field mappings for wrapper metadata
        $wrapper_fields = array('domain_name', 'user_id', 'email', 'domain_lang', 'type');
        foreach ($wrapper_fields as $field) {
            if (isset($all_paths[$field])) {
                $suggestions['custom'][] = array(
                    'meta_key' => '_' . $field,
                    'field' => $field
                );
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Set Yoast SEO meta data (legacy method for backward compatibility)
     */
    private function set_yoast_meta($post_id, $content) {
        if (!function_exists('YoastSEO')) {
            return;
        }

        $yoast_meta = array(
            '_yoast_wpseo_title' => $content['browser_title'] ?? $content['heading'] ?? '',
            '_yoast_wpseo_metadesc' => $content['description'] ?? '',
            '_yoast_wpseo_focuskw' => $content['focus_keyword'] ?? '',
            '_yoast_wpseo_focuskeywords' => $content['keywords'] ?? ''
        );

        foreach ($yoast_meta as $key => $value) {
            if (!empty($value)) {
                update_post_meta($post_id, $key, $value);
            }
        }
    }
}
