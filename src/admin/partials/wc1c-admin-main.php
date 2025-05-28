<?php
/**
 *  Main page view for the WooCommerce 1C Integration plugin
 *
 * @package WooCommerce_1C_Integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="wc1c-status-wrapper">
        <table class="wc1c-status-table widefat">
            <tbody>
                <tr>
                    <td><?php _e('Plugin Version', 'woocommerce-1c-integration'); ?></td>
                    <td><?php echo WC1C_VERSION; ?></td>
                </tr>
                <tr>
                    <td><?php _e('WordPress Version', 'woocommerce-1c-integration'); ?></td>
                    <td><?php echo get_bloginfo('version'); ?></td>
                </tr>
                <tr>
                    <td><?php _e('WooCommerce Version', 'woocommerce-1c-integration'); ?></td>
                    <td><?php echo WC()->version; ?></td>
                </tr>
                <tr>
                    <td><?php _e('PHP Version', 'woocommerce-1c-integration'); ?></td>
                    <td><?php echo phpversion(); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Memory Limit', 'woocommerce-1c-integration'); ?></td>
                    <td><?php echo ini_get('memory_limit'); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Max Execution Time', 'woocommerce-1c-integration'); ?></td>
                    <td><?php echo ini_get('max_execution_time'); ?>s</td>
                </tr>
                <?php 
                // Display required PHP extensions
                $required_extensions = array('xml', 'mbstring', 'curl', 'zip');
                foreach ($required_extensions as $ext): ?>
                <tr>
                    <td><?php printf(__('%s Extension', 'woocommerce-1c-integration'), strtoupper($ext)); ?></td>
                    <td><?php echo extension_loaded($ext) ? 
                        '<span class="wc1c-status-yes">✓ ' . __('Installed', 'woocommerce-1c-integration') . '</span>' : 
                        '<span class="wc1c-status-no">✗ ' . __('Not Installed', 'woocommerce-1c-integration') . '</span>'; 
                    ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>