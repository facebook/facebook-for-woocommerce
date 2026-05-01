/**
 * Facebook Pixel Events - External JavaScript Handler
 *
 * This script fires pixel events in an isolated execution context,
 * ensuring events are sent even if other plugins cause JavaScript errors.
 *
 * Supports WooCommerce Blocks via Store API fetch interception.
 * The pixel event data is read from the Store API response extensions.
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

    // =========================================================================
    // WooCommerce Blocks: Store API approach
    // =========================================================================

    /**
     * Process pixel event data from Store API response.
     *
     * @param {Object} eventData Event data from Store API extensions
     */
    function processStoreApiEvent(eventData) {
        if (!eventData || !eventData.event) {
            return;
        }

        var params = eventData.params || {};

        var event = {
            method: 'track',
            name: eventData.event,
            params: params,
            eventId: params.event_id || null
        };

        fireEvent(event);
    }

    /**
     * Set up fetch interceptor to capture Store API responses.
     * Only intercepts cart/add-item requests to fire AddToCart pixel events.
     */
    function setupFetchInterceptor() {
        var originalFetch = window.fetch;
        if (!originalFetch) {
            return;
        }

        window.fetch = function() {
            var args = arguments;
            var url = args[0];

            // Only intercept add-item requests (not general cart requests)
            var isAddToCartRequest = typeof url === 'string' &&
                (url.indexOf('/wc/store/v1/cart/add-item') !== -1 ||
                 url.indexOf('/wc/store/cart/add-item') !== -1);

            return originalFetch.apply(this, args).then(function(response) {
                if (isAddToCartRequest && response.ok) {
                    // Clone response so we can read it without consuming
                    response.clone().json().then(function(responseData) {
                        if (responseData && responseData.extensions && responseData.extensions['facebook-for-woocommerce']) {
                            processStoreApiEvent(responseData.extensions['facebook-for-woocommerce']);
                        }
                    }).catch(function(e) {
                        logWarning('Store API JSON parse error:', e);
                    });
                }
                return response;
            });
        };
    }

    // =========================================================================
    // Third-party AJAX plugins: XHR interceptor + cookie approach
    // =========================================================================

    /**
     * Check for an AddToCart pixel event cookie set by PHP.
     * PHP sets this cookie during inject_add_to_cart_event() for AJAX requests.
     * This catches third-party plugins (YITH WAPO, custom handlers, etc.) that
     * bypass both the standard WC AJAX endpoint and fragment refresh.
     */
    function checkAddToCartCookie() {
        try {
            var match = document.cookie.match(/(?:^|;\s*)fb_atc_pixel_event=([^;]+)/);
            if (!match) {
                return;
            }

            var eventData = JSON.parse(decodeURIComponent(match[1]));

            // Clear the cookie immediately (one-time use)
            document.cookie = 'fb_atc_pixel_event=; Max-Age=0; path=/';

            if (!eventData || !eventData.event) {
                return;
            }

            var params = eventData.params || {};
            var eventId = params.event_id || null;

            // Skip if this event was already fired by another path (e.g., fragment inline script).
            // This prevents duplicates on standard WooCommerce sites that don't need the cookie fallback.
            if (shouldSkipEvent(eventId)) {
                return;
            }

            fireEvent({
                method: 'track',
                name: eventData.event,
                params: params,
                eventId: eventId
            });

        } catch (e) {
            logWarning('AddToCart cookie parse error:', e);
            // Clear malformed cookie
            document.cookie = 'fb_atc_pixel_event=; Max-Age=0; path=/';
        }
    }

    /**
     * Set up XHR interceptor to detect add-to-cart AJAX from third-party plugins.
     * After any successful POST XHR, checks for a cookie set by PHP containing
     * the AddToCart pixel event data. This is the universal fallback that works
     * regardless of which AJAX endpoint the plugin uses.
     */
    function setupXHRInterceptor() {
        var OrigXHR = window.XMLHttpRequest;
        if (!OrigXHR) {
            return;
        }

        var origOpen = OrigXHR.prototype.open;
        var origSend = OrigXHR.prototype.send;

        OrigXHR.prototype.open = function(method) {
            this._fbMethod = method;
            return origOpen.apply(this, arguments);
        };

        OrigXHR.prototype.send = function() {
            var xhr = this;
            if (xhr._fbMethod && xhr._fbMethod.toUpperCase() === 'POST') {
                xhr.addEventListener('load', function() {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        // Small delay to ensure the browser has processed the Set-Cookie header
                        setTimeout(checkAddToCartCookie, 50);
                    }
                });
            }
            return origSend.apply(this, arguments);
        };
    }

    /**
     * Initialize pixel event handling.
     *
     * If fbq() is already available, fires queued events immediately.
     * If not (e.g. consent manager blocking the SDK), uses Object.defineProperty
     * to set a trap on window.fbq — our handler fires automatically the moment
     * fbq is assigned, with zero overhead in between. No polling, no timers.
     *
     * Also sets up Store API interceptor for WooCommerce Blocks AJAX AddToCart.
     */
    function init() {
        // Set up fetch interceptor for WooCommerce Blocks Store API
        setupFetchInterceptor();

        // Set up XHR interceptor for third-party AJAX plugins (YITH WAPO, custom handlers, etc.)
        setupXHRInterceptor();

        // Check for any pending AddToCart cookie from a previous AJAX request
        checkAddToCartCookie();

        if (typeof fbq === 'function') {
            fireQueuedEvents();
            return;
        }

        // fbq doesn't exist yet — watch for it (zero overhead, no timers).
        // Consent managers block fbq until the
        // user accepts. This fires the moment they assign window.fbq.
        var _fbq = window.fbq;
        Object.defineProperty(window, 'fbq', {
            configurable: true,
            enumerable: true,
            get: function() { return _fbq; },
            set: function(value) {
                _fbq = value;
                if (typeof value === 'function') {
                    // Restore normal property so FB SDK works normally
                    Object.defineProperty(window, 'fbq', {
                        configurable: true,
                        enumerable: true,
                        writable: true,
                        value: value
                    });
                    setTimeout(fireQueuedEvents, 0);
                }
            }
        });
    }

    // Start
    if (document.readyState === 'complete') {
        init();
    } else {
        window.addEventListener('load', init);
    }

})();
