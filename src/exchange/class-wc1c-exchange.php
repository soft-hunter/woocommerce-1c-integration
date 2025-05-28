<?php
/**
 * The exchange protocol handling functionality
 *
 * @package WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/exchange
 * @author Igor Melnyk <igor.melnyk.it@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * Exchange class
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
     * Initialize the class and set its properties.
     *
     * @param string $plugin_name The name of this plugin.
     * @param string $version The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register exchange endpoints
     */
    public function register_endpoints() {
        add_rewrite_rule('^1c-exchange/?$', 'index.php?1c-exchange=true', 'top');
        add_rewrite_tag('%1c-exchange%', 'true');
    }

    /**
     * Handle exchange request
     *
     * @param WP $wp WordPress request object
     */
    public function handle_request($wp) {
        // Check if this is our endpoint
        if (!isset($wp->query_vars['1c-exchange'])) {
            return;
        }
        
        // Log request
        WC1C_Logger::info('Received 1C exchange request', array(
            'method' => $_SERVER['REQUEST_METHOD'],
            'query' => $_SERVER['QUERY_STRING'],
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown'
        ));
        
        // Disable caching
        $this->disable_caching();
        
        // Check authentication
        if (!$this->authenticate()) {
            $this->send_response('failure', '401 Unauthorized');
            exit;
        }
        
        // Process request
        $this->process_request();
        
        // Stop WordPress execution after our response
        exit;
    }

    /**
     * Disable caching for exchange requests
     */
    private function disable_caching() {
        // Disable caching plugins
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        
        if (!defined('DONOTCACHEDB')) {
            define('DONOTCACHEDB', true);
        }
        
        if (!defined('DONOTMINIFY')) {
            define('DONOTMINIFY', true);
        }
        
        if (!defined('DONOTCDN')) {
            define('DONOTCDN', true);
        }
        
        // Set no-cache headers
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Increase PHP limits for import operations
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');
    }

    /**
     * Authenticate exchange request
     *
     * @return bool Authentication result
     */
    private function authenticate() {
        // Check if authentication is enabled
        if (get_option('wc1c_auth_enabled', 'yes') !== 'yes') {
            return true;
        }
        
        // Get credentials
        $username = get_option('wc1c_auth_username');
        $password = get_option('wc1c_auth_password');
        
        // Verify basic auth
        if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
            $auth_user = $_SERVER['PHP_AUTH_USER'];
            $auth_pass = $_SERVER['PHP_AUTH_PW'];
            
            if ($auth_user === $username && $auth_pass === $password) {
                WC1C_Logger::debug('Authentication successful', array(
                    'username' => $auth_user
                ));
                return true;
            }
        }
        
        // Send auth headers if authentication failed
        header('WWW-Authenticate: Basic realm="1C Exchange"');
        
        WC1C_Logger::warning('Authentication failed', array(
            'username' => isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : 'Unknown'
        ));
        
        return false;
    }

    /**
     * Process exchange request
     */
    private function process_request() {
        // Check required GET parameters
        if (!isset($_GET['type']) || !isset($_GET['mode'])) {
            WC1C_Logger::error('Missing required parameters', array(
                'query' => $_SERVER['QUERY_STRING']
            ));
            $this->send_response('failure', 'Missing required parameters');
            return;
        }
        
        $type = sanitize_text_field($_GET['type']);
        $mode = sanitize_text_field($_GET['mode']);
        
        // Process request based on type and mode
        switch ($type) {
            case 'catalog':
                $this->process_catalog_request($mode);
                break;
                
            case 'sale':
                $this->process_sale_request($mode);
                break;
                
            default:
                WC1C_Logger::error('Unknown request type', array(
                    'type' => $type,
                    'mode' => $mode
                ));
                $this->send_response('failure', 'Unknown request type');
                break;
        }
    }

    /**
     * Process catalog request
     *
     * @param string $mode Request mode
     */
    private function process_catalog_request($mode) {
        switch ($mode) {
            case 'checkauth':
                $this->process_checkauth();
                break;
                
            case 'init':
                $this->process_init();
                break;
                
            case 'file':
                $this->process_file();
                break;
                
            case 'import':
                $this->process_import();
                break;
                
            default:
                WC1C_Logger::error('Unknown catalog mode', array(
                    'mode' => $mode
                ));
                $this->send_response('failure', 'Unknown catalog mode');
                break;
        }
    }

    /**
     * Process sale request
     *
     * @param string $mode Request mode
     */
    private function process_sale_request($mode) {
        switch ($mode) {
            case 'checkauth':
                $this->process_checkauth();
                break;
                
            case 'init':
                $this->process_init();
                break;
                
            case 'query':
                $this->process_query();
                break;
                
            case 'success':
                $this->process_success();
                break;
                
            default:
                WC1C_Logger::error('Unknown sale mode', array(
                    'mode' => $mode
                ));
                $this->send_response('failure', 'Unknown sale mode');
                break;
        }
    }

    /**
     * Process checkauth mode
     */
    private function process_checkauth() {
        // Generate session cookie name
        $cookie_name = 'wc1c_exchange_' . md5(uniqid('', true));
        
        // Set cookie
        setcookie($cookie_name, '1', time() + 3600, '/');
        
        // Send response
        echo "success\n";
        echo $cookie_name . "\n";
        echo md5(time()) . "\n";
        
        WC1C_Logger::info('Checkauth successful');
    }

    /**
     * Process init mode
     */
    private function process_init() {
        // Clean up temporary files
        $this->cleanup_temp_files();
        
        // Create temp directory if not exists
        $temp_dir = WC1C_DATA_DIR . 'temp/';
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        // Get max file size
        $max_file_size = get_option('wc1c_max_file_size', 10) * 1024 * 1024; // Convert MB to bytes
        
        // Send response
        echo "zip=no\n";
        echo "file_limit=" . $max_file_size . "\n";
        
        WC1C_Logger::info('Init successful', array(
            'max_file_size' => $max_file_size
        ));
    }

    /**
     * Process file mode
     */
    private function process_file() {
        // Get filename from GET parameters
        if (!isset($_GET['filename'])) {
            WC1C_Logger::error('Missing filename parameter');
            $this->send_response('failure', 'Missing filename parameter');
            return;
        }
        
        // Sanitize filename
        $filename = sanitize_file_name($_GET['filename']);
        
        // Create temp directory if not exists
        $temp_dir = WC1C_DATA_DIR . 'temp/';
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        // Build file path
        $file_path = $temp_dir . $filename;
        
        // Check if this is an image file
        if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $filename)) {
            // Get images directory
            $images_dir = get_option('wc1c_images_dir', WC1C_DATA_DIR . 'images/');
            
            // Create directory if not exists
            if (!file_exists($images_dir)) {
                wp_mkdir_p($images_dir);
            }
            
            // Build image path
            $file_path = $images_dir . $filename;
        }
        
        // Get file data from PHP input stream
        $file_data = file_get_contents('php://input');
        
        if ($file_data === false) {
            WC1C_Logger::error('Failed to read file data from input stream');
            $this->send_response('failure', 'Failed to read file data');
            return;
        }
        
        // Get max file size
        $max_file_size = get_option('wc1c_max_file_size', 10) * 1024 * 1024; // Convert MB to bytes
        
        // Check file size
        if (strlen($file_data) > $max_file_size) {
            WC1C_Logger::error('File exceeds maximum size', array(
                'filename' => $filename,
                'size' => strlen($file_data),
                'max_size' => $max_file_size
            ));
            $this->send_response('failure', 'File exceeds maximum size');
            return;
        }
        
        // Write file
        $result = file_put_contents($file_path, $file_data);
        
        if ($result === false) {
            WC1C_Logger::error('Failed to write file', array(
                'filename' => $filename,
                'path' => $file_path
            ));
            $this->send_response('failure', 'Failed to write file');
            return;
        }
        
        WC1C_Logger::info('File uploaded successfully', array(
            'filename' => $filename,
            'size' => strlen($file_data)
        ));
        
        $this->send_response('success');
    }

    /**
     * Process import mode
     */
    private function process_import() {
        // Get filename from GET parameters
        if (!isset($_GET['filename'])) {
            WC1C_Logger::error('Missing filename parameter');
            $this->send_response('failure', 'Missing filename parameter');
            return;
        }
        
        // Sanitize filename
        $filename = sanitize_file_name($_GET['filename']);
        
        // Build file path
        $file_path = WC1C_DATA_DIR . 'temp/' . $filename;
        
        // Check if file exists
        if (!file_exists($file_path)) {
            WC1C_Logger::error('File not found', array(
                'filename' => $filename,
                'path' => $file_path
            ));
            $this->send_response('failure', 'File not found');
            return;
        }
        
        // Determine import type based on filename
        $import_type = $this->determine_import_type($filename);
        
        if ($import_type === false) {
            WC1C_Logger::error('Unknown import file type', array(
                'filename' => $filename
            ));
            $this->send_response('failure', 'Unknown import file type');
            return;
        }
        
        // Process import
        try {
            $result = $this->process_import_file($file_path, $import_type);
            
            if ($result) {
                $this->send_response('success');
            } else {
                $this->send_response('failure', 'Import processing failed');
            }
        } catch (Exception $e) {
            WC1C_Logger::error('Import exception', array(
                'filename' => $filename,
                'exception' => $e->getMessage()
            ));
            $this->send_response('failure', 'Import exception: ' . $e->getMessage());
        }
    }

    /**
     * Determine import type based on filename
     *
     * @param string $filename Filename
     * @return string|false Import type or false if unknown
     */
    private function determine_import_type($filename) {
        if (strpos($filename, 'import') !== false) {
            return 'catalog';
        } elseif (strpos($filename, 'offers') !== false) {
            return 'offers';
        } elseif (strpos($filename, 'prices') !== false) {
            return 'prices';
        } elseif (strpos($filename, 'orders') !== false) {
            return 'orders';
        }
        
        return false;
    }

    /**
     * Process import file
     *
     * @param string $file_path File path
     * @param string $import_type Import type
     * @return bool Success status
     */
    private function process_import_file($file_path, $import_type) {
        // Get processor class name
        $processor_class = 'WC1C_Processor_' . ucfirst($import_type);

        // Check if processor class exists
        if (!class_exists($processor_class)) {
            // Try to load processor class
            $processor_file = WC1C_PLUGIN_DIR . 'exchange/processors/class-wc1c-processor-' . $import_type . '.php';
            
            if (file_exists($processor_file)) {
                require_once $processor_file;
            }
            
            if (!class_exists($processor_class)) {
                WC1C_Logger::error('Processor class not found', array(
                    'class' => $processor_class,
                    'file' => $processor_file
                ));
                return false;
            }
        }

        // Create processor instance
        $processor = new $processor_class();

        // Process import
        return $processor->process($file_path);
    }

    /**
     * Process query mode
     */
    private function process_query() {
        // Get export orders XML
        $xml = $this->generate_orders_xml();
        
        // Send XML
        header('Content-Type: text/xml; charset=utf-8');
        echo $xml;
        
        WC1C_Logger::info('Orders query processed');
    }

    /**
     * Process success mode
     */
    private function process_success() {
        WC1C_Logger::info('Success mode processed');
        $this->send_response('success');
    }

    /**
     * Generate orders XML
     *
     * @return string XML content
     */
    private function generate_orders_xml() {
        // Create XML document
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        
        // Create root element
        $root = $xml->createElement('КоммерческаяИнформация');
        $root->setAttribute('ВерсияСхемы', '2.05');
        $root->setAttribute('ДатаФормирования', date('Y-m-d\TH:i:s'));
        $xml->appendChild($root);
        
        // Get orders to export
        $orders = $this->get_orders_to_export();
        
        // Process each order
        foreach ($orders as $order) {
            $this->add_order_to_xml($xml, $root, $order);
        }
        
        // Return XML as string
        return $xml->saveXML();
    }

    /**
     * Get orders to export
     *
     * @return array Orders to export
     */
    private function get_orders_to_export() {
        // Get order statuses to export
        $statuses = get_option('wc1c_export_order_statuses', array('processing', 'completed'));
        
        if (empty($statuses)) {
            return array();
        }
        
        // Format statuses for query
        $formatted_statuses = array();
        foreach ($statuses as $status) {
            $formatted_statuses[] = 'wc-' . $status;
        }
        
        // Get date from
        $date_from = get_option('wc1c_export_order_date_from', '');
        
        // Query args
        $args = array(
            'post_type' => 'shop_order',
            'post_status' => $formatted_statuses,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_wc1c_exported',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => '_wc1c_exported',
                    'value' => '0',
                    'compare' => '='
                )
            )
        );
        
        // Add date filter if specified
        if (!empty($date_from)) {
            $args['date_query'] = array(
                array(
                    'after' => $date_from,
                    'inclusive' => true
                )
            );
        }
        
        // Get order IDs
        $order_ids = get_posts($args);
        
        // Load order objects
        $orders = array();
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $orders[] = $order;
                
                // Mark as exported
                update_post_meta($order_id, '_wc1c_exported', '1');
                update_post_meta($order_id, '_wc1c_exported_date', current_time('mysql'));
            }
        }
        
        return $orders;
    }

    /**
     * Add order to XML
     *
     * @param DOMDocument $xml XML document
     * @param DOMElement $root Root element
     * @param WC_Order $order Order object
     */
    private function add_order_to_xml($xml, $root, $order) {
        // Create document element
        $document = $xml->createElement('Документ');
        $root->appendChild($document);
        
        // Add order ID
        $id = $xml->createElement('Ид', $order->get_id());
        $document->appendChild($id);
        
        // Add order number
        $number = $xml->createElement('Номер', $order->get_order_number());
        $document->appendChild($number);
        
        // Add order date
        $date = $xml->createElement('Дата', $order->get_date_created()->format('Y-m-d'));
        $document->appendChild($date);
        
        // Add order time
        $time = $xml->createElement('Время', $order->get_date_created()->format('H:i:s'));
        $document->appendChild($time);
        
        // Add order currency
        $currency = $xml->createElement('Валюта', $order->get_currency());
        $document->appendChild($currency);
        
        // Add order total
        $total = $xml->createElement('Сумма', $order->get_total());
        $document->appendChild($total);
        
        // Add customer info
        $customer = $xml->createElement('Контрагенты');
        $document->appendChild($customer);
        
        $customer_item = $xml->createElement('Контрагент');
        $customer->appendChild($customer_item);
        
        $customer_id = $xml->createElement('Ид', 'customer_' . $order->get_customer_id());
        $customer_item->appendChild($customer_id);
        
        $customer_name = $xml->createElement('Наименование', $order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        $customer_item->appendChild($customer_name);
        
        // Add customer contacts
        $customer_contacts = $xml->createElement('Контакты');
        $customer_item->appendChild($customer_contacts);
        
        // Add email
        if ($order->get_billing_email()) {
            $contact = $xml->createElement('Контакт');
            $customer_contacts->appendChild($contact);
            
            $contact_type = $xml->createElement('Тип', 'Почта');
            $contact->appendChild($contact_type);
            
            $contact_value = $xml->createElement('Значение', $order->get_billing_email());
            $contact->appendChild($contact_value);
        }
        
        // Add phone
        if ($order->get_billing_phone()) {
            $contact = $xml->createElement('Контакт');
            $customer_contacts->appendChild($contact);
            
            $contact_type = $xml->createElement('Тип', 'Телефон');
            $contact->appendChild($contact_type);
            
            $contact_value = $xml->createElement('Значение', $order->get_billing_phone());
            $contact->appendChild($contact_value);
        }
        
        // Add shipping address
        $shipping_address = $xml->createElement('АдресДоставки');
        $customer_item->appendChild($shipping_address);
        
        // Add address presentation
        $address_str = '';
        if ($order->get_shipping_postcode()) {
            $address_str .= $order->get_shipping_postcode() . ', ';
        }
        if ($order->get_shipping_country()) {
            $address_str .= $order->get_shipping_country() . ', ';
        }
        if ($order->get_shipping_state()) {
            $address_str .= $order->get_shipping_state() . ', ';
        }
        if ($order->get_shipping_city()) {
            $address_str .= $order->get_shipping_city() . ', ';
        }
        if ($order->get_shipping_address_1()) {
            $address_str .= $order->get_shipping_address_1();
        }
        if ($order->get_shipping_address_2()) {
            $address_str .= ', ' . $order->get_shipping_address_2();
        }
        
        $presentation = $xml->createElement('Представление', $address_str);
        $shipping_address->appendChild($presentation);
        
        // Add order items
        $products = $xml->createElement('Товары');
        $document->appendChild($products);
        
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            
            // Skip if product doesn't exist
            if (!$product) {
                continue;
            }
            
            $product_item = $xml->createElement('Товар');
            $products->appendChild($product_item);
            
            // Get product 1C ID
            $product_1c_id = get_post_meta($product->get_id(), '_wc1c_id', true);
            
            // Use WooCommerce ID if 1C ID not available
            if (empty($product_1c_id)) {
                $product_1c_id = 'product_' . $product->get_id();
            }
            
            // Add product ID
            $product_id = $xml->createElement('Ид', $product_1c_id);
            $product_item->appendChild($product_id);
            
            // Add product name
            $product_name = $xml->createElement('Наименование', $item->get_name());
            $product_item->appendChild($product_name);
            
            // Add quantity
            $quantity = $xml->createElement('Количество', $item->get_quantity());
            $product_item->appendChild($quantity);
            
            // Add price
            $price = $xml->createElement('ЦенаЗаЕдиницу', $order->get_item_subtotal($item));
            $product_item->appendChild($price);
            
            // Add total
            $item_total = $xml->createElement('Сумма', $item->get_total());
            $product_item->appendChild($item_total);
        }
    }

    /**
     * Clean up temporary files
     */
    private function cleanup_temp_files() {
        $temp_dir = WC1C_DATA_DIR . 'temp/';
        
        // Skip if directory doesn't exist
        if (!file_exists($temp_dir)) {
            return;
        }
        
        // Get files in temp directory
        $files = glob($temp_dir . '*');
        
        // Delete each file
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        
        WC1C_Logger::debug('Temporary files cleaned up');
    }

    /**
     * Send response
     *
     * @param string $status Response status
     * @param string $message Optional message
     */
    private function send_response($status, $message = '') {
        if ($status === 'success') {
            echo "success\n";
            if (!empty($message)) {
                echo $message . "\n";
            }
        } else {
            echo "failure\n";
            if (!empty($message)) {
                echo $message . "\n";
            }
        }
    }

    /**
     * Called when a new order is created
     *
     * @param int $order_id Order ID
     */
    public function on_new_order($order_id) {
        // Mark order as not exported
        update_post_meta($order_id, '_wc1c_exported', '0');
    }

    /**
     * Called when order status changes
     *
     * @param int $order_id Order ID
     * @param string $old_status Old status
     * @param string $new_status New status
     */
    public function on_order_status_changed($order_id, $old_status, $new_status) {
        // Get order statuses to export
        $statuses = get_option('wc1c_export_order_statuses', array('processing', 'completed'));
        
        // Check if new status is in export statuses
        if (in_array($new_status, $statuses)) {
            // Mark order as not exported to trigger re-export
            update_post_meta($order_id, '_wc1c_exported', '0');
        }
    }
}