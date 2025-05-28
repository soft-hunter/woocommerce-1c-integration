<?php
/**
 * Provide a admin area view for the plugin dashboard
 *
 * @package    WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/admin/partials
 * @author     Igor Melnyk <igormelnykit@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

// Get recent sync stats
$recent_syncs = $this->get_recent_sync_stats();
$system_status = $this->get_system_status();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="wc1c-dashboard">
        <div class="wc1c-dashboard-widgets">
            
            <!-- System Status Widget -->
            <div class="wc1c-widget">
                <h2><?php _e('System Status', 'woocommerce-1c-integration'); ?></h2>
                <div class="wc1c-widget-content">
                    <?php $this->display_system_status(); ?>
                </div>
            </div>
            
            <!-- Quick Actions Widget -->
            <div class="wc1c-widget">
                <h2><?php _e('Quick Actions', 'woocommerce-1c-integration'); ?></h2>
                <div class="wc1c-widget-content">
                    <div class="wc1c-quick-actions">
                        <button type="button" class="button button-primary" id="wc1c-manual-sync" data-sync-type="full">
                            <?php _e('Start Full Sync', 'woocommerce-1c-integration'); ?>
                        </button>
                        <button type="button" class="button" id="wc1c-manual-sync" data-sync-type="catalog">
                            <?php _e('Sync Catalog Only', 'woocommerce-1c-integration'); ?>
                        </button>
                        <button type="button" class="button" id="wc1c-test-connection">
                            <?php _e('Test Connection', 'woocommerce-1c-integration'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity Widget -->
            <div class="wc1c-widget">
                <h2><?php _e('Recent Activity', 'woocommerce-1c-integration'); ?></h2>
                <div class="wc1c-widget-content">
                    <?php if (!empty($recent_syncs)): ?>
                        <table class="wc1c-recent-syncs">
                            <thead>
                                <tr>
                                    <th><?php _e('Date', 'woocommerce-1c-integration'); ?></th>
                                    <th><?php _e('Type', 'woocommerce-1c-integration'); ?></th>
                                    <th><?php _e('Status', 'woocommerce-1c-integration'); ?></th>
                                    <th><?php _e('Duration', 'woocommerce-1c-integration'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_syncs as $sync): ?>
                                <tr>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($sync->created_at))); ?></td>
                                    <td><?php echo esc_html($sync->exchange_type); ?></td>
                                    <td>
                                        <span class="wc1c-status wc1c-status-<?php echo esc_attr($sync->status); ?>">
                                            <?php echo esc_html(ucfirst($sync->status)); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($sync->execution_time . 's'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p><?php _e('No recent synchronization activity.', 'woocommerce-1c-integration'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
    </div>
</div>