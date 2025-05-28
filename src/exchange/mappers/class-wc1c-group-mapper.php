<?php
/**
 * Group data mapper for 1C integration
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
 * Group data mapper for 1C integration
 */
class WC1C_Group_Mapper extends WC1C_Base_Mapper {

    /**
     * Map 1C group data to WooCommerce category format
     *
     * @param array $group_data 1C group data
     * @return array WooCommerce category data
     */
    public function map_to_woocommerce($group_data) {
        $mapped_data = array(
            'guid' => $this->get_value($group_data, 'Ид', ''),
            'name' => $this->get_value($group_data, 'Наименование', ''),
            'description' => $this->get_value($group_data, 'Описание', ''),
            'parent_guid' => $this->get_value($group_data, 'ИдРодителя', ''),
            'parent_id' => 0,
            'slug' => ''
        );

        // Generate slug from name
        if (!empty($mapped_data['name'])) {
            $mapped_data['slug'] = sanitize_title($mapped_data['name']);
        }

        // Find parent category ID if parent GUID exists
        if (!empty($mapped_data['parent_guid'])) {
            $mapped_data['parent_id'] = $this->find_category_by_guid($mapped_data['parent_guid']);
        }

        // Apply filters
        $mapped_data = apply_filters('wc1c_group_mapper_data', $mapped_data, $group_data);

        return $mapped_data;
    }

    /**
     * Map WooCommerce category data to 1C group format
     *
     * @param array $category_data WooCommerce category data
     * @return array 1C group data
     */
    public function map_to_1c($category_data) {
        $mapped_data = array(
            'Ид' => $this->get_value($category_data, 'guid', ''),
            'Наименование' => $this->get_value($category_data, 'name', ''),
            'Описание' => $this->get_value($category_data, 'description', ''),
            'ИдРодителя' => $this->get_value($category_data, 'parent_guid', '')
        );

        // Apply filters
        $mapped_data = apply_filters('wc1c_group_mapper_to_1c_data', $mapped_data, $category_data);

        return $mapped_data;
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
     * Validate group data
     *
     * @param array $group_data Group data
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate($group_data) {
        $errors = new WP_Error();

        // Check required fields
        if (empty($group_data['Ид'])) {
            $errors->add('missing_id', __('Group ID is required', 'woocommerce-1c-integration'));
        }

        if (empty($group_data['Наименование'])) {
            $errors->add('missing_name', __('Group name is required', 'woocommerce-1c-integration'));
        }

        // Check name length
        if (strlen($group_data['Наименование']) > 200) {
            $errors->add('name_too_long', __('Group name is too long (max 200 characters)', 'woocommerce-1c-integration'));
        }

        return $errors->has_errors() ? $errors : true;
    }
}