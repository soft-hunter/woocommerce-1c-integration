<?php
/**
 * HPOS-compatible Order Mapper
 *
 * @package    WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/exchange/mappers
 * @author     Igor Melnyk <igor.melnyk.it@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * HPOS-compatible Order Mapper class
 */
class WC1C_HPOS_Order_Mapper extends WC1C_Base_Mapper {

    /**
     * Check if HPOS is enabled
     *
     * @return bool
     */
    private function is_hpos_enabled() {
        return class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && 
               \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }

    /**
     * Get orders using HPOS-compatible method
     *
     * @param array $args Query arguments
     * @return array
     */
    public function get_orders($args = array()) {
        if ($this->is_hpos_enabled()) {
            return $this->get_orders_hpos($args);
        } else {
            return $this->get_orders_legacy($args);
        }
    }

    /**
     * Get orders using HPOS
     *
     * @param array $args Query arguments
     * @return array
     */
    private function get_orders_hpos($args = array()) {
        $default_args = array(
            'status' => array_keys(wc_get_order_statuses()),
            'limit' => -1,
            'meta_query' => array(
                array(
                    'key' => 'wc1c_queried',
                    'compare' => 'NOT EXISTS',
                ),
            ),
        );

        $args = wp_parse_args($args, $default_args);
        return wc_get_orders($args);
    }

    /**
     * Get orders using legacy method
     *
     * @param array $args Query arguments
     * @return array
     */
    private function get_orders_legacy($args = array()) {
        $order_statuses = array_keys(wc_get_order_statuses());
        
        $posts = get_posts(array(
            'post_type' => 'shop_order',
            'post_status' => $order_statuses,
            'numberposts' => -1,
            'meta_query' => array(
                array(
                    'key' => 'wc1c_queried',
                    'compare' => 'NOT EXISTS',
                ),
            ),
        ));

        $orders = array();
        foreach ($posts as $post) {
            $order = wc_get_order($post->ID);
            if ($order) {
                $orders[] = $order;
            }
        }

        return $orders;
    }

    /**
     * Create order using HPOS-compatible method
     *
     * @param array $order_data Order data from 1C
     * @return WC_Order|false
     */
    public function create_order($order_data) {
        try {
            $order = new WC_Order();
            
            if (isset($order_data['status'])) {
                $order->set_status($order_data['status']);
            }
            
            if (isset($order_data['customer_note'])) {
                $order->set_customer_note(sanitize_textarea_field($order_data['customer_note']));
            }
            
            if (isset($order_data['customer_id'])) {
                $order->set_customer_id($order_data['customer_id']);
            }

            $order_id = $order->save();
            
            if (isset($order_data['wc1c_guid'])) {
                $order->update_meta_data('_wc1c_guid', $order_data['wc1c_guid']);
                $order->save_meta_data();
            }

            return $order;

        } catch (Exception $e) {
            if (class_exists('WC1C_Logger')) {
                WC1C_Logger::log("Failed to create order: " . $e->getMessage(), 'error');
            }
            return false;
        }
    }

    /**
     * Update order using HPOS-compatible method
     *
     * @param int   $order_id Order ID
     * @param array $order_data Order data from 1C
     * @return bool
     */
    public function update_order($order_id, $order_data) {
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                return false;
            }

            if (isset($order_data['status'])) {
                $order->set_status($order_data['status']);
            }
            
            if (isset($order_data['customer_note'])) {
                $order->set_customer_note(sanitize_textarea_field($order_data['customer_note']));
            }

            $order->save();
            return true;

        } catch (Exception $e) {
            if (class_exists('WC1C_Logger')) {
                WC1C_Logger::log("Failed to update order: " . $e->getMessage(), 'error');
            }
            return false;
        }
    }
}