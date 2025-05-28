<?php
/**
 * Fired during plugin activation
 *
 * @package WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/includes
 * @author Igor Melnyk <igormelnykit@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 */
class WC1C_Activator {

    /**
     * Activate the plugin
     *
     * @since 2.0.0
     */
    public static function activate() {
        // Log activation
        error_log('WC1C: Plugin activation started');

        // Create database tables
        self::create_database_tables();

        // Create database indexes
        self::create_database_indexes();

        // Create directories
        self::create_directories();

        // Set default options
        self::set_default_options();

        // Add rewrite rules
        self::add_rewrite_rules();

        // Schedule cron jobs
        self::schedule_cron_jobs();

        // Set activation flag
        update_option('wc1c_activated', true);
        update_option('wc1c_version', WC1C_VERSION);
        update_option('wc1c_activation_date', current_time('mysql'));

        // Flush rewrite rules
        flush_rewrite_rules();

        error_log('WC1C: Plugin activation completed');
    }

    /**
     * Create custom database tables
     *
     * @since 2.0.0
     */
    private static function create_database_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Exchange logs table
        $table_name = $wpdb->prefix . 'wc1c_exchange_logs';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            exchange_type varchar(50) NOT NULL,
            operation varchar(100) NOT NULL,
            status varchar(20) NOT NULL,
            message text,
            data longtext,
            execution_time float,
            memory_usage bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY exchange_type (exchange_type),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Sync queue table
        $table_name = $wpdb->prefix . 'wc1c_sync_queue';
        $sql .= "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            item_type varchar(50) NOT NULL,
            item_id bigint(20) NOT NULL,
            action varchar(50) NOT NULL,
            priority int(11) DEFAULT 10,
            status varchar(20) DEFAULT 'pending',
            attempts int(11) DEFAULT 0,
            max_attempts int(11) DEFAULT 3,
            data longtext,
            scheduled_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY item_type_id (item_type, item_id),
            KEY status (status),
            KEY scheduled_at (scheduled_at),
            KEY priority (priority)
        ) $charset_collate;";

        // Mapping table for 1C GUIDs
        $table_name = $wpdb->prefix . 'wc1c_guid_mapping';
        $sql .= "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            wc_id bigint(20) NOT NULL,
            wc_type varchar(50) NOT NULL,
            guid varchar(255) NOT NULL,
            parent_guid varchar(255) NULL,
            last_sync datetime DEFAULT CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY wc_id_type (wc_id, wc_type),
            UNIQUE KEY guid (guid),
            KEY parent_guid (parent_guid),
            KEY last_sync (last_sync)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        error_log('WC1C: Database tables created');
    }

    /**
     * Create database indexes for performance
     *
     * @since 2.0.0
     */
    private static function create_database_indexes() {
        global $wpdb;

        $index_table_names = array(
            $wpdb->postmeta,
            $wpdb->termmeta,
            $wpdb->usermeta,
        );

        foreach ($index_table_names as $table_name) {
            $index_name = 'wc1c_meta_key_meta_value';
            
            // Check if index exists
            $index_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW INDEX FROM `{$table_name}` WHERE Key_name = %s",
                $index_name
            ));

            if (!$index_exists) {
                $wpdb->query("ALTER TABLE `{$table_name}` ADD INDEX `{$index_name}` (meta_key, meta_value(36))");
                
                if ($wpdb->last_error) {
                    error_log("WC1C: Failed to create index on {$table_name}: " . $wpdb->last_error);
                } else {
                    error_log("WC1C: Created index on {$table_name}");
                }
            }
        }
    }

    /**
     * Create required directories
     *
     * @since 2.0.0
     */
    private static function create_directories() {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/woocommerce-1c-integration';

        $directories = array(
            $base_dir,
            $base_dir . '/catalog',
            $base_dir . '/sale',
            $base_dir . '/logs',
            $base_dir . '/temp',
            $base_dir . '/backup'
        );

        foreach ($directories as $dir) {
            if (!wp_mkdir_p($dir)) {
                error_log("WC1C: Failed to create directory: {$dir}");
                continue;
            }

            // Set proper permissions
            chmod($dir, 0755);

            // Add security files
            file_put_contents($dir . '/index.html', '');

            // Add .htaccess for data directories
            if (!in_array(basename($dir), array('logs', 'temp', 'backup'))) {
                $htaccess_content = "Deny from all\n<Files \"*.xml\">\n Allow from all\n</Files>";
                file_put_contents($dir . '/.htaccess', $htaccess_content);
            } else {
                // Logs and temp directories should be completely protected
                file_put_contents($dir . '/.htaccess', 'Deny from all');
            }

            error_log("WC1C: Created directory: {$dir}");
        }
    }

    /**
     * Set default plugin options
     *
     * @since 2.0.0
     */
    private static function set_default_options() {
        $default_options = array(
            'wc1c_enable_logging' => 'yes',
            'wc1c_log_level' => 'info',
            'wc1c_max_execution_time' => 300,
            'wc1c_memory_limit' => '512M',
            'wc1c_file_limit' => '100M',
            'wc1c_cleanup_garbage' => 'yes',
            'wc1c_manage_stock' => 'yes',
            'wc1c_outofstock_status' => 'outofstock',
            'wc1c_xml_charset' => 'UTF-8',
            'wc1c_disable_variations' => 'no',
            'wc1c_prevent_clean' => 'no',
            'wc1c_match_by_sku' => 'no',
            'wc1c_match_categories_by_title' => 'no',
            'wc1c_match_properties_by_title' => 'no',
            'wc1c_sync_interval' => 'hourly',
            'wc1c_auto_sync' => 'no',
            'wc1c_api_timeout' => 30,
            'wc1c_retry_attempts' => 3,
            'wc1c_batch_size' => 100
        );

        foreach ($default_options as $option_name => $default_value) {
            if (get_option($option_name) === false) {
                add_option($option_name, $default_value);
            }
        }

        error_log('WC1C: Default options set');
    }

    /**
     * Add rewrite rules
     *
     * @since 2.0.0
     */
    private static function add_rewrite_rules() {
        add_rewrite_rule(
            '^wc1c/exchange/?$',
            'index.php?wc1c_action=exchange',
            'top'
        );
        
        add_rewrite_rule(
            '^wc1c/api/([^/]+)/?$',
            'index.php?wc1c_action=api&wc1c_endpoint=$matches[1]',
            'top'
        );

        // Add query vars
        global $wp;
        $wp->add_query_var('wc1c_action');
        $wp->add_query_var('wc1c_endpoint');

        error_log('WC1C: Rewrite rules added');
    }

    /**
     * Schedule cron jobs
     *
     * @since 2.0.0
     */
    private static function schedule_cron_jobs() {
        // Schedule log cleanup
        if (!wp_next_scheduled('wc1c_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'wc1c_cleanup_logs');
        }

        // Schedule sync queue processing
        if (!wp_next_scheduled('wc1c_process_sync_queue')) {
            wp_schedule_event(time(), 'wc1c_every_5_minutes', 'wc1c_process_sync_queue');
        }

        // Schedule automatic sync (if enabled)
        $sync_interval = get_option('wc1c_sync_interval', 'hourly');
        if (get_option('wc1c_auto_sync') === 'yes' && !wp_next_scheduled('wc1c_auto_sync')) {
            wp_schedule_event(time(), $sync_interval, 'wc1c_auto_sync');
        }

        error_log('WC1C: Cron jobs scheduled');
    }

    /**
     * Add custom cron intervals
     *
     * @since 2.0.0
     */
    public static function add_cron_intervals($schedules) {
        $schedules['wc1c_every_5_minutes'] = array(
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'woocommerce-1c-integration')
        );

        $schedules['wc1c_every_15_minutes'] = array(
            'interval' => 900,
            'display' => __('Every 15 Minutes', 'woocommerce-1c-integration')
        );

        return $schedules;
    }
}

// Add custom cron intervals
add_filter('cron_schedules', array('WC1C_Activator', 'add_cron_intervals'));