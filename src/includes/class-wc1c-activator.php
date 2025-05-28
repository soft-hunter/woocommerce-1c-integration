<?php
/**
 * Fired during plugin activation
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
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 */
class WC1C_Activator {

    /**
     * Execute activation tasks
     */
    public static function activate() {
        // Create necessary directories
        self::create_directories();
        
        // Create database tables
        self::create_tables();
        
        // Set default options
        self::set_default_options();
        
        // Register rewrite rules
        self::register_rewrite_rules();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create necessary directories
     */
    private static function create_directories() {
        // Get upload directory
        $upload_dir = wp_upload_dir();
        
        // Main data directory
        $data_dir = $upload_dir['basedir'] . '/woocommerce_uploads/1c-exchange/';
        
        // Create directory if not exists
        if (!file_exists($data_dir)) {
            wp_mkdir_p($data_dir);
        }
        
        // Create logs directory
        $log_dir = $data_dir . 'logs/';
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        // Create images directory
        $images_dir = $data_dir . 'images/';
        if (!file_exists($images_dir)) {
            wp_mkdir_p($images_dir);
        }
        
        // Create temp directory for file uploads
        $temp_dir = $data_dir . 'temp/';
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        // Create import directory
        $import_dir = $data_dir . 'import/';
        if (!file_exists($import_dir)) {
            wp_mkdir_p($import_dir);
        }
        
        // Protect directories with index.php
        $index_file = '<?php // Silence is golden';
        if (!file_exists($data_dir . 'index.php')) {
            file_put_contents($data_dir . 'index.php', $index_file);
        }
        if (!file_exists($log_dir . 'index.php')) {
            file_put_contents($log_dir . 'index.php', $index_file);
        }
        if (!file_exists($images_dir . 'index.php')) {
            file_put_contents($images_dir . 'index.php', $index_file);
        }
        if (!file_exists($temp_dir . 'index.php')) {
            file_put_contents($temp_dir . 'index.php', $index_file);
        }
        if (!file_exists($import_dir . 'index.php')) {
            file_put_contents($import_dir . 'index.php', $index_file);
        }
        
        // Protect directories with .htaccess
        $htaccess_content = "# Apache 2.4+\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n# Apache 2.0-2.2\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>";
        if (!file_exists($data_dir . '.htaccess')) {
            file_put_contents($data_dir . '.htaccess', $htaccess_content);
        }
        if (!file_exists($log_dir . '.htaccess')) {
            file_put_contents($log_dir . '.htaccess', $htaccess_content);
        }
    }

    /**
     * Create necessary database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();

        // Sync stats table
        $table_name = $wpdb->prefix . 'wc1c_sync_stats';
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
     * Set default options
     */
    private static function set_default_options() {
        // Authentication
        add_option('wc1c_auth_enabled', 'yes');
        add_option('wc1c_auth_method', 'basic');
        add_option('wc1c_auth_username', '');
        add_option('wc1c_auth_password', '');
        
        // Logging
        add_option('wc1c_enable_logging', 'yes');
        add_option('wc1c_log_level', 'info');
        add_option('wc1c_log_retention_days', 30);
        
        // Import
        add_option('wc1c_max_file_size', 10); // 10MB
        add_option('wc1c_create_categories', 'yes');
        add_option('wc1c_update_existing', 'yes');
        add_option('wc1c_stock_management', 'yes');
        add_option('wc1c_disable_products', 'no');
        add_option('wc1c_price_type', '');
        
        // Export
        add_option('wc1c_export_order_statuses', array('processing', 'completed'));
        add_option('wc1c_export_order_date_from', '');
        add_option('wc1c_export_product_attributes', 'yes');
        
        // Images
        add_option('wc1c_images_dir', WC1C_DATA_DIR . 'images/');
        
        // Generate random credentials if not set
        if (get_option('wc1c_auth_username') === '') {
            update_option('wc1c_auth_username', 'wc1c_' . wp_generate_password(8, false));
        }
        if (get_option('wc1c_auth_password') === '') {
            update_option('wc1c_auth_password', wp_generate_password(16, true));
        }
    }
    
    /**
     * Register rewrite rules for 1C exchange endpoint
     */
    private static function register_rewrite_rules() {
        // Add rewrite rules for 1C exchange
        add_rewrite_rule(
            '1c-exchange$',
            'index.php?1c-exchange=true',
            'top'
        );
        
        // Add rewrite rule for exchange with mode parameter
        add_rewrite_rule(
            '1c-exchange/([^/]+)/?$',
            'index.php?1c-exchange=true&mode=$matches[1]',
            'top'
        );
        
        // Add rewrite rule for exchange with mode and type parameters
        add_rewrite_rule(
            '1c-exchange/([^/]+)/([^/]+)/?$',
            'index.php?1c-exchange=true&mode=$matches[1]&type=$matches[2]',
            'top'
        );
        
        // Register query vars
        add_filter('query_vars', function($vars) {
            $vars[] = '1c-exchange';
            $vars[] = 'mode';
            $vars[] = 'type';
            return $vars;
        });
    }
}