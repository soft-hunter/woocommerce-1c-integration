<?php
/**
 * The public-facing functionality of the plugin
 *
 * @package    WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/public
 * @author     Igor Melnyk <igormelnykit@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for public area.
 */
class WC1C_Public {

    /**
     * The ID of this plugin.
     *
     * @var string $plugin_name The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @var string $version The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @param string $plugin_name The name of the plugin.
     * @param string $version     The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     */
    public function enqueue_styles() {
        // Only enqueue if needed for public functionality
        if ($this->should_enqueue_assets()) {
            wp_enqueue_style(
                $this->plugin_name,
                WC1C_PLUGIN_URL . 'public/css/wc1c-public.css',
                array(),
                $this->version,
                'all'
            );
        }
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     */
    public function enqueue_scripts() {
        // Only enqueue if needed for public functionality
        if ($this->should_enqueue_assets()) {
            wp_enqueue_script(
                $this->plugin_name,
                WC1C_PLUGIN_URL . 'public/js/wc1c-public.js',
                array('jquery'),
                $this->version,
                false
            );
        }
    }

    /**
     * Check if we should enqueue assets
     *
     * @return bool
     */
    private function should_enqueue_assets() {
        // Only enqueue on WooCommerce pages or if specifically needed
        return is_woocommerce() || is_cart() || is_checkout() || is_account_page();
    }
}