<?php

/**
 * The admin-specific functionality of the plugin
 *
 * @package    WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/admin
 * @author     Igor Melnyk <igor.melnyk.it@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two hooks for
 * enqueuing the admin-specific stylesheet and JavaScript.
 */
class WC1C_Admin
{

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
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area.
     */
    public function enqueue_styles()
    {
        // Only load on our plugin pages
        $screen = get_current_screen();
        if (strpos($screen->id, 'wc1c') === false) {
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
    public function enqueue_scripts()
    {
        // Only load on our plugin pages
        $screen = get_current_screen();
        if (strpos($screen->id, 'wc1c') === false) {
            return;
        }

        wp_enqueue_script(
            $this->plugin_name,
            WC1C_PLUGIN_URL . 'admin/js/wc1c-admin.js',
            array('jquery'),
            $this->version,
            true
        );

        // Localize script
        wp_localize_script($this->plugin_name, 'wc1c_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc1c_nonce'),
            'confirm_clear_logs' => __('Are you sure you want to clear all logs?', 'woocommerce-1c-integration'),
            'loading_text' => __('Loading...', 'woocommerce-1c-integration'),
            'success_text' => __('Success', 'woocommerce-1c-integration'),
            'error_text' => __('Error', 'woocommerce-1c-integration')
        ));
    }

    /**
     * Add menu pages.
     */
    public function add_menu_pages()
    {
        // Main menu
        add_menu_page(
            __('1C Integration', 'woocommerce-1c-integration'),
            __('1C Integration', 'woocommerce-1c-integration'),
            'manage_woocommerce',
            'wc1c-integration',
            array($this, 'display_dashboard_page'),
            'dashicons-update',
            56
        );

        // Sub-menu pages
        $submenu_pages = array(
            'dashboard' => array(
                'parent' => 'wc1c-integration',
                'title' => __('Dashboard', 'woocommerce-1c-integration'),
                'menu_title' => __('Dashboard', 'woocommerce-1c-integration'),
                'capability' => 'manage_woocommerce',
                'menu_slug' => 'wc1c-integration',
                'function' => array($this, 'display_dashboard_page')
            ),
            'display' => array(
                'parent' => 'wc1c-integration',
                'title' => __('Display', 'woocommerce-1c-integration'),
                'menu_title' => __('Display', 'woocommerce-1c-integration'),
                'capability' => 'manage_woocommerce',
                'menu_slug' => 'wc1c-display',
                'function' => array($this, 'display_display_page')
            ),
            'settings' => array(
                'parent' => 'wc1c-integration',
                'title' => __('Settings', 'woocommerce-1c-integration'),
                'menu_title' => __('Settings', 'woocommerce-1c-integration'),
                'capability' => 'manage_woocommerce',
                'menu_slug' => 'wc1c-settings',
                'function' => array($this, 'display_settings_page')
            ),
            'logs' => array(
                'parent' => 'wc1c-integration',
                'title' => __('Logs', 'woocommerce-1c-integration'),
                'menu_title' => __('Logs', 'woocommerce-1c-integration'),
                'capability' => 'manage_woocommerce',
                'menu_slug' => 'wc1c-logs',
                'function' => array($this, 'display_logs_page')
            ),
            'tools' => array(
                'parent' => 'wc1c-integration',
                'title' => __('Tools', 'woocommerce-1c-integration'),
                'menu_title' => __('Tools', 'woocommerce-1c-integration'),
                'capability' => 'manage_woocommerce',
                'menu_slug' => 'wc1c-tools',
                'function' => array($this, 'display_tools_page')
            ),
            'status' => array(
                'parent' => 'wc1c-integration',
                'title' => __('Status', 'woocommerce-1c-integration'),
                'menu_title' => __('Status', 'woocommerce-1c-integration'),
                'capability' => 'manage_woocommerce',
                'menu_slug' => 'wc1c-status',
                'function' => array($this, 'display_main_page')
            )
        );

        foreach ($submenu_pages as $page) {
            add_submenu_page(
                $page['parent'],
                $page['title'],
                $page['menu_title'],
                $page['capability'],
                $page['menu_slug'],
                $page['function']
            );
        }
    }




    // public function add_menu_pages()
    // {
    //     // Main menu page
    //     add_menu_page(
    //         __('WooCommerce 1C Integration', 'woocommerce-1c-integration'),
    //         __('1C Integration', 'woocommerce-1c-integration'),
    //         'manage_woocommerce',
    //         'wc1c',
    //         array($this, 'display_main_page'),
    //         'dashicons-randomize',
    //         58
    //     );

    //     // Settings submenu
    //     add_submenu_page(
    //         'wc1c',
    //         __('Settings', 'woocommerce-1c-integration'),
    //         __('Settings', 'woocommerce-1c-integration'),
    //         'manage_woocommerce',
    //         'wc1c',
    //         array($this, 'display_main_page')
    //     );

    //     // Logs submenu
    //     add_submenu_page(
    //         'wc1c',
    //         __('Logs', 'woocommerce-1c-integration'),
    //         __('Logs', 'woocommerce-1c-integration'),
    //         'manage_woocommerce',
    //         'wc1c-logs',
    //         array($this, 'display_logs_page')
    //     );

    //     // Tools submenu
    //     add_submenu_page(
    //         'wc1c',
    //         __('Tools', 'woocommerce-1c-integration'),
    //         __('Tools', 'woocommerce-1c-integration'),
    //         'manage_woocommerce',
    //         'wc1c-tools',
    //         array($this, 'display_tools_page')
    //     );

    //     // Status submenu
    //     add_submenu_page(
    //         'wc1c',
    //         __('Status', 'woocommerce-1c-integration'),
    //         __('Status', 'woocommerce-1c-integration'),
    //         'manage_woocommerce',
    //         'wc1c-status',
    //         array($this, 'display_main_page')
    //     );
    // }

    /**
     * Register plugin settings.
     */
    public function register_settings()
    {
        // General settings
        register_setting('wc1c_general_settings', 'wc1c_auth_enabled');
        register_setting('wc1c_general_settings', 'wc1c_auth_method');
        register_setting('wc1c_general_settings', 'wc1c_auth_username');
        register_setting('wc1c_general_settings', 'wc1c_auth_password');
        register_setting('wc1c_general_settings', 'wc1c_enable_logging');
        register_setting('wc1c_general_settings', 'wc1c_log_level');
        register_setting('wc1c_general_settings', 'wc1c_log_retention_days');
        register_setting('wc1c_general_settings', 'wc1c_max_file_size');

        // Import settings
        register_setting('wc1c_import_settings', 'wc1c_create_categories');
        register_setting('wc1c_import_settings', 'wc1c_update_existing');
        register_setting('wc1c_import_settings', 'wc1c_stock_management');
        register_setting('wc1c_import_settings', 'wc1c_disable_products');
        register_setting('wc1c_import_settings', 'wc1c_price_type');

        // Export settings
        register_setting('wc1c_export_settings', 'wc1c_export_order_statuses');
        register_setting('wc1c_export_settings', 'wc1c_export_order_date_from');
        register_setting('wc1c_export_settings', 'wc1c_export_product_attributes');
    }

    /**
     * Display dashboard page.
     */

    public function display_dashboard_page()
    {
        // Check if tab is set
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        // Include view file
        include(WC1C_PLUGIN_DIR . 'admin/partials/wc1c-admin-dashboard.php');
    }

    /**
     * Display display page.
     */
    public function display_display_page()
    {
        // Check if tab is set
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        // Include view file
        include(WC1C_PLUGIN_DIR . 'admin/partials/wc1c-admin-display.php');
    }
    /**
     * Display settings page.
     */
    public function display_settings_page()
    {
        // Check if tab is set
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        // Include view file
        include(WC1C_PLUGIN_DIR . 'admin/partials/wc1c-admin-settings.php');
    }


    /**
     * Display main settings page.
     */
    public function display_main_page()
    {
        // Check if tab is set
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';

        // Include view file
        include(WC1C_PLUGIN_DIR . 'admin/partials/wc1c-admin-main.php');
    }

    /**
     * Display logs page.
     */
    public function display_logs_page()
    {
        // Check if tab is set
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        // Include view file
        include(WC1C_PLUGIN_DIR . 'admin/partials/wc1c-admin-logs.php');
    }

    /**
     * Display tools page.
     */
    public function display_tools_page()
    {
        // Check if tab is set
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        // Include view file
        include(WC1C_PLUGIN_DIR . 'admin/partials/wc1c-admin-tools.php');
    }



    /**
     * Display general settings
     */
    public function display_general_settings()
    {
        // Get current settings
        $auth_enabled = get_option('wc1c_auth_enabled', 'yes');
        $auth_method = get_option('wc1c_auth_method', 'basic');
        $auth_username = get_option('wc1c_auth_username', '');
        $auth_password = get_option('wc1c_auth_password', '');
        $enable_logging = get_option('wc1c_enable_logging', 'yes');
        $log_level = get_option('wc1c_log_level', 'info');
        $log_retention_days = get_option('wc1c_log_retention_days', 30);
        $max_file_size = get_option('wc1c_max_file_size', 10);

        // Exchange URL
        $exchange_url = home_url('1c-exchange');
?>
        <h2><?php _e('General Settings', 'woocommerce-1c-integration'); ?></h2>

        <div class="wc1c-admin-notice">
            <p><?php _e('These settings control the basic functionality of the 1C integration.', 'woocommerce-1c-integration'); ?></p>
        </div>

        <div class="wc1c-exchange-url">
            <h3><?php _e('Exchange URL', 'woocommerce-1c-integration'); ?></h3>
            <p><?php _e('Use this URL in 1C to connect to your WooCommerce store:', 'woocommerce-1c-integration'); ?></p>
            <code><?php echo esc_url($exchange_url); ?></code>
            <button type="button" class="button" id="wc1c-copy-url"><?php _e('Copy URL', 'woocommerce-1c-integration'); ?></button>
            <button type="button" class="button" id="wc1c-test-connection"><?php _e('Test Connection', 'woocommerce-1c-integration'); ?></button>
            <div id="wc1c-connection-result"></div>
        </div>

        <form method="post" action="options.php" class="wc1c-settings-form">
            <?php settings_fields('wc1c_general_settings'); ?>

            <h3><?php _e('Authentication', 'woocommerce-1c-integration'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Enable Authentication', 'woocommerce-1c-integration'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="wc1c_auth_enabled" value="yes" <?php checked('yes', $auth_enabled); ?> />
                            <?php _e('Enable authentication for 1C exchange', 'woocommerce-1c-integration'); ?>
                        </label>
                        <p class="description"><?php _e('It is highly recommended to keep authentication enabled for security reasons.', 'woocommerce-1c-integration'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Authentication Method', 'woocommerce-1c-integration'); ?></th>
                    <td>
                        <select name="wc1c_auth_method">
                            <option value="basic" <?php selected('basic', $auth_method); ?>><?php _e('HTTP Basic Authentication', 'woocommerce-1c-integration'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Username', 'woocommerce-1c-integration'); ?></th>
                    <td>
                        <input type="text" name="wc1c_auth_username" value="<?php echo esc_attr($auth_username); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Password', 'woocommerce-1c-integration'); ?></th>
                    <td>
                        <input type="password" name="wc1c_auth_password" value="<?php echo esc_attr($auth_password); ?>" class="regular-text" />
                        <button type="button" class="button" id="wc1c-generate-password"><?php _e('Generate', 'woocommerce-1c-integration'); ?></button>
                    </td>
                </tr>
            </table>

            <h3><?php _e('Logging', 'woocommerce-1c-integration'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Enable Logging', 'woocommerce-1c-integration'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="wc1c_enable_logging" value="yes" <?php checked('yes', $enable_logging); ?> />
                            <?php _e('Enable logging for 1C exchange operations', 'woocommerce-1c-integration'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Log Level', 'woocommerce-1c-integration'); ?></th>
                    <td>
                        <select name="wc1c_log_level">
                            <option value="debug" <?php selected('debug', $log_level); ?>><?php _e('Debug', 'woocommerce-1c-integration'); ?></option>
                            <option value="info" <?php selected('info', $log_level); ?>><?php _e('Info', 'woocommerce-1c-integration'); ?></option>
                            <option value="warning" <?php selected('warning', $log_level); ?>><?php _e('Warning', 'woocommerce-1c-integration'); ?></option>
                            <option value="error" <?php selected('error', $log_level); ?>><?php _e('Error', 'woocommerce-1c-integration'); ?></option>
                        </select>
                        <p class="description"><?php _e('Set the minimum log level to record. Debug is the most verbose.', 'woocommerce-1c-integration'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Log Retention', 'woocommerce-1c-integration'); ?></th>
                    <td>
                        <input type="number" name="wc1c_log_retention_days" value="<?php echo esc_attr($log_retention_days); ?>" min="1" max="365" step="1" />
                        <?php _e('days', 'woocommerce-1c-integration'); ?>

                        <p class="description"><?php _e('Number of days to keep logs before automatic cleanup.', 'woocommerce-1c-integration'); ?></p>
                    </td>
                </tr>
            </table>

            <h3><?php _e('Exchange Settings', 'woocommerce-1c-integration'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Maximum File Size', 'woocommerce-1c-integration'); ?></th>
                    <td>
                        <input type="number" name="wc1c_max_file_size" value="<?php echo esc_attr($max_file_size); ?>" min="1" max="100" step="1" />
                        <?php _e('MB', 'woocommerce-1c-integration'); ?>
                        <p class="description"><?php _e('Maximum size of files that can be uploaded from 1C.', 'woocommerce-1c-integration'); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    <?php
    }

    /**
     * Display import settings
     */
    public function display_import_settings()
    {
        // Get current settings
        $create_categories = get_option('wc1c_create_categories', 'yes');
        $update_existing = get_option('wc1c_update_existing', 'yes');
        $stock_management = get_option('wc1c_stock_management', 'yes');
        $disable_products = get_option('wc1c_disable_products', 'no');
        $price_type = get_option('wc1c_price_type', '');
    ?>
        <h2><?php _e('Import Settings', 'woocommerce-1c-integration'); ?></h2>

        <div class="wc1c-admin-notice">
            <p><?php _e('These settings control how products, categories, and other data are imported from 1C.', 'woocommerce-1c-integration'); ?></p>
        </div>

        <form method="post" action="options.php" class="wc1c-settings-form">
            <?php settings_fields('wc1c_import_settings'); ?>

            <h3><?php _e('Product Settings', 'woocommerce-1c-integration'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Create Categories', 'woocommerce-1c-integration'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="wc1c_create_categories" value="yes" <?php checked('yes', $create_categories); ?> />
                            <?php _e('Create product categories from 1C data', 'woocommerce-1c-integration'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Update Existing Products', 'woocommerce-1c-integration'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="wc1c_update_existing" value="yes" <?php checked('yes', $update_existing); ?> />
                            <?php _e('Update existing products with data from 1C', 'woocommerce-1c-integration'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Stock Management', 'woocommerce-1c-integration'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="wc1c_stock_management" value="yes" <?php checked('yes', $stock_management); ?> />
                            <?php _e('Update product stock quantities from 1C', 'woocommerce-1c-integration'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Disable Products', 'woocommerce-1c-integration'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="wc1c_disable_products" value="yes" <?php checked('yes', $disable_products); ?> />
                            <?php _e('Set products to draft if not present in 1C data', 'woocommerce-1c-integration'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Price Type', 'woocommerce-1c-integration'); ?></th>
                    <td>
                        <input type="text" name="wc1c_price_type" value="<?php echo esc_attr($price_type); ?>" class="regular-text" />
                        <p class="description"><?php _e('ID of price type to use from 1C. Leave empty to use the first price found.', 'woocommerce-1c-integration'); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    <?php
    }

    /**
     * Display export settings
     */
    public function display_export_settings()
    {
        // Get current settings
        $export_order_statuses = get_option('wc1c_export_order_statuses', array('processing', 'completed'));
        $export_order_date_from = get_option('wc1c_export_order_date_from', '');
        $export_product_attributes = get_option('wc1c_export_product_attributes', 'yes');

        // Get order statuses
        $order_statuses = wc_get_order_statuses();
    ?>
        <h2><?php _e('Export Settings', 'woocommerce-1c-integration'); ?></h2>

        <div class="wc1c-admin-notice">
            <p><?php _e('These settings control how orders and other data are exported to 1C.', 'woocommerce-1c-integration'); ?></p>
        </div>

        <form method="post" action="options.php" class="wc1c-settings-form">
            <?php settings_fields('wc1c_export_settings'); ?>

            <h3><?php _e('Order Export', 'woocommerce-1c-integration'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Order Statuses to Export', 'woocommerce-1c-integration'); ?></th>
                    <td>
                        <select name="wc1c_export_order_statuses[]" multiple="multiple" class="wc1c-multiselect" style="width: 400px; height: 100px;">
                            <?php foreach ($order_statuses as $status => $label) : ?>
                                <?php
                                $status_name = 'wc-' === substr($status, 0, 3) ? substr($status, 3) : $status;
                                $selected = in_array($status_name, $export_order_statuses) ? 'selected="selected"' : '';
                                ?>
                                <option value="<?php echo esc_attr($status_name); ?>" <?php echo $selected; ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Select which order statuses should be exported to 1C.', 'woocommerce-1c-integration'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Export Orders From Date', 'woocommerce-1c-integration'); ?></th>
                    <td>
                        <input type="date" name="wc1c_export_order_date_from" value="<?php echo esc_attr($export_order_date_from); ?>" />
                        <p class="description"><?php _e('Export only orders created on or after this date. Leave empty to export all orders.', 'woocommerce-1c-integration'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Export Product Attributes', 'woocommerce-1c-integration'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="wc1c_export_product_attributes" value="yes" <?php checked('yes', $export_product_attributes); ?> />
                            <?php _e('Include product attributes in order export', 'woocommerce-1c-integration'); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    <?php
    }

    /**
     * Add plugin action links.
     *
     * @param array $links Plugin action links
     * @return array Modified action links
     */
    public function add_action_links($links)
    {
        $action_links = array(
            'settings' => '<a href="' . admin_url('admin.php?page=wc1c') . '">' . __('Settings', 'woocommerce-1c-integration') . '</a>',
        );

        return array_merge($action_links, $links);
    }

    /**
     * Add plugin row meta links.
     *
     * @param array $links Plugin row meta links
     * @param string $file Plugin base file
     * @return array Modified links
     */
    public function add_plugin_row_meta($links, $file)
    {
        if (WC1C_PLUGIN_BASENAME == $file) {
            $row_meta = array(
                'docs' => '<a href="https://github.com/soft-hunter/woocommerce-1c-integration/wiki" target="_blank">' . __('Documentation', 'woocommerce-1c-integration') . '</a>',
                'support' => '<a href="https://github.com/soft-hunter/woocommerce-1c-integration/issues" target="_blank">' . __('Support', 'woocommerce-1c-integration') . '</a>',
            );

            return array_merge($links, $row_meta);
        }

        return $links;
    }

    /**
     * AJAX handler for clearing logs.
     */
    public function ajax_clear_logs()
    {
        // Check nonce
        check_ajax_referer('wc1c_nonce', 'nonce');

        // Check user capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action.', 'woocommerce-1c-integration')
            ));
            return;
        }

        // Get upload directory
        $upload_dir = wp_upload_dir();

        // Build log directory path
        $log_dir = $upload_dir['basedir'] . '/woocommerce_uploads/1c-exchange/logs/';

        // Skip if directory doesn't exist
        if (!file_exists($log_dir)) {
            wp_send_json_success(array(
                'message' => __('No logs found.', 'woocommerce-1c-integration')
            ));
            return;
        }

        // Get log files
        $files = glob($log_dir . '*.log*');

        // Delete each file
        $deleted = 0;
        foreach ($files as $file) {
            if (is_file($file) && @unlink($file)) {
                $deleted++;
            }
        }

        // Log clearing
        WC1C_Logger::info('Logs cleared via admin interface', array(
            'deleted_files' => $deleted
        ));

        wp_send_json_success(array(
            'message' => sprintf(__('Logs cleared successfully. %d files deleted.', 'woocommerce-1c-integration'), $deleted)
        ));
    }

    /**
     * AJAX handler for getting logs.
     */
    public function ajax_get_logs()
    {
        // Check nonce
        check_ajax_referer('wc1c_nonce', 'nonce');

        // Check user capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action.', 'woocommerce-1c-integration')
            ));
            return;
        }

        // Get parameters
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 50;
        $level = isset($_POST['level']) ? sanitize_text_field($_POST['level']) : '';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';

        // Get logs
        $logs = WC1C_Logger::get_log_entries(array(
            'page' => $page,
            'per_page' => $per_page,
            'level' => $level,
            'search' => $search,
            'date_from' => $date_from,
            'date_to' => $date_to
        ));

        wp_send_json_success($logs);
    }

    /**
     * AJAX handler for testing connection.
     */
    public function ajax_test_connection()
    {
        // Check nonce
        check_ajax_referer('wc1c_nonce', 'nonce');

        // Check user capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action.', 'woocommerce-1c-integration')
            ));
            return;
        }

        // Build exchange URL
        $url = home_url('1c-exchange');

        // Get authentication credentials
        $auth_enabled = get_option('wc1c_auth_enabled', 'yes');
        $username = get_option('wc1c_auth_username', '');
        $password = get_option('wc1c_auth_password', '');

        // Set up request args
        $args = array(
            'timeout' => 30,
            'redirection' => 0,
            'sslverify' => false,
            'user-agent' => 'WooCommerce/1C-Integration Test'
        );

        // Add authentication if enabled
        if ($auth_enabled === 'yes' && !empty($username) && !empty($password)) {
            $args['headers'] = array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
            );
        }

        // Make request to checkauth mode
        $response = wp_remote_get($url . '?type=catalog&mode=checkauth', $args);

        // Check for errors
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => __('Connection error: ', 'woocommerce-1c-integration') . $response->get_error_message()
            ));
            return;
        }

        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            wp_send_json_error(array(
                'message' => sprintf(__('Server returned HTTP code: %d', 'woocommerce-1c-integration'), $response_code)
            ));
            return;
        }

        // Get response body
        $body = wp_remote_retrieve_body($response);

        // Check for success message
        if (strpos($body, 'success') === 0) {
            wp_send_json_success(array(
                'message' => __('Connection successful! Your WordPress site is ready to receive data from 1C.', 'woocommerce-1c-integration'),
                'response' => $body
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Connection failed. Server response:', 'woocommerce-1c-integration'),
                'response' => $body
            ));
        }
    }

    /**
     * Schedule maintenance tasks.
     */
    public function schedule_maintenance_tasks()
    {
        // Schedule daily log cleanup if not already scheduled
        if (!wp_next_scheduled('wc1c_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'wc1c_daily_maintenance');
        }
    }

    /**
     * Get recent synchronization statistics
     *
     * @return array Array of recent sync records
     */
    public function get_recent_sync_stats()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wc1c_sync_stats';

        // Create table if not exists
        $this->create_sync_stats_table();

        // Get last 10 sync records
        $results = $wpdb->get_results(
            "SELECT * FROM {$table_name} 
            ORDER BY created_at DESC 
            LIMIT 10"
        );

        return $results ? $results : array();
    }

    /**
     * Get system status information
     *
     * @return array System status data
     */
    public function get_system_status()
    {
        return array(
            'wp_version' => get_bloginfo('version'),
            'wc_version' => WC()->version,
            'php_version' => phpversion(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_input_vars' => ini_get('max_input_vars'),
            'extensions' => array(
                'curl' => extension_loaded('curl'),
                'xml' => extension_loaded('xml'),
                'mbstring' => extension_loaded('mbstring'),
                'zip' => extension_loaded('zip')
            )
        );
    }

    /**
     * Create sync stats table if not exists
     */
    private function create_sync_stats_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wc1c_sync_stats';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            exchange_type varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            execution_time float NOT NULL,
            items_processed int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Display system status information
     */
    public function display_system_status()
    {
        $status = $this->get_system_status();
    ?>
        <table class="wc1c-status-table widefat">
            <tbody>
                <tr>
                    <td><?php _e('WordPress Version', 'woocommerce-1c-integration'); ?></td>
                    <td><?php echo esc_html($status['wp_version']); ?></td>
                </tr>
                <tr>
                    <td><?php _e('WooCommerce Version', 'woocommerce-1c-integration'); ?></td>
                    <td><?php echo esc_html($status['wc_version']); ?></td>
                </tr>
                <tr>
                    <td><?php _e('PHP Version', 'woocommerce-1c-integration'); ?></td>
                    <td><?php echo esc_html($status['php_version']); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Memory Limit', 'woocommerce-1c-integration'); ?></td>
                    <td><?php echo esc_html($status['memory_limit']); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Max Execution Time', 'woocommerce-1c-integration'); ?></td>
                    <td><?php echo esc_html($status['max_execution_time']); ?>s</td>
                </tr>
            </tbody>
        </table>
<?php
    }
}
