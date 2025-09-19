<?php
/**
 * Simple test runner for basic validation without full WordPress test suite
 * This can be used for quick syntax and basic functionality checks
 */

// Basic error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Colors for CLI output
class TestColors {
    const RED = "\033[0;31m";
    const GREEN = "\033[0;32m";
    const YELLOW = "\033[1;33m";
    const BLUE = "\033[0;34m";
    const NC = "\033[0m"; // No Color
}

// Mock WP_Error class if not exists
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $errors = array();
        private $error_data = array();
        
        public function __construct($code = '', $message = '', $data = '') {
            if (!empty($code)) {
                $this->errors[$code][] = $message;
                if (!empty($data)) {
                    $this->error_data[$code] = $data;
                }
            }
        }
        
        public function get_error_code() {
            $codes = array_keys($this->errors);
            return empty($codes) ? '' : $codes[0];
        }
        
        public function get_error_message($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            return isset($this->errors[$code]) ? $this->errors[$code][0] : '';
        }
        
        public function get_error_data($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            return isset($this->error_data[$code]) ? $this->error_data[$code] : null;
        }
    }
}

class SimpleTestRunner {
    private $tests_passed = 0;
    private $tests_failed = 0;
    private $errors = array();
    
    public function run() {
        echo TestColors::BLUE . "JSON Post Importer - Simple Test Runner" . TestColors::NC . "\n";
        echo str_repeat("=", 50) . "\n\n";
        
        // Test 1: Check if plugin files exist
        $this->test_plugin_files_exist();
        
        // Test 2: Check PHP syntax
        $this->test_php_syntax();
        
        // Test 3: Check class definitions
        $this->test_class_definitions();
        
        // Test 4: Check test utility classes
        $this->test_utility_classes();
        
        // Test 5: Validate JSON test data
        $this->test_json_data_validation();
        
        // Print results
        $this->print_results();
    }
    
    private function test_plugin_files_exist() {
        echo "Testing plugin file structure...\n";
        
        $required_files = array(
            '../includes/class-json-post-creator.php',
            '../includes/class-json-post-importer-api.php',
            '../includes/class-json-post-importer-admin.php',
            '../includes/class-json-post-importer-logger.php',
            '../json-post-importer.php',
            'utils/class-test-data-factory.php',
            'utils/class-test-helpers.php'
        );
        
        foreach ($required_files as $file) {
            if (file_exists(__DIR__ . '/' . $file)) {
                $this->pass("✓ File exists: " . basename($file));
            } else {
                $this->fail("✗ Missing file: " . $file);
            }
        }
        echo "\n";
    }
    
    private function test_php_syntax() {
        echo "Testing PHP syntax...\n";
        
        $php_files = $this->get_php_files();
        
        foreach ($php_files as $file) {
            $output = array();
            $return_code = 0;
            
            exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $return_code);
            
            if ($return_code === 0) {
                $this->pass("✓ Syntax OK: " . basename($file));
            } else {
                $this->fail("✗ Syntax Error in " . basename($file) . ": " . implode("\n", $output));
            }
        }
        echo "\n";
    }
    
    private function test_class_definitions() {
        echo "Testing class definitions...\n";
        
        // Mock WordPress functions for basic testing
        $this->mock_wordpress_functions();
        
        $class_files = array(
            '../includes/class-json-post-creator.php' => 'JSON_Post_Creator',
            '../includes/class-json-post-importer-api.php' => 'JSON_Post_Importer_API',
            '../includes/class-json-post-importer-logger.php' => 'JSON_Post_Importer_Logger',
            'utils/class-test-data-factory.php' => 'Test_Data_Factory',
            'utils/class-test-helpers.php' => 'Test_Helpers'
        );
        
        foreach ($class_files as $file => $class_name) {
            $file_path = __DIR__ . '/' . $file;
            if (file_exists($file_path)) {
                include_once $file_path;
                
                if (class_exists($class_name)) {
                    $this->pass("✓ Class defined: " . $class_name);
                } else {
                    $this->fail("✗ Class not found: " . $class_name);
                }
            }
        }
        echo "\n";
    }
    
    private function test_utility_classes() {
        echo "Testing utility classes...\n";
        
        if (class_exists('Test_Data_Factory')) {
            try {
                $sample_data = Test_Data_Factory::create_sample_json_data();
                if (is_array($sample_data) && count($sample_data) > 0) {
                    $this->pass("✓ Test_Data_Factory::create_sample_json_data() works");
                } else {
                    $this->fail("✗ Test_Data_Factory::create_sample_json_data() returned invalid data");
                }
                
                $mappings = Test_Data_Factory::create_field_mappings();
                if (is_array($mappings) && isset($mappings['basic_mapping'])) {
                    $this->pass("✓ Test_Data_Factory::create_field_mappings() works");
                } else {
                    $this->fail("✗ Test_Data_Factory::create_field_mappings() returned invalid data");
                }
            } catch (Exception $e) {
                $this->fail("✗ Test_Data_Factory error: " . $e->getMessage());
            }
        }
        
        if (class_exists('Test_Helpers')) {
            $this->pass("✓ Test_Helpers class loaded");
        }
        echo "\n";
    }
    
    private function test_json_data_validation() {
        echo "Testing JSON data validation...\n";
        
        if (class_exists('Test_Data_Factory')) {
            $sample_data = Test_Data_Factory::create_sample_json_data();
            $json_string = json_encode($sample_data);
            
            if ($json_string !== false) {
                $this->pass("✓ Sample data can be encoded to JSON");
                
                $decoded = json_decode($json_string, true);
                if ($decoded !== null && is_array($decoded)) {
                    $this->pass("✓ JSON can be decoded back to array");
                } else {
                    $this->fail("✗ JSON decode failed");
                }
            } else {
                $this->fail("✗ Sample data cannot be encoded to JSON");
            }
            
            // Test malformed data
            $malformed = Test_Data_Factory::create_malformed_json_data();
            if (is_array($malformed) && isset($malformed['empty_array'])) {
                $this->pass("✓ Malformed test data structure is correct");
            } else {
                $this->fail("✗ Malformed test data structure is invalid");
            }
        }
        echo "\n";
    }
    
    private function get_php_files() {
        $files = array();
        
        // Get plugin files
        $plugin_dirs = array('../includes/', '../admin/', '../public/');
        foreach ($plugin_dirs as $dir) {
            if (is_dir(__DIR__ . '/' . $dir)) {
                $files = array_merge($files, glob(__DIR__ . '/' . $dir . '*.php'));
            }
        }
        
        // Get test files
        $test_dirs = array('unit/', 'integration/', 'utils/');
        foreach ($test_dirs as $dir) {
            if (is_dir(__DIR__ . '/' . $dir)) {
                $files = array_merge($files, glob(__DIR__ . '/' . $dir . '*.php'));
            }
        }
        
        return $files;
    }
    
    private function mock_wordpress_functions() {
        // Mock essential WordPress functions for basic testing
        if (!function_exists('wp_parse_args')) {
            function wp_parse_args($args, $defaults = '') {
                if (is_object($args)) {
                    $r = get_object_vars($args);
                } elseif (is_array($args)) {
                    $r =& $args;
                } else {
                    parse_str($args, $r);
                }
                
                if (is_array($defaults)) {
                    return array_merge($defaults, $r);
                }
                return $r;
            }
        }
        
        if (!function_exists('sanitize_text_field')) {
            function sanitize_text_field($str) {
                return strip_tags($str);
            }
        }
        
        if (!function_exists('sanitize_textarea_field')) {
            function sanitize_textarea_field($str) {
                return strip_tags($str);
            }
        }
        
        if (!function_exists('wp_kses_post')) {
            function wp_kses_post($data) {
                return strip_tags($data, '<p><br><strong><em><ul><ol><li><a><img>');
            }
        }
        
        if (!function_exists('current_time')) {
            function current_time($type) {
                return date('Y-m-d H:i:s');
            }
        }
        
        if (!function_exists('__')) {
            function __($text, $domain = 'default') {
                return $text;
            }
        }
        
        // WP_Error class will be mocked separately if needed
    }
    
    private function pass($message) {
        echo TestColors::GREEN . $message . TestColors::NC . "\n";
        $this->tests_passed++;
    }
    
    private function fail($message) {
        echo TestColors::RED . $message . TestColors::NC . "\n";
        $this->tests_failed++;
        $this->errors[] = $message;
    }
    
    private function print_results() {
        echo str_repeat("=", 50) . "\n";
        echo TestColors::BLUE . "Test Results:" . TestColors::NC . "\n";
        echo TestColors::GREEN . "Passed: " . $this->tests_passed . TestColors::NC . "\n";
        
        if ($this->tests_failed > 0) {
            echo TestColors::RED . "Failed: " . $this->tests_failed . TestColors::NC . "\n";
            echo "\nErrors:\n";
            foreach ($this->errors as $error) {
                echo TestColors::RED . "- " . strip_tags($error) . TestColors::NC . "\n";
            }
        }
        
        $total = $this->tests_passed + $this->tests_failed;
        $percentage = $total > 0 ? round(($this->tests_passed / $total) * 100, 2) : 0;
        
        echo "\nSuccess Rate: " . $percentage . "%\n";
        
        if ($this->tests_failed === 0) {
            echo TestColors::GREEN . "\n🎉 All tests passed! The plugin structure looks good." . TestColors::NC . "\n";
        } else {
            echo TestColors::YELLOW . "\n⚠️  Some tests failed. Please review the errors above." . TestColors::NC . "\n";
        }
    }
}

// Run the tests
$runner = new SimpleTestRunner();
$runner->run();