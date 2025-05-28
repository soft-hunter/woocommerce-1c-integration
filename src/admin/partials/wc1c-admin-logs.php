<?php
/**
 * Logs admin page view
 *
 * @package WooCommerce_1C_Integration
 * @subpackage WooCommerce_1C_Integration/admin/partials
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}
?>

<div class="wrap wc1c-admin wc1c-logs-page">
    <h1><?php _e('WooCommerce 1C Integration Logs', 'woocommerce-1c-integration'); ?></h1>
    
    <div class="wc1c-admin-notice">
        <p><?php _e('View and manage logs of the 1C integration operations.', 'woocommerce-1c-integration'); ?></p>
    </div>
    
    <div class="wc1c-logs-filter">
        <h2><?php _e('Filter Logs', 'woocommerce-1c-integration'); ?></h2>
        
        <div class="wc1c-filter-form">
            <div class="wc1c-filter-row">
                <div class="wc1c-filter-field">
                    <label for="wc1c-log-level"><?php _e('Log Level', 'woocommerce-1c-integration'); ?></label>
                    <select id="wc1c-log-level">
                        <option value=""><?php _e('All Levels', 'woocommerce-1c-integration'); ?></option>
                        <option value="debug"><?php _e('Debug', 'woocommerce-1c-integration'); ?></option>
                        <option value="info"><?php _e('Info', 'woocommerce-1c-integration'); ?></option>
                        <option value="warning"><?php _e('Warning', 'woocommerce-1c-integration'); ?></option>
                        <option value="error"><?php _e('Error', 'woocommerce-1c-integration'); ?></option>
                    </select>
                </div>
                
                <div class="wc1c-filter-field">
                    <label for="wc1c-date-from"><?php _e('From Date', 'woocommerce-1c-integration'); ?></label>
                    <input type="date" id="wc1c-date-from" />
                </div>
                
                <div class="wc1c-filter-field">
                    <label for="wc1c-date-to"><?php _e('To Date', 'woocommerce-1c-integration'); ?></label>
                    <input type="date" id="wc1c-date-to" />
                </div>
                
                <div class="wc1c-filter-field">
                    <label for="wc1c-search"><?php _e('Search', 'woocommerce-1c-integration'); ?></label>
                    <input type="text" id="wc1c-search" placeholder="<?php esc_attr_e('Search logs...', 'woocommerce-1c-integration'); ?>" />
                </div>
                
                <div class="wc1c-filter-actions">
                    <button type="button" id="wc1c-filter-logs" class="button"><?php _e('Filter', 'woocommerce-1c-integration'); ?></button>
                    <button type="button" id="wc1c-reset-filter" class="button"><?php _e('Reset', 'woocommerce-1c-integration'); ?></button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="wc1c-logs-actions">
        <div class="wc1c-logs-controls">
            <select id="wc1c-per-page">
                <option value="20">20</option>
                <option value="50" selected>50</option>
                <option value="100">100</option>
                <option value="200">200</option>
            </select>
            <label for="wc1c-per-page"><?php _e('logs per page', 'woocommerce-1c-integration'); ?></label>
            
            <button type="button" id="wc1c-clear-logs" class="button button-secondary"><?php _e('Clear Logs', 'woocommerce-1c-integration'); ?></button>
        </div>
    </div>
    
    <div class="wc1c-logs-content">
        <div id="wc1c-logs-loading" class="wc1c-loading">
            <span class="spinner is-active"></span>
            <p><?php _e('Loading logs...', 'woocommerce-1c-integration'); ?></p>
        </div>
        
        <div id="wc1c-logs-table-container">
            <table id="wc1c-logs-table" class="widefat striped">
                <thead>
                    <tr>
                        <th><?php _e('Time', 'woocommerce-1c-integration'); ?></th>
                        <th><?php _e('Level', 'woocommerce-1c-integration'); ?></th>
                        <th><?php _e('Message', 'woocommerce-1c-integration'); ?></th>
                        <th><?php _e('Context', 'woocommerce-1c-integration'); ?></th>
                    </tr>
                </thead>
                <tbody id="wc1c-logs-rows">
                    <!-- Log entries will be inserted here -->
                </tbody>
            </table>
            
            <div id="wc1c-logs-empty" class="wc1c-empty-state" style="display: none;">
                <p><?php _e('No logs found.', 'woocommerce-1c-integration'); ?></p>
            </div>
        </div>
        
        <div id="wc1c-logs-pagination" class="wc1c-pagination">
            <div class="wc1c-pagination-info">
                <?php _e('Page', 'woocommerce-1c-integration'); ?> <span id="wc1c-current-page">1</span> 
                <?php _e('of', 'woocommerce-1c-integration'); ?> <span id="wc1c-total-pages">1</span>
                (<span id="wc1c-total-entries">0</span> <?php _e('entries', 'woocommerce-1c-integration'); ?>)
            </div>
            <div class="wc1c-pagination-controls">
                <button type="button" id="wc1c-first-page" class="button button-secondary" disabled>
                    &laquo; <?php _e('First', 'woocommerce-1c-integration'); ?>
                </button>
                <button type="button" id="wc1c-prev-page" class="button button-secondary" disabled>
                    &lsaquo; <?php _e('Prev', 'woocommerce-1c-integration'); ?>
                </button>
                <button type="button" id="wc1c-next-page" class="button button-secondary" disabled>
                    <?php _e('Next', 'woocommerce-1c-integration'); ?> &rsaquo;
                </button>
                <button type="button" id="wc1c-last-page" class="button button-secondary" disabled>
                    <?php _e('Last', 'woocommerce-1c-integration'); ?> &raquo;
                </button>
            </div>
        </div>
    </div>
    
    <div id="wc1c-log-details-modal" class="wc1c-modal" style="display: none;">
        <div class="wc1c-modal-content">
            <span class="wc1c-modal-close">&times;</span>
            <h3><?php _e('Log Entry Details', 'woocommerce-1c-integration'); ?></h3>
            <div class="wc1c-modal-body">
                <div class="wc1c-log-detail">
                    <strong><?php _e('Time:', 'woocommerce-1c-integration'); ?></strong>
                    <span id="wc1c-modal-time"></span>
                </div>
                <div class="wc1c-log-detail">
                    <strong><?php _e('Level:', 'woocommerce-1c-integration'); ?></strong>
                    <span id="wc1c-modal-level"></span>
                </div>
                <div class="wc1c-log-detail">
                    <strong><?php _e('Message:', 'woocommerce-1c-integration'); ?></strong>
                    <div id="wc1c-modal-message"></div>
                </div>
                <div class="wc1c-log-detail">
                    <strong><?php _e('Context:', 'woocommerce-1c-integration'); ?></strong>
                    <pre id="wc1c-modal-context"></pre>
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Variables
            let currentPage = 1;
            let totalPages = 1;
            let perPage = 50;
            let filterLevel = '';
            let filterSearch = '';
            let filterDateFrom = '';
            let filterDateTo = '';
            
            // Initialize logs
            loadLogs();
            
            // Filter logs button
            $('#wc1c-filter-logs').on('click', function() {
                filterLevel = $('#wc1c-log-level').val();
                filterSearch = $('#wc1c-search').val();
                filterDateFrom = $('#wc1c-date-from').val();
                filterDateTo = $('#wc1c-date-to').val();
                currentPage = 1;
                loadLogs();
            });
            
            // Reset filter button
            $('#wc1c-reset-filter').on('click', function() {
                $('#wc1c-log-level').val('');
                $('#wc1c-search').val('');
                $('#wc1c-date-from').val('');
                $('#wc1c-date-to').val('');
                
                filterLevel = '';
                filterSearch = '';
                filterDateFrom = '';
                filterDateTo = '';
                currentPage = 1;
                loadLogs();
            });
            
            // Per page change
            $('#wc1c-per-page').on('change', function() {
                perPage = $(this).val();
                currentPage = 1;
                loadLogs();
            });
            
            // Clear logs button
            $('#wc1c-clear-logs').on('click', function() {
                if (confirm(wc1c_params.confirm_clear_logs)) {
                    clearLogs();
                }
            });
            
            // Pagination buttons
            $('#wc1c-first-page').on('click', function() {
                currentPage = 1;
                loadLogs();
            });
            
            $('#wc1c-prev-page').on('click', function() {
                if (currentPage > 1) {
                    currentPage--;
                    loadLogs();
                }
            });
            
            $('#wc1c-next-page').on('click', function() {
                if (currentPage < totalPages) {
                    currentPage++;
                    loadLogs();
                }
            });
            
            $('#wc1c-last-page').on('click', function() {
                currentPage = totalPages;
                loadLogs();
            });
            
            // Modal close button
            $('.wc1c-modal-close').on('click', function() {
                $('#wc1c-log-details-modal').hide();
            });
            
            // Close modal when clicking outside of it
            $(window).on('click', function(event) {
                if ($(event.target).is('#wc1c-log-details-modal')) {
                    $('#wc1c-log-details-modal').hide();
                }
            });
            
            // Load logs function
            function loadLogs() {
                $('#wc1c-logs-loading').show();
                $('#wc1c-logs-table-container').hide();
                $('#wc1c-logs-pagination').hide();
                
                $.ajax({
                    url: wc1c_params.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wc1c_get_logs',
                        nonce: wc1c_params.nonce,
                        page: currentPage,
                        per_page: perPage,
                        level: filterLevel,
                        search: filterSearch,
                        date_from: filterDateFrom,
                        date_to: filterDateTo
                    },
                    success: function(response) {
                        $('#wc1c-logs-loading').hide();
                        
                        if (response.success) {
                            const data = response.data;
                            
                            // Update pagination
                            totalPages = data.pages;
                            $('#wc1c-current-page').text(currentPage);
                            $('#wc1c-total-pages').text(totalPages);
                            $('#wc1c-total-entries').text(data.total);
                            
                            // Enable/disable pagination buttons
                            $('#wc1c-first-page, #wc1c-prev-page').prop('disabled', currentPage === 1);
                            $('#wc1c-next-page, #wc1c-last-page').prop('disabled', currentPage === totalPages || totalPages === 0);
                            
                            // Clear table
                            $('#wc1c-logs-rows').empty();
                            
                            // Check if there are entries
                            if (data.entries.length === 0) {
                                $('#wc1c-logs-empty').show();
                                $('#wc1c-logs-table').hide();
                            } else {
                                $('#wc1c-logs-empty').hide();
                                $('#wc1c-logs-table').show();
                                
                                // Add entries to table
                                data.entries.forEach(function(entry) {
                                    const row = $('<tr class="wc1c-log-row" data-entry=\'' + JSON.stringify(entry) + '\'>');
                                    
                                    // Format timestamp
                                    const timestamp = entry.timestamp ? entry.timestamp.replace('T', ' ').substring(0, 19) : '';
                                    
                                    // Format level with color
                                    let levelClass = '';
                                    switch (entry.level) {
                                        case 'error':
                                            levelClass = 'wc1c-log-level-error';
                                            break;
                                        case 'warning':
                                            levelClass = 'wc1c-log-level-warning';
                                            break;
                                        case 'info':
                                            levelClass = 'wc1c-log-level-info';
                                            break;
                                        case 'debug':
                                            levelClass = 'wc1c-log-level-debug';
                                            break;
                                    }
                                    
                                    // Format context
                                    let contextDisplay = '';
                                    if (entry.context) {
                                        try {
                                            if (typeof entry.context === 'string') {
                                                const contextObj = JSON.parse(entry.context);
                                                const keys = Object.keys(contextObj);
                                                if (keys.length > 0) {
                                                    contextDisplay = keys.map(key => key + ': ' + JSON.stringify(contextObj[key]).substring(0, 50)).join(', ');
                                                }
                                            } else if (typeof entry.context === 'object') {
                                                const keys = Object.keys(entry.context);
                                                if (keys.length > 0) {
                                                    contextDisplay = keys.map(key => key + ': ' + JSON.stringify(entry.context[key]).substring(0, 50)).join(', ');
                                                }
                                            }
                                        } catch (e) {
                                            contextDisplay = typeof entry.context === 'string' ? entry.context.substring(0, 50) : '';
                                        }
                                    }
                                    
                                    // Truncate message if too long
                                    const message = entry.message && entry.message.length > 100 
                                        ? entry.message.substring(0, 100) + '...' 
                                        : entry.message;
                                    
                                    row.append($('<td>').text(timestamp));
                                    row.append($('<td>').html('<span class="wc1c-log-level ' + levelClass + '">' + entry.level + '</span>'));
                                    row.append($('<td>').text(message));
                                    row.append($('<td>').text(contextDisplay));
                                    
                                    $('#wc1c-logs-rows').append(row);
                                });
                                
                                // Make rows clickable to show details
                                $('.wc1c-log-row').on('click', function() {
                                    const entry = $(this).data('entry');
                                    showLogDetails(entry);
                                });
                            }
                            
                            $('#wc1c-logs-table-container').show();
                            $('#wc1c-logs-pagination').show();
                        } else {
                            alert('Error loading logs: ' + response.data.message);
                        }
                    },
                    error: function() {
                        $('#wc1c-logs-loading').hide();
                        alert('Error loading logs. Please try again.');
                    }
                });
            }
            
            // Clear logs function
            function clearLogs() {
                $.ajax({
                    url: wc1c_params.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wc1c_clear_logs',
                        nonce: wc1c_params.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            currentPage = 1;
                            loadLogs();
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('Error clearing logs. Please try again.');
                    }
                });
            }
            
            // Show log details function
            function showLogDetails(entry) {
                // Format timestamp
                const timestamp = entry.timestamp ? entry.timestamp.replace('T', ' ').substring(0, 19) : '';
                
                // Set level with color
                let levelClass = '';
                switch (entry.level) {
                    case 'error':
                        levelClass = 'wc1c-log-level-error';
                        break;
                    case 'warning':
                        levelClass = 'wc1c-log-level-warning';
                        break;
                    case 'info':
                        levelClass = 'wc1c-log-level-info';
                        break;
                    case 'debug':
                        levelClass = 'wc1c-log-level-debug';
                        break;
                }
                
                // Format context
                let contextDisplay = '';
                if (entry.context) {
                    try {
                        if (typeof entry.context === 'string') {
                            contextDisplay = JSON.stringify(JSON.parse(entry.context), null, 2);
                        } else if (typeof entry.context === 'object') {
                            contextDisplay = JSON.stringify(entry.context, null, 2);
                        }
                    } catch (e) {
                        contextDisplay = typeof entry.context === 'string' ? entry.context : '';
                    }
                }
                
                // Set modal content
                $('#wc1c-modal-time').text(timestamp);
                $('#wc1c-modal-level').html('<span class="wc1c-log-level ' + levelClass + '">' + entry.level + '</span>');
                $('#wc1c-modal-message').text(entry.message);
                $('#wc1c-modal-context').text(contextDisplay);
                
                // Show modal
                $('#wc1c-log-details-modal').show();
            }
        });
    </script>
</div>