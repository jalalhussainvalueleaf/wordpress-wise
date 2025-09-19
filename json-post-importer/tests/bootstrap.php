<?php
/**
 * PHPUnit bootstrap file for JSON Post Importer tests
 */

// Define test environment
define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname(__DIR__) . '/vendor/yoast/phpunit-polyfills');

// WordPress test suite configuration
$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

// Give access to tests_add_filter() function
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested
 */
function _manually_load_plugin() {
    require dirname(dirname(__FILE__)) . '/json-post-importer.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php';

// Load test utilities
require_once dirname(__FILE__) . '/utils/class-test-data-factory.php';
require_once dirname(__FILE__) . '/utils/class-test-helpers.php';