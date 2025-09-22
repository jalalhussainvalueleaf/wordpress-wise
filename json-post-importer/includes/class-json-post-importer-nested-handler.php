<?php
/**
 * Handles nested JSON structure processing and field path resolution
 */
class JSON_Post_Importer_Nested_Handler {
    
    /**
     * The logger instance
     *
     * @var JSON_Post_Importer_Logger
     */
    private $logger;
    
    /**
     * Default JSON root path for nested structures
     *
     * @var string
     */
    private $default_root_path = 'content';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = new JSON_Post_Importer_Logger();
    }
    
    /**
     * Extract data from nested JSON using dot notation path
     *
     * @param array $data The JSON data
     * @param string $path Dot notation path (e.g., "content.title", "content.yoast_seo_title")
     * @return mixed The extracted value or null if not found
     */
    public function extract_nested_value($data, $path) {
        if (empty($path) || !is_array($data)) {
            return null;
        }
        
        $keys = explode('.', $path);
        $current = $data;
        
        foreach ($keys as $key) {
            if (is_array($current) && array_key_exists($key, $current)) {
                $current = $current[$key];
            } else {
                $this->logger->debug("Path not found: {$path} at key: {$key}");
                return null;
            }
        }
        
        return $current;
    }
    
    /**
     * Set nested value using dot notation path
     *
     * @param array &$data The JSON data (passed by reference)
     * @param string $path Dot notation path
     * @param mixed $value The value to set
     * @return bool Success status
     */
    public function set_nested_value(&$data, $path, $value) {
        if (empty($path)) {
            return false;
        }
        
        $keys = explode('.', $path);
        $current = &$data;
        
        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                // Last key, set the value
                $current[$key] = $value;
            } else {
                // Intermediate key, ensure it's an array
                if (!isset($current[$key]) || !is_array($current[$key])) {
                    $current[$key] = array();
                }
                $current = &$current[$key];
            }
        }
        
        return true;
    }
    
    /**
     * Check if a nested path exists in the data
     *
     * @param array $data The JSON data
     * @param string $path Dot notation path
     * @return bool Whether the path exists
     */
    public function path_exists($data, $path) {
        if (empty($path) || !is_array($data)) {
            return false;
        }
        
        $keys = explode('.', $path);
        $current = $data;
        
        foreach ($keys as $key) {
            if (is_array($current) && array_key_exists($key, $current)) {
                $current = $current[$key];
            } else {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Extract all possible field paths from nested JSON structure
     *
     * @param array $data The JSON data
     * @param string $prefix Current path prefix
     * @param int $max_depth Maximum depth to traverse
     * @param int $current_depth Current traversal depth
     * @return array Array of field paths with sample values
     */
    public function extract_all_field_paths($data, $prefix = '', $max_depth = 5, $current_depth = 0) {
        $paths = array();
        
        if ($current_depth >= $max_depth || !is_array($data)) {
            return $paths;
        }
        
        foreach ($data as $key => $value) {
            $current_path = $prefix ? $prefix . '.' . $key : $key;
            
            if (is_array($value) && !$this->is_indexed_array($value)) {
                // Nested object, recurse
                $nested_paths = $this->extract_all_field_paths(
                    $value, 
                    $current_path, 
                    $max_depth, 
                    $current_depth + 1
                );
                $paths = array_merge($paths, $nested_paths);
            } else {
                // Leaf value or indexed array, add to paths
                $paths[$current_path] = $this->format_field_preview($value);
            }
        }
        
        return $paths;
    }
    
    /**
     * Check if array is indexed (numeric keys starting from 0)
     *
     * @param array $array The array to check
     * @return bool Whether the array is indexed
     */
    private function is_indexed_array($array) {
        if (!is_array($array)) {
            return false;
        }
        
        $keys = array_keys($array);
        return $keys === array_keys($keys);
    }
    
    /**
     * Format field value for preview display
     *
     * @param mixed $value The field value
     * @return string Formatted preview
     */
    private function format_field_preview($value) {
        if ($value === null || $value === '') {
            return '—';
        }
        
        if (is_string($value)) {
            return strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value;
        }
        
        if (is_array($value)) {
            if ($this->is_indexed_array($value)) {
                return '[' . count($value) . ' items]';
            } else {
                return '{' . count($value) . ' fields}';
            }
        }
        
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        
        if (is_numeric($value)) {
            return (string) $value;
        }
        
        return '[' . gettype($value) . ']';
    }
    
    /**
     * Extract wrapper metadata from JSON structure
     *
     * @param array $data The JSON data
     * @param array $wrapper_fields Fields to extract as wrapper metadata
     * @return array Extracted wrapper metadata
     */
    public function extract_wrapper_metadata($data, $wrapper_fields = null) {
        if ($wrapper_fields === null) {
            $wrapper_fields = array('domain_name', 'user_id', 'email', 'domain_lang', 'type');
        }
        
        $metadata = array();
        
        foreach ($wrapper_fields as $field) {
            if (isset($data[$field])) {
                $metadata[$field] = $data[$field];
            }
        }
        
        $this->logger->debug('Extracted wrapper metadata', $metadata);
        
        return $metadata;
    }
    
    /**
     * Extract content from nested structure using root path
     *
     * @param array $data The JSON data
     * @param string $root_path Root path for content (default: 'content')
     * @return array Content data
     */
    public function extract_content_data($data, $root_path = null) {
        if ($root_path === null) {
            $root_path = $this->default_root_path;
        }
        
        if (empty($root_path)) {
            return $data;
        }
        
        $content = $this->extract_nested_value($data, $root_path);
        
        if ($content === null) {
            $this->logger->warning("Content not found at root path: {$root_path}");
            return $data; // Fallback to original data
        }
        
        if (!is_array($content)) {
            $this->logger->warning("Content at root path is not an array: {$root_path}");
            return $data; // Fallback to original data
        }
        
        return $content;
    }
    
    /**
     * Validate field path format
     *
     * @param string $path The field path to validate
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate_field_path($path) {
        if (empty($path)) {
            return new WP_Error('empty_path', 'Field path cannot be empty');
        }
        
        // Check for invalid characters
        if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $path)) {
            return new WP_Error('invalid_characters', 'Field path contains invalid characters. Use only letters, numbers, underscores, and dots.');
        }
        
        // Check for consecutive dots
        if (strpos($path, '..') !== false) {
            return new WP_Error('consecutive_dots', 'Field path cannot contain consecutive dots');
        }
        
        // Check for leading or trailing dots
        if (substr($path, 0, 1) === '.' || substr($path, -1) === '.') {
            return new WP_Error('leading_trailing_dots', 'Field path cannot start or end with a dot');
        }
        
        return true;
    }
    
    /**
     * Generate hierarchical structure for UI display
     *
     * @param array $paths Array of field paths
     * @return array Hierarchical structure for tree view
     */
    public function generate_hierarchical_structure($paths) {
        $structure = array();
        
        foreach ($paths as $path => $preview) {
            $keys = explode('.', $path);
            $current = &$structure;
            
            foreach ($keys as $i => $key) {
                if (!isset($current[$key])) {
                    $current[$key] = array(
                        'children' => array(),
                        'is_leaf' => false,
                        'path' => implode('.', array_slice($keys, 0, $i + 1)),
                        'preview' => null
                    );
                }
                
                if ($i === count($keys) - 1) {
                    // Leaf node
                    $current[$key]['is_leaf'] = true;
                    $current[$key]['preview'] = $preview;
                } else {
                    // Intermediate node
                    $current = &$current[$key]['children'];
                }
            }
        }
        
        return $structure;
    }
    
    /**
     * Process field mappings with nested path support
     *
     * @param array $item JSON data item
     * @param array $field_mappings Field mapping configuration
     * @param array $options Processing options
     * @return array Processed field data
     */
    public function process_field_mappings($item, $field_mappings, $options = array()) {
        $processed_data = array();
        $root_path = isset($options['json_root_path']) ? $options['json_root_path'] : $this->default_root_path;
        
        // Extract wrapper metadata if enabled
        if (!empty($options['import_wrapper_meta'])) {
            $processed_data['wrapper_metadata'] = $this->extract_wrapper_metadata($item);
        }
        
        // Extract content data
        $content_data = $this->extract_content_data($item, $root_path);
        
        // Process standard field mappings
        if (!empty($field_mappings['standard'])) {
            foreach ($field_mappings['standard'] as $wp_field => $json_path) {
                if (!empty($json_path)) {
                    $value = $this->extract_nested_value($content_data, $json_path);
                    if ($value !== null) {
                        $processed_data['standard'][$wp_field] = $value;
                    }
                }
            }
        }
        
        // Process Yoast SEO field mappings
        if (!empty($field_mappings['yoast_seo'])) {
            foreach ($field_mappings['yoast_seo'] as $yoast_field => $json_path) {
                if (!empty($json_path)) {
                    $value = $this->extract_nested_value($content_data, $json_path);
                    if ($value !== null) {
                        $processed_data['yoast_seo'][$yoast_field] = $value;
                    }
                }
            }
        }
        
        // Process custom field mappings
        if (!empty($field_mappings['custom'])) {
            foreach ($field_mappings['custom'] as $custom_field) {
                if (!empty($custom_field['meta_key']) && !empty($custom_field['field'])) {
                    // Check if it's a wrapper field or content field
                    $value = null;
                    
                    // First try to get from wrapper metadata
                    if (isset($item[$custom_field['field']])) {
                        $value = $item[$custom_field['field']];
                    } else {
                        // Then try to get from content data
                        $value = $this->extract_nested_value($content_data, $custom_field['field']);
                    }
                    
                    if ($value !== null) {
                        $processed_data['custom'][$custom_field['meta_key']] = $value;
                    }
                }
            }
        }
        
        // Process taxonomy mappings
        if (!empty($field_mappings['taxonomies'])) {
            foreach ($field_mappings['taxonomies'] as $taxonomy_mapping) {
                if (!empty($taxonomy_mapping['taxonomy']) && !empty($taxonomy_mapping['field'])) {
                    $value = $this->extract_nested_value($content_data, $taxonomy_mapping['field']);
                    if ($value !== null) {
                        $processed_data['taxonomies'][$taxonomy_mapping['taxonomy']] = $value;
                    }
                }
            }
        }
        
        $this->logger->debug('Processed field mappings', array(
            'root_path' => $root_path,
            'processed_data' => $processed_data
        ));
        
        return $processed_data;
    }
    
    /**
     * Get default root path
     *
     * @return string Default root path
     */
    public function get_default_root_path() {
        return $this->default_root_path;
    }
    
    /**
     * Set default root path
     *
     * @param string $path New default root path
     */
    public function set_default_root_path($path) {
        $this->default_root_path = $path;
    }
}