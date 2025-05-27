<?php

/**
 * WooCommerce 1C Integration - HPOS Compatibility Functions
 * 
 * This file provides compatibility functions for both HPOS and legacy WooCommerce installations.
 * It ensures the plugin works correctly regardless of the WooCommerce data storage method.
 * 
 * @package WooCommerce_1C
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Check if HPOS (High-Performance Order Storage) is enabled
 * 
 * @return bool True if HPOS is enabled, false otherwise
 */
function wc1c_is_hpos_enabled()
{
    if (!class_exists('WooCommerce')) {
        return false;
    }

    // Check if HPOS classes exist
    if (!class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')) {
        return false;
    }

    try {
        $controller = wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class);
        return $controller->custom_orders_table_usage_is_enabled();
    } catch (Exception $e) {
        if (function_exists('wc1c_log')) {
            wc1c_log('Error checking HPOS status: ' . $e->getMessage(), 'WARNING');
        }
        return false;
    }
}

/**
 * Get orders with HPOS compatibility
 * 
 * @param array $args Query arguments
 * @return array Array of WC_Order objects or WP_Post objects
 */
function wc1c_get_orders($args = array())
{
    // Default arguments
    $defaults = array(
        'limit' => -1,
        'status' => 'any',
        'type' => 'shop_order',
        'return' => 'objects'
    );

    $args = wp_parse_args($args, $defaults);

    if (wc1c_is_hpos_enabled()) {
        // Use HPOS method
        try {
            return wc_get_orders($args);
        } catch (Exception $e) {
            if (function_exists('wc1c_log')) {
                wc1c_log('Error getting orders via HPOS: ' . $e->getMessage(), 'ERROR');
            }
            return array();
        }
    }

    // Fallback to legacy method
    $post_args = array(
        'post_type' => $args['type'],
        'post_status' => $args['status'],
        'numberposts' => $args['limit'],
        'fields' => 'ids'
    );

    if (isset($args['meta_query'])) {
        $post_args['meta_query'] = $args['meta_query'];
    }

    if (isset($args['date_query'])) {
        $post_args['date_query'] = $args['date_query'];
    }

    $post_ids = get_posts($post_args);

    if ($args['return'] === 'objects') {
        $orders = array();
        foreach ($post_ids as $post_id) {
            $order = wc_get_order($post_id);
            if ($order) {
                $orders[] = $order;
            }
        }
        return $orders;
    }

    return $post_ids;
}

/**
 * Update order with HPOS compatibility
 * 
 * @param int $order_id Order ID
 * @param array $data Data to update
 * @return bool|int True on success, false on failure
 */
function wc1c_update_order($order_id, $data)
{
    if (!$order_id || !is_array($data)) {
        return false;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        if (function_exists('wc1c_log')) {
            wc1c_log('Order not found: ' . $order_id, 'ERROR');
        }
        return false;
    }

    try {
        foreach ($data as $key => $value) {
            switch ($key) {
                case 'status':
                    $order->set_status($value);
                    break;

                case 'customer_note':
                    $order->set_customer_note(sanitize_textarea_field($value));
                    break;

                case 'billing_first_name':
                    $order->set_billing_first_name(sanitize_text_field($value));
                    break;

                case 'billing_last_name':
                    $order->set_billing_last_name(sanitize_text_field($value));
                    break;

                case 'billing_email':
                    $order->set_billing_email(sanitize_email($value));
                    break;

                case 'billing_phone':
                    $order->set_billing_phone(sanitize_text_field($value));
                    break;

                case 'shipping_first_name':
                    $order->set_shipping_first_name(sanitize_text_field($value));
                    break;

                case 'shipping_last_name':
                    $order->set_shipping_last_name(sanitize_text_field($value));
                    break;

                case 'currency':
                    $order->set_currency($value);
                    break;

                case 'total':
                    $order->set_total($value);
                    break;

                case 'customer_id':
                    $order->set_customer_id(intval($value));
                    break;

                default:
                    // Handle custom meta data
                    $order->update_meta_data($key, $value);
                    break;
            }
        }

        $result = $order->save();

        if (function_exists('wc1c_log')) {
            wc1c_log('Updated order: ' . $order_id, 'DEBUG');
        }

        return $result;
    } catch (Exception $e) {
        if (function_exists('wc1c_log')) {
            wc1c_log('Error updating order ' . $order_id . ': ' . $e->getMessage(), 'ERROR');
        }
        return false;
    }
}

/**
 * Get order meta with HPOS compatibility
 * 
 * @param int $order_id Order ID
 * @param string $key Meta key
 * @param bool $single Return single value
 * @return mixed Meta value
 */
function wc1c_get_order_meta($order_id, $key, $single = true)
{
    if (!$order_id || !$key) {
        return false;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        // Fallback to legacy method
        return get_post_meta($order_id, $key, $single);
    }

    return $order->get_meta($key, $single);
}

/**
 * Update order meta with HPOS compatibility
 * 
 * @param int $order_id Order ID
 * @param string $key Meta key
 * @param mixed $value Meta value
 * @return bool Success status
 */
function wc1c_update_order_meta($order_id, $key, $value)
{
    if (!$order_id || !$key) {
        return false;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        // Fallback to legacy method
        return update_post_meta($order_id, $key, $value);
    }

    try {
        $order->update_meta_data($key, $value);
        return $order->save();
    } catch (Exception $e) {
        if (function_exists('wc1c_log')) {
            wc1c_log('Error updating order meta: ' . $e->getMessage(), 'ERROR');
        }
        return false;
    }
}

/**
 * Delete order meta with HPOS compatibility
 * 
 * @param int $order_id Order ID
 * @param string $key Meta key
 * @return bool Success status
 */
function wc1c_delete_order_meta($order_id, $key)
{
    if (!$order_id || !$key) {
        return false;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        // Fallback to legacy method
        return delete_post_meta($order_id, $key);
    }

    try {
        $order->delete_meta_data($key);
        return $order->save();
    } catch (Exception $e) {
        if (function_exists('wc1c_log')) {
            wc1c_log('Error deleting order meta: ' . $e->getMessage(), 'ERROR');
        }
        return false;
    }
}

/**
 * Get product meta with CRUD compatibility
 * 
 * @param int $product_id Product ID
 * @param string $key Meta key
 * @param bool $single Return single value
 * @return mixed Meta value
 */
function wc1c_get_product_meta($product_id, $key, $single = true)
{
    if (!$product_id || !$key) {
        return false;
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        return get_post_meta($product_id, $key, $single);
    }

    return $product->get_meta($key, $single);
}

/**
 * Update product meta with CRUD compatibility
 * 
 * @param int $product_id Product ID
 * @param string $key Meta key
 * @param mixed $value Meta value
 * @return bool Success status
 */
function wc1c_update_product_meta($product_id, $key, $value)
{
    if (!$product_id || !$key) {
        return false;
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        return update_post_meta($product_id, $key, $value);
    }

    try {
        $product->update_meta_data($key, $value);
        return $product->save();
    } catch (Exception $e) {
        if (function_exists('wc1c_log')) {
            wc1c_log('Error updating product meta: ' . $e->getMessage(), 'ERROR');
        }
        return false;
    }
}

/**
 * Update product stock with CRUD compatibility
 * 
 * @param int $product_id Product ID
 * @param float $quantity Stock quantity
 * @param string $stock_status Stock status
 * @return bool Success status
 */
function wc1c_update_product_stock_compat($product_id, $quantity, $stock_status = null)
{
    if (!$product_id) {
        return false;
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        if (function_exists('wc1c_log')) {
            wc1c_log('Product not found for stock update: ' . $product_id, 'ERROR');
        }
        return false;
    }

    try {
        $quantity = wc1c_parse_decimal($quantity);

        $product->set_manage_stock(WC1C_MANAGE_STOCK === 'yes');
        $product->set_stock_quantity($quantity);

        if ($stock_status) {
            $product->set_stock_status($stock_status);
        } else {
            $product->set_stock_status($quantity > 0 ? 'instock' : WC1C_OUTOFSTOCK_STATUS);
        }

        return $product->save();
    } catch (Exception $e) {
        if (function_exists('wc1c_log')) {
            wc1c_log('Error updating product stock: ' . $e->getMessage(), 'ERROR');
        }
        return false;
    }
}

/**
 * Update product price with CRUD compatibility
 * 
 * @param int $product_id Product ID
 * @param float $regular_price Regular price
 * @param float $sale_price Sale price (optional)
 * @return bool Success status
 */
function wc1c_update_product_price($product_id, $regular_price, $sale_price = null)
{
    if (!$product_id) {
        return false;
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        if (function_exists('wc1c_log')) {
            wc1c_log('Product not found for price update: ' . $product_id, 'ERROR');
        }
        return false;
    }

    try {
        $regular_price = wc1c_parse_decimal($regular_price);

        if ($regular_price < 0) {
            if (function_exists('wc1c_log')) {
                wc1c_log('Negative price not allowed for product: ' . $product_id, 'WARNING');
            }
            return false;
        }

        $product->set_regular_price($regular_price);

        if ($sale_price !== null) {
            $sale_price = wc1c_parse_decimal($sale_price);
            if ($sale_price >= 0 && $sale_price < $regular_price) {
                $product->set_sale_price($sale_price);
                $product->set_price($sale_price);
            } else {
                $product->set_sale_price('');
                $product->set_price($regular_price);
            }
        } else {
            $product->set_price($regular_price);
        }

        return $product->save();
    } catch (Exception $e) {
        if (function_exists('wc1c_log')) {
            wc1c_log('Error updating product price: ' . $e->getMessage(), 'ERROR');
        }
        return false;
    }
}

/**
 * Get product variations with HPOS compatibility
 * 
 * @param int $product_id Parent product ID
 * @return array Array of variation IDs
 */
function wc1c_get_product_variations($product_id)
{
    if (!$product_id) {
        return array();
    }

    $product = wc_get_product($product_id);
    if (!$product || !$product->is_type('variable')) {
        return array();
    }

    try {
        if (wc1c_is_hpos_enabled()) {
            // Use HPOS method
            $data_store = WC_Data_Store::load('product-variation');
            return $data_store->get_children($product_id);
        } else {
            // Legacy method
            return $product->get_children();
        }
    } catch (Exception $e) {
        if (function_exists('wc1c_log')) {
            wc1c_log('Error getting product variations: ' . $e->getMessage(), 'ERROR');
        }
        return array();
    }
}

/**
 * Create product variation with CRUD compatibility
 * 
 * @param int $parent_id Parent product ID
 * @param array $variation_data Variation data
 * @return int|false Variation ID on success, false on failure
 */
function wc1c_create_product_variation($parent_id, $variation_data = array())
{
    if (!$parent_id) {
        return false;
    }

    try {
        $variation = new WC_Product_Variation();
        $variation->set_parent_id($parent_id);

        // Set default data
        $variation->set_status('publish');
        $variation->set_catalog_visibility('visible');

        // Set variation data
        foreach ($variation_data as $key => $value) {
            switch ($key) {
                case 'sku':
                    $variation->set_sku($value);
                    break;
                case 'regular_price':
                    $variation->set_regular_price($value);
                    break;
                case 'sale_price':
                    $variation->set_sale_price($value);
                    break;
                case 'stock_quantity':
                    $variation->set_stock_quantity($value);
                    break;
                case 'stock_status':
                    $variation->set_stock_status($value);
                    break;
                case 'weight':
                    $variation->set_weight($value);
                    break;
                case 'length':
                    $variation->set_length($value);
                    break;
                case 'width':
                    $variation->set_width($value);
                    break;
                case 'height':
                    $variation->set_height($value);
                    break;
                case 'attributes':
                    $variation->set_attributes($value);
                    break;
                default:
                    $variation->update_meta_data($key, $value);
                    break;
            }
        }

        $variation_id = $variation->save();

        if (function_exists('wc1c_log')) {
            wc1c_log('Created product variation: ' . $variation_id . ' for parent: ' . $parent_id, 'DEBUG');
        }

        return $variation_id;
    } catch (Exception $e) {
        if (function_exists('wc1c_log')) {
            wc1c_log('Error creating product variation: ' . $e->getMessage(), 'ERROR');
        }
        return false;
    }
}

/**
 * Update product attributes with CRUD compatibility
 * 
 * @param int $product_id Product ID
 * @param array $attributes Attributes array
 * @return bool Success status
 */
function wc1c_update_product_attributes($product_id, $attributes)
{
    if (!$product_id || !is_array($attributes)) {
        return false;
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        return false;
    }

    try {
        $product_attributes = array();

        foreach ($attributes as $key => $attribute_data) {
            $attribute = new WC_Product_Attribute();

            if (isset($attribute_data['name'])) {
                $attribute->set_name($attribute_data['name']);
            }

            if (isset($attribute_data['options'])) {
                $attribute->set_options($attribute_data['options']);
            }

            if (isset($attribute_data['position'])) {
                $attribute->set_position($attribute_data['position']);
            }

            if (isset($attribute_data['visible'])) {
                $attribute->set_visible($attribute_data['visible']);
            }

            if (isset($attribute_data['variation'])) {
                $attribute->set_variation($attribute_data['variation']);
            }

            $product_attributes[$key] = $attribute;
        }

        $product->set_attributes($product_attributes);
        return $product->save();
    } catch (Exception $e) {
        if (function_exists('wc1c_log')) {
            wc1c_log('Error updating product attributes: ' . $e->getMessage(), 'ERROR');
        }
        return false;
    }
}

/**
 * Get order items with HPOS compatibility
 * 
 * @param int $order_id Order ID
 * @param string $type Item type (line_item, shipping, etc.)
 * @return array Order items
 */
function wc1c_get_order_items($order_id, $type = 'line_item')
{
    if (!$order_id) {
        return array();
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return array();
    }

    try {
        return $order->get_items($type);
    } catch (Exception $e) {
        if (function_exists('wc1c_log')) {
            wc1c_log('Error getting order items: ' . $e->getMessage(), 'ERROR');
        }
        return array();
    }
}

/**
 * Add item to order with HPOS compatibility
 * 
 * @param int $order_id Order ID
 * @param WC_Order_Item $item Order item
 * @return int|false Item ID on success, false on failure
 */
function wc1c_add_order_item($order_id, $item)
{
    if (!$order_id || !$item) {
        return false;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return false;
    }

    try {
        $item_id = $order->add_item($item);
        $order->save();
        return $item_id;
    } catch (Exception $e) {
        if (function_exists('wc1c_log')) {
            wc1c_log('Error adding order item: ' . $e->getMessage(), 'ERROR');
        }
        return false;
    }
}

/**
 * Remove order items with HPOS compatibility
 * 
 * @param int $order_id Order ID
 * @param string $type Item type
 * @return bool Success status
 */
function wc1c_remove_order_items($order_id, $type = 'line_item')
{
    if (!$order_id) {
        return false;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return false;
    }

    try {
        $order->remove_order_items($type);
        return $order->save();
    } catch (Exception $e) {
        if (function_exists('wc1c_log')) {
            wc1c_log('Error removing order items: ' . $e->getMessage(), 'ERROR');
        }
        return false;
    }
}

/**
 * Check if function exists and is callable
 * 
 * @param string $function_name Function name
 * @return bool True if function exists and is callable
 */
function wc1c_function_exists($function_name)
{
    return function_exists($function_name) && is_callable($function_name);
}

/**
 * Safe call to WooCommerce function with fallback
 * 
 * @param string $function_name Function name
 * @param array $args Function arguments
 * @param mixed $fallback Fallback value
 * @return mixed Function result or fallback
 */
function wc1c_safe_call($function_name, $args = array(), $fallback = null)
{
    if (wc1c_function_exists($function_name)) {
        try {
            return call_user_func_array($function_name, $args);
        } catch (Exception $e) {
            if (function_exists('wc1c_log')) {
                wc1c_log('Error calling function ' . $function_name . ': ' . $e->getMessage(), 'ERROR');
            }
            return $fallback;
        }
    }

    return $fallback;
}

/**
 * Get WooCommerce version with caching
 * 
 * @return string WooCommerce version
 */
function wc1c_get_wc_version()
{
    static $version = null;

    if ($version === null) {
        if (defined('WC_VERSION')) {
            $version = WC_VERSION;
        } elseif (function_exists('WC')) {
            $version = WC()->version;
        } else {
            $version = '0.0.0';
        }
    }

    return $version;
}

/**
 * Check if WooCommerce version is at least the specified version
 * 
 * @param string $min_version Minimum version to check
 * @return bool True if current version is >= min_version
 */
function wc1c_is_wc_version_gte($min_version)
{
    return version_compare(wc1c_get_wc_version(), $min_version, '>=');
}

/**
 * Get product type with fallback
 * 
 * @param int|WC_Product $product Product ID or object
 * @return string Product type
 */
function wc1c_get_product_type($product)
{
    if (is_numeric($product)) {
        $product = wc_get_product($product);
    }

    if (!$product || !is_a($product, 'WC_Product')) {
        return 'simple';
    }

    return $product->get_type();
}

/**
 * Set product type with CRUD compatibility
 * 
 * @param int|WC_Product $product Product ID or object
 * @param string $type Product type
 * @return bool Success status
 */
function wc1c_set_product_type($product, $type)
{
    if (is_numeric($product)) {
        $product = wc_get_product($product);
    }

    if (!$product || !is_a($product, 'WC_Product')) {
        return false;
    }

    try {
        // For variable products, we need to set the type via terms
        if ($type === 'variable') {
            wp_set_object_terms($product->get_id(), 'variable', 'product_type');
        } else {
            wp_set_object_terms($product->get_id(), $type, 'product_type');
        }

        return true;
    } catch (Exception $e) {
        if (function_exists('wc1c_log')) {
            wc1c_log('Error setting product type: ' . $e->getMessage(), 'ERROR');
        }
        return false;
    }
}

/**
 * Get term meta with compatibility
 * 
 * @param int $term_id Term ID
 * @param string $key Meta key
 * @param bool $single Return single value
 * @return mixed Meta value
 */
function wc1c_get_term_meta($term_id, $key, $single = true)
{
    if (!$term_id || !$key) {
        return false;
    }

    if (function_exists('get_term_meta')) {
        return get_term_meta($term_id, $key, $single);
    }

    // Fallback for older WordPress versions
    if (function_exists('get_woocommerce_term_meta')) {
        return get_woocommerce_term_meta($term_id, $key, $single);
    }

    return false;
}

/**
 * Update term meta with compatibility
 * 
 * @param int $term_id Term ID
 * @param string $key Meta key
 * @param mixed $value Meta value
 * @return bool Success status
 */
function wc1c_update_term_meta($term_id, $key, $value)
{
    if (!$term_id || !$key) {
        return false;
    }

    if (function_exists('update_term_meta')) {
        return update_term_meta($term_id, $key, $value);
    }

    // Fallback for older WordPress versions
    if (function_exists('update_woocommerce_term_meta')) {
        return update_woocommerce_term_meta($term_id, $key, $value);
    }

    return false;
}

/**
 * Delete term meta with compatibility
 * 
 * @param int $term_id Term ID
 * @param string $key Meta key
 * @return bool Success status
 */
function wc1c_delete_term_meta($term_id, $key)
{
    if (!$term_id || !$key) {
        return false;
    }

    if (function_exists('delete_term_meta')) {
        return delete_term_meta($term_id, $key);
    }

    // Fallback for older WordPress versions
    if (function_exists('delete_woocommerce_term_meta')) {
        return delete_woocommerce_term_meta($term_id, $key);
    }

    return false;
}

/**
 * Get order status list with compatibility
 * 
 * @return array Order statuses
 */
function wc1c_get_order_statuses()
{
    if (function_exists('wc_get_order_statuses')) {
        return wc_get_order_statuses();
    }

    // Fallback for older WooCommerce versions
    return array(
        'wc-pending'    => __('Pending payment', 'woocommerce'),
        'wc-processing' => __('Processing', 'woocommerce'),
        'wc-on-hold'    => __('On hold', 'woocommerce'),
        'wc-completed'  => __('Completed', 'woocommerce'),
        'wc-cancelled'  => __('Cancelled', 'woocommerce'),
        'wc-refunded'   => __('Refunded', 'woocommerce'),
        'wc-failed'     => __('Failed', 'woocommerce'),
    );
}

/**
 * Get currency list with compatibility
 * 
 * @return array Currency list
 */
function wc1c_get_currencies()
{
    if (function_exists('get_woocommerce_currencies')) {
        return get_woocommerce_currencies();
    }

    // Basic fallback
    return array(
        'USD' => 'US Dollar',
        'EUR' => 'Euro',
        'RUB' => 'Russian Ruble',
        'UAH' => 'Ukrainian Hryvnia',
    );
}

/**
 * Format price with compatibility
 * 
 * @param float $price Price to format
 * @param array $args Formatting arguments
 * @return string Formatted price
 */
function wc1c_price($price, $args = array())
{
    if (function_exists('wc_price')) {
        return wc_price($price, $args);
    }

    // Basic fallback
    $currency = get_woocommerce_currency();
    $symbol = get_woocommerce_currency_symbol($currency);

    return $symbol . number_format($price, 2);
}

/**
 * Clean product cache
 * 
 * @param int $product_id Product ID
 */
function wc1c_clean_product_cache($product_id)
{
    if (!$product_id) {
        return;
    }

    // Clean WordPress cache
    wp_cache_delete($product_id, 'posts');
    wp_cache_delete($product_id, 'post_meta');

    // Clean WooCommerce cache
    if (function_exists('wc_delete_product_transients')) {
        wc_delete_product_transients($product_id);
    }

    // Clean object cache
    wp_cache_delete('wc_product_' . $product_id);
    wp_cache_delete('wc_var_prices_' . $product_id);

    // Clear lookup table cache if HPOS is enabled
    if (wc1c_is_hpos_enabled() && function_exists('wc_update_product_lookup_tables')) {
        wc_update_product_lookup_tables($product_id);
    }
}

/**
 * Clean order cache
 * 
 * @param int $order_id Order ID
 */
function wc1c_clean_order_cache($order_id)
{
    if (!$order_id) {
        return;
    }

    // Clean WordPress cache
    wp_cache_delete($order_id, 'posts');
    wp_cache_delete($order_id, 'post_meta');

    // Clean WooCommerce cache
    wp_cache_delete('order-items-' . $order_id, 'orders');
    wp_cache_delete('order-' . $order_id, 'orders');

    // Clean HPOS cache if enabled
    if (wc1c_is_hpos_enabled()) {
        wp_cache_delete('wc_order_' . $order_id);
    }
}

/**
 * Batch clean cache for multiple items
 * 
 * @param array $ids Array of IDs
 * @param string $type Type of items (product, order)
 */
function wc1c_batch_clean_cache($ids, $type = 'product')
{
    if (!is_array($ids) || empty($ids)) {
        return;
    }

    foreach ($ids as $id) {
        switch ($type) {
            case 'product':
                wc1c_clean_product_cache($id);
                break;
            case 'order':
                wc1c_clean_order_cache($id);
                break;
        }
    }

    // Force garbage collection after batch cleaning
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
}

/**
 * Get attachment ID by URL with caching
 * 
 * @param string $url Attachment URL
 * @return int|false Attachment ID or false
 */
function wc1c_get_attachment_id_by_url($url)
{
    if (!$url) {
        return false;
    }

    static $cache = array();

    if (isset($cache[$url])) {
        return $cache[$url];
    }

    $attachment_id = attachment_url_to_postid($url);
    $cache[$url] = $attachment_id;

    return $attachment_id;
}

/**
 * Create product image attachment
 * 
 * @param string $image_url Image URL
 * @param int $product_id Product ID
 * @param string $desc Image description
 * @return int|false Attachment ID or false
 */
function wc1c_create_image_attachment($image_url, $product_id, $desc = '')
{
    if (!$image_url || !$product_id) {
        return false;
    }

    // Check if attachment already exists
    $attachment_id = wc1c_get_attachment_id_by_url($image_url);
    if ($attachment_id) {
        return $attachment_id;
    }

    try {
        // Download image
        $tmp = download_url($image_url);
        if (is_wp_error($tmp)) {
            return false;
        }

        // Prepare file array
        $file_array = array(
            'name' => basename($image_url),
            'tmp_name' => $tmp
        );

        // Create attachment
        $attachment_id = media_handle_sideload($file_array, $product_id, $desc);

        // Clean up temp file
        @unlink($tmp);

        if (is_wp_error($attachment_id)) {
            return false;
        }

        return $attachment_id;
    } catch (Exception $e) {
        if (function_exists('wc1c_log')) {
            wc1c_log('Error creating image attachment: ' . $e->getMessage(), 'ERROR');
        }
        return false;
    }
}

/**
 * Set product gallery images
 * 
 * @param int $product_id Product ID
 * @param array $image_ids Array of attachment IDs
 * @return bool Success status
 */
function wc1c_set_product_gallery($product_id, $image_ids)
{
    if (!$product_id || !is_array($image_ids)) {
        return false;
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        return false;
    }

    try {
        // Set featured image (first image)
        if (!empty($image_ids)) {
            $featured_image = array_shift($image_ids);
            $product->set_image_id($featured_image);
        }

        // Set gallery images (remaining images)
        $product->set_gallery_image_ids($image_ids);

        return $product->save();
    } catch (Exception $e) {
        if (function_exists('wc1c_log')) {
            wc1c_log('Error setting product gallery: ' . $e->getMessage(), 'ERROR');
        }
        return false;
    }
}

/**
 * Validate product data
 * 
 * @param array $product_data Product data
 * @return array Validation errors
 */
function wc1c_validate_product_data($product_data)
{
    $errors = array();

    // Check required fields
    if (empty($product_data['name'])) {
        $errors[] = 'Product name is required';
    }

    // Validate price
    if (isset($product_data['regular_price'])) {
        $price = wc1c_parse_decimal($product_data['regular_price']);
        if ($price < 0) {
            $errors[] = 'Price cannot be negative';
        }
    }

    // Validate stock quantity
    if (isset($product_data['stock_quantity'])) {
        $stock = wc1c_parse_decimal($product_data['stock_quantity']);
        if ($stock < 0) {
            $errors[] = 'Stock quantity cannot be negative';
        }
    }

    // Validate SKU uniqueness
    if (isset($product_data['sku']) && !empty($product_data['sku'])) {
        $existing_id = wc_get_product_id_by_sku($product_data['sku']);
        if ($existing_id && (!isset($product_data['id']) || $existing_id != $product_data['id'])) {
            $errors[] = 'SKU already exists';
        }
    }

    return $errors;
}

/**
 * Validate order data
 * 
 * @param array $order_data Order data
 * @return array Validation errors
 */
function wc1c_validate_order_data($order_data)
{
    $errors = array();

    // Validate status
    if (isset($order_data['status'])) {
        $valid_statuses = array_keys(wc1c_get_order_statuses());
        $status = 'wc-' . ltrim($order_data['status'], 'wc-');

        if (!in_array($status, $valid_statuses)) {
            $errors[] = 'Invalid order status';
        }
    }

    // Validate email
    if (isset($order_data['billing_email']) && !is_email($order_data['billing_email'])) {
        $errors[] = 'Invalid email address';
    }

    // Validate total
    if (isset($order_data['total'])) {
        $total = wc1c_parse_decimal($order_data['total']);
        if ($total < 0) {
            $errors[] = 'Order total cannot be negative';
        }
    }

    return $errors;
}

/**
 * Get system info for debugging
 * 
 * @return array System information
 */
function wc1c_get_system_info()
{
    return array(
        'wp_version' => get_bloginfo('version'),
        'wc_version' => wc1c_get_wc_version(),
        'php_version' => PHP_VERSION,
        'hpos_enabled' => wc1c_is_hpos_enabled(),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'post_max_size' => ini_get('post_max_size'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'mysql_version' => $GLOBALS['wpdb']->db_version(),
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    );
}

/**
 * Check system requirements
 * 
 * @return array Array of requirement check results
 */
function wc1c_check_system_requirements()
{
    $requirements = array();

    // PHP version
    $requirements['php_version'] = array(
        'required' => '7.4',
        'current' => PHP_VERSION,
        'status' => version_compare(PHP_VERSION, '7.4', '>=')
    );

    // WordPress version
    $requirements['wp_version'] = array(
        'required' => '5.0',
        'current' => get_bloginfo('version'),
        'status' => version_compare(get_bloginfo('version'), '5.0', '>=')
    );

    // WooCommerce version
    $requirements['wc_version'] = array(
        'required' => '5.0',
        'current' => wc1c_get_wc_version(),
        'status' => version_compare(wc1c_get_wc_version(), '5.0', '>=')
    );

    // Required PHP extensions
    $required_extensions = array('xml', 'mbstring', 'curl', 'zip');
    foreach ($required_extensions as $extension) {
        $requirements['ext_' . $extension] = array(
            'required' => true,
            'current' => extension_loaded($extension),
            'status' => extension_loaded($extension)
        );
    }

    // Memory limit
    $memory_limit = ini_get('memory_limit');
    $memory_bytes = wc1c_filesize_to_bytes($memory_limit);
    $requirements['memory_limit'] = array(
        'required' => '256M',
        'current' => $memory_limit,
        'status' => $memory_bytes >= wc1c_filesize_to_bytes('256M')
    );

    return $requirements;
}

/**
 * Convert filesize to bytes (duplicate from exchange.php for compatibility)
 * 
 * @param string $filesize Filesize string
 * @return int Bytes
 */
if (!function_exists('wc1c_filesize_to_bytes')) {
    function wc1c_filesize_to_bytes($filesize)
    {
        switch (substr($filesize, -1)) {
            case 'G':
            case 'g':
                return (int) $filesize * 1073741824;
            case 'M':
            case 'm':
                return (int) $filesize * 1048576;
            case 'K':
            case 'k':
                return (int) $filesize * 1024;
            default:
                return (int) $filesize;
        }
    }
}

/**
 * Parse decimal number (duplicate from main plugin for compatibility)
 * 
 * @param string $number Number string
 * @return float Parsed number
 */
if (!function_exists('wc1c_parse_decimal')) {
    function wc1c_parse_decimal($number)
    {
        $number = str_replace(array(',', ' '), array('.', ''), $number);
        return (float) $number;
    }
}

/**
 * Initialize compatibility layer
 */
function wc1c_init_compatibility()
{
    // Log compatibility status
    if (function_exists('wc1c_log')) {
        $hpos_status = wc1c_is_hpos_enabled() ? 'enabled' : 'disabled';
        wc1c_log('Compatibility layer initialized. HPOS: ' . $hpos_status, 'INFO');
    }

    // Set up compatibility hooks
    add_action('woocommerce_init', 'wc1c_setup_compatibility_hooks');
}

/**
 * Setup compatibility hooks
 */
function wc1c_setup_compatibility_hooks()
{
    // Hook into WooCommerce events for cache clearing
    add_action('woocommerce_update_product', 'wc1c_clean_product_cache');
    add_action('woocommerce_new_product', 'wc1c_clean_product_cache');
    add_action('woocommerce_update_order', 'wc1c_clean_order_cache');
    add_action('woocommerce_new_order', 'wc1c_clean_order_cache');

    // Add compatibility filters
    add_filter('wc1c_product_data_validation', 'wc1c_validate_product_data');
    add_filter('wc1c_order_data_validation', 'wc1c_validate_order_data');
}

// Initialize compatibility layer
add_action('init', 'wc1c_init_compatibility', 5);
