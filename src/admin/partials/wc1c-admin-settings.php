<?php
/**
 * Settings page template
 *
 * @package    WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/admin/partials
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}
?>

<div class="wrap wc1c-admin-page">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors(); ?>
    
    <form method="post" action="options.php" id="wc1c-settings-form">
        <?php
        settings_fields('wc1c_settings');
        do_settings_sections('wc1c_settings');
        ?>
        
        <div class="wc1c-settings-section">
            <h3><?php _e('General Settings', 'woocommerce-1c-integration'); ?></h3>
            <table class="wc1c-form-table">
                <tr>
                    <th scope="row">
                        <label for="wc1c_enable_logging"><?php _e('Enable Logging', 'woocommerce-1c-integration'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="wc1c_enable_logging" name="wc1c_enable_logging" value="1" <?php checked(get_option('wc1c_enable_logging', 1)); ?> />
                        <p class="wc1c-help-text"><?php _e('Enable detailed logging of exchange operations.', 'woocommerce-1c-integration'); ?></p>
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
                        <p class="wc1c-help-text"><?php _e('Minimum log level to record.', 'woocommerce-1c-integration'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="wc1c-settings-section">
            <h3><?php _e('Exchange Settings', 'woocommerce-1c-integration'); ?></h3>
            <table class="wc1c-form-table">
                <tr>
                    <th scope="row">
                        <label for="wc1c_max_execution_time"><?php _e('Max Execution Time', 'woocommerce-1c-integration'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="wc1c_max_execution_time" name="wc1c_max_execution_time" value="<?php echo esc_attr(get_option('wc1c_max_execution_time', 300)); ?>" min="60" max="3600" />
                        <p class="wc1c-help-text"><?php _e('Maximum execution time for exchange operations (seconds).', 'woocommerce-1c-integration'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wc1c_memory_limit"><?php _e('Memory Limit', 'woocommerce-1c-integration'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="wc1c_memory_limit" name="wc1c_memory_limit" value="<?php echo esc_attr(get_option('wc1c_memory_limit', '512M')); ?>" />
                        <p class="wc1c-help-text"><?php _e('Memory limit for exchange operations (e.g., 512M, 1G).', 'woocommerce-1c-integration'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wc1c_file_limit"><?php _e('File Size Limit', 'woocommerce-1c-integration'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="wc1c_file_limit" name="wc1c_file_limit" value="<?php echo esc_attr(get_option('wc1c_file_limit', '100M')); ?>" />
                        <p class="wc1c-help-text"><?php _e('Maximum file size for uploads (e.g., 100M, 1G).', 'woocommerce-1c-integration'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="wc1c-settings-section">
            <h3><?php _e('Security Settings', 'woocommerce-1c-integration'); ?></h3>
            <table class="wc1c-form-table">
                <tr>
                    <th scope="row">
                        <label for="wc1c_rate_limit"><?php _e('Rate Limit', 'woocommerce-1c-integration'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="wc1c_rate_limit" name="wc1c_rate_limit" value="<?php echo esc_attr(get_option('wc1c_rate_limit', 60)); ?>" min="1" max="1000" />
                        <p class="wc1c-help-text"><?php _e('Maximum requests per hour from single IP.', 'woocommerce-1c-integration'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wc1c_enable_ip_whitelist"><?php _e('Enable IP Whitelist', 'woocommerce-1c-integration'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="wc1c_enable_ip_whitelist" name="wc1c_enable_ip_whitelist" value="1" <?php checked(get_option('wc1c_enable_ip_whitelist', 0)); ?> />
                        <p class="wc1c-help-text"><?php _e('Only allow connections from whitelisted IP addresses.', 'woocommerce-1c-integration'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wc1c_ip_whitelist"><?php _e('IP Whitelist', 'woocommerce-1c-integration'); ?></label>
                    </th>
                    <td>
                        <textarea id="wc1c_ip_whitelist" name="wc1c_ip_whitelist" placeholder="192.168.1.1
10.0.0.0/8"><?php echo esc_textarea(get_option('wc1c_ip_whitelist', '')); ?></textarea>
                        <p class="wc1c-help-text"><?php _e('One IP address or CIDR range per line.', 'woocommerce-1c-integration'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="wc1c-button-group">
            <?php submit_button(__('Save Settings', 'woocommerce-1c-integration'), 'primary', 'submit', false); ?>
            <button type="button" class="button button-secondary wc1c-export-settings">
                <?php _e('Export Settings', 'woocommerce-1c-integration'); ?>
            </button>
            <button type="button" class="button button-secondary wc1c-import-settings">
                <?php _e('Import Settings', 'woocommerce-1c-integration'); ?>
            </button>
        </div>
        
        <div class="wc1c-draft-saved" style="display: none;">
            <em><?php _e('Draft saved automatically', 'woocommerce-1c-integration'); ?></em>
        </div>
    </form>
</div>