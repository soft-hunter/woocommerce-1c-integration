<?php
if (!defined('ABSPATH')) exit;

function wc1c_admin_init() {
  global $wc_product_attributes;

  if (!isset($wc_product_attributes)) return;

  $taxonomies = array_merge(array('product_cat'), array_keys($wc_product_attributes));
  foreach ($taxonomies as $taxonomy) {
    add_filter("manage_edit-{$taxonomy}_columns", 'wc1c_manage_edit_taxonomy_columns');
    add_filter("manage_{$taxonomy}_custom_column", 'wc1c_manage_taxonomy_custom_column', 10, 3);
  }
}
add_action('init', 'wc1c_admin_init');

function wc1c_manage_edit_taxonomy_columns($columns) {
  $columns_after = array(
    'wc1c_guid' => __("1C Id", 'woocommerce-1c-integration'),
  );

  return array_merge($columns, $columns_after);
}

function wc1c_manage_taxonomy_custom_column($columns, $column, $id) {
  if ($column == 'wc1c_guid') {
    $guid = get_term_meta($id, 'wc1c_guid', true);
    if ($guid && strpos($guid, '::') !== false) {
      list($taxonomy, $guid) = explode('::', $guid, 2);
    }
    $columns .= $guid ? "<small>" . esc_html($guid) . "</small>" : '<span class="na">–</span>';
  }

  return $columns;
}

function wc1c_woocommerce_attribute_taxonomy_compare($a, $b) {
  foreach (array('a', 'b') as $arg) {
    $$arg = property_exists($$arg, 'wc1c_order') ? $$arg->wc1c_order : 1000 + $$arg->attribute_id;
  }

  if ($a == $b) return 0;
  return $a < $b ? -1 : 1;
}

function wc1c_woocommerce_attribute_taxonomies($attribute_taxonomies) {
  if (is_admin() && @$_GET['page'] == 'product_attributes') {
    $guids = get_option('wc1c_guid_attributes', array());
    $attribute_ids = array_values($guids);

    foreach ($attribute_taxonomies as $attribute_taxonomy) {
      $guid = array_search($attribute_taxonomy->attribute_id, $guids);
      if ($guid !== false) $attribute_taxonomy->attribute_label .= sprintf(" [%s: %s]", __("1C Id", 'woocommerce-1c-integration'), $guid);
    }
  }

  $orders = get_option('wc1c_order_attributes', array());
  foreach ($attribute_taxonomies as $attribute_taxonomy) {
    $order = array_search($attribute_taxonomy->attribute_id, $orders);
    if ($order !== false) $attribute_taxonomy->wc1c_order = $order;
  }
  usort($attribute_taxonomies, 'wc1c_woocommerce_attribute_taxonomy_compare');

  return $attribute_taxonomies;
}
add_filter('woocommerce_attribute_taxonomies', 'wc1c_woocommerce_attribute_taxonomies');

function wc1c_manage_edit_product_columns($columns) {
  $columns_after = array(
    'wc1c_guid' => __("1C Id", 'woocommerce-1c-integration'),
  );

  return array_merge($columns, $columns_after);
}
add_filter('manage_edit-product_columns', 'wc1c_manage_edit_product_columns');

function wc1c_manage_product_posts_custom_column($column) {
  global $post;

  if ($column == 'wc1c_guid') {
    $guid = get_post_meta($post->ID, '_wc1c_guid', true);
    echo $guid ? "<small>" . esc_html($guid) . "</small>" : '<span class="na">–</span>';
  }
}
add_action('manage_product_posts_custom_column', 'wc1c_manage_product_posts_custom_column');

function wc1c_manage_edit_shop_order_columns($columns) {
  $columns_after = array(
    'wc1c_guid' => __("1C Id", 'woocommerce-1c-integration'),
  );

  return array_merge($columns, $columns_after);
}
add_filter('manage_edit-shop_order_columns', 'wc1c_manage_edit_shop_order_columns');

function wc1c_manage_shop_order_posts_custom_column($column) {
  global $post;

  if ($column == 'wc1c_guid') {
    $guid = get_post_meta($post->ID, '_wc1c_guid', true);
    echo $guid ? "<small>" . esc_html($guid) . "</small>" : '<span class="na">–</span>';
  }
}
add_action('manage_shop_order_posts_custom_column', 'wc1c_manage_shop_order_posts_custom_column');

function wc1c_woocommerce_attribute_deleted($attribute_id, $attribute_name, $taxonomy) {
  $guids = get_option('wc1c_guid_attributes', array());
  $guid = array_search($attribute_id, $guids);
  if ($guid === false) return;

  if (isset($guids[$guid])) {
    unset($guids[$guid]);
    update_option('wc1c_guid_attributes', $guids);
  }

  $timestamps = get_option('wc1c_timestamp_attributes', array());
  if (isset($timestamps[$guid])) {
    unset($timestamps[$guid]);
    update_option('wc1c_timestamp_attributes', $timestamps);
  }

  $orders = get_option('wc1c_order_attributes', array());
  $order_index = array_search($attribute_id, $orders);
  if ($order_index !== false) {
    unset($orders[$order_index]);
    update_option('wc1c_order_attributes', $orders);
  }
}
add_action('woocommerce_attribute_deleted', 'wc1c_woocommerce_attribute_deleted', 10, 3);



//add_action('admin_menu', 'wc1c_admin_menu');

function wc1c_admin_menu_page_settings() {
  echo "TODO";
}

function wc1c_admin_menu_page_todo() {
  echo "TODO";
}
