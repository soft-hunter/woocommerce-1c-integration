/**
 * Public JavaScript for WooCommerce 1C Integration
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        WC1C_Public.init();
    });

    var WC1C_Public = {
        
        init: function() {
            this.bindEvents();
            this.initSyncStatus();
        },

        bindEvents: function() {
            // Add any public-facing event handlers here
        },

        initSyncStatus: function() {
            // Update sync status indicators
            $('.wc1c-sync-status').each(function() {
                var $status = $(this);
                var lastSync = $status.data('last-sync');
                
                if (lastSync) {
                    var syncDate = new Date(lastSync * 1000);
                    var now = new Date();
                    var diffHours = (now - syncDate) / (1000 * 60 * 60);
                    
                    if (diffHours > 24) {
                        $status.addClass('warning').text('Sync outdated');
                    }
                }
            });
        }
    };

})(jQuery);