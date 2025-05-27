<?php
/**
 * WooCommerce 1C Integration - Orders Exchange
 * HPOS Compatible Version
 */

if (!defined('ABSPATH')) exit;

// Load compatibility functions
if (!function_exists('wc1c_is_hpos_enabled')) {
    require_once WC1C_PLUGIN_DIR . 'compat.php';
}

function wc1c_orders_start_element_handler($is_full, $names, $depth, $name, $attrs) {
    global $wc1c_document;

    if (@$names[$depth - 1] == 'КоммерческаяИнформация' && $name == 'Документ') {
        $wc1c_document = array();
    }
    elseif (@$names[$depth - 1] == 'Документ' && $name == 'Контрагенты') {
        $wc1c_document['Контрагенты'] = array();
    }
    elseif (@$names[$depth - 1] == 'Контрагенты' && $name == 'Контрагент') {
        $wc1c_document['Контрагенты'][] = array();
    }
    elseif (@$names[$depth - 1] == 'Документ' && $name == 'Товары') {
        $wc1c_document['Товары'] = array();
    }
    elseif (@$names[$depth - 1] == 'Товары' && $name == 'Товар') {
        $wc1c_document['Товары'][] = array();
    }
    elseif (@$names[$depth - 1] == 'Товар' && $name == 'ЗначенияРеквизитов') {
        $i = count($wc1c_document['Товары']) - 1;
        $wc1c_document['Товары'][$i]['ЗначенияРеквизитов'] = array();
    }
    elseif (@$names[$depth - 2] == 'Товар' && @$names[$depth - 1] == 'ЗначенияРеквизитов' && $name == 'ЗначениеРеквизита') {
        $i = count($wc1c_document['Товары']) - 1;
        $wc1c_document['Товары'][$i]['ЗначенияРеквизитов'][] = array();
    }
    elseif (@$names[$depth - 1] == 'Товар' && $name == 'ХарактеристикиТовара') {
        $i = count($wc1c_document['Товары']) - 1;
        $wc1c_document['Товары'][$i]['ХарактеристикиТовара'] = array();
    }
    elseif (@$names[$depth - 2] == 'Товар' && @$names[$depth - 1] == 'ХарактеристикиТовара' && $name == 'ХарактеристикаТовара') {
        $i = count($wc1c_document['Товары']) - 1;
        $wc1c_document['Товары'][$i]['ХарактеристикиТовара'][] = array();
    }
    elseif (@$names[$depth - 1] == 'Документ' && $name == 'ЗначенияРеквизитов') {
        $wc1c_document['ЗначенияРеквизитов'] = array();
    }
    elseif (@$names[$depth - 1] == 'ЗначенияРеквизитов' && $name == 'ЗначениеРеквизита') {
        $wc1c_document['ЗначенияРеквизитов'][] = array();
    }
}

function wc1c_orders_character_data_handler($is_full, $names, $depth, $name, $data) {
    global $wc1c_document;

    if (@$names[$depth - 2] == 'КоммерческаяИнформация' && @$names[$depth - 1] == 'Документ' && !in_array($name, array('Контрагенты', 'Товары', 'ЗначенияРеквизитов'))) {
        @$wc1c_document[$name] .= $data;
    }
    elseif (@$names[$depth - 2] == 'Контрагенты' && @$names[$depth - 1] == 'Контрагент') {
        $i = count($wc1c_document['Контрагенты']) - 1;
        @$wc1c_document['Контрагенты'][$i][$name] .= $data;
    }
    elseif (@$names[$depth - 2] == 'Товары' && @$names[$depth - 1] == 'Товар' && !in_array($name, array('СтавкиНалогов', 'ЗначенияРеквизитов', 'ХарактеристикиТовара'))) {
        $i = count($wc1c_document['Товары']) - 1;
        @$wc1c_document['Товары'][$i][$name] .= $data;
    }
    elseif (@$names[$depth - 3] == 'Товар' && @$names[$depth - 2] == 'ЗначенияРеквизитов' && @$names[$depth - 1] == 'ЗначениеРеквизита') {
        $i = count($wc1c_document['Товары']) - 1;
        $j = count($wc1c_document['Товары'][$i]['ЗначенияРеквизитов']) - 1;
        @$wc1c_document['Товары'][$i]['ЗначенияРеквизитов'][$j][$name] .= $data;
    }
    elseif (@$names[$depth - 3] == 'Товар' && @$names[$depth - 2] == 'ХарактеристикиТовара' && @$names[$depth - 1] == 'ХарактеристикаТовара') {
        $i = count($wc1c_document['Товары']) - 1;
        $j = count($wc1c_document['Товары'][$i]['ХарактеристикиТовара']) - 1;
        @$wc1c_document['Товары'][$i]['ХарактеристикиТовара'][$j][$name] .= $data;
    }
    elseif (@$names[$depth - 3] == 'Документ' && @$names[$depth - 2] == 'ЗначенияРеквизитов' && @$names[$depth - 1] == 'ЗначениеРеквизита') {
        $i = count($wc1c_document['ЗначенияРеквизитов']) - 1;
        @$wc1c_document['ЗначенияРеквизитов'][$i][$name] .= $data;
    }
}

function wc1c_orders_end_element_handler($is_full, $names, $depth, $name) {
    global $wpdb, $wc1c_document;

    if (@$names[$depth - 1] == 'КоммерческаяИнформация' && $name == 'Документ') {
        wc1c_replace_document($wc1c_document);
    }
    elseif (!$depth && $name == 'КоммерческаяИнформация') {
        // Clean transients with HPOS compatibility
        $wpdb->query($wpdb->prepare("DELETE FROM $wpdb->options WHERE option_name LIKE %s", '_transient_%'));
        wc1c_check_wpdb_error();

        do_action('wc1c_post_orders', $is_full);
        
        if (function_exists('wc1c_log')) {
            wc1c_log('Orders import completed', 'INFO');
        }
    }
}

/**
 * Replace document products with HPOS compatibility
 */
function wc1c_replace_document_products($order, $document_products) {
    if (!$order || !is_a($order, 'WC_Order')) {
        if (function_exists('wc1c_log')) {
            wc1c_log('Invalid order object in wc1c_replace_document_products', 'ERROR');
        }
        return;
    }

    $line_items = $order->get_items();
    $line_item_ids = array();
    
    foreach ($document_products as $i => $document_product) {
        $product_id = wc1c_post_id_by_meta('_wc1c_guid', $document_product['Ид']);
        if (!$product_id) {
            if (function_exists('wc1c_log')) {
                wc1c_log('Product not found for GUID: ' . $document_product['Ид'], 'WARNING');
            }
            continue;
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            if (function_exists('wc1c_log')) {
                wc1c_log('Failed to get product: ' . $product_id, 'ERROR');
            }
            continue;
        }

        $document_products[$i]['product'] = $product;
        
        $current_line_item_id = null;
        foreach ($line_items as $line_item_id => $line_item) {
            if ($line_item->get_product_id() != $product->get_id() ||
                (int) $line_item->get_variation_id() != $product->get_id()) continue;

            $current_line_item_id = $line_item_id;
            break;
        }
        $document_products[$i]['line_item_id'] = $current_line_item_id;

        if ($current_line_item_id) $line_item_ids[] = $current_line_item_id;
    }

    // Remove old items if needed
    $old_line_item_ids = array_diff(array_keys($line_items), $line_item_ids);
    if ($old_line_item_ids) {
        $order->remove_order_items('line_item');

        foreach ($document_products as $i => $document_product) {
            $document_products[$i]['line_item_id'] = null;
        }
    }

    // Add new items
    foreach ($document_products as $document_product) {
        if (!isset($document_product['product'])) continue;
        
        $product = $document_product['product'];
        $quantity = isset($document_product['Количество']) ? wc1c_parse_decimal($document_product['Количество']) : 1;
        $coefficient = isset($document_product['Коэффициент']) ? wc1c_parse_decimal($document_product['Коэффициент']) : 1;
        $quantity *= $coefficient;

        if (!empty($document_product['Сумма'])) {
            $total = wc1c_parse_decimal($document_product['Сумма']);
        } else {
            $price = wc1c_parse_decimal(@$document_product['ЦенаЗаЕдиницу']);
            $total = $price * $quantity;
        }

        try {
            // Create order item using HPOS compatible method
            $item = new WC_Order_Item_Product();
            $item->set_product($product);
            $item->set_quantity($quantity);
            $item->set_subtotal($total);
            $item->set_total($total);

            // Handle variations
            if ($product instanceof WC_Product_Variation) {
                $attributes = $product->get_variation_attributes();
                $variation = array();
                foreach ($attributes as $attribute_key => $attribute_value) {
                    $variation[urldecode($attribute_key)] = urldecode($attribute_value);
                }
                $item->set_variation($variation);
            }

            $line_item_id = $order->add_item($item);
            if (!$line_item_id) {
                if (function_exists('wc1c_log')) {
                    wc1c_log('Failed to add product to order: ' . $product->get_id(), 'ERROR');
                }
            }
        } catch (Exception $e) {
            if (function_exists('wc1c_log')) {
                wc1c_log('Exception adding product to order: ' . $e->getMessage(), 'ERROR');
            }
        }
    }

    // Save order after adding all items
    try {
        $order->save();
    } catch (Exception $e) {
        if (function_exists('wc1c_log')) {
            wc1c_log('Failed to save order after adding products: ' . $e->getMessage(), 'ERROR');
        }
    }
}

/**
 * Replace document services with HPOS compatibility
 */
function wc1c_replace_document_services($order, $document_services) {
    if (!$order || !is_a($order, 'WC_Order')) {
        return 0;
    }

    static $shipping_methods;

    $shipping_items = $order->get_shipping_methods();

    if ($shipping_items && !$document_services) {
        $order->remove_order_items('shipping');
        return 0;
    }

    if (!$shipping_methods) {
        if ($shipping = WC()->shipping) {
            $shipping->load_shipping_methods();
            $shipping_methods = $shipping->get_shipping_methods();
        }
    }

    $shipping_cost_sum = 0;
    foreach ($document_services as $document_service) {
        foreach ($shipping_methods as $shipping_method_id => $shipping_method) {
            if ($document_service['Наименование'] != $shipping_method->title) continue;

            $shipping_cost = wc1c_parse_decimal($document_service['Сумма']);
            $shipping_cost_sum += $shipping_cost;

            $method_title = isset($shipping_method->method_title) ? $shipping_method->method_title : '';

            try {
                if (!$shipping_items) {
                    $shipping_rate = new WC_Shipping_Rate($shipping_method->id, $method_title, $shipping_cost);
                    $shipping_item_id = $order->add_shipping($shipping_rate);
                    if (!$shipping_item_id) {
                        if (function_exists('wc1c_log')) {
                            wc1c_log('Failed to add shipping to order', 'ERROR');
                        }
                    }
                } else {
                    $shipping_item_id = key($shipping_items);
                    $shipping_item = $shipping_items[$shipping_item_id];
                    $shipping_item->set_method_title($method_title);
                    $shipping_item->set_total($shipping_cost);
                }
            } catch (Exception $e) {
                if (function_exists('wc1c_log')) {
                    wc1c_log('Exception handling shipping: ' . $e->getMessage(), 'ERROR');
                }
            }

            break;
        }
    }

    return $shipping_cost_sum;
}

/**
 * Filter for new order data
 */
function wc1c_woocommerce_new_order_data($order_data) {
    global $wc1c_document;

    if (isset($wc1c_document['Номер'])) {
        $order_data['import_id'] = $wc1c_document['Номер'];
    }

    return $order_data;
}
add_filter('woocommerce_new_order_data', 'wc1c_woocommerce_new_order_data');

/**
 * Replace document with HPOS compatibility
 */
function wc1c_replace_document($document) {
    global $wpdb;

    if (!isset($document['ХозОперация']) || !isset($document['Роль']) ||
        $document['ХозОперация'] != "Заказ товара" || $document['Роль'] != "Продавец") {
        return;
    }

    if (function_exists('wc1c_log')) {
        wc1c_log('Processing document: ' . (@$document['Номер'] ?: 'unknown'), 'DEBUG');
    }

    $order = null;
    if (isset($document['Номер'])) {
        $order = wc_get_order($document['Номер']);
    }

    if (!$order) {
        try {
            // Create new order using HPOS compatible method
            $order = new WC_Order();
            $order->set_status('on-hold');
            
            if (isset($document['Комментарий'])) {
                $order->set_customer_note(sanitize_textarea_field($document['Комментарий']));
            }

            // Handle customer assignment
            $contragent_name = @$document['Контрагенты'][0]['Наименование'];
            if ($contragent_name == "Гость") {
                $user_id = 0;
            } elseif ($contragent_name && strpos($contragent_name, ' ') !== false) {
                list($first_name, $last_name) = explode(' ', $contragent_name, 2);
                $result = $wpdb->get_var($wpdb->prepare(
                    "SELECT u1.user_id FROM $wpdb->usermeta u1 
                     JOIN $wpdb->usermeta u2 ON u1.user_id = u2.user_id 
                     WHERE (u1.meta_key = 'billing_first_name' AND u1.meta_value = %s 
                            AND u2.meta_key = 'billing_last_name' AND u2.meta_value = %s) 
                        OR (u1.meta_key = 'shipping_first_name' AND u1.meta_value = %s 
                            AND u2.meta_key = 'shipping_last_name' AND u2.meta_value = %s)",
                    $first_name, $last_name, $first_name, $last_name
                ));
                wc1c_check_wpdb_error();
                if ($result) $user_id = $result;
            }

            if (isset($user_id)) {
                $order->set_customer_id($user_id);
            }

            // Set order date if provided
            if (isset($document['Дата'])) {
                $date = $document['Дата'];
                if (!empty($document['Время'])) {
                    $date .= " {$document['Время']}";
                }
                $timestamp = strtotime($date);
                if ($timestamp) {
                    $order->set_date_created($timestamp);
                }
            }

            $order_id = $order->save();
            if (!$order_id) {
                wc1c_error("Failed to create order");
            }

            // Set metadata using HPOS compatible methods
            if (!isset($user_id) && $contragent_name) {
                $order->update_meta_data('wc1c_contragent', $contragent_name);
            }

            if (isset($document['Ид'])) {
                $order->update_meta_data('_wc1c_guid', $document['Ид']);
            }

            $order->save();

            if (function_exists('wc1c_log')) {
                wc1c_log('Created new order: ' . $order_id, 'INFO');
            }

        } catch (Exception $e) {
            if (function_exists('wc1c_log')) {
                wc1c_log('Failed to create order: ' . $e->getMessage(), 'ERROR');
            }
            return;
        }
    } else {
        try {
            // Update existing order
            if (isset($document['ЗначенияРеквизитов'])) {
                foreach ($document['ЗначенияРеквизитов'] as $requisite) {
                    if ($requisite['Наименование'] == 'Статуса заказа ИД' &&
                        in_array($requisite['Значение'], array("pending", "processing", "on-hold", "completed", "cancelled", "refunded", "failed"))) {
                        $order->set_status($requisite['Значение']);
                        break;
                    }
                }

                foreach ($document['ЗначенияРеквизитов'] as $requisite) {
                    if ($requisite['Наименование'] == 'Отменен' && $requisite['Значение'] == 'true') {
                        $order->set_status('cancelled');
                        break;
                    }
                }
            }

            $order->save();

        } catch (Exception $e) {
            if (function_exists('wc1c_log')) {
                wc1c_log('Failed to update order: ' . $e->getMessage(), 'ERROR');
            }
        }
    }

    // Handle deletion flag
    $is_deleted = false;
    if (isset($document['ЗначенияРеквизитов'])) {
        foreach ($document['ЗначенияРеквизитов'] as $requisite) {
            if ($requisite['Наименование'] == 'ПометкаУдаления' && $requisite['Значение'] == 'true') {
                $is_deleted = true;
                break;
            }
        }
    }

    if ($is_deleted && $order->get_status() != 'trash') {
        wp_trash_post($order->get_id());
    } elseif (!$is_deleted && $order->get_status() == 'trash') {
        wp_untrash_post($order->get_id());
    }

    try {
        // Update order properties using HPOS compatible methods
        if (isset($document['Валюта'])) {
            $order->set_currency($document['Валюта']);
        }

        if (isset($document['Сумма'])) {
            $order->set_total(wc1c_parse_decimal($document['Сумма']));
        }

        // Process products and services
        $document_products = array();
        $document_services = array();
        
        if (isset($document['Товары'])) {
            foreach ($document['Товары'] as $document_product) {
                $is_service = false;
                if (isset($document_product['ЗначенияРеквизитов']
                    foreach ($document_product['ЗначенияРеквизитов'] as $document_product_requisite) {
                    if ($document_product_requisite['Наименование'] == 'ТипНоменклатуры') {
                        if ($document_product_requisite['Значение'] == 'Услуга') {
                            $document_services[] = $document_product;
                            $is_service = true;
                        }
                        break;
                    }
                }
                
                if (!$is_service) {
                    $document_products[] = $document_product;
                }
            }
        }

        // Replace products and services
        wc1c_replace_document_products($order, $document_products);
        $shipping_total = wc1c_replace_document_services($order, $document_services);

        if ($shipping_total) {
            $order->set_shipping_total($shipping_total);
        }

        // Save all changes
        $order->save();

        if (function_exists('wc1c_log')) {
            wc1c_log('Successfully processed order: ' . $order->get_id(), 'INFO');
        }

    } catch (Exception $e) {
        if (function_exists('wc1c_log')) {
            wc1c_log('Exception processing order: ' . $e->getMessage(), 'ERROR');
        }
        wc1c_error("Failed to process order: " . $e->getMessage());
    }
}