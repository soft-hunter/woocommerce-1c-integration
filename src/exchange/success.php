<?php
if (!defined('ABSPATH')) exit;

// Check if HPOS is enabled
$hpos_enabled = wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled();

if ($hpos_enabled) {
  // Use HPOS query
  $order_query = new \WC_Order_Query(array(
    'limit' => -1,
    'status' => array_keys(wc_get_order_statuses()),
    'meta_query' => array(
      array(
        'key' => 'wc1c_querying',
        'value' => 1,
      ),
      array(
        'key' => 'wc1c_queried',
        'compare' => 'NOT EXISTS',
      ),
    ),
  ));
  $orders = $order_query->get_orders();
  
  foreach ($orders as $order) {
    $order->update_meta_data('wc1c_queried', 1);
    $order->save();
  }
} else {
  // Use legacy post query
  $order_statuses = array_keys(wc_get_order_statuses());
  $order_posts = get_posts(array(
    'post_type' => 'shop_order',
    'post_status' => $order_statuses,
    'numberposts' => -1,
    'meta_query' => array(
      array(
        'key' => 'wc1c_querying',
        'value' => 1,
      ),
      array(
        'key' => 'wc1c_queried',
        'compare' => "NOT EXISTS",
      ),
    ),
  ));

  foreach ($order_posts as $order_post) {
    update_post_meta($order_post->ID, 'wc1c_queried', 1);
  }
}
