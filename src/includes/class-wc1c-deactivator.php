<?php
/**
 * Fired during plugin deactivation
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
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 */
class WC1C_Deactivator {

    /**
     * Execute deactivation tasks
     */
    public static function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('wc1c_daily_maintenance');
        
        // Clean up database tables if plugin is being deleted
        if (isset($_GET['action']) && $_GET['action'] === 'delete-plugin') {
            self::cleanup_database();
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log deactivation
        if (class_exists('WC1C_Logger')) {
            WC1C_Logger::info('Plugin deactivated');
        }
    }

    /**
     * Clean up database tables and options
     */
    private static function cleanup_database() {
        global $wpdb;

        // Drop sync stats table
        $table_name = $wpdb->prefix . 'wc1c_sync_stats';
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");

        // Remove plugin options
        $options = array(
            'wc1c_auth_enabled',
            'wc1c_auth_method',
            'wc1c_auth_username',
            'wc1c_auth_password',
            'wc1c_enable_logging',
            'wc1c_log_level',
            'wc1c_log_retention_days',
            'wc1c_max_file_size',
            'wc1c_create_categories',
            'wc1c_update_existing',
            'wc1c_stock_management',
            'wc1c_disable_products',
            'wc1c_price_type',
            'wc1c_export_order_statuses',
            'wc1c_export_order_date_from',
            'wc1c_export_product_attributes'
        );

        foreach ($options as $option) {
            delete_option($option);
        }

        // Log cleanup
        if (class_exists('WC1C_Logger')) {
            WC1C_Logger::info('Plugin database cleanup completed');
        }
    }
}