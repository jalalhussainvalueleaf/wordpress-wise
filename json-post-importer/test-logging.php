<?php
/**
 * Simple test script for logging functionality
 * This file should be removed in production
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Only allow administrators to run this test
if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}

// Load the logger
require_once plugin_dir_path(__FILE__) . 'includes/class-json-post-importer-logger.php';

// Create logger instance
$logger = new JSON_Post_Importer_Logger();

// Test different log levels
$logger->info('Test logging functionality initialized');
$logger->debug('This is a debug message (only visible if debug mode is enabled)');
$logger->warning('This is a warning message');
$logger->error('This is an error message');

// Test import logging
$logger->log_import_start('test_session_123', array(
    'total_items' => 10,
    'batch_size' => 5
));

$logger->log_batch_processed('test_session_123', 1, array(
    'created' => 3,
    'updated' => 1,
    'skipped' => 1,
    'errors' => array()
));

$logger->log_post_action('created', 123, array('title' => 'Test Post'));

$logger->log_media_import('https://example.com/image.jpg', 456);

$logger->log_import_end('test_session_123', array(
    'created_posts' => 5,
    'updated_posts' => 2,
    'skipped_posts' => 2,
    'error_count' => 1
));

echo '<div class="notice notice-success"><p>Logging test completed! Check the logs in the admin interface.</p></div>';