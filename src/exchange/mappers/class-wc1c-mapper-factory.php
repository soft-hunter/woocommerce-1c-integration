<?php
/**
 * Mapper factory for 1C integration
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
 * Mapper factory for 1C integration
 */
class WC1C_Mapper_Factory {

    /**
     * Mapper instances
     *
     * @var array
     */
    private static $mappers = array();

    /**
     * Get mapper instance
     *
     * @param string $type Mapper type
     * @return WC1C_Base_Mapper|null Mapper instance or null if not found
     */
    public static function get_mapper($type) {
        if (!isset(self::$mappers[$type])) {
            self::$mappers[$type] = self::create_mapper($type);
        }

        return self::$mappers[$type];
    }

    /**
     * Create mapper instance
     *
     * @param string $type Mapper type
     * @return WC1C_Base_Mapper|null Mapper instance or null if not found
     */
    private static function create_mapper($type) {
        $mapper_classes = array(
            'group' => 'WC1C_Group_Mapper',
            'property' => 'WC1C_Property_Mapper',
            'product' => 'WC1C_Product_Mapper',
            'variation' => 'WC1C_Variation_Mapper',
            'offer' => 'WC1C_Offer_Mapper',
            'order' => 'WC1C_Order_Mapper'
        );

        if (!isset($mapper_classes[$type])) {
            return null;
        }

        $class_name = $mapper_classes[$type];

        // Load mapper class if not already loaded
        if (!class_exists($class_name)) {
            $file_name = 'class-' . str_replace('_', '-', strtolower($class_name)) . '.php';
            $file_path = WC1C_PLUGIN_DIR . 'exchange/mappers/' . $file_name;

            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }

        if (!class_exists($class_name)) {
            return null;
        }

        return new $class_name();
    }

    /**
     * Register custom mapper
     *
     * @param string $type Mapper type
     * @param string $class_name Mapper class name
     * @return bool True if registered successfully
     */
    public static function register_mapper($type, $class_name) {
        if (!class_exists($class_name)) {
            return false;
        }

        // Verify the class extends the base mapper
        if (!is_subclass_of($class_name, 'WC1C_Base_Mapper')) {
            return false;
        }

        self::$mappers[$type] = new $class_name();
        return true;
    }

    /**
     * Get all available mapper types
     *
     * @return array Available mapper types
     */
    public static function get_available_types() {
        return array('group', 'property', 'product', 'variation', 'offer', 'order');
    }

    /**
     * Clear mapper cache
     */
    public static function clear_cache() {
        self::$mappers = array();
    }
}