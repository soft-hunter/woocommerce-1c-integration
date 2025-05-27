<?php
/*
Plugin Name: WooCommerce 1C Integration
Version: 1.0.0
Description: Enhanced data exchange between WooCommerce and 1C:Enterprise.
Author: Igor Melnyk
Author URI: https://github.com/soft-hunter
Plugin URI: https://github.com/soft-hunter/woocommerce-1c-integration
Text Domain: woocommerce-1c-integration
Domain Path: /languages
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
WC requires at least: 5.0
WC tested up to: 8.0
License: GPL v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Network: false
*/

if (!defined('ABSPATH')) exit;

// Prevent direct access
if (!function_exists('add_action')) {
    echo 'Hi there! I\'m just a plugin, not much I can do when called directly.';
    exit;
}

require_once ABSPATH . "wp-admin/includes/plugin.php";

if (!defined('__DIR__')) define('__DIR__', dirname(__FILE__));

// Plugin constants
define('WC1C_PLUGIN_DIR', __DIR__ . '/');
define('WC1C_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WC1C_PLUGIN_BASEDIR', dirname(WC1C_PLUGIN_BASENAME) . '/');
define('WC1C_VERSION', '1.0.0');
define('WC1C_MIN_PHP_VERSION', '7.4');
define('WC1C_MIN_WP_VERSION', '5.0');
define('WC1C_MIN_WC_VERSION', '5.0');

// Data directory configuration
$upload_dir = wp_upload_dir();
define('WC1C_DATA_DIR', "{$upload_dir['basedir']}/woocommerce_uploads/1c-exchange/");

// Configuration constants with secure defaults
if (!defined('WC1C_SUPPRESS_NOTICES')) define('WC1C_SUPPRESS_NOTICES', true);
if (!defined('WC1C_FILE_LIMIT')) define('WC1C_FILE_LIMIT', '100M');
if (!defined('WC1C_XML_CHARSET')) define('WC1C_XML_CHARSET', 'UTF-8');
if (!defined('WC1C_DISABLE_VARIATIONS')) define('WC1C_DISABLE_VARIATIONS', false);
if (!defined('WC1C_OUTOFSTOCK_STATUS')) define('WC1C_OUTOFSTOCK_STATUS', 'outofstock');
if (!defined('WC1C_MANAGE_STOCK')) define('WC1C_MANAGE_STOCK', 'yes');
if (!defined('WC1C_CLEANUP_GARBAGE')) define('WC1C_CLEANUP_GARBAGE', true);
if (!defined('WC1C_ENABLE_LOGGING')) define('WC1C_ENABLE_LOGGING', true);
if (!defined('WC1C_MAX_EXECUTION_TIME')) define('WC1C_MAX_EXECUTION_TIME', 300);
if (!defined('WC1C_RATE_LIMIT')) define('WC1C_RATE_LIMIT', 60);

// Product import settings
if (!defined('WC1C_PRODUCT_DESCRIPTION_TO_CONTENT')) define('WC1C_PRODUCT_DESCRIPTION_TO_CONTENT', false);
if (!defined('WC1C_PREVENT_CLEAN')) define('WC1C_PREVENT_CLEAN', false);
if (!defined('WC1C_UPDATE_POST_NAME')) define('WC1C_UPDATE_POST_NAME', false);
if (!defined('WC1C_MATCH_BY_SKU')) define('WC1C_MATCH_BY_SKU', false);
if (!defined('WC1C_MATCH_CATEGORIES_BY_TITLE')) define('WC1C_MATCH_CATEGORIES_BY_TITLE', false);
if (!defined('WC1C_MATCH_PROPERTIES_BY_TITLE')) define('WC1C_MATCH_PROPERTIES_BY_TITLE', false);
if (!defined('WC1C_MATCH_PROPERTY_OPTIONS_BY_TITLE')) define('WC1C_MATCH_PROPERTY_OPTIONS_BY_TITLE', false);
if (!defined('WC1C_USE_GUID_AS_PROPERTY_OPTION_SLUG')) define('WC1C_USE_GUID_AS_PROPERTY_OPTION_SLUG', true);

// Price and offer settings
if (!defined('WC1C_PRICE_TYPE')) define('WC1C_PRICE_TYPE', null);
if (!defined('WC1C_CURRENCY')) define('WC1C_CURRENCY', null);
if (!defined('WC1C_PRESERVE_PRODUCT_VARIATIONS')) define('WC1C_PRESERVE_PRODUCT_VARIATIONS', false);

/**
 * Check system requirements
 */
function wc1c_check_requirements()
{
    $errors = array();

    // Check PHP version
    if (version_compare(PHP_VERSION, WC1C_MIN_PHP_VERSION, '<')) {
        $errors[] = sprintf(
            __('WooCommerce 1C requires PHP %s or higher. You are running PHP %s.', 'woocommerce-1c-integration'),
            WC1C_MIN_PHP_VERSION,
            PHP_VERSION
        );
    }

    // Check WordPress version
    if (version_compare(get_bloginfo('version'), WC1C_MIN_WP_VERSION, '<')) {
        $errors[] = sprintf(
            __('WooCommerce 1C requires WordPress %s or higher. You are running WordPress %s.', 'woocommerce-1c-integration'),
            WC1C_MIN_WP_VERSION,
            get_bloginfo('version')
        );
    }

    // Check required PHP extensions
    $required_extensions = array('xml', 'mbstring', 'curl');
    foreach ($required_extensions as $extension) {
        if (!extension_loaded($extension)) {
            $errors[] = sprintf(
                __('WooCommerce 1C requires the PHP %s extension.', 'woocommerce-1c-integration'),
                $extension
            );
        }
    }

    return $errors;
}

/**
 * Initialize plugin
 */
function wc1c_init()
{
    // Check system requirements
    $requirement_errors = wc1c_check_requirements();
    if (!empty($requirement_errors)) {
        add_action('admin_notices', function () use ($requirement_errors) {
            foreach ($requirement_errors as $error) {
                printf('<div class="error"><p>%s</p></div>', esc_html($error));
            }
        });
        return;
    }

    // Check if WooCommerce is active
    if (!is_plugin_active("woocommerce/woocommerce.php")) {
        add_action('admin_notices', 'wc1c_woocommerce_admin_notices');
        return;
    }

    // Check WooCommerce version
    if (defined('WC_VERSION') && version_compare(WC_VERSION, WC1C_MIN_WC_VERSION, '<')) {
        add_action('admin_notices', function () {
            $message = sprintf(
                __('WooCommerce 1C requires WooCommerce %s or higher. You are running WooCommerce %s.', 'woocommerce-1c-integration'),
                WC1C_MIN_WC_VERSION,
                WC_VERSION
            );
            printf('<div class="error"><p>%s</p></div>', esc_html($message));
        });
        return;
    }

    // Load plugin functionality
    wc1c_load_plugin();
}
add_action('init', 'wc1c_init');

/**
 * WooCommerce missing notice
 */
function wc1c_woocommerce_admin_notices()
{
    $plugin_data = get_plugin_data(__FILE__);
    $message = sprintf(
        __('Plugin <strong>%s</strong> requires plugin <strong>WooCommerce</strong> to be installed and activated.', 'woocommerce-1c-integration'),
        $plugin_data['Name']
    );
    printf('<div class="error"><p>%s</p></div>', $message);
}

/**
 * Load plugin functionality
 */
function wc1c_load_plugin()
{
    // Load text domain
    add_action('plugins_loaded', 'wc1c_plugins_loaded');

    // Initialize logging
    wc1c_init_logging();

    // Log plugin initialization
    wc1c_log('Plugin initialized', 'INFO');
}

/**
 * Load plugin text domain
 */
function wc1c_plugins_loaded()
{
    $plugin_data = get_plugin_data(__FILE__);
    $languages_dir = WC1C_PLUGIN_BASEDIR . '/languages';
    load_plugin_textdomain('woocommerce-1c-integration', false, $languages_dir);
}

/**
 * Initialize logging system
 */
function wc1c_init_logging()
{
    if (!WC1C_ENABLE_LOGGING) return;

    // Ensure logs directory exists
    $logs_dir = WC1C_DATA_DIR . 'logs';
    if (!is_dir($logs_dir)) {
        wp_mkdir_p($logs_dir);
        file_put_contents($logs_dir . '/index.html', '');
        file_put_contents($logs_dir . '/.htaccess', 'Deny from all');
    }
}

/**
 * Plugin activation
 */
function wc1c_activate()
{
    global $wpdb;

    // Check requirements on activation
    $requirement_errors = wc1c_check_requirements();
    if (!empty($requirement_errors)) {
        wp_die(
            implode('<br>', $requirement_errors),
            __('Plugin Activation Error', 'woocommerce-1c-integration'),
            array('back_link' => true)
        );
    }

    // Log activation
    wc1c_log('Plugin activation started', 'INFO');

    // Create database indexes for performance
    wc1c_create_database_indexes();

    // Create all required directories with proper structure
    wc1c_create_directories();

    // Add rewrite rules
    wc1c_add_rewrite_rules();
    flush_rewrite_rules();

    // Set activation flag
    update_option('wc1c_activated', true);
    update_option('wc1c_version', WC1C_VERSION);

    wc1c_log('Plugin activation completed', 'INFO');
}
register_activation_hook(__FILE__, 'wc1c_activate');

/**
 * Create database indexes for performance
 */
function wc1c_create_database_indexes()
{
    global $wpdb;

    $index_table_names = array(
        $wpdb->postmeta,
        $wpdb->termmeta,
        $wpdb->usermeta,
    );

    foreach ($index_table_names as $index_table_name) {
        $index_name = 'wc1c_meta_key_meta_value';
        $result = $wpdb->get_var($wpdb->prepare(
            "SHOW INDEX FROM `%s` WHERE Key_name = %s",
            $index_table_name,
            $index_name
        ));

        if ($result) continue;

        $wpdb->query($wpdb->prepare(
            "ALTER TABLE `%s` ADD INDEX `%s` (meta_key, meta_value(36))",
            $index_table_name,
            $index_name
        ));

        if ($wpdb->last_error) {
            wc1c_log("Failed to create index on $index_table_name: " . $wpdb->last_error, 'ERROR');
        } else {
            wc1c_log("Created index on $index_table_name", 'INFO');
        }
    }
}

/**
 * Create all required directories
 */
function wc1c_create_directories()
{
    $directories = array(
        WC1C_DATA_DIR,
        WC1C_DATA_DIR . 'catalog',
        WC1C_DATA_DIR . 'sale',
        WC1C_DATA_DIR . 'logs',
        WC1C_DATA_DIR . 'temp'
    );

    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            if (!wp_mkdir_p($dir)) {
                wc1c_log("Failed to create directory: $dir", 'ERROR');
                continue;
            }

            // Set proper permissions
            chmod($dir, 0755);

            // Add security files
            file_put_contents($dir . '/index.html', '');

            // Add .htaccess for data directories (not logs)
            if (!in_array(basename($dir), array('logs', 'temp'))) {
                $htaccess_content = "Deny from all\n<Files \"*.xml\">\n  Allow from all\n</Files>";
                file_put_contents($dir . '/.htaccess', $htaccess_content);
            } else {
                // Logs directory should be completely protected
                file_put_contents($dir . '/.htaccess', 'Deny from all');
            }

            wc1c_log("Created directory: $dir", 'INFO');
        }
    }
}

/**
 * Plugin deactivation
 */
function wc1c_deactivate()
{
    wc1c_log('Plugin deactivation started', 'INFO');

    flush_rewrite_rules();

    // Clear any scheduled events
    wp_clear_scheduled_hook('wc1c_cleanup_logs');

    wc1c_log('Plugin deactivation completed', 'INFO');
}
register_deactivation_hook(__FILE__, 'wc1c_deactivate');

/**
 * Add rewrite rules
 */
function wc1c_add_rewrite_rules()
{
    add_rewrite_rule("wc1c/exchange/?$", "index.php?wc1c=exchange", 'top');
    add_rewrite_rule("wc1c/clean/?$", "index.php?wc1c=clean", 'top');
}

/**
 * Clean up term metadata on term deletion
 */
function wc1c_delete_term($term_id, $tt_id, $taxonomy, $deleted_term)
{
    global $wpdb;

    if ($taxonomy != 'product_cat' && strpos($taxonomy, 'pa_') !== 0) return;

    $wpdb->delete($wpdb->termmeta, array('term_id' => $term_id));
    if (function_exists('wc1c_check_wpdb_error')) wc1c_check_wpdb_error();

    wc1c_log("Cleaned up metadata for deleted term: $term_id", 'DEBUG');
}
add_action('delete_term', 'wc1c_delete_term', 10, 4);

/**
 * Get WooCommerce attribute by ID with caching
 */
function wc1c_woocommerce_attribute_by_id($attribute_id)
{
    global $wpdb;

    $cache_key = "wc1c_woocommerce_attribute_by_id-$attribute_id";
    $attribute = wp_cache_get($cache_key);

    if ($attribute === false) {
        $attribute = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_id = %d",
            $attribute_id
        ), ARRAY_A);

        if (function_exists('wc1c_check_wpdb_error')) wc1c_check_wpdb_error();

        if ($attribute) {
            $attribute['taxonomy'] = wc_attribute_taxonomy_name($attribute['attribute_name']);
            wp_cache_set($cache_key, $attribute, '', 3600); // Cache for 1 hour
        }
    }

    return $attribute;
}

/**
 * Delete WooCommerce attribute
 */
function wc1c_delete_woocommerce_attribute($attribute_id)
{
    global $wpdb;

    $attribute = wc1c_woocommerce_attribute_by_id($attribute_id);
    if (!$attribute) return false;

    delete_option("{$attribute['taxonomy']}_children");

    $terms = get_terms($attribute['taxonomy'], "hide_empty=0");
    foreach ($terms as $term) {
        wp_delete_term($term->term_id, $attribute['taxonomy']);
    }

    $wpdb->delete("{$wpdb->prefix}woocommerce_attribute_taxonomies", array('attribute_id' => $attribute_id));
    if (function_exists('wc1c_check_wpdb_error')) wc1c_check_wpdb_error();

    // Clear cache
    wp_cache_delete("wc1c_woocommerce_attribute_by_id-$attribute_id");

    wc1c_log("Deleted WooCommerce attribute: $attribute_id", 'INFO');

    return true;
}

/**
 * Parse decimal number from 1C format
 */
function wc1c_parse_decimal($number)
{
    $number = str_replace(array(',', ' '), array('.', ''), $number);
    return (float) $number;
}

/**
 * Enhanced logging function
 */
function wc1c_log($message, $level = 'INFO', $context = array())
{
    if (!WC1C_ENABLE_LOGGING) return;

    // Ensure logs directory exists
    $logs_dir = WC1C_DATA_DIR . 'logs';
    if (!is_dir($logs_dir)) {
        wp_mkdir_p($logs_dir);
        file_put_contents($logs_dir . '/index.html', '');
        file_put_contents($logs_dir . '/.htaccess', 'Deny from all');
    }

    $log_file = $logs_dir . '/wc1c-' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user = wp_get_current_user();
    $username = $user->exists() ? $user->user_login : 'anonymous';

    $context_str = !empty($context) ? ' ' . json_encode($context) : '';
    $log_entry = "[$timestamp] [$level] [IP:$ip] [User:$username] $message$context_str" . PHP_EOL;

    // Use file locking to prevent corruption
    $fp = fopen($log_file, 'a');
    if ($fp) {
        if (flock($fp, LOCK_EX)) {
            fwrite($fp, $log_entry);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

    // Rotate logs if they get too large (>10MB)
    if (file_exists($log_file) && filesize($log_file) > 10 * 1024 * 1024) {
        rename($log_file, $log_file . '.old');
        wc1c_log('Log file rotated', 'INFO');
    }
}

/**
 * Schedule log cleanup
 */
function wc1c_schedule_log_cleanup()
{
    if (!wp_next_scheduled('wc1c_cleanup_logs')) {
        wp_schedule_event(time(), 'daily', 'wc1c_cleanup_logs');
    }
}
add_action('wp', 'wc1c_schedule_log_cleanup');

/**
 * Clean up old log files
 */
function wc1c_cleanup_old_logs()
{
    $logs_dir = WC1C_DATA_DIR . 'logs';
    if (!is_dir($logs_dir)) return;

    $files = glob($logs_dir . '/wc1c-*.log*');
    $cutoff_time = time() - (30 * DAY_IN_SECONDS); // Keep logs for 30 days

    foreach ($files as $file) {
        if (filemtime($file) < $cutoff_time) {
            unlink($file);
        }
    }
}
add_action('wc1c_cleanup_logs', 'wc1c_cleanup_old_logs');

/**
 * Add admin menu and settings
 */
function wc1c_admin_menu()
{
    if (!current_user_can('manage_woocommerce')) return;

    add_submenu_page(
        'woocommerce',
        __('1C Exchange', 'woocommerce-1c-integration'),
        __('1C Exchange', 'woocommerce-1c-integration'),
        'manage_woocommerce',
        'wc1c-settings',
        'wc1c_admin_page'
    );
}
add_action('admin_menu', 'wc1c_admin_menu');

/**
 * Admin page content
 */
function wc1c_admin_page()
{
?>
    <div class="wrap">
        <h1><?php _e('WooCommerce 1C Exchange', 'woocommerce-1c-integration'); ?></h1>

        <div class="card">
            <h2><?php _e('Exchange URL', 'woocommerce-1c-integration'); ?></h2>
            <p><?php _e('Use this URL in your 1C configuration:', 'woocommerce-1c-integration'); ?></p>
            <code><?php echo home_url('/?wc1c=exchange'); ?></code>
            <p><em><?php _e('Or if you have pretty permalinks enabled:', 'woocommerce-1c-integration'); ?></em></p>
            <code><?php echo home_url('/wc1c/exchange/'); ?></code>
        </div>

        <div class="card">
            <h2><?php _e('System Status', 'woocommerce-1c-integration'); ?></h2>
            <table class="widefat">
                <tbody>
                    <tr>
                        <td><?php _e('Plugin Version', 'woocommerce-1c-integration'); ?></td>
                        <td><?php echo WC1C_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Data Directory', 'woocommerce-1c-integration'); ?></td>
                        <td><?php echo is_dir(WC1C_DATA_DIR) ? '✅ ' . WC1C_DATA_DIR : '❌ Not found'; ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Logging', 'woocommerce-1c-integration'); ?></td>
                        <td><?php echo WC1C_ENABLE_LOGGING ? '✅ Enabled' : '❌ Disabled'; ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('PHP Version', 'woocommerce-1c-integration'); ?></td>
                        <td><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Memory Limit', 'woocommerce-1c-integration'); ?></td>
                        <td><?php echo ini_get('memory_limit'); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Max Execution Time', 'woocommerce-1c-integration'); ?></td>
                        <td><?php echo ini_get('max_execution_time'); ?>s</td>
                    </tr>
                    <tr>
                        <td><?php _e('Post Max Size', 'woocommerce-1c-integration'); ?></td>
                        <td><?php echo ini_get('post_max_size'); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Upload Max Filesize', 'woocommerce-1c-integration'); ?></td>
                        <td><?php echo ini_get('upload_max_filesize'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php if (WC1C_ENABLE_LOGGING): ?>
            <div class="card">
                <h2><?php _e('Recent Log Entries', 'woocommerce-1c-integration'); ?></h2>
                <?php wc1c_display_recent_logs(); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2><?php _e('Configuration Constants', 'woocommerce-1c-integration'); ?></h2>
            <p><?php _e('Add these constants to your wp-config.php file to customize the plugin behavior:', 'woocommerce-1c-integration'); ?></p>
            <pre><code>// Basic Settings
define('WC1C_ENABLE_LOGGING', true);
define('WC1C_FILE_LIMIT', '100M');
define('WC1C_MAX_EXECUTION_TIME', 300);
define('WC1C_RATE_LIMIT', 60);

// Product Import Settings
define('WC1C_MATCH_BY_SKU', false);
define('WC1C_MATCH_CATEGORIES_BY_TITLE', false);
define('WC1C_PREVENT_CLEAN', false);

// Stock Management
define('WC1C_MANAGE_STOCK', 'yes');
define('WC1C_OUTOFSTOCK_STATUS', 'outofstock');</code></pre>
        </div>
    </div>
<?php
}

/**
 * Display recent log entries
 */
function wc1c_display_recent_logs()
{
    $log_file = WC1C_DATA_DIR . 'logs/wc1c-' . date('Y-m-d') . '.log';

    if (!file_exists($log_file)) {
        echo '<p>' . __('No log entries for today.', 'woocommerce-1c-integration') . '</p>';
        return;
    }

    $lines = file($log_file);
    $recent_lines = array_slice($lines, -20); // Last 20 lines

    echo '<div style="background: #f1f1f1; padding: 10px; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px;">';
    foreach ($recent_lines as $line) {
        echo esc_html($line) . '<br>';
    }
    echo '</div>';
}

/**
 * Add plugin action links
 */
function wc1c_plugin_action_links($actions)
{
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        admin_url('admin.php?page=wc1c-settings'),
        __('Settings', 'woocommerce-1c-integration')
    );

    array_unshift($actions, $settings_link);

    return $actions;
}
add_filter('plugin_action_links_' . WC1C_PLUGIN_BASENAME, 'wc1c_plugin_action_links');

/**
 * Add plugin row meta
 */
function wc1c_plugin_row_meta($plugin_meta, $plugin_file)
{
    if ($plugin_file != WC1C_PLUGIN_BASENAME) return $plugin_meta;

    $plugin_meta_after = array(
        'docs' => sprintf(
            '<a href="%s" target="_blank">%s</a>',
            'https://github.com/soft-hunter/woocommerce-1c-integration/wiki',
            __('Documentation', 'woocommerce-1c-integration')
        ),
        'support' => sprintf(
            '<a href="%s" target="_blank">%s</a>',
            'https://github.com/soft-hunter/woocommerce-1c-integration/issues',
            __('Support', 'woocommerce-1c-integration')
        ),
    );

    return array_merge($plugin_meta, $plugin_meta_after);
}
add_filter('plugin_row_meta', 'wc1c_plugin_row_meta', 10, 2);

/**
 * Check if plugin needs update
 */
function wc1c_check_version()
{
    $installed_version = get_option('wc1c_version', '0.0.0');

    if (version_compare($installed_version, WC1C_VERSION, '<')) {
        wc1c_upgrade($installed_version);
        update_option('wc1c_version', WC1C_VERSION);
    }
}
add_action('admin_init', 'wc1c_check_version');

/**
 * Handle plugin upgrades
 */
function wc1c_upgrade($from_version)
{
    wc1c_log("Upgrading from version $from_version to " . WC1C_VERSION, 'INFO');

    // Ensure directories exist
    wc1c_create_directories();

    // Recreate database indexes if needed
    if (version_compare($from_version, '1.0.0', '<')) {
        wc1c_create_database_indexes();
    }

    wc1c_log("Upgrade completed", 'INFO');
}

/**
 * Add admin notices
 */
function wc1c_admin_notices()
{
    // Check if data directory is writable
    if (!is_writable(WC1C_DATA_DIR)) {
        printf(
            '<div class="error"><p>%s</p></div>',
            sprintf(
                __('WooCommerce 1C data directory is not writable: %s', 'woocommerce-1c-integration'),
                WC1C_DATA_DIR
            )
        );
    }

    // Check if required PHP extensions are loaded
    $required_extensions = array('xml', 'mbstring', 'curl');
    foreach ($required_extensions as $extension) {
        if (!extension_loaded($extension)) {
            printf(
                '<div class="error"><p>%s</p></div>',
                sprintf(
                    __('WooCommerce 1C requires the PHP %s extension.', 'woocommerce-1c-integration'),
                    $extension
                )
            );
        }
    }
}
add_action('admin_notices', 'wc1c_admin_notices');

/**
 * Handle AJAX requests for log viewing
 */
function wc1c_ajax_get_logs()
{
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('Insufficient permissions', 'woocommerce-1c-integration'));
    }

    check_ajax_referer('wc1c_logs', 'nonce');

    $date = sanitize_text_field($_POST['date'] ?? date('Y-m-d'));
    $log_file = WC1C_DATA_DIR . "logs/wc1c-$date.log";

    if (!file_exists($log_file)) {
        wp_send_json_error(__('Log file not found', 'woocommerce-1c-integration'));
    }

    $content = file_get_contents($log_file);
    wp_send_json_success(array('content' => $content));
}
add_action('wp_ajax_wc1c_get_logs', 'wc1c_ajax_get_logs');

/**
 * Handle AJAX requests for clearing logs
 */
function wc1c_ajax_clear_logs()
{
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('Insufficient permissions', 'woocommerce-1c-integration'));
    }

    check_ajax_referer('wc1c_logs', 'nonce');

    $logs_dir = WC1C_DATA_DIR . 'logs';
    $files = glob($logs_dir . '/wc1c-*.log*');

    foreach ($files as $file) {
        unlink($file);
    }

    wc1c_log('Log files cleared by admin', 'INFO');
    wp_send_json_success(__('Log files cleared', 'woocommerce-1c-integration'));
}
add_action('wp_ajax_wc1c_clear_logs', 'wc1c_ajax_clear_logs');

/**
 * Add dashboard widget
 */
function wc1c_dashboard_widget()
{
    if (!current_user_can('manage_woocommerce')) return;

    wp_add_dashboard_widget(
        'wc1c_dashboard_widget',
        __('WooCommerce 1C Exchange', 'woocommerce-1c-integration'),
        'wc1c_dashboard_widget_content'
    );
}
add_action('wp_dashboard_setup', 'wc1c_dashboard_widget');

/**
 * Dashboard widget content
 */
function wc1c_dashboard_widget_content()
{
    $log_file = WC1C_DATA_DIR . 'logs/wc1c-' . date('Y-m-d') . '.log';
    $last_exchange = get_option('wc1c_last_exchange', 0);

    echo '<div class="wc1c-dashboard-widget">';

    if ($last_exchange) {
        printf(
            '<p><strong>%s:</strong> %s</p>',
            __('Last Exchange', 'woocommerce-1c-integration'),
            date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_exchange)
        );
    } else {
        printf('<p>%s</p>', __('No exchanges yet', 'woocommerce-1c-integration'));
    }

    if (file_exists($log_file)) {
        $log_size = filesize($log_file);
        printf(
            '<p><strong>%s:</strong> %s</p>',
            __('Today\'s Log Size', 'woocommerce-1c-integration'),
            size_format($log_size)
        );
    }

    printf(
        '<p><a href="%s" class="button">%s</a></p>',
        admin_url('admin.php?page=wc1c-settings'),
        __('View Settings', 'woocommerce-1c-integration')
    );

    echo '</div>';
}

/**
 * Add custom CSS for admin
 */
function wc1c_admin_styles()
{
    $screen = get_current_screen();
    if ($screen->id !== 'woocommerce_page_wc1c-settings') return;

?>
    <style>
        .wc1c-dashboard-widget {
            font-size: 13px;
        }

        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            margin: 20px 0;
            padding: 20px;
            box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
        }

        .card h2 {
            margin-top: 0;
            font-size: 18px;
            font-weight: 600;
        }

        .card code {
            background: #f1f1f1;
            padding: 8px 12px;
            border-radius: 3px;
            display: inline-block;
            margin: 5px 0;
            font-family: Consolas, Monaco, monospace;
        }

        .card pre {
            background: #f8f8f8;
            border: 1px solid #ddd;
            border-radius: 3px;
            padding: 15px;
            overflow-x: auto;
        }

        .card pre code {
            background: none;
            padding: 0;
            border-radius: 0;
            display: block;
        }
    </style>
<?php
}
add_action('admin_head', 'wc1c_admin_styles');

/**
 * Update last exchange timestamp
 */
function wc1c_update_last_exchange()
{
    update_option('wc1c_last_exchange', time());
}

/**
 * Get plugin info
 */
function wc1c_get_plugin_info()
{
    return array(
        'version' => WC1C_VERSION,
        'data_dir' => WC1C_DATA_DIR,
        'logging_enabled' => WC1C_ENABLE_LOGGING,
        'php_version' => PHP_VERSION,
        'wp_version' => get_bloginfo('version'),
        'wc_version' => defined('WC_VERSION') ? WC_VERSION : 'Not installed',
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'post_max_size' => ini_get('post_max_size'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
    );
}

/**
 * Rate limiting functionality
 */
function wc1c_check_rate_limit($ip = null)
{
    if (!WC1C_RATE_LIMIT) return true;

    $ip = $ip ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $cache_key = "wc1c_rate_limit_$ip";
    $requests = wp_cache_get($cache_key);

    if ($requests === false) {
        wp_cache_set($cache_key, 1, '', 3600); // 1 hour
        return true;
    }

    if ($requests >= WC1C_RATE_LIMIT) {
        wc1c_log("Rate limit exceeded for IP: $ip", 'SECURITY');
        return false;
    }

    wp_cache_set($cache_key, $requests + 1, '', 3600);
    return true;
}

/**
 * Security headers
 */
function wc1c_add_security_headers()
{
    if (!is_admin() && get_query_var('wc1c')) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}
add_action('send_headers', 'wc1c_add_security_headers');

/**
 * Handle plugin updates
 */
function wc1c_handle_plugin_update()
{
    $current_version = get_option('wc1c_version', '0.0.0');

    if (version_compare($current_version, WC1C_VERSION, '<')) {
        wc1c_upgrade($current_version);
        update_option('wc1c_version', WC1C_VERSION);
        wc1c_log("Plugin updated from $current_version to " . WC1C_VERSION, 'INFO');
    }
}
add_action('admin_init', 'wc1c_handle_plugin_update');

/**
 * REST API endpoints
 */
function wc1c_register_rest_routes()
{
    register_rest_route('wc1c/v1', '/status', array(
        'methods' => 'GET',
        'callback' => 'wc1c_rest_get_status',
        'permission_callback' => function () {
            return current_user_can('manage_woocommerce');
        }
    ));

    register_rest_route('wc1c/v1', '/logs', array(
        'methods' => 'GET',
        'callback' => 'wc1c_rest_get_logs',
        'permission_callback' => function () {
            return current_user_can('manage_woocommerce');
        }
    ));
}
add_action('rest_api_init', 'wc1c_register_rest_routes');

/**
 * REST API: Get plugin status
 */
function wc1c_rest_get_status($request)
{
    return rest_ensure_response(wc1c_get_plugin_info());
}

/**
 * REST API: Get logs
 */
function wc1c_rest_get_logs($request)
{
    $date = $request->get_param('date') ?: date('Y-m-d');
    $log_file = WC1C_DATA_DIR . "logs/wc1c-$date.log";

    if (!file_exists($log_file)) {
        return new WP_Error('no_logs', 'No logs found for this date', array('status' => 404));
    }

    $content = file_get_contents($log_file);
    return rest_ensure_response(array(
        'date' => $date,
        'content' => $content,
        'size' => filesize($log_file)
    ));
}

/**
 * WP-CLI commands
 */
if (defined('WP_CLI') && WP_CLI) {
    // WP-CLI commands would be loaded here if the class exists
    $cli_file = WC1C_PLUGIN_DIR . 'includes/class-wc1c-cli.php';
    if (file_exists($cli_file)) {
        require_once $cli_file;
    }
}

/**
 * Add custom capabilities
 */
function wc1c_add_capabilities()
{
    $role = get_role('shop_manager');
    if ($role) {
        $role->add_cap('manage_wc1c');
    }

    $role = get_role('administrator');
    if ($role) {
        $role->add_cap('manage_wc1c');
    }
}
add_action('admin_init', 'wc1c_add_capabilities');

/**
 * Remove custom capabilities on deactivation
 */
function wc1c_remove_capabilities()
{
    $roles = array('shop_manager', 'administrator');
    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            $role->remove_cap('manage_wc1c');
        }
    }
}

/**
 * Performance monitoring
 */
function wc1c_monitor_performance()
{
    if (!wc1c_is_debug()) return;

    $memory_usage = memory_get_usage(true);
    $memory_peak = memory_get_peak_usage(true);
    $execution_time = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];

    wc1c_log("Performance: Memory: " . size_format($memory_usage) .
        " Peak: " . size_format($memory_peak) .
        " Time: " . round($execution_time, 3) . "s", 'DEBUG');
}
add_action('shutdown', 'wc1c_monitor_performance');

/**
 * Debug function for development
 */
function wc1c_debug($data, $label = '')
{
    if (!wc1c_is_debug()) return;

    $debug_data = array(
        'label' => $label,
        'data' => $data,
        'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
    );

    wc1c_log('DEBUG: ' . ($label ?: 'Debug data'), 'DEBUG', $debug_data);
}

/**
 * Check if we're in debug mode
 */
function wc1c_is_debug()
{
    return defined('WP_DEBUG') && WP_DEBUG && defined('WC1C_DEBUG') && WC1C_DEBUG;
}

/**
 * Uninstall cleanup (only when plugin is deleted)
 */
function wc1c_uninstall()
{
    // This function is called from uninstall.php
    global $wpdb;

    // Remove database indexes
    $index_table_names = array(
        $wpdb->postmeta,
        $wpdb->termmeta,
        $wpdb->usermeta,
    );

    foreach ($index_table_names as $index_table_name) {
        $index_name = 'wc1c_meta_key_meta_value';
        $result = $wpdb->get_var($wpdb->prepare(
            "SHOW INDEX FROM `%s` WHERE Key_name = %s",
            $index_table_name,
            $index_name
        ));

        if ($result) {
            $wpdb->query($wpdb->prepare(
                "DROP INDEX `%s` ON `%s`",
                $index_name,
                $index_table_name
            ));
        }
    }

    // Remove plugin options
    $options = array(
        'wc1c_activated',
        'wc1c_version',
        'wc1c_last_exchange',
        'wc1c_guid_attributes',
        'wc1c_timestamp_attributes',
        'wc1c_order_attributes',
        'wc1c_currency'
    );

    foreach ($options as $option) {
        delete_option($option);
    }

    // Clear scheduled events
    wp_clear_scheduled_hook('wc1c_cleanup_logs');

    // Optionally remove data directory (uncomment if desired)
    // wc1c_remove_data_directory();
}

/**
 * Remove data directory and all contents
 */
function wc1c_remove_data_directory()
{
    if (!is_dir(WC1C_DATA_DIR)) return;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(WC1C_DATA_DIR, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $path => $item) {
        $item->isDir() ? rmdir($path) : unlink($path);
    }

    rmdir(WC1C_DATA_DIR);
}

/**
 * Load required files
 */
require_once WC1C_PLUGIN_DIR . "admin.php";
require_once WC1C_PLUGIN_DIR . "exchange.php";

// Load exchange handlers if they exist
$exchange_dir = WC1C_PLUGIN_DIR . 'exchange/';
if (is_dir($exchange_dir)) {
    $exchange_files = array('import.php', 'offers.php', 'orders.php', 'query.php', 'success.php');

    foreach ($exchange_files as $file) {
        $file_path = $exchange_dir . $file;
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
}

/**
 * Plugin loaded hook
 */
do_action('wc1c_loaded');

/**
 * Initialize the plugin
 */
if (did_action('plugins_loaded')) {
    wc1c_init();
} else {
    add_action('plugins_loaded', 'wc1c_init');
}
