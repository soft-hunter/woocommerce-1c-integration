/**
 * Admin JavaScript for WooCommerce 1C Integration (Plain JS Version)
 *
 * @package WooCommerce_1C_Integration
 */

(function() { // Using an IIFE (Immediately Invoked Function Expression) to avoid polluting global scope
    'use strict';

    // Helper function for making fetch requests (similar to $.ajax)
    async function fetchData(url, options) {
        const { method = 'GET', headers = {}, body = null, isFormData = false } = options;
        const fetchOptions = {
            method: method,
            headers: headers,
        };

        if (body) {
            if (isFormData) { // body is already a FormData object
                fetchOptions.body = body;
            } else { // body is an object, convert to URLSearchParams for POST
                fetchOptions.headers['Content-Type'] = fetchOptions.headers['Content-Type'] || 'application/x-www-form-urlencoded;charset=UTF-8';
                const urlSearchParams = new URLSearchParams();
                for (const key in body) {
                    if (Object.hasOwnProperty.call(body, key)) {
                        urlSearchParams.append(key, body[key]);
                    }
                }
                fetchOptions.body = urlSearchParams.toString();
            }
        }

        try {
            const response = await fetch(url, fetchOptions);
            const responseData = await response.json(); // Assuming all responses are JSON

            if (!response.ok) {
                // Construct an error object similar to what jQuery AJAX might produce in error callback
                const error = new Error(responseData.data && responseData.data.message ? responseData.data.message : `HTTP error! Status: ${response.status}`);
                error.response = response;
                error.responseData = responseData;
                throw error;
            }
            return responseData;
        } catch (error) {
            console.error('Fetch error:', error.message, error.responseData || error);
            throw error; // Re-throw to be caught by the caller
        }
    }


    // Document ready equivalent in plain JS
    document.addEventListener('DOMContentLoaded', function() {

        // --- Show/Hide Password ---
        const showPasswordCheckbox = document.getElementById('wc1c_show_password_checkbox');
        const passwordField = document.getElementById('wc1c_auth_password_field');

        if (showPasswordCheckbox && passwordField) {
            showPasswordCheckbox.addEventListener('change', function() {
                passwordField.type = this.checked ? 'text' : 'password';
            });
        }

        // --- Copy exchange URL to clipboard ---
        const copyUrlButton = document.getElementById('wc1c-copy-url');
        if (copyUrlButton) {
            copyUrlButton.addEventListener('click', function() {
                const urlElement = this.previousElementSibling; // Assuming <code> is the direct previous sibling
                if (urlElement && urlElement.tagName === 'CODE') {
                    const url = urlElement.textContent;
                    copyToClipboard(url); // Uses the global helper function

                    const originalText = this.textContent;
                    this.textContent = wc1c_params.success_text || 'Copied!';
                    setTimeout(() => {
                        this.textContent = originalText;
                    }, 2000);
                }
            });
        }

        // --- Test connection button ---
        const testConnectionButton = document.getElementById('wc1c-test-connection');
        const connectionResultContainer = document.getElementById('wc1c-connection-result');

        if (testConnectionButton && connectionResultContainer) {
            const originalTestButtonText = testConnectionButton.textContent;

            testConnectionButton.addEventListener('click', async function() {
                this.disabled = true;
                this.textContent = wc1c_params.loading_text || 'Loading...';
                connectionResultContainer.classList.remove('success', 'error');
                connectionResultContainer.innerHTML = '';

                try {
                    const response = await fetchData(wc1c_params.ajax_url, {
                        method: 'POST',
                        body: {
                            action: 'wc1c_test_connection',
                            nonce: wc1c_params.nonce
                        }
                    });

                    if (response.success) {
                        connectionResultContainer.classList.add('success');
                        connectionResultContainer.innerHTML = `<p><strong>${escapeHtml(response.data.message)}</strong></p>`;
                        if (response.data.response) {
                            connectionResultContainer.innerHTML += `<p><strong>Response:</strong></p><pre>${escapeHtml(response.data.response)}</pre>`;
                        }
                    } else {
                        connectionResultContainer.classList.add('error');
                        connectionResultContainer.innerHTML = `<p><strong>${escapeHtml(response.data.message || 'An error occurred.')}</strong></p>`;
                        if (response.data.response) {
                            connectionResultContainer.innerHTML += `<p><strong>Response:</strong></p><pre>${escapeHtml(response.data.response)}</pre>`;
                        }
                    }
                } catch (error) {
                    connectionResultContainer.classList.add('error');
                    connectionResultContainer.innerHTML = `<p><strong>${wc1c_params.error_text || 'Error'}</strong></p><p>${escapeHtml(error.message || 'Request failed. Please check your server logs and the browser console.')}</p>`;
                } finally {
                    this.disabled = false;
                    this.textContent = originalTestButtonText;
                }
            });
        }

        // --- Generate password button ---
        const generatePasswordButton = document.getElementById('wc1c-generate-password');
        if (generatePasswordButton) {
            generatePasswordButton.addEventListener('click', function() {
                const passwordInputField = document.querySelector('input[name="wc1c_auth_password"]');
                if (passwordInputField) {
                    passwordInputField.value = generatePassword(16); // Uses the global helper function
                }
            });
        }

        // --- Select2 Initialization (Removed for Plain JS version) ---
        // Select2 is a jQuery plugin. If you need this functionality,
        // you'll need to use jQuery or find a plain JS alternative.
        // Example:
        // if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
        //     jQuery('.wc1c-multiselect').select2({
        //         width: '400px'
        //     });
        // } else {
        //     console.warn('Select2 cannot be initialized because jQuery or Select2 is not available.');
        // }


        // --- Tool buttons in tools page ---
        const toolButtons = document.querySelectorAll('.wc1c-tool-button');
        toolButtons.forEach(button => {
            const originalToolButtonText = button.textContent;
            button.addEventListener('click', async function() {
                const action = this.dataset.action;
                const confirmMsg = this.dataset.confirm;

                if (confirmMsg && !confirm(confirmMsg)) {
                    return;
                }

                this.disabled = true;
                this.textContent = wc1c_params.loading_text || 'Loading...';

                try {
                    const response = await fetchData(wc1c_params.ajax_url, {
                        method: 'POST',
                        body: {
                            action: action,
                            nonce: wc1c_params.nonce
                        }
                    });

                    if (response.success) {
                        alert(response.data.message);
                        if (response.data.reload) {
                            window.location.reload();
                        }
                    } else {
                        alert((wc1c_params.error_text || 'Error') + ': ' + (response.data.message || 'An unknown error occurred.'));
                    }
                } catch (error) {
                    alert((wc1c_params.error_text || 'Error') + ': ' + (error.message || 'Request failed. Please check server logs and browser console.'));
                } finally {
                    this.disabled = false;
                    this.textContent = originalToolButtonText;
                }
            });
        });

    }); // End of DOMContentLoaded

    // --- Utility Functions (already plain JS) ---

    /**
     * Copy text to clipboard
     * @param {string} text Text to copy
     */
    function copyToClipboard(text) {
        if (!navigator.clipboard) { // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed'; // Prevent scrolling to bottom of page in MS Edge.
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
            } catch (err) {
                console.error('Fallback: Oops, unable to copy', err);
            }
            document.body.removeChild(textArea);
            return;
        }
        navigator.clipboard.writeText(text).then(function() {
            // console.log('Async: Copying to clipboard was successful!');
        }, function(err) {
            console.error('Async: Could not copy text: ', err);
        });
    }

    /**
     * Generate random password
     * @param {number} length Password length
     * @returns {string} Generated password
     */
    function generatePassword(length = 16) {
        const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-+=<>?';
        let password = '';
        const cryptoObj = window.crypto || window.msCrypto; // for better randomness

        if (cryptoObj && cryptoObj.getRandomValues) {
            const values = new Uint32Array(length);
            cryptoObj.getRandomValues(values);
            for (let i = 0; i < length; i++) {
                password += charset[values[i] % charset.length];
            }
        } else { // Fallback for older browsers
            for (let i = 0; i < length; i++) {
                password += charset.charAt(Math.floor(Math.random() * charset.length));
            }
        }
        return password;
    }

    /**
     * Escape HTML to prevent XSS
     * @param {string} unsafe Unsafe string
     * @returns {string} Safe string
     */
    function escapeHtml(unsafe) {
        if (typeof unsafe !== 'string') {
            if (unsafe === null || typeof unsafe === 'undefined') return '';
            try {
                unsafe = String(unsafe);
            } catch (e) {
                return '';
            }
        }
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;") // Corrected line
            .replace(/'/g, "&#039;"); // Corrected line (using &#039; for single quote)
    }

    /**
     * Format date string to YYYY-MM-DD
     * @param {Date} date Date object
     * @returns {string} Formatted date
     */
    function formatDate(date) {
        if (!(date instanceof Date)) date = new Date(date);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    /**
     * Format date and time string to YYYY-MM-DD HH:MM:SS
     * @param {Date} date Date object
     * @returns {string} Formatted date and time
     */
    function formatDateTime(date) {
        if (!(date instanceof Date)) date = new Date(date);
        const formattedDate = formatDate(date);
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        const seconds = String(date.getSeconds()).padStart(2, '0');
        return `${formattedDate} ${hours}:${minutes}:${seconds}`;
    }

    /**
     * Format bytes to human-readable string
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

})(); // End of IIFE