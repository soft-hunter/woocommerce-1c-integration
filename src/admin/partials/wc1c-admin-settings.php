<?php
/**
 * Provide a admin area view for the plugin settings
 *
 * @package    WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/admin/partials
 * @author     Igor Melnyk <igor.melnyk.it@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('wc1c_settings');
        do_settings_sections('wc1c_settings');
        ?>
        
        <table class="form-table">
            <tbody>
                <!-- General Settings -->
                <tr>
                    <th scope="row">
                        <label for="wc1c_enable_logging"><?php _e('Enable Logging', 'woocommerce-1c-integration'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="wc1c_enable_logging" name="wc1c_enable_logging" value="1" <?php checked(get_option('wc1c_enable_logging', 1)); ?> />
                        <p class="description"><?php _e('Enable detailed logging for debugging purposes.', 'woocommerce-1c-integration'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="wc1c_log_level"><?php _e('Log Level', 'woocommerce-1c-integration'); ?></label>
                    </th>
                    <td>
                        <select id="wc1c_log_level" name="wc1c_log_level">
                            <option value="debug" <?php selected(get_option('wc1c_log_level', 'info'), 'debug'); ?>><?php _e('Debug', 'woocommerce-1c-integration'); ?></option>
                            <option value="info" <?php selected(get_option('wc1c_log_level', 'info'), 'info'); ?>><?php _e('Info', 'woocommerce-1c-integration'); ?></option>
                            <option value="warning" <?php selected(get_option('wc1c_log_level', 'info'), 'warning'); ?>><?php _e('Warning', 'woocommerce-1c-integration'); ?></option>
                            <option value="error" <?php selected(get_option('wc1c_log_level', 'info'), 'error'); ?>><?php _e('Error', 'woocommerce-1c-integration'); ?></option>
                        </select>
                    </td>
                </tr>
                
                <!-- Performance Settings -->
                <tr>
                    <th scope="row">
                        <label for="wc1c_max_execution_time"><?php _e('Max Execution Time', 'woocommerce-1c-integration'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="wc1c_max_execution_time" name="wc1c_max_execution_time" value="<?php echo esc_attr(get_option('wc1c_max_execution_time', 300)); ?>" min="60" max="3600" />
                        <p class="description"><?php _e('Maximum execution time in seconds for synchronization operations.', 'woocommerce-1c-integration'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="wc1c_memory_limit"><?php _e('Memory Limit', 'woocommerce-1c-integration'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="wc1c_memory_limit" name="wc1c_memory_limit" value="<?php echo esc_attr(get_option('wc1c_memory_limit', '256M')); ?>" />
                        <p class="description"><?php _e('Memory limit for synchronization operations (e.g., 256M, 512M).', 'woocommerce-1c-integration'); ?></p>
                    </td>
                </tr>
                
                <!-- Synchronization Settings -->
                <tr>
                    <th scope="row">
                        <label for="wc1c_auto_sync"><?php _e('Auto Sync', 'woocommerce-1c-integration'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="wc1c_auto_sync" name="wc1c_auto_sync" value="1" <?php checked(get_option('wc1c_auto_sync', 0)); ?> />
                        <p class="description"><?php _e('Enable automatic synchronization based on schedule.', 'woocommerce-1c-integration'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="wc1c_sync_interval"><?php _e('Sync Interval', 'woocommerce-1c-integration'); ?></label>
                    </th>
                    <td>
                        <select id="wc1c_sync_interval" name="wc1c_sync_interval">
                            <option value="hourly" <?php selected(get_option('wc1c_sync_interval', 'daily'), 'hourly'); ?>><?php _e('Hourly', 'woocommerce-1c-integration'); ?></option>
                            <option value="twicedaily" <?php selected(get_option('wc1c_sync_interval', 'daily'), 'twicedaily'); ?>><?php _e('Twice Daily', 'woocommerce-1c-integration'); ?></option>
                            <option value="daily" <?php selected(get_option('wc1c_sync_interval', 'daily'), 'daily'); ?>><?php _e('Daily', 'woocommerce-1c-integration'); ?></option>
                            <option value="weekly" <?php selected(get_option('wc1c_sync_interval', 'daily'), 'weekly'); ?>><?php _e('Weekly', 'woocommerce-1c-integration'); ?></option>
                        </select>
                    </td>
                </tr>
                
                <!-- Product Settings -->
                <tr>
                    <th scope="row">
                        <label for="wc1c_manage_stock"><?php _e('Manage Stock', 'woocommerce-1c-integration'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="wc1c_manage_stock" name="wc1c_manage_stock" value="1" <?php checked(get_option('wc1c_manage_stock', 1)); ?> />
                        <p class="description"><?php _e('Enable stock management for synchronized products.', 'woocommerce-1c-integration'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="wc1c_match_by_sku"><?php _e('Match by SKU', 'woocommerce-1c-integration'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="wc1c_match_by_sku" name="wc1c_match_by_sku" value="1" <?php checked(get_option('wc1c_match_by_sku', 1)); ?> />
                        <p class="description"><?php _e('Match products by SKU instead of GUID when possible.', 'woocommerce-1c-integration'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <?php submit_button(); ?>
    </form>
</div>