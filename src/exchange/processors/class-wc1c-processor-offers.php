<?php
/**
 * Offers processor for WooCommerce 1C Integration
 *
 * Handles importing offers (prices and stock) data from 1C:Enterprise
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
 * Offers processor class
 */
class WC1C_Processor_Offers {

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
     * Offers processed count
     *
     * @var int
     */
    private $offers_count = 0;

    /**
     * Process offers import
     *
     * @param string $file_path Path to XML file
     * @return bool Success status
     */
    public function process($file_path) {
        $this->start_time = microtime(true);
        WC1C_Logger::info('Starting offers import processing', array('file' => basename($file_path)));
        
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
            WC1C_Logger::info('Offers import completed', array(
                'execution_time' => $execution_time,
                'offers_count' => $this->offers_count
            ));
            
            // Trigger action for post-processing
            do_action('wc1c_offers_import_complete', $this->offers_count);
            
            return true;
            
        } catch (Exception $e) {
            $this->reader->close();
            WC1C_Logger::error('Offers import failed: ' . $e->getMessage(), array(
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
        
        // Process nodes
        while ($this->reader->read()) {
            if ($this->reader->nodeType === XMLReader::ELEMENT) {
                switch ($this->reader->name) {
                    case 'ПакетПредложений':
                    case 'Предложения':
                        $this->process_offers_packet();
                        break;
                }
            }
        }
    }

    /**
     * Process offers packet
     */
    private function process_offers_packet() {
        // Process offers nodes
        while ($this->reader->read() && 
               !($this->reader->nodeType === XMLReader::END_ELEMENT && 
                 ($this->reader->name === 'ПакетПредложений' || $this->reader->name === 'Предложения'))) {
            
            if ($this->reader->nodeType === XMLReader::ELEMENT && $this->reader->name === 'Предложение') {
                $this->process_offer();
            }
        }
    }

    /**
     * Process offer
     */
    private function process_offer() {
        $offer = array(
            'id' => '',
            'product_id' => '',
            'price' => '',
            'quantity' => '',
            'prices' => array()
        );
        
        // Read offer data
        while ($this->reader->read() && 
               !($this->reader->nodeType === XMLReader::END_ELEMENT && $this->reader->name === 'Предложение')) {
            
            if ($this->reader->nodeType === XMLReader::ELEMENT) {
                switch ($this->reader->name) {
                    case 'Ид':
                        $offer['id'] = $this->get_element_text();
                        break;
                        
                    case 'Цены':
                        $offer['prices'] = $this->process_offer_prices();
                        break;
                        
                    case 'Количество':
                        $offer['quantity'] = $this->get_element_text();
                        break;
                }
            }
        }
        
        // Skip if empty or invalid
        if (empty($offer['id'])) {
            return;
        }
        
        // Get product ID from offer ID (may be composite)
        $parts = explode('#', $offer['id']);
        $product_1c_id = $parts[0];
        
        // Find product by 1C ID
        $product_id = WC1C::get_post_id_by_meta('_wc1c_id', $product_1c_id);
        
        if (!$product_id) {
            WC1C_Logger::debug('Product not found for offer', array(
                'offer_id' => $offer['id'],
                'product_1c_id' => $product_1c_id
            ));
            return;
        }
        
        $offer['product_id'] = $product_id;
        
        // Update product with offer data
        $this->update_product_from_offer($offer);
        $this->offers_count++;
    }

    /**
     * Process offer prices
     *
     * @return array Prices
     */
    private function process_offer_prices() {
        $prices = array();
        
        // Read prices
        while ($this->reader->read() && 
               !($this->reader->nodeType === XMLReader::END_ELEMENT && $this->reader->name === 'Цены')) {
            
            if ($this->reader->nodeType === XMLReader::ELEMENT && $this->reader->name === 'Цена') {
                $price = $this->process_offer_price();
                if (!empty($price)) {
                    $prices[] = $price;
                }
            }
        }
        
        return $prices;
    }

    /**
     * Process offer price
     *
     * @return array Price data
     */
    private function process_offer_price() {
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
     * Update product from offer data
     *
     * @param array $offer Offer data
     */
    private function update_product_from_offer($offer) {
        $product_id = $offer['product_id'];
        
        // Get product
        $product = wc_get_product($product_id);
        
        if (!$product) {
            WC1C_Logger::error('Failed to get product', array(
                'product_id' => $product_id
            ));
            return;
        }
        
        $updated = false;
        
        // Update prices if available
        if (!empty($offer['prices'])) {
            $updated = $this->update_product_prices($product, $offer['prices']) || $updated;
        }
        
        // Update stock if available and stock management is enabled
        if (get_option('wc1c_stock_management', 'yes') === 'yes' && isset($offer['quantity'])) {
            $updated = $this->update_product_stock($product, $offer['quantity']) || $updated;
        }
        
        // Save product if updated
        if ($updated) {
            $product->save();
            
            WC1C_Logger::debug('Updated product from offer', array(
                'product_id' => $product_id,
                'offer_id' => $offer['id']
            ));
        }
    }

    /**
     * Update product prices
     *
     * @param WC_Product $product Product object
     * @param array $prices Price data
     * @return bool Whether product was updated
     */
    private function update_product_prices($product, $prices) {
        if (empty($prices)) {
            return false;
        }
        
        $updated = false;
        $regular_price = '';
        $sale_price = '';
        
        // Get price based on configured price type
        $price_type = get_option('wc1c_price_type', '');
        
        foreach ($prices as $price) {
            if (empty($price_type) || $price['type'] === $price_type) {
                $regular_price = WC1C::parse_decimal($price['value']);
                break;
            }
        }
        
        // If no matching price type, use the first one
        if (empty($regular_price) && !empty($prices[0]['value'])) {
            $regular_price = WC1C::parse_decimal($prices[0]['value']);
        }
        
        // Check if price is different
        if (!empty($regular_price) && $regular_price != $product->get_regular_price()) {
            $product->set_regular_price($regular_price);
            $product->set_price($regular_price);
            $updated = true;
        }
        
        // Update sale price if available
        if (!empty($sale_price) && $sale_price != $product->get_sale_price()) {
            $product->set_sale_price($sale_price);
            if ($sale_price < $regular_price) {
                $product->set_price($sale_price);
            }
            $updated = true;
        }
        
        return $updated;
    }

    /**
     * Update product stock
     *
     * @param WC_Product $product Product object
     * @param string $quantity Stock quantity
     * @return bool Whether product was updated
     */
    private function update_product_stock($product, $quantity) {
        $quantity = WC1C::parse_decimal($quantity);
        
        // Check if stock quantity is different
        if ($quantity != $product->get_stock_quantity()) {
            $product->set_manage_stock(true);
            $product->set_stock_quantity($quantity);
            
            // Set stock status based on quantity
            $stock_status = ($quantity > 0) ? 'instock' : 'outofstock';
            $product->set_stock_status($stock_status);
            
            return true;
        }
        
        return false;
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