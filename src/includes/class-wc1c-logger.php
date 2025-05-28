<?php
/**
 * The logger functionality of the plugin
 *
 * @package WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/includes
 * @author Igor Melnyk <igor.melnyk.it@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * The logger class
 */
class WC1C_Logger {

    /**
     * Log levels
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';

    /**
     * Log a debug message
     *
     * @param string $message Log message
     * @param array $context Log context
     */
    public static function debug($message, $context = array()) {
        self::log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Log an info message
     *
     * @param string $message Log message
     * @param array $context Log context
     */
    public static function info($message, $context = array()) {
        self::log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log a warning message
     *
     * @param string $message Log message
     * @param array $context Log context
     */
    public static function warning($message, $context = array()) {
        self::log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log an error message
     *
     * @param string $message Log message
     * @param array $context Log context
     */
    public static function error($message, $context = array()) {
        self::log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log a message
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Log context
     */
    private static function log($level, $message, $context = array()) {
        // Check if logging is enabled
        if (get_option('wc1c_enable_logging', 'yes') !== 'yes') {
            return;
        }
        
        // Check log level threshold
        $min_level = get_option('wc1c_log_level', 'info');
        
        if (!self::is_level_enabled($level, $min_level)) {
            return;
        }
        
        // Get log file path
        $file_path = self::get_log_file_path();
        
        if (!$file_path) {
            return;
        }
        
        // Format log entry
        $log_entry = array(
            'timestamp' => current_time('mysql', true),
            'level' => $level,
            'message' => $message,
            'context' => $context
        );
        
        // Convert to JSON
        $json_entry = wp_json_encode($log_entry) . PHP_EOL;
        
        // Write to file
        file_put_contents($file_path, $json_entry, FILE_APPEND);
    }

    /**
     * Check if log level is enabled
     *
     * @param string $level Log level to check
     * @param string $min_level Minimum log level
     * @return bool Is level enabled
     */
    private static function is_level_enabled($level, $min_level) {
        $levels = array(
            self::LEVEL_DEBUG => 0,
            self::LEVEL_INFO => 1,
            self::LEVEL_WARNING => 2,
            self::LEVEL_ERROR => 3
        );
        
        // If level doesn't exist, default to info
        $level_value = isset($levels[$level]) ? $levels[$level] : 1;
        $min_level_value = isset($levels[$min_level]) ? $levels[$min_level] : 1;
        
        return $level_value >= $min_level_value;
    }

    /**
     * Get log file path
     *
     * @return string|false Log file path or false on failure
     */
    private static function get_log_file_path() {
        // Get upload directory
        $upload_dir = wp_upload_dir();
        
        // Build log directory path
        $log_dir = $upload_dir['basedir'] . '/woocommerce_uploads/1c-exchange/logs/';
        
        // Create log directory if it doesn't exist
        if (!file_exists($log_dir)) {
            if (!wp_mkdir_p($log_dir)) {
                return false;
            }
            
            // Create .htaccess file to protect logs
            file_put_contents($log_dir . '.htaccess', 'deny from all');
            
            // Create index.php file to prevent directory listing
            file_put_contents($log_dir . 'index.php', '<?php // Silence is golden');
        }
        
        // Get current date for log file name
        $date = date('Y-m-d');
        
        // Build log file path
        $log_file = $log_dir . 'wc1c-' . $date . '.log';
        
        return $log_file;
    }

    /**
     * Get log entries
     *
     * @param array $args Query arguments
     * @return array Log entries
     */
    public static function get_log_entries($args = array()) {
        // Default arguments
        $defaults = array(
            'page' => 1,
            'per_page' => 50,
            'level' => '',
            'search' => '',
            'date_from' => '',
            'date_to' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Get upload directory
        $upload_dir = wp_upload_dir();
        
        // Build log directory path
        $log_dir = $upload_dir['basedir'] . '/woocommerce_uploads/1c-exchange/logs/';
        
        // Return empty result if directory doesn't exist
        if (!file_exists($log_dir)) {
            return array(
                'entries' => array(),
                'total' => 0,
                'pages' => 0
            );
        }
        
        // Get log files
        $log_files = glob($log_dir . 'wc1c-*.log');
        
        // Sort files by date (newest first)
        usort($log_files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        // Filter files by date
        if (!empty($args['date_from']) || !empty($args['date_to'])) {
            $filtered_files = array();
            
            foreach ($log_files as $file) {
                // Extract date from filename
                preg_match('/wc1c-(\d{4}-\d{2}-\d{2})\.log/', $file, $matches);
                
                if (empty($matches[1])) {
                    continue;
                }
                
                $file_date = $matches[1];
                
                // Check date from
                if (!empty($args['date_from']) && $file_date < $args['date_from']) {
                    continue;
                }
                
                // Check date to
                if (!empty($args['date_to']) && $file_date > $args['date_to']) {
                    continue;
                }
                
                $filtered_files[] = $file;
            }
            
            $log_files = $filtered_files;
        }
        
        // Read and parse log entries
        $entries = array();
        
        foreach ($log_files as $file) {
            // Read file line by line
            $handle = fopen($file, 'r');
            
            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    // Skip empty lines
                    if (empty(trim($line))) {
                        continue;
                    }
                    
                    // Parse JSON
                    $entry = json_decode($line, true);
                    
                    if (!$entry) {
                        continue;
                    }
                    
                    // Filter by level
                    if (!empty($args['level']) && $entry['level'] !== $args['level']) {
                        continue;
                    }
                    
                    // Filter by search
                    if (!empty($args['search'])) {
                        $search_in = strtolower($entry['message']);
                        
                        // Also search in context if available
                        if (!empty($entry['context']) && is_array($entry['context'])) {
                            $search_in .= ' ' . strtolower(wp_json_encode($entry['context']));
                        }
                        
                        if (strpos($search_in, strtolower($args['search'])) === false) {
                            continue;
                        }
                    }
                    
                    // Add entry to results
                    $entries[] = $entry;
                }
                
                fclose($handle);
            }
        }
        
        // Count total entries
        $total = count($entries);
        
        // Calculate total pages
        $per_page = max(1, (int) $args['per_page']);
        $pages = ceil($total / $per_page);
        
        // Paginate entries
        $page = max(1, min($pages, (int) $args['page']));
        $offset = ($page - 1) * $per_page;
        $entries = array_slice($entries, $offset, $per_page);
        
        return array(
            'entries' => $entries,
            'total' => $total,
            'pages' => $pages
        );
    }

    /**
     * Clean up old logs
     */
    public static function cleanup_old_logs() {
        // Get retention days
        $retention_days = get_option('wc1c_log_retention_days', 30);
        
        // Skip if retention is disabled
        if ($retention_days <= 0) {
            return;
        }
        
        // Get upload directory
        $upload_dir = wp_upload_dir();
        
        // Build log directory path
        $log_dir = $upload_dir['basedir'] . '/woocommerce_uploads/1c-exchange/logs/';
        
        // Skip if directory doesn't exist
        if (!file_exists($log_dir)) {
            return;
        }
        
        // Get log files
        $log_files = glob($log_dir . 'wc1c-*.log');
        
        // Calculate cutoff timestamp
        $cutoff = time() - ($retention_days * DAY_IN_SECONDS);
        
        // Delete old files
        $deleted = 0;
        
        foreach ($log_files as $file) {
            // Check file modification time
            if (filemtime($file) < $cutoff) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }
        
        if ($deleted > 0) {
            self::info('Cleaned up old logs', array(
                'deleted_files' => $deleted,
                'retention_days' => $retention_days
            ));
        }
    }
}

// Add cleanup cron job
add_action('wc1c_daily_maintenance', array('WC1C_Logger', 'cleanup_old_logs'));