<?php
/**
 * Enhanced logging functionality
 *
 * @package    WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/includes
 * @author     Igor Melnyk <igormelnykit@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * Enhanced logging functionality for the plugin
 */
class WC1C_Logger {

    /**
     * Log levels
     */
    const EMERGENCY = 'emergency';
    const ALERT     = 'alert';
    const CRITICAL  = 'critical';
    const ERROR     = 'error';
    const WARNING   = 'warning';
    const NOTICE    = 'notice';
    const INFO      = 'info';
    const DEBUG     = 'debug';

    /**
     * WooCommerce logger instance
     *
     * @var WC_Logger
     */
    private static $wc_logger;

    /**
     * Plugin logger instance
     *
     * @var WC1C_Logger
     */
    private static $instance;

    /**
     * Log file path
     *
     * @var string
     */
    private static $log_file;

    /**
     * Log directory path
     *
     * @var string
     */
    private static $log_dir;

    /**
     * Maximum log file size (10MB)
     *
     * @var int
     */
    private static $max_file_size = 10485760;

    /**
     * Maximum number of log files to keep
     *
     * @var int
     */
    private static $max_files = 10;

    /**
     * Log level hierarchy for filtering
     *
     * @var array
     */
    private static $level_hierarchy = array(
        self::DEBUG     => 0,
        self::INFO      => 1,
        self::NOTICE    => 2,
        self::WARNING   => 3,
        self::ERROR     => 4,
        self::CRITICAL  => 5,
        self::ALERT     => 6,
        self::EMERGENCY => 7
    );

    /**
     * Initialize logger
     */
    public static function init() {
        // Initialize WooCommerce logger if available
        if (function_exists('wc_get_logger')) {
            self::$wc_logger = wc_get_logger();
        }

        // Set up log directory
        $upload_dir = wp_upload_dir();
        self::$log_dir = $upload_dir['basedir'] . '/woocommerce-1c-integration/logs';
        
        // Create log directory if it doesn't exist
        if (!is_dir(self::$log_dir)) {
            wp_mkdir_p(self::$log_dir);
            
            // Add security files
            file_put_contents(self::$log_dir . '/index.html', '');
            file_put_contents(self::$log_dir . '/.htaccess', 'Deny from all');
        }

        // Set current log file
        self::$log_file = self::$log_dir . '/wc1c-' . date('Y-m-d') . '.log';

        // Schedule log cleanup
        self::schedule_cleanup();
    }

    /**
     * Get logger instance
     *
     * @return WC1C_Logger
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Log a message
     *
     * @param string $message Log message
     * @param string $level   Log level
     * @param array  $context Additional context data
     */
    public static function log($message, $level = self::INFO, $context = array()) {
        // Check if logging is enabled
        if (!self::is_logging_enabled()) {
            return;
        }

        // Check if we should log this level
        if (!self::should_log_level($level)) {
            return;
        }

        // Initialize if not done
        if (!self::$log_file) {
            self::init();
        }

        // Prepare log entry
        $timestamp = current_time('Y-m-d H:i:s');
        $ip = self::get_client_ip();
        $user = wp_get_current_user();
        $username = $user->exists() ? $user->user_login : 'anonymous';
        
        // Add system context information
        $context_info = array_merge(array(
            'ip' => $ip,
            'user' => $username,
            'memory_usage' => size_format(memory_get_usage(true)),
            'memory_peak' => size_format(memory_get_peak_usage(true)),
            'request_uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''
        ), $context);

        // Format context as JSON
        $context_str = !empty($context_info) ? ' ' . wp_json_encode($context_info, JSON_UNESCAPED_UNICODE) : '';
        
        // Create log entry
        $log_entry = "[{$timestamp}] [{$level}] {$message}{$context_str}" . PHP_EOL;

        // Write to file
        self::write_to_file($log_entry);

        // Write to WooCommerce logger if available
        if (self::$wc_logger) {
            self::$wc_logger->log($level, $message, array(
                'source' => 'woocommerce-1c-integration',
                'context' => $context_info
            ));
        }

        // Write to database for important messages
        if (in_array($level, array(self::ERROR, self::CRITICAL, self::ALERT, self::EMERGENCY))) {
            self::write_to_database($message, $level, $context_info);
        }

        // Rotate log files if needed
        self::rotate_logs();

        // Trigger action for external handling
        do_action('wc1c_log_entry', $message, $level, $context_info);
    }

    /**
     * Log emergency message
     *
     * @param string $message Log message
     * @param array  $context Additional context data
     */
    public static function emergency($message, $context = array()) {
        self::log($message, self::EMERGENCY, $context);
    }

    /**
     * Log alert message
     *
     * @param string $message Log message
     * @param array  $context Additional context data
     */
    public static function alert($message, $context = array()) {
        self::log($message, self::ALERT, $context);
    }

    /**
     * Log critical message
     *
     * @param string $message Log message
     * @param array  $context Additional context data
     */
    public static function critical($message, $context = array()) {
        self::log($message, self::CRITICAL, $context);
    }

    /**
     * Log error message
     *
     * @param string $message Log message
     * @param array  $context Additional context data
     */
    public static function error($message, $context = array()) {
        self::log($message, self::ERROR, $context);
    }

    /**
     * Log warning message
     *
     * @param string $message Log message
     * @param array  $context Additional context data
     */
    public static function warning($message, $context = array()) {
        self::log($message, self::WARNING, $context);
    }

    /**
     * Log notice message
     *
     * @param string $message Log message
     * @param array  $context Additional context data
     */
    public static function notice($message, $context = array()) {
        self::log($message, self::NOTICE, $context);
    }

    /**
     * Log info message
     *
     * @param string $message Log message
     * @param array  $context Additional context data
     */
    public static function info($message, $context = array()) {
        self::log($message, self::INFO, $context);
    }

    /**
     * Log debug message
     *
     * @param string $message Log message
     * @param array  $context Additional context data
     */
    public static function debug($message, $context = array()) {
        self::log($message, self::DEBUG, $context);
    }

    /**
     * Write log entry to file
     *
     * @param string $log_entry Log entry
     */
    private static function write_to_file($log_entry) {
        if (!self::$log_file) {
            return;
        }

        // Use file locking to prevent corruption
        $fp = fopen(self::$log_file, 'a');
        if ($fp) {
            if (flock($fp, LOCK_EX)) {
                fwrite($fp, $log_entry);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }
    }

    /**
     * Write important log entries to database
     *
     * @param string $message Log message
     * @param string $level   Log level
     * @param array  $context Context data
     */
    private static function write_to_database($message, $level, $context = array()) {
        global $wpdb;

        // Check if the table exists
        $table_name = $wpdb->prefix . 'wc1c_exchange_logs';
        
        // Create table if it doesn't exist
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            self::create_log_table();
        }

        $wpdb->insert(
            $table_name,
            array(
                'exchange_type' => 'system',
                'operation' => 'log',
                'status' => $level,
                'message' => $message,
                'data' => wp_json_encode($context),
                'execution_time' => 0,
                'memory_usage' => memory_get_usage(true),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s')
        );
    }

    /**
     * Create log table
     */
    private static function create_log_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wc1c_exchange_logs';
        
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            exchange_type varchar(50) NOT NULL DEFAULT '',
            operation varchar(100) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT '',
            message text,
            data longtext,
            execution_time decimal(10,4) DEFAULT 0,
            memory_usage bigint(20) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY exchange_type (exchange_type),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Check if logging is enabled
     *
     * @return bool
     */
    private static function is_logging_enabled() {
        return get_option('wc1c_enable_logging', 'yes') === 'yes';
    }

    /**
     * Check if we should log this level
     *
     * @param string $level Log level
     * @return bool
     */
    private static function should_log_level($level) {
        $min_level = get_option('wc1c_log_level', self::INFO);
        
        if (!isset(self::$level_hierarchy[$level]) || !isset(self::$level_hierarchy[$min_level])) {
            return true; // Log unknown levels by default
        }

        return self::$level_hierarchy[$level] >= self::$level_hierarchy[$min_level];
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private static function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'HTTP_X_REAL_IP',            // Nginx proxy
            'REMOTE_ADDR'                // Standard
        );

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                
                $ip = trim($ip);
                
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Rotate log files if they get too large
     */
    private static function rotate_logs() {
        if (!self::$log_file || !file_exists(self::$log_file)) {
            return;
        }

        // Check if current log file is too large
        if (filesize(self::$log_file) > self::$max_file_size) {
            $backup_file = self::$log_file . '.old';
            
            // Remove old backup if exists
            if (file_exists($backup_file)) {
                unlink($backup_file);
            }
            
            // Rename current log to backup
            rename(self::$log_file, $backup_file);
            
            // Log rotation message
            self::log('Log file rotated', self::INFO);
        }
    }

    /**
     * Clean up old log files
     */
    public static function cleanup_old_logs() {
        if (!self::$log_dir || !is_dir(self::$log_dir)) {
            return;
        }

        $retention_days = get_option('wc1c_log_retention_days', 30);
        $cutoff_time = time() - ($retention_days * DAY_IN_SECONDS);

        $files = glob(self::$log_dir . '/wc1c-*.log*');
        $deleted_count = 0;

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $deleted_count++;
                }
            }
        }

        // Keep only the most recent files if we have too many
        $remaining_files = glob(self::$log_dir . '/wc1c-*.log*');
        if (count($remaining_files) > self::$max_files) {
            // Sort by modification time (newest first)
            usort($remaining_files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });

            // Remove excess files
            $excess_files = array_slice($remaining_files, self::$max_files);
            foreach ($excess_files as $file) {
                if (unlink($file)) {
                    $deleted_count++;
                }
            }
        }

        if ($deleted_count > 0) {
            self::info("Cleaned up {$deleted_count} old log files");
        }
    }

    /**
     * Schedule log cleanup
     */
    private static function schedule_cleanup() {
        if (!wp_next_scheduled('wc1c_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'wc1c_cleanup_logs');
        }
    }

    /**
     * Get log files list
     *
     * @return array List of log files
     */
    public static function get_log_files() {
        if (!self::$log_dir || !is_dir(self::$log_dir)) {
            return array();
        }

        $files = glob(self::$log_dir . '/wc1c-*.log*');
        $log_files = array();

        foreach ($files as $file) {
            $log_files[] = array(
                'name' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'modified' => filemtime($file),
                'readable' => is_readable($file)
            );
        }

        // Sort by modification time (newest first)
        usort($log_files, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });

        return $log_files;
    }

    /**
     * Read log file content
     *
     * @param string $filename Log file name
     * @param int    $lines    Number of lines to read from end (default: 100)
     * @return string|false Log content or false on error
     */
    public static function read_log_file($filename, $lines = 100) {
        if (!self::$log_dir) {
            return false;
        }

        $file_path = self::$log_dir . '/' . basename($filename);
        
        if (!file_exists($file_path) || !is_readable($file_path)) {
            return false;
        }

        // Read last N lines efficiently
        $file = new SplFileObject($file_path, 'r');
        $file->seek(PHP_INT_MAX);
        $total_lines = $file->key();
        
        $start_line = max(0, $total_lines - $lines);
        $content = array();
        
        $file->seek($start_line);
        while (!$file->eof()) {
            $content[] = $file->current();
            $file->next();
        }

        return implode('', $content);
    }

    /**
     * Get log statistics
     *
     * @param int $days Number of days to analyze (default: 7)
     * @return array Log statistics
     */
    public static function get_log_statistics($days = 7) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wc1c_exchange_logs';
        $since = date('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            return array(
                'total_entries' => 0,
                'by_level' => array(),
                'by_day' => array(),
                'error_rate' => 0
            );
        }

        $stats = array(
            'total_entries' => 0,
            'by_level' => array(),
            'by_day' => array(),
            'error_rate' => 0
        );

        // Get total entries
        $stats['total_entries'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE created_at >= %s",
            $since
        ));

        // Get entries by level
        $by_level = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) as count FROM {$table_name} 
             WHERE created_at >= %s 
             GROUP BY status 
             ORDER BY count DESC",
            $since
        ));

        foreach ($by_level as $row) {
            $stats['by_level'][$row->status] = (int) $row->count;
        }

        // Get entries by day
        $by_day = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM {$table_name} 
             WHERE created_at >= %s 
             GROUP BY DATE(created_at) 
             ORDER BY date DESC",
            $since
        ));

        foreach ($by_day as $row) {
            $stats['by_day'][$row->date] = (int) $row->count;
        }

        // Calculate error rate
        $error_levels = array(self::ERROR, self::CRITICAL, self::ALERT, self::EMERGENCY);
        $error_count = 0;
        foreach ($error_levels as $level) {
            if (isset($stats['by_level'][$level])) {
                $error_count += $stats['by_level'][$level];
            }
        }

        $stats['error_rate'] = $stats['total_entries'] > 0 ? 
            round(($error_count / $stats['total_entries']) * 100, 2) : 0;

        return $stats;
    }

    /**
     * Clear all log files
     *
     * @return bool True on success, false on failure
     */
    public static function clear_logs() {
        if (!self::$log_dir || !is_dir(self::$log_dir)) {
            return false;
        }

        $files = glob(self::$log_dir . '/wc1c-*.log*');
        $success = true;

        foreach ($files as $file) {
            if (!unlink($file)) {
                $success = false;
            }
        }

        // Clear database logs
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc1c_exchange_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") == $table_name) {
            $wpdb->query("TRUNCATE TABLE {$table_name}");
        }

        if ($success) {
            self::info('All log files cleared');
        }

        return $success;
    }

    /**
     * Export logs as JSON
     *
     * @param array $options Export options
     * @return string JSON encoded logs
     */
    public static function export_logs($options = array()) {
        $defaults = array(
            'days' => 7,
            'level' => null,
            'include_context' => true
        );

        $options = wp_parse_args($options, $defaults);

        global $wpdb;
        $table_name = $wpdb->prefix . 'wc1c_exchange_logs';
        $since = date('Y-m-d H:i:s', time() - ($options['days'] * DAY_IN_SECONDS));

        $where_clauses = array("created_at >= %s");
        $where_values = array($since);

        if ($options['level']) {
            $where_clauses[] = "status = %s";
            $where_values[] = $options['level'];
        }

        $where_sql = implode(' AND ', $where_clauses);

        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY created_at DESC",
            $where_values
        ));

        $export_data = array(
            'export_date' => current_time('mysql'),
            'options' => $options,
            'statistics' => self::get_log_statistics($options['days']),
            'logs' => array()
        );

        foreach ($logs as $log) {
            $log_entry = array(
                'id' => $log->id,
                'exchange_type' => $log->exchange_type,
                'operation' => $log->operation,
                'status' => $log->status,
                'message' => $log->message,
                'execution_time' => $log->execution_time,
                'memory_usage' => $log->memory_usage,
                'created_at' => $log->created_at
            );

            if ($options['include_context'] && $log->data) {
                $log_entry['context'] = json_decode($log->data, true);
            }

            $export_data['logs'][] = $log_entry;
        }

        return wp_json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Import logs from JSON
     *
     * @param string $json_data JSON encoded logs
     * @return bool True on success, false on failure
     */
    public static function import_logs($json_data) {
        $data = json_decode($json_data, true);
        
        if (!$data || !isset($data['logs'])) {
            return false;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'wc1c_exchange_logs';
        
        // Ensure table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            self::create_log_table();
        }

        $success = true;
        foreach ($data['logs'] as $log) {
            $result = $wpdb->insert(
                $table_name,
                array(
                    'exchange_type' => $log['exchange_type'],
                    'operation' => $log['operation'],
                    'status' => $log['status'],
                    'message' => $log['message'],
                    'data' => isset($log['context']) ? wp_json_encode($log['context']) : '',
                    'execution_time' => $log['execution_time'],
                    'memory_usage' => $log['memory_usage'],
                    'created_at' => $log['created_at']
                ),
                array('%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s')
            );

            if ($result === false) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Get log entries for admin display
     *
     * @param array $args Query arguments
     * @return array Log entries with pagination info
     */
    public static function get_log_entries($args = array()) {
        global $wpdb;

        $defaults = array(
            'per_page' => 50,
            'page' => 1,
            'level' => '',
            'search' => '',
            'date_from' => '',
            'date_to' => '',
            'orderby' => 'created_at',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);
        $table_name = $wpdb->prefix . 'wc1c_exchange_logs';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            return array(
                'logs' => array(),
                'total' => 0,
                'pages' => 0,
                'current_page' => 1
            );
        }

        // Build WHERE clause
        $where_clauses = array('1=1');
        $where_values = array();

        if (!empty($args['level'])) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $args['level'];
        }

        if (!empty($args['search'])) {
            $where_clauses[] = 'message LIKE %s';
            $where_values[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }

        if (!empty($args['date_from'])) {
            $where_clauses[] = 'created_at >= %s';
            $where_values[] = $args['date_from'] . ' 00:00:00';
        }

        if (!empty($args['date_to'])) {
            $where_clauses[] = 'created_at <= %s';
            $where_values[] = $args['date_to'] . ' 23:59:59';
        }

        $where_sql = implode(' AND ', $where_clauses);

        // Get total count
        $count_sql = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_sql}";
        if (!empty($where_values)) {
            $total = $wpdb->get_var($wpdb->prepare($count_sql, $where_values));
        } else {
            $total = $wpdb->get_var($count_sql);
        }

        // Calculate pagination
        $total_pages = ceil($total / $args['per_page']);
        $offset = ($args['page'] - 1) * $args['per_page'];

        // Build ORDER BY clause
        $allowed_orderby = array('id', 'status', 'created_at', 'exchange_type', 'operation');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Get logs
        $logs_sql = "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, array($args['per_page'], $offset));
        
        if (!empty($where_values)) {
            $logs = $wpdb->get_results($wpdb->prepare($logs_sql, $query_values));
        } else {
            $logs = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table_name} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
                $args['per_page'],
                $offset
            ));
        }

        // Process logs for display
        $processed_logs = array();
        foreach ($logs as $log) {
            $processed_log = (array) $log;
            
            // Parse context data
            if ($log->data) {
                $processed_log['context'] = json_decode($log->data, true);
            } else {
                $processed_log['context'] = array();
            }

            // Format memory usage
            $processed_log['memory_usage_formatted'] = size_format($log->memory_usage);
            
            // Format execution time
            $processed_log['execution_time_formatted'] = number_format($log->execution_time, 4) . 's';
            
            // Add CSS class for level
            $processed_log['level_class'] = self::get_level_css_class($log->status);
            
            $processed_logs[] = $processed_log;
        }

        return array(
            'logs' => $processed_logs,
            'total' => (int) $total,
            'pages' => (int) $total_pages,
            'current_page' => (int) $args['page'],
            'per_page' => (int) $args['per_page']
        );
    }

    /**
     * Get CSS class for log level
     *
     * @param string $level Log level
     * @return string CSS class
     */
    private static function get_level_css_class($level) {
        $classes = array(
            self::EMERGENCY => 'log-emergency',
            self::ALERT     => 'log-alert',
            self::CRITICAL  => 'log-critical',
            self::ERROR     => 'log-error',
            self::WARNING   => 'log-warning',
            self::NOTICE    => 'log-notice',
            self::INFO      => 'log-info',
            self::DEBUG     => 'log-debug'
        );

        return isset($classes[$level]) ? $classes[$level] : 'log-unknown';
    }

    /**
     * Log exchange operation
     *
     * @param string $operation Operation name
     * @param string $status Operation status
     * @param string $message Log message
     * @param array $data Additional data
     * @param float $execution_time Execution time in seconds
     */
    public static function log_exchange($operation, $status, $message, $data = array(), $execution_time = 0) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wc1c_exchange_logs';
        
        // Ensure table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            self::create_log_table();
        }

        $wpdb->insert(
            $table_name,
            array(
                'exchange_type' => 'exchange',
                'operation' => $operation,
                'status' => $status,
                'message' => $message,
                'data' => wp_json_encode($data),
                'execution_time' => $execution_time,
                'memory_usage' => memory_get_usage(true),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s')
        );

        // Also log to file if it's an important operation
        if (in_array($status, array('error', 'critical', 'success'))) {
            $level = ($status === 'success') ? self::INFO : self::ERROR;
            self::log($message, $level, array_merge($data, array(
                'operation' => $operation,
                'execution_time' => $execution_time
            )));
        }
    }

    /**
     * Get recent exchange operations
     *
     * @param int $limit Number of operations to retrieve
     * @return array Recent exchange operations
     */
    public static function get_recent_exchanges($limit = 10) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wc1c_exchange_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            return array();
        }

        $exchanges = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE exchange_type = 'exchange' 
             ORDER BY created_at DESC 
             LIMIT %d",
            $limit
        ));

        $processed_exchanges = array();
        foreach ($exchanges as $exchange) {
            $processed_exchange = (array) $exchange;
            $processed_exchange['data'] = json_decode($exchange->data, true);
            $processed_exchange['memory_usage_formatted'] = size_format($exchange->memory_usage);
            $processed_exchange['execution_time_formatted'] = number_format($exchange->execution_time, 4) . 's';
            $processed_exchanges[] = $processed_exchange;
        }

        return $processed_exchanges;
    }

    /**
     * Monitor log file size and send alerts if needed
     */
    public static function monitor_log_size() {
        if (!self::$log_dir || !is_dir(self::$log_dir)) {
            return;
        }

        $total_size = 0;
        $files = glob(self::$log_dir . '/wc1c-*.log*');
        
        foreach ($files as $file) {
            $total_size += filesize($file);
        }

        // Alert if logs exceed 100MB
        $max_total_size = 100 * 1024 * 1024; // 100MB
        if ($total_size > $max_total_size) {
            self::warning('Log directory size exceeded limit', array(
                'current_size' => size_format($total_size),
                'max_size' => size_format($max_total_size),
                'file_count' => count($files)
            ));

            // Trigger cleanup
            self::cleanup_old_logs();
        }
    }

    /**
     * Get logger configuration
     *
     * @return array Logger configuration
     */
    public static function get_config() {
        return array(
            'enabled' => self::is_logging_enabled(),
            'level' => get_option('wc1c_log_level', self::INFO),
            'retention_days' => get_option('wc1c_log_retention_days', 30),
            'max_file_size' => self::$max_file_size,
            'max_files' => self::$max_files,
            'log_dir' => self::$log_dir,
            'current_log_file' => self::$log_file,
            'wc_logger_available' => (self::$wc_logger !== null)
        );
    }

    /**
     * Update logger configuration
     *
     * @param array $config Configuration options
     * @return bool True on success
     */
    public static function update_config($config) {
        $updated = false;

        if (isset($config['enabled'])) {
            update_option('wc1c_enable_logging', $config['enabled'] ? 'yes' : 'no');
            $updated = true;
        }

        if (isset($config['level']) && isset(self::$level_hierarchy[$config['level']])) {
            update_option('wc1c_log_level', $config['level']);
            $updated = true;
        }

        if (isset($config['retention_days']) && is_numeric($config['retention_days'])) {
            update_option('wc1c_log_retention_days', (int) $config['retention_days']);
            $updated = true;
        }

        return $updated;
    }

    /**
     * Test logging functionality
     *
     * @return array Test results
     */
    public static function test_logging() {
        $results = array(
            'file_logging' => false,
            'database_logging' => false,
            'wc_logging' => false,
            'directory_writable' => false,
            'errors' => array()
        );

        // Test directory writability
        if (self::$log_dir && is_dir(self::$log_dir) && is_writable(self::$log_dir)) {
            $results['directory_writable'] = true;
        } else {
            $results['errors'][] = 'Log directory is not writable: ' . self::$log_dir;
        }

        // Test file logging
        $test_message = 'Test log entry - ' . time();
        $test_file = self::$log_dir . '/test.log';
        
        if (file_put_contents($test_file, $test_message . PHP_EOL, LOCK_EX) !== false) {
            $results['file_logging'] = true;
            unlink($test_file); // Clean up
        } else {
            $results['errors'][] = 'Failed to write to log file';
        }

        // Test database logging
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc1c_exchange_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") == $table_name) {
            $test_result = $wpdb->insert(
                $table_name,
                array(
                    'exchange_type' => 'test',
                    'operation' => 'logging_test',
                    'status' => 'info',
                    'message' => $test_message,
                    'data' => '{}',
                    'execution_time' => 0,
                    'memory_usage' => 0,
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s')
            );

            if ($test_result !== false) {
                $results['database_logging'] = true;
                // Clean up test entry
                $wpdb->delete($table_name, array('message' => $test_message), array('%s'));
            } else {
                $results['errors'][] = 'Failed to write to database log table';
            }
        } else {
            $results['errors'][] = 'Database log table does not exist';
        }

        // Test WooCommerce logging
        if (self::$wc_logger) {
            try {
                self::$wc_logger->info($test_message, array('source' => 'woocommerce-1c-integration-test'));
                $results['wc_logging'] = true;
            } catch (Exception $e) {
                $results['errors'][] = 'WooCommerce logging failed: ' . $e->getMessage();
            }
        } else {
            $results['errors'][] = 'WooCommerce logger not available';
        }

        return $results;
    }

    /**
     * Get log level options for admin interface
     *
     * @return array Log level options
     */
    public static function get_level_options() {
        return array(
            self::DEBUG     => __('Debug', 'woocommerce-1c-integration'),
            self::INFO      => __('Info', 'woocommerce-1c-integration'),
            self::NOTICE    => __('Notice', 'woocommerce-1c-integration'),
            self::WARNING   => __('Warning', 'woocommerce-1c-integration'),
            self::ERROR     => __('Error', 'woocommerce-1c-integration'),
            self::CRITICAL  => __('Critical', 'woocommerce-1c-integration'),
            self::ALERT     => __('Alert', 'woocommerce-1c-integration'),
            self::EMERGENCY => __('Emergency', 'woocommerce-1c-integration')
        );
    }
}

// Initialize logger
WC1C_Logger::init();

// Hook cleanup function
add_action('wc1c_cleanup_logs', array('WC1C_Logger', 'cleanup_old_logs'));

// Hook log size monitoring
add_action('wc1c_daily_maintenance', array('WC1C_Logger', 'monitor_log_size'));