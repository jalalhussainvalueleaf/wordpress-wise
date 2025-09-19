# JSON Post Importer Test Suite

This directory contains comprehensive unit and integration tests for the JSON Post Importer WordPress plugin.

## Test Structure

```
tests/
├── bootstrap.php                    # PHPUnit bootstrap file
├── phpunit.xml                     # PHPUnit configuration
├── run-tests.sh                    # Test runner script
├── README.md                       # This file
├── unit/                          # Unit tests
│   ├── test-basic-functionality.php
│   ├── test-json-post-creator.php
│   ├── test-field-mapping-validation.php
│   └── test-api-endpoints.php
├── integration/                   # Integration tests
│   └── test-complete-workflows.php
└── utils/                        # Test utilities
    ├── class-test-data-factory.php
    └── class-test-helpers.php
```

## Test Coverage

### Unit Tests

1. **JSON_Post_Creator Class Tests** (`test-json-post-creator.php`)
   - Basic post creation from JSON data
   - Post updates and existing post handling
   - Field sanitization and validation
   - Taxonomy and meta field processing
   - Date parsing and error handling
   - Media handling workflows

2. **Field Mapping and Validation Tests** (`test-field-mapping-validation.php`)
   - JSON structure validation
   - Field mapping configuration validation
   - Custom field and taxonomy mapping
   - Data type handling
   - Error handling for malformed data

3. **API Endpoints Tests** (`test-api-endpoints.php`)
   - REST API route registration
   - Permission checking
   - Request/response handling
   - Error response formatting
   - Custom post type support

4. **Basic Functionality Tests** (`test-basic-functionality.php`)
   - WordPress environment verification
   - Plugin class loading
   - Test utility functionality

### Integration Tests

1. **Complete Workflows Tests** (`test-complete-workflows.php`)
   - End-to-end file upload workflow
   - Complete API import workflow
   - Batch import processing
   - Media handling integration
   - Custom field mapping workflows
   - Error handling across components
   - Update existing posts workflow

## Requirements

- PHP 7.4 or higher
- WordPress test suite
- PHPUnit 9.0 or higher
- MySQL/MariaDB for test database
- Composer (for dependency management)

## Setup

### 1. Install Dependencies

```bash
# Install Composer dependencies
composer install

# Or use the test runner
./tests/run-tests.sh install
```

### 2. Configure Environment

Set the following environment variables (optional):

```bash
export WP_TESTS_DIR=/tmp/wordpress-tests-lib
export WP_CORE_DIR=/tmp/wordpress/
export DB_NAME=wordpress_test
export DB_USER=root
export DB_PASS=
export DB_HOST=localhost
```

### 3. Install WordPress Test Suite

The test runner will automatically install the WordPress test suite:

```bash
./tests/run-tests.sh install
```

## Running Tests

### Run All Tests

```bash
# Using test runner (recommended)
./tests/run-tests.sh test

# Using PHPUnit directly
phpunit --configuration tests/phpunit.xml
```

### Run Specific Test Files

```bash
# Run only unit tests
phpunit --configuration tests/phpunit.xml tests/unit/

# Run only integration tests
phpunit --configuration tests/phpunit.xml tests/integration/

# Run specific test file
phpunit --configuration tests/phpunit.xml tests/unit/test-json-post-creator.php
```

### Run Specific Test Methods

```bash
# Run specific test method
phpunit --configuration tests/phpunit.xml --filter test_create_basic_post
```

### Generate Coverage Report

```bash
# Using test runner
./tests/run-tests.sh coverage

# Using PHPUnit directly
phpunit --configuration tests/phpunit.xml --coverage-html tests/coverage
```

## Code Style Checks

```bash
# Run code style checks
./tests/run-tests.sh cs

# Fix code style issues automatically
composer cbf
```

## Test Data and Utilities

### Test Data Factory

The `Test_Data_Factory` class provides methods to create test data:

```php
// Create sample JSON data
$json_data = Test_Data_Factory::create_sample_json_data();

// Create field mappings
$mappings = Test_Data_Factory::create_field_mappings();

// Create import options
$options = Test_Data_Factory::create_import_options();
```

### Test Helpers

The `Test_Helpers` class provides utility methods:

```php
// Clean up test data
Test_Helpers::cleanup_test_data();

// Mark posts/terms as test data
Test_Helpers::mark_as_test_post($post_id);
Test_Helpers::mark_as_test_term($term_id);

// Create temporary JSON files
$file_path = Test_Helpers::create_temp_json_file($data);

// Assert post meta and terms
Test_Helpers::assert_post_meta($post_id, $expected_meta);
Test_Helpers::assert_post_terms($post_id, $taxonomy, $expected_terms);
```

## Writing New Tests

### Unit Test Example

```php
class Test_My_Feature extends WP_UnitTestCase {
    
    public function setUp(): void {
        parent::setUp();
        // Setup code
    }
    
    public function tearDown(): void {
        Test_Helpers::cleanup_test_data();
        parent::tearDown();
    }
    
    public function test_my_feature() {
        // Test implementation
        $this->assertTrue(true);
    }
}
```

### Integration Test Example

```php
class Test_My_Integration extends WP_UnitTestCase {
    
    public function test_complete_workflow() {
        // Test complete workflow
        $result = $this->run_complete_workflow();
        $this->assertIsArray($result);
        
        // Clean up
        Test_Helpers::cleanup_test_data();
    }
}
```

## Continuous Integration

The test suite is designed to work with CI/CD pipelines. Example GitHub Actions workflow:

```yaml
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:5.7
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: wordpress_test
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 7.4
      - name: Install dependencies
        run: composer install
      - name: Run tests
        run: ./tests/run-tests.sh test
        env:
          DB_PASSWORD: root
```

## Troubleshooting

### Common Issues

1. **WordPress test suite not found**
   - Run `./tests/run-tests.sh install` to install the test suite

2. **Database connection errors**
   - Ensure MySQL/MariaDB is running
   - Check database credentials in environment variables
   - Create the test database manually if needed

3. **Permission errors**
   - Make sure the test runner script is executable: `chmod +x tests/run-tests.sh`

4. **Memory limit errors**
   - Increase PHP memory limit: `php -d memory_limit=512M vendor/bin/phpunit`

### Debug Mode

Enable debug output by setting the `WP_DEBUG` constant:

```php
// In wp-tests-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Contributing

When adding new features:

1. Write tests for new functionality
2. Ensure all existing tests pass
3. Maintain test coverage above 80%
4. Follow WordPress coding standards
5. Update this README if adding new test categories

## Test Requirements Coverage

This test suite covers the following requirements from the specification:

- **Requirement 1.2**: Field mapping and validation logic
- **Requirement 3.2**: API endpoint functionality and responses  
- **Requirement 4.1**: Core post creation and update functionality
- **Requirement 5.2**: Complete import workflows and error handling