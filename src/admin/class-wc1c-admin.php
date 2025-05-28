<?php
/**
 * The admin-specific functionality of the plugin
 *
 * @package    WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/admin
 * @author     Igor Melnyk <igormelnykit@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * The admin-specific functionality of the plugin.
 */
class WC1C_Admin {

    /**
     * The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area.
     */
    public function enqueue_styles($hook_suffix) {
        if (!$this->is_plugin_page($hook_suffix)) {
            return;
        }

        wp_enqueue_style(
            $this->plugin_name,
            WC1C_PLUGIN_URL . 'admin/css/wc1c-admin.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the admin area.
     */
    public function enqueue_scripts($hook_suffix) {
        if (!$this->is_plugin_page($hook_suffix)) {
            return;
        }

        wp_enqueue_script(
            $this->plugin_name,
            WC1C_PLUGIN_URL . 'admin/js/wc1c-admin.js',
            array('jquery'),
            $this->version,
            false
        );

        wp_localize_script(
            $this->plugin_name,
            'wc1c_admin',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wc1c_admin_nonce'),
                'strings' => array(
                    'confirm_sync' => __('Are you sure you want to start synchronization?', 'woocommerce-1c-integration'),
                    'sync_in_progress' => __('Synchronization in progress...', 'woocommerce-1c-integration'),
                    'sync_completed' => __('Synchronization completed successfully!', 'woocommerce-1c-integration'),
                    'sync_failed' => __('Synchronization failed. Please check the logs.', 'woocommerce-1c-integration'),
                )
            )
        );
    }

    /**
     * Add admin menu
     */
    public function add_plugin_admin_menu() {
        add_menu_page(
            __('1C Integration', 'woocommerce-1c-integration'),
            __('1C Integration', 'woocommerce-1c-integration'),
            'manage_woocommerce',
            'wc1c-integration',
            array($this, 'display_dashboard_page'),
            'dashicons-update',
            56
        );

        add_submenu_page(
            'wc1c-integration',
            __('Dashboard', 'woocommerce-1c-integration'),
            __('Dashboard', 'woocommerce-1c-integration'),
            'manage_woocommerce',
            'wc1c-integration',
            array($this, 'display_dashboard_page')
        );

        add_submenu_page(
            'wc1c-integration',
            __('Settings', 'woocommerce-1c-integration'),
            __('Settings', 'woocommerce-1c-integration'),
            'manage_woocommerce',
            'wc1c-settings',
            array($this, 'display_settings_page')
        );

        add_submenu_page(
            'wc1c-integration',
            __('Logs', 'woocommerce-1c-integration'),
            __('Logs', 'woocommerce-1c-integration'),
            'manage_woocommerce',
            'wc1c-logs',
            array($this, 'display_logs_page')
        );

        add_submenu_page(
            'wc1c-integration',
            __('Tools', 'woocommerce-1c-integration'),
            __('Tools', 'woocommerce-1c-integration'),
            'manage_woocommerce',
            'wc1c-tools',
            array($this, 'display_tools_page')
        );
    }

    /**
     * Initialize admin functionality
     */
    public function admin_init() {
        $this->register_settings();
        $this->register_ajax_handlers();
        $this->register_column_handlers();
        $this->handle_form_submissions();
        
        add_action('admin_notices', array($this, 'display_admin_notices'));
    }

    /**
     * Register plugin settings
     */
    private function register_settings() {
        $settings = array(
            'wc1c_enable_logging', 'wc1c_log_level', 'wc1c_max_execution_time',
            'wc1c_memory_limit', 'wc1c_file_limit', 'wc1c_cleanup_garbage',
            'wc1c_manage_stock', 'wc1c_outofstock_status', 'wc1c_xml_charset',
            'wc1c_disable_variations', 'wc1c_prevent_clean', 'wc1c_match_by_sku',
            'wc1c_match_categories_by_title', 'wc1c_match_properties_by_title',
            'wc1c_sync_interval', 'wc1c_auto_sync', 'wc1c_api_timeout',
            'wc1c_retry_attempts', 'wc1c_batch_size'
        );

        foreach ($settings as $setting) {
            register_setting('wc1c_settings', $setting);
        }
    }

    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        $ajax_actions = array(
            'wc1c_manual_sync', 'wc1c_clear_logs', 'wc1c_test_connection',
            'wc1c_clear_cache', 'wc1c_validate_data', 'wc1c_export_settings'
        );

        foreach ($ajax_actions as $action) {
            add_action('wp_ajax_' . $action, array($this, 'handle_' . str_replace('wc1c_', '', $action)));
        }
    }

    /**
     * Register column handlers
     */
    private function register_column_handlers() {
        add_filter('manage_edit-product_columns', array($this, 'add_product_columns'));
        add_action('manage_product_posts_custom_column', array($this, 'display_product_columns'), 10, 2);
        add_filter('manage_edit-shop_order_columns', array($this, 'add_order_columns'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'display_order_columns'), 10, 2);
    }

    /**
     * Handle form submissions
     */
    private function handle_form_submissions() {
        if (!isset($_POST['action']) || !current_user_can('manage_woocommerce')) {
            return;
        }

        $action = sanitize_text_field($_POST['action']);
        
        switch ($action) {
            case 'reset_data':
                if (wp_verify_nonce($_POST['wc1c_reset_data_nonce'], 'wc1c_reset_data')) {
                    $this->handle_reset_data();
                }
                break;
            case 'cleanup_data':
                if (wp_verify_nonce($_POST['wc1c_cleanup_data_nonce'], 'wc1c_cleanup_data')) {
                    $this->handle_cleanup_data();
                }
                break;
            case 'import_settings':
                if (wp_verify_nonce($_POST['wc1c_import_settings_nonce'], 'wc1c_import_settings')) {
                    $this->handle_import_settings();
                }
                break;
            case 'export_settings':
                if (wp_verify_nonce($_POST['wc1c_export_settings_nonce'], 'wc1c_export_settings')) {
                    $this->handle_export_settings();
                }
                break;
        }
    }

    /**
     * Display page methods
     */
    public function display_dashboard_page() {
        include_once WC1C_PLUGIN_DIR . 'admin/partials/wc1c-admin-dashboard.php';
    }

    public function display_settings_page() {
        include_once WC1C_PLUGIN_DIR . 'admin/partials/wc1c-admin-settings.php';
    }

    public function display_logs_page() {
        include_once WC1C_PLUGIN_DIR . 'admin/partials/wc1c-admin-logs.php';
    }

    public function display_tools_page() {
        include_once WC1C_PLUGIN_DIR . 'admin/partials/wc1c-admin-tools.php';
    }

    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        if (!class_exists('WooCommerce')) {
            echo '<div class="error"><p>';
            echo __('WooCommerce 1C Integration requires WooCommerce to be installed and activated.', 'woocommerce-1c-integration');
            echo '</p></div>';
            return;
        }

        $upload_dir = wp_upload_dir();
        $data_dir = $upload_dir['basedir'] . '/woocommerce-1c-integration';
        
        if (!is_writable($data_dir)) {
            echo '<div class="error"><p>';
            printf(
                __('WooCommerce 1C Integration data directory is not writable: %s', 'woocommerce-1c-integration'),
                esc_html($data_dir)
            );
            echo '</p></div>';
        }

        $recent_errors = $this->get_recent_sync_errors();
        if (!empty($recent_errors)) {
            echo '<div class="error"><p>';
            printf(
                __('There have been %d synchronization errors in the last 24 hours. <a href="%s">View logs</a>', 'woocommerce-1c-integration'),
                count($recent_errors),
                admin_url('admin.php?page=wc1c-logs')
            );
            echo '</p></div>';
        }
    }

    /**
     * AJAX Handlers
     */
    public function handle_manual_sync() {
        check_ajax_referer('wc1c_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'woocommerce-1c-integration'));
        }

        $sync_type = sanitize_text_field($_POST['sync_type'] ?? 'full');

        try {
            $exchange = new WC1C_Exchange('woocommerce-1c-integration', WC1C_VERSION);
            $result = $exchange->manual_sync($sync_type);

            wp_send_json_success(array(
                'message' => __('Synchronization completed successfully', 'woocommerce-1c-integration'),
                'result' => $result
            ));
        } catch (Exception $e) {
            WC1C_Logger::log('Manual sync failed: ' . $e->getMessage(), 'error');
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    public function handle_clear_logs() {
        check_ajax_referer('wc1c_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'woocommerce-1c-integration'));
        }

        try {
            WC1C_Logger::cleanup_old_logs();
            wp_send_json_success(array(
                'message' => __('Logs cleared successfully', 'woocommerce-1c-integration')
            ));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    public function handle_test_connection() {
        check_ajax_referer('wc1c_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'woocommerce-1c-integration'));
        }

        try {
            $auth = new WC1C_Auth();
            $result = $auth->test_connection();

            if ($result) {
                wp_send_json_success(array(
                    'message' => __('Connection test successful', 'woocommerce-1c-integration')
                ));
            } else {
                wp_send_json_error(array(
                    'message' => __('Connection test failed', 'woocommerce-1c-integration')
                ));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    public function handle_clear_cache() {
        check_ajax_referer('wc1c_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'woocommerce-1c-integration'));
        }

        try {
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }

            $upload_dir = wp_upload_dir();
            $cache_dirs = array(
                $upload_dir['basedir'] . '/woocommerce-1c-integration/cache',
                $upload_dir['basedir'] . '/woocommerce-1c-integration/temp'
            );

            foreach ($cache_dirs as $cache_dir) {
                if (is_dir($cache_dir)) {
                    $files = glob($cache_dir . '/*');
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            unlink($file);
                        }
                    }
                }
            }

            wp_send_json_success(array(
                'message' => __('Cache cleared successfully', 'woocommerce-1c-integration')
            ));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    public function handle_validate_data() {
        check_ajax_referer('wc1c_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'woocommerce-1c-integration'));
        }

        try {
            global $wpdb;
            $issues = array();
            
            // Check for products without 1C GUID
            $products_without_guid = $wpdb->get_var("
                SELECT COUNT(p.ID) 
                FROM {$wpdb->posts} p 
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wc1c_guid'
                WHERE p.post_type = 'product' 
                AND p.post_status = 'publish' 
                AND pm.meta_value IS NULL
            ");
            
            if ($products_without_guid > 0) {
                $issues[] = sprintf(
                    __('%d products without 1C GUID found', 'woocommerce-1c-integration'),
                    $products_without_guid
                );
            }

            // Check for orphaned metadata
            $orphaned_meta = $wpdb->get_var("
                SELECT COUNT(pm.meta_id) 
                FROM {$wpdb->postmeta} pm
                LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE p.ID IS NULL
                AND pm.meta_key LIKE '_wc1c_%'
            ");
            
            if ($orphaned_meta > 0) {
                $issues[] = sprintf(
                    __('%d orphaned metadata entries found', 'woocommerce-1c-integration'),
                    $orphaned_meta
                );
            }

            if (empty($issues)) {
                wp_send_json_success(array(
                    'message' => __('Data validation completed. No issues found.', 'woocommerce-1c-integration')
                ));
            } else {
                wp_send_json_success(array(
                    'message' => __('Data validation completed. Issues found:', 'woocommerce-1c-integration') . '<br>' . implode('<br>', $issues)
                ));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Form submission handlers
     */
    private function handle_reset_data() {
        global $wpdb;
        
        try {
            // Remove all plugin options
            $options = array(
                'wc1c_enable_logging', 'wc1c_log_level', 'wc1c_max_execution_time',
                'wc1c_memory_limit', 'wc1c_file_limit', 'wc1c_cleanup_garbage',
                'wc1c_manage_stock', 'wc1c_outofstock_status', 'wc1c_xml_charset',
                'wc1c_disable_variations', 'wc1c_prevent_clean', 'wc1c_match_by_sku',
                'wc1c_match_categories_by_title', 'wc1c_match_properties_by_title',
                'wc1c_sync_interval', 'wc1c_auto_sync', 'wc1c_api_timeout',
                'wc1c_retry_attempts', 'wc1c_batch_size'
            );

            foreach ($options as $option) {
                delete_option($option);
            }

            // Remove plugin metadata
            $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_wc1c_%'");
            $wpdb->query("DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE '_wc1c_%'");

            // Clear logs
            WC1C_Logger::cleanup_old_logs();

            // Clear files
            $upload_dir = wp_upload_dir();
            $plugin_dir = $upload_dir['basedir'] . '/woocommerce-1c-integration';
            
            if (is_dir($plugin_dir)) {
                $this->recursive_rmdir($plugin_dir);
            }

            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo __('All plugin data has been reset successfully.', 'woocommerce-1c-integration');
                echo '</p></div>';
            });

        } catch (Exception $e) {
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error is-dismissible"><p>';
                echo sprintf(__('Error resetting data: %s', 'woocommerce-1c-integration'), $e->getMessage());
                echo '</p></div>';
            });
        }
    }

    private function handle_cleanup_data() {
        global $wpdb;
        
        try {
            // Remove orphaned metadata
            $wpdb->query("
                DELETE pm FROM {$wpdb->postmeta} pm
                LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE p.ID IS NULL AND pm.meta_key LIKE '_wc1c_%'
            ");

            $wpdb->query("
                DELETE tm FROM {$wpdb->termmeta} tm
                LEFT JOIN {$wpdb->terms} t ON tm.term_id = t.term_id
                WHERE t.term_id IS NULL AND tm.meta_key LIKE '_wc1c_%'
            ");

            // Clean old files
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/woocommerce-1c-integration/temp';
            
            if (is_dir($temp_dir)) {
                $files = glob($temp_dir . '/*');
                foreach ($files as $file) {
                    if (is_file($file) && filemtime($file) < (time() - 86400)) {
                        unlink($file);
                    }
                }
            }

            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo __('Orphaned data cleaned up successfully.', 'woocommerce-1c-integration');
                echo '</p></div>';
            });

        } catch (Exception $e) {
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error is-dismissible"><p>';
                echo sprintf(__('Error cleaning up data: %s', 'woocommerce-1c-integration'), $e->getMessage());
                echo '</p></div>';
            });
        }
    }

    public function handle_export_settings() {
        $settings = array();
        $options = array(
            'wc1c_enable_logging', 'wc1c_log_level', 'wc1c_max_execution_time',
            'wc1c_memory_limit', 'wc1c_file_limit', 'wc1c_cleanup_garbage',
            'wc1c_manage_stock', 'wc1c_outofstock_status', 'wc1c_xml_charset',
            'wc1c_disable_variations', 'wc1c_prevent_clean', 'wc1c_match_by_sku',
            'wc1c_match_categories_by_title', 'wc1c_match_properties_by_title',
            'wc1c_sync_interval', 'wc1c_auto_sync', 'wc1c_api_timeout',
            'wc1c_retry_attempts', 'wc1c_batch_size'
        );

        foreach ($options as $option) {
            $settings[$option] = get_option($option);
        }

        $export_data = array(
            'version' => WC1C_VERSION,
            'timestamp' => current_time('mysql'),
            'settings' => $settings
        );

        $filename = 'wc1c-settings-' . date('Y-m-d-H-i-s') . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen(json_encode($export_data)));
        
        echo json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    }

    private function handle_import_settings() {
        if (!isset($_FILES['settings_file']) || $_FILES['settings_file']['error'] !== UPLOAD_ERR_OK) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>';
                echo __('Error uploading settings file.', 'woocommerce-1c-integration');
                echo '</p></div>';
            });
            return;
        }

        try {
            $file_content = file_get_contents($_FILES['settings_file']['tmp_name']);
            $import_data = json_decode($file_content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception(__('Invalid JSON file.', 'woocommerce-1c-integration'));
            }

            if (!isset($import_data['settings']) || !is_array($import_data['settings'])) {
                throw new Exception(__('Invalid settings file format.', 'woocommerce-1c-integration'));
            }

            foreach ($import_data['settings'] as $option => $value) {
                update_option($option, $value);
            }

            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo __('Settings imported successfully.', 'woocommerce-1c-integration');
                echo '</p></div>';
            });

        } catch (Exception $e) {
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error is-dismissible"><p>';
                echo sprintf(__('Error importing settings: %s', 'woocommerce-1c-integration'), $e->getMessage());
                echo '</p></div>';
            });
        }
    }

    /**
     * Product and Order Column Management
     */
    public function add_product_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            
            if ($key === 'name') {
                $new_columns['wc1c_guid'] = __('1C GUID', 'woocommerce-1c-integration');
                $new_columns['wc1c_last_sync'] = __('Last Sync', 'woocommerce-1c-integration');
            }
        }
        
        return $new_columns;
    }

    public function display_product_columns($column, $post_id) {
        switch ($column) {
            case 'wc1c_guid':
                $guid = get_post_meta($post_id, '_wc1c_guid', true);
                echo $guid ? '<small>' . esc_html($guid) . '</small>' : '—';
                break;
                
            case 'wc1c_last_sync':
                $last_sync = get_post_meta($post_id, '_wc1c_last_sync', true);
                if ($last_sync) {
                    echo '<small>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_sync))) . '</small>';
                } else {
                    echo '—';
                }
                break;
        }
    }

    public function add_order_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            
            if ($key === 'order_status') {
                $new_columns['wc1c_sync_status'] = __('1C Sync', 'woocommerce-1c-integration');
            }
        }
        
        return $new_columns;
    }

    public function display_order_columns($column, $post_id) {
        switch ($column) {
            case 'wc1c_sync_status':
                $sync_status = get_post_meta($post_id, '_wc1c_sync_status', true);
                $sync_date = get_post_meta($post_id, '_wc1c_sync_date', true);
                
                if ($sync_status === 'synced' && $sync_date) {
                    echo '<span style="color: green;">✓</span> ';
                    echo '<small>' . esc_html(date_i18n('M j', strtotime($sync_date))) . '</small>';
                } elseif ($sync_status === 'pending') {
                    echo '<span style="color: orange;">⏳</span> ' . __('Pending', 'woocommerce-1c-integration');
                } elseif ($sync_status === 'error') {
                    echo '<span style="color: red;">✗</span> ' . __('Error', 'woocommerce-1c-integration');
                } else {
                    echo '—';
                }
                break;
        }
    }

    /**
     * Utility methods
     */
    private function is_plugin_page($hook_suffix) {
        $plugin_pages = array(
            'toplevel_page_wc1c-integration',
            'wc1c-integration_page_wc1c-settings',
            'wc1c-integration_page_wc1c-logs',
            'wc1c-integration_page_wc1c-tools'
        );
        
        return in_array($hook_suffix, $plugin_pages);
    }

    private function get_recent_sync_errors() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc1c_exchange_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return array();
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE status IN ('error', 'critical') 
             AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY created_at DESC
             LIMIT 10"
        ));
    }

    private function recursive_rmdir($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->recursive_rmdir($path) : unlink($path);
        }
        
        rmdir($dir);
    }

    /**
     * Get sync statistics for dashboard
     */
    public function get_sync_statistics() {
        global $wpdb;
        
        $stats = array(
            'total_products' => 0,
            'synced_products' => 0,
            'total_orders' => 0,
            'synced_orders' => 0,
            'last_sync' => null,
            'sync_errors' => 0
        );

        // Get product stats
        $stats['total_products'] = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts} 
            WHERE post_type = 'product' AND post_status = 'publish'
        ");

        $stats['synced_products'] = $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product' 
            AND p.post_status = 'publish'
            AND pm.meta_key = '_wc1c_guid'
            AND pm.meta_value != ''
        ");

        // Get order stats
        $stats['total_orders'] = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts} 
            WHERE post_type = 'shop_order'
        ");

        $stats['synced_orders'] = $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
            AND pm.meta_key = '_wc1c_sync_status'
            AND pm.meta_value = 'synced'
        ");

        // Get last sync time
        $last_sync = $wpdb->get_var("
            SELECT MAX(meta_value) FROM {$wpdb->postmeta}
            WHERE meta_key = '_wc1c_last_sync'
        ");

        if ($last_sync) {
            $stats['last_sync'] = $last_sync;
        }

        // Get recent errors
        $table_name = $wpdb->prefix . 'wc1c_exchange_logs';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            $stats['sync_errors'] = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} 
                 WHERE status IN ('error', 'critical') 
                 AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)"
            ));
        }

        return $stats;
    }

    /**
     * Get recent logs for dashboard
     */
    public function get_recent_logs($limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc1c_exchange_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return array();
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             ORDER BY created_at DESC 
             LIMIT %d",
            $limit
        ));
    }

    /**
     * Get HPOS status for system information
     */
    public function get_hpos_status() {
        $status = array(
            'hpos_available' => false,
            'hpos_enabled' => false,
            'hpos_sync_enabled' => false
        );
        
        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
            $status['hpos_available'] = true;
            $status['hpos_enabled'] = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
            $status['hpos_sync_enabled'] = \Automattic\WooCommerce\Utilities\OrderUtil::is_custom_order_tables_in_sync();
        }
        
        return $status;
    }

    /**
     * Display system status including HPOS
     */
    public function display_system_status() {
        $hpos_status = $this->get_hpos_status();
        ?>
        <table class="wc1c-status-table">
            <tr>
                <td><?php _e('Plugin Version', 'woocommerce-1c-integration'); ?></td>
                <td><?php echo WC1C_VERSION; ?></td>
            </tr>
            <tr>
                <td><?php _e('WooCommerce Version', 'woocommerce-1c-integration'); ?></td>
                <td><?php echo defined('WC_VERSION') ? WC_VERSION : 'Not installed'; ?></td>
            </tr>
            <tr>
                <td><?php _e('WordPress Version', 'woocommerce-1c-integration'); ?></td>
                <td><?php echo get_bloginfo('version'); ?></td>
            </tr>
            <tr>
                <td><?php _e('PHP Version', 'woocommerce-1c-integration'); ?></td>
                <td><?php echo PHP_VERSION; ?></td>
            </tr>
            <tr>
                <td><?php _e('Memory Limit', 'woocommerce-1c-integration'); ?></td>
                <td><?php echo ini_get('memory_limit'); ?></td>
            </tr>
            <tr>
                <td><?php _e('Max Execution Time', 'woocommerce-1c-integration'); ?></td>
                <td><?php echo ini_get('max_execution_time'); ?> seconds</td>
            </tr>
            <tr>
                <td><?php _e('HPOS Available', 'woocommerce-1c-integration'); ?></td>
                <td>
                    <?php if ($hpos_status['hpos_available']): ?>
                        <span class="wc1c-status-yes">✅ <?php _e('Yes', 'woocommerce-1c-integration'); ?></span>
                    <?php else: ?>
                        <span class="wc1c-status-no">❌ <?php _e('No', 'woocommerce-1c-integration'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><?php _e('HPOS Enabled', 'woocommerce-1c-integration'); ?></td>
                <td>
                    <?php if ($hpos_status['hpos_enabled']): ?>
                        <span class="wc1c-status-yes">✅ <?php _e('Yes', 'woocommerce-1c-integration'); ?></span>
                    <?php else: ?>
                        <span class="wc1c-status-no">❌ <?php _e('No', 'woocommerce-1c-integration'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($hpos_status['hpos_available']): ?>
            <tr>
                <td><?php _e('HPOS Sync Status', 'woocommerce-1c-integration'); ?></td>
                <td>
                    <?php if ($hpos_status['hpos_sync_enabled']): ?>
                        <span class="wc1c-status-yes">✅ <?php _e('In Sync', 'woocommerce-1c-integration'); ?></span>
                    <?php else: ?>
                        <span class="wc1c-status-warning">⚠️ <?php _e('Out of Sync', 'woocommerce-1c-integration'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        <?php
    }
}