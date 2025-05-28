<?php
/**
 * Product data mapper for 1C integration
 *
 * @package    WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/exchange/mappers
 * @author     Igor Melnyk <igor.melnyk.it@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

/**
 * Product data mapper for 1C integration
 */
class WC1C_Product_Mapper extends WC1C_Base_Mapper {

    /**
     * Map 1C product data to WooCommerce product format
     *
     * @param array $product_data 1C product data
     * @return array WooCommerce product data
     */
    public function map_to_woocommerce($product_data) {
        $mapped_data = array(
            'guid' => $this->get_value($product_data, 'Ид', ''),
            'name' => $this->get_value($product_data, 'Наименование', ''),
            'description' => $this->get_value($product_data, 'Описание', ''),
            'short_description' => '',
            'sku' => $this->get_value($product_data, 'Артикул', ''),
            'status' => $this->map_product_status($this->get_value($product_data, 'Статус', '')),
            'manage_stock' => defined('WC1C_MANAGE_STOCK') ? (WC1C_MANAGE_STOCK === 'yes') : true,
            'stock_status' => 'instock',
            'categories' => array(),
            'attributes' => array(),
            'images' => array()
        );

        // Map full name if available
        $full_name = $this->get_requisite_value($product_data, 'Полное наименование');
        if (!empty($full_name)) {
            $mapped_data['name'] = $full_name;
        }

        // Map HTML description
        $html_description = $this->get_requisite_value($product_data, 'ОписаниеВФорматеHTML');
        if (!empty($html_description)) {
            if (defined('WC1C_PRODUCT_DESCRIPTION_TO_CONTENT') && WC1C_PRODUCT_DESCRIPTION_TO_CONTENT) {
                $mapped_data['description'] = $html_description;
            } else {
                $mapped_data['short_description'] = $html_description;
            }
        }

        // Map dimensions
        $mapped_data['weight'] = $this->parse_decimal($this->get_requisite_value($product_data, 'Вес'));
        $mapped_data['length'] = $this->parse_decimal($this->get_requisite_value($product_data, 'Длина'));
        $mapped_data['width'] = $this->parse_decimal($this->get_requisite_value($product_data, 'Ширина'));
        $mapped_data['height'] = $this->parse_decimal($this->get_requisite_value($product_data, 'Высота'));

        // Map categories
        if (isset($product_data['Группы']) && is_array($product_data['Группы'])) {
            $mapped_data['categories'] = $this->map_product_categories($product_data['Группы']);
        }

        // Map attributes
        if (isset($product_data['ЗначенияСвойств']) && is_array($product_data['ЗначенияСвойств'])) {
            $mapped_data['attributes'] = $this->map_product_attributes($product_data['ЗначенияСвойств']);
        }

        // Map requisites as attributes
        if (isset($product_data['ЗначенияРеквизитов']) && is_array($product_data['ЗначенияРеквизитов'])) {
            $requisite_attributes = $this->map_requisites_to_attributes($product_data['ЗначенияРеквизитов']);
            $mapped_data['attributes'] = array_merge($mapped_data['attributes'], $requisite_attributes);
        }

        // Map images
        if (isset($product_data['Картинка']) && is_array($product_data['Картинка'])) {
            $mapped_data['images'] = $this->map_product_images($product_data['Картинка']);
        }

        // Map manufacturer
        if (isset($product_data['Изготовитель']['Наименование'])) {
            $mapped_data['attributes'][] = array(
                'name' => __('Manufacturer', 'woocommerce-1c-integration'),
                'value' => $product_data['Изготовитель']['Наименование'],
                'position' => 0,
                'is_visible' => 1,
                'is_variation' => 0,
                'is_taxonomy' => 0
            );
        }

        // Map base unit
        if (isset($product_data['БазоваяЕдиница']) && !empty(trim($product_data['БазоваяЕдиница']))) {
            $mapped_data['attributes'][] = array(
                'name' => __('Base Unit', 'woocommerce-1c-integration'),
                'value' => trim($product_data['БазоваяЕдиница']),
                'position' => 0,
                'is_visible' => 1,
                'is_variation' => 0,
                'is_taxonomy' => 0
            );
        }

        // Apply filters
        $mapped_data = apply_filters('wc1c_product_mapper_data', $mapped_data, $product_data);

        return $mapped_data;
    }

    /**
     * Map WooCommerce product data to 1C product format
     *
     * @param array $product_data WooCommerce product data
     * @return array 1C product data
     */
    public function map_to_1c($product_data) {
        $mapped_data = array(
            'Ид' => $this->get_value($product_data, 'guid', ''),
            'Наименование' => $this->get_value($product_data, 'name', ''),
            'Описание' => $this->get_value($product_data, 'description', ''),
            'Артикул' => $this->get_value($product_data, 'sku', ''),
            'Статус' => $this->map_product_status_to_1c($this->get_value($product_data, 'status', 'publish'))
        );

        // Apply filters
        $mapped_data = apply_filters('wc1c_product_mapper_to_1c_data', $mapped_data, $product_data);

        return $mapped_data;
    }

    /**
     * Map product status from 1C to WooCommerce
     *
     * @param string $status_1c 1C product status
     * @return string WooCommerce product status
     */
    private function map_product_status($status_1c) {
        $status_map = array(
            'Удален' => 'trash',
            'Черновик' => 'draft',
            '' => 'publish'
        );

        return isset($status_map[$status_1c]) ? $status_map[$status_1c] : 'publish';
    }

    /**
     * Map product status from WooCommerce to 1C
     *
     * @param string $status_wc WooCommerce product status
     * @return string 1C product status
     */
    private function map_product_status_to_1c($status_wc) {
        $status_map = array(
            'trash' => 'Удален',
            'draft' => 'Черновик',
            'publish' => ''
        );

        return isset($status_map[$status_wc]) ? $status_map[$status_wc] : '';
    }

    /**
     * Map product categories
     *
     * @param array $groups 1C groups
     * @return array Category IDs
     */
    private function map_product_categories($groups) {
        $category_ids = array();

        foreach ($groups as $group_guid) {
            $category_id = $this->find_category_by_guid($group_guid);
            if ($category_id) {
                $category_ids[] = $category_id;
            }
        }

        return $category_ids;
    }

    /**
     * Map product attributes
     *
     * @param array $properties 1C properties
     * @return array Product attributes
     */
    private function map_product_attributes($properties) {
        $attributes = array();
        $guids = get_option('wc1c_guid_attributes', array());

        foreach ($properties as $property) {
            $property_guid = $this->get_value($property, 'Ид', '');
            $property_values = $this->get_value($property, 'Значение', array());

            if (empty($property_guid) || empty($property_values)) {
                continue;
            }

            // Find attribute
            if (!isset($guids[$property_guid])) {
                continue;
            }

            $attribute_id = $guids[$property_guid];
            $attribute = wc_get_attribute($attribute_id);
            
            if (!$attribute) {
                continue;
            }

            $taxonomy = $attribute->slug;
            $attribute_terms = array();
            $attribute_values = array();

            foreach ($property_values as $property_value) {
                if (empty($property_value)) {
                    continue;
                }

                // Check if it's a GUID reference to a term
                if ($attribute->type === 'select' && preg_match('/^[a-f0-9-]{36}$/i', $property_value)) {
                    $term_id = $this->find_term_by_guid($taxonomy, $property_value);
                    if ($term_id) {
                        $attribute_terms[] = $term_id;
                    }
                } else {
                    // Handle multiple values if delimiter is defined
                    if (defined('WC1C_MULTIPLE_VALUES_DELIMITER')) {
                        $values = explode(WC1C_MULTIPLE_VALUES_DELIMITER, $property_value);
                        foreach ($values as $value) {
                            $value = trim($value);
                            if (!empty($value)) {
                                $attribute_values[] = $value;
                            }
                        }
                    } else {
                        $attribute_values[] = $property_value;
                    }
                }
            }

            // Create attribute data
            $attribute_data = array(
                'name' => $taxonomy,
                'value' => implode(' | ', $attribute_values),
                'position' => count($attributes),
                'is_visible' => 1,
                'is_variation' => 0,
                'is_taxonomy' => !empty($attribute_terms)
            );

            if (!empty($attribute_terms)) {
                $attribute_data['terms'] = $attribute_terms;
                $attribute_data['taxonomy'] = $taxonomy;
            }

            $attributes[] = $attribute_data;
        }

        return $attributes;
    }

    /**
     * Map requisites to attributes
     *
     * @param array $requisites 1C requisites
     * @return array Product attributes
     */
    private function map_requisites_to_attributes($requisites) {
        $attributes = array();

        foreach ($requisites as $requisite) {
            $name = $this->get_value($requisite, 'Наименование', '');
            $values = $this->get_value($requisite, 'Значение', array());

            if (empty($name) || empty($values)) {
                continue;
            }

            // Skip file references
            if (strpos($values[0], 'import_files/') === 0) {
                continue;
            }

            // Skip special requisites that are handled elsewhere
            $skip_requisites = array(
                'Полное наименование',
                'ОписаниеВФорматеHTML',
                'Вес',
                'Длина',
                'Ширина',
                'Высота',
                'ОписаниеФайла'
            );

            if (in_array($name, $skip_requisites)) {
                continue;
            }

            // Format attribute name
            $attribute_name = $this->format_requisite_name($name);

            $attributes[] = array(
                'name' => $attribute_name,
                'value' => implode(' | ', $values),
                'position' => count($attributes),
                'is_visible' => 0,
                'is_variation' => 0,
                'is_taxonomy' => 0
            );
        }

        return $attributes;
    }

    /**
     * Map product images
     *
     * @param array $images 1C images
     * @return array Image data
     */
    private function map_product_images($images) {
        $mapped_images = array();

        foreach ($images as $image_path) {
            if (empty($image_path)) {
                continue;
            }

            $mapped_images[] = array(
                'path' => $image_path,
                'title' => '',
                'description' => ''
            );
        }

        return $mapped_images;
    }

    /**
     * Get requisite value by name
     *
     * @param array $product_data Product data
     * @param string $requisite_name Requisite name
     * @return string Requisite value
     */
    private function get_requisite_value($product_data, $requisite_name) {
        if (!isset($product_data['ЗначенияРеквизитов']) || !is_array($product_data['ЗначенияРеквизитов'])) {
            return '';
        }

        foreach ($product_data['ЗначенияРеквизитов'] as $requisite) {
            if ($this->get_value($requisite, 'Наименование', '') === $requisite_name) {
                $values = $this->get_value($requisite, 'Значение', array());
                return !empty($values) ? $values[0] : '';
            }
        }

        return '';
    }

    /**
     * Format requisite name for display
     *
     * @param string $name Requisite name
     * @return string Formatted name
     */
    private function format_requisite_name($name) {
        // Convert CamelCase to readable format
        if (strpos($name, ' ') === false) {
            $name = preg_replace_callback('/(?<!^)\p{Lu}/u', function($matches) {
                return ' ' . mb_strtolower($matches[0], 'UTF-8');
            }, $name);
        }

        return $name;
    }

    /**
     * Find category by GUID
     *
     * @param string $guid Category GUID
     * @return int Category ID or 0 if not found
     */
    private function find_category_by_guid($guid) {
        global $wpdb;

        $term_id = $wpdb->get_var($wpdb->prepare(
            "SELECT tm.term_id FROM {$wpdb->termmeta} tm 
             JOIN {$wpdb->term_taxonomy} tt ON tm.term_id = tt.term_id 
             WHERE tm.meta_key = 'wc1c_guid' AND tm.meta_value = %s AND tt.taxonomy = 'product_cat'",
            "product_cat::{$guid}"
        ));

        return $term_id ? (int) $term_id : 0;
    }

    /**
     * Find term by GUID
     *
     * @param string $taxonomy Taxonomy name
     * @param string $guid Term GUID
     * @return int Term ID or 0 if not found
     */
    private function find_term_by_guid($taxonomy, $guid) {
        global $wpdb;

        $term_id = $wpdb->get_var($wpdb->prepare(
            "SELECT tm.term_id FROM {$wpdb->termmeta} tm 
             JOIN {$wpdb->term_taxonomy} tt ON tm.term_id = tt.term_id 
             WHERE tm.meta_key = 'wc1c_guid' AND tm.meta_value = %s AND tt.taxonomy = %s",
            "{$taxonomy}::{$guid}",
            $taxonomy
        ));

        return $term_id ? (int) $term_id : 0;
    }

    /**
     * Validate product data
     *
     * @param array $product_data Product data
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate($product_data) {
        $errors = new WP_Error();

        // Check required fields
        if (empty($product_data['Ид'])) {
            $errors->add('missing_id', __('Product ID is required', 'woocommerce-1c-integration'));
        }

        if (empty($product_data['Наименование'])) {
            $errors->add('missing_name', __('Product name is required', 'woocommerce-1c-integration'));
        }

        // Check name length
        if (strlen($product_data['Наименование']) > 200) {
            $errors->add('name_too_long', __('Product name is too long (max 200 characters)', 'woocommerce-1c-integration'));
        }

        // Validate SKU if present
        if (!empty($product_data['Артикул'])) {
            if (strlen($product_data['Артикул']) > 100) {
                $errors->add('sku_too_long', __('Product SKU is too long (max 100 characters)', 'woocommerce-1c-integration'));
            }

            // Check for invalid characters in SKU
            if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $product_data['Артикул'])) {
                $errors->add('invalid_sku', __('Product SKU contains invalid characters', 'woocommerce-1c-integration'));
            }
        }

        return $errors->has_errors() ? $errors : true;
    }
}