<?php
/**
 * Catalog processor for WooCommerce 1C Integration
 *
 * Handles importing product catalog data from 1C:Enterprise
 *
 * @package WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/exchange/processors
 * @author Igor Melnyk <igor.melnyk.it@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * Catalog processor class
 */
class WC1C_Processor_Catalog {

    /**
     * XML Reader instance
     *
     * @var XMLReader
     */
    private $reader;

    /**
     * Import start time
     *
     * @var float
     */
    private $start_time;

    /**
     * Products processed count
     *
     * @var int
     */
    private $products_count = 0;

    /**
     * Categories processed count
     *
     * @var int
     */
    private $categories_count = 0;

    /**
     * Current node data
     *
     * @var array
     */
    private $current_data = array();

    /**
     * Process catalog import
     *
     * @param string $file_path Path to XML file
     * @return bool Success status
     */
    public function process($file_path) {
        $this->start_time = microtime(true);
        WC1C_Logger::info('Starting catalog import processing', array('file' => basename($file_path)));
        
        // Create XMLReader instance
        $this->reader = new XMLReader();
        
        // Try to open the file
        $result = $this->reader->open($file_path, 'UTF-8', LIBXML_NONET | LIBXML_NOCDATA);
        if (!$result) {
            WC1C_Logger::error('Failed to open XML file', array('file' => basename($file_path)));
            return false;
        }
        
        // Start processing
        try {
            // Check if WooCommerce is active
            if (!function_exists('wc_get_product')) {
                throw new Exception('WooCommerce functions not available');
            }
            
            // Process the XML file
            $this->process_xml();
            
            // Close the reader
            $this->reader->close();
            
            $execution_time = microtime(true) - $this->start_time;
            
            // Log successful completion
            WC1C_Logger::info('Catalog import completed', array(
                'execution_time' => $execution_time,
                'products_count' => $this->products_count,
                'categories_count' => $this->categories_count
            ));
            
            // Trigger action for post-processing
            do_action('wc1c_catalog_import_complete', $this->products_count, $this->categories_count);
            
            return true;
            
        } catch (Exception $e) {
            $this->reader->close();
            WC1C_Logger::error('Catalog import failed: ' . $e->getMessage(), array(
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
            return false;
        }
    }

    /**
     * Process XML file
     */
    private function process_xml() {
        // Move to the first element
        while ($this->reader->read() && $this->reader->nodeType !== XMLReader::ELEMENT);
        
        // Check root element
        if ($this->reader->name !== 'КоммерческаяИнформация') {
            throw new Exception('Invalid XML format: root element must be КоммерческаяИнформация');
        }
        
        // Get schema version
        $version = $this->reader->getAttribute('ВерсияСхемы');
        if (!$version) {
            $version = '2.05';
        }
        
        WC1C_Logger::info('Schema version: ' . $version);
        
        // Process nodes
        while ($this->reader->read()) {
            if ($this->reader->nodeType === XMLReader::ELEMENT) {
                switch ($this->reader->name) {
                    case 'Классификатор':
                        $this->process_classifier();
                        break;
                        
                    case 'Товары':
                    case 'Каталог':
                        $this->process_products();
                        break;
                }
            }
        }
    }

    /**
     * Process classifier (categories, properties, etc.)
     */
    private function process_classifier() {
        // Skip if classifier processing is disabled
        if (get_option('wc1c_create_categories', 'yes') !== 'yes') {
            WC1C_Logger::info('Skipping classifier processing (disabled in settings)');
            $this->skip_node();
            return;
        }
        
        $in_groups = false;
        $in_properties = false;
        
        // Process classifier nodes
        while ($this->reader->read() && 
               !($this->reader->nodeType === XMLReader::END_ELEMENT && $this->reader->name === 'Классификатор')) {
            
            if ($this->reader->nodeType === XMLReader::ELEMENT) {
                switch ($this->reader->name) {
                    case 'Группы':
                        $in_groups = true;
                        break;
                        
                    case 'Свойства':
                        $in_properties = true;
                        break;
                        
                    case 'Группа':
                        if ($in_groups) {
                            $this->process_category();
                        } else {
                            $this->skip_node();
                        }
                        break;
                        
                    case 'Свойство':
                        if ($in_properties) {
                            $this->process_property();
                        } else {
                            $this->skip_node();
                        }
                        break;
                }
            } elseif ($this->reader->nodeType === XMLReader::END_ELEMENT) {
                if ($this->reader->name === 'Группы') {
                    $in_groups = false;
                } elseif ($this->reader->name === 'Свойства') {
                    $in_properties = false;
                }
            }
        }
    }

    /**
     * Process category
     */
    private function process_category() {
        $category = array(
            'id' => '',
            'name' => '',
            'parent_id' => ''
        );
        
        // Read category data
        while ($this->reader->read() && 
               !($this->reader->nodeType === XMLReader::END_ELEMENT && $this->reader->name === 'Группа')) {
            
            if ($this->reader->nodeType === XMLReader::ELEMENT) {
                switch ($this->reader->name) {
                    case 'Ид':
                        $category['id'] = $this->get_element_text();
                        break;
                        
                    case 'Наименование':
                        $category['name'] = $this->get_element_text();
                        break;
                        
                    case 'ИдРодителя':
                        $category['parent_id'] = $this->get_element_text();
                        break;
                }
            }
        }
        
        // Skip if empty or invalid
        if (empty($category['id']) || empty($category['name'])) {
            return;
        }
        
        // Create or update category
        $this->create_or_update_category($category);
        $this->categories_count++;
    }

    /**
     * Create or update category
     *
     * @param array $category Category data
     * @return int Term ID
     */
    private function create_or_update_category($category) {
        // Look for existing category by meta
        $term_id = WC1C::get_term_id_by_meta('_wc1c_id', $category['id']);
        
        // Get parent term ID
        $parent_id = 0;
        if (!empty($category['parent_id'])) {
            $parent_id = WC1C::get_term_id_by_meta('_wc1c_id', $category['parent_id']);
            if (!$parent_id) {
                $parent_id = 0;
            }
        }
        
        // Create or update term
        if ($term_id) {
            // Update existing term
            wp_update_term($term_id, 'product_cat', array(
                'name' => $category['name'],
                'parent' => $parent_id
            ));
            
            WC1C_Logger::debug('Updated category', array(
                'name' => $category['name'],
                'term_id' => $term_id,
                'parent_id' => $parent_id
            ));
            
        } else {
            // Create new term
            $result = wp_insert_term($category['name'], 'product_cat', array(
                'parent' => $parent_id
            ));
            
            if (is_wp_error($result)) {
                WC1C_Logger::error('Failed to create category', array(
                    'name' => $category['name'],
                    'error' => $result->get_error_message()
                ));
                return 0;
            }
            
            $term_id = $result['term_id'];
            
            // Add 1C ID as term meta
            update_term_meta($term_id, '_wc1c_id', $category['id']);
            
            WC1C_Logger::debug('Created category', array(
                'name' => $category['name'],
                'term_id' => $term_id,
                'parent_id' => $parent_id
            ));
        }
        
        return $term_id;
    }

    /**
     * Process property (attribute)
     */
    private function process_property() {
        $property = array(
            'id' => '',
            'name' => '',
            'values' => array()
        );
        
        $in_values = false;
        
        // Read property data
        while ($this->reader->read() && 
               !($this->reader->nodeType === XMLReader::END_ELEMENT && $this->reader->name === 'Свойство')) {
            
            if ($this->reader->nodeType === XMLReader::ELEMENT) {
                switch ($this->reader->name) {
                    case 'Ид':
                        $property['id'] = $this->get_element_text();
                        break;
                        
                    case 'Наименование':
                        $property['name'] = $this->get_element_text();
                        break;
                        
                    case 'ВариантыЗначений':
                        $in_values = true;
                        break;
                        
                    case 'ВариантЗначения':
                        if ($in_values) {
                            $value = $this->process_property_value();
                            if (!empty($value)) {
                                $property['values'][] = $value;
                            }
                        }
                        break;
                }
            } elseif ($this->reader->nodeType === XMLReader::END_ELEMENT) {
                if ($this->reader->name === 'ВариантыЗначений') {
                    $in_values = false;
                }
            }
        }
        
        // Skip if empty or invalid
        if (empty($property['id']) || empty($property['name'])) {
            return;
        }
        
        // Create or update attribute
        $this->create_or_update_attribute($property);
    }

    /**
     * Process property value
     *
     * @return array Value data
     */
    private function process_property_value() {
        $value = array(
            'id' => '',
            'value' => ''
        );
        
        // Read value data
        while ($this->reader->read() && 
               !($this->reader->nodeType === XMLReader::END_ELEMENT && $this->reader->name === 'ВариантЗначения')) {
            
            if ($this->reader->nodeType === XMLReader::ELEMENT) {
                switch ($this->reader->name) {
                    case 'Ид':
                        $value['id'] = $this->get_element_text();
                        break;
                        
                    case 'Значение':
                        $value['value'] = $this->get_element_text();
                        break;
                }
            }
        }
        
        return (!empty($value['id']) && !empty($value['value'])) ? $value : array();
    }

    /**
     * Create or update attribute
     *
     * @param array $property Attribute data
     * @return int Attribute ID
     */
    private function create_or_update_attribute($property) {
        global $wpdb;
        
        // Prepare attribute name (slug)
        $attribute_name = wc_sanitize_taxonomy_name($property['name']);
        
        // Check if attribute exists
        $attribute_id = wc_attribute_taxonomy_id_by_name($attribute_name);
        
        if ($attribute_id) {
            // Update existing attribute
            $wpdb->update(
                $wpdb->prefix . 'woocommerce_attribute_taxonomies',
                array('attribute_label' => $property['name']),
                array('attribute_id' => $attribute_id),
                array('%s'),
                array('%d')
            );
            
            WC1C_Logger::debug('Updated attribute', array(
                'name' => $property['name'],
                'attribute_id' => $attribute_id
            ));
            
        } else {
            // Create new attribute
            $wpdb->insert(
                $wpdb->prefix . 'woocommerce_attribute_taxonomies',
                array(
                    'attribute_label' => $property['name'],
                    'attribute_name' => $attribute_name,
                    'attribute_type' => 'select',
                    'attribute_orderby' => 'menu_order',
                    'attribute_public' => 0
                ),
                array('%s', '%s', '%s', '%s', '%d')
            );
            
            $attribute_id = $wpdb->insert_id;
            
            WC1C_Logger::debug('Created attribute', array(
                'name' => $property['name'],
                'attribute_id' => $attribute_id
            ));
        }
        
        // Store 1C ID in attribute meta
        update_option('wc1c_attribute_' . $attribute_id, $property['id']);
        
        // Get taxonomy name
        $taxonomy = wc_attribute_taxonomy_name($attribute_name);
        
        // Register taxonomy if needed
        if (!taxonomy_exists($taxonomy)) {
            register_taxonomy(
                $taxonomy,
                apply_filters('woocommerce_taxonomy_objects_' . $taxonomy, array('product')),
                apply_filters('woocommerce_taxonomy_args_' . $taxonomy, array(
                    'hierarchical' => true,
                    'show_ui' => false,
                    'query_var' => true,
                    'rewrite' => false,
                ))
            );
        }
        
        // Create or update attribute values
        foreach ($property['values'] as $value) {
            $term = term_exists($value['value'], $taxonomy);
            
            if (!$term) {
                $term = wp_insert_term($value['value'], $taxonomy);
                if (!is_wp_error($term)) {
                    update_term_meta($term['term_id'], '_wc1c_id', $value['id']);
                }
            } elseif (is_array($term)) {
                update_term_meta($term['term_id'], '_wc1c_id', $value['id']);
            }
        }
        
        // Clear caches
        delete_transient('wc_attribute_taxonomies');
        
        return $attribute_id;
    }

    /**
     * Process products
     */
    private function process_products() {
        // Process product nodes
        while ($this->reader->read() && 
               !($this->reader->nodeType === XMLReader::END_ELEMENT && 
                 ($this->reader->name === 'Товары' || $this->reader->name === 'Каталог'))) {
            
            if ($this->reader->nodeType === XMLReader::ELEMENT && $this->reader->name === 'Товар') {
                $this->process_product();
            }
        }
    }

    /**
     * Process product
     */
    private function process_product() {
        $product = array(
            'id' => '',
            'name' => '',
            'description' => '',
            'categories' => array(),
            'sku' => '',
            'barcode' => '',
            'attributes' => array(),
            'images' => array(),
            'prices' => array(),
            'vat' => '',
            'manufacturer' => '',
            'country' => '',
            'weight' => '',
            'height' => '',
            'width' => '',
            'length' => ''
        );
        
        $in_requisites = false;
        $in_images = false;
        
        // Read product data
        while ($this->reader->read() && 
               !($this->reader->nodeType === XMLReader::END_ELEMENT && $this->reader->name === 'Товар')) {
            
            if ($this->reader->nodeType === XMLReader::ELEMENT) {
                switch ($this->reader->name) {
                    case 'Ид':
                        $product['id'] = $this->get_element_text();
                        break;
                        
                    case 'Наименование':
                        $product['name'] = $this->get_element_text();
                        break;
                        
                    case 'Описание':
                        $product['description'] = $this->get_element_text();
                        break;
                        
                    case 'Группы':
                        $product['categories'] = $this->process_product_categories();
                        break;
                        
                    case 'Артикул':
                        $product['sku'] = $this->get_element_text();
                        break;
                        
                    case 'Штрихкод':
                        $product['barcode'] = $this->get_element_text();
                        break;
                        
                    case 'ЗначенияСвойств':
                        $product['attributes'] = $this->process_product_attributes();
                        break;
                        
                    case 'Картинки':
                        $in_images = true;
                        break;
                        
                    case 'Картинка':
                        if ($in_images) {
                            $image = $this->get_element_text();
                            if (!empty($image)) {
                                $product['images'][] = $image;
                            }
                        }
                        break;
                        
                    case 'Цены':
                        $product['prices'] = $this->process_product_prices();
                        break;
                        
                    case 'СтавкиНалогов':
                        $product['vat'] = $this->process_product_vat();
                        break;
                        
                    case 'Изготовитель':
                        $product['manufacturer'] = $this->get_element_text();
                        break;
                        
                    case 'СтранаПроисхождения':
                        $product['country'] = $this->get_element_text();
                        break;
                        
                    case 'Вес':
                        $product['weight'] = $this->get_element_text();
                        break;
                        
                    case 'Высота':
                        $product['height'] = $this->get_element_text();
                        break;
                        
                    case 'Ширина':
                        $product['width'] = $this->get_element_text();
                        break;
                        
                    case 'Длина':
                        $product['length'] = $this->get_element_text();
                        break;
                        
                    case 'ЗначениеРеквизита':
                        if ($in_requisites) {
                            $this->process_product_requisite($product);
                        } else {
                            $this->skip_node();
                        }
                        break;
                        
                    case 'ЗначенияРеквизитов':
                        $in_requisites = true;
                        break;
                }
            } elseif ($this->reader->nodeType === XMLReader::END_ELEMENT) {
                if ($this->reader->name === 'Картинки') {
                    $in_images = false;
                } elseif ($this->reader->name === 'ЗначенияРеквизитов') {
                    $in_requisites = false;
                }
            }
        }
        
        // Skip if empty or invalid
        if (empty($product['id']) || empty($product['name'])) {
            return;
        }
        
        // Create or update product
        $this->create_or_update_product($product);
        $this->products_count++;
    }

    /**
     * Process product categories
     *
     * @return array Category IDs
     */
    private function process_product_categories() {
        $categories = array();
        
        // Read categories
        while ($this->reader->read() && 
               !($this->reader->nodeType === XMLReader::END_ELEMENT && $this->reader->name === 'Группы')) {
            
            if ($this->reader->nodeType === XMLReader::ELEMENT && $this->reader->name === 'Ид') {
                $category_id = $this->get_element_text();
                if (!empty($category_id)) {
                    $categories[] = $category_id;
                }
            }
        }
        
        return $categories;
    }

    /**
     * Process product attributes
     *
     * @return array Attributes
     */
    private function process_product_attributes() {
        $attributes = array();
        
        // Read attributes
        while ($this->reader->read() && 
               !($this->reader->nodeType === XMLReader::END_ELEMENT && $this->reader->name === 'ЗначенияСвойств')) {
            
            if ($this->reader->nodeType === XMLReader::ELEMENT && $this->reader->name === 'ЗначенияСвойства') {
                $attribute = $this->process_product_attribute();
                if (!empty($attribute)) {
                    $attributes[] = $attribute;
                }
            }
        }
        
        return $attributes;
    }

    /**
     * Process product attribute
     *
     * @return array Attribute data
     */
    private function process_product_attribute() {
        $attribute = array(
            'id' => '',
            'value' => ''
        );
        
        // Read attribute data
        while ($this->reader->read() && 
               !($this->reader->nodeType === XMLReader::END_ELEMENT && $this->reader->name === 'ЗначенияСвойства')) {
            
            if ($this->reader->nodeType === XMLReader::ELEMENT) {
                switch ($this->reader->name) {
                    case 'Ид':
                        $attribute['id'] = $this->get_element_text();
                        break;
                        
                    case 'Значение':
                        $attribute['value'] = $this->get_element_text();
                        break;
                }
            }
        }
        
        return (!empty($attribute['id'])) ? $attribute : array();
    }

    /**
     * Process product prices
     *
     * @return array Prices
     */
    private function process_product_prices() {
        $prices = array();
        
        // Read prices
        while ($this->reader->read() && 
               !($this->reader->nodeType === XMLReader::END_ELEMENT && $this->reader->name === 'Цены')) {
            
            if ($this->reader->nodeType === XMLReader::ELEMENT && $this->reader->name === 'Цена') {
                $price = $this->process_product_price();
                if (!empty($price)) {
                    $prices[] = $price;
                }
            }
        }
        
        return $prices;
    }

    /**
     * Process product price
     *
     * @return array Price data
     */
    private function process_product_price() {
        $price = array(
            'type' => '',
            'currency' => '',
            'value' => ''
        );
        
        // Read price data
        while ($this->reader->read() && 
               !($this->reader->nodeType === XMLReader::END_ELEMENT && $this->reader->name === 'Цена')) {
            
            if ($this->reader->nodeType === XMLReader::ELEMENT) {
                switch ($this->reader->name) {
                    case 'ИдТипаЦены':
                        $price['type'] = $this->get_element_text();
                        break;
                        
                    case 'Валюта':
                        $price['currency'] = $this->get_element_text();
                        break;
                        
                    case 'ЦенаЗаЕдиницу':
                        $price['value'] = $this->get_element_text();
                        break;
                }
            }
        }
        
        return (!empty($price['value'])) ? $price : array();
    }

    /**
     * Process product VAT
     *
     * @return string VAT rate
     */
    private function process_product_vat() {
        $vat = '';
        
        // Read VAT data
        while ($this->reader->read() && 
               !($this->reader->nodeType === XMLReader::END_ELEMENT && $this->reader->name === 'СтавкиНалогов')) {
            
            if ($this->reader->nodeType === XMLReader::ELEMENT && $this->reader->name === 'СтавкаНалога') {
                $this->process_vat_rate($vat);
            }
        }
        
        return $vat;
    }

    /**
     * Process VAT rate
     *
     * @param string &$vat VAT rate reference
     */
    private function process_vat_rate(&$vat) {
        $name = '';
        $rate = '';
        
        // Read VAT rate data
        while ($this->reader->read() && 
               !($this->reader->nodeType === XMLReader::END_ELEMENT && $this->reader->name === 'СтавкаНалога')) {
            
            if ($this->reader->nodeType === XMLReader::ELEMENT) {
                switch ($this->reader->name) {
                    case 'Наименование':
                        $name = $this->get_element_text();
                        break;
                        
                    case 'Ставка':
                        $rate = $this->get_element_text();
                        break;
                }
            }
        }
        
        if ($name === 'НДС') {
            $vat = $rate;
        }
    }

    /**
     * Process product requisite
     *
     * @param array &$product Product data reference
     */
    private function process_product_requisite(&$product) {
        $name = '';
        $value = '';
        
        // Read requisite data
        while ($this->reader->read() && 
               !($this->reader->nodeType === XMLReader::END_ELEMENT && $this->reader->name === 'ЗначениеРеквизита')) {
            
            if ($this->reader->nodeType === XMLReader::ELEMENT) {
                switch ($this->reader->name) {
                    case 'Наименование':
                        $name = $this->get_element_text();
                        break;
                        
                    case 'Значение':
                        $value = $this->get_element_text();
                        break;
                }
            }
        }
        
        // Map requisites to product data
        if (!empty($name) && !empty($value)) {
            switch ($name) {
                case 'Вес':
                    $product['weight'] = $value;
                    break;
                    
                case 'Высота':
                    $product['height'] = $value;
                    break;
                    
                case 'Ширина':
                    $product['width'] = $value;
                    break;
                    
                case 'Длина':
                    $product['length'] = $value;
                    break;
                    
                case 'Бренд':
                case 'Производитель':
                    $product['manufacturer'] = $value;
                    break;
                    
                // Add more mappings as needed
            }
            
            // Store all requisites for custom handling
            if (!isset($product['requisites'])) {
                $product['requisites'] = array();
            }
            
            $product['requisites'][$name] = $value;
        }
    }

    /**
     * Create or update product
     *
     * @param array $data Product data
     * @return int Product ID
     */
    private function create_or_update_product($data) {
        // Check if product exists
        $product_id = WC1C::get_post_id_by_meta('_wc1c_id', $data['id']);
        $update_existing = get_option('wc1c_update_existing', 'yes') === 'yes';
        
        // Skip update if disabled
        if ($product_id && !$update_existing) {
            WC1C_Logger::debug('Skipping product update (disabled in settings)', array(
                'product_id' => $product_id,
                'name' => $data['name']
            ));
            return $product_id;
        }
        
        // Prepare product data
        $product_data = array(
            'post_title' => $data['name'],
            'post_content' => $data['description'],
            'post_status' => 'publish',
            'post_type' => 'product'
        );
        
        // Get main price
        $regular_price = '';
        $sale_price = '';
        
        if (!empty($data['prices'])) {
            // Get price based on configured price type
            $price_type = get_option('wc1c_price_type', '');
            
            foreach ($data['prices'] as $price) {
                if (empty($price_type) || $price['type'] === $price_type) {
                    $regular_price = WC1C::parse_decimal($price['value']);
                    break;
                }
            }
            
            // If no matching price type, use the first one
            if (empty($regular_price) && !empty($data['prices'][0]['value'])) {
                $regular_price = WC1C::parse_decimal($data['prices'][0]['value']);
            }
        }
        
        if ($product_id) {
            // Update existing product
            $product_data['ID'] = $product_id;
            $product_id = wp_update_post($product_data);
            
            WC1C_Logger::debug('Updated product', array(
                'product_id' => $product_id,
                'name' => $data['name']
            ));
            
        } else {
            // Create new product
            $product_id = wp_insert_post($product_data);
            
            // Store 1C ID as post meta
            if ($product_id) {
                update_post_meta($product_id, '_wc1c_id', $data['id']);
            }
            
            WC1C_Logger::debug('Created product', array(
                'product_id' => $product_id,
                'name' => $data['name']
            ));
        }
        
        if (is_wp_error($product_id)) {
            WC1C_Logger::error('Failed to save product', array(
                'name' => $data['name'],
                'error' => $product_id->get_error_message()
            ));
            return 0;
        }
        
        // Update product meta
        if (!empty($data['sku'])) {
            update_post_meta($product_id, '_sku', $data['sku']);
        }
        
        // Update prices
        if (!empty($regular_price)) {
            update_post_meta($product_id, '_regular_price', $regular_price);
            update_post_meta($product_id, '_price', $regular_price);
        }
        
        if (!empty($sale_price)) {
            update_post_meta($product_id, '_sale_price', $sale_price);
            if ($sale_price < $regular_price) {
                update_post_meta($product_id, '_price', $sale_price);
            }
        }
        
        // Update barcode
        if (!empty($data['barcode'])) {
            update_post_meta($product_id, '_wc1c_barcode', $data['barcode']);
        }
        
        // Update dimensions
        if (!empty($data['weight'])) {
            update_post_meta($product_id, '_weight', WC1C::parse_decimal($data['weight']));
        }
        
        if (!empty($data['length'])) {
            update_post_meta($product_id, '_length', WC1C::parse_decimal($data['length']));
        }
        
        if (!empty($data['width'])) {
            update_post_meta($product_id, '_width', WC1C::parse_decimal($data['width']));
        }
        
        if (!empty($data['height'])) {
            update_post_meta($product_id, '_height', WC1C::parse_decimal($data['height']));
        }
        
        // Update categories
        if (!empty($data['categories'])) {
            $category_ids = array();
            
            foreach ($data['categories'] as $category_1c_id) {
                $term_id = WC1C::get_term_id_by_meta('_wc1c_id', $category_1c_id);
                if ($term_id) {
                    $category_ids[] = $term_id;
                }
            }
            
            if (!empty($category_ids)) {
                wp_set_object_terms($product_id, $category_ids, 'product_cat');
            }
        }
        
        // Update attributes
        if (!empty($data['attributes'])) {
            $this->update_product_attributes($product_id, $data['attributes']);
        }
        
        // Update images
        if (!empty($data['images'])) {
            $this->update_product_images($product_id, $data['images']);
        }
        
        // Set default visibility
        update_post_meta($product_id, '_visibility', 'visible');
        
        // Set stock status if needed
        $stock_management = get_option('wc1c_stock_management', 'yes') === 'yes';
        if ($stock_management) {
            // Default to in stock, will be updated by offers import
            update_post_meta($product_id, '_manage_stock', 'yes');
            update_post_meta($product_id, '_stock_status', 'instock');
            update_post_meta($product_id, '_stock', 0);
        }
        
        // Set product type (simple by default)
        wp_set_object_terms($product_id, 'simple', 'product_type');
        
        // Store original 1C data for reference
        update_post_meta($product_id, '_wc1c_raw_data', maybe_serialize($data));
        
        // Allow extensions to modify product
        do_action('wc1c_after_product_created', $product_id, $data);
        
        return $product_id;
    }

    /**
     * Update product attributes
     *
     * @param int $product_id Product ID
     * @param array $attributes Attributes data
     */
    private function update_product_attributes($product_id, $attributes) {
        $product_attributes = array();
        
        foreach ($attributes as $attribute) {
            if (empty($attribute['id']) || empty($attribute['value'])) {
                continue;
            }
            
            // Find attribute by 1C ID
            $attribute_id = $this->get_attribute_id_by_1c_id($attribute['id']);
            
            if (!$attribute_id) {
                continue;
            }
            
            // Get attribute info
            $attribute_info = WC1C::get_woocommerce_attribute_by_id($attribute_id);
            if (!$attribute_info) {
                continue;
            }
            
            // Get taxonomy name
            $taxonomy = wc_attribute_taxonomy_name($attribute_info->attribute_name);
            
            // Find term by value
            $term = get_term_by('name', $attribute['value'], $taxonomy);
            
            if (!$term) {
                // Create term if it doesn't exist
                $term_result = wp_insert_term($attribute['value'], $taxonomy);
                
                if (is_wp_error($term_result)) {
                    continue;
                }
                
                $term_id = $term_result['term_id'];
            } else {
                $term_id = $term->term_id;
            }
            
            // Add term to product
            wp_set_object_terms($product_id, $term_id, $taxonomy, true);
            
            // Add to product attributes array
            $product_attributes[$taxonomy] = array(
                'name' => $taxonomy,
                'value' => '',
                'position' => count($product_attributes),
                'is_visible' => 1,
                'is_variation' => 0,
                'is_taxonomy' => 1
            );
        }
        
        // Save product attributes
        update_post_meta($product_id, '_product_attributes', $product_attributes);
    }

    /**
     * Get attribute ID by 1C ID
     *
     * @param string $id_1c 1C attribute ID
     * @return int|false Attribute ID or false if not found
     */
    private function get_attribute_id_by_1c_id($id_1c) {
        global $wpdb;
        
        $attributes = $wpdb->get_results("SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies");
        
        foreach ($attributes as $attribute) {
            $stored_id = get_option('wc1c_attribute_' . $attribute->attribute_id);
            
            if ($stored_id === $id_1c) {
                return $attribute->attribute_id;
            }
        }
        
        return false;
    }

    /**
     * Update product images
     *
     * @param int $product_id Product ID
     * @param array $images Image filenames
     */
    private function update_product_images($product_id, $images) {
        // Get images directory
        $images_dir = get_option('wc1c_images_dir', WC1C_DATA_DIR . 'images/');
        
        // Ensure trailing slash
        $images_dir = trailingslashit($images_dir);
        
        // Array to store attachment IDs
        $attachment_ids = array();
        
        // Process each image
        foreach ($images as $index => $image_path) {
            // Skip empty paths
            if (empty($image_path)) {
                continue;
            }
            
            // Clean up the path
            $image_path = sanitize_text_field($image_path);
            
            // Build full path
            $full_path = $images_dir . $image_path;
            
            // Skip if file doesn't exist
            if (!file_exists($full_path)) {
                WC1C_Logger::debug('Image file not found', array(
                    'product_id' => $product_id,
                    'path' => $full_path
                ));
                continue;
            }
            
            // Get file info
            $file_type = wp_check_filetype(basename($full_path), null);
            
            // Skip if not a valid image type
            if (empty($file_type['type'])) {
                continue;
            }
            
            // Prepare attachment data
            $attachment = array(
                'post_mime_type' => $file_type['type'],
                'post_title' => sanitize_file_name(basename($full_path)),
                'post_content' => '',
                'post_status' => 'inherit'
            );
            
            // Check if image already exists
            $existing_attachment_id = $this->get_attachment_id_by_filename(basename($full_path));
            
            if ($existing_attachment_id) {
                // Use existing attachment
                $attachment_ids[] = $existing_attachment_id;
                continue;
            }
            
            // Insert the attachment
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['path'] . '/' . basename($full_path);
            
            // Copy file to uploads directory
            if (!copy($full_path, $file_path)) {
                WC1C_Logger::error('Failed to copy image file', array(
                    'product_id' => $product_id,
                    'source' => $full_path,
                    'destination' => $file_path
                ));
                continue;
            }
            
            // Insert attachment
            $attachment_id = wp_insert_attachment($attachment, $file_path, $product_id);
            
            // Skip if error
            if (is_wp_error($attachment_id)) {
                WC1C_Logger::error('Failed to insert attachment', array(
                    'product_id' => $product_id,
                    'error' => $attachment_id->get_error_message()
                ));
                continue;
            }
            
            // Generate attachment metadata
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
            wp_update_attachment_metadata($attachment_id, $attachment_data);
            
            // Add to attachment IDs
            $attachment_ids[] = $attachment_id;
        }
        
        // Set featured image (first image)
        if (!empty($attachment_ids)) {
            set_post_thumbnail($product_id, $attachment_ids[0]);
            
            // Add other images to product gallery
            if (count($attachment_ids) > 1) {
                $gallery_ids = array_slice($attachment_ids, 1);
                update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
            }
        }
    }

    /**
     * Get attachment ID by filename
     *
     * @param string $filename Image filename
     * @return int|false Attachment ID or false if not found
     */
    private function get_attachment_id_by_filename($filename) {
        global $wpdb;
        
        $attachment = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE guid LIKE %s
             AND post_type = 'attachment'",
            '%' . $filename
        ));
        
        return !empty($attachment[0]) ? (int) $attachment[0] : false;
    }

    /**
     * Skip current node and its children
     */
    private function skip_node() {
        $depth = 1;
        
        while ($depth > 0 && $this->reader->read()) {
            if ($this->reader->nodeType === XMLReader::ELEMENT && !$this->reader->isEmptyElement) {
                $depth++;
            } elseif ($this->reader->nodeType === XMLReader::END_ELEMENT) {
                $depth--;
            }
        }
    }

    /**
     * Get element text content
     *
     * @return string Text content
     */
    private function get_element_text() {
        // Return empty string if the element is empty
        if ($this->reader->isEmptyElement) {
            return '';
        }
        
        $text = '';
        
        // Read until we find the end of the element
        while ($this->reader->read()) {
            if ($this->reader->nodeType === XMLReader::TEXT || 
                $this->reader->nodeType === XMLReader::CDATA || 
                $this->reader->nodeType === XMLReader::WHITESPACE || 
                $this->reader->nodeType === XMLReader::SIGNIFICANT_WHITESPACE) {
                $text .= $this->reader->value;
            } elseif ($this->reader->nodeType === XMLReader::END_ELEMENT) {
                break;
            } elseif ($this->reader->nodeType === XMLReader::ELEMENT) {
                // Handle nested elements - we skip them and continue
                $this->skip_node();
            }
        }
        
        return trim($text);
    }
}