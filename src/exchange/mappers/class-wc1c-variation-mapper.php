<?php
/**
 * Variation data mapper for 1C integration
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
 * Variation data mapper for 1C integration
 */
class WC1C_Variation_Mapper extends WC1C_Base_Mapper {

    /**
     * Map 1C variation data to WooCommerce variation format
     *
     * @param array $variation_data 1C variation data
     * @return array WooCommerce variation data
     */
    public function map_to_woocommerce($variation_data) {
        $mapped_data = array(
            'guid' => $this->get_value($variation_data, 'Ид', ''),
            'sku' => $this->get_value($variation_data, 'Артикул', ''),
            'manage_stock' => defined('WC1C_MANAGE_STOCK') ? (WC1C_MANAGE_STOCK === 'yes') : true,
            'stock_status' => 'instock',
            'attributes' => array()
        );

        // Map characteristics to variation attributes
        if (isset($variation_data['ХарактеристикиТовара']) && is_array($variation_data['ХарактеристикиТовара'])) {
            $mapped_data['attributes'] = $this->map_variation_attributes($variation_data['ХарактеристикиТовара']);
        }

        // Apply filters
        $mapped_data = apply_filters('wc1c_variation_mapper_data', $mapped_data, $variation_data);

        return $mapped_data;
    }

    /**
     * Map WooCommerce variation data to 1C variation format
     *
     * @param array $variation_data WooCommerce variation data
     * @return array 1C variation data
     */
    public function map_to_1c($variation_data) {
        $mapped_data = array(
            'Ид' => $this->get_value($variation_data, 'guid', ''),
            'Артикул' => $this->get_value($variation_data, 'sku', '')
        );

        // Apply filters
        $mapped_data = apply_filters('wc1c_variation_mapper_to_1c_data', $mapped_data, $variation_data);

        return $mapped_data;
    }

    /**
     * Map variation attributes
     *
     * @param array $characteristics 1C characteristics
     * @return array Variation attributes
     */
    private function map_variation_attributes($characteristics) {
        $attributes = array();

        foreach ($characteristics as $characteristic) {
            $name = $this->get_value($characteristic, 'Наименование', '');
            $value = $this->get_value($characteristic, 'Значение', '');

            if (empty($name) || empty($value)) {
                continue;
            }

            // Clean up characteristic name (remove parentheses content)
            $name = preg_replace('/\s+\(.*\)$/', '', $name);

            $attribute_key = 'attribute_' . sanitize_title($name);
            $attributes[$attribute_key] = $value;
        }

        return $attributes;
    }

    /**
     * Validate variation data
     *
     * @param array $variation_data Variation data
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate($variation_data) {
        $errors = new WP_Error();

        // Check required fields
        if (empty($variation_data['Ид'])) {
            $errors->add('missing_id', __('Variation ID is required', 'woocommerce-1c-integration'));
        }

        // Validate SKU if present
        if (!empty($variation_data['Артикул'])) {
            if (strlen($variation_data['Артикул']) > 100) {
                $errors->add('sku_too_long', __('Variation SKU is too long (max 100 characters)', 'woocommerce-1c-integration'));
            }

            // Check for invalid characters in SKU
            if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $variation_data['Артикул'])) {
                $errors->add('invalid_sku', __('Variation SKU contains invalid characters', 'woocommerce-1c-integration'));
            }
        }

        return $errors->has_errors() ? $errors : true;
    }
}