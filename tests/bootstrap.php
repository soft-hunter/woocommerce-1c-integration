<?php
/**
 * PHPUnit bootstrap file
 *
 * @package WooCommerce_1C_Integration
 */

// Define test environment
define('WC1C_TESTING', true);

// Load WordPress test environment
$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

// Check if WordPress tests are available
if (!file_exists($_tests_dir . '/includes/functions.php')) {
    echo "WordPress test suite not found. Please install it first:\n";
    echo "bash bin/install-wp-tests.sh wordpress_test root '' localhost latest\n";
    exit(1);
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested
 */
function _manually_load_plugin() {
    require dirname(__DIR__) . '/src/woocommerce-1c-integration.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

require $_tests_dir . '/includes/bootstrap.php';