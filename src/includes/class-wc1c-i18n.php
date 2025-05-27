<?php
/**
 * Define the internationalization functionality
 *
 * @package    WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/includes
 * @author     Igor Melnyk <igormelnykit@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 */
class WC1C_i18n {

    /**
     * Load the plugin text domain for translation.
     *
     * @since 2.0.0
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'woocommerce-1c-integration',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
}