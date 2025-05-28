/**
 * Admin JavaScript for WooCommerce 1C Integration
 *
 * @package WooCommerce_1C_Integration
 */

(function($) {
    'use strict';

    // Document ready
    $(function() {
        // Copy exchange URL to clipboard
        $('#wc1c-copy-url').on('click', function() {
            const url = $(this).prev('code').text();
            copyToClipboard(url);
            
            const button = $(this);
            const originalText = button.text();
            
            button.text(wc1c_params.success_text);
            setTimeout(function() {
                button.text(originalText);
            }, 2000);
        });

        // Test connection button
        $('#wc1c-test-connection').on('click', function() {
            const button = $(this);
            const resultContainer = $('#wc1c-connection-result');
            
            // Store original text
            if (!button.data('original-text')) {
                button.data('original-text', button.text());
            }
            
            // Set button to loading state
            button.prop('disabled', true);
            button.text(wc1c_params.loading_text);
            
            // Clear previous results
            resultContainer.removeClass('success error').empty();
            
            // Make AJAX request
            $.ajax({
                url: wc1c_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc1c_test_connection',
                    nonce: wc1c_params.nonce
                },
                success: function(response) {
                    // Reset button
                    button.prop('disabled', false);
                    button.text(button.data('original-text'));
                    
                    if (response.success) {
                        resultContainer.addClass('success').removeClass('error');
                        resultContainer.html('<p><strong>' + response.data.message + '</strong></p>');
                        
                        if (response.data.response) {
                            resultContainer.append('<p><strong>Response:</strong></p><pre>' + response.data.response + '</pre>');
                        }
                    } else {
                        resultContainer.addClass('error').removeClass('success');
                        resultContainer.html('<p><strong>' + response.data.message + '</strong></p>');
                        
                        if (response.data.response) {
                            resultContainer.append('<p><strong>Response:</strong></p><pre>' + response.data.response + '</pre>');
                        }
                    }
                },
                error: function() {
                    // Reset button
                    button.prop('disabled', false);
                    button.text(button.data('original-text'));
                    
                    // Show error
                    resultContainer.addClass('error').removeClass('success');
                    resultContainer.html('<p><strong>' + wc1c_params.error_text + '</strong></p><p>Request failed. Please check your server logs.</p>');
                }
            });
        });

        // Generate password button
        $('#wc1c-generate-password').on('click', function() {
            const passwordField = $('input[name="wc1c_auth_password"]');
            const password = generatePassword(16);
            passwordField.val(password);
        });

        // Initialize multiselect fields
        if ($.fn.select2) {
            $('.wc1c-multiselect').select2({
                width: '400px'
            });
        }

        // Tool buttons in tools page
        $('.wc1c-tool-button').on('click', function() {
            const button = $(this);
            const action = button.data('action');
            const confirmMsg = button.data('confirm');
            
            if (confirmMsg && !confirm(confirmMsg)) {
                return;
            }
            
            // Store original text
            if (!button.data('original-text')) {
                button.data('original-text', button.text());
            }
            
            // Set button to loading state
            button.prop('disabled', true);
            button.text(wc1c_params.loading_text);
            
            // Make AJAX request
            $.ajax({
                url: wc1c_params.ajax_url,
                type: 'POST',
                data: {
                    action: action,
                    nonce: wc1c_params.nonce
                },
                success: function(response) {
                    // Reset button
                    button.prop('disabled', false);
                    button.text(button.data('original-text'));
                    
                    if (response.success) {
                        alert(response.data.message);
                        
                        // Reload page if needed
                        if (response.data.reload) {
                            window.location.reload();
                        }
                    } else {
                        alert(wc1c_params.error_text + ': ' + response.data.message);
                    }
                },
                error: function() {
                    // Reset button
                    button.prop('disabled', false);
                    button.text(button.data('original-text'));
                    
                    // Show error
                    alert(wc1c_params.error_text + ': Request failed. Please check your server logs.');
                }
            });
        });
    });

    /**
     * Copy text to clipboard
     * 
     * @param {string} text Text to copy
     * @returns {boolean} Success
     */
    function copyToClipboard(text) {
        // Create temporary input
        const input = document.createElement('textarea');
        input.value = text;
        document.body.appendChild(input);
        
        // Select and copy
        input.select();
        const result = document.execCommand('copy');
        
        // Remove temporary input
        document.body.removeChild(input);
        
        return result;
    }

    /**
     * Generate random password
     * 
     * @param {number} length Password length
     * @returns {string} Generated password
     */
    function generatePassword(length) {
        const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-+=<>?';
        let password = '';
        
        for (let i = 0; i < length; i++) {
            const randomIndex = Math.floor(Math.random() * charset.length);
            password += charset[randomIndex];
        }
        
        return password;
    }
    
    /**
     * Format date string to YYYY-MM-DD
     * 
     * @param {Date} date Date object
     * @returns {string} Formatted date
     */
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        
        return `${year}-${month}-${day}`;
    }
    
    /**
     * Format date and time string to YYYY-MM-DD HH:MM:SS
     * 
     * @param {Date} date Date object
     * @returns {string} Formatted date and time
     */
    function formatDateTime(date) {
        const formattedDate = formatDate(date);
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        const seconds = String(date.getSeconds()).padStart(2, '0');
        
        return `${formattedDate} ${hours}:${minutes}:${seconds}`;
    }
    
    /**
     * Format bytes to human-readable string
     * 
     * @param {number} bytes Number of bytes
     * @param {number} decimals Number of decimal places
     * @returns {string} Formatted size
     */
    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }
    
    /**
     * Escape HTML
     * 
     * @param {string} unsafe Unsafe string
     * @returns {string} Safe string
     */
    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&")
            .replace(/</g, "<")
            .replace(/>/g, ">")
            .replace(/"/g, """)
            .replace(/'/g, "'");
    }
})(jQuery);