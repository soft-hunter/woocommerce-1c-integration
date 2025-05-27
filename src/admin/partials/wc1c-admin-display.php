<?php
/**
 * Provide a admin area view for the plugin
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
    
    <div class="wc1c-admin-content">
        <div class="wc1c-card">
            <h2><?php _e('Exchange URLs', 'woocommerce-1c-integration'); ?></h2>
            <p><?php _e('Use one of these URLs in your 1C configuration:', 'woocommerce-1c-integration'); ?></p>
            
            <div class="wc1c-exchange-url">
                <strong><?php _e('Standard URL:', 'woocommerce-1c-integration'); ?></strong><br>
                <code><?php echo home_url('/?wc1c_endpoint=exchange'); ?></code>
                <button type="button" class="button button-small wc1c-copy-url" data-url="<?php echo esc_attr(home_url('/?wc1c_endpoint=exchange')); ?>">
                    <?php _e('Copy', 'woocommerce-1c-integration'); ?>
                </button>
            </div>
            
            <?php if (get_option('permalink_structure')): ?>
            <div class="wc1c-exchange-url">
                <strong><?php _e('Pretty URL:', 'woocommerce-1c-integration'); ?></strong><br>
                <code><?php echo home_url('/wc1c/exchange/'); ?></code>
                <button type="button" class="button button-small wc1c-copy-url" data-url="<?php echo esc_attr(home_url('/wc1c/exchange/')); ?>">
                    <?php _e('Copy', 'woocommerce-1c-integration'); ?>
                </button>
            </div>
            <?php endif; ?>
        </div>

        <div class="wc1c-status-grid">
            <div class="wc1c-card">
                <h2><?php _e('System Status', 'woocommerce-1c-integration'); ?></h2>
                <?php $this->display_system_status(); ?>
            </div>

            <div class="wc1c-card">
                <h2><?php _e('Recent Activity', 'woocommerce-1c-integration'); ?></h2>
                <?php $this->display_recent_activity(); ?>
            </div>
        </div>

        <div class="wc1c-card">
            <h2><?php _e('Quick Actions', 'woocommerce-1c-integration'); ?></h2>
            <div class="wc1c-button-group">
                <button type="button" class="button button-secondary wc1c-test-connection">
                    <?php _e('Test Connection', 'woocommerce-1c-integration'); ?>
                </button>
                <button type="button" class="button button-secondary wc1c-refresh-status">
                    <?php _e('Refresh Status', 'woocommerce-1c-integration'); ?>
                </button>
                <button type="button" class="button button-secondary wc1c-clear-logs">
                    <?php _e('Clear Logs', 'woocommerce-1c-integration'); ?>
                </button>
            </div>
        </div>
    </div>
</div>