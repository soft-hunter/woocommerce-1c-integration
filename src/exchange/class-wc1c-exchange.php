<?php
/**
 * Main exchange functionality
 *
 * @package    WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/exchange
 * @author     Igor Melnyk <igormelnykit@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * Main exchange functionality for 1C integration
 */
class WC1C_Exchange {

    /**
     * The ID of this plugin.
     *
     * @var string $plugin_name The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @var string $version The current version of this plugin.
     */
    private $version;

    /**
     * Exchange data directory
     *
     * @var string
     */
    private $data_dir;

    /**
     * Current exchange session
     *
     * @var array
     */
    private $session;

    /**
     * Initialize the class and set its properties.
     *
     * @param string $plugin_name The name of the plugin.
     * @param string $version     The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        
        $upload_dir = wp_upload_dir();
        $this->data_dir = $upload_dir['basedir'] . '/woocommerce-1c-integration';
        
        $this->init_session();
    }

    /**
     * Add rewrite rules for exchange endpoints
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^wc1c/exchange/?$',
            'index.php?wc1c_action=exchange',
            'top'
        );
        
        add_rewrite_rule(
            '^wc1c/api/([^/]+)/?$',
            'index.php?wc1c_action=api&wc1c_endpoint=$matches[1]',
            'top'
        );

        // Add query vars
        add_filter('query_vars', array($this, 'add_query_vars'));
    }

    /**
     * Add query variables
     *
     * @param array $vars Query variables
     * @return array
     */
    public function add_query_vars($vars) {
        $vars[] = 'wc1c_action';
        $vars[] = 'wc1c_endpoint';
        return $vars;
    }

    /**
     * Handle exchange requests
     */
    public function handle_exchange_request() {
        $action = get_query_var('wc1c_action');
        
        if (empty($action)) {
            return;
        }

        // Set up exchange environment
        $this->setup_exchange_environment();

        try {
            switch ($action) {
                case 'exchange':
                    $this->handle_1c_exchange();
                    break;
                    
                case 'api':
                    $this->handle_api_request();
                    break;
                    
                default:
                    $this->send_error_response('Invalid action', 400);
            }
        } catch (Exception $e) {
            WC1C_Logger::log('Exchange error: ' . $e->getMessage(), 'error', array(
                'action' => $action,
                'trace' => $e->getTraceAsString()
            ));
            $this->send_error_response($e->getMessage(), 500);
        }
    }

    /**
     * Handle 1C exchange protocol
     */
    private function handle_1c_exchange() {
        $type = sanitize_text_field($_GET['type'] ?? '');
        $mode = sanitize_text_field($_GET['mode'] ?? '');
        $filename = sanitize_file_name($_GET['filename'] ?? '');

        // Validate parameters
        if (empty($type) || empty($mode)) {
            $this->send_error_response('Missing required parameters', 400);
        }

        // Validate type
        $allowed_types = array('catalog', 'sale');
        if (!in_array($type, $allowed_types)) {
            $this->send_error_response('Invalid type parameter', 400);
        }

        // Validate mode
        $allowed_modes = array('checkauth', 'init', 'file', 'import', 'query', 'success');
        if (!in_array($mode, $allowed_modes)) {
            $this->send_error_response('Invalid mode parameter', 400);
        }

        WC1C_Logger::log("Exchange request: type={$type}, mode={$mode}, filename={$filename}", 'info');

        // Handle different modes
        switch ($mode) {
            case 'checkauth':
                $this->handle_checkauth();
                break;
                
            case 'init':
                $this->handle_init($type);
                break;
                
            case 'file':
                $this->handle_file($type, $filename);
                break;
                
            case 'import':
                $this->handle_import($type, $filename);
                break;
                
            case 'query':
                $this->handle_query($type);
                break;
                
            case 'success':
                $this->handle_success($type);
                break;
        }
    }

    /**
     * Handle authentication check
     */
    private function handle_checkauth() {
        $auth = new WC1C_Auth();
        
        if ($auth->authenticate()) {
            $cookie = $auth->generate_session_cookie();
            $this->send_success_response("success\nwc1c-auth\n{$cookie}");
        } else {
            $this->send_error_response('Authentication failed', 401);
        }
    }

    /**
     * Handle initialization
     */
    private function handle_init($type) {
        // Check authentication
        $auth = new WC1C_Auth();
        if (!$auth->is_authenticated()) {
            $this->send_error_response('Not authenticated', 401);
        }

        // Ensure directories exist
        $this->ensure_directories($type);

        // Clean up old files if needed
        if (get_option('wc1c_cleanup_garbage', 'yes') === 'yes') {
            $this->cleanup_old_files($type);
        }

        // Check system capabilities
        $capabilities = $this->get_system_capabilities();

        $response = "zip=yes\nfile_limit={$capabilities['file_limit']}";
        $this->send_success_response($response);
    }

    /**
     * Handle file upload
     */
    private function handle_file($type, $filename) {
        // Check authentication
        $auth = new WC1C_Auth();
        if (!$auth->is_authenticated()) {
            $this->send_error_response('Not authenticated', 401);
        }

        if (empty($filename)) {
            $this->send_success_response('success');
            return;
        }

        // Validate filename
        if (!$this->is_valid_filename($filename)) {
            $this->send_error_response('Invalid filename', 400);
        }

        // Handle file upload
        $file_handler = new WC1C_File_Handler($this->data_dir);
        $result = $file_handler->handle_upload($type, $filename);

        if ($result) {
            WC1C_Logger::log("File uploaded: {$filename}", 'info');
            
            // If this is a sale type, process immediately
            if ($type === 'sale') {
                $this->process_sale_files();
            }
            
            $this->send_success_response('success');
        } else {
            $this->send_error_response('File upload failed', 500);
        }
    }

    /**
     * Handle import
     */
    private function handle_import($type, $filename) {
        // Check authentication
        $auth = new WC1C_Auth();
        if (!$auth->is_authenticated()) {
            $this->send_error_response('Not authenticated', 401);
        }

        if (empty($filename)) {
            $this->send_error_response('Filename required for import', 400);
        }

        // Start transaction
        $this->start_transaction();

        try {
            $importer = $this->get_importer($type, $filename);
            $result = $importer->import();

            $this->commit_transaction();
            
            WC1C_Logger::log("Import completed: {$filename}", 'info', $result);
            $this->send_success_response('success');
            
        } catch (Exception $e) {
            $this->rollback_transaction();
            throw $e;
        }
    }

    /**
     * Handle query (export orders to 1C)
     */
    private function handle_query($type) {
        // Check authentication
        $auth = new WC1C_Auth();
        if (!$auth->is_authenticated()) {
            $this->send_error_response('Not authenticated', 401);
        }

        $exporter = new WC1C_Orders();
        $xml = $exporter->export_orders();

        header('Content-Type: text/xml; charset=UTF-8');
        echo $xml;
        exit;
    }

    /**
     * Handle success confirmation
     */
    private function handle_success($type) {
        // Check authentication
        $auth = new WC1C_Auth();
        if (!$auth->is_authenticated()) {
            $this->send_error_response('Not authenticated', 401);
        }

        // Mark orders as successfully exported
        $orders = new WC1C_Orders();
        $orders->mark_orders_as_exported();

        // Clean up temporary files
        $this->cleanup_temp_files($type);

        // Update last sync time
        update_option('wc1c_last_sync', current_time('mysql'));

        WC1C_Logger::log("Exchange completed successfully for type: {$type}", 'info');
        $this->send_success_response('success');
    }

    /**
     * Handle API requests
     */
    private function handle_api_request() {
        $endpoint = get_query_var('wc1c_endpoint');
        
        // Check authentication for API
        $auth = new WC1C_Auth();
        if (!$auth->is_authenticated()) {
            $this->send_json_error('Not authenticated', 401);
        }

        switch ($endpoint) {
            case 'status':
                $this->api_get_status();
                break;
                
            case 'sync':
                $this->api_manual_sync();
                break;
                
            case 'logs':
                $this->api_get_logs();
                break;
                
            default:
                $this->send_json_error('Invalid endpoint', 404);
        }
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('wc1c/v1', '/status', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_status'),
            'permission_callback' => array($this, 'check_rest_permissions')
        ));

        register_rest_route('wc1c/v1', '/sync', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_manual_sync'),
            'permission_callback' => array($this, 'check_rest_permissions')
        ));
    }

    /**
     * Check REST API permissions
     */
    public function check_rest_permissions() {
        return current_user_can('manage_woocommerce');
    }

    /**
     * Manual sync functionality
     */
    public function manual_sync($type = 'full') {
        WC1C_Logger::log("Manual sync started: {$type}", 'info');

        $start_time = microtime(true);
        $start_memory = memory_get_usage(true);

        try {
            switch ($type) {
                case 'products':
                    $result = $this->sync_products();
                    break;
                    
                case 'orders':
                    $result = $this->sync_orders();
                    break;
                    
                case 'full':
                default:
                    $result = $this->sync_full();
                    break;
            }

            $execution_time = microtime(true) - $start_time;
            $memory_used = memory_get_usage(true) - $start_memory;

            WC1C_Logger::log("Manual sync completed", 'info', array(
                'type' => $type,
                'execution_time' => $execution_time,
                'memory_used' => size_format($memory_used),
                'result' => $result
            ));

            return $result;

        } catch (Exception $e) {
            WC1C_Logger::log("Manual sync failed: " . $e->getMessage(), 'error');
            throw $e;
        }
    }

    /**
     * Setup exchange environment
     */
    private function setup_exchange_environment() {
        // Disable time limit
        $this->disable_time_limit();

        // Set memory limit
        $this->set_memory_limit();

        // Set error handling
        set_error_handler(array($this, 'handle_exchange_error'));
        set_exception_handler(array($this, 'handle_exchange_exception'));

        // Set output buffering
        ob_start();
    }

    /**
     * Get system capabilities
     */
    private function get_system_capabilities() {
        $file_limits = array(
            $this->filesize_to_bytes('10M'),
            $this->filesize_to_bytes(ini_get('post_max_size')),
            $this->filesize_to_bytes(ini_get('memory_limit')),
        );

        // Check available memory
        if (function_exists('exec')) {
            @exec("grep ^MemFree: /proc/meminfo", $output, $status);
            if ($status === 0 && !empty($output)) {
                $output = preg_split("/\s+/", $output[0]);
                $file_limits[] = intval($output[1] * 1000 * 0.7);
            }
        }

        // Apply custom file limit
        $custom_limit = get_option('wc1c_file_limit', '100M');
        if ($custom_limit) {
            $file_limits[] = $this->filesize_to_bytes($custom_limit);
        }

        return array(
            'file_limit' => min($file_limits),
            'zip_support' => class_exists('ZipArchive') || $this->has_unzip_command(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        );
    }

    /**
     * Convert filesize string to bytes
     */
    private function filesize_to_bytes($filesize) {
        switch (substr($filesize, -1)) {
            case 'G':
            case 'g':
                return (int) $filesize * 1073741824;
            case 'M':
            case 'm':
                return (int) $filesize * 1048576;
            case 'K':
            case 'k':
                return (int) $filesize * 1024;
            default:
                return (int) $filesize;
        }
    }

    /**
     * Check if unzip command is available
     */
    private function has_unzip_command() {
        @exec("which unzip", $_, $status);
        return $status === 0;
    }

    /**
     * Validate filename
     */
    private function is_valid_filename($filename) {
        // Check for path traversal
        if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            return false;
        }

        // Check allowed extensions
        $allowed_extensions = array('xml', 'zip');
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        return in_array($extension, $allowed_extensions);
    }

    /**
     * Ensure directories exist
     */
    private function ensure_directories($type) {
        $directories = array(
            $this->data_dir,
            $this->data_dir . '/' . $type,
            $this->data_dir . '/temp',
            $this->data_dir . '/backup'
        );

        foreach ($directories as $dir) {
            if (!wp_mkdir_p($dir)) {
                throw new Exception("Failed to create directory: {$dir}");
            }
        }
    }

    /**
     * Get appropriate importer
     */
    private function get_importer($type, $filename) {
        $file_path = $this->data_dir . '/' . $type . '/' . $filename;
        
        if (!file_exists($file_path)) {
            throw new Exception("File not found: {$filename}");
        }

        // Determine importer type based on filename
        if (strpos($filename, 'import') === 0) {
            return new WC1C_Import($file_path);
        } elseif (strpos($filename, 'offers') === 0) {
            return new WC1C_Offers($file_path);
        } elseif (strpos($filename, 'orders') === 0) {
            return new WC1C_Orders($file_path);
        } else {
            throw new Exception("Unknown file type: {$filename}");
        }
    }

    /**
     * Initialize session
     */
    private function init_session() {
        $this->session = array(
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'operations' => array()
        );
    }

    /**
     * Start database transaction
     */
    private function start_transaction() {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        WC1C_Logger::log('Transaction started', 'debug');
    }

    /**
     * Commit database transaction
     */
    private function commit_transaction() {
        global $wpdb;
        $wpdb->query('COMMIT');
        WC1C_Logger::log('Transaction committed', 'debug');
    }

    /**
     * Rollback database transaction
     */
    private function rollback_transaction() {
        global $wpdb;
        $wpdb->query('ROLLBACK');
        WC1C_Logger::log('Transaction rolled back', 'debug');
    }

    /**
     * Disable time limit
     */
    private function disable_time_limit() {
        $disabled_functions = explode(',', ini_get('disable_functions'));
        if (!in_array('set_time_limit', $disabled_functions)) {
            $max_time = get_option('wc1c_max_execution_time', 300);
            @set_time_limit($max_time);
        }
    }

    /**
     * Set memory limit
     */
    private function set_memory_limit() {
        $memory_limit = get_option('wc1c_memory_limit', '512M');
        @ini_set('memory_limit', $memory_limit);
    }

    /**
     * Handle exchange errors
     */
    public function handle_exchange_error($errno, $errstr, $errfile = '', $errline = 0) {
        if (error_reporting() === 0) {
            return false;
        }

        $message = "PHP Error: {$errstr} in {$errfile} on line {$errline}";
        WC1C_Logger::log($message, 'error');
        
        return true;
    }

    /**
     * Handle exchange exceptions
     */
    public function handle_exchange_exception($exception) {
        $message = "Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine();
        WC1C_Logger::log($message, 'error');
        
        $this->send_error_response($exception->getMessage(), 500);
    }

    /**
     * Send success response
     */
    private function send_success_response($message) {
        header('Content-Type: text/plain; charset=UTF-8');
        echo $message;
        exit;
    }

    /**
     * Send error response
     */
    private function send_error_response($message, $code = 500) {
        http_response_code($code);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Error: {$message}";
        exit;
    }

    /**
     * Send JSON error response
     */
    private function send_json_error($message, $code = 500) {
        http_response_code($code);
        wp_send_json_error(array('message' => $message));
    }

    /**
     * Cleanup old files
     */
    private function cleanup_old_files($type) {
        $dir = $this->data_dir . '/' . $type;
        $files = glob($dir . '/*');
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < (time() - 86400)) { // 24 hours
                unlink($file);
            }
        }
    }

    /**
     * Cleanup temporary files
     */
    private function cleanup_temp_files($type) {
        $temp_dir = $this->data_dir . '/temp';
        $files = glob($temp_dir . '/*');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Process sale files
     */
    private function process_sale_files() {
        $sale_dir = $this->data_dir . '/sale';
        $xml_files = glob($sale_dir . '/*.xml');
        
        foreach ($xml_files as $file) {
            $filename = basename($file);
            try {
                $importer = $this->get_importer('sale', $filename);
                $importer->import();
                WC1C_Logger::log("Processed sale file: {$filename}", 'info');
            } catch (Exception $e) {
                WC1C_Logger::log("Failed to process sale file {$filename}: " . $e->getMessage(), 'error');
            }
        }
    }

    /**
     * API: Get status
     */
    private function api_get_status() {
        $status = array(
            'plugin_version' => WC1C_VERSION,
            'last_sync' => get_option('wc1c_last_sync'),
            'sync_status' => $this->get_sync_status(),
            'system_info' => $this->get_system_capabilities()
        );
        
        wp_send_json_success($status);
    }

    /**
     * API: Manual sync
     */
    private function api_manual_sync() {
        $type = sanitize_text_field($_POST['type'] ?? 'full');
        
        try {
            $result = $this->manual_sync($type);
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * API: Get logs
     */
    private function api_get_logs() {
        $date = sanitize_text_field($_GET['date'] ?? date('Y-m-d'));
        $level = sanitize_text_field($_GET['level'] ?? 'all');
        $limit = intval($_GET['limit'] ?? 100);

        $logs = $this->get_logs($date, $level, $limit);
        wp_send_json_success($logs);
    }

    /**
     * Get logs for API
     */
    private function get_logs($date, $level, $limit) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc1c_exchange_logs';
        
        $where_conditions = array("DATE(created_at) = %s");
        $where_values = array($date);
        
        if ($level !== 'all') {
            $where_conditions[] = "status = %s";
            $where_values[] = $level;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE {$where_clause} 
             ORDER BY created_at DESC 
             LIMIT %d",
            array_merge($where_values, array($limit))
        );
        
        return $wpdb->get_results($query);
    }

    /**
     * Get current sync status
     */
    private function get_sync_status() {
        $last_sync = get_option('wc1c_last_sync');
        $sync_in_progress = get_transient('wc1c_sync_in_progress');
        
        if ($sync_in_progress) {
            return 'in_progress';
        } elseif ($last_sync) {
            $last_sync_time = strtotime($last_sync);
            $hours_since_sync = (time() - $last_sync_time) / 3600;
            
            if ($hours_since_sync < 1) {
                return 'recent';
            } elseif ($hours_since_sync < 24) {
                return 'normal';
            } else {
                return 'outdated';
            }
        } else {
            return 'never';
        }
    }

    /**
     * Sync products only
     */
    private function sync_products() {
        set_transient('wc1c_sync_in_progress', true, 3600);
        
        try {
            $result = array(
                'products_processed' => 0,
                'products_updated' => 0,
                'products_created' => 0,
                'errors' => array()
            );

            // This would typically involve calling 1C API or processing files
            // For now, we'll simulate the process
            
            $products = $this->get_pending_product_updates();
            
            foreach ($products as $product_data) {
                try {
                    $product_id = $this->process_product_update($product_data);
                    
                    if ($product_id) {
                        $result['products_processed']++;
                        
                        if ($this->is_new_product($product_id)) {
                            $result['products_created']++;
                        } else {
                            $result['products_updated']++;
                        }
                    }
                } catch (Exception $e) {
                    $result['errors'][] = $e->getMessage();
                    WC1C_Logger::log("Product sync error: " . $e->getMessage(), 'error');
                }
            }
            
            delete_transient('wc1c_sync_in_progress');
            return $result;
            
        } catch (Exception $e) {
            delete_transient('wc1c_sync_in_progress');
            throw $e;
        }
    }

    /**
     * Sync orders only
     */
    private function sync_orders() {
        set_transient('wc1c_sync_in_progress', true, 3600);
        
        try {
            $result = array(
                'orders_processed' => 0,
                'orders_exported' => 0,
                'orders_updated' => 0,
                'errors' => array()
            );

            // Export new orders to 1C
            $orders_exporter = new WC1C_Orders();
            $export_result = $orders_exporter->export_pending_orders();
            
            $result['orders_exported'] = $export_result['exported_count'];
            $result['orders_processed'] += $export_result['exported_count'];
            
            // Import order status updates from 1C
            $import_result = $orders_exporter->import_order_updates();
            
            $result['orders_updated'] = $import_result['updated_count'];
            $result['orders_processed'] += $import_result['updated_count'];
            
            if (!empty($export_result['errors'])) {
                $result['errors'] = array_merge($result['errors'], $export_result['errors']);
            }
            
            if (!empty($import_result['errors'])) {
                $result['errors'] = array_merge($result['errors'], $import_result['errors']);
            }
            
            delete_transient('wc1c_sync_in_progress');
            return $result;
            
        } catch (Exception $e) {
            delete_transient('wc1c_sync_in_progress');
            throw $e;
        }
    }

    /**
     * Full synchronization
     */
    private function sync_full() {
        set_transient('wc1c_sync_in_progress', true, 7200); // 2 hours for full sync
        
        try {
            $result = array(
                'products' => array(),
                'orders' => array(),
                'categories' => array(),
                'attributes' => array(),
                'total_time' => 0,
                'errors' => array()
            );

            $start_time = microtime(true);

            // 1. Sync categories first
            WC1C_Logger::log('Starting category sync', 'info');
            $result['categories'] = $this->sync_categories();

            // 2. Sync attributes
            WC1C_Logger::log('Starting attribute sync', 'info');
            $result['attributes'] = $this->sync_attributes();

            // 3. Sync products
            WC1C_Logger::log('Starting product sync', 'info');
            $result['products'] = $this->sync_products();

            // 4. Sync orders
            WC1C_Logger::log('Starting order sync', 'info');
            $result['orders'] = $this->sync_orders();

            $result['total_time'] = microtime(true) - $start_time;
            
            // Update last full sync time
            update_option('wc1c_last_full_sync', current_time('mysql'));
            
            delete_transient('wc1c_sync_in_progress');
            return $result;
            
        } catch (Exception $e) {
            delete_transient('wc1c_sync_in_progress');
            throw $e;
        }
    }

    /**
     * Sync categories
     */
    private function sync_categories() {
        $result = array(
            'categories_processed' => 0,
            'categories_created' => 0,
            'categories_updated' => 0,
            'errors' => array()
        );

        // This would typically process category data from 1C
        // Implementation depends on your specific 1C integration needs
        
        return $result;
    }

    /**
     * Sync attributes
     */
    private function sync_attributes() {
        $result = array(
            'attributes_processed' => 0,
            'attributes_created' => 0,
            'attributes_updated' => 0,
            'errors' => array()
        );

        // This would typically process attribute data from 1C
        // Implementation depends on your specific 1C integration needs
        
        return $result;
    }

    /**
     * Get pending product updates
     */
    private function get_pending_product_updates() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc1c_sync_queue';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE item_type = 'product' 
             AND status = 'pending' 
             AND scheduled_at <= NOW()
             ORDER BY priority DESC, created_at ASC
             LIMIT %d",
            get_option('wc1c_batch_size', 100)
        ));
    }

    /**
     * Process product update
     */
    private function process_product_update($product_data) {
        // This would contain the actual product processing logic
        // For now, return a mock product ID
        return rand(1, 1000);
    }

    /**
     * Check if product is new
     */
    private function is_new_product($product_id) {
        // Check if this product was created in the current sync
        return get_post_meta($product_id, '_wc1c_created_in_sync', true) === 'yes';
    }

    /**
     * REST API: Get status
     */
    public function rest_get_status($request) {
        return rest_ensure_response($this->api_get_status());
    }

    /**
     * REST API: Manual sync
     */
    public function rest_manual_sync($request) {
        $type = $request->get_param('type') ?: 'full';
        
        try {
            $result = $this->manual_sync($type);
            return rest_ensure_response($result);
        } catch (Exception $e) {
            return new WP_Error('sync_failed', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * Add exchange log entry
     */
    public function add_exchange_log($exchange_type, $operation, $status, $message = '', $data = array(), $execution_time = 0) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc1c_exchange_logs';
        
        $wpdb->insert(
            $table_name,
            array(
                'exchange_type' => $exchange_type,
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
        
        return $wpdb->insert_id;
    }

    /**
     * Get exchange statistics
     */
    public function get_exchange_stats($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc1c_exchange_logs';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                exchange_type,
                status,
                COUNT(*) as count,
                AVG(execution_time) as avg_time,
                MAX(execution_time) as max_time,
                DATE(created_at) as date
             FROM {$table_name} 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY exchange_type, status, DATE(created_at)
             ORDER BY created_at DESC",
            $days
        ));
    }

    /**
     * Schedule sync operation
     */
    public function schedule_sync($item_type, $item_id, $action, $priority = 10, $data = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc1c_sync_queue';
        
        // Check if item is already in queue
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} 
             WHERE item_type = %s AND item_id = %d AND action = %s AND status = 'pending'",
            $item_type, $item_id, $action
        ));
        
        if ($existing) {
            // Update existing entry
            $wpdb->update(
                $table_name,
                array(
                    'priority' => $priority,
                    'data' => wp_json_encode($data),
                    'scheduled_at' => current_time('mysql')
                ),
                array('id' => $existing),
                array('%d', '%s', '%s'),
                array('%d')
            );
            return $existing;
        } else {
            // Insert new entry
            $wpdb->insert(
                $table_name,
                array(
                    'item_type' => $item_type,
                    'item_id' => $item_id,
                    'action' => $action,
                    'priority' => $priority,
                    'data' => wp_json_encode($data),
                    'scheduled_at' => current_time('mysql'),
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%d', '%s', '%d', '%s', '%s', '%s')
            );
            return $wpdb->insert_id;
        }
    }

    /**
     * Process sync queue
     */
    public function process_sync_queue($limit = 50) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc1c_sync_queue';
        
        // Get pending items
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE status = 'pending' 
             AND attempts < max_attempts
             AND scheduled_at <= NOW()
             ORDER BY priority DESC, created_at ASC
             LIMIT %d",
            $limit
        ));
        
        $processed = 0;
        $errors = 0;
        
        foreach ($items as $item) {
            try {
                // Mark as processing
                $wpdb->update(
                    $table_name,
                    array('status' => 'processing'),
                    array('id' => $item->id),
                    array('%s'),
                    array('%d')
                );
                
                // Process the item
                $this->process_sync_queue_item($item);
                
                // Mark as completed
                $wpdb->update(
                    $table_name,
                    array(
                        'status' => 'completed',
                        'processed_at' => current_time('mysql')
                    ),
                    array('id' => $item->id),
                    array('%s', '%s'),
                    array('%d')
                );
                
                $processed++;
                
            } catch (Exception $e) {
                // Mark as failed and increment attempts
                $wpdb->update(
                    $table_name,
                    array(
                        'status' => 'failed',
                        'attempts' => $item->attempts + 1
                    ),
                    array('id' => $item->id),
                    array('%s', '%d'),
                    array('%d')
                );
                
                WC1C_Logger::log("Sync queue item failed: " . $e->getMessage(), 'error', array(
                    'item_id' => $item->id,
                    'item_type' => $item->item_type,
                    'action' => $item->action
                ));
                
                $errors++;
            }
        }
        
        return array(
            'processed' => $processed,
            'errors' => $errors,
            'total' => count($items)
        );
    }

    /**
     * Process individual sync queue item
     */
    private function process_sync_queue_item($item) {
        $data = json_decode($item->data, true);
        
        switch ($item->item_type) {
            case 'product':
                $this->process_product_sync_item($item->item_id, $item->action, $data);
                break;
                
            case 'order':
                $this->process_order_sync_item($item->item_id, $item->action, $data);
                break;
                
            case 'category':
                $this->process_category_sync_item($item->item_id, $item->action, $data);
                break;
                
            default:
                throw new Exception("Unknown item type: {$item->item_type}");
        }
    }

    /**
     * Process product sync item
     */
    private function process_product_sync_item($product_id, $action, $data) {
        switch ($action) {
            case 'create':
            case 'update':
                // Process product update/creation
                break;
                
            case 'delete':
                // Process product deletion
                break;
                
            default:
                throw new Exception("Unknown product action: {$action}");
        }
    }

    /**
     * Process order sync item
     */
    private function process_order_sync_item($order_id, $action, $data) {
        switch ($action) {
            case 'export':
                // Export order to 1C
                break;
                
            case 'update_status':
                // Update order status from 1C
                break;
                
            default:
                throw new Exception("Unknown order action: {$action}");
        }
    }

    /**
     * Process category sync item
     */
    private function process_category_sync_item($category_id, $action, $data) {
        switch ($action) {
            case 'create':
            case 'update':
                // Process category update/creation
                break;
                
            case 'delete':
                // Process category deletion
                break;
                
            default:
                throw new Exception("Unknown category action: {$action}");
        }
    }
}