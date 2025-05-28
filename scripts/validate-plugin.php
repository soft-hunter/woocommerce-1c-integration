<?php
/**
 * Plugin Validation Script
 * Run this script to validate the plugin structure and code
 */

// Prevent direct web access
if (php_sapi_name() !== 'cli') {
    exit('This script must be run from command line.');
}

// Colors for CLI output
class Colors {
    const RED = "\033[0;31m";
    const GREEN = "\033[0;32m";
    const YELLOW = "\033[1;33m";
    const BLUE = "\033[0;34m";
    const NC = "\033[0m"; // No Color
}

class PluginValidator {
    
    private $errors = array();
    private $warnings = array();
    private $success = array();
    private $plugin_dir;
    
    public function __construct($plugin_dir) {
        $this->plugin_dir = rtrim($plugin_dir, '/');
    }
    
    public function validate() {
        echo Colors::BLUE . "🔍 Starting Plugin Validation...\n" . Colors::NC;
        
        $this->check_file_structure();
        $this->check_main_plugin_file();
        $this->check_class_files();
        $this->check_security();
        $this->check_documentation();
        $this->check_wordpress_standards();
        
        $this->display_results();
        
        return empty($this->errors);
    }
    
    private function check_file_structure() {
        echo "📁 Checking file structure...\n";
        
        $required_files = array(
            'woocommerce-1c-integration.php',
            'includes/class-wc1c.php',
            'includes/class-wc1c-activator.php',
            'includes/class-wc1c-deactivator.php',
            'includes/class-wc1c-loader.php',
            'includes/class-wc1c-logger.php',
            'admin/class-wc1c-admin.php',
            'exchange/class-wc1c-exchange.php',
            'exchange/class-wc1c-auth.php',
            'exchange/class-wc1c-xml-parser.php',
            'exchange/mappers/class-wc1c-hpos-order-mapper.php',
            'uninstall.php',
            'composer.json',
            'README.md',
            'CHANGELOG.md',
            'LICENSE'
        );
        
        foreach ($required_files as $file) {
            $full_path = $this->plugin_dir . '/' . $file;
            if (file_exists($full_path)) {
                $this->success[] = "✅ Found: $file";
            } else {
                $this->errors[] = "❌ Missing: $file";
            }
        }
    }
    
    private function check_main_plugin_file() {
        echo "🔧 Checking main plugin file...\n";
        
        $main_file = $this->plugin_dir . '/src/woocommerce-1c-integration.php';
        if (!file_exists($main_file)) {
            $this->errors[] = "❌ Main plugin file not found";
            return;
        }
        
        $content = file_get_contents($main_file);
        
        // Check plugin header
        $required_headers = array(
            'Plugin Name:',
            'Version:',
            'Description:',
            'Author:',
            'License:',
            'Text Domain:'
        );
        
        foreach ($required_headers as $header) {
            if (strpos($content, $header) !== false) {
                $this->success[] = "✅ Plugin header contains: $header";
            } else {
                $this->errors[] = "❌ Missing plugin header: $header";
            }
        }
        
        // Check security
        if (strpos($content, "if (!defined('ABSPATH'))") !== false) {
            $this->success[] = "✅ Direct access protection found";
        } else {
            $this->errors[] = "❌ Missing direct access protection";
        }
    }
    
    private function check_class_files() {
        echo "🏗️ Checking class files...\n";
        
        $class_files = glob($this->plugin_dir . '/src/*/class-*.php');
        $class_files = array_merge($class_files, glob($this->plugin_dir . '/src/*/*/class-*.php'));
        
        foreach ($class_files as $file) {
            $relative_path = str_replace($this->plugin_dir . '/', '', $file);
            $content = file_get_contents($file);
            
            // Check direct access protection
            if (strpos($content, "if (!defined('ABSPATH'))") !== false) {
                $this->success[] = "✅ Security check in: $relative_path";
            } else {
                $this->errors[] = "❌ Missing security check in: $relative_path";
            }
        }
    }
    
    private function check_security() {
        echo "🔒 Checking security measures...\n";
        
        // Check uninstall.php
        $uninstall_file = $this->plugin_dir . '/src/uninstall.php';
        if (file_exists($uninstall_file)) {
            $content = file_get_contents($uninstall_file);
            if (strpos($content, "if (!defined('WP_UNINSTALL_PLUGIN'))") !== false) {
                $this->success[] = "✅ Uninstall.php has proper security check";
            } else {
                $this->errors[] = "❌ Uninstall.php missing security check";
            }
        }
    }
    
    private function check_documentation() {
        echo "📚 Checking documentation...\n";
        
        $doc_files = array('README.md', 'CHANGELOG.md', 'LICENSE');
        
        foreach ($doc_files as $file) {
            $full_path = $this->plugin_dir . '/' . $file;
            if (file_exists($full_path) && filesize($full_path) > 100) {
                $this->success[] = "✅ Documentation file exists and has content: $file";
            } else {
                $this->warnings[] = "⚠️ Documentation file missing or empty: $file";
            }
        }
    }
    
    private function check_wordpress_standards() {
        echo "📋 Checking WordPress standards...\n";
        
        // Check for WordPress hooks
        $php_files = $this->get_all_php_files();
        $hooks_found = false;
        
        foreach ($php_files as $file) {
            $content = file_get_contents($file);
            if (strpos($content, 'add_action') !== false || strpos($content, 'add_filter') !== false) {
                $hooks_found = true;
                break;
            }
        }
        
        if ($hooks_found) {
            $this->success[] = "✅ WordPress hooks found";
        } else {
            $this->warnings[] = "⚠️ No WordPress hooks found";
        }
    }
    
    private function get_all_php_files() {
        $files = array();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->plugin_dir . '/src')
        );
        
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
    
    private function display_results() {
        echo "\n" . Colors::BLUE . "📊 Validation Results:\n" . Colors::NC;
        
        if (!empty($this->success)) {
            echo Colors::GREEN . "\n✅ SUCCESS (" . count($this->success) . "):\n" . Colors::NC;
            foreach ($this->success as $message) {
                echo "  $message\n";
            }
        }
        
        if (!empty($this->warnings)) {
            echo Colors::YELLOW . "\n⚠️ WARNINGS (" . count($this->warnings) . "):\n" . Colors::NC;
            foreach ($this->warnings as $message) {
                echo "  $message\n";
            }
        }
        
        if (!empty($this->errors)) {
            echo Colors::RED . "\n❌ ERRORS (" . count($this->errors) . "):\n" . Colors::NC;
            foreach ($this->errors as $message) {
                echo "  $message\n";
            }
        }
        
        echo "\n" . Colors::BLUE . "📈 Summary:\n" . Colors::NC;
        echo "  Success: " . Colors::GREEN . count($this->success) . Colors::NC . "\n";
        echo "  Warnings: " . Colors::YELLOW . count($this->warnings) . Colors::NC . "\n";
        echo "  Errors: " . Colors::RED . count($this->errors) . Colors::NC . "\n";
        
        if (empty($this->errors)) {
            echo "\n" . Colors::GREEN . "🎉 Plugin validation passed! Ready for deployment.\n" . Colors::NC;
        } else {
            echo "\n" . Colors::RED . "❌ Plugin validation failed. Please fix errors before deployment.\n" . Colors::NC;
        }
    }
}

// Run validation
$plugin_dir = dirname(__DIR__);
$validator = new PluginValidator($plugin_dir);
$success = $validator->validate();
exit($success ? 0 : 1);