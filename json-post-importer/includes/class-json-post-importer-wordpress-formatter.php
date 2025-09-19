<?php
/**
 * WordPress Standard Formatter for JSON Post Importer
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    JSON_Post_Importer
 * @subpackage JSON_Post_Importer/includes
 */

/**
 * WordPress Standard Formatter Class
 *
 * Handles automatic formatting of JSON data to WordPress standards,
 * including Yoast SEO, custom fields, and WordPress post structure.
 *
 * @package    JSON_Post_Importer
 * @subpackage JSON_Post_Importer/includes
 * @author     Your Name <email@example.com>
 */
class JSON_Post_Importer_WordPress_Formatter {

    /**
     * WordPress standard post fields
     */
    const WP_STANDARD_FIELDS = [
        'post_title',
        'post_content',
        'post_excerpt',
        'post_status',
        'post_date',
        'post_author',
        'post_name',
        'post_type',
        'post_parent',
        'menu_order',
        'comment_status',
        'ping_status'
    ];

    /**
     * Yoast SEO meta fields
     */
    const YOAST_SEO_FIELDS = [
        '_yoast_wpseo_title',
        '_yoast_wpseo_metadesc',
        '_yoast_wpseo_focuskw',
        '_yoast_wpseo_meta-robots-noindex',
        '_yoast_wpseo_meta-robots-nofollow',
        '_yoast_wpseo_canonical',
        '_yoast_wpseo_opengraph-title',
        '_yoast_wpseo_opengraph-description',
        '_yoast_wpseo_opengraph-image',
        '_yoast_wpseo_twitter-title',
        '_yoast_wpseo_twitter-description',
        '_yoast_wpseo_twitter-image'
    ];

    /**
     * Common WordPress meta fields
     */
    const WP_META_FIELDS = [
        '_thumbnail_id',
        '_wp_page_template',
        '_edit_lock',
        '_edit_last'
    ];

    /**
     * Logger instance
     */
    private $logger;

    /**
     * Initialize the formatter
     */
    public function __construct() {
        $this->logger = new JSON_Post_Importer_Logger();
    }    
/**
     * Format JSON data to WordPress standards
     *
     * @param array $json_data Raw JSON data
     * @param array $options Formatting options
     * @return array Formatted WordPress data
     */
    public function format_to_wordpress_standard($json_data, $options = []) {
        $this->logger->debug('Starting WordPress standard formatting', [
            'data_count' => count($json_data),
            'options' => $options
        ]);

        $formatted_data = [];
        $default_options = [
            'auto_detect_fields' => true,
            'generate_seo_meta' => true,
            'create_excerpts' => true,
            'generate_slugs' => true,
            'format_content' => true,
            'detect_featured_images' => true,
            'process_taxonomies' => true,
            'add_schema_markup' => true
        ];

        $options = array_merge($default_options, $options);

        foreach ($json_data as $index => $item) {
            try {
                $formatted_item = $this->format_single_item($item, $options);
                $formatted_data[] = $formatted_item;
            } catch (Exception $e) {
                $this->logger->error('Error formatting item', [
                    'index' => $index,
                    'error' => $e->getMessage(),
                    'item' => $item
                ]);
                continue;
            }
        }

        $this->logger->info('WordPress formatting completed', [
            'original_count' => count($json_data),
            'formatted_count' => count($formatted_data)
        ]);

        return $formatted_data;
    }

    /**
     * Format a single JSON item to WordPress standard
     *
     * @param array $item Single JSON item
     * @param array $options Formatting options
     * @return array Formatted WordPress item
     */
    private function format_single_item($item, $options) {
        $formatted = [
            'post_data' => [],
            'meta_data' => [],
            'taxonomy_data' => [],
            'media_data' => []
        ];

        // Auto-detect and map standard WordPress fields
        if ($options['auto_detect_fields']) {
            $formatted['post_data'] = $this->auto_detect_wordpress_fields($item);
        }

        // Generate SEO meta data
        if ($options['generate_seo_meta']) {
            $formatted['meta_data'] = array_merge(
                $formatted['meta_data'],
                $this->generate_seo_meta($item, $formatted['post_data'])
            );
        }

        // Create excerpt if not present
        if ($options['create_excerpts'] && empty($formatted['post_data']['post_excerpt'])) {
            $formatted['post_data']['post_excerpt'] = $this->generate_excerpt(
                $formatted['post_data']['post_content'] ?? ''
            );
        }

        // Generate slug if not present
        if ($options['generate_slugs'] && empty($formatted['post_data']['post_name'])) {
            $formatted['post_data']['post_name'] = $this->generate_slug(
                $formatted['post_data']['post_title'] ?? ''
            );
        }

        // Format content for WordPress
        if ($options['format_content']) {
            $formatted['post_data']['post_content'] = $this->format_content_for_wordpress(
                $formatted['post_data']['post_content'] ?? ''
            );
        }

        // Detect and process featured images
        if ($options['detect_featured_images']) {
            $featured_image = $this->detect_featured_image($item);
            if ($featured_image) {
                $formatted['media_data']['featured_image'] = $featured_image;
            }
        }

        // Process taxonomies
        if ($options['process_taxonomies']) {
            $formatted['taxonomy_data'] = $this->process_taxonomies($item);
        }

        // Add schema markup
        if ($options['add_schema_markup']) {
            $formatted['meta_data']['_schema_markup'] = $this->generate_schema_markup($formatted);
        }

        return $formatted;
    } 
   /**
     * Auto-detect WordPress fields from JSON data
     *
     * @param array $item JSON item
     * @return array WordPress post data
     */
    private function auto_detect_wordpress_fields($item) {
        $post_data = [];
        $flattened = $this->flatten_array($item);

        // Field mapping patterns
        $field_patterns = [
            'post_title' => ['title', 'name', 'heading', 'subject', 'headline', 'post_title'],
            'post_content' => ['content', 'body', 'description', 'text', 'article', 'post_content'],
            'post_excerpt' => ['excerpt', 'summary', 'abstract', 'intro', 'lead', 'post_excerpt'],
            'post_status' => ['status', 'state', 'published', 'post_status'],
            'post_date' => ['date', 'created', 'published', 'timestamp', 'created_at', 'published_at', 'post_date'],
            'post_author' => ['author', 'creator', 'user', 'by', 'writer', 'post_author'],
            'post_name' => ['slug', 'permalink', 'url_slug', 'post_name'],
            'post_type' => ['type', 'post_type', 'content_type'],
            'post_parent' => ['parent', 'parent_id', 'post_parent'],
            'menu_order' => ['order', 'sort_order', 'menu_order', 'position']
        ];

        foreach ($field_patterns as $wp_field => $patterns) {
            $value = $this->find_field_by_patterns($flattened, $patterns);
            if ($value !== null) {
                $post_data[$wp_field] = $this->sanitize_field_value($wp_field, $value);
            }
        }

        // Set defaults for required fields
        if (empty($post_data['post_title'])) {
            $post_data['post_title'] = 'Untitled Post';
        }

        if (empty($post_data['post_status'])) {
            $post_data['post_status'] = 'draft';
        }

        if (empty($post_data['post_type'])) {
            $post_data['post_type'] = 'post';
        }

        if (empty($post_data['post_date'])) {
            $post_data['post_date'] = current_time('mysql');
        }

        return $post_data;
    }

    /**
     * Generate SEO meta data
     *
     * @param array $item Original JSON item
     * @param array $post_data WordPress post data
     * @return array SEO meta data
     */
    private function generate_seo_meta($item, $post_data) {
        $seo_meta = [];
        $flattened = $this->flatten_array($item);

        // Yoast SEO patterns
        $seo_patterns = [
            '_yoast_wpseo_title' => ['seo_title', 'meta_title', 'page_title', 'seo.title'],
            '_yoast_wpseo_metadesc' => ['seo_description', 'meta_description', 'description', 'seo.description'],
            '_yoast_wpseo_focuskw' => ['focus_keyword', 'keyword', 'main_keyword', 'seo.keyword'],
            '_yoast_wpseo_canonical' => ['canonical', 'canonical_url', 'seo.canonical'],
            '_yoast_wpseo_opengraph-title' => ['og_title', 'facebook_title', 'social.facebook.title'],
            '_yoast_wpseo_opengraph-description' => ['og_description', 'facebook_description', 'social.facebook.description'],
            '_yoast_wpseo_opengraph-image' => ['og_image', 'facebook_image', 'social.facebook.image'],
            '_yoast_wpseo_twitter-title' => ['twitter_title', 'social.twitter.title'],
            '_yoast_wpseo_twitter-description' => ['twitter_description', 'social.twitter.description'],
            '_yoast_wpseo_twitter-image' => ['twitter_image', 'social.twitter.image']
        ];

        foreach ($seo_patterns as $meta_key => $patterns) {
            $value = $this->find_field_by_patterns($flattened, $patterns);
            if ($value !== null) {
                $seo_meta[$meta_key] = sanitize_text_field($value);
            }
        }

        // Generate missing SEO fields from post data
        if (empty($seo_meta['_yoast_wpseo_title']) && !empty($post_data['post_title'])) {
            $seo_meta['_yoast_wpseo_title'] = $post_data['post_title'];
        }

        if (empty($seo_meta['_yoast_wpseo_metadesc']) && !empty($post_data['post_excerpt'])) {
            $seo_meta['_yoast_wpseo_metadesc'] = wp_trim_words($post_data['post_excerpt'], 25);
        }

        return $seo_meta;
    }

    /**
     * Process taxonomies from JSON data
     *
     * @param array $item JSON item
     * @return array Taxonomy data
     */
    private function process_taxonomies($item) {
        $taxonomy_data = [];
        $flattened = $this->flatten_array($item);

        $taxonomy_patterns = [
            'category' => ['category', 'categories', 'cat', 'section', 'topic'],
            'post_tag' => ['tag', 'tags', 'keywords', 'labels'],
            'product_cat' => ['product_category', 'product_categories'],
            'product_tag' => ['product_tag', 'product_tags']
        ];

        foreach ($taxonomy_patterns as $taxonomy => $patterns) {
            $value = $this->find_field_by_patterns($flattened, $patterns);
            if ($value !== null) {
                $taxonomy_data[$taxonomy] = $this->process_taxonomy_terms($value);
            }
        }

        return $taxonomy_data;
    }

    /**
     * Process taxonomy terms
     *
     * @param mixed $terms Terms data
     * @return array Processed terms
     */
    private function process_taxonomy_terms($terms) {
        if (is_string($terms)) {
            // Handle comma-separated strings
            return array_map('trim', explode(',', $terms));
        }

        if (is_array($terms)) {
            $processed = [];
            foreach ($terms as $term) {
                if (is_string($term)) {
                    $processed[] = trim($term);
                } elseif (is_array($term) && isset($term['name'])) {
                    $processed[] = trim($term['name']);
                }
            }
            return $processed;
        }

        return [];
    }  
  /**
     * Detect featured image from JSON data
     *
     * @param array $item JSON item
     * @return string|null Featured image URL
     */
    private function detect_featured_image($item) {
        $flattened = $this->flatten_array($item);
        
        $image_patterns = [
            'featured_image', 'thumbnail', 'image', 'photo', 'picture',
            'featured_image_url', 'thumbnail_url', 'image_url',
            'main_image', 'hero_image', 'cover_image'
        ];

        $image_url = $this->find_field_by_patterns($flattened, $image_patterns);
        
        if ($image_url && filter_var($image_url, FILTER_VALIDATE_URL)) {
            return $image_url;
        }

        return null;
    }

    /**
     * Generate excerpt from content
     *
     * @param string $content Post content
     * @return string Generated excerpt
     */
    private function generate_excerpt($content) {
        if (empty($content)) {
            return '';
        }

        // Strip HTML tags and shortcodes
        $content = wp_strip_all_tags($content);
        $content = strip_shortcodes($content);
        
        // Generate excerpt
        return wp_trim_words($content, 55, '...');
    }

    /**
     * Generate slug from title
     *
     * @param string $title Post title
     * @return string Generated slug
     */
    private function generate_slug($title) {
        if (empty($title)) {
            return '';
        }

        return sanitize_title($title);
    }

    /**
     * Format content for WordPress
     *
     * @param string $content Raw content
     * @return string Formatted content
     */
    private function format_content_for_wordpress($content) {
        if (empty($content)) {
            return '';
        }

        // Convert line breaks to paragraphs
        $content = wpautop($content);
        
        // Process shortcodes if any
        $content = do_shortcode($content);
        
        // Clean up extra whitespace
        $content = trim($content);

        return $content;
    }

    /**
     * Generate schema markup
     *
     * @param array $formatted_data Formatted post data
     * @return string JSON-LD schema markup
     */
    private function generate_schema_markup($formatted_data) {
        $post_data = $formatted_data['post_data'];
        $meta_data = $formatted_data['meta_data'];

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post_data['post_title'] ?? '',
            'description' => $post_data['post_excerpt'] ?? '',
            'datePublished' => $post_data['post_date'] ?? '',
            'dateModified' => $post_data['post_date'] ?? '',
        ];

        // Add author if available
        if (!empty($post_data['post_author'])) {
            $schema['author'] = [
                '@type' => 'Person',
                'name' => $post_data['post_author']
            ];
        }

        // Add featured image if available
        if (!empty($formatted_data['media_data']['featured_image'])) {
            $schema['image'] = $formatted_data['media_data']['featured_image'];
        }

        return json_encode($schema, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Find field value by patterns
     *
     * @param array $flattened Flattened array
     * @param array $patterns Field patterns to search
     * @return mixed|null Field value or null
     */
    private function find_field_by_patterns($flattened, $patterns) {
        foreach ($patterns as $pattern) {
            // Direct match
            if (isset($flattened[$pattern])) {
                return $flattened[$pattern];
            }

            // Case-insensitive search
            foreach ($flattened as $key => $value) {
                if (strcasecmp($key, $pattern) === 0) {
                    return $value;
                }
            }

            // Partial match
            foreach ($flattened as $key => $value) {
                if (stripos($key, $pattern) !== false) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Flatten nested array with dot notation
     *
     * @param array $array Array to flatten
     * @param string $prefix Key prefix
     * @return array Flattened array
     */
    private function flatten_array($array, $prefix = '') {
        $result = [];

        foreach ($array as $key => $value) {
            $new_key = $prefix === '' ? $key : $prefix . '.' . $key;

            if (is_array($value) && !empty($value)) {
                $result = array_merge($result, $this->flatten_array($value, $new_key));
            } else {
                $result[$new_key] = $value;
            }
        }

        return $result;
    }

    /**
     * Sanitize field value based on field type
     *
     * @param string $field_name WordPress field name
     * @param mixed $value Field value
     * @return mixed Sanitized value
     */
    private function sanitize_field_value($field_name, $value) {
        switch ($field_name) {
            case 'post_title':
                return sanitize_text_field($value);
            
            case 'post_content':
                return wp_kses_post($value);
            
            case 'post_excerpt':
                return sanitize_textarea_field($value);
            
            case 'post_status':
                $valid_statuses = ['publish', 'draft', 'pending', 'private', 'future'];
                return in_array($value, $valid_statuses) ? $value : 'draft';
            
            case 'post_date':
                return $this->sanitize_date($value);
            
            case 'post_name':
                return sanitize_title($value);
            
            case 'post_type':
                return sanitize_key($value);
            
            case 'post_author':
                return is_numeric($value) ? intval($value) : sanitize_text_field($value);
            
            case 'post_parent':
            case 'menu_order':
                return intval($value);
            
            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Sanitize date value
     *
     * @param mixed $date Date value
     * @return string Sanitized date
     */
    private function sanitize_date($date) {
        if (empty($date)) {
            return current_time('mysql');
        }

        // Try to parse various date formats
        $timestamp = strtotime($date);
        
        if ($timestamp === false) {
            return current_time('mysql');
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Get WordPress standard mapping suggestions
     *
     * @param array $json_data Sample JSON data
     * @return array Mapping suggestions
     */
    public function get_mapping_suggestions($json_data) {
        if (empty($json_data) || !is_array($json_data)) {
            return [];
        }

        $sample_item = is_array($json_data[0]) ? $json_data[0] : $json_data;
        $flattened = $this->flatten_array($sample_item);
        $suggestions = [];

        // WordPress standard fields
        $field_patterns = [
            'post_title' => ['title', 'name', 'heading', 'subject', 'headline'],
            'post_content' => ['content', 'body', 'description', 'text', 'article'],
            'post_excerpt' => ['excerpt', 'summary', 'abstract', 'intro', 'lead'],
            'post_status' => ['status', 'state', 'published'],
            'post_date' => ['date', 'created', 'published', 'timestamp'],
            'post_author' => ['author', 'creator', 'user', 'by', 'writer']
        ];

        foreach ($field_patterns as $wp_field => $patterns) {
            $matched_field = $this->find_best_field_match($flattened, $patterns);
            if ($matched_field) {
                $suggestions['standard'][$wp_field] = $matched_field;
            }
        }

        // SEO fields
        $seo_patterns = [
            '_yoast_wpseo_title' => ['seo_title', 'meta_title', 'page_title'],
            '_yoast_wpseo_metadesc' => ['seo_description', 'meta_description'],
            '_yoast_wpseo_focuskw' => ['focus_keyword', 'keyword', 'main_keyword']
        ];

        foreach ($seo_patterns as $seo_field => $patterns) {
            $matched_field = $this->find_best_field_match($flattened, $patterns);
            if ($matched_field) {
                $suggestions['seo'][$seo_field] = $matched_field;
            }
        }

        // Taxonomy fields
        $taxonomy_patterns = [
            'category' => ['category', 'categories', 'cat', 'section'],
            'post_tag' => ['tag', 'tags', 'keywords', 'labels']
        ];

        foreach ($taxonomy_patterns as $taxonomy => $patterns) {
            $matched_field = $this->find_best_field_match($flattened, $patterns);
            if ($matched_field) {
                $suggestions['taxonomies'][$taxonomy] = $matched_field;
            }
        }

        return $suggestions;
    }

    /**
     * Find best field match from patterns
     *
     * @param array $flattened Flattened data
     * @param array $patterns Search patterns
     * @return string|null Best matching field name
     */
    private function find_best_field_match($flattened, $patterns) {
        $scores = [];

        foreach ($flattened as $field_name => $value) {
            foreach ($patterns as $pattern) {
                $score = 0;
                
                // Exact match gets highest score
                if (strcasecmp($field_name, $pattern) === 0) {
                    $score = 100;
                }
                // Contains pattern gets medium score
                elseif (stripos($field_name, $pattern) !== false) {
                    $score = 50;
                }
                // Pattern contains field name gets lower score
                elseif (stripos($pattern, $field_name) !== false) {
                    $score = 25;
                }

                if ($score > 0) {
                    $scores[$field_name] = max($scores[$field_name] ?? 0, $score);
                }
            }
        }

        if (empty($scores)) {
            return null;
        }

        // Return field with highest score
        arsort($scores);
        return array_key_first($scores);
    }
}
