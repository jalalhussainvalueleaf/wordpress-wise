<?php
/**
 * The logging functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    JSON_Post_Importer
 * @subpackage JSON_Post_Importer/includes
 */

/**
 * The logging functionality of the plugin.
 *
 * Defines the plugin name, version, and handles all logging operations
 * including error logging, debug logging, log rotation, and cleanup.
 *
 * @package    JSON_Post_Importer
 * @subpackage JSON_Post_Importer/includes
 * @author     Your Name <email@example.com>
 */
class JSON_Post_Importer_Logger {

    /**
     * Log levels
     */
    const LEVEL_ERROR = 'error';
    const LEVEL_WARNING = 'warning';
    const LEVEL_INFO = 'info';
    const LEVEL_DEBUG = 'debug';

    /**
     * Maximum log file size in bytes (5MB)
     */
    const MAX_LOG_SIZE = 5242880;

    /**
     * Maximum number of log files to keep
     */
    const MAX_LOG_FILES = 5;

    /**
     * Log directory path
     *
     * @var string
     */
    private $log_dir;

    /**
     * Current log file path
     *
     * @var string
     */
    private $log_file;

    /**
     * Debug mode enabled
     *
     * @var bool
     */
    private $debug_mode;

    /**
     * Initialize the logger
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->setup_log_directory();
        $this->debug_mode = get_option('jpi_debug_mode', false);
    }

    /**
     * Setup log directory and file paths
     *
     * @since    1.0.0
     */
    private function setup_log_directory() {
        $upload_dir = wp_upload_dir();
        $this->log_dir = $upload_dir['basedir'] . '/json-post-importer-logs/';
        $this->log_file = $this->log_dir . 'json-post-importer.log';

        // Create log directory if it doesn't exist
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
            
            // Add .htaccess to protect log files
            $htaccess_content = "Order deny,allow\nDeny from all\n";
            file_put_contents($this->log_dir . '.htaccess', $htaccess_content);
            
            // Add index.php to prevent directory listing
            file_put_contents($this->log_dir . 'index.php', '<?php // Silence is golden');
        }
    }

    /**
     * Log an error message
     *
     * @since    1.0.0
     * @param    string    $message    The error message
     * @param    array     $context    Additional context data
     */
    public function error($message, $context = array()) {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log a warning message
     *
     * @since    1.0.0
     * @param    string    $message    The warning message
     * @param    array     $context    Additional context data
     */
    public function warning($message, $context = array()) {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log an info message
     *
     * @since    1.0.0
     * @param    string    $message    The info message
     * @param    array     $context    Additional context data
     */
    public function info($message, $context = array()) {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log a debug message (only if debug mode is enabled)
     *
     * @since    1.0.0
     * @param    string    $message    The debug message
     * @param    array     $context    Additional context data
     */
    public function debug($message, $context = array()) {
        if ($this->debug_mode) {
            $this->log(self::LEVEL_DEBUG, $message, $context);
        }
    }

    /**
     * Log a message with specified level
     *
     * @since    1.0.0
     * @param    string    $level      Log level
     * @param    string    $message    The message to log
     * @param    array     $context    Additional context data
     */
    private function log($level, $message, $context = array()) {
        // Check if log rotation is needed
        $this->rotate_logs_if_needed();

        // Prepare log entry
        $timestamp = current_time('Y-m-d H:i:s');
        $user_id = get_current_user_id();
        $user_info = $user_id ? " [User: {$user_id}]" : " [User: Guest]";
        
        // Add request URI if available
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'CLI';
        
        // Format context data
        $context_string = '';
        if (!empty($context)) {
            $context_string = ' | Context: ' . wp_json_encode($context, JSON_UNESCAPED_SLASHES);
        }

        // Create log entry
        $log_entry = sprintf(
            "[%s] %s%s | %s | %s%s\n",
            $timestamp,
            strtoupper($level),
            $user_info,
            $request_uri,
            $message,
            $context_string
        );

        // Write to log file
        error_log($log_entry, 3, $this->log_file);

        // Also log to WordPress debug log if WP_DEBUG_LOG is enabled
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log("JSON Post Importer [{$level}]: {$message}");
        }
    }

    /**
     * Log import session start
     *
     * @since    1.0.0
     * @param    string    $session_id    Import session ID
     * @param    array     $config        Import configuration
     */
    public function log_import_start($session_id, $config = array()) {
        $this->info("Import session started", array(
            'session_id' => $session_id,
            'config' => $config
        ));
    }

    /**
     * Log import session end
     *
     * @since    1.0.0
     * @param    string    $session_id    Import session ID
     * @param    array     $results       Import results
     */
    public function log_import_end($session_id, $results = array()) {
        $this->info("Import session completed", array(
            'session_id' => $session_id,
            'results' => $results
        ));
    }
    
    /**
     * Log enhanced import session end with detailed field type and processing statistics
     *
     * @since    1.0.0
     * @param    string    $session_id    Import session ID
     * @param    array     $results       Enhanced import results with field type tracking
     */
    public function log_import_end_enhanced($session_id, $results = array()) {
        $this->info("Enhanced import session completed", array(
            'session_id' => $session_id,
            'created_posts' => $results['created_posts'] ?? 0,
            'updated_posts' => $results['updated_posts'] ?? 0,
            'skipped_posts' => $results['skipped_posts'] ?? 0,
            'error_count' => $results['error_count'] ?? 0,
            'total_items' => $results['total_items'] ?? 0,
            'processed_items' => $results['processed_items'] ?? 0,
            'field_type_summary' => $this->summarize_field_type_progress($results['field_type_progress'] ?? array()),
            'duplicate_detection_summary' => $results['duplicate_detection_stats'] ?? array(),
            'nested_extraction_summary' => $results['nested_extraction_stats'] ?? array()
        ));
        
        // Log detailed field type processing summary
        if (!empty($results['field_type_progress'])) {
            $this->info("Field type processing summary", array(
                'session_id' => $session_id,
                'field_types' => $results['field_type_progress']
            ));
        }
        
        // Log duplicate detection effectiveness
        if (!empty($results['duplicate_detection_stats'])) {
            $total_duplicates = array_sum($results['duplicate_detection_stats']);
            if ($total_duplicates > 0) {
                $this->info("Duplicate detection summary", array(
                    'session_id' => $session_id,
                    'total_duplicates_found' => $total_duplicates,
                    'detection_methods' => $results['duplicate_detection_stats']
                ));
            }
        }
        
        // Log nested extraction effectiveness
        if (!empty($results['nested_extraction_stats'])) {
            $stats = $results['nested_extraction_stats'];
            $total_attempts = $stats['successful_extractions'] + $stats['failed_extractions'];
            
            if ($total_attempts > 0) {
                $success_rate = round(($stats['successful_extractions'] / $total_attempts) * 100, 2);
                
                $this->info("Nested data extraction summary", array(
                    'session_id' => $session_id,
                    'total_extraction_attempts' => $total_attempts,
                    'successful_extractions' => $stats['successful_extractions'],
                    'failed_extractions' => $stats['failed_extractions'],
                    'success_rate_percentage' => $success_rate,
                    'unique_paths_used' => count($stats['nested_paths_used'] ?? array())
                ));
            }
        }
    }
    
    /**
     * Summarize field type progress for logging
     *
     * @param    array    $field_type_progress    Field type progress data
     * @return   array    Summarized field type data
     */
    private function summarize_field_type_progress($field_type_progress) {
        $summary = array();
        
        foreach ($field_type_progress as $field_type => $stats) {
            $total_attempts = $stats['processed'] + $stats['errors'];
            
            if ($total_attempts > 0) {
                $success_rate = round(($stats['processed'] / $total_attempts) * 100, 2);
                
                $summary[$field_type] = array(
                    'processed' => $stats['processed'],
                    'errors' => $stats['errors'],
                    'success_rate_percentage' => $success_rate
                );
            }
        }
        
        return $summary;
    }

    /**
     * Log import batch processing
     *
     * @since    1.0.0
     * @param    string    $session_id    Import session ID
     * @param    int       $batch_num     Batch number
     * @param    array     $batch_results Batch results
     */
    public function log_batch_processed($session_id, $batch_num, $batch_results = array()) {
        $this->debug("Batch processed", array(
            'session_id' => $session_id,
            'batch_number' => $batch_num,
            'results' => $batch_results
        ));
    }
    
    /**
     * Log enhanced import batch processing with field type tracking
     *
     * @since    1.0.0
     * @param    string    $session_id    Import session ID
     * @param    int       $batch_num     Batch number
     * @param    array     $batch_results Enhanced batch results with field type tracking
     */
    public function log_batch_processed_enhanced($session_id, $batch_num, $batch_results = array()) {
        $this->debug("Enhanced batch processed", array(
            'session_id' => $session_id,
            'batch_number' => $batch_num,
            'created' => $batch_results['created'] ?? 0,
            'updated' => $batch_results['updated'] ?? 0,
            'skipped' => $batch_results['skipped'] ?? 0,
            'error_count' => count($batch_results['errors'] ?? array()),
            'field_type_progress' => $batch_results['field_type_progress'] ?? array(),
            'duplicate_detection' => $batch_results['duplicate_detection'] ?? array(),
            'nested_extraction_stats' => $batch_results['nested_extraction_stats'] ?? array()
        ));
        
        // Log field-specific processing details if debug mode is enabled
        if ($this->debug_mode && !empty($batch_results['field_type_progress'])) {
            foreach ($batch_results['field_type_progress'] as $field_type => $stats) {
                if ($stats['processed'] > 0 || $stats['errors'] > 0) {
                    $this->debug("Field type processing: {$field_type}", array(
                        'session_id' => $session_id,
                        'batch_number' => $batch_num,
                        'processed' => $stats['processed'],
                        'errors' => $stats['errors']
                    ));
                }
            }
        }
        
        // Log nested extraction details if available
        if (!empty($batch_results['nested_extraction_stats']['nested_paths_used'])) {
            $this->debug("Nested paths used in batch", array(
                'session_id' => $session_id,
                'batch_number' => $batch_num,
                'paths' => $batch_results['nested_extraction_stats']['nested_paths_used']
            ));
        }
    }

    /**
     * Log post creation/update
     *
     * @since    1.0.0
     * @param    string    $action        'created' or 'updated'
     * @param    int       $post_id       WordPress post ID
     * @param    array     $post_data     Original post data
     */
    public function log_post_action($action, $post_id, $post_data = array()) {
        $this->debug("Post {$action}", array(
            'post_id' => $post_id,
            'action' => $action,
            'data' => $post_data
        ));
    }

    /**
     * Log media import
     *
     * @since    1.0.0
     * @param    string    $url           Media URL
     * @param    int       $attachment_id Attachment ID (if successful)
     * @param    string    $error         Error message (if failed)
     */
    public function log_media_import($url, $attachment_id = null, $error = null) {
        if ($error) {
            $this->warning("Media import failed", array(
                'url' => $url,
                'error' => $error
            ));
        } else {
            $this->debug("Media imported", array(
                'url' => $url,
                'attachment_id' => $attachment_id
            ));
        }
    }

    /**
     * Rotate logs if current log file is too large
     *
     * @since    1.0.0
     */
    private function rotate_logs_if_needed() {
        if (!file_exists($this->log_file)) {
            return;
        }

        if (filesize($this->log_file) > self::MAX_LOG_SIZE) {
            $this->rotate_logs();
        }
    }

    /**
     * Rotate log files
     *
     * @since    1.0.0
     */
    private function rotate_logs() {
        // Move existing log files
        for ($i = self::MAX_LOG_FILES - 1; $i >= 1; $i--) {
            $old_file = $this->log_dir . "json-post-importer.log.{$i}";
            $new_file = $this->log_dir . "json-post-importer.log." . ($i + 1);
            
            if (file_exists($old_file)) {
                if ($i == self::MAX_LOG_FILES - 1) {
                    // Delete the oldest file
                    unlink($old_file);
                } else {
                    rename($old_file, $new_file);
                }
            }
        }

        // Move current log to .1
        if (file_exists($this->log_file)) {
            rename($this->log_file, $this->log_dir . 'json-post-importer.log.1');
        }
    }

    /**
     * Get log files list
     *
     * @since    1.0.0
     * @return   array    Array of log file information
     */
    public function get_log_files() {
        $files = array();
        
        if (file_exists($this->log_file)) {
            $files[] = array(
                'name' => 'json-post-importer.log',
                'path' => $this->log_file,
                'size' => filesize($this->log_file),
                'modified' => filemtime($this->log_file)
            );
        }

        // Get rotated log files
        for ($i = 1; $i <= self::MAX_LOG_FILES; $i++) {
            $file_path = $this->log_dir . "json-post-importer.log.{$i}";
            if (file_exists($file_path)) {
                $files[] = array(
                    'name' => "json-post-importer.log.{$i}",
                    'path' => $file_path,
                    'size' => filesize($file_path),
                    'modified' => filemtime($file_path)
                );
            }
        }

        return $files;
    }

    /**
     * Get log file content
     *
     * @since    1.0.0
     * @param    string    $filename    Log filename
     * @param    int       $lines       Number of lines to read (0 for all)
     * @return   string|false           Log content or false on error
     */
    public function get_log_content($filename = 'json-post-importer.log', $lines = 0) {
        $file_path = $this->log_dir . $filename;
        
        if (!file_exists($file_path)) {
            return false;
        }

        if ($lines == 0) {
            return file_get_contents($file_path);
        }

        // Read last N lines
        $file = new SplFileObject($file_path, 'r');
        $file->seek(PHP_INT_MAX);
        $total_lines = $file->key();
        
        $start_line = max(0, $total_lines - $lines);
        $content = '';
        
        $file->seek($start_line);
        while (!$file->eof()) {
            $content .= $file->current();
            $file->next();
        }

        return $content;
    }

    /**
     * Clear log files
     *
     * @since    1.0.0
     * @param    string    $filename    Specific filename to clear (optional)
     * @return   bool                   Success status
     */
    public function clear_logs($filename = null) {
        if ($filename) {
            $file_path = $this->log_dir . $filename;
            if (file_exists($file_path)) {
                return unlink($file_path);
            }
            return false;
        }

        // Clear all log files
        $files = glob($this->log_dir . 'json-post-importer.log*');
        $success = true;
        
        foreach ($files as $file) {
            if (!unlink($file)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Enable or disable debug mode
     *
     * @since    1.0.0
     * @param    bool    $enabled    Debug mode status
     */
    public function set_debug_mode($enabled) {
        $this->debug_mode = (bool) $enabled;
        update_option('jpi_debug_mode', $this->debug_mode);
        
        if ($enabled) {
            $this->info("Debug mode enabled");
        } else {
            $this->info("Debug mode disabled");
        }
    }

    /**
     * Check if debug mode is enabled
     *
     * @since    1.0.0
     * @return   bool    Debug mode status
     */
    public function is_debug_mode() {
        return $this->debug_mode;
    }

    /**
     * Get log statistics
     *
     * @since    1.0.0
     * @return   array    Log statistics
     */
    public function get_log_stats() {
        $stats = array(
            'total_files' => 0,
            'total_size' => 0,
            'oldest_entry' => null,
            'newest_entry' => null,
            'debug_mode' => $this->debug_mode
        );

        $files = $this->get_log_files();
        $stats['total_files'] = count($files);

        foreach ($files as $file) {
            $stats['total_size'] += $file['size'];
            
            if ($stats['oldest_entry'] === null || $file['modified'] < $stats['oldest_entry']) {
                $stats['oldest_entry'] = $file['modified'];
            }
            
            if ($stats['newest_entry'] === null || $file['modified'] > $stats['newest_entry']) {
                $stats['newest_entry'] = $file['modified'];
            }
        }

        return $stats;
    }
}