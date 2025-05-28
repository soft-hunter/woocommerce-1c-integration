<?php
/**
 * Cache manager for 1C integration
 *
 * @package    WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/exchange/cache
 * @author     Igor Melnyk <igor.melnyk.it@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

/**
 * Cache manager for 1C integration
 */
class WC1C_Cache_Manager {

    /**
     * Cache group prefix
     */
    const CACHE_GROUP = 'wc1c';

    /**
     * Default cache expiration (1 hour)
     */
    const DEFAULT_EXPIRATION = 3600;

    /**
     * Cache statistics
     *
     * @var array
     */
    private static $stats = array(
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0
    );

    /**
     * Get cached value
     *
     * @param string $key Cache key
     * @param string $group Cache group
     * @return mixed Cached value or false if not found
     */
    public static function get($key, $group = 'default') {
        $cache_key = self::build_key($key);
        $cache_group = self::build_group($group);
        
        $value = wp_cache_get($cache_key, $cache_group);
        
        if ($value !== false) {
            self::$stats['hits']++;
        } else {
            self::$stats['misses']++;
        }
        
        return $value;
    }

    /**
     * Set cached value
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param string $group Cache group
     * @param int $expiration Cache expiration in seconds
     * @return bool True if cached successfully
     */
    public static function set($key, $value, $group = 'default', $expiration = self::DEFAULT_EXPIRATION) {
        $cache_key = self::build_key($key);
        $cache_group = self::build_group($group);
        
        $result = wp_cache_set($cache_key, $value, $cache_group, $expiration);
        
        if ($result) {
            self::$stats['sets']++;
        }
        
        return $result;
    }

    /**
     * Delete cached value
     *
     * @param string $key Cache key
     * @param string $group Cache group
     * @return bool True if deleted successfully
     */
    public static function delete($key, $group = 'default') {
        $cache_key = self::build_key($key);
        $cache_group = self::build_group($group);
        
        $result = wp_cache_delete($cache_key, $cache_group);
        
        if ($result) {
            self::$stats['deletes']++;
        }
        
        return $result;
    }

    /**
     * Flush cache group
     *
     * @param string $group Cache group
     * @return bool True if flushed successfully
     */
    public static function flush_group($group = 'default') {
        $cache_group = self::build_group($group);
        return wp_cache_flush_group($cache_group);
    }

    /**
     * Flush all 1C cache
     *
     * @return bool True if flushed successfully
     */
    public static function flush_all() {
        $groups = array('default', 'products', 'categories', 'attributes', 'orders', 'meta');
        $success = true;
        
        foreach ($groups as $group) {
            if (!self::flush_group($group)) {
                $success = false;
            }
        }
        
        return $success;
    }

    /**
     * Get or set cached value
     *
     * @param string $key Cache key
     * @param callable $callback Callback to generate value if not cached
     * @param string $group Cache group
     * @param int $expiration Cache expiration in seconds
     * @return mixed Cached or generated value
     */
    public static function remember($key, $callback, $group = 'default', $expiration = self::DEFAULT_EXPIRATION) {
        $value = self::get($key, $group);
        
        if ($value === false && is_callable($callback)) {
            $value = call_user_func($callback);
            if ($value !== false) {
                self::set($key, $value, $group, $expiration);
            }
        }
        
        return $value;
    }

    /**
     * Cache product data
     *
     * @param string $guid Product GUID
     * @param array $data Product data
     * @param int $expiration Cache expiration
     * @return bool True if cached successfully
     */
    public static function cache_product($guid, $data, $expiration = self::DEFAULT_EXPIRATION) {
        return self::set("product_{$guid}", $data, 'products', $expiration);
    }

    /**
     * Get cached product data
     *
     * @param string $guid Product GUID
     * @return array|false Product data or false if not cached
     */
    public static function get_product($guid) {
        return self::get("product_{$guid}", 'products');
    }

    /**
     * Cache category data
     *
     * @param string $guid Category GUID
     * @param array $data Category data
     * @param int $expiration Cache expiration
     * @return bool True if cached successfully
     */
    public static function cache_category($guid, $data, $expiration = self::DEFAULT_EXPIRATION) {
        return self::set("category_{$guid}", $data, 'categories', $expiration);
    }

    /**
     * Get cached category data
     *
     * @param string $guid Category GUID
     * @return array|false Category data or false if not cached
     */
    public static function get_category($guid) {
        return self::get("category_{$guid}", 'categories');
    }

    /**
     * Cache attribute data
     *
     * @param string $guid Attribute GUID
     * @param array $data Attribute data
     * @param int $expiration Cache expiration
     * @return bool True if cached successfully
     */
    public static function cache_attribute($guid, $data, $expiration = self::DEFAULT_EXPIRATION) {
        return self::set("attribute_{$guid}", $data, 'attributes', $expiration);
    }

    /**
     * Get cached attribute data
     *
     * @param string $guid Attribute GUID
     * @return array|false Attribute data or false if not cached
     */
    public static function get_attribute($guid) {
        return self::get("attribute_{$guid}", 'attributes');
    }

    /**
     * Cache meta lookup
     *
     * @param string $meta_key Meta key
     * @param string $meta_value Meta value
     * @param int $object_id Object ID
     * @param int $expiration Cache expiration
     * @return bool True if cached successfully
     */
    public static function cache_meta_lookup($meta_key, $meta_value, $object_id, $expiration = self::DEFAULT_EXPIRATION) {
        $key = "meta_{$meta_key}_{$meta_value}";
        return self::set($key, $object_id, 'meta', $expiration);
    }

    /**
     * Get cached meta lookup
     *
     * @param string $meta_key Meta key
     * @param string $meta_value Meta value
     * @return int|false Object ID or false if not cached
     */
    public static function get_meta_lookup($meta_key, $meta_value) {
        $key = "meta_{$meta_key}_{$meta_value}";
        return self::get($key, 'meta');
    }

    /**
     * Build cache key
     *
     * @param string $key Original key
     * @return string Built cache key
     */
    private static function build_key($key) {
        return sanitize_key($key);
    }

    /**
     * Build cache group
     *
     * @param string $group Original group
     * @return string Built cache group
     */
    private static function build_group($group) {
        return self::CACHE_GROUP . '_' . sanitize_key($group);
    }

    /**
     * Get cache statistics
     *
     * @return array Cache statistics
     */
    public static function get_stats() {
        $total_requests = self::$stats['hits'] + self::$stats['misses'];
        $hit_rate = $total_requests > 0 ? (self::$stats['hits'] / $total_requests) * 100 : 0;
        
        return array_merge(self::$stats, array(
            'total_requests' => $total_requests,
            'hit_rate' => round($hit_rate, 2)
        ));
    }

    /**
     * Reset cache statistics
     */
    public static function reset_stats() {
        self::$stats = array(
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'deletes' => 0
        );
    }

    /**
     * Warm up cache with frequently accessed data
     *
     * @param array $options Warmup options
     */
    public static function warmup($options = array()) {
        $defaults = array(
            'products' => true,
            'categories' => true,
            'attributes' => true,
            'limit' => 100
        );
        
        $options = wp_parse_args($options, $defaults);
        
        if ($options['products']) {
            self::warmup_products($options['limit']);
        }
        
        if ($options['categories']) {
            self::warmup_categories($options['limit']);
        }
        
        if ($options['attributes']) {
            self::warmup_attributes();
        }
    }

    /**
     * Warm up product cache
     *
     * @param int $limit Number of products to cache
     */
    private static function warmup_products($limit) {
        global $wpdb;
        
        $products = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, pm.meta_value as guid 
             FROM {$wpdb->posts} p 
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
             WHERE p.post_type = 'product' 
             AND p.post_status = 'publish' 
             AND pm.meta_key = '_wc1c_guid' 
             LIMIT %d",
            $limit
        ));
        
        foreach ($products as $product) {
            $product_data = array(
                'ID' => $product->ID,
                'guid' => $product->guid,
                'cached_at' => time()
            );
            
            self::cache_product($product->guid, $product_data);
        }
    }

    /**
     * Warm up category cache
     *
     * @param int $limit Number of categories to cache
     */
    private static function warmup_categories($limit) {
        global $wpdb;
        
        $categories = $wpdb->get_results($wpdb->prepare(
            "SELECT t.term_id, tm.meta_value as guid 
             FROM {$wpdb->terms} t 
             JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id 
             JOIN {$wpdb->termmeta} tm ON t.term_id = tm.term_id 
             WHERE tt.taxonomy = 'product_cat' 
             AND tm.meta_key = 'wc1c_guid' 
             LIMIT %d",
            $limit
        ));
        
        foreach ($categories as $category) {
            if (strpos($category->guid, '::') !== false) {
                list(, $guid) = explode('::', $category->guid, 2);
            } else {
                $guid = $category->guid;
            }
            
            $category_data = array(
                'term_id' => $category->term_id,
                'guid' => $guid,
                'cached_at' => time()
            );
            
            self::cache_category($guid, $category_data);
        }
    }

    /**
     * Warm up attribute cache
     */
    private static function warmup_attributes() {
        $guids = get_option('wc1c_guid_attributes', array());
        
        foreach ($guids as $guid => $attribute_id) {
            $attribute_data = array(
                'attribute_id' => $attribute_id,
                'guid' => $guid,
                'cached_at' => time()
            );
            
            self::cache_attribute($guid, $attribute_data);
        }
    }

    /**
     * Schedule cache cleanup
     */
    public static function schedule_cleanup() {
        if (!wp_next_scheduled('wc1c_cache_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wc1c_cache_cleanup');
        }
    }

    /**
     * Cleanup expired cache entries
     */
    public static function cleanup() {
        // WordPress handles cache expiration automatically
        // This method can be used for custom cleanup logic
        
        // Clear any orphaned cache entries
        self::cleanup_orphaned_entries();
        
        // Log cleanup
        if (function_exists('wc1c_log')) {
            wc1c_log('Cache cleanup completed', 'DEBUG');
        }
    }

    /**
     * Cleanup orphaned cache entries
     */
    private static function cleanup_orphaned_entries() {
        global $wpdb;
        
        // Get all cached product GUIDs
        $cached_products = array();
        // Note: This would require a custom cache backend to implement
        // For now, we'll rely on WordPress cache expiration
        
        // Get all actual product GUIDs from database
        $actual_products = $wpdb->get_col(
            "SELECT meta_value FROM {$wpdb->postmeta} 
             WHERE meta_key = '_wc1c_guid'"
        );
        
        // Remove cache entries for deleted products
        $orphaned = array_diff($cached_products, $actual_products);
        foreach ($orphaned as $guid) {
            self::delete("product_{$guid}", 'products');
        }
    }

    /**
     * Get cache size information
     *
     * @return array Cache size information
     */
    public static function get_cache_info() {
        // This would require a custom cache backend to implement properly
        // For now, return basic information
        
        return array(
            'backend' => 'WordPress Object Cache',
            'persistent' => wp_using_ext_object_cache(),
            'stats' => self::get_stats()
        );
    }

    /**
     * Export cache data for debugging
     *
     * @param string $group Cache group to export
     * @return array Cache data
     */
    public static function export_cache_data($group = 'default') {
        // This would require a custom cache backend to implement
        // For now, return empty array
        
        return array(
            'group' => $group,
            'exported_at' => time(),
            'data' => array()
        );
    }

    /**
     * Import cache data
     *
     * @param array $cache_data Cache data to import
     * @return bool True if imported successfully
     */
    public static function import_cache_data($cache_data) {
        if (!isset($cache_data['group']) || !isset($cache_data['data'])) {
            return false;
        }
        
        $group = $cache_data['group'];
        $success = true;
        
        foreach ($cache_data['data'] as $key => $value) {
            if (!self::set($key, $value, $group)) {
                $success = false;
            }
        }
        
        return $success;
    }
}

// Schedule cache cleanup
add_action('wc1c_cache_cleanup', array('WC1C_Cache_Manager', 'cleanup'));