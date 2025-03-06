/**
 * Facebook for WooCommerce API
 *
 * @package FacebookCommerce
 */

/* global jQuery, fb_api_data */

var FacebookWooCommerceAPI = (function($) {
    'use strict';
    
    // API methods will be dynamically created based on fb_api_data
    var api = {
        /**
         * Make an API request.
         *
         * @param {string} endpoint API endpoint.
         * @param {string} method HTTP method.
         * @param {Object} data Request data.
         * @return {Promise} Promise that resolves with the response.
         */
        request: function(endpoint, method, data) {
            var url = fb_api_data.api_url + endpoint;
            
            var options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': fb_api_data.nonce
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
    
    // Dynamically create API methods based on definitions
    if (fb_api_data && fb_api_data.endpoints) {
        Object.keys(fb_api_data.endpoints).forEach(function(methodName) {
            var def = fb_api_data.endpoints[methodName];
            
            api[methodName] = function() {
                var params = {};
                var args = Array.prototype.slice.call(arguments);
                
                // Map arguments to parameters
                if (def.params && def.params.length) {
                    def.params.forEach(function(param, index) {
                        if (args[index] !== undefined) {
                            params[param] = args[index];
                        }
                    });
                }
                
                return api.request(def.endpoint, def.method, params);
            };
        });
    }
    
    // Initialize and expose the API
    $(document).ready(function() {
        // Log available methods for debugging
        if (window.console && window.console.debug) {
            console.debug('Facebook for WooCommerce API initialized with methods:', Object.keys(api));
        }
        
        // Make the API available globally
        window.FacebookWooCommerceAPI = api;
    });
    
    return api;
})(jQuery); 