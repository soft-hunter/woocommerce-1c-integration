<?php

/**
 * WooCommerce 1C Integration - Offers Exchange
 * HPOS Compatible Version with Enhanced Error Handling
 */

if (!defined('ABSPATH')) exit;

// Load compatibility functions
if (!function_exists('wc1c_is_hpos_enabled')) {
  require_once WC1C_PLUGIN_DIR . 'compat.php';
}

if (!defined('WC1C_PRICE_TYPE')) define('WC1C_PRICE_TYPE', null);
if (!defined('WC1C_PRESERVE_PRODUCT_VARIATIONS')) define('WC1C_PRESERVE_PRODUCT_VARIATIONS', false);

function wc1c_offers_start_element_handler($is_full, $names, $depth, $name, $attrs)
{
  global $wc1c_price_types, $wc1c_offer, $wc1c_price;

  if (@$names[$depth - 1] == 'ПакетПредложений' && $name == 'ТипыЦен') {
    $wc1c_price_types = array();
  } elseif (@$names[$depth - 1] == 'ТипыЦен' && $name == 'ТипЦены') {
    $wc1c_price_types[] = array();
  } elseif (@$names[$depth - 1] == 'Предложение' && $name == 'Склад') {
    if (isset($attrs['КоличествоНаСкладе'])) {
      @$wc1c_offer['КоличествоНаСкладе'] += floatval($attrs['КоличествоНаСкладе']);
    }
  } elseif (@$names[$depth - 1] == 'Предложения' && $name == 'Предложение') {
    $wc1c_offer = array(
      'ХарактеристикиТовара' => array(),
    );
  } elseif (@$names[$depth - 1] == 'ХарактеристикиТовара' && $name == 'ХарактеристикаТовара') {
    $wc1c_offer['ХарактеристикиТовара'][] = array();
  } elseif (@$names[$depth - 1] == 'Цены' && $name == 'Цена') {
    $wc1c_price = array();
  }
}

function wc1c_offers_character_data_handler($is_full, $names, $depth, $name, $data)
{
  global $wc1c_price_types, $wc1c_offer, $wc1c_price;

  if (@$names[$depth - 2] == 'ТипыЦен' && @$names[$depth - 1] == 'ТипЦены' && $name != 'Налог') {
    $i = count($wc1c_price_types) - 1;
    @$wc1c_price_types[$i][$name] .= $data;
  } elseif (@$names[$depth - 2] == 'Предложения' && @$names[$depth - 1] == 'Предложение' && !in_array($name, array('БазоваяЕдиница', 'ХарактеристикиТовара', 'Цены'))) {
    @$wc1c_offer[$name] .= $data;
  } elseif (@$names[$depth - 2] == 'ХарактеристикиТовара' && @$names[$depth - 1] == 'ХарактеристикаТовара') {
    $i = count($wc1c_offer['ХарактеристикиТовара']) - 1;
    @$wc1c_offer['ХарактеристикиТовара'][$i][$name] .= $data;
  } elseif (@$names[$depth - 2] == 'Цены' && @$names[$depth - 1] == 'Цена') {
    @$wc1c_price[$name] .= $data;
  }
}

function wc1c_offers_end_element_handler($is_full, $names, $depth, $name)
{
  global $wpdb, $wc1c_price_types, $wc1c_price_type, $wc1c_offer, $wc1c_suboffers, $wc1c_price;

  if (@$names[$depth - 1] == 'ПакетПредложений' && $name == 'ТипыЦен') {
    if (!WC1C_PRICE_TYPE) {
      $wc1c_price_type = isset($wc1c_price_types[0]) ? $wc1c_price_types[0] : array();
    } else {
      $wc1c_price_type = null;
      foreach ($wc1c_price_types as $price_type) {
        if (@$price_type['Ид'] == WC1C_PRICE_TYPE || @$price_type['Наименование'] == WC1C_PRICE_TYPE) {
          $wc1c_price_type = $price_type;
          break;
        }
      }
      if (!$wc1c_price_type) {
        wc1c_error("Failed to match price type: " . WC1C_PRICE_TYPE);
      }
    }

    if (!empty($wc1c_price_type['Валюта'])) {
      wc1c_update_currency($wc1c_price_type['Валюта']);
      update_option('wc1c_currency', $wc1c_price_type['Валюта']);
    }
  } elseif (@$names[$depth - 1] == 'Цены' && $name == 'Цена') {
    if (!isset($wc1c_offer['Цена']) && (!isset($wc1c_price['ИдТипаЦены']) || @$wc1c_price['ИдТипаЦены'] == @$wc1c_price_type['Ид'])) {
      $wc1c_offer['Цена'] = $wc1c_price;
    } else {
      $price_type_id = isset($wc1c_price['ИдТипаЦены']) ? $wc1c_price['ИдТипаЦены'] : 'default';
      $wc1c_offer["Цена_{$price_type_id}"] = $wc1c_price;
    }
  } elseif (@$names[$depth - 1] == 'ХарактеристикаТовара' && $name == 'Наименование') {
    $i = count($wc1c_offer['ХарактеристикиТовара']) - 1;
    if ($i >= 0 && isset($wc1c_offer['ХарактеристикиТовара'][$i]['Наименование'])) {
      $wc1c_offer['ХарактеристикиТовара'][$i]['Наименование'] = preg_replace("/\s+\(.*\)$/", '', $wc1c_offer['ХарактеристикиТовара'][$i]['Наименование']);
    }
  } elseif (@$names[$depth - 1] == 'Предложения' && $name == 'Предложение') {
    if (!isset($wc1c_offer['Ид'])) {
      if (function_exists('wc1c_log')) {
        wc1c_log('Offer missing ID, skipping', 'WARNING');
      }
      return;
    }

    if (strpos($wc1c_offer['Ид'], '#') === false || WC1C_DISABLE_VARIATIONS) {
      // Simple product or variations disabled
      $guid = $wc1c_offer['Ид'];
      try {
        $_post_id = wc1c_replace_offer($is_full, $guid, $wc1c_offer);
        if ($_post_id) {
          $_product = wc_get_product($_post_id);
          if ($_product) {
            $_qnty = $_product->get_stock_quantity();
            if (!$_qnty) {
              $_product->set_stock_status(WC1C_OUTOFSTOCK_STATUS);
              $_product->save();
            }
          }
        }
      } catch (Exception $e) {
        if (function_exists('wc1c_log')) {
          wc1c_log('Error processing simple offer: ' . $e->getMessage(), 'ERROR');
        }
      }
    } else {
      // Variable product
      $guid = $wc1c_offer['Ид'];
      list($product_guid,) = explode('#', $guid, 2);

      if (empty($wc1c_suboffers) || $wc1c_suboffers[0]['product_guid'] != $product_guid) {
        if ($wc1c_suboffers) {
          wc1c_replace_suboffers($is_full, $wc1c_suboffers);
        }
        $wc1c_suboffers = array();
      }

      $wc1c_suboffers[] = array(
        'guid' => $wc1c_offer['Ид'],
        'product_guid' => $product_guid,
        'offer' => $wc1c_offer,
      );
    }
  } elseif (@$names[$depth - 1] == 'ПакетПредложений' && $name == 'Предложения') {
    if ($wc1c_suboffers) {
      wc1c_replace_suboffers($is_full, $wc1c_suboffers);
    }
  } elseif (!$depth && $name == 'КоммерческаяИнформация') {
    // Clean transients with HPOS compatibility
    $wpdb->query($wpdb->prepare("DELETE FROM $wpdb->options WHERE option_name LIKE %s", '_transient_%'));
    wc1c_check_wpdb_error();

    do_action('wc1c_post_offers', $is_full);

    if (function_exists('wc1c_log')) {
      wc1c_log('Offers import completed', 'INFO');
    }
  }
}

/**
 * Update currency with validation
 */
function wc1c_update_currency($currency)
{
  if (!$currency || !array_key_exists($currency, get_woocommerce_currencies())) {
    if (function_exists('wc1c_log')) {
      wc1c_log('Invalid currency: ' . $currency, 'WARNING');
    }
    return;
  }

  update_option('woocommerce_currency', $currency);

  $currency_position = array(
    'RUB' => 'right_space',
    'UAH' => 'right_space',
    'USD' => 'left',
    'EUR' => 'left',
  );

  if (isset($currency_position[$currency])) {
    update_option('woocommerce_currency_pos', $currency_position[$currency]);
  }

  if (function_exists('wc1c_log')) {
    wc1c_log('Currency updated to: ' . $currency, 'INFO');
  }
}

/**
 * Replace offer post meta with HPOS compatibility
 */
function wc1c_replace_offer_post_meta($is_full, $post_id, $offer, $attributes = array())
{
  if (!$post_id) {
    if (function_exists('wc1c_log')) {
      wc1c_log('Invalid post ID in wc1c_replace_offer_post_meta', 'ERROR');
    }
    return;
  }

  // Get product using HPOS compatible method
  $product = wc_get_product($post_id);
  if (!$product) {
    if (function_exists('wc1c_log')) {
      wc1c_log('Failed to get product: ' . $post_id, 'ERROR');
    }
    return;
  }

  try {
    // Handle pricing
    $price = isset($offer['Цена']['ЦенаЗаЕдиницу']) ? wc1c_parse_decimal($offer['Цена']['ЦенаЗаЕдиницу']) : null;
    if (!is_null($price)) {
      $coefficient = isset($offer['Цена']['Коэффициент']) ? wc1c_parse_decimal($offer['Цена']['Коэффициент']) : null;
      if (!is_null($coefficient) && $coefficient != 0) {
        $price *= $coefficient;
      }

      // Use CRUD API for pricing
      $product->set_regular_price($price);
      $product->set_manage_stock(WC1C_MANAGE_STOCK === 'yes');

      // Handle sale price logic
      $sale_price = $product->get_sale_price();
      $sale_price_from = $product->get_date_on_sale_from();
      $sale_price_to = $product->get_date_on_sale_to();

      if (empty($sale_price)) {
        $product->set_price($price);
      } else {
        $now = time();
        $sale_active = true;

        if ($sale_price_from && $sale_price_from->getTimestamp() > $now) {
          $sale_active = false;
        }
        if ($sale_price_to && $sale_price_to->getTimestamp() < $now) {
          $sale_active = false;
          // Clear expired sale
          $product->set_sale_price('');
          $product->set_date_on_sale_from('');
          $product->set_date_on_sale_to('');
        }

        $product->set_price($sale_active ? $sale_price : $price);
      }
    }

    // Handle stock quantity
    $quantity = isset($offer['Количество']) ? $offer['Количество'] : @$offer['КоличествоНаСкладе'];
    if (!is_null($quantity)) {
      $quantity = wc1c_parse_decimal($quantity);

      // Use CRUD API for stock management
      $product->set_stock_quantity($quantity);
      $stock_status = $quantity > 0 ? 'instock' : WC1C_OUTOFSTOCK_STATUS;
      $product->set_stock_status($stock_status);
    }

    // Handle variation attributes using HPOS compatible method
    if ($attributes && $product->is_type('variation')) {
      $variation_attributes = array();
      foreach ($attributes as $attribute_name => $attribute_value) {
        $attribute_key = 'attribute_' . sanitize_title($attribute_name);
        $variation_attributes[$attribute_key] = $attribute_value;
      }

      // Update variation attributes
      foreach ($variation_attributes as $key => $value) {
        $product->update_meta_data($key, $value);
      }

      // Clean up old attributes
      $all_meta = $product->get_meta_data();
      foreach ($all_meta as $meta) {
        $meta_key = $meta->key;
        if (strpos($meta_key, 'attribute_') === 0 && !array_key_exists($meta_key, $variation_attributes)) {
          $product->delete_meta_data($meta_key);
        }
      }
    }

    // Save all changes at once
    $product->save();

    if (function_exists('wc1c_log')) {
      wc1c_log('Updated product: ' . $post_id . ' with price: ' . ($price ?: 'N/A') . ' and stock: ' . ($quantity ?: 'N/A'), 'DEBUG');
    }
  } catch (Exception $e) {
    if (function_exists('wc1c_log')) {
      wc1c_log('Exception in wc1c_replace_offer_post_meta: ' . $e->getMessage(), 'ERROR');
    }
  }

  do_action('wc1c_post_offer_meta', $post_id, $offer, $is_full);
}

/**
 * Replace offer with HPOS compatibility
 */
function wc1c_replace_offer($is_full, $guid, $offer)
{
  if (!$guid) {
    if (function_exists('wc1c_log')) {
      wc1c_log('Empty GUID in wc1c_replace_offer', 'ERROR');
    }
    return null;
  }

  $post_id = wc1c_post_id_by_meta('_wc1c_guid', $guid);
  if ($post_id) {
    wc1c_replace_offer_post_meta($is_full, $post_id, $offer);
  } else {
    if (function_exists('wc1c_log')) {
      wc1c_log('Product not found for GUID: ' . $guid, 'WARNING');
    }
  }

  do_action('wc1c_post_offer', $post_id, $offer, $is_full);
  return $post_id;
}

/**
 * Replace product variation with HPOS compatibility
 */
function wc1c_replace_product_variation($guid, $parent_post_id, $order)
{
  if (!$guid || !$parent_post_id) {
    if (function_exists('wc1c_log')) {
      wc1c_log('Invalid parameters in wc1c_replace_product_variation', 'ERROR');
    }
    return null;
  }

  $post_id = wc1c_post_id_by_meta('_wc1c_guid', $guid);

  $args = array(
    'menu_order' => $order,
  );

  if (!$post_id) {
    try {
      $args = array_merge($args, array(
        'post_type' => 'product_variation',
        'post_parent' => $parent_post_id,
        'post_title' => "Product #$parent_post_id Variation",
        'post_status' => 'publish',
      ));

      $post_id = wp_insert_post($args, true);
      wc1c_check_wpdb_error();
      wc1c_check_wp_error($post_id);

      // Use HPOS compatible method for meta
      $variation = wc_get_product($post_id);
      if ($variation) {
        $variation->update_meta_data('_wc1c_guid', $guid);
        $variation->save();
      }

      $is_added = true;
    } catch (Exception $e) {
      if (function_exists('wc1c_log')) {
        wc1c_log('Failed to create variation: ' . $e->getMessage(), 'ERROR');
      }
      return null;
    }
  }

  $post = get_post($post_id);
  if (!$post) {
    wc1c_error("Failed to get post: $post_id");
  }

  if (empty($is_added)) {
    $is_changed = false;
    foreach ($args as $key => $value) {
      if ($post->$key != $value) {
        $is_changed = true;
        break;
      }
    }

    if ($is_changed) {
      try {
        $args = array_merge($args, array('ID' => $post_id));
        $post_id = wp_update_post($args, true);
        wc1c_check_wp_error($post_id);
      } catch (Exception $e) {
        if (function_exists('wc1c_log')) {
          wc1c_log('Failed to update variation: ' . $e->getMessage(), 'ERROR');
        }
      }
    }
  }

  return $post_id;
}

/**
 * Replace suboffers with HPOS compatibility
 */
function wc1c_replace_suboffers($is_full, $suboffers, $are_products = false)
{
  if (!$suboffers) return;

  $product_guid = $suboffers[0]['product_guid'];
  $post_id = wc1c_post_id_by_meta('_wc1c_guid', $product_guid);

  if (!$post_id && !$are_products) {
    if (function_exists('wc1c_log')) {
      wc1c_log('Parent product not found for GUID: ' . $product_guid, 'WARNING');
    }
    return;
  }

  if ($are_products) {
    $product = $suboffers[0]['product'];
    $product['Ид'] = $product_guid;
    $post_id = wc1c_replace_product($suboffers[0]['is_full'], $product_guid, $product);
  }

  if (!$post_id) {
    if (function_exists('wc1c_log')) {
      wc1c_log('Failed to get or create parent product', 'ERROR');
    }
    return;
  }

  try {
    // Set product type to variable using HPOS compatible method
    if (!WC1C_DISABLE_VARIATIONS) {
      $product = wc_get_product($post_id);
      if ($product && !$product->is_type('variable')) {
        $result = wp_set_post_terms($post_id, 'variable', 'product_type');
        wc1c_check_wp_error($result);
      }
    }

    // Collect characteristics
    $offer_characteristics = array();
    foreach ($suboffers as $suboffer) {
      if (isset($suboffer['offer']['ХарактеристикиТовара'])) {
        foreach ($suboffer['offer']['ХарактеристикиТовара'] as $suboffer_characteristic) {
          $characteristic_name = $suboffer_characteristic['Наименование'];
          if (!isset($offer_characteristics[$characteristic_name])) {
            $offer_characteristics[$characteristic_name] = array();
          }

          $characteristic_value = @$suboffer_characteristic['Значение'];
          if ($characteristic_value && !in_array($characteristic_value, $offer_characteristics[$characteristic_name])) {
            $offer_characteristics[$characteristic_name][] = $characteristic_value;
          }
        }
      }
    }

    // Update product attributes using HPOS compatible method
    if ($offer_characteristics) {
      ksort($offer_characteristics);
      foreach ($offer_characteristics as $characteristic_name => &$characteristic_values) {
        sort($characteristic_values);
      }

      $product = wc_get_product($post_id);
      if ($product) {
        $current_product_attributes = $product->get_attributes();

        $product_attributes = array();

        // Preserve non-variation attributes
        foreach ($current_product_attributes as $current_product_attribute_key => $current_product_attribute) {
          if (!$current_product_attribute->get_variation()) {
            $product_attributes[$current_product_attribute_key] = $current_product_attribute;
          }
        }

        // Add variation attributes
        foreach ($offer_characteristics as $offer_characteristic_name => $offer_characteristic_values) {
          $product_attribute_key = sanitize_title($offer_characteristic_name);

          $attribute = new WC_Product_Attribute();
          $attribute->set_name(wc_clean($offer_characteristic_name));
          $attribute->set_options($offer_characteristic_values);
          $attribute->set_position(count($product_attributes));
          $attribute->set_visible(true);
          $attribute->set_variation(true);

          $product_attributes[$product_attribute_key] = $attribute;
        }

        $product->set_attributes($product_attributes);
        $product->save();
      }
    }

    // Get current variations using HPOS compatible method
    $current_product_variation_ids = array();
    if (wc1c_is_hpos_enabled()) {
      // HPOS method
      $data_store = WC_Data_Store::load('product-variation');
      $variation_ids = $data_store->get_children($post_id);
      $current_product_variation_ids = $variation_ids;
    } else {
      // Legacy method
      $product_variation_posts = get_children(array(
        'post_parent' => $post_id,
        'post_type' => 'product_variation',
        'numberposts' => -1,
        'post_status' => 'any'
      ));
      foreach ($product_variation_posts as $product_variation_post) {
        $current_product_variation_ids[] = $product_variation_post->ID;
      }
    }

    // Process variations
    $product_variation_ids = array();
    foreach ($suboffers as $i => $suboffer) {
      $product_variation_id = wc1c_replace_product_variation($suboffer['guid'], $post_id, $i + 1);
      if ($product_variation_id) {
        $product_variation_ids[] = $product_variation_id;

        // Set variation attributes
        $attributes = array_fill_keys(array_keys($offer_characteristics), '');
        if (isset($suboffer['offer']['ХарактеристикиТовара'])) {
          foreach ($suboffer['offer']['ХарактеристикиТовара'] as $suboffer_characteristic) {
            $suboffer_characteristic_value = @$suboffer_characteristic['Значение'];
            if ($suboffer_characteristic_value) {
              $attributes[$suboffer_characteristic['Наименование']] = $suboffer_characteristic_value;
            }
          }
        }

        // Update variation data
        if ($are_products) {
          wc1c_replace_offer_post_meta($is_full, $product_variation_id, array(), $attributes);
        } else {
          wc1c_replace_offer_post_meta($is_full, $product_variation_id, $suboffer['offer'], $attributes);
        }
      }
    }

    // Clean up unused variations
    if (!WC1C_PRESERVE_PRODUCT_VARIATIONS) {
      $deleted_product_variation_ids = array_diff($current_product_variation_ids, $product_variation_ids);
      foreach ($deleted_product_variation_ids as $deleted_product_variation_id) {
        $variation = wc_get_product($deleted_product_variation_id);
        if ($variation) {
          $variation->delete(true);
        } else {
          wp_delete_post($deleted_product_variation_id, true);
        }
      }

      if (count($deleted_product_variation_ids) > 0 && function_exists('wc1c_log')) {
        wc1c_log('Deleted ' . count($deleted_product_variation_ids) . ' unused variations for product: ' . $post_id, 'INFO');
      }
    }

    if (function_exists('wc1c_log')) {
      wc1c_log('Processed ' . count($suboffers) . ' variations for product: ' . $post_id, 'INFO');
    }
  } catch (Exception $e) {
    if (function_exists('wc1c_log')) {
      wc1c_log('Exception in wc1c_replace_suboffers: ' . $e->getMessage(), 'ERROR');
    }
  }
}

/**
 * Enhanced error handling for offers processing
 */
function wc1c_validate_offer_data($offer)
{
  $errors = array();

  if (!isset($offer['Ид']) || empty($offer['Ид'])) {
    $errors[] = 'Missing offer ID';
  }

  if (isset($offer['Цена']['ЦенаЗаЕдиницу'])) {
    $price = wc1c_parse_decimal($offer['Цена']['ЦенаЗаЕдиницу']);
    if ($price < 0) {
      $errors[] = 'Negative price not allowed';
    }
  }

  if (isset($offer['Количество'])) {
    $quantity = wc1c_parse_decimal($offer['Количество']);
    if ($quantity < 0) {
      $errors[] = 'Negative quantity not allowed';
    }
  }

  return $errors;
}

/**
 * Batch update offers for better performance
 */
function wc1c_batch_update_offers($offers)
{
  if (!is_array($offers) || empty($offers)) {
    return;
  }

  $batch_size = apply_filters('wc1c_offers_batch_size', 50);
  $batches = array_chunk($offers, $batch_size);

  foreach ($batches as $batch_index => $batch) {
    if (function_exists('wc1c_log')) {
      wc1c_log('Processing offers batch ' . ($batch_index + 1) . ' of ' . count($batches), 'INFO');
    }

    foreach ($batch as $offer) {
      $validation_errors = wc1c_validate_offer_data($offer);
      if (!empty($validation_errors)) {
        if (function_exists('wc1c_log')) {
          wc1c_log('Offer validation failed: ' . implode(', ', $validation_errors), 'WARNING');
        }
        continue;
      }

      // Process individual offer
      wc1c_replace_offer(true, $offer['Ид'], $offer);
    }

    // Clear caches between batches
    wp_cache_flush();
    if (function_exists('gc_collect_cycles')) {
      gc_collect_cycles();
    }
  }
}

/**
 * Get product by GUID with caching
 */
function wc1c_get_product_by_guid($guid)
{
  static $cache = array();

  if (isset($cache[$guid])) {
    return $cache[$guid];
  }

  $post_id = wc1c_post_id_by_meta('_wc1c_guid', $guid);
  $product = $post_id ? wc_get_product($post_id) : null;

  $cache[$guid] = $product;

  return $product;
}

/**
 * Update product stock with validation
 */
function wc1c_update_product_stock($product, $quantity)
{
  if (!$product || !is_numeric($quantity)) {
    return false;
  }

  $quantity = wc1c_parse_decimal($quantity);

  // Validate quantity
  if ($quantity < 0) {
    if (function_exists('wc1c_log')) {
      wc1c_log('Negative stock quantity not allowed for product: ' . $product->get_id(), 'WARNING');
    }
    $quantity = 0;
  }

  try {
    $product->set_manage_stock(WC1C_MANAGE_STOCK === 'yes');
    $product->set_stock_quantity($quantity);

    $stock_status = $quantity > 0 ? 'instock' : WC1C_OUTOFSTOCK_STATUS;
    $product->set_stock_status($stock_status);

    return true;
  } catch (Exception $e) {
    if (function_exists('wc1c_log')) {
      wc1c_log('Failed to update stock for product ' . $product->get_id() . ': ' . $e->getMessage(), 'ERROR');
    }
    return false;
  }
}

/**
 * Update product price with validation
 */
function wc1c_update_product_price($product, $price)
{
  if (!$product || !is_numeric($price)) {
    return false;
  }

  $price = wc1c_parse_decimal($price);

  // Validate price
  if ($price < 0) {
    if (function_exists('wc1c_log')) {
      wc1c_log('Negative price not allowed for product: ' . $product->get_id(), 'WARNING');
    }
    return false;
  }

  try {
    $product->set_regular_price($price);

    // Handle sale price logic
    $sale_price = $product->get_sale_price();
    if (empty($sale_price)) {
      $product->set_price($price);
    } else {
      // Check if sale is active
      $sale_from = $product->get_date_on_sale_from();
      $sale_to = $product->get_date_on_sale_to();
      $now = time();

      $sale_active = true;
      if ($sale_from && $sale_from->getTimestamp() > $now) {
        $sale_active = false;
      }
      if ($sale_to && $sale_to->getTimestamp() < $now) {
        $sale_active = false;
        // Clear expired sale
        $product->set_sale_price('');
        $product->set_date_on_sale_from('');
        $product->set_date_on_sale_to('');
      }

      $product->set_price($sale_active ? $sale_price : $price);
    }

    return true;
  } catch (Exception $e) {
    if (function_exists('wc1c_log')) {
      wc1c_log('Failed to update price for product ' . $product->get_id() . ': ' . $e->getMessage(), 'ERROR');
    }
    return false;
  }
}

/**
 * Clean up orphaned variations
 */
function wc1c_cleanup_orphaned_variations()
{
  global $wpdb;

  if (wc1c_is_hpos_enabled()) {
    // HPOS compatible cleanup
    $orphaned_variations = $wpdb->get_col("
            SELECT p.ID 
            FROM {$wpdb->posts} p 
            LEFT JOIN {$wpdb->posts} parent ON p.post_parent = parent.ID 
            WHERE p.post_type = 'product_variation' 
            AND (parent.ID IS NULL OR parent.post_type != 'product')
        ");
  } else {
    // Legacy cleanup
    $orphaned_variations = $wpdb->get_col("
            SELECT p.ID 
            FROM {$wpdb->posts} p 
            LEFT JOIN {$wpdb->posts} parent ON p.post_parent = parent.ID 
            WHERE p.post_type = 'product_variation' 
            AND (parent.ID IS NULL OR parent.post_type != 'product')
        ");
  }

  if (!empty($orphaned_variations)) {
    foreach ($orphaned_variations as $variation_id) {
      $variation = wc_get_product($variation_id);
      if ($variation) {
        $variation->delete(true);
      }
    }

    if (function_exists('wc1c_log')) {
      wc1c_log('Cleaned up ' . count($orphaned_variations) . ' orphaned variations', 'INFO');
    }
  }
}

// Hook for cleanup
add_action('wc1c_post_offers', 'wc1c_cleanup_orphaned_variations');

/**
 * Memory optimization for large imports
 */
function wc1c_optimize_memory_for_offers()
{
  // Clear product caches
  wp_cache_delete_group('products');
  wp_cache_delete_group('product_meta');

  // Force garbage collection
  if (function_exists('gc_collect_cycles')) {
    gc_collect_cycles();
  }

  // Log memory usage
  if (function_exists('wc1c_log')) {
    $memory_usage = memory_get_usage(true);
    $memory_peak = memory_get_peak_usage(true);
    wc1c_log('Memory usage: ' . size_format($memory_usage) . ' / Peak: ' . size_format($memory_peak), 'DEBUG');
  }
}

// Hook for memory optimization
add_action('wc1c_offers_batch_processed', 'wc1c_optimize_memory_for_offers');
