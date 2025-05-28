<?php
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

/**
 * The file that defines the core plugin class
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
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 */
class WC1C {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @var WC1C_Loader $loader Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @var string $plugin_name The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @var string $version The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     */
    public function __construct() {
        if (defined('WC1C_VERSION')) {
            $this->version = WC1C_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'woocommerce-1c-integration';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_exchange_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wc1c-loader.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wc1c-i18n.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-wc1c-admin.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-wc1c-public.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'exchange/class-wc1c-exchange.php';

        $this->loader = new WC1C_Loader();
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the WC1C_i18n class in order to set the domain and to register the hook
     * with WordPress.
     */
    private function set_locale() {
        $plugin_i18n = new WC1C_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     */
    private function define_admin_hooks() {
        $plugin_admin = new WC1C_Admin($this->get_plugin_name(), $this->get_version());
        
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');
        $this->loader->add_action('admin_init', $plugin_admin, 'admin_init');
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     */
    private function define_public_hooks() {
        $plugin_public = new WC1C_Public($this->get_plugin_name(), $this->get_version());
        
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
    }

    /**
     * Register all of the hooks related to the exchange functionality
     * of the plugin.
     */
    private function define_exchange_hooks() {
        $plugin_exchange = new WC1C_Exchange($this->get_plugin_name(), $this->get_version());
        
        $this->loader->add_action('init', $plugin_exchange, 'add_rewrite_rules');
        $this->loader->add_filter('query_vars', $plugin_exchange, 'add_query_vars');
        $this->loader->add_action('template_redirect', $plugin_exchange, 'handle_exchange_request');
        $this->loader->add_action('rest_api_init', $plugin_exchange, 'register_rest_routes');
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Get post ID by meta key and value
     */
    public static function get_post_id_by_meta($meta_key, $meta_value) {
        global $wpdb;
        
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = %s AND meta_value = %s 
             LIMIT 1",
            $meta_key,
            $meta_value
        ));
        
        return $post_id ? intval($post_id) : false;
    }

    /**
     * Get term ID by meta key and value
     */
    public static function get_term_id_by_meta($meta_key, $meta_value) {
        global $wpdb;
        
        $term_id = $wpdb->get_var($wpdb->prepare(
            "SELECT term_id FROM {$wpdb->termmeta} 
             WHERE meta_key = %s AND meta_value = %s 
             LIMIT 1",
            $meta_key,
            $meta_value
        ));
        
        return $term_id ? intval($term_id) : false;
    }

    /**
     * Get WooCommerce attribute by ID
     */
    public static function get_woocommerce_attribute_by_id($attribute_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}woocommerce_attribute_taxonomies 
             WHERE attribute_id = %d",
            $attribute_id
        ));
    }

    /**
     * Delete WooCommerce attribute
     */
    public static function delete_woocommerce_attribute($attribute_id) {
        global $wpdb;
        
        $attribute = self::get_woocommerce_attribute_by_id($attribute_id);
        if (!$attribute) {
            return false;
        }
        
        // Delete attribute taxonomy
        $taxonomy = wc_attribute_taxonomy_name($attribute->attribute_name);
        if (taxonomy_exists($taxonomy)) {
            $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));
            foreach ($terms as $term) {
                wp_delete_term($term->term_id, $taxonomy);
            }
        }
        
        // Delete from database
        $wpdb->delete(
            $wpdb->prefix . 'woocommerce_attribute_taxonomies',
            array('attribute_id' => $attribute_id),
            array('%d')
        );
        
        delete_transient('wc_attribute_taxonomies');
        
        return true;
    }

    /**
     * Parse decimal number from string
     */
    public static function parse_decimal($number) {
        return wc_format_decimal($number);
    }

    /**
     * Check for WordPress database errors
     */
    public static function check_wpdb_error() {
        global $wpdb;
        if (!empty($wpdb->last_error)) {
            throw new Exception('Database error: ' . $wpdb->last_error);
        }
    }
}