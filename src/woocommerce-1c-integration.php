<?php
/**
 * Plugin Name: WooCommerce 1C Integration
 * Plugin URI: https://github.com/soft-hunter/woocommerce-1c-integration
 * Description: Enhanced data exchange between WooCommerce and 1C:Enterprise with modern architecture, security, and performance optimizations. Supports High-Performance Order Storage (HPOS).
 * Version: 1.0.0
 * Author: Igor Melnyk <melnyk.igor.k@gmail.com>
 * Author URI: https://github.com/soft-hunter
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: woocommerce-1c-integration
 * Domain Path: /
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 7.1
 * WC tested up to: 8.5
 * Network: false
 *
 * @package WooCommerce_1C_Integration
 * @version 1.0.0
 * @author Igor Melnyk <melnyk.igor.k@gmail.com>
 * @copyright 2025 Igor Melnyk
 * @license GPL-3.0-or-later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

// Define plugin constants
define('WC1C_VERSION', '1.0.0');
define('WC1C_MIN_PHP_VERSION', '7.4');
define('WC1C_MIN_WP_VERSION', '5.0');
define('WC1C_MIN_WC_VERSION', '7.1');
define('WC1C_PLUGIN_FILE', __FILE__);
define('WC1C_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC1C_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC1C_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Declare HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables',
            __FILE__,
            true
        );
    }
});

/**
 * The code that runs during plugin activation.
 */
function activate_wc1c() {
    require_once WC1C_PLUGIN_DIR . 'includes/class-wc1c-activator.php';
    WC1C_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_wc1c() {
    require_once WC1C_PLUGIN_DIR . 'includes/class-wc1c-deactivator.php';
    WC1C_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_wc1c');
register_deactivation_hook(__FILE__, 'deactivate_wc1c');

/**
 * Check system requirements
 */
function wc1c_check_requirements() {
    $errors = array();

    // Check PHP version
    if (version_compare(PHP_VERSION, WC1C_MIN_PHP_VERSION, '<')) {
        $errors[] = sprintf(
            __('WooCommerce 1C Integration requires PHP %s or higher. You are running PHP %s.', 'woocommerce-1c-integration'),
            WC1C_MIN_PHP_VERSION,
            PHP_VERSION
        );
    }

    // Check WordPress version
    if (version_compare(get_bloginfo('version'), WC1C_MIN_WP_VERSION, '<')) {
        $errors[] = sprintf(
            __('WooCommerce 1C Integration requires WordPress %s or higher. You are running WordPress %s.', 'woocommerce-1c-integration'),
            WC1C_MIN_WP_VERSION,
            get_bloginfo('version')
        );
    }

    // Check WooCommerce
    if (!class_exists('WooCommerce')) {
        $errors[] = __('WooCommerce 1C Integration requires WooCommerce to be installed and activated.', 'woocommerce-1c-integration');
    } elseif (defined('WC_VERSION') && version_compare(WC_VERSION, WC1C_MIN_WC_VERSION, '<')) {
        $errors[] = sprintf(
            __('WooCommerce 1C Integration requires WooCommerce %s or higher. You are running WooCommerce %s.', 'woocommerce-1c-integration'),
            WC1C_MIN_WC_VERSION,
            WC_VERSION
        );
    }

    // Check required PHP extensions
    $required_extensions = array('xml', 'mbstring', 'curl', 'zip');
    foreach ($required_extensions as $extension) {
        if (!extension_loaded($extension)) {
            $errors[] = sprintf(
                __('WooCommerce 1C Integration requires the PHP %s extension.', 'woocommerce-1c-integration'),
                $extension
            );
        }
    }

    // Display errors if any
    if (!empty($errors)) {
        add_action('admin_notices', function() use ($errors) {
            foreach ($errors as $error) {
                printf('<div class="error"><p>%s</p></div>', esc_html($error));
            }
        });
        return false;
    }

    return true;
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
function wc1c_init_plugin() {
    // Check if requirements are met
    if (!wc1c_check_requirements()) {
        return;
    }

    // Load the main plugin class
    require_once WC1C_PLUGIN_DIR . 'includes/class-wc1c.php';

    // Initialize the plugin
    $plugin = new WC1C();
    $plugin->run();
}

// Initialize plugin after WordPress and plugins are loaded
add_action('plugins_loaded', 'wc1c_init_plugin');
