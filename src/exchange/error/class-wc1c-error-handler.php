<?php
/**
 * Error handler for 1C integration
 *
 * @package    WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/exchange/error
 * @author     Igor Melnyk <igor.melnyk.it@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * Error handler for 1C integration
 */
class WC1C_Error_Handler {

    /**
     * Error levels
     */
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_CRITICAL = 'CRITICAL';

    /**
     * Collected errors
     *
     * @var array
     */
    private static $errors = array();

    /**
     * Error context
     *
     * @var array
     */
    private static $context = array();

    /**
     * Maximum errors to store
     *
     * @var int
     */
    private static $max_errors = 1000;

    /**
     * Initialize error handler
     */
    public static function init() {
        // Set custom error and exception handlers for 1C operations
        add_action('wc1c_exchange_start', array(__CLASS__, 'start_error_handling'));
        add_action('wc1c_exchange_end', array(__CLASS__, 'end_error_handling'));
    }

    /**
     * Start error handling for exchange
     */
    public static function start_error_handling() {
        self::set_context('exchange', 'started');
        self::clear_errors();
    }

    /**
     * End error handling for exchange
     */
    public static function end_error_handling() {
        self::set_context('exchange', 'completed');
        
        // Log error summary if there were any errors
        if (self::has_errors()) {
            self::log_error_summary();
        }
    }

    /**
     * Add error
     *
     * @param string $message Error message
     * @param string $level Error level
     * @param array $context Additional context
     * @param Exception|null $exception Exception object
     */
    public static function add_error($message, $level = self::LEVEL_ERROR, $context = array(), $exception = null) {
        $error = array(
            'message' => $message,
            'level' => $level,
            'context' => array_merge(self::$context, $context),
            'timestamp' => time(),
            'microtime' => microtime(true),
            'memory_usage' => memory_get_usage(true),
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10)
        );

        if ($exception) {
            $error['exception'] = array(
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            );
        }

        // Add to errors array
        self::$errors[] = $error;

        // Limit the number of stored errors
        if (count(self::$errors) > self::$max_errors) {
            array_shift(self::$errors);
        }

        // Log immediately for critical errors
        if ($level === self::LEVEL_CRITICAL || $level === self::LEVEL_ERROR) {
            self::log_error($error);
        }

        // Trigger action for external handling
        do_action('wc1c_error_added', $error);
    }

    /**
     * Add warning
     *
     * @param string $message Warning message
     * @param array $context Additional context
     */
    public static function add_warning($message, $context = array()) {
        self::add_error($message, self::LEVEL_WARNING, $context);
    }

    /**
     * Add info message
     *
     * @param string $message Info message
     * @param array $context Additional context
     */
    public static function add_info($message, $context = array()) {
        self::add_error($message, self::LEVEL_INFO, $context);
    }

    /**
     * Add debug message
     *
     * @param string $message Debug message
     * @param array $context Additional context
     */
    public static function add_debug($message, $context = array()) {
        if (defined('WC1C_DEBUG') && WC1C_DEBUG) {
            self::add_error($message, self::LEVEL_DEBUG, $context);
        }
    }

    /**
     * Handle exception
     *
     * @param Exception $exception Exception to handle
     * @param array $context Additional context
     */
    public static function handle_exception($exception, $context = array()) {
        $message = sprintf(
            'Exception: %s in %s:%d',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );

        self::add_error($message, self::LEVEL_CRITICAL, $context, $exception);
    }

    /**
     * Handle WordPress error
     *
     * @param WP_Error $wp_error WordPress error object
     * @param array $context Additional context
     */
    public static function handle_wp_error($wp_error, $context = array()) {
        if (!is_wp_error($wp_error)) {
            return;
        }

        foreach ($wp_error->get_error_codes() as $code) {
            $messages = $wp_error->get_error_messages($code);
            foreach ($messages as $message) {
                $error_context = array_merge($context, array('wp_error_code' => $code));
                self::add_error($message, self::LEVEL_ERROR, $error_context);
            }
        }
    }

    /**
     * Set context information
     *
     * @param string $key Context key
     * @param mixed $value Context value
     */
    public static function set_context($key, $value) {
        self::$context[$key] = $value;
    }

    /**
     * Get context information
     *
     * @param string $key Context key (optional)
     * @return mixed Context value or all context if key not provided
     */
    public static function get_context($key = null) {
        if ($key === null) {
            return self::$context;
        }

        return isset(self::$context[$key]) ? self::$context[$key] : null;
    }

    /**
     * Clear context
     */
    public static function clear_context() {
        self::$context = array();
    }

    /**
     * Get all errors
     *
     * @param string $level Filter by error level (optional)
     * @return array Array of errors
     */
    public static function get_errors($level = null) {
        if ($level === null) {
            return self::$errors;
        }

        return array_filter(self::$errors, function($error) use ($level) {
            return $error['level'] === $level;
        });
    }

    /**
     * Check if there are any errors
     *
     * @param string $level Check for specific error level (optional)
     * @return bool True if there are errors
     */
    public static function has_errors($level = null) {
        return count(self::get_errors($level)) > 0;
    }

    /**
     * Get error count
     *
     * @param string $level Count specific error level (optional)
     * @return int Number of errors
     */
    public static function get_error_count($level = null) {
        return count(self::get_errors($level));
    }

    /**
     * Clear all errors
     */
    public static function clear_errors() {
        self::$errors = array();
    }

    /**
     * Get error summary
     *
     * @return array Error summary
     */
    public static function get_error_summary() {
        $summary = array(
            'total' => count(self::$errors),
            'by_level' => array(),
            'recent_errors' => array_slice(self::$errors, -10), // Last 10 errors
            'first_error' => null,
            'last_error' => null
        );

        // Count by level
        foreach (self::$errors as $error) {
            $level = $error['level'];
            if (!isset($summary['by_level'][$level])) {
                $summary['by_level'][$level] = 0;
            }
            $summary['by_level'][$level]++;
        }

        // Get first and last errors
        if (!empty(self::$errors)) {
            $summary['first_error'] = self::$errors[0];
            $summary['last_error'] = end(self::$errors);
        }

        return $summary;
    }

    /**
     * Log individual error
     *
     * @param array $error Error data
     */
    private static function log_error($error) {
        if (!function_exists('wc1c_log')) {
            return;
        }

        $message = sprintf('[%s] %s', $error['level'], $error['message']);
        
        $log_context = array(
            'level' => $error['level'],
            'context' => $error['context'],
            'memory_usage' => size_format($error['memory_usage'])
        );

        if (isset($error['exception'])) {
            $log_context['exception'] = $error['exception'];
        }

        wc1c_log($message, $error['level'], $log_context);
    }

    /**
     * Log error summary
     */
    private static function log_error_summary() {
        if (!function_exists('wc1c_log')) {
            return;
        }

        $summary = self::get_error_summary();
        
        $message = sprintf(
            'Error Summary: %d total errors (%s)',
            $summary['total'],
            implode(', ', array_map(function($level, $count) {
                return "$count $level";
            }, array_keys($summary['by_level']), $summary['by_level']))
        );

        wc1c_log($message, 'INFO', array('error_summary' => $summary));
    }

    /**
     * Export errors for debugging
     *
     * @param array $options Export options
     * @return array Exported error data
     */
    public static function export_errors($options = array()) {
        $defaults = array(
            'include_backtrace' => false,
            'include_context' => true,
            'level_filter' => null,
            'limit' => null
        );

        $options = wp_parse_args($options, $defaults);
        
        $errors = self::get_errors($options['level_filter']);
        
        if ($options['limit']) {
            $errors = array_slice($errors, -$options['limit']);
        }

        // Clean up errors for export
        $exported_errors = array();
        foreach ($errors as $error) {
            $exported_error = array(
                'message' => $error['message'],
                'level' => $error['level'],
                'timestamp' => $error['timestamp'],
                'formatted_time' => date('Y-m-d H:i:s', $error['timestamp'])
            );

            if ($options['include_context']) {
                $exported_error['context'] = $error['context'];
            }

            if ($options['include_backtrace']) {
                $exported_error['backtrace'] = $error['backtrace'];
            }

            if (isset($error['exception'])) {
                $exported_error['exception'] = $error['exception'];
            }

            $exported_errors[] = $exported_error;
        }

        return array(
            'errors' => $exported_errors,
            'summary' => self::get_error_summary(),
            'export_options' => $options,
            'exported_at' => time()
        );
    }

    /**
     * Create error report
     *
     * @return string Error report as formatted text
     */
    public static function create_error_report() {
        $summary = self::get_error_summary();
        $report = array();

        $report[] = "=== WooCommerce 1C Error Report ===";
        $report[] = "Generated: " . date('Y-m-d H:i:s');
        $report[] = "";

        $report[] = "Summary:";
        $report[] = "- Total Errors: " . $summary['total'];
        
        foreach ($summary['by_level'] as $level => $count) {
            $report[] = "- {$level}: {$count}";
        }
        $report[] = "";

        if ($summary['first_error']) {
            $report[] = "First Error:";
            $report[] = "- Time: " . date('Y-m-d H:i:s', $summary['first_error']['timestamp']);
            $report[] = "- Level: " . $summary['first_error']['level'];
            $report[] = "- Message: " . $summary['first_error']['message'];
            $report[] = "";
        }

        if ($summary['last_error']) {
            $report[] = "Last Error:";
            $report[] = "- Time: " . date('Y-m-d H:i:s', $summary['last_error']['timestamp']);
            $report[] = "- Level: " . $summary['last_error']['level'];
            $report[] = "- Message: " . $summary['last_error']['message'];
            $report[] = "";
        }

        $report[] = "Recent Errors:";
        foreach ($summary['recent_errors'] as $error) {
            $report[] = sprintf(
                "- [%s] %s: %s",
                date('H:i:s', $error['timestamp']),
                $error['level'],
                $error['message']
            );
        }

        return implode("\n", $report);
    }

    /**
     * Send error notification
     *
     * @param array $options Notification options
     * @return bool True if notification sent successfully
     */
    public static function send_error_notification($options = array()) {
        $defaults = array(
            'min_level' => self::LEVEL_ERROR,
            'email' => get_option('admin_email'),
            'subject' => 'WooCommerce 1C Integration Error Report'
        );

        $options = wp_parse_args($options, $defaults);

        // Check if we have errors at the minimum level
        $critical_errors = self::get_errors(self::LEVEL_CRITICAL);
        $errors = self::get_errors(self::LEVEL_ERROR);

        if (empty($critical_errors) && empty($errors)) {
            return false;
        }

        $report = self::create_error_report();
        
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        return wp_mail($options['email'], $options['subject'], $report, $headers);
    }

    /**
     * Register error recovery callback
     *
     * @param string $error_type Error type to handle
     * @param callable $callback Recovery callback
     */
    public static function register_recovery_callback($error_type, $callback) {
        if (!is_callable($callback)) {
            return false;
        }

        add_action("wc1c_error_recovery_{$error_type}", $callback, 10, 2);
        return true;
    }

    /**
     * Attempt error recovery
     *
     * @param string $error_type Error type
     * @param array $error_data Error data
     * @return bool True if recovery was attempted
     */
    public static function attempt_recovery($error_type, $error_data) {
        $recovery_attempted = false;

        // Try registered recovery callbacks
        if (has_action("wc1c_error_recovery_{$error_type}")) {
            do_action("wc1c_error_recovery_{$error_type}", $error_data, self::$context);
            $recovery_attempted = true;
        }

        // Log recovery attempt
        if ($recovery_attempted) {
            self::add_info("Recovery attempted for error type: {$error_type}", array(
                'error_type' => $error_type,
                'error_data' => $error_data
            ));
        }

        return $recovery_attempted;
    }

    /**
     * Get error statistics
     *
     * @param int $hours Number of hours to analyze (default: 24)
     * @return array Error statistics
     */
    public static function get_error_statistics($hours = 24) {
        $cutoff_time = time() - ($hours * 3600);
        $recent_errors = array_filter(self::$errors, function($error) use ($cutoff_time) {
            return $error['timestamp'] >= $cutoff_time;
        });

        $stats = array(
            'period_hours' => $hours,
            'total_errors' => count($recent_errors),
            'error_rate' => count($recent_errors) / $hours, // errors per hour
            'by_level' => array(),
            'by_hour' => array()
        );

        // Count by level
        foreach ($recent_errors as $error) {
            $level = $error['level'];
            if (!isset($stats['by_level'][$level])) {
                $stats['by_level'][$level] = 0;
            }
            $stats['by_level'][$level]++;
        }

        // Count by hour
        foreach ($recent_errors as $error) {
            $hour = date('Y-m-d H', $error['timestamp']);
            if (!isset($stats['by_hour'][$hour])) {
                $stats['by_hour'][$hour] = 0;
            }
            $stats['by_hour'][$hour]++;
        }

        return $stats;
    }
}

// Initialize error handler
WC1C_Error_Handler::init();