/**
 * Facebook for WooCommerce Connection API.
 *
 * @package FacebookCommerce
 */

/* global jQuery, ajaxurl, fb_woo_settings */

(function($) {
    'use strict';

    /**
     * Facebook for WooCommerce Connection API.
     *
     * @since 2.3.5
     */
    var FacebookWooCommerceConnectionAPI = {

        /**
         * Initialize the API.
         *
         * @since 2.3.5
         */
        init: function() {
            // Nothing to initialize
        },

        /**
         * Update Facebook settings.
         *
         * @since 2.3.5
         *
         * @param {Object} settings Settings to update.
         * @return {Promise} Promise that resolves with the response.
         */
        updateSettings: function(settings) {
            return this.apiRequest('settings/update', 'POST', settings);
        },

        /**
         * Uninstall Facebook integration.
         *
         * @since 2.3.5
         *
         * @return {Promise} Promise that resolves with the response.
         */
        uninstall: function() {
            return this.apiRequest('settings/uninstall', 'POST');
        },

        /**
         * Get connection extras.
         *
         * @since 2.3.5
         *
         * @return {Promise} Promise that resolves with the response.
         */
        getConnectionExtras: function() {
            return this.apiRequest('connection/extras', 'GET');
        },

        /**
         * Make an API request.
         *
         * @since 2.3.5
         *
         * @param {string} endpoint API endpoint.
         * @param {string} method HTTP method.
         * @param {Object} data Request data.
         * @return {Promise} Promise that resolves with the response.
         */
        apiRequest: function(endpoint, method, data) {
            var url = fb_woo_settings.api_url + endpoint;
            
            var options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': fb_woo_settings.nonce
                }
            };

            if (data) {
                options.body = JSON.stringify(data);
            }

            return fetch(url, options)
                .then(function(response) {
                    return response.json();
                })
                .then(function(response) {
                    if (!response.success) {
                        throw new Error(response.message || 'Unknown error');
                    }
                    return response.data;
                });
        }
    };

    // Initialize the API
    $(document).ready(function() {
        FacebookWooCommerceConnectionAPI.init();

        // Make the API available globally
        window.FacebookWooCommerceConnectionAPI = FacebookWooCommerceConnectionAPI;
    });

})(jQuery); 