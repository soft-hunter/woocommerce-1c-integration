<?php

/**
 * Data processing functionality for 1C integration
 *
 * @package    WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/exchange
 * @author     Igor Melnyk <igormelnykit@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * Data processing functionality for 1C integration
 */
class WC1C_Data_Processor
{

    /**
     * Processing context
     *
     * @var array
     */
    private $context = array();

    /**
     * Processing statistics
     *
     * @var array
     */
    private $stats = array(
        'groups_processed' => 0,
        'products_processed' => 0,
        'offers_processed' => 0,
        'orders_processed' => 0,
        'errors' => 0,
        'start_time' => 0,
        'end_time' => 0
    );

    /**
     * Batch processing settings
     *
     * @var array
     */
    private $batch_settings = array(
        'batch_size' => 100,
        'memory_limit_percent' => 80,
        'time_limit' => 300
    );

    /**
     * Data mappers
     *
     * @var array
     */
    private $mappers = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->init_context();
        $this->init_mappers();
    }

    /**
     * Process catalog data
     *
     * @param array $catalog_data Catalog data
     * @param bool $is_full_exchange Full exchange flag
     * @return array Processing results
     */
    public function process_catalog($catalog_data, $is_full_exchange = false)
    {
        $this->stats['start_time'] = microtime(true);
        $this->context['is_full_exchange'] = $is_full_exchange;

        WC1C_Logger::log("Starting catalog processing", 'info', array(
            'is_full_exchange' => $is_full_exchange,
            'groups_count' => count($catalog_data['groups'] ?? array()),
            'products_count' => count($catalog_data['products'] ?? array())
        ));

        try {
            // Process in order: groups, properties, products
            if (isset($catalog_data['groups'])) {
                $this->process_groups($catalog_data['groups']);
            }

            if (isset($catalog_data['properties'])) {
                $this->process_properties($catalog_data['properties']);
            }

            if (isset($catalog_data['products'])) {
                $this->process_products($catalog_data['products']);
            }

            // Cleanup if full exchange
            if ($is_full_exchange) {
                $this->cleanup_catalog_data();
            }

            $this->stats['end_time'] = microtime(true);

            WC1C_Logger::log("Catalog processing completed", 'info', array(
                'stats' => $this->stats,
                'duration' => round($this->stats['end_time'] - $this->stats['start_time'], 2)
            ));

            return $this->get_processing_results();
        } catch (Exception $e) {
            WC1C_Logger::log("Catalog processing failed: " . $e->getMessage(), 'error');
            throw $e;
        }
    }

    /**
     * Process offers data
     *
     * @param array $offers_data Offers data
     * @param bool $is_full_exchange Full exchange flag
     * @return array Processing results
     */
    public function process_offers($offers_data, $is_full_exchange = false)
    {
        $this->stats['start_time'] = microtime(true);
        $this->context['is_full_exchange'] = $is_full_exchange;

        WC1C_Logger::log("Starting offers processing", 'info', array(
            'is_full_exchange' => $is_full_exchange,
            'offers_count' => count($offers_data['offers'] ?? array())
        ));

        try {
            // Process price types first
            if (isset($offers_data['price_types'])) {
                $this->process_price_types($offers_data['price_types']);
            }

            // Process offers
            if (isset($offers_data['offers'])) {
                $this->process_offers_batch($offers_data['offers']);
            }

            $this->stats['end_time'] = microtime(true);

            WC1C_Logger::log("Offers processing completed", 'info', array(
                'stats' => $this->stats,
                'duration' => round($this->stats['end_time'] - $this->stats['start_time'], 2)
            ));

            return $this->get_processing_results();
        } catch (Exception $e) {
            WC1C_Logger::log("Offers processing failed: " . $e->getMessage(), 'error');
            throw $e;
        }
    }

    /**
     * Process orders data
     *
     * @param array $orders_data Orders data
     * @return array Processing results
     */
    public function process_orders($orders_data)
    {
        $this->stats['start_time'] = microtime(true);

        WC1C_Logger::log("Starting orders processing", 'info', array(
            'orders_count' => count($orders_data['orders'] ?? array())
        ));

        try {
            if (isset($orders_data['orders'])) {
                $this->process_orders_batch($orders_data['orders']);
            }

            $this->stats['end_time'] = microtime(true);

            WC1C_Logger::log("Orders processing completed", 'info', array(
                'stats' => $this->stats,
                'duration' => round($this->stats['end_time'] - $this->stats['start_time'], 2)
            ));

            return $this->get_processing_results();
        } catch (Exception $e) {
            WC1C_Logger::log("Orders processing failed: " . $e->getMessage(), 'error');
            throw $e;
        }
    }

    /**
     * Process groups
     *
     * @param array $groups Groups data
     */
    private function process_groups($groups)
    {
        foreach ($groups as $group) {
            try {
                $this->process_single_group($group);
                $this->stats['groups_processed']++;
            } catch (Exception $e) {
                $this->stats['errors']++;
                WC1C_Logger::log("Failed to process group: " . $e->getMessage(), 'error', array(
                    'group_id' => $group['id'] ?? 'unknown'
                ));
            }
        }
    }

    /**
     * Process single group
     *
     * @param array $group Group data
     */
    private function process_single_group($group)
    {
        $mapper = $this->get_mapper('group');
        $wc_data = $mapper->map_to_woocommerce($group);

        // Check if category exists
        $term_id = $this->find_existing_category($wc_data['guid']);

        if ($term_id) {
            $this->update_category($term_id, $wc_data);
        } else {
            $this->create_category($wc_data);
        }
    }

    /**
     * Process properties
     *
     * @param array $properties Properties data
     */
    private function process_properties($properties)
    {
        foreach ($properties as $property) {
            try {
                $this->process_single_property($property);
            } catch (Exception $e) {
                $this->stats['errors']++;
                WC1C_Logger::log("Failed to process property: " . $e->getMessage(), 'error', array(
                    'property_id' => $property['id'] ?? 'unknown'
                ));
            }
        }
    }

    /**
     * Process single property
     *
     * @param array $property Property data
     */
    private function process_single_property($property)
    {
        $mapper = $this->get_mapper('property');
        $wc_data = $mapper->map_to_woocommerce($property);

        // Check if attribute exists
        $attribute_id = $this->find_existing_attribute($wc_data['guid']);

        if ($attribute_id) {
            $this->update_attribute($attribute_id, $wc_data);
        } else {
            $attribute_id = $this->create_attribute($wc_data);
        }

        // Process property options
        if (isset($property['options']) && is_array($property['options'])) {
            $this->process_property_options($attribute_id, $property['options']);
        }
    }

    /**
     * Process property options
     *
     * @param int $attribute_id Attribute ID
     * @param array $options Property options
     */
    private function process_property_options($attribute_id, $options)
    {
        $attribute = wc_get_attribute($attribute_id);
        if (!$attribute) {
            return;
        }

        $taxonomy = $attribute->slug;

        foreach ($options as $option) {
            try {
                $this->process_single_property_option($taxonomy, $option);
            } catch (Exception $e) {
                WC1C_Logger::log("Failed to process property option: " . $e->getMessage(), 'error', array(
                    'option_id' => $option['id'] ?? 'unknown',
                    'taxonomy' => $taxonomy
                ));
            }
        }
    }

    /**
     * Process single property option
     *
     * @param string $taxonomy Taxonomy name
     * @param array $option Option data
     */
    private function process_single_property_option($taxonomy, $option)
    {
        $mapper = $this->get_mapper('property_option');
        $wc_data = $mapper->map_to_woocommerce($option);

        // Check if term exists
        $term_id = $this->find_existing_term($taxonomy, $wc_data['guid']);

        if ($term_id) {
            $this->update_term($term_id, $taxonomy, $wc_data);
        } else {
            $this->create_term($taxonomy, $wc_data);
        }
    }

    /**
     * Process products in batches
     *
     * @param array $products Products data
     */
    private function process_products($products)
    {
        $batches = array_chunk($products, $this->batch_settings['batch_size']);

        foreach ($batches as $batch_index => $batch) {
            WC1C_Logger::log("Processing product batch", 'debug', array(
                'batch' => $batch_index + 1,
                'total_batches' => count($batches),
                'batch_size' => count($batch)
            ));

            foreach ($batch as $product) {
                try {
                    $this->process_single_product($product);
                    $this->stats['products_processed']++;
                } catch (Exception $e) {
                    $this->stats['errors']++;
                    WC1C_Logger::log("Failed to process product: " . $e->getMessage(), 'error', array(
                        'product_id' => $product['id'] ?? 'unknown'
                    ));
                }
            }

            // Memory management
            $this->manage_memory();

            // Check time limit
            if ($this->is_time_limit_exceeded()) {
                WC1C_Logger::log("Time limit exceeded, stopping product processing", 'warning');
                break;
            }
        }
    }

    /**
     * Process single product
     *
     * @param array $product Product data
     */
    private function process_single_product($product)
    {
        $mapper = $this->get_mapper('product');
        $wc_data = $mapper->map_to_woocommerce($product);

        // Check if product exists
        $post_id = $this->find_existing_product($wc_data['guid']);

        if ($post_id) {
            $this->update_product($post_id, $wc_data);
        } else {
            $post_id = $this->create_product($wc_data);
        }

        // Process product variations if any
        if (isset($product['variations']) && is_array($product['variations'])) {
            $this->process_product_variations($post_id, $product['variations']);
        }

        // Process product images
        if (isset($product['images']) && is_array($product['images'])) {
            $this->process_product_images($post_id, $product['images']);
        }
    }

    /**
     * Process product variations
     *
     * @param int $parent_id Parent product ID
     * @param array $variations Variations data
     */
    private function process_product_variations($parent_id, $variations)
    {
        // Set product type to variable
        wp_set_object_terms($parent_id, 'variable', 'product_type');

        foreach ($variations as $variation) {
            try {
                $this->process_single_variation($parent_id, $variation);
            } catch (Exception $e) {
                WC1C_Logger::log("Failed to process variation: " . $e->getMessage(), 'error', array(
                    'variation_id' => $variation['id'] ?? 'unknown',
                    'parent_id' => $parent_id
                ));
            }
        }
    }

    /**
     * Process single variation
     *
     * @param int $parent_id Parent product ID
     * @param array $variation Variation data
     */
    private function process_single_variation($parent_id, $variation)
    {
        $mapper = $this->get_mapper('variation');
        $wc_data = $mapper->map_to_woocommerce($variation);

        // Check if variation exists
        $variation_id = $this->find_existing_variation($wc_data['guid']);

        if ($variation_id) {
            $this->update_variation($variation_id, $wc_data);
        } else {
            $this->create_variation($parent_id, $wc_data);
        }
    }

    /**
     * Process product images
     *
     * @param int $product_id Product ID
     * @param array $images Images data
     */
    private function process_product_images($product_id, $images)
    {
        $file_handler = new WC1C_File_Handler();
        $attachment_ids = array();

        foreach ($images as $image) {
            try {
                $attachment_id = $this->process_single_image($product_id, $image, $file_handler);
                if ($attachment_id) {
                    $attachment_ids[] = $attachment_id;
                }
            } catch (Exception $e) {
                WC1C_Logger::log("Failed to process image: " . $e->getMessage(), 'error', array(
                    'image_path' => $image['path'] ?? 'unknown',
                    'product_id' => $product_id
                ));
            }
        }

        // Set featured image and gallery
        if (!empty($attachment_ids)) {
            set_post_thumbnail($product_id, $attachment_ids[0]);

            if (count($attachment_ids) > 1) {
                update_post_meta($product_id, '_product_image_gallery', implode(',', array_slice($attachment_ids, 1)));
            }
        }
    }

    /**
     * Process single image
     *
     * @param int $product_id Product ID
     * @param array $image Image data
     * @param WC1C_File_Handler $file_handler File handler
     * @return int|false Attachment ID or false on failure
     */
    private function process_single_image($product_id, $image, $file_handler)
    {
        $image_path = $file_handler->get_catalog_file_path($image['path']);

        if (!file_exists($image_path)) {
            return false;
        }

        // Check if attachment already exists
        $existing_id = $this->find_existing_attachment($image_path);
        if ($existing_id) {
            return $existing_id;
        }

        // Upload image
        $upload = wp_upload_bits(
            basename($image_path),
            null,
            file_get_contents($image_path)
        );

        if ($upload['error']) {
            throw new Exception("Failed to upload image: " . $upload['error']);
        }

        // Create attachment
        $attachment = array(
            'post_mime_type' => $upload['type'],
            'post_title' => $image['title'] ?? basename($image_path),
            'post_content' => $image['description'] ?? '',
            'post_status' => 'inherit'
        );

        $attachment_id = wp_insert_attachment($attachment, $upload['file'], $product_id);

        if (is_wp_error($attachment_id)) {
            throw new Exception("Failed to create attachment: " . $attachment_id->get_error_message());
        }

        // Generate metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $metadata);

        return $attachment_id;
    }

    /**
     * Process offers in batches
     *
     * @param array $offers Offers data
     */
    private function process_offers_batch($offers)
    {
        $batches = array_chunk($offers, $this->batch_settings['batch_size']);

        foreach ($batches as $batch_index => $batch) {
            WC1C_Logger::log("Processing offer batch", 'debug', array(
                'batch' => $batch_index + 1,
                'total_batches' => count($batches),
                'batch_size' => count($batch)
            ));

            foreach ($batch as $offer) {
                try {
                    $this->process_single_offer($offer);
                    $this->stats['offers_processed']++;
                } catch (Exception $e) {
                    $this->stats['errors']++;
                    WC1C_Logger::log("Failed to process offer: " . $e->getMessage(), 'error', array(
                        'offer_id' => $offer['id'] ?? 'unknown'
                    ));
                }
            }

            $this->manage_memory();

            if ($this->is_time_limit_exceeded()) {
                WC1C_Logger::log("Time limit exceeded, stopping offer processing", 'warning');
                break;
            }
        }
    }

    /**
     * Process single offer
     *
     * @param array $offer Offer data
     */
    private function process_single_offer($offer)
    {
        $mapper = $this->get_mapper('offer');
        $wc_data = $mapper->map_to_woocommerce($offer);

        // Find product by GUID
        $product_id = $this->find_existing_product($wc_data['product_guid']);

        if (!$product_id) {
            throw new Exception("Product not found for offer: " . $wc_data['product_guid']);
        }

        // Check if this is a variation offer
        if (isset($wc_data['variation_guid'])) {
            $variation_id = $this->find_existing_variation($wc_data['variation_guid']);
            if ($variation_id) {
                $this->update_offer_data($variation_id, $wc_data);
            }
        } else {
            $this->update_offer_data($product_id, $wc_data);
        }
    }

    /**
     * Update offer data
     *
     * @param int $product_id Product ID
     * @param array $offer_data Offer data
     */
    private function update_offer_data($product_id, $offer_data)
    {
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        // Update price
        if (isset($offer_data['price'])) {
            $product->set_regular_price($offer_data['price']);
        }

        // Update stock
        if (isset($offer_data['stock_quantity'])) {
            $product->set_manage_stock(true);
            $product->set_stock_quantity($offer_data['stock_quantity']);
            $product->set_stock_status($offer_data['stock_quantity'] > 0 ? 'instock' : 'outofstock');
        }

        // Update other offer-specific data
        if (isset($offer_data['sku'])) {
            $product->set_sku($offer_data['sku']);
        }

        $product->save();
    }

    /**
     * Process orders in batches
     *
     * @param array $orders Orders data
     */
    private function process_orders_batch($orders)
    {
        foreach ($orders as $order) {
            try {
                $this->process_single_order($order);
                $this->stats['orders_processed']++;
            } catch (Exception $e) {
                $this->stats['errors']++;
                WC1C_Logger::log("Failed to process order: " . $e->getMessage(), 'error', array(
                    'order_id' => $order['id'] ?? 'unknown'
                ));
            }
        }
    }

    /**
     * Process single order
     *
     * @param array $order Order data
     */
    private function process_single_order($order)
    {
        $mapper = $this->get_mapper('order');
        $wc_data = $mapper->map_to_woocommerce($order);

        // Check if order exists
        $order_id = $this->find_existing_order($wc_data['guid']);

        if ($order_id) {
            $this->update_order($order_id, $wc_data);
        } else {
            $this->create_order($wc_data);
        }
    }

    /**
     * Process price types
     *
     * @param array $price_types Price types data
     */
    private function process_price_types($price_types)
    {
        // Store price types for later use
        $this->context['price_types'] = $price_types;

        // Update currency if specified
        if (isset($price_types[0]['currency'])) {
            $this->update_currency($price_types[0]['currency']);
        }
    }

    /**
     * Update currency
     *
     * @param string $currency Currency code
     */
    private function update_currency($currency)
    {
        $supported_currencies = get_woocommerce_currencies();

        if (array_key_exists($currency, $supported_currencies)) {
            update_option('woocommerce_currency', $currency);

            // Set currency position based on currency
            $currency_positions = array(
                'RUB' => 'right_space',
                'UAH' => 'right_space',
                'USD' => 'left',
                'EUR' => 'left'
            );

            if (isset($currency_positions[$currency])) {
                update_option('woocommerce_currency_pos', $currency_positions[$currency]);
            }

            WC1C_Logger::log("Currency updated", 'info', array('currency' => $currency));
        }
    }

    /**
     * Find existing category
     *
     * @param string $guid Category GUID
     * @return int|false Category term ID or false
     */
    private function find_existing_category($guid)
    {
        global $wpdb;

        $term_id = $wpdb->get_var($wpdb->prepare(
            "SELECT tm.term_id FROM {$wpdb->termmeta} tm 
             JOIN {$wpdb->term_taxonomy} tt ON tm.term_id = tt.term_id 
             WHERE tm.meta_key = 'wc1c_guid' AND tm.meta_value = %s AND tt.taxonomy = 'product_cat'",
            "product_cat::{$guid}"
        ));

        return $term_id ? (int) $term_id : false;
    }

    /**
     * Find existing attribute
     *
     * @param string $guid Attribute GUID
     * @return int|false Attribute ID or false
     */
    private function find_existing_attribute($guid)
    {
        $guids = get_option('wc1c_guid_attributes', array());
        return isset($guids[$guid]) ? (int) $guids[$guid] : false;
    }

    /**
     * Find existing term
     *
     * @param string $taxonomy Taxonomy name
     * @param string $guid Term GUID
     * @return int|false Term ID or false
     */
    private function find_existing_term($taxonomy, $guid)
    {
        global $wpdb;

        $term_id = $wpdb->get_var($wpdb->prepare(
            "SELECT tm.term_id FROM {$wpdb->termmeta} tm 
             JOIN {$wpdb->term_taxonomy} tt ON tm.term_id = tt.term_id 
             WHERE tm.meta_key = 'wc1c_guid' AND tm.meta_value = %s AND tt.taxonomy = %s",
            "{$taxonomy}::{$guid}",
            $taxonomy
        ));

        return $term_id ? (int) $term_id : false;
    }

    /**
     * Find existing product
     *
     * @param string $guid Product GUID
     * @return int|false Product ID or false
     */
    private function find_existing_product($guid)
    {
        global $wpdb;

        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_wc1c_guid' AND meta_value = %s",
            $guid
        ));

        return $post_id ? (int) $post_id : false;
    }

    /**
     * Find existing variation
     *
     * @param string $guid Variation GUID
     * @return int|false Variation ID or false
     */
    private function find_existing_variation($guid)
    {
        return $this->find_existing_product($guid);
    }

    /**
     * Find existing order
     *
     * @param string $guid Order GUID
     * @return int|false Order ID or false
     */
    private function find_existing_order($guid)
    {
        global $wpdb;

        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_wc1c_guid' AND pm.meta_value = %s AND p.post_type = 'shop_order'",
            $guid
        ));

        return $post_id ? (int) $post_id : false;
    }

    /**
     * Find existing attachment
     *
     * @param string $file_path File path
     * @return int|false Attachment ID or false
     */
    private function find_existing_attachment($file_path)
    {
        global $wpdb;

        $filename = basename($file_path);
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s",
            '%' . $filename
        ));

        return $attachment_id ? (int) $attachment_id : false;
    }

    /**
     * Create category
     *
     * @param array $category_data Category data
     * @return int Category term ID
     */
    private function create_category($category_data)
    {
        $args = array(
            'description' => $category_data['description'] ?? '',
            'slug' => $category_data['slug'] ?? '',
            'parent' => $category_data['parent_id'] ?? 0
        );

        $result = wp_insert_term($category_data['name'], 'product_cat', $args);

        if (is_wp_error($result)) {
            throw new Exception("Failed to create category: " . $result->get_error_message());
        }

        $term_id = $result['term_id'];

        // Store GUID
        update_term_meta($term_id, 'wc1c_guid', "product_cat::{$category_data['guid']}");
        update_term_meta($term_id, 'wc1c_timestamp', time());

        return $term_id;
    }

    /**
     * Update category
     *
     * @param int $term_id Term ID
     * @param array $category_data Category data
     */
    private function update_category($term_id, $category_data)
    {
        $args = array(
            'name' => $category_data['name'],
            'description' => $category_data['description'] ?? '',
            'parent' => $category_data['parent_id'] ?? 0
        );

        $result = wp_update_term($term_id, 'product_cat', $args);

        if (is_wp_error($result)) {
            throw new Exception("Failed to update category: " . $result->get_error_message());
        }

        update_term_meta($term_id, 'wc1c_timestamp', time());
    }

    /**
     * Create attribute
     *
     * @param array $attribute_data Attribute data
     * @return int Attribute ID
     */
    private function create_attribute($attribute_data)
    {
        global $wpdb;

        $attribute_name = wc_sanitize_taxonomy_name($attribute_data['name']);

        // Ensure unique name
        $counter = 1;
        $original_name = $attribute_name;
        while ($this->attribute_name_exists($attribute_name)) {
            $attribute_name = $original_name . '-' . $counter;
            $counter++;
        }

        $data = array(
            'attribute_label' => $attribute_data['name'],
            'attribute_name' => $attribute_name,
            'attribute_type' => $attribute_data['type'] ?? 'select',
            'attribute_orderby' => 'menu_order',
            'attribute_public' => 0
        );

        $result = $wpdb->insert("{$wpdb->prefix}woocommerce_attribute_taxonomies", $data);

        if (!$result) {
            throw new Exception("Failed to create attribute");
        }

        $attribute_id = $wpdb->insert_id;

        // Store GUID mapping
        $guids = get_option('wc1c_guid_attributes', array());
        $guids[$attribute_data['guid']] = $attribute_id;
        update_option('wc1c_guid_attributes', $guids);

        // Store timestamp
        $timestamps = get_option('wc1c_timestamp_attributes', array());
        $timestamps[$attribute_data['guid']] = time();
        update_option('wc1c_timestamp_attributes', $timestamps);

        // Clear attribute cache
        delete_transient('wc_attribute_taxonomies');

        return $attribute_id;
    }

    /**
     * Update attribute
     *
     * @param int $attribute_id Attribute ID
     * @param array $attribute_data Attribute data
     */
    private function update_attribute($attribute_id, $attribute_data)
    {
        global $wpdb;

        $data = array(
            'attribute_label' => $attribute_data['name'],
            'attribute_type' => $attribute_data['type'] ?? 'select'
        );

        $result = $wpdb->update(
            "{$wpdb->prefix}woocommerce_attribute_taxonomies",
            $data,
            array('attribute_id' => $attribute_id)
        );

        if ($result === false) {
            throw new Exception("Failed to update attribute");
        }

        // Update timestamp
        $timestamps = get_option('wc1c_timestamp_attributes', array());
        $timestamps[$attribute_data['guid']] = time();
        update_option('wc1c_timestamp_attributes', $timestamps);

        // Clear attribute cache
        delete_transient('wc_attribute_taxonomies');
    }

    /**
     * Create term
     *
     * @param string $taxonomy Taxonomy name
     * @param array $term_data Term data
     * @return int Term ID
     */
    private function create_term($taxonomy, $term_data)
    {
        $args = array(
            'description' => $term_data['description'] ?? '',
            'slug' => $term_data['slug'] ?? ''
        );

        $result = wp_insert_term($term_data['name'], $taxonomy, $args);

        if (is_wp_error($result)) {
            throw new Exception("Failed to create term: " . $result->get_error_message());
        }

        $term_id = $result['term_id'];

        // Store GUID
        update_term_meta($term_id, 'wc1c_guid', "{$taxonomy}::{$term_data['guid']}");
        update_term_meta($term_id, 'wc1c_timestamp', time());

        return $term_id;
    }

    /**
     * Update term
     *
     * @param int $term_id Term ID
     * @param string $taxonomy Taxonomy name
     * @param array $term_data Term data
     */
    private function update_term($term_id, $taxonomy, $term_data)
    {
        $args = array(
            'name' => $term_data['name'],
            'description' => $term_data['description'] ?? ''
        );

        $result = wp_update_term($term_id, $taxonomy, $args);

        if (is_wp_error($result)) {
            throw new Exception("Failed to update term: " . $result->get_error_message());
        }

        update_term_meta($term_id, 'wc1c_timestamp', time());
    }

    /**
     * Create product
     *
     * @param array $product_data Product data
     * @return int Product ID
     */
    private function create_product($product_data)
    {
        $product = new WC_Product_Simple();

        // Set basic data
        $product->set_name($product_data['name']);
        $product->set_description($product_data['description'] ?? '');
        $product->set_short_description($product_data['short_description'] ?? '');
        $product->set_sku($product_data['sku'] ?? '');
        $product->set_status($product_data['status'] ?? 'publish');

        // Set pricing
        if (isset($product_data['regular_price'])) {
            $product->set_regular_price($product_data['regular_price']);
        }

        // Set stock management
        if (isset($product_data['manage_stock'])) {
            $product->set_manage_stock($product_data['manage_stock']);
        }

        if (isset($product_data['stock_quantity'])) {
            $product->set_stock_quantity($product_data['stock_quantity']);
        }

        if (isset($product_data['stock_status'])) {
            $product->set_stock_status($product_data['stock_status']);
        }

        // Set dimensions
        if (isset($product_data['weight'])) {
            $product->set_weight($product_data['weight']);
        }

        if (isset($product_data['length'])) {
            $product->set_length($product_data['length']);
        }

        if (isset($product_data['width'])) {
            $product->set_width($product_data['width']);
        }

        if (isset($product_data['height'])) {
            $product->set_height($product_data['height']);
        }

        $product_id = $product->save();

        if (!$product_id) {
            throw new Exception("Failed to create product");
        }

        // Store GUID
        update_post_meta($product_id, '_wc1c_guid', $product_data['guid']);
        update_post_meta($product_id, '_wc1c_timestamp', time());

        // Set categories
        if (isset($product_data['categories']) && is_array($product_data['categories'])) {
            wp_set_object_terms($product_id, $product_data['categories'], 'product_cat');
        }

        // Set attributes
        if (isset($product_data['attributes']) && is_array($product_data['attributes'])) {
            $this->set_product_attributes($product_id, $product_data['attributes']);
        }

        return $product_id;
    }

    /**
     * Update product
     *
     * @param int $product_id Product ID
     * @param array $product_data Product data
     */
    private function update_product($product_id, $product_data)
    {
        $product = wc_get_product($product_id);

        if (!$product) {
            throw new Exception("Product not found: {$product_id}");
        }

        // Update basic data
        $product->set_name($product_data['name']);
        $product->set_description($product_data['description'] ?? '');
        $product->set_short_description($product_data['short_description'] ?? '');

        if (isset($product_data['sku'])) {
            $product->set_sku($product_data['sku']);
        }

        // Update pricing
        if (isset($product_data['regular_price'])) {
            $product->set_regular_price($product_data['regular_price']);
        }

        // Update stock
        if (isset($product_data['manage_stock'])) {
            $product->set_manage_stock($product_data['manage_stock']);
        }

        if (isset($product_data['stock_quantity'])) {
            $product->set_stock_quantity($product_data['stock_quantity']);
        }

        if (isset($product_data['stock_status'])) {
            $product->set_stock_status($product_data['stock_status']);
        }

        // Update dimensions
        if (isset($product_data['weight'])) {
            $product->set_weight($product_data['weight']);
        }

        if (isset($product_data['length'])) {
            $product->set_length($product_data['length']);
        }

        if (isset($product_data['width'])) {
            $product->set_width($product_data['width']);
        }

        if (isset($product_data['height'])) {
            $product->set_height($product_data['height']);
        }

        $product->save();

        // Update timestamp
        update_post_meta($product_id, '_wc1c_timestamp', time());

        // Update categories
        if (isset($product_data['categories']) && is_array($product_data['categories'])) {
            wp_set_object_terms($product_id, $product_data['categories'], 'product_cat');
        }

        // Update attributes
        if (isset($product_data['attributes']) && is_array($product_data['attributes'])) {
            $this->set_product_attributes($product_id, $product_data['attributes']);
        }
    }

    /**
     * Create variation
     *
     * @param int $parent_id Parent product ID
     * @param array $variation_data Variation data
     * @return int Variation ID
     */
    private function create_variation($parent_id, $variation_data)
    {
        $variation = new WC_Product_Variation();
        $variation->set_parent_id($parent_id);

        // Set basic data
        if (isset($variation_data['sku'])) {
            $variation->set_sku($variation_data['sku']);
        }

        // Set pricing
        if (isset($variation_data['regular_price'])) {
            $variation->set_regular_price($variation_data['regular_price']);
        }

        // Set stock
        if (isset($variation_data['manage_stock'])) {
            $variation->set_manage_stock($variation_data['manage_stock']);
        }

        if (isset($variation_data['stock_quantity'])) {
            $variation->set_stock_quantity($variation_data['stock_quantity']);
        }

        if (isset($variation_data['stock_status'])) {
            $variation->set_stock_status($variation_data['stock_status']);
        }

        // Set attributes
        if (isset($variation_data['attributes']) && is_array($variation_data['attributes'])) {
            $variation->set_attributes($variation_data['attributes']);
        }

        $variation_id = $variation->save();

        if (!$variation_id) {
            throw new Exception("Failed to create variation");
        }

        // Store GUID
        update_post_meta($variation_id, '_wc1c_guid', $variation_data['guid']);
        update_post_meta($variation_id, '_wc1c_timestamp', time());

        return $variation_id;
    }

    /**
     * Update variation
     *
     * @param int $variation_id Variation ID
     * @param array $variation_data Variation data
     */
    private function update_variation($variation_id, $variation_data)
    {
        $variation = wc_get_product($variation_id);

        if (!$variation || !$variation->is_type('variation')) {
            throw new Exception("Variation not found: {$variation_id}");
        }

        // Update basic data
        if (isset($variation_data['sku'])) {
            $variation->set_sku($variation_data['sku']);
        }

        // Update pricing
        if (isset($variation_data['regular_price'])) {
            $variation->set_regular_price($variation_data['regular_price']);
        }

        // Update stock
        if (isset($variation_data['manage_stock'])) {
            $variation->set_manage_stock($variation_data['manage_stock']);
        }

        if (isset($variation_data['stock_quantity'])) {
            $variation->set_stock_quantity($variation_data['stock_quantity']);
        }

        if (isset($variation_data['stock_status'])) {
            $variation->set_stock_status($variation_data['stock_status']);
        }

        // Update attributes
        if (isset($variation_data['attributes']) && is_array($variation_data['attributes'])) {
            $variation->set_attributes($variation_data['attributes']);
        }

        $variation->save();

        // Update timestamp
        update_post_meta($variation_id, '_wc1c_timestamp', time());
    }

    /**
     * Create order
     *
     * @param array $order_data Order data
     * @return int Order ID
     */
    private function create_order($order_data)
    {
        $order = wc_create_order();

        if (is_wp_error($order)) {
            throw new Exception("Failed to create order: " . $order->get_error_message());
        }

        $this->update_order_data($order, $order_data);

        $order_id = $order->save();

        // Store GUID
        update_post_meta($order_id, '_wc1c_guid', $order_data['guid']);
        update_post_meta($order_id, '_wc1c_timestamp', time());

        return $order_id;
    }

    /**
     * Update order
     *
     * @param int $order_id Order ID
     * @param array $order_data Order data
     */
    private function update_order($order_id, $order_data)
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            throw new Exception("Order not found: {$order_id}");
        }

        $this->update_order_data($order, $order_data);
        $order->save();

        // Update timestamp
        update_post_meta($order_id, '_wc1c_timestamp', time());
    }

    /**
     * Update order data
     *
     * @param WC_Order $order Order object
     * @param array $order_data Order data
     */
    private function update_order_data($order, $order_data)
    {
        // Set status
        if (isset($order_data['status'])) {
            $order->set_status($order_data['status']);
        }

        // Set customer
        if (isset($order_data['customer_id'])) {
            $order->set_customer_id($order_data['customer_id']);
        }

        // Set billing address
        if (isset($order_data['billing'])) {
            $order->set_address($order_data['billing'], 'billing');
        }

        // Set shipping address
        if (isset($order_data['shipping'])) {
            $order->set_address($order_data['shipping'], 'shipping');
        }

        // Set currency
        if (isset($order_data['currency'])) {
            $order->set_currency($order_data['currency']);
        }

        // Set totals
        if (isset($order_data['total'])) {
            $order->set_total($order_data['total']);
        }

        // Add line items
        if (isset($order_data['line_items']) && is_array($order_data['line_items'])) {
            // Remove existing items
            foreach ($order->get_items() as $item_id => $item) {
                $order->remove_item($item_id);
            }

            // Add new items
            foreach ($order_data['line_items'] as $line_item) {
                $product = wc_get_product($line_item['product_id']);
                if ($product) {
                    $item = new WC_Order_Item_Product();
                    $item->set_product($product);
                    $item->set_quantity($line_item['quantity']);
                    $item->set_subtotal($line_item['subtotal']);
                    $item->set_total($line_item['total']);
                    $order->add_item($item);
                }
            }
        }

        // Recalculate totals
        $order->calculate_totals();
    }

    /**
     * Set product attributes
     *
     * @param int $product_id Product ID
     * @param array $attributes Attributes data
     */
    private function set_product_attributes($product_id, $attributes)
    {
        $product_attributes = array();

        foreach ($attributes as $attribute) {
            $attribute_key = sanitize_title($attribute['name']);

            $product_attributes[$attribute_key] = array(
                'name' => $attribute['name'],
                'value' => $attribute['value'],
                'position' => $attribute['position'] ?? 0,
                'is_visible' => $attribute['is_visible'] ?? 1,
                'is_variation' => $attribute['is_variation'] ?? 0,
                'is_taxonomy' => $attribute['is_taxonomy'] ?? 0
            );

            // If it's a taxonomy attribute, set terms
            if ($attribute['is_taxonomy'] && isset($attribute['taxonomy'])) {
                wp_set_object_terms($product_id, $attribute['terms'], $attribute['taxonomy']);
            }
        }

        update_post_meta($product_id, '_product_attributes', $product_attributes);
    }

    /**
     * Check if attribute name exists
     *
     * @param string $name Attribute name
     * @return bool
     */
    private function attribute_name_exists($name)
    {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s",
            $name
        ));

        return $count > 0;
    }

    /**
     * Cleanup catalog data
     */
    private function cleanup_catalog_data()
    {
        if (!$this->context['is_full_exchange']) {
            return;
        }

        WC1C_Logger::log("Starting catalog cleanup", 'info');

        // Clean categories
        $this->cleanup_categories();

        // Clean attributes
        $this->cleanup_attributes();

        // Clean products
        $this->cleanup_products();

        WC1C_Logger::log("Catalog cleanup completed", 'info');
    }

    /**
     * Cleanup categories
     */
    private function cleanup_categories()
    {
        global $wpdb;

        $term_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT tm.term_id FROM {$wpdb->termmeta} tm 
             JOIN {$wpdb->term_taxonomy} tt ON tm.term_id = tt.term_id 
             WHERE tt.taxonomy = 'product_cat' 
             AND tm.meta_key = 'wc1c_timestamp' 
             AND tm.meta_value != %d",
            time()
        ));

        foreach ($term_ids as $term_id) {
            wp_delete_term($term_id, 'product_cat');
        }

        WC1C_Logger::log("Cleaned up categories", 'debug', array('count' => count($term_ids)));
    }

    /**
     * Cleanup attributes
     */
    private function cleanup_attributes()
    {
        $timestamps = get_option('wc1c_timestamp_attributes', array());
        $guids = get_option('wc1c_guid_attributes', array());

        $deleted_count = 0;

        foreach ($timestamps as $guid => $timestamp) {
            if ($timestamp != time() && isset($guids[$guid])) {
                $attribute_id = $guids[$guid];

                // Delete attribute
                $this->delete_attribute($attribute_id);

                unset($guids[$guid]);
                unset($timestamps[$guid]);
                $deleted_count++;
            }
        }

        update_option('wc1c_guid_attributes', $guids);
        update_option('wc1c_timestamp_attributes', $timestamps);

        WC1C_Logger::log("Cleaned up attributes", 'debug', array('count' => $deleted_count));
    }

    /**
     * Cleanup products
     */
    private function cleanup_products()
    {
        global $wpdb;

        $post_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT pm.post_id FROM {$wpdb->postmeta} pm 
             JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE p.post_type IN ('product', 'product_variation') 
             AND pm.meta_key = '_wc1c_timestamp' 
             AND pm.meta_value != %d",
            time()
        ));

        foreach ($post_ids as $post_id) {
            wp_delete_post($post_id, true);
        }

        WC1C_Logger::log("Cleaned up products", 'debug', array('count' => count($post_ids)));
    }

    /**
     * Delete attribute
     *
     * @param int $attribute_id Attribute ID
     */
    private function delete_attribute($attribute_id)
    {
        global $wpdb;

        $attribute = wc_get_attribute($attribute_id);
        if (!$attribute) {
            return;
        }

        $taxonomy = $attribute->slug;

        // Delete all terms
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'fields' => 'ids'
        ));

        foreach ($terms as $term_id) {
            wp_delete_term($term_id, $taxonomy);
        }

        // Delete attribute
        $wpdb->delete(
            "{$wpdb->prefix}woocommerce_attribute_taxonomies",
            array('attribute_id' => $attribute_id)
        );

        // Clear cache
        delete_transient('wc_attribute_taxonomies');
    }

    /**
     * Initialize context
     */
    private function init_context()
    {
        $this->context = array(
            'is_full_exchange' => false,
            'price_types' => array(),
            'current_batch' => 0,
            'total_batches' => 0
        );
    }

    /**
     * Initialize mappers
     */
    private function init_mappers()
    {
        $this->mappers = array(
            'group' => new WC1C_Group_Mapper(),
            'property' => new WC1C_Property_Mapper(),
            'property_option' => new WC1C_Property_Option_Mapper(),
            'product' => new WC1C_Product_Mapper(),
            'variation' => new WC1C_Variation_Mapper(),
            'offer' => new WC1C_Offer_Mapper(),
            'order' => new WC1C_Order_Mapper()
        );
    }

    /**
     * Get mapper
     *
     * @param string $type Mapper type
     * @return object Mapper instance
     */
    private function get_mapper($type)
    {
        if (!isset($this->mappers[$type])) {
            throw new Exception("Mapper not found: {$type}");
        }

        return $this->mappers[$type];
    }

    /**
     * Manage memory usage
     */
    private function manage_memory()
    {
        $memory_usage = memory_get_usage(true);
        $memory_limit = $this->get_memory_limit();
        $usage_percent = ($memory_usage / $memory_limit) * 100;

        if ($usage_percent > $this->batch_settings['memory_limit_percent']) {
            // Force garbage collection
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            // Clear object cache
            wp_cache_flush();

            WC1C_Logger::log("Memory cleanup performed", 'debug', array(
                'usage_before' => size_format($memory_usage),
                'usage_after' => size_format(memory_get_usage(true)),
                'usage_percent' => round($usage_percent, 2)
            ));
        }
    }

    /**
     * Check if time limit is exceeded
     *
     * @return bool
     */
    private function is_time_limit_exceeded()
    {
        if ($this->stats['start_time'] == 0) {
            return false;
        }

        $elapsed = microtime(true) - $this->stats['start_time'];
        return $elapsed > $this->batch_settings['time_limit'];
    }

    /**
     * Get memory limit in bytes
     *
     * @return int
     */
    private function get_memory_limit()
    {
        $limit = ini_get('memory_limit');

        if ($limit == -1) {
            return PHP_INT_MAX;
        }

        return $this->convert_to_bytes($limit);
    }

    /**
     * Convert size string to bytes
     *
     * @param string $size Size string (e.g., '128M', '1G')
     * @return int Size in bytes
     */
    private function convert_to_bytes($size)
    {
        $size = trim($size);
        $last = strtolower($size[strlen($size) - 1]);
        $size = (int) $size;

        switch ($last) {
            case 'g':
                $size *= 1024;
            case 'm':
                $size *= 1024;
            case 'k':
                $size *= 1024;
        }

        return $size;
    }

    /**
     * Get processing results
     *
     * @return array Processing results
     */
    private function get_processing_results()
    {
        return array(
            'success' => $this->stats['errors'] == 0,
            'stats' => $this->stats,
            'context' => $this->context,
            'duration' => $this->stats['end_time'] - $this->stats['start_time'],
            'memory_peak' => memory_get_peak_usage(true)
        );
    }

    /**
     * Get processing statistics
     *
     * @return array Statistics
     */
    public function get_stats()
    {
        return $this->stats;
    }

    /**
     * Reset statistics
     */
    public function reset_stats()
    {
        $this->stats = array(
            'groups_processed' => 0,
            'products_processed' => 0,
            'offers_processed' => 0,
            'orders_processed' => 0,
            'errors' => 0,
            'start_time' => 0,
            'end_time' => 0
        );
    }

    /**
     * Set batch settings
     *
     * @param array $settings Batch settings
     */
    public function set_batch_settings($settings)
    {
        $this->batch_settings = array_merge($this->batch_settings, $settings);
    }

    /**
     * Get batch settings
     *
     * @return array Batch settings
     */
    public function get_batch_settings()
    {
        return $this->batch_settings;
    }
}
