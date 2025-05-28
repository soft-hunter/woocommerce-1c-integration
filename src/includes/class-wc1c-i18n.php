<?php
/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @package WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/includes
 * @author Igor Melnyk <igor.melnyk.it@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * Internationalization class
 */
class WC1C_i18n {

    /**
     * Load the plugin text domain for translation
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'woocommerce-1c-integration',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
}