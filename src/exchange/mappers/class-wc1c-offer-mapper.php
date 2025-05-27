<?php
/**
 * Offer data mapper for 1C integration
 *
 * @package    WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/exchange/mappers
 * @author     Igor Melnyk <igormelnykit@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * Offer data mapper for 1C integration
 */
class WC1C_Offer_Mapper extends WC1C_Base_Mapper {

    /**
     * Map 1C offer data to WooCommerce format
     *
     * @param array $offer_data 1C offer data
     * @return array WooCommerce offer data
     */
    public function map_to_woocommerce($offer_data) {
        $guid = $this->get_value($offer_data, 'Ид', '');
        
        // Determine if this is a variation offer
        $is_variation = strpos($guid, '#') !== false;
        
        $mapped_data = array(
            'guid' => $guid,
            'product_guid' => $is_variation ? explode('#', $guid)[0] : $guid,
            'variation_guid' => $is_variation ? $guid : null,
            'sku' => $this->get_value($offer_data, 'Артикул', ''),
            'price' => null,
            'stock_quantity' => null
        );

        // Map price
        if (isset($offer_data['Цена']['ЦенаЗаЕдиницу'])) {
            $price = $this->parse_decimal($offer_data['Цена']['ЦенаЗаЕдиницу']);
            
            // Apply coefficient if present
            if (isset($offer_data['Цена']['Коэффициент'])) {
                $coefficient = $this->parse_decimal($offer_data['Цена']['Коэффициент']);
                if ($coefficient > 0) {
                    $price *= $coefficient;
                }
            }
            
            $mapped_data['price'] = $price;
        }

        // Map stock quantity
        $stock_quantity = $this->get_value($offer_data, 'Количество', null);
        if ($stock_quantity === null) {
            $stock_quantity = $this->get_value($offer_data, 'КоличествоНаСкладе', null);
        }
        
        if ($stock_quantity !== null) {
            $mapped_data['stock_quantity'] = $this->parse_decimal($stock_quantity);
        }

        // Apply filters
        $mapped_data = apply_filters('wc1c_offer_mapper_data', $mapped_data, $offer_data);

        return $mapped_data;
    }

    /**
     * Map WooCommerce offer data to 1C format
     *
     * @param array $offer_data WooCommerce offer data
     * @return array 1C offer data
     */
    public function map_to_1c($offer_data) {
        $mapped_data = array(
            'Ид' => $this->get_value($offer_data, 'guid', ''),
            'Артикул' => $this->get_value($offer_data, 'sku', ''),
            'Количество' => $this->get_value($offer_data, 'stock_quantity', 0)
        );

        // Map price
        if (isset($offer_data['price'])) {
            $mapped_data['Цена'] = array(
                'ЦенаЗаЕдиницу' => $offer_data['price']
            );
        }

        // Apply filters
        $mapped_data = apply_filters('wc1c_offer_mapper_to_1c_data', $mapped_data, $offer_data);

        return $mapped_data;
    }

    /**
     * Validate offer data
     *
     * @param array $offer_data Offer data
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate($offer_data) {
        $errors = new WP_Error();

        // Check required fields
        if (empty($offer_data['Ид'])) {
            $errors->add('missing_id', __('Offer ID is required', 'woocommerce-1c-integration'));
        }

        // Validate price if present
        if (isset($offer_data['Цена']['ЦенаЗаЕдиницу'])) {
            $price = $this->parse_decimal($offer_data['Цена']['ЦенаЗаЕдиницу']);
            if ($price < 0) {
                $errors->add('invalid_price', __('Offer price cannot be negative', 'woocommerce-1c-integration'));
            }
        }

        // Validate stock quantity if present
        $stock_quantity = $this->get_value($offer_data, 'Количество', null);
        if ($stock_quantity === null) {
            $stock_quantity = $this->get_value($offer_data, 'КоличествоНаСкладе', null);
        }
        
        if ($stock_quantity !== null) {
            $quantity = $this->parse_decimal($stock_quantity);
            if ($quantity < 0) {
                $errors->add('invalid_quantity', __('Offer stock quantity cannot be negative', 'woocommerce-1c-integration'));
            }
        }

        return $errors->has_errors() ? $errors : true;
    }
}