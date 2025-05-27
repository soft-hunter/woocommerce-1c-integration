<?php
/**
 * Fired during plugin deactivation
 *
 * @package    WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/includes
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
     * Short Description. (use period)
     *
     * Long Description.
     */
    public static function deactivate() {
        // Clear rewrite rules
        flush_rewrite_rules();

        // Clear scheduled events
        self::clear_scheduled_events();

        // Clear transients
        self::clear_transients();

        // Log deactivation
        if (class_exists('WC1C_Logger')) {
            WC1C_Logger::log('Plugin deactivated', WC1C_Logger::INFO);
        }
    }

    /**
     * Clear all scheduled events
     */
    private static function clear_scheduled_events() {
        $scheduled_events = array(
            'wc1c_cleanup_logs',
            'wc1c_sync_check',
            'wc1c_maintenance',
            'wc1c_cache_cleanup'
        );

        foreach ($scheduled_events as $event) {
            wp_clear_scheduled_hook($event);
        }
    }

    /**
     * Clear plugin transients
     */
    private static function clear_transients() {
        global $wpdb;

        // Use prepared statement to clear transients safely
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_wc1c_%',
                '_transient_timeout_wc1c_%'
            )
        );

        // Clear object cache
        wp_cache_flush();
    }

    /**
     * Remove database indexes created by the plugin
     */
    public static function remove_database_indexes() {
        global $wpdb;

        $index_table_names = array(
            $wpdb->postmeta,
            $wpdb->termmeta,
            $wpdb->usermeta,
        );

        foreach ($index_table_names as $table_name) {
            $index_name = 'wc1c_meta_key_meta_value';
            
            // Check if index exists using prepared statement
            $index_exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                     WHERE table_schema = %s 
                     AND table_name = %s 
                     AND index_name = %s",
                    DB_NAME,
                    $table_name,
                    $index_name
                )
            );

            if ($index_exists) {
                // Note: Index names cannot be parameterized, but we're using a fixed value
                $wpdb->query("DROP INDEX `{$index_name}` ON `{$table_name}`");
            }
        }
    }

    /**
     * Clean up plugin options
     */
    public static function cleanup_options() {
        $plugin_options = array(
            'wc1c_settings',
            'wc1c_version',
            'wc1c_last_exchange',
            'wc1c_guid_attributes',
            'wc1c_timestamp_attributes',
            'wc1c_order_attributes',
            'wc1c_currency'
        );

        foreach ($plugin_options as $option) {
            delete_option($option);
        }
    }

    /**
     * Remove plugin capabilities
     */
    public static function remove_capabilities() {
        $capabilities = array(
            'manage_wc1c_exchange',
            'view_wc1c_logs',
            'configure_wc1c'
        );

        $roles = array('administrator', 'shop_manager');

        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($capabilities as $capability) {
                    $role->remove_cap($capability);
                }
            }
        }
    }
}