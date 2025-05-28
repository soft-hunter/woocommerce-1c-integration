<?php
/**
 * Data validator for 1C integration
 *
 * @package    WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/exchange/validators
 * @author     Igor Melnyk <igor.melnyk.it@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * Data validator for 1C integration
 */
class WC1C_Data_Validator {

    /**
     * Validation rules
     *
     * @var array
     */
    private $rules = array();

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_default_rules();
    }

    /**
     * Initialize default validation rules
     */
    private function init_default_rules() {
        $this->rules = array(
            'product' => array(
                'required' => array('Ид', 'Наименование'),
                'max_length' => array(
                    'Наименование' => 200,
                    'Артикул' => 100
                ),
                'numeric' => array(),
                'custom' => array()
            ),
            'group' => array(
                'required' => array('Ид', 'Наименование'),
                'max_length' => array(
                    'Наименование' => 200
                ),
                'numeric' => array(),
                'custom' => array()
            ),
            'property' => array(
                'required' => array('Ид', 'Наименование'),
                'max_length' => array(
                    'Наименование' => 200
                ),
                'numeric' => array(),
                'custom' => array()
            ),
            'offer' => array(
                'required' => array('Ид'),
                'max_length' => array(),
                'numeric' => array('Цена.ЦенаЗаЕдиницу', 'Количество'),
                'custom' => array()
            ),
            'order' => array(
                'required' => array('Ид', 'Номер'),
                'max_length' => array(),
                'numeric' => array('Сумма'),
                'custom' => array()
            )
        );

        // Allow customization of validation rules
        $this->rules = apply_filters('wc1c_validation_rules', $this->rules);
    }

    /**
     * Validate data
     *
     * @param array $data Data to validate
     * @param string $type Data type
     * @return WP_Error|bool WP_Error if validation fails, true if valid
     */
    public function validate($data, $type) {
        if (!isset($this->rules[$type])) {
            return new WP_Error('unknown_type', sprintf(__('Unknown data type: %s', 'woocommerce-1c-integration'), $type));
        }

        $rules = $this->rules[$type];
        $errors = new WP_Error();

        // Validate required fields
        $required_validation = $this->validate_required_fields($data, $rules['required']);
        if (is_wp_error($required_validation)) {
            $this->merge_errors($errors, $required_validation);
        }

        // Validate field lengths
        $length_validation = $this->validate_field_lengths($data, $rules['max_length']);
        if (is_wp_error($length_validation)) {
            $this->merge_errors($errors, $length_validation);
        }

        // Validate numeric fields
        $numeric_validation = $this->validate_numeric_fields($data, $rules['numeric']);
        if (is_wp_error($numeric_validation)) {
            $this->merge_errors($errors, $numeric_validation);
        }

        // Run custom validations
        foreach ($rules['custom'] as $validator) {
            if (is_callable($validator)) {
                $custom_validation = call_user_func($validator, $data, $type);
                if (is_wp_error($custom_validation)) {
                    $this->merge_errors($errors, $custom_validation);
                }
            }
        }

        // Apply validation filters
        $errors = apply_filters('wc1c_validation_errors', $errors, $data, $type);

        return $errors->has_errors() ? $errors : true;
    }

    /**
     * Validate required fields
     *
     * @param array $data Data to validate
     * @param array $required_fields Required field names
     * @return WP_Error|bool WP_Error if validation fails, true if valid
     */
    private function validate_required_fields($data, $required_fields) {
        $errors = new WP_Error();

        foreach ($required_fields as $field) {
            $value = $this->get_nested_value($data, $field);
            if (empty($value)) {
                $errors->add(
                    'missing_' . str_replace('.', '_', $field),
                    sprintf(__('Field "%s" is required', 'woocommerce-1c-integration'), $field)
                );
            }
        }

        return $errors->has_errors() ? $errors : true;
    }

    /**
     * Validate field lengths
     *
     * @param array $data Data to validate
     * @param array $length_rules Length validation rules
     * @return WP_Error|bool WP_Error if validation fails, true if valid
     */
    private function validate_field_lengths($data, $length_rules) {
        $errors = new WP_Error();

        foreach ($length_rules as $field => $max_length) {
            $value = $this->get_nested_value($data, $field);
            if (!empty($value) && strlen($value) > $max_length) {
                $errors->add(
                    'field_too_long_' . str_replace('.', '_', $field),
                    sprintf(
                        __('Field "%s" is too long (max %d characters)', 'woocommerce-1c-integration'),
                        $field,
                        $max_length
                    )
                );
            }
        }

        return $errors->has_errors() ? $errors : true;
    }

    /**
     * Validate numeric fields
     *
     * @param array $data Data to validate
     * @param array $numeric_fields Numeric field names
     * @return WP_Error|bool WP_Error if validation fails, true if valid
     */
    private function validate_numeric_fields($data, $numeric_fields) {
        $errors = new WP_Error();

        foreach ($numeric_fields as $field) {
            $value = $this->get_nested_value($data, $field);
            if (!empty($value) && !is_numeric($value)) {
                $errors->add(
                    'invalid_numeric_' . str_replace('.', '_', $field),
                    sprintf(__('Field "%s" must be numeric', 'woocommerce-1c-integration'), $field)
                );
            }
        }

        return $errors->has_errors() ? $errors : true;
    }

    /**
     * Get nested value from array using dot notation
     *
     * @param array $data Data array
     * @param string $key Key with dot notation (e.g., 'Цена.ЦенаЗаЕдиницу')
     * @return mixed Value or null if not found
     */
    private function get_nested_value($data, $key) {
        $keys = explode('.', $key);
        $value = $data;

        foreach ($keys as $nested_key) {
            if (!is_array($value) || !isset($value[$nested_key])) {
                return null;
            }
            $value = $value[$nested_key];
        }

        return $value;
    }

    /**
     * Merge validation errors
     *
     * @param WP_Error $target Target error object
     * @param WP_Error $source Source error object
     */
    private function merge_errors($target, $source) {
        foreach ($source->get_error_codes() as $code) {
            $messages = $source->get_error_messages($code);
            foreach ($messages as $message) {
                $target->add($code, $message);
            }
        }
    }

    /**
     * Add custom validation rule
     *
     * @param string $type Data type
     * @param callable $validator Validation function
     * @return bool True if added successfully
     */
    public function add_custom_validator($type, $validator) {
        if (!isset($this->rules[$type])) {
            return false;
        }

        if (!is_callable($validator)) {
            return false;
        }

        $this->rules[$type]['custom'][] = $validator;
        return true;
    }

    /**
     * Set validation rules for a type
     *
     * @param string $type Data type
     * @param array $rules Validation rules
     */
    public function set_rules($type, $rules) {
        $this->rules[$type] = wp_parse_args($rules, array(
            'required' => array(),
            'max_length' => array(),
            'numeric' => array(),
            'custom' => array()
        ));
    }

    /**
     * Get validation rules for a type
     *
     * @param string $type Data type
     * @return array|null Validation rules or null if type not found
     */
    public function get_rules($type) {
        return isset($this->rules[$type]) ? $this->rules[$type] : null;
    }

    /**
     * Validate SKU format
     *
     * @param string $sku SKU to validate
     * @return bool True if valid SKU format
     */
    public static function validate_sku($sku) {
        if (empty($sku)) {
            return true; // SKU is optional
        }

        // Check length
        if (strlen($sku) > 100) {
            return false;
        }

        // Check for valid characters (alphanumeric, hyphens, underscores)
        return preg_match('/^[a-zA-Z0-9\-_]+$/', $sku);
    }

    /**
     * Validate GUID format
     *
     * @param string $guid GUID to validate
     * @return bool True if valid GUID format
     */
    public static function validate_guid($guid) {
        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $guid);
    }

    /**
     * Validate price value
     *
     * @param mixed $price Price to validate
     * @return bool True if valid price
     */
    public static function validate_price($price) {
        if (!is_numeric($price)) {
            return false;
        }

        return (float) $price >= 0;
    }

    /**
     * Validate quantity value
     *
     * @param mixed $quantity Quantity to validate
     * @return bool True if valid quantity
     */
    public static function validate_quantity($quantity) {
        if (!is_numeric($quantity)) {
            return false;
        }

        return (float) $quantity >= 0;
    }

    /**
     * Validate email address
     *
     * @param string $email Email to validate
     * @return bool True if valid email
     */
    public static function validate_email($email) {
        return is_email($email);
    }

    /**
     * Validate phone number
     *
     * @param string $phone Phone to validate
     * @return bool True if valid phone
     */
    public static function validate_phone($phone) {
        // Basic phone validation - can be customized
        return preg_match('/^[\+]?[0-9\s\-\(\)]{7,20}$/', $phone);
    }

    /**
     * Validate date format
     *
     * @param string $date Date to validate
     * @param string $format Expected date format
     * @return bool True if valid date
     */
    public static function validate_date($date, $format = 'Y-m-d') {
        $datetime = DateTime::createFromFormat($format, $date);
        return $datetime && $datetime->format($format) === $date;
    }
}