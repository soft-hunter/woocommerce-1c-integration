<?php
/**
 * Order data mapper for 1C integration
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
 * Order data mapper for 1C integration
 */
class WC1C_Order_Mapper extends WC1C_Base_Mapper {

    /**
     * Map 1C order data to WooCommerce order format
     *
     * @param array $order_data 1C order data
     * @return array WooCommerce order data
     */
    public function map_to_woocommerce($order_data) {
        $mapped_data = array(
            'guid' => $this->get_value($order_data, 'Ид', ''),
            'number' => $this->get_value($order_data, 'Номер', ''),
            'date' => $this->get_value($order_data, 'Дата', ''),
            'time' => $this->get_value($order_data, 'Время', ''),
            'status' => 'on-hold',
            'currency' => $this->get_value($order_data, 'Валюта', get_woocommerce_currency()),
            'total' => 0,
            'customer_id' => 0,
            'billing' => array(),
            'shipping' => array(),
            'line_items' => array()
        );

        // Parse date and time
        if (!empty($mapped_data['date'])) {
            $datetime = $mapped_data['date'];
            if (!empty($mapped_data['time'])) {
                $datetime .= ' ' . $mapped_data['time'];
            }
            $mapped_data['date_created'] = $datetime;
        }

        // Map total
        if (isset($order_data['Сумма'])) {
            $mapped_data['total'] = $this->parse_decimal($order_data['Сумма']);
        }

        // Map customer
        if (isset($order_data['Контрагенты']) && is_array($order_data['Контрагенты'])) {
            $customer_data = $this->map_order_customer($order_data['Контрагенты']);
            $mapped_data = array_merge($mapped_data, $customer_data);
        }

        // Map line items
        if (isset($order_data['Товары']) && is_array($order_data['Товары'])) {
            $mapped_data['line_items'] = $this->map_order_line_items($order_data['Товары']);
        }

        // Map status from requisites
        if (isset($order_data['ЗначенияРеквизитов']) && is_array($order_data['ЗначенияРеквизитов'])) {
            $status = $this->get_order_status_from_requisites($order_data['ЗначенияРеквизитов']);
            if ($status) {
                $mapped_data['status'] = $status;
            }
        }

        // Apply filters
        $mapped_data = apply_filters('wc1c_order_mapper_data', $mapped_data, $order_data);

        return $mapped_data;
    }

    /**
     * Map WooCommerce order data to 1C format
     *
     * @param array $order_data WooCommerce order data
     * @return array 1C order data
     */
    public function map_to_1c($order_data) {
        $mapped_data = array(
            'Ид' => $this->get_value($order_data, 'guid', ''),
            'Номер' => $this->get_value($order_data, 'number', ''),
            'Дата' => $this->get_value($order_data, 'date', ''),
            'Время' => $this->get_value($order_data, 'time', ''),
            'ХозОперация' => 'Заказ товара',
            'Роль' => 'Продавец',
            'Валюта' => $this->get_value($order_data, 'currency', get_woocommerce_currency()),
            'Сумма' => $this->get_value($order_data, 'total', 0),
            'Комментарий' => $this->get_value($order_data, 'comment', ''),
            'Контрагенты' => array(),
            'Товары' => array(),
            'ЗначенияРеквизитов' => array()
        );

        // Map customer data
        if (isset($order_data['customer'])) {
            $mapped_data['Контрагенты'] = $this->map_customer_to_1c($order_data['customer']);
        }

        // Map line items
        if (isset($order_data['line_items']) && is_array($order_data['line_items'])) {
            $mapped_data['Товары'] = $this->map_line_items_to_1c($order_data['line_items']);
        }

        // Map order status and other requisites
        $mapped_data['ЗначенияРеквизитов'] = $this->map_order_requisites_to_1c($order_data);

        // Apply filters
        $mapped_data = apply_filters('wc1c_order_mapper_to_1c_data', $mapped_data, $order_data);

        return $mapped_data;
    }

    /**
     * Map order customer data
     *
     * @param array $contragents 1C contragents data
     * @return array Customer data
     */
    private function map_order_customer($contragents) {
        $customer_data = array(
            'customer_id' => 0,
            'billing' => array(),
            'shipping' => array()
        );

        foreach ($contragents as $contragent) {
            $role = $this->get_value($contragent, 'Роль', '');
            $name = $this->get_value($contragent, 'Наименование', '');

            if ($name === 'Гость') {
                $customer_data['customer_id'] = 0;
                continue;
            }

            // Try to find existing customer
            if (strpos($name, ' ') !== false) {
                list($first_name, $last_name) = explode(' ', $name, 2);
                $customer_id = $this->find_customer_by_name($first_name, $last_name);
                if ($customer_id) {
                    $customer_data['customer_id'] = $customer_id;
                }
            }

            // Map address data
            $address_data = array(
                'first_name' => $this->get_value($contragent, 'Имя', ''),
                'last_name' => $this->get_value($contragent, 'Фамилия', ''),
                'company' => '',
                'address_1' => '',
                'address_2' => '',
                'city' => '',
                'state' => '',
                'postcode' => '',
                'country' => '',
                'email' => '',
                'phone' => ''
            );

            // Parse address if available
            if (isset($contragent['АдресРегистрации']['АдресноеПоле'])) {
                $address_fields = $contragent['АдресРегистрации']['АдресноеПоле'];
                if (!is_array($address_fields)) {
                    $address_fields = array($address_fields);
                }

                foreach ($address_fields as $field) {
                    $type = $this->get_value($field, 'Тип', '');
                    $value = $this->get_value($field, 'Значение', '');

                    switch ($type) {
                        case 'Почтовый индекс':
                            $address_data['postcode'] = $value;
                            break;
                        case 'Страна':
                            $address_data['country'] = $value;
                            break;
                        case 'Регион':
                            $address_data['state'] = $value;
                            break;
                        case 'Город':
                            $address_data['city'] = $value;
                            break;
                    }
                }
            }

            // Parse contacts if available
            if (isset($contragent['Контакты']['Контакт'])) {
                $contacts = $contragent['Контакты']['Контакт'];
                if (!is_array($contacts)) {
                    $contacts = array($contacts);
                }

                foreach ($contacts as $contact) {
                    $type = $this->get_value($contact, 'Тип', '');
                    $value = $this->get_value($contact, 'Значение', '');

                    switch ($type) {
                        case 'Почта':
                            $address_data['email'] = $value;
                            break;
                        case 'ТелефонРабочий':
                            $address_data['phone'] = $value;
                            break;
                    }
                }
            }

            // Assign to billing or shipping based on role
            if ($role === 'Плательщик') {
                $customer_data['billing'] = $address_data;
            } elseif ($role === 'Получатель') {
                $customer_data['shipping'] = $address_data;
            } else {
                // Default to billing if role is not specified
                $customer_data['billing'] = $address_data;
            }
        }

        return $customer_data;
    }

    /**
     * Map order line items
     *
     * @param array $products 1C products data
     * @return array Line items
     */
    private function map_order_line_items($products) {
        $line_items = array();

        foreach ($products as $product_data) {
            $product_guid = $this->get_value($product_data, 'Ид', '');
            $product_name = $this->get_value($product_data, 'Наименование', '');
            $quantity = $this->parse_decimal($this->get_value($product_data, 'Количество', 1));
            $price_per_item = $this->parse_decimal($this->get_value($product_data, 'ЦенаЗаЕдиницу', 0));
            $total = $this->parse_decimal($this->get_value($product_data, 'Сумма', 0));

            // Apply coefficient if present
            if (isset($product_data['Коэффициент'])) {
                $coefficient = $this->parse_decimal($product_data['Коэффициент']);
                $quantity *= $coefficient;
            }

            // Calculate total if not provided
            if ($total == 0 && $price_per_item > 0) {
                $total = $price_per_item * $quantity;
            }

            // Find WooCommerce product
            $product_id = $this->find_product_by_guid($product_guid);

            $line_items[] = array(
                'product_id' => $product_id,
                'product_guid' => $product_guid,
                'name' => $product_name,
                'quantity' => $quantity,
                'subtotal' => $total,
                'total' => $total
            );
        }

        return $line_items;
    }

    /**
     * Get order status from requisites
     *
     * @param array $requisites Order requisites
     * @return string|null Order status
     */
    private function get_order_status_from_requisites($requisites) {
        foreach ($requisites as $requisite) {
            $name = $this->get_value($requisite, 'Наименование', '');
            $value = $this->get_value($requisite, 'Значение', '');

            if ($name === 'Статуса заказа ИД') {
                $valid_statuses = array('pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed');
                if (in_array($value, $valid_statuses)) {
                    return $value;
                }
            }

            if ($name === 'Отменен' && $value === 'true') {
                return 'cancelled';
            }
        }

        return null;
    }

    /**
     * Map customer data to 1C format
     *
     * @param array $customer_data Customer data
     * @return array 1C contragents data
     */
    private function map_customer_to_1c($customer_data) {
        $contragents = array();

        // Billing contragent
        if (!empty($customer_data['billing'])) {
            $billing = $customer_data['billing'];
            $contragent = array(
                'Ид' => 'wc1c#user#' . $this->get_value($customer_data, 'customer_id', 0),
                'Роль' => 'Плательщик',
                'Наименование' => trim($billing['first_name'] . ' ' . $billing['last_name']),
                'ПолноеНаименование' => trim($billing['first_name'] . ' ' . $billing['last_name'])
            );

            if (!empty($billing['first_name'])) {
                $contragent['Имя'] = $billing['first_name'];
            }

            if (!empty($billing['last_name'])) {
                $contragent['Фамилия'] = $billing['last_name'];
            }

            // Add address
            $address_fields = array();
            if (!empty($billing['postcode'])) {
                $address_fields[] = array('Тип' => 'Почтовый индекс', 'Значение' => $billing['postcode']);
            }
            if (!empty($billing['country'])) {
                $address_fields[] = array('Тип' => 'Страна', 'Значение' => $billing['country']);
            }
            if (!empty($billing['state'])) {
                $address_fields[] = array('Тип' => 'Регион', 'Значение' => $billing['state']);
            }
            if (!empty($billing['city'])) {
                $address_fields[] = array('Тип' => 'Город', 'Значение' => $billing['city']);
            }

            if (!empty($address_fields)) {
                $contragent['АдресРегистрации'] = array(
                    'АдресноеПоле' => $address_fields
                );
            }

            // Add contacts
            $contacts = array();
            if (!empty($billing['email'])) {
                $contacts[] = array('Тип' => 'Почта', 'Значение' => $billing['email']);
            }
            if (!empty($billing['phone'])) {
                $contacts[] = array('Тип' => 'ТелефонРабочий', 'Значение' => $billing['phone']);
            }

            if (!empty($contacts)) {
                $contragent['Контакты'] = array('Контакт' => $contacts);
            }

            $contragents[] = $contragent;
        }

        // Shipping contragent (if different from billing)
        if (!empty($customer_data['shipping']) && $customer_data['shipping'] !== $customer_data['billing']) {
            $shipping = $customer_data['shipping'];
            $contragent = array(
                'Ид' => 'wc1c#user#' . $this->get_value($customer_data, 'customer_id', 0) . '#shipping',
                'Роль' => 'Получатель',
                'Наименование' => trim($shipping['first_name'] . ' ' . $shipping['last_name']),
                'ПолноеНаименование' => trim($shipping['first_name'] . ' ' . $shipping['last_name'])
            );

            // Add similar mapping as billing...
            $contragents[] = $contragent;
        }

        return $contragents;
    }

    /**
     * Map line items to 1C format
     *
     * @param array $line_items Line items
     * @return array 1C products data
     */
    private function map_line_items_to_1c($line_items) {
        $products = array();

        foreach ($line_items as $item) {
            $product = array(
                'Ид' => $this->get_value($item, 'product_guid', ''),
                'Наименование' => $this->get_value($item, 'name', ''),
                'БазоваяЕдиница' => 'шт',
                'ЦенаЗаЕдиницу' => $this->get_value($item, 'subtotal', 0) / max(1, $this->get_value($item, 'quantity', 1)),
                'Количество' => $this->get_value($item, 'quantity', 1),
                'Сумма' => $this->get_value($item, 'total', 0),
                'ЗначенияРеквизитов' => array(
                    array(
                        'Наименование' => 'ТипНоменклатуры',
                        'Значение' => 'Товар'
                    )
                )
            );

            $products[] = $product;
        }

        return $products;
    }

    /**
     * Map order requisites to 1C format
     *
     * @param array $order_data Order data
     * @return array 1C requisites
     */
    private function map_order_requisites_to_1c($order_data) {
        $status = $this->get_value($order_data, 'status', 'on-hold');
        $has_shipping = $this->get_value($order_data, 'has_shipping', false);
        $payment_method = $this->get_value($order_data, 'payment_method_title', '');
        $modified_at = $this->get_value($order_data, 'modified_at', '');

        $requisites = array(
            array(
                'Наименование' => 'Заказ оплачен',
                'Значение' => !in_array($status, array('on-hold', 'pending')) ? 'true' : 'false'
            ),
            array(
                'Наименование' => 'Доставка разрешена',
                'Значение' => $has_shipping ? 'true' : 'false'
            ),
            array(
                'Наименование' => 'Отменен',
                'Значение' => $status === 'cancelled' ? 'true' : 'false'
            ),
            array(
                'Наименование' => 'Финальный статус',
                'Значение' => !in_array($status, array('trash', 'on-hold', 'pending', 'processing')) ? 'true' : 'false'
            ),
            array(
                'Наименование' => 'Статус заказа',
                'Значение' => wc_get_order_status_name($status)
            )
        );

        if (!empty($modified_at)) {
            $requisites[] = array(
                'Наименование' => 'Дата изменения статуса',
                'Значение' => $modified_at
            );
        }

        if (!empty($payment_method)) {
            $requisites[] = array(
                'Наименование' => 'Метод оплаты',
                'Значение' => $payment_method
            );
        }

        // Apply filters to allow customization
        $requisites = apply_filters('wc1c_order_requisites_to_1c', $requisites, $order_data);

        return $requisites;
    }

    /**
     * Find customer by name
     *
     * @param string $first_name First name
     * @param string $last_name Last name
     * @return int Customer ID or 0 if not found
     */
    private function find_customer_by_name($first_name, $last_name) {
        global $wpdb;

        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT u1.user_id FROM {$wpdb->usermeta} u1 
             JOIN {$wpdb->usermeta} u2 ON u1.user_id = u2.user_id 
             WHERE (u1.meta_key = 'billing_first_name' AND u1.meta_value = %s 
                    AND u2.meta_key = 'billing_last_name' AND u2.meta_value = %s) 
                OR (u1.meta_key = 'shipping_first_name' AND u1.meta_value = %s 
                    AND u2.meta_key = 'shipping_last_name' AND u2.meta_value = %s)",
            $first_name, $last_name, $first_name, $last_name
        ));

        return $user_id ? (int) $user_id : 0;
    }

    /**
     * Find product by GUID
     *
     * @param string $guid Product GUID
     * @return int Product ID or 0 if not found
     */
    private function find_product_by_guid($guid) {
        global $wpdb;

        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT pm.post_id FROM {$wpdb->postmeta} pm 
             JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE pm.meta_key = '_wc1c_guid' AND pm.meta_value = %s 
             AND p.post_type IN ('product', 'product_variation')",
            $guid
        ));

        return $post_id ? (int) $post_id : 0;
    }

    /**
     * Validate order data
     *
     * @param array $order_data Order data
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate($order_data) {
        $errors = new WP_Error();

        // Check required fields
        if (empty($order_data['Ид'])) {
            $errors->add('missing_id', __('Order ID is required', 'woocommerce-1c-integration'));
        }

        if (empty($order_data['Номер'])) {
            $errors->add('missing_number', __('Order number is required', 'woocommerce-1c-integration'));
        }

        // Validate operation type
        $operation = $this->get_value($order_data, 'ХозОперация', '');
        if ($operation !== 'Заказ товара') {
            $errors->add('invalid_operation', __('Invalid order operation type', 'woocommerce-1c-integration'));
        }

        // Validate role
        $role = $this->get_value($order_data, 'Роль', '');
        if ($role !== 'Продавец') {
            $errors->add('invalid_role', __('Invalid order role', 'woocommerce-1c-integration'));
        }

        // Validate total if present
        if (isset($order_data['Сумма'])) {
            $total = $this->parse_decimal($order_data['Сумма']);
            if ($total < 0) {
                $errors->add('invalid_total', __('Order total cannot be negative', 'woocommerce-1c-integration'));
            }
        }

        return $errors->has_errors() ? $errors : true;
    }
}