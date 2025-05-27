/**
 * Admin JavaScript for WooCommerce 1C Integration
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize admin functionality
        WC1C_Admin.init();
    });

    var WC1C_Admin = {
        
        init: function() {
            this.bindEvents();
            this.initLogViewer();
            this.initStatusChecker();
            this.initSettingsForm();
        },

        bindEvents: function() {
            // Test connection button
            $(document).on('click', '.wc1c-test-connection', this.testConnection);
            
            // Clear logs button
            $(document).on('click', '.wc1c-clear-logs', this.clearLogs);
            
            // Refresh status button
            $(document).on('click', '.wc1c-refresh-status', this.refreshStatus);
            
            // Export settings button
            $(document).on('click', '.wc1c-export-settings', this.exportSettings);
            
            // Import settings button
            $(document).on('click', '.wc1c-import-settings', this.importSettings);
            
            // Copy URL button
            $(document).on('click', '.wc1c-copy-url', this.copyUrl);
        },

        initLogViewer: function() {
            var $logViewer = $('.wc1c-log-viewer');
            if ($logViewer.length) {
                // Auto-scroll to bottom
                $logViewer.scrollTop($logViewer[0].scrollHeight);
                
                // Auto-refresh logs every 30 seconds
                setInterval(function() {
                    WC1C_Admin.refreshLogs();
                }, 30000);
            }
        },

        initStatusChecker: function() {
            // Check status every 5 minutes
            setInterval(function() {
                WC1C_Admin.checkSystemStatus();
            }, 300000);
        },

        initSettingsForm: function() {
            var $form = $('#wc1c-settings-form');
            if ($form.length) {
                // Auto-save draft every 30 seconds
                setInterval(function() {
                    WC1C_Admin.saveDraft();
                }, 30000);
                
                // Warn about unsaved changes
                $form.on('change', 'input, select, textarea', function() {
                    WC1C_Admin.markUnsaved();
                });
            }
        },

        testConnection: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            $button.prop('disabled', true).text('Testing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wc1c_test_connection',
                    nonce: wc1c_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WC1C_Admin.showNotice('Connection test successful!', 'success');
                    } else {
                        WC1C_Admin.showNotice('Connection test failed: ' + response.data, 'error');
                    }
                },
                error: function() {
                    WC1C_Admin.showNotice('Connection test failed: Network error', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        clearLogs: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to clear all logs?')) {
                return;
            }
            
            var $button = $(this);
            var originalText = $button.text();
            
            $button.prop('disabled', true).text('Clearing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wc1c_clear_logs',
                    nonce: wc1c_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.wc1c-log-viewer').empty();
                        WC1C_Admin.showNotice('Logs cleared successfully!', 'success');
                    } else {
                        WC1C_Admin.showNotice('Failed to clear logs: ' + response.data, 'error');
                    }
                },
                error: function() {
                    WC1C_Admin.showNotice('Failed to clear logs: Network error', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        refreshStatus: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            $button.prop('disabled', true).text('Refreshing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wc1c_get_system_status',
                    nonce: wc1c_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WC1C_Admin.updateStatusDisplay(response.data);
                        WC1C_Admin.showNotice('Status refreshed!', 'success');
                    } else {
                        WC1C_Admin.showNotice('Failed to refresh status: ' + response.data, 'error');
                    }
                },
                error: function() {
                    WC1C_Admin.showNotice('Failed to refresh status: Network error', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        refreshLogs: function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wc1c_get_recent_logs',
                    nonce: wc1c_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var $logViewer = $('.wc1c-log-viewer');
                        $logViewer.html(response.data.logs);
                        $logViewer.scrollTop($logViewer[0].scrollHeight);
                    }
                }
            });
        },

        checkSystemStatus: function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wc1c_check_system_health',
                    nonce: wc1c_admin.nonce
                },
                success: function(response) {
                    if (response.success && response.data.alerts.length > 0) {
                        response.data.alerts.forEach(function(alert) {
                            WC1C_Admin.showNotice(alert.message, alert.type);
                        });
                    }
                }
            });
        },

        copyUrl: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var url = $button.data('url');
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function() {
                    WC1C_Admin.showNotice('URL copied to clipboard!', 'success');
                });
            } else {
                // Fallback for older browsers
                var $temp = $('<input>');
                $('body').append($temp);
                $temp.val(url).select();
                document.execCommand('copy');
                $temp.remove();
                WC1C_Admin.showNotice('URL copied to clipboard!', 'success');
            }
        },

        exportSettings: function(e) {
            e.preventDefault();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wc1c_export_settings',
                    nonce: wc1c_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var blob = new Blob([JSON.stringify(response.data, null, 2)], {
                            type: 'application/json'
                        });
                        var url = window.URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url;
                        a.download = 'wc1c-settings-' + new Date().toISOString().split('T')[0] + '.json';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);
                        
                        WC1C_Admin.showNotice('Settings exported successfully!', 'success');
                    } else {
                        WC1C_Admin.showNotice('Failed to export settings: ' + response.data, 'error');
                    }
                }
            });
        },

        importSettings: function(e) {
            e.preventDefault();
            
            var $input = $('<input type="file" accept=".json">');
            $input.on('change', function(e) {
                var file = e.target.files[0];
                if (file) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        try {
                            var settings = JSON.parse(e.target.result);
                            WC1C_Admin.doImportSettings(settings);
                        } catch (error) {
                            WC1C_Admin.showNotice('Invalid settings file format', 'error');
                        }
                    };
                    reader.readAsText(file);
                }
            });
            $input.click();
        },

        doImportSettings: function(settings) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wc1c_import_settings',
                    settings: JSON.stringify(settings),
                    nonce: wc1c_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WC1C_Admin.showNotice('Settings imported successfully! Page will reload.', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        WC1C_Admin.showNotice('Failed to import settings: ' + response.data, 'error');
                    }
                }
            });
        },

        saveDraft: function() {
            var $form = $('#wc1c-settings-form');
            if ($form.length && $form.hasClass('unsaved')) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: $form.serialize() + '&action=wc1c_save_draft&nonce=' + wc1c_admin.nonce,
                    success: function(response) {
                        if (response.success) {
                            $form.removeClass('unsaved');
                            $('.wc1c-draft-saved').show().delay(2000).fadeOut();
                        }
                    }
                });
            }
        },

        markUnsaved: function() {
            $('#wc1c-settings-form').addClass('unsaved');
            $('.wc1c-draft-saved').hide();
        },

        updateStatusDisplay: function(status) {
            $.each(status, function(key, value) {
                var $element = $('.wc1c-status-' + key);
                if ($element.length) {
                    $element.text(value);
                }
            });
        },

        showNotice: function(message, type) {
            type = type || 'info';
            
            var $notice = $('<div class="wc1c-notice notice-' + type + '">' + message + '</div>');
            $('.wc1c-admin-page').prepend($notice);
            
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $notice.remove();
                });
            }, 5000);
        }
    };

})(jQuery);