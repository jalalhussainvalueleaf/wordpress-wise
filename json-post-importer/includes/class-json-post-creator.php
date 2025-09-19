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
     * Constructor
     */
    public function __construct() {
        $this->logger = new JSON_Post_Importer_Logger();
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
        ));
        
        // Prepare post data
        $post_data = $this->prepare_post_data($item, $options);
        if (is_wp_error($post_data)) {
            return $post_data;
        }
        
        // Check for existing post
        $existing_id = $this->find_existing_post($item, $options);
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
        
        // Process taxonomies
        $this->process_taxonomies($post_id, $item, $options);
        
        // Process meta fields
        $this->process_meta_fields($post_id, $item, $options);
        
        // Process featured image
        if (!$options['skip_thumbnail'] && !empty($item['featured_image'])) {
            $this->process_featured_image($post_id, $item['featured_image'], $options);
        }
        
        // Process attachments
        if ($options['import_attachments'] && !empty($item['attachments'])) {
            $this->process_attachments($post_id, $item['attachments'], $options);
        }
        
        // Action after post is imported/updated
        do_action('jpi_after_post_import', $post_id, $item, $options);
        
        return array(
            'id' => $post_id,
            'updated' => $is_update,
            'message' => $is_update ? 'Post updated successfully' : 'Post created successfully'
        );
    }
    
    /**
     * Prepare post data from JSON item
     *
     * @param array $item JSON data item
     * @param array $options Import options
     * @return array|WP_Error Prepared post data or error
     */
    private function prepare_post_data($item, $options) {
        $post_data = array(
            'post_type' => $options['post_type'],
            'post_status' => $options['post_status'],
            'post_author' => $options['default_author'],
        );
        
        // Map standard fields
        foreach ($this->field_mappings as $json_field => $wp_field) {
            if (isset($item[$json_field])) {
                $post_data[$wp_field] = $this->sanitize_field($wp_field, $item[$json_field], $item);
            }
        }
        
        // Ensure required fields
        if (empty($post_data['post_title'])) {
            $post_data['post_title'] = sprintf(__('Untitled %s', 'json-post-importer'), current_time('mysql'));
        }
        
        // Process content
        if (!empty($item['content'])) {
            $post_data['post_content'] = $this->process_content($item['content'], $item);
        }
        
        // Handle dates
        if (!empty($item['date'])) {
            $post_data['post_date'] = $this->parse_date($item['date']);
            if (empty($post_data['post_modified']) && !empty($item['modified'])) {
                $post_data['post_modified'] = $this->parse_date($item['modified']);
            } elseif (empty($post_data['post_modified'])) {
                $post_data['post_modified'] = $post_data['post_date'];
            }
        }
        
        return $post_data;
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
     * Sanitize field value based on field type
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param array $item Full item data
     * @return mixed Sanitized value
     */
    private function sanitize_field($field, $value, $item) {
        switch ($field) {
            case 'post_title':
                return sanitize_text_field($value);
                
            case 'post_content':
                return wp_kses_post($value);
                
            case 'post_excerpt':
                return sanitize_textarea_field($value);
                
            case 'post_status':
                $allowed_statuses = array('publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash');
                return in_array($value, $allowed_statuses, true) ? $value : $this->default_post_status;
                
            case 'post_type':
                return post_type_exists($value) ? $value : $this->default_post_type;
                
            case 'post_author':
                $user = get_user_by('id', $value);
                return $user ? $user->ID : get_current_user_id();
                
            default:
                return is_string($value) ? sanitize_text_field($value) : $value;
        }
    }
    
    /**
     * Parse date string into MySQL datetime format
     *
     * @param string $date_string Date string to parse
     * @return string Formatted date string
     */
    private function parse_date($date_string) {
        $timestamp = strtotime($date_string);
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : current_time('mysql');
    }
    
    /**
     * Process taxonomies for a post
     *
     * @param int $post_id Post ID
     * @param array $item JSON data item
     * @param array $options Import options
     */
    private function process_taxonomies($post_id, $item, $options) {
        if (empty($item['taxonomies']) || !is_array($item['taxonomies'])) {
            return;
        }
        
        foreach ($item['taxonomies'] as $taxonomy => $terms) {
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }
            
            if (is_string($terms)) {
                $terms = array_map('trim', explode(',', $terms));
            }
            
            if (!empty($terms)) {
                wp_set_object_terms($post_id, $terms, $taxonomy);
            }
        }
    }
    
    /**
     * Process meta fields for a post
     *
     * @param int $post_id Post ID
     * @param array $item JSON data item
     * @param array $options Import options
     */
    private function process_meta_fields($post_id, $item, $options) {
        if (empty($item['meta']) || !is_array($item['meta'])) {
            return;
        }
        
        foreach ($item['meta'] as $key => $value) {
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
        }
    }
    
    /**
     * Process featured image for a post
     *
     * @param int $post_id Post ID
     * @param string|array $image_data Image URL or array with image data
     * @param array $options Import options
     * @return int|WP_Error Attachment ID or error
     */
    private function process_featured_image($post_id, $image_data, $options) {
        $image_url = is_array($image_data) ? ($image_data['url'] ?? '') : $image_data;
        
        if (empty($image_url)) {
            return new WP_Error('empty_url', 'No image URL provided');
        }
        
        // Upload the image
        $attachment_id = $this->upload_media($image_url);
        
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }
        
        // Set as featured image
        set_post_thumbnail($post_id, $attachment_id);
        
        // Update attachment metadata if additional data provided
        if (is_array($image_data)) {
            $attachment_data = array(
                'ID' => $attachment_id,
                'post_title' => $image_data['title'] ?? '',
                'post_excerpt' => $image_data['caption'] ?? '',
                'post_content' => $image_data['description'] ?? '',
                'menu_order' => $image_data['menu_order'] ?? 0,
            );
            
            // Remove empty values
            $attachment_data = array_filter($attachment_data);
            
            if (!empty($attachment_data)) {
                wp_update_post($attachment_data);
            }
            
            // Update alt text if provided
            if (!empty($image_data['alt'])) {
                update_post_meta($attachment_id, '_wp_attachment_image_alt', $image_data['alt']);
            }
        }
        
        return $attachment_id;
    }
    
    /**
     * Process attachments for a post
     *
     * @param int $post_id Post ID
     * @param array $attachment_data Optional attachment data
     * @return int|WP_Error Attachment ID or error
     */
    private function upload_media($file_url, $attachment_data = array()) {
        if (empty($file_url)) {
            $this->logger->error('No file URL provided for media upload');
            return new WP_Error('empty_url', 'No file URL provided');
        }

        // Validate URL
        if (!filter_var($file_url, FILTER_VALIDATE_URL)) {
            $this->logger->error('Invalid file URL provided for media upload', array('url' => $file_url));
            return new WP_Error('invalid_url', 'Invalid file URL');
        }
        
        $this->logger->debug('Starting media upload', array('url' => $file_url));

        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // Set up basic file data
        $file_array = array(
            'name' => basename(parse_url($file_url, PHP_URL_PATH)),
            'tmp_name' => download_url($file_url)
        );
        
        // Check for download errors
        if (is_wp_error($file_array['tmp_name'])) {
            $this->logger->log_media_import($file_url, null, $file_array['tmp_name']->get_error_message());
            return $file_array['tmp_name'];
        }
        
        // Prepare attachment data
        $attachment_data = wp_parse_args($attachment_data, array(
            'post_mime_type' => $this->get_mime_type($file_array['tmp_name']),
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($file_array['name'])),
            'post_content' => '',
            'post_status' => 'inherit',
        ));
        
        // Insert the attachment
        $attachment_id = media_handle_sideload($file_array, 0, null, $attachment_data);
        
        // Clean up temp file
        @unlink($file_array['tmp_name']);
        
        if (is_wp_error($attachment_id)) {
            $this->logger->log_media_import($file_url, null, $attachment_id->get_error_message());
        } else {
            $this->logger->log_media_import($file_url, $attachment_id);
        }
        
        return $attachment_id;
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
     * Set Yoast SEO meta data
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
