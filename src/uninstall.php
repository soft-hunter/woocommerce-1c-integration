<?php
/**
 * WooCommerce 1C Exchange Uninstall
 * 
 * This file is executed when the plugin is deleted (not just deactivated).
 * It removes all plugin data, files, and database entries.
 * 
 * @package WooCommerce_1C
 * @version 1.0.0
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN') && !defined('WP_CLI')) {
    exit('Direct access denied');
}

// Security check - ensure we're actually uninstalling this plugin
if (!defined('WP_UNINSTALL_PLUGIN') || WP_UNINSTALL_PLUGIN !== 'woocommerce-1c-integration/woocommerce-1c.php') {
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

// Load exchange functions for cleanup
if (file_exists(WC1C_PLUGIN_DIR . "exchange.php")) {
    require_once WC1C_PLUGIN_DIR . "exchange.php";
}

// Disable time limit for cleanup process
if (function_exists('wc1c_disable_time_limit')) {
    wc1c_disable_time_limit();
} else {
    $disabled_functions = explode(',', ini_get('disable_functions'));
    if (!in_array('set_time_limit', $disabled_functions)) {
        @set_time_limit(0);
    }
}

global $wpdb;

/**
 * Log uninstall process
 */
function wc1c_uninstall_log($message) {
    if (defined('WP_CLI') && WP_CLI) {
        WP_CLI::log($message);
    } else {
        error_log("WC1C Uninstall: $message");
    }
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
                } else {
                    wc1c_uninstall_log("Failed to remove directory: $path");
                }
            } else {
                if (unlink($path)) {
                    $file_count++;
                } else {
                    wc1c_uninstall_log("Failed to remove file: $path");
                }
            }
        }
        
        // Remove the main directory
        if (rmdir(WC1C_DATA_DIR)) {
            wc1c_uninstall_log("Removed $file_count files and $dir_count directories");
        } else {
            wc1c_uninstall_log("Failed to remove main data directory");
        }
        
    } catch (Exception $e) {
        wc1c_uninstall_log('Error removing data directory: ' . $e->getMessage());
    }
} else {
    wc1c_uninstall_log('Data directory not found, skipping file cleanup');
}

/**
 * 2. Clean up term metadata (categories and attributes)
 */
wc1c_uninstall_log('Cleaning up term metadata');

// Get all terms with 1C metadata
$term_meta_keys = $wpdb->get_col(
    "SELECT DISTINCT meta_key FROM $wpdb->termmeta WHERE meta_key LIKE 'wc1c_%'"
);

if ($term_meta_keys) {
    foreach ($term_meta_keys as $meta_key) {
        $deleted_count = $wpdb->query($wpdb->prepare(
            "DELETE FROM $wpdb->termmeta WHERE meta_key = %s",
            $meta_key
        ));
        wc1c_uninstall_log("Removed $deleted_count entries for meta_key: $meta_key");
    }
} else {
    wc1c_uninstall_log('No term metadata found to clean up');
}

/**
 * 3. Clean up post metadata (products and orders)
 */
wc1c_uninstall_log('Cleaning up post metadata');

$post_meta_keys = $wpdb->get_col(
    "SELECT DISTINCT meta_key FROM $wpdb->postmeta WHERE meta_key LIKE '_wc1c_%' OR meta_key LIKE 'wc1c_%'"
);

if ($post_meta_keys) {
    foreach ($post_meta_keys as $meta_key) {
        $deleted_count = $wpdb->query($wpdb->prepare(
            "DELETE FROM $wpdb->postmeta WHERE meta_key = %s",
            $meta_key
        ));
        wc1c_uninstall_log("Removed $deleted_count entries for meta_key: $meta_key");
    }
} else {
    wc1c_uninstall_log('No post metadata found to clean up');
}

/**
 * 4. Clean up user metadata
 */
wc1c_uninstall_log('Cleaning up user metadata');

$user_meta_keys = $wpdb->get_col(
    "SELECT DISTINCT meta_key FROM $wpdb->usermeta WHERE meta_key LIKE 'wc1c_%'"
);

if ($user_meta_keys) {
    foreach ($user_meta_keys as $meta_key) {
        $deleted_count = $wpdb->query($wpdb->prepare(
            "DELETE FROM $wpdb->usermeta WHERE meta_key = %s",
            $meta_key
        ));
        wc1c_uninstall_log("Removed $deleted_count entries for meta_key: $meta_key");
    }
} else {
    wc1c_uninstall_log('No user metadata found to clean up');
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
    "SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'wc1c_%'"
);

foreach ($other_options as $option) {
    if (delete_option($option)) {
        $removed_options++;
    }
}

wc1c_uninstall_log("Removed $removed_options plugin options");

/**
 * 6. Remove WooCommerce attributes created by the plugin
 */
if (class_exists('WooCommerce')) {
    wc1c_uninstall_log('Cleaning up WooCommerce attributes');
    
    // Get attributes that were created by 1C
    $wc1c_attributes = get_option('wc1c_guid_attributes', array());
    
    if (!empty($wc1c_attributes)) {
        foreach ($wc1c_attributes as $guid => $attribute_id) {
            // Get attribute details
            $attribute = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_id = %d",
                $attribute_id
            ));
            
            if ($attribute) {
                $taxonomy = wc_attribute_taxonomy_name($attribute->attribute_name);
                
                // Delete all terms in this taxonomy
                $terms = get_terms(array(
                    'taxonomy' => $taxonomy,
                    'hide_empty' => false,
                    'fields' => 'ids'
                ));
                
                foreach ($terms as $term_id) {
                    wp_delete_term($term_id, $taxonomy);
                }
                
                // Delete the attribute taxonomy
                $wpdb->delete(
                    "{$wpdb->prefix}woocommerce_attribute_taxonomies",
                    array('attribute_id' => $attribute_id)
                );
                
                // Clean up taxonomy options
                delete_option("{$taxonomy}_children");
                
                wc1c_uninstall_log("Removed WooCommerce attribute: {$attribute->attribute_name}");
            }
        }
        
        // Clear attribute cache
        delete_transient('wc_attribute_taxonomies');
    } else {
        wc1c_uninstall_log('No WooCommerce attributes found to clean up');
    }
}

/**
 * 7. Remove database indexes created by the plugin
 */
wc1c_uninstall_log('Removing database indexes');

$index_table_names = array(
    $wpdb->postmeta,
    $wpdb->termmeta,
    $wpdb->usermeta,
);

$removed_indexes = 0;
foreach ($index_table_names as $table_name) {
    $index_name = 'wc1c_meta_key_meta_value';
    
    // Check if index exists
    $index_exists = $wpdb->get_var($wpdb->prepare(
        "SHOW INDEX FROM `%s` WHERE Key_name = %s",
        $table_name,
        $index_name
    ));
    
    if ($index_exists) {
        $result = $wpdb->query($wpdb->prepare(
            "DROP INDEX `%s` ON `%s`",
            $index_name,
            $table_name
        ));
        
        if ($result !== false) {
            $removed_indexes++;
            wc1c_uninstall_log("Removed index from table: $table_name");
        } else {
            wc1c_uninstall_log("Failed to remove index from table: $table_name");
        }
    }
}

wc1c_uninstall_log("Removed $removed_indexes database indexes");

/**
 * 8. Clear scheduled events
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
 * 9. Remove rewrite rules
 */
wc1c_uninstall_log('Flushing rewrite rules');
flush_rewrite_rules();

/**
 * 10. Clear all caches
 */
wc1c_uninstall_log('Clearing caches');

// Clear WordPress object cache
wp_cache_flush();

// Clear WooCommerce caches if available
if (function_exists('wc_delete_product_transients')) {
    wc_delete_product_transients();
}

// Clear any transients created by the plugin
$wpdb->query(
    "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_wc1c_%' OR option_name LIKE '_transient_timeout_wc1c_%'"
);

/**
 * 11. Final cleanup and verification
 */
wc1c_uninstall_log('Performing final verification');

// Check if any plugin data remains
$remaining_options = $wpdb->get_var(
    "SELECT COUNT(*) FROM $wpdb->options WHERE option_name LIKE 'wc1c_%'"
);

$remaining_post_meta = $wpdb->get_var(
    "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key LIKE 'wc1c_%' OR meta_key LIKE '_wc1c_%'"
);

$remaining_term_meta = $wpdb->get_var(
    "SELECT COUNT(*) FROM $wpdb->termmeta WHERE meta_key LIKE 'wc1c_%'"
);

$remaining_user_meta = $wpdb->get_var(
    "SELECT COUNT(*) FROM $wpdb->usermeta WHERE meta_key LIKE 'wc1c_%'"
);

if ($remaining_options > 0) {
    wc1c_uninstall_log("Warning: $remaining_options plugin options still remain");
}

if ($remaining_post_meta > 0) {
    wc1c_uninstall_log("Warning: $remaining_post_meta post meta entries still remain");
}

if ($remaining_term_meta > 0) {
    wc1c_uninstall_log("Warning: $remaining_term_meta term meta entries still remain");
}

if ($remaining_user_meta > 0) {
    wc1c_uninstall_log("Warning: $remaining_user_meta user meta entries still remain");
}

/**
 * 12. Log completion
 */
$total_remaining = $remaining_options + $remaining_post_meta + $remaining_term_meta + $remaining_user_meta;

if ($total_remaining === 0) {
    wc1c_uninstall_log('✅ Plugin uninstall completed successfully - all data removed');
} else {
    wc1c_uninstall_log("⚠️ Plugin uninstall completed with $total_remaining remaining entries");
}

// Final message for CLI
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::success('WooCommerce 1C Exchange plugin has been completely uninstalled.');
} else {
    // For web-based uninstall (though this shouldn't normally be seen)
    echo "WooCommerce 1C Exchange plugin uninstalled successfully.\n";
}

/**
 * Optional: Remove products and orders created by 1C
 * Uncomment the following section if you want to remove all products and orders
 * that were imported from 1C (WARNING: This is irreversible!)
 */

/*
wc1c_uninstall_log('Removing products imported from 1C');

// Get all products with 1C GUID
$product_ids = $wpdb->get_col(
    "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wc1c_guid'"
);

if ($product_ids) {
    foreach ($product_ids as $product_id) {
        // Remove product images first
        $attachments = get_attached_media('image', $product_id);
        foreach ($attachments as $attachment) {
            wp_delete_attachment($attachment->ID, true);
        }
        
        // Remove the product
        wp_delete_post($product_id, true);
    }
    
    wc1c_uninstall_log('Removed ' . count($product_ids) . ' products imported from 1C');
}

// Get all orders with 1C GUID
$order_ids = $wpdb->get_col(
    "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wc1c_guid' AND post_id IN (SELECT ID FROM $wpdb->posts WHERE post_type = 'shop_order')"
);

if ($order_ids) {
    foreach ($order_ids as $order_id) {
        wp_delete_post($order_id, true);
    }
    
    wc1c_uninstall_log('Removed ' . count($order_ids) . ' orders from 1C');
}
*/