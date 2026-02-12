/**
 * Facebook Pixel Events - External JavaScript Handler
 *
 * This script fires pixel events in an isolated execution context,
 * ensuring events are sent even if other plugins cause JavaScript errors.
 *
 * @package FacebookCommerce
 */

(function() {
    'use strict';

    // Early exit if no data from PHP
    if (typeof wc_facebook_pixel_data === 'undefined') {
        return;
    }

    var data = wc_facebook_pixel_data;
    var firedEvents = {};

    /**
     * Generate a unique event ID for deduplication
     *
     * @return {string} UUID-like event ID
     */
    function generateEventId() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random() * 16 | 0;
            var v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    /**
     * Build event data object for fbq()
     *
     * @param {Object} event Event object from PHP
     * @return {Object} Prepared event data
     */
    function buildEventData(event) {
        return {
            method: event.method || 'track',
            name: event.name,
            params: event.params || {},
            eventId: event.eventId || null
        };
    }

    /**
     * Check if event should be skipped (already fired)
     *
     * @param {string|null} eventId Event ID for deduplication
     * @return {boolean} True if should skip
     */
    function shouldSkipEvent(eventId) {
        return eventId && firedEvents[eventId];
    }

    /**
     * Mark event as fired for deduplication
     *
     * @param {string|null} eventId Event ID
     */
    function markEventFired(eventId) {
        if (eventId) {
            firedEvents[eventId] = true;
        }
    }

    /**
     * Log warning to console (with safety check)
     *
     * @param {string} message Warning message
     * @param {*} data Additional data to log
     */
    function logWarning(message, data) {
        if (typeof console !== 'undefined' && console.warn) {
            console.warn('[FB Pixel]', message, data);
        }
    }

    /**
     * Fire a single event using fbq()
     *
     * @param {Object} event Event object with name, params, method, eventId
     */
    function fireEvent(event) {
        var eventData = buildEventData(event);

        // Skip if already fired (deduplication)
        if (shouldSkipEvent(eventData.eventId)) {
            return;
        }

        // Skip if fbq not available
        if (typeof fbq !== 'function') {
            logWarning('fbq not available, skipping event:', eventData.name);
            return;
        }

        try {
            var params = eventData.params;

            // Fire the event with eventID as 4th argument for deduplication
            if (eventData.eventId) {
                fbq(eventData.method, eventData.name, params, {eventID: eventData.eventId});
            } else {
                fbq(eventData.method, eventData.name, params);
            }

            markEventFired(eventData.eventId);

        } catch (e) {
            logWarning('Event error: ' + eventData.name, e);
        }
    }

    /**
     * Fire all queued events from PHP
     */
    function fireQueuedEvents() {
        var events = data.eventQueue;

        if (!events || !Array.isArray(events)) {
            return;
        }

        for (var i = 0; i < events.length; i++) {
            try {
                fireEvent(events[i]);
            } catch (e) {
                logWarning('fireQueuedEvents loop error:', e);
            }
        }

        // Clear events after firing to prevent duplicate firing
        data.eventQueue = [];
    }

    /**
     * Handle click on add-to-cart button
     * Fires AddToCart pixel immediately before AJAX starts
     *
     * @param {Event} e Click event
     */
    function handleAddToCartClick(e) {
        var button = e.target.closest('.add_to_cart_button');
        if (!button) {
            return;
        }

        // Get product ID from WooCommerce's data attribute
        var productId = button.getAttribute('data-product_id');
        if (!productId) {
            return;
        }

        // Try to get product data from localized object first
        var productData = data.productData && data.productData[productId];

        // Fallback: read from data attributes on button (for WC Blocks)
        if (!productData) {
            var contentIdsAttr = button.getAttribute('data-fb-content-ids');
            if (!contentIdsAttr) {
                return; // No data available
            }

            try {
                productData = {
                    event_id: button.getAttribute('data-fb-event-id'),
                    content_ids: JSON.parse(contentIdsAttr),
                    content_name: button.getAttribute('data-fb-content-name') || '',
                    content_type: button.getAttribute('data-fb-content-type') || 'product',
                    value: parseFloat(button.getAttribute('data-fb-value')) || 0,
                    currency: button.getAttribute('data-fb-currency') || 'USD'
                };
            } catch (err) {
                logWarning('Failed to parse product data from attributes', err);
                return;
            }
        }
        console.log("productData", productData);

        // Use pre-generated event ID from server for deduplication with CAPI
        var eventId = productData.event_id || generateEventId();

        // Build event params from product data
        var params = {
            content_ids: productData.content_ids,
            content_type: productData.content_type || 'product',
            content_name: productData.content_name || '',
            contents: productData.contents || [],
            value: parseFloat(productData.value) || 0,
            currency: productData.currency || 'USD'
        };

        // Store event ID on button for potential use
        button.setAttribute('data-fb-event-id', eventId);

        // Fire the pixel immediately
        fireEvent({
            method: 'track',
            name: 'AddToCart',
            params: params,
            eventId: eventId
        });
    }

    /**
     * Initialize click handlers for add-to-cart buttons
     */
    function initAddToCartHandlers() {
        // Use event delegation on document for dynamically added buttons
        document.addEventListener('click', handleAddToCartClick, true);
    }

    /**
     * Initialize event firing on page load
     */
    function init() {
        // Initialize add-to-cart click handlers immediately
        initAddToCartHandlers();

        if (document.readyState === 'complete') {
            fireQueuedEvents();
        } else {
            window.addEventListener('load', fireQueuedEvents);
        }
    }

    // Start
    init();

})();
