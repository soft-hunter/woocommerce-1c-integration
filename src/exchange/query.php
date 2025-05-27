<?php
if (!defined('ABSPATH')) exit;

if (!defined('WC1C_CURRENCY')) define('WC1C_CURRENCY', null);

WC();

// Check if HPOS is enabled
$hpos_enabled = wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled();

if ($hpos_enabled) {
  // Use HPOS query
  $order_query = new \WC_Order_Query(array(
    'limit' => -1,
    'status' => array_keys(wc_get_order_statuses()),
    'meta_query' => array(
      array(
        'key' => 'wc1c_queried',
        'compare' => 'NOT EXISTS',
      ),
    ),
  ));
  $orders = $order_query->get_orders();
} else {
  // Use legacy post query
  $order_statuses = array_keys(wc_get_order_statuses());
  $order_posts = get_posts(array(
    'post_type' => 'shop_order',
    'post_status' => $order_statuses,
    'numberposts' => -1,
    'meta_query' => array(
      array(
        'key' => 'wc1c_queried',
        'compare' => "NOT EXISTS",
      ),
    ),
  ));
  
  $orders = array();
  foreach ($order_posts as $order_post) {
    $order = wc_get_order($order_post->ID);
    if ($order) {
      $orders[] = $order;
    }
  }
}

$order_ids = array();
$documents = array();

foreach ($orders as $order) {
  if (!$order) continue;
  
  $order_id = $order->get_id();
  $order_ids[] = $order_id;

  $order_line_items = $order->get_items();

  // Process line items
  foreach ($order_line_items as $key => $order_line_item) {
    $product_id = $order_line_item->get_variation_id() ? $order_line_item->get_variation_id() : $order_line_item->get_product_id();
    $guid = '';
    
    if ($product_id) {
      $product = wc_get_product($product_id);
      if ($product) {
        $guid = $product->get_meta('_wc1c_guid', true);
      }
    }

    $order_line_items[$key] = array(
      'product_id' => $order_line_item->get_product_id(),
      'variation_id' => $order_line_item->get_variation_id(),
      'name' => $order_line_item->get_name(),
      'qty' => $order_line_item->get_quantity(),
      'line_total' => $order_line_item->get_total(),
      'wc1c_guid' => $guid,
    );
  }

  $order_shipping_items = $order->get_shipping_methods();

  // Get order meta using CRUD methods
  $order_meta = array();
  
  // Billing address
  $order_meta['_billing_first_name'] = $order->get_billing_first_name();
  $order_meta['_billing_last_name'] = $order->get_billing_last_name();
  $order_meta['_billing_email'] = $order->get_billing_email();
  $order_meta['_billing_phone'] = $order->get_billing_phone();
  $order_meta['_billing_country'] = $order->get_billing_country();
  $order_meta['_billing_state'] = $order->get_billing_state();
  $order_meta['_billing_city'] = $order->get_billing_city();
  $order_meta['_billing_postcode'] = $order->get_billing_postcode();
  $order_meta['_billing_address_1'] = $order->get_billing_address_1();
  $order_meta['_billing_address_2'] = $order->get_billing_address_2();
  
  // Shipping address
  $order_meta['_shipping_first_name'] = $order->get_shipping_first_name();
  $order_meta['_shipping_last_name'] = $order->get_shipping_last_name();
  $order_meta['_shipping_country'] = $order->get_shipping_country();
  $order_meta['_shipping_state'] = $order->get_shipping_state();
  $order_meta['_shipping_city'] = $order->get_shipping_city();
  $order_meta['_shipping_postcode'] = $order->get_shipping_postcode();
  $order_meta['_shipping_address_1'] = $order->get_shipping_address_1();
  $order_meta['_shipping_address_2'] = $order->get_shipping_address_2();
  
  // Order totals
  $order_meta['_order_total'] = $order->get_total();
  $order_meta['_order_currency'] = $order->get_currency();
  $order_meta['_payment_method_title'] = $order->get_payment_method_title();

  $address_items = array(
    'postcode' => "Почтовый индекс",
    'country_name' => "Страна",
    'state' => "Регион",
    'city' => "Город",
  );
  $contact_items = array(
    'email' => "Почта",
    'phone' => "ТелефонРабочий",
  );

  $contragent_meta = $order->get_meta('wc1c_contragent', true);
  $contragents = array();
  
  foreach (array('billing', 'shipping') as $type) {
    $contragent = array();

    $name = array();
    $first_name = $type === 'billing' ? $order->get_billing_first_name() : $order->get_shipping_first_name();
    $last_name = $type === 'billing' ? $order->get_billing_last_name() : $order->get_shipping_last_name();
    
    if ($first_name) $name[] = $first_name;
    if ($last_name) $name[] = $last_name;
    
    $contragent['first_name'] = $first_name;
    $contragent['last_name'] = $last_name;

    $name = implode(' ', $name);
    if (!$name) {
      $contragent['name'] = $contragent_meta ? $contragent_meta : "Гость";
      $contragent['user_id'] = 0;
    } else {
      $contragent['name'] = $name;
      $contragent['user_id'] = $order->get_customer_id();
    }

    // Get country name
    $country_code = $type === 'billing' ? $order->get_billing_country() : $order->get_shipping_country();
    if ($country_code) {
      $countries = WC()->countries->get_countries();
      $order_meta["_{$type}_country_name"] = isset($countries[$country_code]) ? $countries[$country_code] : $country_code;
    }

    $full_address = array();
    $address_fields = array(
      'postcode' => $type === 'billing' ? $order->get_billing_postcode() : $order->get_shipping_postcode(),
      'country_name' => $order_meta["_{$type}_country_name"] ?? '',
      'state' => $type === 'billing' ? $order->get_billing_state() : $order->get_shipping_state(),
      'city' => $type === 'billing' ? $order->get_billing_city() : $order->get_shipping_city(),
      'address_1' => $type === 'billing' ? $order->get_billing_address_1() : $order->get_shipping_address_1(),
      'address_2' => $type === 'billing' ? $order->get_billing_address_2() : $order->get_shipping_address_2(),
    );
    
    foreach ($address_fields as $field_value) {
      if (!empty($field_value)) $full_address[] = $field_value;
    }
    $contragent['full_address'] = implode(", ", $full_address);

    $contragent['address'] = array();
    foreach ($address_items as $address_key => $address_item_name) {
      $field_value = $address_fields[$address_key] ?? '';
      if (!empty($field_value)) {
        $contragent['address'][$address_item_name] = $field_value;
      }
    }

    $contragent['contacts'] = array();
    if ($type === 'billing') {
      if ($order->get_billing_email()) {
        $contragent['contacts']['Почта'] = $order->get_billing_email();
      }
      if ($order->get_billing_phone()) {
        $contragent['contacts']['ТелефонРабочий'] = $order->get_billing_phone();
      }
    }

    $contragents[$type] = $contragent;
  }

  $products = array();
  foreach ($order_line_items as $order_line_item) {
    $products[] = array(
      'guid' => $order_line_item['wc1c_guid'],
      'name' => $order_line_item['name'],
      'price_per_item' => $order_line_item['line_total'] / $order_line_item['qty'],
      'quantity' => $order_line_item['qty'],
      'total' => $order_line_item['line_total'],
      'type' => "Товар",
    );
  }

  foreach ($order_shipping_items as $order_shipping_item) {
    if (!$order_shipping_item->get_total()) continue;

    $products[] = array(
      'guid' => 'ORDER_DELIVERY',
      'name' => $order_shipping_item->get_name(),
      'price_per_item' => $order_shipping_item->get_total(),
      'quantity' => 1,
      'total' => $order_shipping_item->get_total(),
      'type' => "Услуга",
    );
  }

  $statuses = array(
    'cancelled' => "Отменен",
    'trash' => "Удален",
  );
  $status = $order->get_status();
  if (array_key_exists($status, $statuses)) {
    $order_status_name = $statuses[$status];
  } else {
    $order_status_name = wc_get_order_status_name($status);
  }

  if (WC1C_CURRENCY) {
    $document_currency = WC1C_CURRENCY;
  } else {
    $document_currency = get_option('wc1c_currency', $order->get_currency());
  }

  $document = array(
    'order_id' => $order_id,
    'currency' => $document_currency,
    'total' => $order->get_total(),
    'comment' => $order->get_customer_note(),
    'contragents' => $contragents,
    'products' => $products,
    'payment_method_title' => $order->get_payment_method_title(),
    'status' => $status,
    'status_name' => $order_status_name,
    'has_shipping' => count($order_shipping_items) > 0,
    'modified_at' => $order->get_date_modified() ? $order->get_date_modified()->format('Y-m-d H:i:s') : '',
  );
  
  $order_date = $order->get_date_created();
  if ($order_date) {
    list($document['date'], $document['time']) = explode(' ', $order_date->format('Y-m-d H:i:s'), 2);
  } else {
    $document['date'] = date('Y-m-d');
    $document['time'] = date('H:i:s');
  }

  $documents[] = $document;
}

$documents = apply_filters('wc1c_query_documents', $documents);

echo '<?xml version="1.0" encoding="' . WC1C_XML_CHARSET . '"?>';
?>

<КоммерческаяИнформация ВерсияСхемы="2.05" ДатаФормирования="<?php echo date("Y-m-dTH:i:s", WC1C_TIMESTAMP) ?>">
  <?php foreach ($documents as $document): ?>
    <Документ>
      <Ид>wc1c#order#<?php echo $document['order_id'] ?></Ид>
      <Номер><?php echo $document['order_id'] ?></Номер>
      <Дата><?php echo $document['date'] ?></Дата>
      <Время><?php echo $document['time'] ?></Время>
      <ХозОперация>Заказ товара</ХозОперация>
      <Роль>Продавец</Роль>
      <Валюта><?php echo esc_xml($document['currency']) ?></Валюта>
      <Сумма><?php echo $document['total'] ?></Сумма>
      <Комментарий><?php echo esc_xml($document['comment']) ?></Комментарий>
      <Контрагенты>
        <?php foreach ($document['contragents'] as $type => $contragent): ?>
          <Контрагент>
            <Ид>wc1c#user#<?php echo $contragent['user_id'] ?></Ид>
            <Роль><?php echo $type == 'billing' ? "Плательщик" : "Получатель" ?></Роль>
            <?php if (!empty($contragent['name'])): ?>
              <Наименование><?php echo esc_xml($contragent['name']) ?></Наименование>
              <ПолноеНаименование><?php echo esc_xml($contragent['name']) ?></ПолноеНаименование>
            <?php endif ?>
            <?php if (!empty($contragent['first_name'])): ?>
              <Имя><?php echo esc_xml($contragent['first_name']) ?></Имя>
            <?php endif ?>
            <?php if (!empty($contragent['last_name'])): ?>
              <Фамилия><?php echo esc_xml($contragent['last_name']) ?></Фамилия>
            <?php endif ?>
            <?php if (!empty($contragent['full_address']) || $contragent['address']): ?>
              <АдресРегистрации>
                <?php if (!empty($contragent['full_address'])): ?>
                  <Представление><?php echo esc_xml($contragent['full_address']) ?></Представление>  
                <?php endif ?>
                <?php foreach ($contragent['address'] as $address_item_name => $address_item_value): ?>
                  <АдресноеПоле>
                    <Тип><?php echo esc_xml($address_item_name) ?></Тип>
                    <Значение><?php echo esc_xml($address_item_value) ?></Значение>
                  </АдресноеПоле>
                <?php endforeach ?>
              </АдресРегистрации>
            <?php endif ?>
            <Контакты>
              <?php foreach ($contragent['contacts'] as $contact_item_name => $contact_item_value): ?>
                <Контакт>
                  <Тип><?php echo esc_xml($contact_item_name) ?></Тип>
                  <Значение><?php echo esc_xml($contact_item_value) ?></Значение>
                </Контакт>
              <?php endforeach ?>
            </Контакты>
          </Контрагент>
        <?php endforeach ?>
      </Контрагенты>
      <Товары>
        <?php foreach ($document['products'] as $product): ?>
          <Товар>
            <?php if (!empty($product['guid'])): ?>
              <Ид><?php echo esc_xml($product['guid']) ?></Ид>
            <?php endif ?>
            <Наименование><?php echo esc_xml($product['name']) ?></Наименование>
            <БазоваяЕдиница Код="796" НаименованиеПолное="Штука" МеждународноеСокращение="PCE">шт</БазоваяЕдиница>
            <ЦенаЗаЕдиницу><?php echo $product['price_per_item'] ?></ЦенаЗаЕдиницу>
            <Количество><?php echo $product['quantity'] ?></Количество>
            <Сумма><?php echo $product['total'] ?></Сумма>
            <ЗначенияРеквизитов>
              <ЗначениеРеквизита>
                <Наименование>ТипНоменклатуры</Наименование>
                <Значение><?php echo esc_xml($product['type']) ?></Значение>
              </ЗначениеРеквизита>
            </ЗначенияРеквизитов>
          </Товар>
        <?php endforeach ?>
      </Товары>
      <ЗначенияРеквизитов>
        <?php
        $requisites = array(
          'Заказ оплачен' => !in_array($document['status'], array('on-hold', 'pending')) ? 'true' : 'false',
          'Доставка разрешена' => $document['has_shipping'] ? 'true' : 'false',
          'Отменен' => $document['status'] == 'cancelled' ? 'true' : 'false',
          'Финальный статус' => !in_array($document['status'], array('trash', 'on-hold', 'pending', 'processing')) ? 'true' : 'false',
          'Статус заказа' => $document['status_name'],
          'Дата изменения статуса' => $document['modified_at'],
        );
        if ($document['payment_method_title']) $requisites['Метод оплаты'] = $document['payment_method_title'];
        $requisites = apply_filters('wc1c_query_order_requisites', $requisites, $document);
        foreach ($requisites as $requisite_key => $requisite_value): ?>
          <ЗначениеРеквизита>
            <Наименование><?php echo esc_xml($requisite_key) ?></Наименование>
            <Значение><?php echo esc_xml($requisite_value) ?></Значение>
          </ЗначениеРеквизита>
        <?php endforeach; ?>
      </ЗначенияРеквизитов>
    </Документ>
  <?php endforeach ?>
</КоммерческаяИнформация>

<?php
foreach ($order_ids as $order_id) {
  $order = wc_get_order($order_id);
  if ($order) {
    $order->update_meta_data('wc1c_querying', 1);
    $order->save();
  }
}
?>
