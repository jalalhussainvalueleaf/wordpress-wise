#!/bin/bash

# Test runner script for JSON Post Importer plugin

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Default values
WP_TESTS_DIR=${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR:-/tmp/wordpress/}
DB_NAME=${DB_NAME:-wordpress_test}
DB_USER=${DB_USER:-root}
DB_PASS=${DB_PASS:-}
DB_HOST=${DB_HOST:-localhost}

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to install WordPress test suite
install_wp_tests() {
    if [ -d $WP_TESTS_DIR ]; then
        print_status "WordPress test suite already installed at $WP_TESTS_DIR"
        return
    fi

    print_status "Installing WordPress test suite..."
    
    # Create tests directory
    mkdir -p $WP_TESTS_DIR
    
    # Download test suite
    svn co --quiet https://develop.svn.wordpress.org/trunk/tests/phpunit/includes/ $WP_TESTS_DIR/includes
    svn co --quiet https://develop.svn.wordpress.org/trunk/tests/phpunit/data/ $WP_TESTS_DIR/data
    
    # Download WordPress
    if [ ! -d $WP_CORE_DIR ]; then
        mkdir -p $WP_CORE_DIR
        wget -nv -O /tmp/wordpress.tar.gz https://wordpress.org/latest.tar.gz
        tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C $WP_CORE_DIR
    fi
    
    # Create wp-tests-config.php
    wget -nv -O $WP_TESTS_DIR/wp-tests-config.php https://develop.svn.wordpress.org/trunk/wp-tests-config-sample.php
    
    # Update config with database settings
    sed -i "s/youremptytestdbnamehere/$DB_NAME/" $WP_TESTS_DIR/wp-tests-config.php
    sed -i "s/yourusernamehere/$DB_USER/" $WP_TESTS_DIR/wp-tests-config.php
    sed -i "s/yourpasswordhere/$DB_PASS/" $WP_TESTS_DIR/wp-tests-config.php
    sed -i "s|localhost|$DB_HOST|" $WP_TESTS_DIR/wp-tests-config.php
    
    print_status "WordPress test suite installed successfully"
}

# Function to create test database
create_test_db() {
    print_status "Creating test database..."
    
    # Check if MySQL is available
    if ! command -v mysql &> /dev/null; then
        print_error "MySQL not found. Please install MySQL or MariaDB."
        exit 1
    fi
    
    # Create database
    mysql -u$DB_USER -p$DB_PASS -h$DB_HOST -e "CREATE DATABASE IF NOT EXISTS $DB_NAME;" 2>/dev/null || {
        print_warning "Could not create database. It may already exist or you may need to create it manually."
    }
}

# Function to install Composer dependencies
install_dependencies() {
    print_status "Installing Composer dependencies..."
    
    if ! command -v composer &> /dev/null; then
        print_error "Composer not found. Please install Composer first."
        exit 1
    fi
    
    cd "$(dirname "$0")/.."
    composer install --no-interaction --prefer-dist
}

# Function to run tests
run_tests() {
    print_status "Running tests..."
    
    cd "$(dirname "$0")/.."
    
    # Export environment variables
    export WP_TESTS_DIR
    export WP_CORE_DIR
    
    # Run PHPUnit
    if [ -f vendor/bin/phpunit ]; then
        vendor/bin/phpunit --configuration tests/phpunit.xml "$@"
    else
        phpunit --configuration tests/phpunit.xml "$@"
    fi
}

# Function to run code style checks
run_code_style() {
    print_status "Running code style checks..."
    
    cd "$(dirname "$0")/.."
    
    if [ -f vendor/bin/phpcs ]; then
        vendor/bin/phpcs --standard=WordPress includes/ admin/ public/
    else
        print_warning "PHPCS not found. Install via Composer to run code style checks."
    fi
}

# Function to generate coverage report
generate_coverage() {
    print_status "Generating coverage report..."
    
    cd "$(dirname "$0")/.."
    
    # Export environment variables
    export WP_TESTS_DIR
    export WP_CORE_DIR
    
    # Run PHPUnit with coverage
    if [ -f vendor/bin/phpunit ]; then
        vendor/bin/phpunit --configuration tests/phpunit.xml --coverage-html tests/coverage
    else
        phpunit --configuration tests/phpunit.xml --coverage-html tests/coverage
    fi
    
    print_status "Coverage report generated in tests/coverage/"
}

# Main script logic
case "$1" in
    "install")
        install_dependencies
        install_wp_tests
        create_test_db
        ;;
    "test")
        shift
        run_tests "$@"
        ;;
    "cs")
        run_code_style
        ;;
    "coverage")
        generate_coverage
        ;;
    "all")
        install_dependencies
        install_wp_tests
        create_test_db
        run_tests
        run_code_style
        ;;
    *)
        echo "Usage: $0 {install|test|cs|coverage|all}"
        echo ""
        echo "Commands:"
        echo "  install   - Install dependencies and WordPress test suite"
        echo "  test      - Run PHPUnit tests"
        echo "  cs        - Run code style checks"
        echo "  coverage  - Generate test coverage report"
        echo "  all       - Run install, test, and code style checks"
        echo ""
        echo "Environment variables:"
        echo "  WP_TESTS_DIR - WordPress test suite directory (default: /tmp/wordpress-tests-lib)"
        echo "  WP_CORE_DIR  - WordPress core directory (default: /tmp/wordpress/)"
        echo "  DB_NAME      - Test database name (default: wordpress_test)"
        echo "  DB_USER      - Database user (default: root)"
        echo "  DB_PASS      - Database password (default: empty)"
        echo "  DB_HOST      - Database host (default: localhost)"
        exit 1
        ;;
esac