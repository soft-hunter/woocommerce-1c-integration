<?php
/**
 * The file that defines the core plugin class
 *
 * @package    WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/includes
 * @author     Igor Melnyk
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * The core plugin class.
 */
class WC1C {

    /**
     * The loader that's responsible for maintaining and registering all hooks.
     *
     * @since    1.0.0
     * @access   protected
     * @var      WC1C_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * @since    1.0.0
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
     * @since    1.0.0
     * @access   private
     */
    private function set_locale() {
        $plugin_i18n = new WC1C_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        $plugin_admin = new WC1C_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');
        $this->loader->add_action('admin_init', $plugin_admin, 'options_update');
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     */
    private function define_public_hooks() {
        $plugin_public = new WC1C_Public($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
    }

    /**
     * Register all of the hooks related to the exchange functionality
     */
    private function define_exchange_hooks() {
        $plugin_exchange = new WC1C_Exchange($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('init', $plugin_exchange, 'add_rewrite_rules');
        $this->loader->add_action('template_redirect', $plugin_exchange, 'handle_exchange_request');
        $this->loader->add_filter('query_vars', $plugin_exchange, 'add_query_vars');
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
     * Get post ID by meta key and value with proper SQL preparation
     */
    public static function get_post_id_by_meta($meta_key, $meta_value) {
        global $wpdb;

        if ($meta_value === null) {
            return null;
        }

        $cache_key = "wc1c_post_id_by_meta-{$meta_key}-{$meta_value}";
        $post_id = wp_cache_get($cache_key);
        
        if ($post_id === false) {
            $post_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta} 
                     JOIN {$wpdb->posts} ON post_id = ID 
                     WHERE meta_key = %s AND meta_value = %s",
                    $meta_key,
                    $meta_value
                )
            );

            if ($post_id) {
                wp_cache_set($cache_key, $post_id, '', 3600); // Cache for 1 hour
            }
        }

        return $post_id;
    }

    /**
     * Get term ID by meta key and value with proper SQL preparation
     */
    public static function get_term_id_by_meta($meta_key, $meta_value) {
        global $wpdb;

        if ($meta_value === null) {
            return null;
        }

        $cache_key = "wc1c_term_id_by_meta-{$meta_key}-{$meta_value}";
        $term_id = wp_cache_get($cache_key);
        
        if ($term_id === false) {
            $term_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT tm.term_id FROM {$wpdb->termmeta} tm 
                     JOIN {$wpdb->terms} t ON tm.term_id = t.term_id 
                     WHERE meta_key = %s AND meta_value = %s",
                    $meta_key,
                    $meta_value
                )
            );

            if ($term_id) {
                wp_cache_set($cache_key, $term_id, '', 3600); // Cache for 1 hour
            }
        }

        return $term_id;
    }

    /**
     * Get WooCommerce attribute by ID with proper SQL preparation
     */
    public static function get_woocommerce_attribute_by_id($attribute_id) {
        global $wpdb;

        $cache_key = "wc1c_woocommerce_attribute_by_id-{$attribute_id}";
        $attribute = wp_cache_get($cache_key);

        if ($attribute === false) {
            $attribute = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_id = %d",
                    $attribute_id
                ),
                ARRAY_A
            );

            if ($attribute) {
                $attribute['taxonomy'] = wc_attribute_taxonomy_name($attribute['attribute_name']);
                wp_cache_set($cache_key, $attribute, '', 3600); // Cache for 1 hour
            }
        }

        return $attribute;
    }

    /**
     * Delete WooCommerce attribute with proper cleanup
     */
    public static function delete_woocommerce_attribute($attribute_id) {
        global $wpdb;

        $attribute = self::get_woocommerce_attribute_by_id($attribute_id);
        if (!$attribute) {
            return false;
        }

        delete_option("{$attribute['taxonomy']}_children");

        $terms = get_terms($attribute['taxonomy'], "hide_empty=0");
        foreach ($terms as $term) {
            wp_delete_term($term->term_id, $attribute['taxonomy']);
        }

        $result = $wpdb->delete(
            "{$wpdb->prefix}woocommerce_attribute_taxonomies",
            array('attribute_id' => $attribute_id),
            array('%d')
        );

        // Clear cache
        wp_cache_delete("wc1c_woocommerce_attribute_by_id-{$attribute_id}");

        return $result !== false;
    }

    /**
     * Parse decimal number from 1C format
     */
    public static function parse_decimal($number) {
        $number = str_replace(array(',', ' '), array('.', ''), $number);
        return (float) $number;
    }

    /**
     * Check database error and handle it
     */
    public static function check_wpdb_error() {
        global $wpdb;

        if (!$wpdb->last_error) {
            return;
        }

        $error_message = sprintf(
            'Database error: %s for query: %s',
            $wpdb->last_error,
            $wpdb->last_query
        );

        error_log($error_message);

        if (class_exists('WC1C_Logger')) {
            WC1C_Logger::log($error_message, WC1C_Logger::ERROR);
        }

        throw new Exception($error_message);
    }
}