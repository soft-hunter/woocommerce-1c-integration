<?php
/**
 * Provide a admin area view for the plugin logs
 *
 * @package    WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/admin/partials
 * @author     Igor Melnyk <igormelnykit@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

// Get logs
$logs = $this->get_recent_logs();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="wc1c-logs-header">
        <form method="post" style="display: inline;">
            <?php wp_nonce_field('wc1c_clear_logs', 'wc1c_clear_logs_nonce'); ?>
            <input type="hidden" name="action" value="clear_logs" />
            <button type="submit" class="button" onclick="return confirm('<?php _e('Are you sure you want to clear all logs?', 'woocommerce-1c-integration'); ?>')">
                <?php _e('Clear Logs', 'woocommerce-1c-integration'); ?>
            </button>
        </form>
        
        <form method="get" style="display: inline; float: right;">
            <input type="hidden" name="page" value="wc1c-logs" />
            <select name="log_level">
                <option value=""><?php _e('All Levels', 'woocommerce-1c-integration'); ?></option>
                <option value="error" <?php selected($_GET['log_level'] ?? '', 'error'); ?>><?php _e('Error', 'woocommerce-1c-integration'); ?></option>
                <option value="warning" <?php selected($_GET['log_level'] ?? '', 'warning'); ?>><?php _e('Warning', 'woocommerce-1c-integration'); ?></option>
                <option value="info" <?php selected($_GET['log_level'] ?? '', 'info'); ?>><?php _e('Info', 'woocommerce-1c-integration'); ?></option>
                <option value="debug" <?php selected($_GET['log_level'] ?? '', 'debug'); ?>><?php _e('Debug', 'woocommerce-1c-integration'); ?></option>
            </select>
            <button type="submit" class="button"><?php _e('Filter', 'woocommerce-1c-integration'); ?></button>
        </form>
    </div>
    
    <div class="wc1c-logs-container">
        <?php if (!empty($logs)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Date', 'woocommerce-1c-integration'); ?></th>
                        <th><?php _e('Level', 'woocommerce-1c-integration'); ?></th>
                        <th><?php _e('Message', 'woocommerce-1c-integration'); ?></th>
                        <th><?php _e('Context', 'woocommerce-1c-integration'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at))); ?></td>
                        <td>
                            <span class="wc1c-log-level wc1c-log-level-<?php echo esc_attr($log->level); ?>">
                                <?php echo esc_html(strtoupper($log->level)); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($log->message); ?></td>
                        <td>
                            <?php if (!empty($log->context)): ?>
                                <details>
                                    <summary><?php _e('View Context', 'woocommerce-1c-integration'); ?></summary>
                                    <pre><?php echo esc_html($log->context); ?></pre>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php _e('No logs found.', 'woocommerce-1c-integration'); ?></p>
        <?php endif; ?>
    </div>
</div>