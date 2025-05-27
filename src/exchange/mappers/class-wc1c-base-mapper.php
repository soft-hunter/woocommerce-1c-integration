<?php
/**
 * Base data mapper for 1C integration
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
 * Base data mapper for 1C integration
 */
abstract class WC1C_Base_Mapper {

    /**
     * Map 1C data to WooCommerce format
     *
     * @param array $data_1c 1C data
     * @return array WooCommerce data
     */
    abstract public function map_to_woocommerce($data_1c);

    /**
     * Map WooCommerce data to 1C format
     *
     * @param array $data_wc WooCommerce data
     * @return array 1C data
     */
    abstract public function map_to_1c($data_wc);

    /**
     * Validate data
     *
     * @param array $data Data to validate
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    abstract public function validate($data);

    /**
     * Get value from array with default
     *
     * @param array $array Source array
     * @param string $key Array key
     * @param mixed $default Default value
     * @return mixed Value or default
     */
    protected function get_value($array, $key, $default = null) {
        return isset($array[$key]) ? $array[$key] : $default;
    }

    /**
     * Parse decimal number from 1C format
     *
     * @param string|float $number Number to parse
     * @return float Parsed number
     */
    protected function parse_decimal($number) {
        if (is_numeric($number)) {
            return (float) $number;
        }

        // Handle 1C decimal format (comma as decimal separator)
        $number = str_replace(array(',', ' '), array('.', ''), (string) $number);
        return (float) $number;
    }

    /**
     * Format decimal number for 1C
     *
     * @param float $number Number to format
     * @param int $decimals Number of decimal places
     * @return string Formatted number
     */
    protected function format_decimal($number, $decimals = 2) {
        return number_format((float) $number, $decimals, '.', '');
    }

    /**
     * Sanitize text for safe storage
     *
     * @param string $text Text to sanitize
     * @return string Sanitized text
     */
    protected function sanitize_text($text) {
        return sanitize_text_field(trim($text));
    }

    /**
     * Sanitize HTML content
     *
     * @param string $content HTML content to sanitize
     * @return string Sanitized content
     */
    protected function sanitize_html($content) {
        return wp_kses_post(trim($content));
    }

    /**
     * Convert boolean value
     *
     * @param mixed $value Value to convert
     * @return bool Boolean value
     */
    protected function to_boolean($value) {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, array('true', '1', 'yes', 'on'), true);
        }

        return (bool) $value;
    }

    /**
     * Convert boolean to 1C format
     *
     * @param bool $value Boolean value
     * @return string 1C boolean format
     */
    protected function from_boolean($value) {
        return $value ? 'true' : 'false';
    }

    /**
     * Generate unique slug
     *
     * @param string $text Text to convert to slug
     * @param string $separator Separator character
     * @return string Generated slug
     */
    protected function generate_slug($text, $separator = '-') {
        return sanitize_title($text, '', $separator);
    }

    /**
     * Validate GUID format
     *
     * @param string $guid GUID to validate
     * @return bool True if valid GUID format
     */
    protected function is_valid_guid($guid) {
        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $guid);
    }

    /**
     * Clean up text for comparison
     *
     * @param string $text Text to clean
     * @return string Cleaned text
     */
    protected function clean_text($text) {
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Convert array to string with delimiter
     *
     * @param array $array Array to convert
     * @param string $delimiter Delimiter
     * @return string Converted string
     */
    protected function array_to_string($array, $delimiter = ' | ') {
        if (!is_array($array)) {
            return (string) $array;
        }

        return implode($delimiter, array_filter($array));
    }

    /**
     * Convert string to array with delimiter
     *
     * @param string $string String to convert
     * @param string $delimiter Delimiter
     * @return array Converted array
     */
    protected function string_to_array($string, $delimiter = ' | ') {
        if (is_array($string)) {
            return $string;
        }

        return array_filter(array_map('trim', explode($delimiter, $string)));
    }

    /**
     * Log mapping operation
     *
     * @param string $message Log message
     * @param string $level Log level
     * @param array $context Additional context
     */
    protected function log($message, $level = 'DEBUG', $context = array()) {
        if (function_exists('wc1c_log')) {
            $context['mapper'] = get_class($this);
            wc1c_log($message, $level, $context);
        }
    }

    /**
     * Apply mapping filters
     *
     * @param string $filter_name Filter name
     * @param mixed $value Value to filter
     * @param array $source_data Source data
     * @return mixed Filtered value
     */
    protected function apply_filters($filter_name, $value, $source_data = array()) {
        return apply_filters($filter_name, $value, $source_data, $this);
    }

    /**
     * Validate required fields
     *
     * @param array $data Data to validate
     * @param array $required_fields Required field names
     * @return WP_Error|bool WP_Error if validation fails, true if valid
     */
    protected function validate_required_fields($data, $required_fields) {
        $errors = new WP_Error();

        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $errors->add(
                    'missing_' . $field,
                    sprintf(__('Field "%s" is required', 'woocommerce-1c-integration'), $field)
                );
            }
        }

        return $errors->has_errors() ? $errors : true;
    }

    /**
     * Validate field length
     *
     * @param string $value Field value
     * @param int $max_length Maximum length
     * @param string $field_name Field name for error message
     * @return WP_Error|bool WP_Error if validation fails, true if valid
     */
    protected function validate_field_length($value, $max_length, $field_name) {
        if (strlen($value) > $max_length) {
            return new WP_Error(
                'field_too_long',
                sprintf(
                    __('Field "%s" is too long (max %d characters)', 'woocommerce-1c-integration'),
                    $field_name,
                    $max_length
                )
            );
        }

        return true;
    }

    /**
     * Validate numeric field
     *
     * @param mixed $value Field value
     * @param string $field_name Field name for error message
     * @param bool $allow_negative Allow negative values
     * @return WP_Error|bool WP_Error if validation fails, true if valid
     */
    protected function validate_numeric_field($value, $field_name, $allow_negative = true) {
        if (!is_numeric($value)) {
            return new WP_Error(
                'invalid_numeric',
                sprintf(__('Field "%s" must be numeric', 'woocommerce-1c-integration'), $field_name)
            );
        }

        if (!$allow_negative && $value < 0) {
            return new WP_Error(
                'negative_not_allowed',
                sprintf(__('Field "%s" cannot be negative', 'woocommerce-1c-integration'), $field_name)
            );
        }

        return true;
    }

    /**
     * Merge validation errors
     *
     * @param WP_Error $errors1 First error object
     * @param WP_Error $errors2 Second error object
     * @return WP_Error Merged errors
     */
    protected function merge_errors($errors1, $errors2) {
        if (!is_wp_error($errors1)) {
            return $errors2;
        }

        if (!is_wp_error($errors2)) {
            return $errors1;
        }

        foreach ($errors2->get_error_codes() as $code) {
            $messages = $errors2->get_error_messages($code);
            foreach ($messages as $message) {
                $errors1->add($code, $message);
            }
        }

        return $errors1;
    }
}