<?php
/**
 * WooCommerce 1C Integration Uninstall
 * 
 * This file is executed when the plugin is deleted (not just deactivated).
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied');
}

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit('Direct access denied');
}

// Security check - ensure we're actually uninstalling this plugin
if (!defined('WP_UNINSTALL_PLUGIN') || WP_UNINSTALL_PLUGIN !== 'woocommerce-1c-integration/woocommerce-1c-integration.php') {
    exit('Invalid uninstall request');
}

// Load plugin constants if not already defined
if (!defined('WC1C_PLUGIN_DIR')) {
    define('WC1C_PLUGIN_DIR', __DIR__ . '/');
}

if (!defined('WC1C_DATA_DIR')) {
    $upload_dir = wp_upload_dir();
    define('WC1C_DATA_DIR', "{$upload_dir['basedir']}/woocommerce_uploads/1c-exchange/");
}

// Disable time limit for cleanup process
$disabled_functions = explode(',', ini_get('disable_functions'));
if (!in_array('set_time_limit', $disabled_functions)) {
    @set_time_limit(0);
}

global $wpdb;

/**
 * Log uninstall process
 */
function wc1c_uninstall_log($message) {
    error_log("WC1C Uninstall: $message");
}

wc1c_uninstall_log('Starting WooCommerce 1C plugin uninstall process');

/**
 * 1. Remove all uploaded files and directories
 */
if (is_dir(WC1C_DATA_DIR)) {
    wc1c_uninstall_log('Removing data directory: ' . WC1C_DATA_DIR);
    
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(WC1C_DATA_DIR, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        $file_count = 0;
        $dir_count = 0;
        
        foreach ($iterator as $path => $item) {
            if ($item->isDir()) {
                if (rmdir($path)) {
                    $dir_count++;
                }
            } else {
                if (unlink($path)) {
                    $file_count++;
                }
            }
        }
        
        // Remove the main directory
        if (rmdir(WC1C_DATA_DIR)) {
            wc1c_uninstall_log("Removed $file_count files and $dir_count directories");
        }
        
    } catch (Exception $e) {
        wc1c_uninstall_log('Error removing data directory: ' . $e->getMessage());
    }
}

/**
 * 2. Clean up term metadata (categories and attributes)
 */
wc1c_uninstall_log('Cleaning up term metadata');

// Get all terms with 1C metadata using prepared statements
$term_meta_keys = $wpdb->get_col(
    $wpdb->prepare(
        "SELECT DISTINCT meta_key FROM {$wpdb->termmeta} WHERE meta_key LIKE %s",
        'wc1c_%'
    )
);

if ($term_meta_keys) {
    foreach ($term_meta_keys as $meta_key) {
        $deleted_count = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->termmeta} WHERE meta_key = %s",
                $meta_key
            )
        );
        wc1c_uninstall_log("Removed $deleted_count entries for meta_key: $meta_key");
    }
}

/**
 * 3. Clean up post metadata (products and orders)
 */
wc1c_uninstall_log('Cleaning up post metadata');

$post_meta_keys = $wpdb->get_col(
    $wpdb->prepare(
        "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
        '_wc1c_%',
        'wc1c_%'
    )
);

if ($post_meta_keys) {
    foreach ($post_meta_keys as $meta_key) {
        $deleted_count = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
                $meta_key
            )
        );
        wc1c_uninstall_log("Removed $deleted_count entries for meta_key: $meta_key");
    }
}

/**
 * 4. Clean up user metadata
 */
wc1c_uninstall_log('Cleaning up user metadata');

$user_meta_keys = $wpdb->get_col(
    $wpdb->prepare(
        "SELECT DISTINCT meta_key FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
        'wc1c_%'
    )
);

if ($user_meta_keys) {
    foreach ($user_meta_keys as $meta_key) {
        $deleted_count = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
                $meta_key
            )
        );
        wc1c_uninstall_log("Removed $deleted_count entries for meta_key: $meta_key");
    }
}

/**
 * 5. Remove plugin options
 */
wc1c_uninstall_log('Removing plugin options');

$plugin_options = array(
    'wc1c_activated',
    'wc1c_version',
    'wc1c_last_exchange',
    'wc1c_guid_attributes',
    'wc1c_timestamp_attributes',
    'wc1c_order_attributes',
    'wc1c_currency',
    'wc1c_price_type',
    'wc1c_settings'
);

$removed_options = 0;
foreach ($plugin_options as $option) {
    if (delete_option($option)) {
        $removed_options++;
    }
}

// Remove any other options that start with wc1c_
$other_options = $wpdb->get_col(
    $wpdb->prepare(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
        'wc1c_%'
    )
);

foreach ($other_options as $option) {
    if (delete_option($option)) {
        $removed_options++;
    }
}

wc1c_uninstall_log("Removed $removed_options plugin options");

/**
 * 6. Clear scheduled events
 */
wc1c_uninstall_log('Clearing scheduled events');

$scheduled_events = array(
    'wc1c_cleanup_logs',
    'wc1c_sync_check',
    'wc1c_maintenance'
);

$cleared_events = 0;
foreach ($scheduled_events as $event) {
    if (wp_clear_scheduled_hook($event)) {
        $cleared_events++;
    }
}

wc1c_uninstall_log("Cleared $cleared_events scheduled events");

/**
 * 7. Clear all caches
 */
wc1c_uninstall_log('Clearing caches');

// Clear WordPress object cache
wp_cache_flush();

// Clear any transients created by the plugin
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        '_transient_wc1c_%',
        '_transient_timeout_wc1c_%'
    )
);

wc1c_uninstall_log('✅ Plugin uninstall completed successfully');