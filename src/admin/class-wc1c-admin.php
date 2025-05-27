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
 *
 * Defines the plugin name, version, and hooks for admin area.
 */
class WC1C_Admin {

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
     * @param string $version     The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @param string $hook_suffix The current admin page.
     */
    public function enqueue_styles($hook_suffix) {
        // Only load on our plugin pages
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
     *
     * @param string $hook_suffix The current admin page.
     */
    public function enqueue_scripts($hook_suffix) {
        // Only load on our plugin pages
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

        // Localize script
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
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('1C Integration', 'woocommerce-1c-integration'),
            __('1C Integration', 'woocommerce-1c-integration'),
            'manage_woocommerce',
            'wc1c-integration',
            array($this, 'display_dashboard_page'),
            'dashicons-update',
            56
        );

        // Dashboard submenu
        add_submenu_page(
            'wc1c-integration',
            __('Dashboard', 'woocommerce-1c-integration'),
            __('Dashboard', 'woocommerce-1c-integration'),
            'manage_woocommerce',
            'wc1c-integration',
            array($this, 'display_dashboard_page')
        );

        // Settings submenu
        add_submenu_page(
            'wc1c-integration',
            __('Settings', 'woocommerce-1c-integration'),
            __('Settings', 'woocommerce-1c-integration'),
            'manage_woocommerce',
            'wc1c-settings',
            array($this, 'display_settings_page')
        );

        // Logs submenu
        add_submenu_page(
            'wc1c-integration',
            __('Logs', 'woocommerce-1c-integration'),
            __('Logs', 'woocommerce-1c-integration'),
            'manage_woocommerce',
            'wc1c-logs',
            array($this, 'display_logs_page')
        );

        // Tools submenu
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
        // Register settings
        $this->register_settings();

        // Add admin notices
        add_action('admin_notices', array($this, 'display_admin_notices'));

        // Handle AJAX requests
        add_action('wp_ajax_wc1c_manual_sync', array($this, 'handle_manual_sync'));
        add_action('wp_ajax_wc1c_clear_logs', array($this, 'handle_clear_logs'));
        add_action('wp_ajax_wc1c_test_connection', array($this, 'handle_test_connection'));

        // Add product columns
        add_filter('manage_edit-product_columns', array($this, 'add_product_columns'));
        add_action('manage_product_posts_custom_column', array($this, 'display_product_columns'), 10, 2);

        // Add order columns
        add_filter('manage_edit-shop_order_columns', array($this, 'add_order_columns'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'display_order_columns'), 10, 2);
    }

    /**
     * Register plugin settings
     */
    private function register_settings() {
        register_setting('wc1c_settings', 'wc1c_enable_logging');
        register_setting('wc1c_settings', 'wc1c_log_level');
        register_setting('wc1c_settings', 'wc1c_max_execution_time');
        register_setting('wc1c_settings', 'wc1c_memory_limit');
        register_setting('wc1c_settings', 'wc1c_file_limit');
        register_setting('wc1c_settings', 'wc1c_cleanup_garbage');
        register_setting('wc1c_settings', 'wc1c_manage_stock');
        register_setting('wc1c_settings', 'wc1c_outofstock_status');
        register_setting('wc1c_settings', 'wc1c_xml_charset');
        register_setting('wc1c_settings', 'wc1c_disable_variations');
        register_setting('wc1c_settings', 'wc1c_prevent_clean');
        register_setting('wc1c_settings', 'wc1c_match_by_sku');
        register_setting('wc1c_settings', 'wc1c_match_categories_by_title');
        register_setting('wc1c_settings', 'wc1c_match_properties_by_title');
        register_setting('wc1c_settings', 'wc1c_sync_interval');
        register_setting('wc1c_settings', 'wc1c_auto_sync');
        register_setting('wc1c_settings', 'wc1c_api_timeout');
        register_setting('wc1c_settings', 'wc1c_retry_attempts');
        register_setting('wc1c_settings', 'wc1c_batch_size');
    }

    /**
     * Display dashboard page
     */
    public function display_dashboard_page() {
        include_once WC1C_PLUGIN_DIR . 'admin/partials/wc1c-admin-dashboard.php';
    }

    /**
     * Display settings page
     */
    public function display_settings_page() {
        include_once WC1C_PLUGIN_DIR . 'admin/partials/wc1c-admin-settings.php';
    }

    /**
     * Display logs page
     */
    public function display_logs_page() {
        include_once WC1C_PLUGIN_DIR . 'admin/partials/wc1c-admin-logs.php';
    }

    /**
     * Display tools page
     */
    public function display_tools_page() {
        include_once WC1C_PLUGIN_DIR . 'admin/partials/wc1c-admin-tools.php';
    }

    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            echo '<div class="error"><p>';
            echo __('WooCommerce 1C Integration requires WooCommerce to be installed and activated.', 'woocommerce-1c-integration');
            echo '</p></div>';
            return;
        }

        // Check directory permissions
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

        // Check for recent sync errors
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
     * Handle manual sync AJAX request
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
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Handle clear logs AJAX request
     */
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
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Handle test connection AJAX request
     */
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
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Add product columns
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

    /**
     * Display product columns
     */
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

    /**
     * Add order columns
     */
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

    /**
     * Display order columns
     */
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
     * Check if current page is a plugin page
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

    /**
     * Get recent sync errors
     */
    private function get_recent_sync_errors() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc1c_exchange_logs';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE status IN ('error', 'critical') 
             AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY created_at DESC
             LIMIT 10"
        ));
    }

    /**
     * Display HPOS status in system information
     */
    private function get_hpos_status() {
        $status = array();
        
        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
            $status['hpos_available'] = true;
            $status['hpos_enabled'] = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
            $status['hpos_sync_enabled'] = \Automattic\WooCommerce\Utilities\OrderUtil::is_custom_order_tables_in_sync();
        } else {
            $status['hpos_available'] = false;
            $status['hpos_enabled'] = false;
            $status['hpos_sync_enabled'] = false;
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