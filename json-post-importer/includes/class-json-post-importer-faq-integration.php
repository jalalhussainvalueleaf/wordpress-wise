<?php
/**
 * FAQ Integration for JSON Post Importer
 *
 * Integrates FAQ data from JSON imports with the post-faq-manager plugin.
 * Processes FAQ data during post import and stores it using post-faq-manager format.
 *
 * @since      1.0.0
 * @package    JSON_Post_Importer
 * @subpackage JSON_Post_Importer/includes
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * FAQ Integration Class
 *
 * Handles the integration between JSON Post Importer and post-faq-manager plugin.
 * Processes FAQ data from JSON imports and stores it in the correct format.
 */
class JSON_Post_Importer_FAQ_Integration {

    /**
     * The logger instance
     *
     * @since    1.0.0
     * @access   private
     * @var      JSON_Post_Importer_Logger    $logger    Logger instance for debugging and error tracking.
     */
    private $logger;

    /**
     * Plugin dependency status
     *
     * @since    1.0.0
     * @access   private
     * @var      bool    $faq_manager_active    Whether post-faq-manager plugin is active.
     */
    private $faq_manager_active;

    /**
     * Initialize the FAQ integration
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->logger = new JSON_Post_Importer_Logger();
        $this->check_dependencies();
        $this->init_hooks();
    }

    /**
     * Check if required dependencies are available
     *
     * @since    1.0.0
     * @access   private
     */
    private function check_dependencies() {
        // Check if post-faq-manager plugin is active
        $this->faq_manager_active = $this->is_faq_manager_active();
        
        if (!$this->faq_manager_active) {
            $this->logger->debug('FAQ Integration: post-faq-manager plugin is not active');
        } else {
            $this->logger->debug('FAQ Integration: post-faq-manager plugin detected and active');
        }
    }

    /**
     * Initialize WordPress hooks
     *
     * @since    1.0.0
     * @access   private
     */
    private function init_hooks() {
        // Only register hooks if post-faq-manager is active
        if ($this->faq_manager_active) {
            // Hook into post import process
            add_action('jpi_after_post_import', array($this, 'process_post_faqs'), 10, 3);
            
            $this->logger->debug('FAQ Integration: Hooks registered successfully');
        } else {
            $this->logger->debug('FAQ Integration: Hooks not registered - post-faq-manager not active');
        }
    }

    /**
     * Check if post-faq-manager plugin is active
     *
     * @since    1.0.0
     * @access   public
     * @return   bool    True if post-faq-manager is active, false otherwise.
     */
    public function is_faq_manager_active() {
        // Check for specific functions that the post-faq-manager plugin provides
        $required_functions = array(
            'faq_manager_add_meta_box',
            'faq_manager_save_fields',
            'faq_manager_register_rest_routes',
            'faq_manager_get_faqs'
        );
        
        foreach ($required_functions as $function) {
            if (function_exists($function)) {
                return true;
            }
        }
        
        // Alternative check - look for plugin file in active plugins
        if (function_exists('is_plugin_active')) {
            return is_plugin_active('post-faq-manager/post-faq-manager.php');
        }
        
        // Check if the plugin constants are defined
        if (defined('POST_FAQ_MANAGER_PATH') || defined('POST_FAQ_MANAGER_URL')) {
            return true;
        }
        
        return false;
    }

    /**
     * Process FAQ data for an imported post
     *
     * This is the main entry point for FAQ processing, called after a post is imported.
     *
     * @since    1.0.0
     * @access   public
     * @param    int     $post_id    The ID of the imported post.
     * @param    array   $item       The original JSON data item.
     * @param    array   $options    Import options and settings.
     */
    public function process_post_faqs($post_id, $item, $options) {
        // Early return if dependencies not met
        if (!$this->faq_manager_active) {
            $this->logger->debug('FAQ Integration: Skipping FAQ processing - post-faq-manager not active', array(
                'post_id' => $post_id
            ));
            return;
        }

        // Validate inputs
        if (empty($post_id) || !is_numeric($post_id)) {
            $this->logger->error('FAQ Integration: Invalid post ID provided', array(
                'post_id' => $post_id
            ));
            return;
        }

        if (empty($item) || !is_array($item)) {
            $this->logger->error('FAQ Integration: Invalid item data provided', array(
                'post_id' => $post_id,
                'item_type' => gettype($item)
            ));
            return;
        }

        try {
            $this->logger->debug('FAQ Integration: Starting FAQ processing', array(
                'post_id' => $post_id,
                'has_faq_data' => $this->has_faq_data($item),
                'item_keys' => array_keys($item)
            ));

            // Check if item contains FAQ data
            if (!$this->has_faq_data($item)) {
                $this->logger->debug('FAQ Integration: No FAQ data found in item', array(
                    'post_id' => $post_id,
                    'item_structure' => $this->get_item_structure_debug($item)
                ));
                return;
            }

            // Extract FAQ data from the item
            $faq_data = $this->extract_faq_data($item, $options);
            
            if (empty($faq_data)) {
                $this->logger->debug('FAQ Integration: No valid FAQ data found after extraction', array(
                    'post_id' => $post_id
                ));
                return;
            }

            // Process and save FAQ data
            $result = $this->save_faq_data($post_id, $faq_data);
            
            if ($result) {
                $this->logger->debug('FAQ Integration: Successfully processed FAQ data', array(
                    'post_id' => $post_id,
                    'faq_count' => count($faq_data)
                ));
            } else {
                $this->logger->error('FAQ Integration: Failed to save FAQ data', array(
                    'post_id' => $post_id
                ));
            }

        } catch (Exception $e) {
            $this->logger->error('FAQ Integration: Error processing FAQ data', array(
                'post_id' => $post_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
        }
    }

    /**
     * Check if the item contains FAQ data
     *
     * @since    1.0.0
     * @access   private
     * @param    array   $item   The JSON data item to check.
     * @return   bool    True if FAQ data is present, false otherwise.
     */
    private function has_faq_data($item) {
        // Check for standard FAQ field
        if (!empty($item['faq']) && is_array($item['faq'])) {
            return true;
        }

        // Check for alternative FAQ field names
        $faq_fields = array('faqs', 'faq_data', 'questions', 'qa');
        foreach ($faq_fields as $field) {
            if (!empty($item[$field]) && is_array($item[$field])) {
                return true;
            }
        }

        // Check nested content for FAQ data
        if (!empty($item['content']) && is_array($item['content'])) {
            foreach ($faq_fields as $field) {
                if (!empty($item['content'][$field]) && is_array($item['content'][$field])) {
                    return true;
                }
            }
            
            // Also check for 'faq' in content
            if (!empty($item['content']['faq']) && is_array($item['content']['faq'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the current status of the FAQ integration
     *
     * @since    1.0.0
     * @access   public
     * @return   array   Status information about the FAQ integration.
     */
    public function get_integration_status() {
        return array(
            'faq_manager_active' => $this->faq_manager_active,
            'hooks_registered' => $this->faq_manager_active,
            'version' => '1.0.0',
            'ready' => $this->faq_manager_active
        );
    }

    /**
     * Extract FAQ data from JSON item
     *
     * @since    1.0.0
     * @access   private
     * @param    array   $item     The JSON data item.
     * @param    array   $options  Import options and settings.
     * @return   array   Extracted and validated FAQ data.
     */
    private function extract_faq_data($item, $options) {
        $faq_data = array();

        // Try to find FAQ data in various locations
        $faq_sources = $this->get_faq_data_sources($item);
        
        foreach ($faq_sources as $source_data) {
            if (!empty($source_data) && is_array($source_data)) {
                $processed_faqs = $this->process_faq_array($source_data);
                if (!empty($processed_faqs)) {
                    $faq_data = array_merge($faq_data, $processed_faqs);
                }
            }
        }

        // Remove duplicates and validate
        $faq_data = $this->validate_and_clean_faq_data($faq_data);

        $this->logger->debug('FAQ Integration: Extracted FAQ data', array(
            'original_sources' => count($faq_sources),
            'final_faq_count' => count($faq_data)
        ));

        return $faq_data;
    }

    /**
     * Get potential FAQ data sources from the item
     *
     * @since    1.0.0
     * @access   private
     * @param    array   $item   The JSON data item.
     * @return   array   Array of potential FAQ data sources.
     */
    private function get_faq_data_sources($item) {
        $sources = array();

        // Standard FAQ field
        if (!empty($item['faq']) && is_array($item['faq'])) {
            $sources[] = $item['faq'];
        }

        // Alternative FAQ field names
        $faq_fields = array('faqs', 'faq_data', 'questions', 'qa', 'question_answer');
        foreach ($faq_fields as $field) {
            if (!empty($item[$field]) && is_array($item[$field])) {
                $sources[] = $item[$field];
            }
        }

        // Check nested content for FAQ data
        if (!empty($item['content']) && is_array($item['content'])) {
            // Check for 'faq' in content
            if (!empty($item['content']['faq']) && is_array($item['content']['faq'])) {
                $sources[] = $item['content']['faq'];
            }
            
            // Check alternative field names in content
            foreach ($faq_fields as $field) {
                if (!empty($item['content'][$field]) && is_array($item['content'][$field])) {
                    $sources[] = $item['content'][$field];
                }
            }
        }

        return $sources;
    }

    /**
     * Process an array of FAQ data into standardized format
     *
     * @since    1.0.0
     * @access   private
     * @param    array   $faq_array   Raw FAQ array data.
     * @return   array   Processed FAQ data.
     */
    private function process_faq_array($faq_array) {
        $processed = array();

        foreach ($faq_array as $faq_item) {
            if (!is_array($faq_item)) {
                continue;
            }

            $question = '';
            $answer = '';

            // Try different field name variations for question
            $question_fields = array('question', 'q', 'title', 'heading', 'query');
            foreach ($question_fields as $field) {
                if (!empty($faq_item[$field])) {
                    $question = sanitize_text_field($faq_item[$field]);
                    break;
                }
            }

            // Try different field name variations for answer
            $answer_fields = array('answer', 'a', 'response', 'content', 'text', 'body');
            foreach ($answer_fields as $field) {
                if (!empty($faq_item[$field])) {
                    $answer = wp_kses_post($faq_item[$field]);
                    break;
                }
            }

            // Only add if both question and answer are present
            if (!empty($question) && !empty($answer)) {
                $processed[] = array(
                    'question' => $question,
                    'answer' => $answer
                );
            }
        }

        return $processed;
    }

    /**
     * Validate and clean FAQ data
     *
     * @since    1.0.0
     * @access   private
     * @param    array   $faq_data   Raw FAQ data.
     * @return   array   Validated and cleaned FAQ data.
     */
    private function validate_and_clean_faq_data($faq_data) {
        $cleaned = array();
        $seen_questions = array();

        foreach ($faq_data as $faq) {
            if (!is_array($faq) || empty($faq['question']) || empty($faq['answer'])) {
                continue;
            }

            // Remove duplicates based on question
            $question_key = strtolower(trim($faq['question']));
            if (in_array($question_key, $seen_questions)) {
                continue;
            }

            $seen_questions[] = $question_key;
            $cleaned[] = array(
                'question' => trim($faq['question']),
                'answer' => trim($faq['answer'])
            );
        }

        return $cleaned;
    }

    /**
     * Save FAQ data to post meta using post-faq-manager format
     *
     * @since    1.0.0
     * @access   private
     * @param    int     $post_id    The post ID.
     * @param    array   $faq_data   The FAQ data to save.
     * @return   bool    True on success, false on failure.
     */
    private function save_faq_data($post_id, $faq_data) {
        if (empty($post_id) || empty($faq_data)) {
            return false;
        }

        try {
            // Enable FAQ functionality for this post
            $enabled_result = update_post_meta($post_id, '_faq_manager_enabled', '1');
            
            // Save the FAQ data
            $data_result = update_post_meta($post_id, '_faq_manager_data', $faq_data);

            $this->logger->debug('FAQ Integration: Saved FAQ meta data', array(
                'post_id' => $post_id,
                'enabled_result' => $enabled_result,
                'data_result' => $data_result,
                'faq_count' => count($faq_data)
            ));

            return $data_result !== false;

        } catch (Exception $e) {
            $this->logger->error('FAQ Integration: Error saving FAQ data', array(
                'post_id' => $post_id,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Get item structure for debugging
     *
     * @since    1.0.0
     * @access   private
     * @param    array   $item   The JSON data item.
     * @return   array   Simplified structure for debugging.
     */
    private function get_item_structure_debug($item) {
        $structure = array();
        
        if (is_array($item)) {
            foreach ($item as $key => $value) {
                if (is_array($value)) {
                    $structure[$key] = 'array(' . count($value) . ')';
                } else {
                    $structure[$key] = gettype($value);
                }
            }
        }
        
        return $structure;
    }

    /**
     * Initialize the FAQ integration (static method for external calls)
     *
     * @since    1.0.0
     * @access   public
     * @return   JSON_Post_Importer_FAQ_Integration   The FAQ integration instance.
     */
    public static function init() {
        static $instance = null;
        
        if (null === $instance) {
            $instance = new self();
        }
        
        return $instance;
    }
}