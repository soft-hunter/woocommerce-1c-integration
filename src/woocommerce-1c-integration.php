<?php
/**
 * Plugin Name: WooCommerce 1C Integration
 * Description: Integrates WooCommerce with 1C:Enterprise accounting software.
 * Version: 1.0.0
 * Author: Igor Melnyk
 * Requires at least: 5.2
 * Plugin URI: https://github.com/soft-hunter/woocommerce-1c-integration
 * Requires PHP: 7.2
 * Author URI: https://github.com/soft-hunter
 * Text Domain: woocommerce-1c-integration
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * WC requires at least: 3.0.0
 * WC tested up to: 7.0.0
 * WooCommerce 1C Integration
 *
 * @package WooCommerce_1C_Integration
 * @author Igor Melnyk <igor.melnyk.it@gmail.com>
 * @copyright 2023 Igor Melnyk
 * @license GPL-2.0-or-later
 *
 * @wordpress-plugin
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

// After other plugin constants, before the HPOS compatibility check
define('WC1C_DOING_UNINSTALL', defined('WP_UNINSTALL_PLUGIN'));
define('WC1C_UNINSTALL_LOG_FILE', WP_CONTENT_DIR . '/wc1c-uninstall.log');

// Setup upload directory constant immediately to ensure it's available to all files
$upload_dir = wp_upload_dir();
define('WC1C_DATA_DIR', "{$upload_dir['basedir']}/woocommerce_uploads/1c-exchange/");

// Declare HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
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
 *
 * @return bool Whether requirements are met
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
            echo '<div class="error">';
            foreach ($errors as $error) {
                echo '<p>' . esc_html($error) . '</p>';
            }
            echo '</div>';
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

    // Create data directory if it doesn't exist
    if (!file_exists(WC1C_DATA_DIR)) {
        wp_mkdir_p(WC1C_DATA_DIR);

        // Add index.php to prevent directory listing
        $index_file = WC1C_DATA_DIR . 'index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, '<?php // Silence is golden');
        }

        // Add .htaccess for additional security on Apache
        $htaccess_file = WC1C_DATA_DIR . '.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, 'Deny from all');
        }
    }

    // Load the main plugin class
    require_once WC1C_PLUGIN_DIR . 'includes/class-wc1c.php';

    // Initialize the plugin
    $plugin = new WC1C();
    $plugin->run();

    // Register a daily maintenance action if not already scheduled
    if (!wp_next_scheduled('wc1c_daily_maintenance')) {
        wp_schedule_event(time(), 'daily', 'wc1c_daily_maintenance');
    }
}

// Initialize plugin after WordPress and plugins are loaded
add_action('plugins_loaded', 'wc1c_init_plugin', 20); // Priority 20 to ensure WooCommerce is loaded first

// Add uninstall hook registration
register_uninstall_hook(__FILE__, 'wc1c_uninstall_plugin');

/**
 * Uninstall hook callback
 */
function wc1c_uninstall_plugin() {
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        return;
    }

    // Your existing uninstall.php will handle the actual uninstallation
    require_once plugin_dir_path(__FILE__) . 'uninstall.php';
}
