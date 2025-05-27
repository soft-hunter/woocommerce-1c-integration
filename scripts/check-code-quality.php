<?php
/**
 * Code Quality Analysis Script
 * Checks for common code quality issues
 */

// Prevent direct web access
if (php_sapi_name() !== 'cli') {
    exit('This script must be run from command line.');
}

class CodeQualityChecker {
    
    private $errors = array();
    private $warnings = array();
    private $success = array();
    private $plugin_dir;
    
    public function __construct($plugin_dir) {
        $this->plugin_dir = rtrim($plugin_dir, '/');
    }
    
    public function check() {
        echo "🔍 Starting Code Quality Analysis...\n";
        
        $this->check_php_syntax();
        $this->check_security_issues();
        $this->check_performance_issues();
        $this->check_code_standards();
        $this->check_documentation();
        
        $this->display_results();
        
        return empty($this->errors);
    }
    
    private function check_php_syntax() {
        echo "🔧 Checking PHP syntax...\n";
        
        $php_files = $this->get_all_php_files();
        $syntax_errors = 0;
        
        foreach ($php_files as $file) {
            $output = array();
            $return_var = 0;
            exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $return_var);
            
            if ($return_var !== 0) {
                $relative_path = str_replace($this->plugin_dir . '/', '', $file);
                $this->errors[] = "❌ Syntax error in: $relative_path";
                $syntax_errors++;
            }
        }
        
        if ($syntax_errors === 0) {
            $this->success[] = "✅ All PHP files have valid syntax";
        }
    }
    
    private function check_security_issues() {
        echo "� Checking security issues...\n";
        
        $php_files = $this->get_all_php_files();
        
        foreach ($php_files as $file) {
            $content = file_get_contents($file);
            $relative_path = str_replace($this->plugin_dir . '/', '', $file);
            
            // Check for direct access protection
            if (!preg_match("/if\s*\(\s*!\s*defined\s*\(\s*['\"]ABSPATH['\"]\s*\)\s*\)/", $content)) {
                $this->errors[] = "❌ Missing direct access protection: $relative_path";
            }
            
            // Check for SQL injection risks
            if (preg_match('/\$wpdb->(query|get_var|get_results|get_col)/', $content)) {
                if (!preg_match('/\$wpdb->prepare\s*\(/', $content)) {
                    $this->warnings[] = "⚠️ Potential SQL injection risk: $relative_path";
                }
            }
            
            // Check for XSS risks
            if (preg_match('/echo\s+\$[^;]*;/', $content)) {
                if (!preg_match('/(esc_html|esc_attr|wp_kses)/', $content)) {
                    $this->warnings[] = "⚠️ Potential XSS risk: $relative_path";
                }
            }
        }
        
        $this->success[] = "✅ Security analysis completed";
    }
    
    private function check_performance_issues() {
        echo "⚡ Checking performance issues...\n";
        
        $php_files = $this->get_all_php_files();
        
        foreach ($php_files as $file) {
            $content = file_get_contents($file);
            $relative_path = str_replace($this->plugin_dir . '/', '', $file);
            
            // Check for inefficient database queries
            if (preg_match('/get_posts\s*\(\s*array\s*\(.*["\']numberposts["\']\s*=>\s*-1/', $content)) {
                $this->warnings[] = "⚠️ Potentially inefficient query (numberposts = -1): $relative_path";
            }
            
            // Check for missing caching
            if (preg_match('/\$wpdb->get_/', $content)) {
                if (!preg_match('/(wp_cache_get|wp_cache_set|transient)/', $content)) {
                    $this->warnings[] = "⚠️ Database query without caching: $relative_path";
                }
            }
        }
        
        $this->success[] = "✅ Performance analysis completed";
    }
    
    private function check_code_standards() {
        echo "� Checking coding standards...\n";
        
        $php_files = $this->get_all_php_files();
        
        foreach ($php_files as $file) {
            $content = file_get_contents($file);
            $relative_path = str_replace($this->plugin_dir . '/', '', $file);
            
            // Check for proper indentation (basic check)
            $lines = explode("\n", $content);
            foreach ($lines as $line_num => $line) {
                if (preg_match('/^\t+ /', $line)) {
                    $this->warnings[] = "⚠️ Mixed tabs and spaces on line " . ($line_num + 1) . ": $relative_path";
                    break; // Only report once per file
                }
            }
            
            // Check for proper function naming
            if (preg_match('/function\s+[A-Z]/', $content)) {
                $this->warnings[] = "⚠️ Function names should be lowercase: $relative_path";
            }
        }
        
        $this->success[] = "✅ Coding standards check completed";
    }
    
    private function check_documentation() {
        echo "📚 Checking documentation...\n";
        
        $php_files = $this->get_all_php_files();
        $documented_files = 0;
        
        foreach ($php_files as $file) {
            $content = file_get_contents($file);
            
            // Check for PHPDoc comments
            if (preg_match('/\/\*\*/', $content)) {
                $documented_files++;
            }
        }
        
        $total_files = count($php_files);
        $documentation_percentage = ($documented_files / $total_files) * 100;
        
        if ($documentation_percentage >= 80) {
            $this->success[] = "✅ Good documentation coverage: " . round($documentation_percentage) . "%";
        } elseif ($documentation_percentage >= 50) {
            $this->warnings[] = "⚠️ Moderate documentation coverage: " . round($documentation_percentage) . "%";
        } else {
            $this->errors[] = "❌ Poor documentation coverage: " . round($documentation_percentage) . "%";
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
        echo "\n📊 Code Quality Results:\n";
        
        if (!empty($this->success)) {
            echo "\n✅ SUCCESS (" . count($this->success) . "):\n";
            foreach ($this->success as $message) {
                echo "  $message\n";
            }
        }
        
        if (!empty($this->warnings)) {
            echo "\n⚠️ WARNINGS (" . count($this->warnings) . "):\n";
            foreach ($this->warnings as $message) {
                echo "  $message\n";
            }
        }
        
        if (!empty($this->errors)) {
            echo "\n❌ ERRORS (" . count($this->errors) . "):\n";
            foreach ($this->errors as $message) {
                echo "  $message\n";
            }
        }
        
        echo "\n📈 Summary:\n";
        echo "  Success: " . count($this->success) . "\n";
        echo "  Warnings: " . count($this->warnings) . "\n";
        echo "  Errors: " . count($this->errors) . "\n";
        
        if (empty($this->errors)) {
            echo "\n🎉 Code quality check passed!\n";
        } else {
            echo "\n❌ Code quality check failed. Please fix errors.\n";
        }
    }
}

// Run code quality check
$plugin_dir = dirname(__DIR__);
$checker = new CodeQualityChecker($plugin_dir);
$success = $checker->check();
exit($success ? 0 : 1);