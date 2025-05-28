<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @package    WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/includes
 * @author     Igor Melnyk <igor.melnyk.it@gmail.com>
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
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @var      WC1C_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @var      string    $version    The current version of the plugin.
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
        $this->version = WC1C_VERSION;
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
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @access   private
     */
    private function load_dependencies() {
        // The class responsible for orchestrating the actions and filters of the core plugin.
        require_once WC1C_PLUGIN_DIR . 'includes/class-wc1c-loader.php';

        // The class responsible for defining internationalization functionality of the plugin.
        require_once WC1C_PLUGIN_DIR . 'includes/class-wc1c-i18n.php';
        
        // The class responsible for logging plugin events.
        require_once WC1C_PLUGIN_DIR . 'includes/class-wc1c-logger.php';

        // The class responsible for defining all actions that occur in the admin area.
        require_once WC1C_PLUGIN_DIR . 'admin/class-wc1c-admin.php';

        // The class responsible for defining all actions that occur in the public-facing side.
        require_once WC1C_PLUGIN_DIR . 'public/class-wc1c-public.php';
        
        // The class responsible for the exchange functionality.
        require_once WC1C_PLUGIN_DIR . 'exchange/class-wc1c-exchange.php';

        // Create loader instance
        $this->loader = new WC1C_Loader();
        
        // Create directories needed for plugin operation
        $this->create_directories();
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the WC1C_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @access   private
     */
    private function set_locale() {
        $plugin_i18n = new WC1C_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @access   private
     */
    private function define_admin_hooks() {
        $plugin_admin = new WC1C_Admin($this->get_plugin_name(), $this->get_version());

        // Admin styles and scripts
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        
        // Admin menu and settings
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_menu_pages');
        $this->loader->add_action('admin_init', $plugin_admin, 'register_settings');
        
        // Plugin links
        $this->loader->add_filter('plugin_action_links_' . WC1C_PLUGIN_BASENAME, $plugin_admin, 'add_action_links');
        $this->loader->add_filter('plugin_row_meta', $plugin_admin, 'add_plugin_row_meta', 10, 2);
        
        // Ajax handlers
        $this->loader->add_action('wp_ajax_wc1c_clear_logs', $plugin_admin, 'ajax_clear_logs');
        $this->loader->add_action('wp_ajax_wc1c_get_logs', $plugin_admin, 'ajax_get_logs');
        $this->loader->add_action('wp_ajax_wc1c_test_connection', $plugin_admin, 'ajax_test_connection');
        
        // Maintenance tasks
        $this->loader->add_action('admin_init', $plugin_admin, 'schedule_maintenance_tasks');
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @access   private
     */
    private function define_public_hooks() {
        $plugin_public = new WC1C_Public($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
    }
    
    /**
     * Register all of the hooks related to the exchange functionality
     * of the plugin.
     *
     * @access   private
     */
    private function define_exchange_hooks() {
        $exchange = new WC1C_Exchange($this->get_plugin_name(), $this->get_version());
        
        // Exchange endpoints
        $this->loader->add_action('init', $exchange, 'register_endpoints');
        $this->loader->add_action('parse_request', $exchange, 'handle_request');
        
        // Order hooks
        $this->loader->add_action('woocommerce_new_order', $exchange, 'on_new_order');
        $this->loader->add_action('woocommerce_order_status_changed', $exchange, 'on_order_status_changed', 10, 3);
        
        // Maintenance
        $this->loader->add_action('wc1c_cleanup_temp_files', $exchange, 'cleanup_temp_files');
    }
    
    /**
     * Create necessary directories for plugin operation
     */
    private function create_directories() {
        // Make sure upload directory exists
        if (!is_dir(WC1C_DATA_DIR)) {
            wp_mkdir_p(WC1C_DATA_DIR);
        }
        
        // Create subdirectories
        $dirs = array(
            WC1C_DATA_DIR . 'logs/',
            WC1C_DATA_DIR . 'images/',
            WC1C_DATA_DIR . 'temp/',
            WC1C_DATA_DIR . 'import/',
        );
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }
        }
        
        // Create index.php files to prevent directory listing
        $index_file = "<?php\n// Silence is golden.";
        @file_put_contents(WC1C_DATA_DIR . 'index.php', $index_file);
        
        foreach ($dirs as $dir) {
            @file_put_contents($dir . '/index.php', $index_file);
        }
    }

    /**
     * Run the loader to execute all the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @return    WC1C_Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }
    
    /**
     * Helper function to get post by custom meta field value
     *
     * @param string $meta_key Meta key to search by
     * @param string $meta_value Meta value to search for
     * @return int|null Post ID or null if not found
     */
    public function get_post_id_by_meta($meta_key, $meta_value) {
        global $wpdb;
        
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
            $meta_key,
            $meta_value
        ));
        
        return $post_id ? (int) $post_id : null;
    }
    
    /**
     * Helper function to get term by custom meta field value
     *
     * @param string $meta_key Meta key to search by
     * @param string $meta_value Meta value to search for
     * @return int|null Term ID or null if not found
     */
    public function get_term_id_by_meta($meta_key, $meta_value) {
        global $wpdb;
        
        $term_id = $wpdb->get_var($wpdb->prepare(
            "SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
            $meta_key,
            $meta_value
        ));
        
        return $term_id ? (int) $term_id : null;
    }
    
    /**
     * Helper function to get WooCommerce attribute by ID
     *
     * @param int $attribute_id Attribute ID
     * @return array|null Attribute data or null if not found
     */
    public function get_woocommerce_attribute_by_id($attribute_id) {
        global $wpdb;
        
        $attribute = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_id = %d",
            $attribute_id
        ), ARRAY_A);
        
        return $attribute;
    }
    
    /**
     * Helper function to parse decimal value
     * 
     * @param mixed $value Value to parse
     * @return float Parsed decimal value
     */
    public function parse_decimal($value) {
        // Replace comma with dot for proper float conversion
        $value = str_replace(',', '.', $value);
        
        // Remove all non-numeric characters except dot
        $value = preg_replace('/[^0-9.-]/', '', $value);
        
        return (float) $value;
    }
}