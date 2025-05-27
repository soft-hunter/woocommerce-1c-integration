<?php
/**
 * Comprehensive Plugin Tests
 *
 * @package WooCommerce_1C_Integration
 */

class WC1C_Comprehensive_Test extends WP_UnitTestCase {

    private $plugin_dir;
    
    public function setUp(): void {
        parent::setUp();
        $this->plugin_dir = dirname(__DIR__) . '/src';
        
        // Activate WooCommerce if available
        if (class_exists('WooCommerce')) {
            activate_plugin('woocommerce/woocommerce.php');
        }
    }

    /**
     * Test plugin constants
     */
    public function test_plugin_constants() {
        // Load main plugin file
        require_once $this->plugin_dir . '/woocommerce-1c-integration.php';
        
        $this->assertTrue(defined('WC1C_VERSION'), 'WC1C_VERSION constant should be defined');
        $this->assertTrue(defined('WC1C_PLUGIN_FILE'), 'WC1C_PLUGIN_FILE constant should be defined');
        $this->assertTrue(defined('WC1C_PLUGIN_DIR'), 'WC1C_PLUGIN_DIR constant should be defined');
        $this->assertTrue(defined('WC1C_PLUGIN_URL'), 'WC1C_PLUGIN_URL constant should be defined');
    }

    /**
     * Test class autoloading
     */
    public function test_class_autoloading() {
        $classes_to_test = array(
            'WC1C',
            'WC1C_Activator',
            'WC1C_Deactivator',
            'WC1C_Loader',
            'WC1C_Logger',
            'WC1C_Admin',
            'WC1C_Exchange',
            'WC1C_Auth',
            'WC1C_XML_Parser',
            'WC1C_HPOS_Order_Mapper'
        );

        foreach ($classes_to_test as $class) {
            $file_path = $this->get_class_file_path($class);
            if ($file_path && file_exists($file_path)) {
                require_once $file_path;
                $this->assertTrue(class_exists($class), "Class $class should exist");
            }
        }
    }

    /**
     * Test HPOS compatibility
     */
    public function test_hpos_compatibility() {
        // Mock WooCommerce HPOS classes if not available
        if (!class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
            $this->markTestSkipped('WooCommerce HPOS not available');
        }

        $mapper_file = $this->plugin_dir . '/exchange/mappers/class-wc1c-hpos-order-mapper.php';
        $this->assertFileExists($mapper_file, 'HPOS Order Mapper should exist');

        require_once $mapper_file;
        $this->assertTrue(class_exists('WC1C_HPOS_Order_Mapper'), 'HPOS Order Mapper class should exist');

        $mapper = new WC1C_HPOS_Order_Mapper();
        $this->assertTrue(method_exists($mapper, 'get_orders'), 'get_orders method should exist');
        $this->assertTrue(method_exists($mapper, 'create_order'), 'create_order method should exist');
        $this->assertTrue(method_exists($mapper, 'update_order'), 'update_order method should exist');
    }

    /**
     * Test security measures
     */
    public function test_security_measures() {
        $php_files = $this->get_all_php_files();
        
        foreach ($php_files as $file) {
            $content = file_get_contents($file);
            
            // Check for direct access protection
            $this->assertStringContainsString(
                "if (!defined('ABSPATH'))",
                $content,
                "File $file should have direct access protection"
            );
            
            // Check for potential SQL injection vulnerabilities
            if (preg_match('/\$wpdb->query\s*\(\s*["\'].*\$/', $content)) {
                $this->assertStringContainsString(
                    '$wpdb->prepare(',
                    $content,
                    "File $file should use prepared statements"
                );
            }
        }
    }

    /**
     * Test plugin activation/deactivation
     */
    public function test_plugin_activation() {
        $activator_file = $this->plugin_dir . '/includes/class-wc1c-activator.php';
        $this->assertFileExists($activator_file, 'Activator class should exist');

        require_once $activator_file;
        $this->assertTrue(class_exists('WC1C_Activator'), 'Activator class should be loadable');
        $this->assertTrue(method_exists('WC1C_Activator', 'activate'), 'Activate method should exist');

        $deactivator_file = $this->plugin_dir . '/includes/class-wc1c-deactivator.php';
        $this->assertFileExists($deactivator_file, 'Deactivator class should exist');

        require_once $deactivator_file;
        $this->assertTrue(class_exists('WC1C_Deactivator'), 'Deactivator class should be loadable');
        $this->assertTrue(method_exists('WC1C_Deactivator', 'deactivate'), 'Deactivate method should exist');
    }

    /**
     * Test logger functionality
     */
    public function test_logger() {
        $logger_file = $this->plugin_dir . '/includes/class-wc1c-logger.php';
        $this->assertFileExists($logger_file, 'Logger class should exist');

        require_once $logger_file;
        $this->assertTrue(class_exists('WC1C_Logger'), 'Logger class should be loadable');
        $this->assertTrue(method_exists('WC1C_Logger', 'log'), 'Log method should exist');

        // Test log levels
        $reflection = new ReflectionClass('WC1C_Logger');
        $this->assertTrue($reflection->hasConstant('ERROR'), 'ERROR log level should be defined');
        $this->assertTrue($reflection->hasConstant('WARNING'), 'WARNING log level should be defined');
        $this->assertTrue($reflection->hasConstant('INFO'), 'INFO log level should be defined');
        $this->assertTrue($reflection->hasConstant('DEBUG'), 'DEBUG log level should be defined');
    }

    /**
     * Test admin interface
     */
    public function test_admin_interface() {
        $admin_file = $this->plugin_dir . '/admin/class-wc1c-admin.php';
        $this->assertFileExists($admin_file, 'Admin class should exist');

        require_once $admin_file;
        $this->assertTrue(class_exists('WC1C_Admin'), 'Admin class should be loadable');

        // Check for required admin files
        $admin_css = $this->plugin_dir . '/admin/css/wc1c-admin.css';
        $this->assertFileExists($admin_css, 'Admin CSS should exist');

        $admin_js = $this->plugin_dir . '/admin/js/wc1c-admin.js';
        $this->assertFileExists($admin_js, 'Admin JS should exist');
    }

    /**
     * Test internationalization
     */
    public function test_internationalization() {
        $i18n_file = $this->plugin_dir . '/includes/class-wc1c-i18n.php';
        $this->assertFileExists($i18n_file, 'i18n class should exist');

        $pot_file = $this->plugin_dir . '/languages/woocommerce-1c-integration.pot';
        $this->assertFileExists($pot_file, 'POT file should exist');

        // Check for translation functions in PHP files
        $php_files = $this->get_all_php_files();
        $translation_found = false;

        foreach ($php_files as $file) {
            $content = file_get_contents($file);
            if (strpos($content, '__(' !== false || strpos($content, '_e(') !== false)) {
                $translation_found = true;
                break;
            }
        }

        $this->assertTrue($translation_found, 'Translation functions should be used');
    }

    /**
     * Helper method to get class file path
     */
    private function get_class_file_path($class_name) {
        $class_file = strtolower(str_replace('_', '-', $class_name));
        $class_file = 'class-' . $class_file . '.php';

        $possible_paths = array(
            $this->plugin_dir . '/includes/' . $class_file,
            $this->plugin_dir . '/admin/' . $class_file,
            $this->plugin_dir . '/exchange/' . $class_file,
            $this->plugin_dir . '/exchange/mappers/' . $class_file,
            $this->plugin_dir . '/public/' . $class_file,
        );

        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return false;
    }

    /**
     * Helper method to get all PHP files
     */
    private function get_all_php_files() {
        $files = array();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->plugin_dir)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}