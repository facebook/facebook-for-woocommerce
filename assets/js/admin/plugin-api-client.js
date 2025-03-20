/**
 * Facebook for WooCommerce API
 *
 * @package FacebookCommerce
 */

/* global jQuery, fb_api_data */

(function ($) {
    'use strict';

    /**
     * Facebook WooCommerce API Client Factory
     * Creates an API client with the provided nonce
     * 
     * NONCE is a required argument for all requests! Each calling page must provide a fresh nonce;
     * this ensures that other scripts on the page cannot make unauthenticated requests to the API.
     * 
     * @param {string} nonce The WordPress REST API nonce
     * @return {Object} API client instance
    */
    window.GeneratePluginAPIClient = function (nonce) {
        // Create a new API client instance
        const apiClient = {
            // Base request method
            request: function (endpoint, method, data) {
                return new Promise(function (resolve, reject) {
                    $.ajax({
                        url: fb_api_data.api_url + endpoint,
                        method: method,
                        data: JSON.stringify(data),
                        contentType: 'application/json',
                        beforeSend: function (xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', nonce);
                        },
                        success: function (response) {
                            resolve(response);
                        },
                        error: function (jqXHR, textStatus, errorThrown) {
                            reject({
                                status: jqXHR.status,
                                message: errorThrown
                            });
                        }
                    });
                });
            },

            // Validation helper
            validateParam: function (value, type) {
                switch (type) {
                    case 'string':
                        return typeof value === 'string';
                    case 'int':
                    case 'integer':
                        return Number.isInteger(value);
                    case 'float':
                    case 'double':
                        return typeof value === 'number';
                    case 'bool':
                    case 'boolean':
                        return typeof value === 'boolean';
                    case 'array':
                        return Array.isArray(value);
                    case 'object':
                        return typeof value === 'object' && value !== null && !Array.isArray(value);
                    default:
                        return true; // Unknown type, assume valid
                }
            }
        };

        // Generate API methods from endpoint definitions
        if (fb_api_data.endpoints && typeof fb_api_data.endpoints === 'object') {
            // Convert object to array if needed
            const endpointsArray = Array.isArray(fb_api_data.endpoints)
                ? fb_api_data.endpoints
                : Object.values(fb_api_data.endpoints);

            endpointsArray.forEach(function (endpoint) {
                // Create method name from class name
                var methodName = endpoint.className.replace('Request', '');
                methodName = methodName.charAt(0).toLowerCase() + methodName.slice(1);

                // Create the method with validation
                apiClient[methodName] = function (data) {
                    // Initialize data if not provided
                    data = data || {};

                    // Validate required parameters
                    if (endpoint.required && Array.isArray(endpoint.required)) {
                        for (var i = 0; i < endpoint.required.length; i++) {
                            var param = endpoint.required[i];
                            if (data[param] === undefined) {
                                return Promise.reject({
                                    status: 400,
                                    message: 'Missing required parameter: ' + param
                                });
                            }
                        }
                    }

                    // Validate parameter types
                    if (endpoint.params && typeof data === 'object') {
                        for (var key in data) {
                            if (endpoint.params[key]) {
                                if (!apiClient.validateParam(data[key], endpoint.params[key])) {
                                    return Promise.reject({
                                        status: 400,
                                        message: 'Invalid type for parameter ' + key + '. Expected ' + endpoint.params[key]
                                    });
                                }
                            }
                        }
                    }

                    // Make the request
                    return apiClient.request(endpoint.path, endpoint.method, data);
                };
            });
        }

        return apiClient;
    };

})(jQuery); 