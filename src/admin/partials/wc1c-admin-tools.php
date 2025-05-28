<?php
/**
 * Provide a admin area view for the plugin tools
 *
 * @package    WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/admin/partials
 * @author     Igor Melnyk <igormelnykit@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

// Handle export settings via GET request
if (isset($_GET['action']) && $_GET['action'] === 'export_settings' && wp_verify_nonce($_GET['_wpnonce'], 'wc1c_export_settings')) {
    $admin = new WC1C_Admin('woocommerce-1c-integration', WC1C_VERSION);
    $admin->handle_export_settings();
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="wc1c-tools">
        
        <!-- Database Tools -->
        <div class="wc1c-tool-section">
            <h2><?php _e('Database Tools', 'woocommerce-1c-integration'); ?></h2>
            
            <div class="wc1c-tool">
                <h3><?php _e('Reset Plugin Data', 'woocommerce-1c-integration'); ?></h3>
                <p><?php _e('This will remove all plugin data including settings, logs, and sync history.', 'woocommerce-1c-integration'); ?></p>
                <form method="post">
                    <?php wp_nonce_field('wc1c_reset_data', 'wc1c_reset_data_nonce'); ?>
                    <input type="hidden" name="action" value="reset_data" />
                    <button type="submit" class="button button-secondary" onclick="return confirm('<?php _e('Are you sure? This action cannot be undone!', 'woocommerce-1c-integration'); ?>')">
                        <?php _e('Reset All Data', 'woocommerce-1c-integration'); ?>
                    </button>
                </form>
            </div>
            
            <div class="wc1c-tool">
                <h3><?php _e('Cleanup Orphaned Data', 'woocommerce-1c-integration'); ?></h3>
                <p><?php _e('Remove orphaned metadata and temporary files.', 'woocommerce-1c-integration'); ?></p>
                <form method="post">
                    <?php wp_nonce_field('wc1c_cleanup_data', 'wc1c_cleanup_data_nonce'); ?>
                    <input type="hidden" name="action" value="cleanup_data" />
                    <button type="submit" class="button">
                        <?php _e('Cleanup Data', 'woocommerce-1c-integration'); ?>
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Import/Export Tools -->
        <div class="wc1c-tool-section">
            <h2><?php _e('Import/Export Tools', 'woocommerce-1c-integration'); ?></h2>
            
            <div class="wc1c-tool">
                <h3><?php _e('Export Settings', 'woocommerce-1c-integration'); ?></h3>
                <p><?php _e('Export plugin settings to a JSON file.', 'woocommerce-1c-integration'); ?></p>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wc1c-tools&action=export_settings'), 'wc1c_export_settings'); ?>" class="button">
                    <?php _e('Export Settings', 'woocommerce-1c-integration'); ?>
                </a>
            </div>
            
            <div class="wc1c-tool">
                <h3><?php _e('Import Settings', 'woocommerce-1c-integration'); ?></h3>
                <p><?php _e('Import plugin settings from a JSON file.', 'woocommerce-1c-integration'); ?></p>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('wc1c_import_settings', 'wc1c_import_settings_nonce'); ?>
                    <input type="hidden" name="action" value="import_settings" />
                    <input type="file" name="settings_file" accept=".json" required />
                    <button type="submit" class="button">
                        <?php _e('Import Settings', 'woocommerce-1c-integration'); ?>
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Diagnostic Tools -->
        <div class="wc1c-tool-section">
            <h2><?php _e('Diagnostic Tools', 'woocommerce-1c-integration'); ?></h2>
            
            <div class="wc1c-tool">
                <h3><?php _e('System Information', 'woocommerce-1c-integration'); ?></h3>
                <p><?php _e('View detailed system information for troubleshooting.', 'woocommerce-1c-integration'); ?></p>
                <button type="button" class="button" onclick="document.getElementById('system-info').style.display = document.getElementById('system-info').style.display === 'none' ? 'block' : 'none';">
                    <?php _e('Show System Info', 'woocommerce-1c-integration'); ?>
                </button>
                
                <div id="system-info" style="display: none; margin-top: 15px;">
                    <?php
                    $admin = new WC1C_Admin('woocommerce-1c-integration', WC1C_VERSION);
                    $admin->display_system_status();
                    ?>
                </div>
            </div>
            
            <div class="wc1c-tool">
                <h3><?php _e('Clear Cache', 'woocommerce-1c-integration'); ?></h3>
                <p><?php _e('Clear all cached data and temporary files.', 'woocommerce-1c-integration'); ?></p>
                <button type="button" class="button" id="clear-cache-btn">
                    <?php _e('Clear Cache', 'woocommerce-1c-integration'); ?>
                </button>
            </div>
            
            <div class="wc1c-tool">
                <h3><?php _e('Validate Data', 'woocommerce-1c-integration'); ?></h3>
                <p><?php _e('Check for data integrity issues and inconsistencies.', 'woocommerce-1c-integration'); ?></p>
                <button type="button" class="button" id="validate-data-btn">
                    <?php _e('Validate Data', 'woocommerce-1c-integration'); ?>
                </button>
            </div>
        </div>
        
        <!-- Manual Sync Tools -->
        <div class="wc1c-tool-section">
            <h2><?php _e('Manual Sync Tools', 'woocommerce-1c-integration'); ?></h2>
            
            <div class="wc1c-tool">
                <h3><?php _e('Test Connection', 'woocommerce-1c-integration'); ?></h3>
                <p><?php _e('Test the connection to 1C system.', 'woocommerce-1c-integration'); ?></p>
                <button type="button" class="button" id="test-connection-btn">
                    <?php _e('Test Connection', 'woocommerce-1c-integration'); ?>
                </button>
            </div>
            
            <div class="wc1c-tool">
                <h3><?php _e('Manual Synchronization', 'woocommerce-1c-integration'); ?></h3>
                <p><?php _e('Start a manual synchronization process.', 'woocommerce-1c-integration'); ?></p>
                <select id="sync-type">
                    <option value="full"><?php _e('Full Sync', 'woocommerce-1c-integration'); ?></option>
                    <option value="catalog"><?php _e('Catalog Only', 'woocommerce-1c-integration'); ?></option>
                    <option value="offers"><?php _e('Offers Only', 'woocommerce-1c-integration'); ?></option>
                    <option value="orders"><?php _e('Orders Only', 'woocommerce-1c-integration'); ?></option>
                </select>
                <button type="button" class="button button-primary" id="manual-sync-btn">
                    <?php _e('Start Sync', 'woocommerce-1c-integration'); ?>
                </button>
            </div>
        </div>
        
        <!-- Maintenance Tools -->
        <div class="wc1c-tool-section">
            <h2><?php _e('Maintenance Tools', 'woocommerce-1c-integration'); ?></h2>
            
            <div class="wc1c-tool">
                <h3><?php _e('Rebuild Product Index', 'woocommerce-1c-integration'); ?></h3>
                <p><?php _e('Rebuild the product index for faster synchronization.', 'woocommerce-1c-integration'); ?></p>
                <button type="button" class="button" id="rebuild-index-btn">
                    <?php _e('Rebuild Index', 'woocommerce-1c-integration'); ?>
                </button>
            </div>
            
            <div class="wc1c-tool">
                <h3><?php _e('Fix Broken References', 'woocommerce-1c-integration'); ?></h3>
                <p><?php _e('Fix broken product and category references.', 'woocommerce-1c-integration'); ?></p>
                <button type="button" class="button" id="fix-references-btn">
                    <?php _e('Fix References', 'woocommerce-1c-integration'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Results container -->
    <div id="tool-results" style="display: none; margin-top: 20px;">
        <div class="notice notice-info">
            <p id="tool-results-message"></p>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    
    // Clear cache
    $('#clear-cache-btn').on('click', function() {
        var $btn = $(this);
        $btn.addClass('loading').text('<?php _e('Clearing...', 'woocommerce-1c-integration'); ?>');
        
        $.post(ajaxurl, {
            action: 'wc1c_clear_cache',
            nonce: wc1c_admin.nonce
        }, function(response) {
            showResult(response.success ? 'success' : 'error', response.data.message);
        }).always(function() {
            $btn.removeClass('loading').text('<?php _e('Clear Cache', 'woocommerce-1c-integration'); ?>');
        });
    });
    
    // Validate data
    $('#validate-data-btn').on('click', function() {
        var $btn = $(this);
        $btn.addClass('loading').text('<?php _e('Validating...', 'woocommerce-1c-integration'); ?>');
        
        $.post(ajaxurl, {
            action: 'wc1c_validate_data',
            nonce: wc1c_admin.nonce
        }, function(response) {
            showResult(response.success ? 'success' : 'error', response.data.message);
        }).always(function() {
            $btn.removeClass('loading').text('<?php _e('Validate Data', 'woocommerce-1c-integration'); ?>');
        });
    });
    
    // Test connection
    $('#test-connection-btn').on('click', function() {
        var $btn = $(this);
        $btn.addClass('loading').text('<?php _e('Testing...', 'woocommerce-1c-integration'); ?>');
        
        $.post(ajaxurl, {
            action: 'wc1c_test_connection',
            nonce: wc1c_admin.nonce
        }, function(response) {
            showResult(response.success ? 'success' : 'error', response.data.message);
        }).always(function() {
            $btn.removeClass('loading').text('<?php _e('Test Connection', 'woocommerce-1c-integration'); ?>');
        });
    });
    
    // Manual sync
    $('#manual-sync-btn').on('click', function() {
        if (!confirm(wc1c_admin.strings.confirm_sync)) {
            return;
        }
        
        var $btn = $(this);
        var syncType = $('#sync-type').val();
        
        $btn.addClass('loading').text('<?php _e('Syncing...', 'woocommerce-1c-integration'); ?>');
        
        $.post(ajaxurl, {
            action: 'wc1c_manual_sync',
            sync_type: syncType,
            nonce: wc1c_admin.nonce
        }, function(response) {
            showResult(response.success ? 'success' : 'error', response.data.message);
        }).always(function() {
            $btn.removeClass('loading').text('<?php _e('Start Sync', 'woocommerce-1c-integration'); ?>');
        });
    });
    
    // Rebuild index
    $('#rebuild-index-btn').on('click', function() {
        var $btn = $(this);
        $btn.addClass('loading').text('<?php _e('Rebuilding...', 'woocommerce-1c-integration'); ?>');
        
        $.post(ajaxurl, {
            action: 'wc1c_rebuild_index',
            nonce: wc1c_admin.nonce
        }, function(response) {
            showResult(response.success ? 'success' : 'error', response.data.message);
        }).always(function() {
            $btn.removeClass('loading').text('<?php _e('Rebuild Index', 'woocommerce-1c-integration'); ?>');
        });
    });
    
    // Fix references
    $('#fix-references-btn').on('click', function() {
        var $btn = $(this);
        $btn.addClass('loading').text('<?php _e('Fixing...', 'woocommerce-1c-integration'); ?>');
        
        $.post(ajaxurl, {
            action: 'wc1c_fix_references',
            nonce: wc1c_admin.nonce
        }, function(response) {
            showResult(response.success ? 'success' : 'error', response.data.message);
        }).always(function() {
            $btn.removeClass('loading').text('<?php _e('Fix References', 'woocommerce-1c-integration'); ?>');
        });
    });
    
    function showResult(type, message) {
        var $container = $('#tool-results');
        var $notice = $container.find('.notice');
        
        $notice.removeClass('notice-success notice-error notice-info')
               .addClass('notice-' + (type === 'success' ? 'success' : 'error'));
        
        $('#tool-results-message').text(message);
        $container.show();
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $container.fadeOut();
        }, 5000);
        
        // Scroll to results
        $('html, body').animate({
            scrollTop: $container.offset().top - 50
        }, 500);
    }
});
</script>

<style>
.wc1c-tools {
    max-width: 1200px;
}

.wc1c-tool-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-bottom: 20px;
    padding: 20px;
}

.wc1c-tool-section h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.wc1c-tool {
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #f0f0f0;
}

.wc1c-tool:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.wc1c-tool h3 {
    margin-bottom: 8px;
    color: #23282d;
}

.wc1c-tool p {
    margin-bottom: 15px;
    color: #666;
}

.wc1c-status-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.wc1c-status-table td {
    padding: 8px 12px;
    border: 1px solid #ddd;
    vertical-align: top;
}

.wc1c-status-table td:first-child {
    font-weight: 600;
    background-color: #f9f9f9;
    width: 200px;
}

.wc1c-status-yes {
    color: #46b450;
}

.wc1c-status-no {
    color: #dc3232;
}

.wc1c-status-warning {
    color: #ffb900;
}

#sync-type {
    margin-right: 10px;
    vertical-align: top;
}

.button.loading {
    opacity: 0.6;
    pointer-events: none;
}
</style>

<?php
// Handle POST requests for tools
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $admin = new WC1C_Admin('woocommerce-1c-integration', WC1C_VERSION);
    
    switch ($_POST['action']) {
        case 'reset_data':
            if (wp_verify_nonce($_POST['wc1c_reset_data_nonce'], 'wc1c_reset_data')) {
                $admin->handle_reset_data();
            }
            break;
            
        case 'cleanup_data':
            if (wp_verify_nonce($_POST['wc1c_cleanup_data_nonce'], 'wc1c_cleanup_data')) {
                $admin->handle_cleanup_data();
            }
            break;
            
        case 'import_settings':
            if (wp_verify_nonce($_POST['wc1c_import_settings_nonce'], 'wc1c_import_settings')) {
                $admin->handle_import_settings();
            }
            break;
    }
}
?>