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
            // See: https://developers.facebook.com/docs/meta-pixel/advanced/deduplicate-pixel-and-server-events
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
     * Fire all static events from PHP
     */
    function fireStaticEvents() {
        var events = data.staticEvents;

        if (!events || !Array.isArray(events)) {
            return;
        }

        for (var i = 0; i < events.length; i++) {
            try {
                fireEvent(events[i]);
            } catch (e) {
                logWarning('fireStaticEvents loop error:', e);
            }
        }
    }

    /**
     * Initialize event firing on page load
     */
    function init() {
        if (document.readyState === 'complete') {
            fireStaticEvents();
        } else {
            window.addEventListener('load', fireStaticEvents);
        }
    }

    // Start
    init();

})();
