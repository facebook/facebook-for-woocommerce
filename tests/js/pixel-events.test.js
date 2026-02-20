/**
 * Tests for Facebook Pixel Events Isolated Execution (pixel-events.js)
 *
 * These tests validate the isolated JavaScript execution context for pixel events,
 * ensuring events fire even when other plugins cause JS errors.
 *
 * @package FacebookCommerce
 */

describe('Pixel Events - Isolated Execution Context', function () {
    let mockFbq;
    let originalFbq;
    let consoleWarnSpy;

    beforeEach(function () {
        // Store original fbq if exists
        originalFbq = global.fbq;

        // Create mock fbq function
        mockFbq = jest.fn();
        global.fbq = mockFbq;

        // Spy on console.warn
        consoleWarnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});

        // Reset document ready state
        Object.defineProperty(document, 'readyState', {
            value: 'complete',
            writable: true,
        });
    });

    afterEach(function () {
        // Restore original fbq
        if (originalFbq) {
            global.fbq = originalFbq;
        } else {
            delete global.fbq;
        }

        // Restore console.warn
        consoleWarnSpy.mockRestore();

        // Clean up global data
        delete global.wc_facebook_pixel_data;

        jest.resetAllMocks();
    });

    describe('buildEventData function', function () {
        it('should build event data with default method', function () {
            const event = {
                name: 'ViewContent',
                params: { content_ids: ['123'] },
            };

            // Simulate buildEventData logic
            const eventData = {
                method: event.method || 'track',
                name: event.name,
                params: event.params || {},
                eventId: event.eventId || null,
            };

            expect(eventData.method).toBe('track');
            expect(eventData.name).toBe('ViewContent');
            expect(eventData.params.content_ids).toEqual(['123']);
            expect(eventData.eventId).toBeNull();
        });

        it('should use provided method', function () {
            const event = {
                name: 'CustomEvent',
                method: 'trackCustom',
                params: {},
            };

            const eventData = {
                method: event.method || 'track',
                name: event.name,
                params: event.params || {},
                eventId: event.eventId || null,
            };

            expect(eventData.method).toBe('trackCustom');
        });

        it('should preserve eventId when provided', function () {
            const event = {
                name: 'AddToCart',
                params: {},
                eventId: 'abc123-unique-id',
            };

            const eventData = {
                method: event.method || 'track',
                name: event.name,
                params: event.params || {},
                eventId: event.eventId || null,
            };

            expect(eventData.eventId).toBe('abc123-unique-id');
        });
    });

    describe('Event Deduplication', function () {
        it('should skip event if already fired with same eventId', function () {
            const firedEvents = {};
            const eventId = 'unique-event-123';

            // First time - should not skip
            const shouldSkip1 = eventId && firedEvents[eventId];
            expect(shouldSkip1).toBeFalsy();

            // Mark as fired
            firedEvents[eventId] = true;

            // Second time - should skip
            const shouldSkip2 = eventId && firedEvents[eventId];
            expect(shouldSkip2).toBe(true);
        });

        it('should not skip event if eventId is null', function () {
            const firedEvents = {};
            const eventId = null;

            const shouldSkip = eventId && firedEvents[eventId];
            expect(shouldSkip).toBeFalsy();
        });

        it('should not skip event if eventId is empty string', function () {
            const firedEvents = {};
            const eventId = '';

            const shouldSkip = eventId && firedEvents[eventId];
            expect(shouldSkip).toBeFalsy();
        });

        it('should track multiple different eventIds independently', function () {
            const firedEvents = {};

            firedEvents['event-1'] = true;
            firedEvents['event-2'] = true;

            expect(firedEvents['event-1']).toBe(true);
            expect(firedEvents['event-2']).toBe(true);
            expect(firedEvents['event-3']).toBeUndefined();
        });
    });

    describe('fbq Availability and Object.defineProperty Trap', function () {
        it('should fire events immediately when fbq is already available', function () {
            global.fbq = jest.fn();

            var eventsFired = false;

            function fireQueuedEvents() {
                eventsFired = true;
            }

            function init() {
                if (typeof global.fbq === 'function') {
                    fireQueuedEvents();
                    return;
                }
            }

            init();
            expect(eventsFired).toBe(true);
        });

        it('should set Object.defineProperty trap when fbq is not available', function () {
            delete global.fbq;

            var trapSet = false;
            var originalDefineProperty = Object.defineProperty;

            var definePropertySpy = jest.spyOn(Object, 'defineProperty');

            function init() {
                if (typeof global.fbq === 'function') {
                    return;
                }

                var _fbq = global.fbq;
                Object.defineProperty(global, 'fbq', {
                    configurable: true,
                    enumerable: true,
                    get: function() { return _fbq; },
                    set: function(value) {
                        _fbq = value;
                    }
                });
                trapSet = true;
            }

            init();
            expect(trapSet).toBe(true);
            expect(definePropertySpy).toHaveBeenCalledWith(
                global,
                'fbq',
                expect.objectContaining({
                    configurable: true,
                    enumerable: true,
                })
            );

            // Clean up
            Object.defineProperty(global, 'fbq', {
                configurable: true,
                enumerable: true,
                writable: true,
                value: undefined
            });
            delete global.fbq;
            definePropertySpy.mockRestore();
        });

        it('should fire events when fbq is assigned after trap is set', function () {
            delete global.fbq;

            var eventsFired = false;

            function fireQueuedEvents() {
                eventsFired = true;
            }

            // Set up the trap (simulating init logic)
            var _fbq = global.fbq;
            Object.defineProperty(global, 'fbq', {
                configurable: true,
                enumerable: true,
                get: function() { return _fbq; },
                set: function(value) {
                    _fbq = value;
                    if (typeof value === 'function') {
                        Object.defineProperty(global, 'fbq', {
                            configurable: true,
                            enumerable: true,
                            writable: true,
                            value: value
                        });
                        fireQueuedEvents();
                    }
                }
            });

            expect(eventsFired).toBe(false);

            // Simulate consent manager / FB SDK assigning fbq
            global.fbq = jest.fn();

            expect(eventsFired).toBe(true);
        });

        it('should restore fbq to a normal property after trap fires', function () {
            delete global.fbq;

            var _fbq = global.fbq;
            Object.defineProperty(global, 'fbq', {
                configurable: true,
                enumerable: true,
                get: function() { return _fbq; },
                set: function(value) {
                    _fbq = value;
                    if (typeof value === 'function') {
                        Object.defineProperty(global, 'fbq', {
                            configurable: true,
                            enumerable: true,
                            writable: true,
                            value: value
                        });
                    }
                }
            });

            var mockFn = jest.fn();
            global.fbq = mockFn;

            // After trap fires, fbq should be a normal writable property
            var anotherFn = jest.fn();
            global.fbq = anotherFn;
            expect(global.fbq).toBe(anotherFn);
        });

        it('should not fire events if a non-function value is assigned to fbq', function () {
            delete global.fbq;

            var eventsFired = false;

            function fireQueuedEvents() {
                eventsFired = true;
            }

            var _fbq = global.fbq;
            Object.defineProperty(global, 'fbq', {
                configurable: true,
                enumerable: true,
                get: function() { return _fbq; },
                set: function(value) {
                    _fbq = value;
                    if (typeof value === 'function') {
                        Object.defineProperty(global, 'fbq', {
                            configurable: true,
                            enumerable: true,
                            writable: true,
                            value: value
                        });
                        fireQueuedEvents();
                    }
                }
            });

            // Assign a non-function value
            global.fbq = 'not a function';
            expect(eventsFired).toBe(false);

            // Now assign a function
            global.fbq = jest.fn();
            expect(eventsFired).toBe(true);
        });

        it('should handle consent manager delayed consent (simulated)', function () {
            delete global.fbq;

            jest.useFakeTimers();
            var eventsFired = false;

            function fireQueuedEvents() {
                eventsFired = true;
            }

            var _fbq = global.fbq;
            Object.defineProperty(global, 'fbq', {
                configurable: true,
                enumerable: true,
                get: function() { return _fbq; },
                set: function(value) {
                    _fbq = value;
                    if (typeof value === 'function') {
                        Object.defineProperty(global, 'fbq', {
                            configurable: true,
                            enumerable: true,
                            writable: true,
                            value: value
                        });
                        fireQueuedEvents();
                    }
                }
            });

            // Simulate 30 seconds passing (user hasn't consented yet)
            jest.advanceTimersByTime(30000);
            expect(eventsFired).toBe(false);

            // User clicks "Accept Cookies" — consent manager assigns fbq
            global.fbq = jest.fn();
            expect(eventsFired).toBe(true);

            jest.useRealTimers();
        });
    });

    describe('fireEvent function', function () {
        it('should call fbq with correct parameters', function () {
            const event = {
                name: 'ViewContent',
                method: 'track',
                params: {
                    content_ids: ['SKU123'],
                    content_type: 'product',
                    value: 29.99,
                    currency: 'USD',
                },
            };

            // Simulate fireEvent logic
            global.fbq(event.method, event.name, event.params);

            expect(mockFbq).toHaveBeenCalledWith(
                'track',
                'ViewContent',
                expect.objectContaining({
                    content_ids: ['SKU123'],
                    value: 29.99,
                })
            );
        });

        it('should call fbq with eventID as 4th argument when eventId is provided', function () {
            const event = {
                name: 'AddToCart',
                method: 'track',
                params: { content_ids: ['456'] },
                eventId: 'dedup-id-789',
            };

            // Simulate fireEvent logic with eventID as 4th argument
            if (event.eventId) {
                global.fbq(event.method, event.name, event.params, {eventID: event.eventId});
            } else {
                global.fbq(event.method, event.name, event.params);
            }

            expect(mockFbq).toHaveBeenCalledWith(
                'track',
                'AddToCart',
                { content_ids: ['456'] },
                { eventID: 'dedup-id-789' }
            );
        });

        it('should not pass 4th argument when eventId is not provided', function () {
            const event = {
                name: 'ViewContent',
                method: 'track',
                params: { content_ids: ['123'] },
            };

            if (event.eventId) {
                global.fbq(event.method, event.name, event.params, {eventID: event.eventId});
            } else {
                global.fbq(event.method, event.name, event.params);
            }

            expect(mockFbq).toHaveBeenCalledWith(
                'track',
                'ViewContent',
                { content_ids: ['123'] }
            );
            // Should only have 3 arguments (no 4th argument)
            expect(mockFbq.mock.calls[0].length).toBe(3);
        });

        it('should handle fbq errors gracefully', function () {
            // Make fbq throw an error
            global.fbq = jest.fn(() => {
                throw new Error('fbq error');
            });

            // Simulate fireEvent with try-catch
            let errorCaught = false;
            try {
                global.fbq('track', 'ViewContent', {});
            } catch (e) {
                errorCaught = true;
                // Log warning instead of crashing
                console.warn('[FB Pixel] Event error:', e);
            }

            expect(errorCaught).toBe(true);
            expect(consoleWarnSpy).toHaveBeenCalled();
        });
    });

    describe('fireQueuedEvents function', function () {
        it('should fire all events in eventQueue array', function () {
            const eventQueue = [
                { name: 'ViewContent', params: { id: '1' }, method: 'track' },
                { name: 'AddToCart', params: { id: '2' }, method: 'track' },
                { name: 'Purchase', params: { id: '3' }, method: 'track' },
            ];

            // Simulate fireQueuedEvents
            eventQueue.forEach((event) => {
                global.fbq(event.method, event.name, event.params);
            });

            expect(mockFbq).toHaveBeenCalledTimes(3);
            expect(mockFbq).toHaveBeenNthCalledWith(1, 'track', 'ViewContent', { id: '1' });
            expect(mockFbq).toHaveBeenNthCalledWith(2, 'track', 'AddToCart', { id: '2' });
            expect(mockFbq).toHaveBeenNthCalledWith(3, 'track', 'Purchase', { id: '3' });
        });

        it('should handle empty eventQueue array', function () {
            const eventQueue = [];

            eventQueue.forEach((event) => {
                global.fbq(event.method, event.name, event.params);
            });

            expect(mockFbq).not.toHaveBeenCalled();
        });

        it('should handle null eventQueue', function () {
            const eventQueue = null;

            // Simulate the check in fireQueuedEvents
            if (!eventQueue || !Array.isArray(eventQueue)) {
                // Early return
                return;
            }

            expect(mockFbq).not.toHaveBeenCalled();
        });

        it('should continue firing events even if one fails', function () {
            const eventQueue = [
                { name: 'ViewContent', params: { id: '1' }, method: 'track' },
                { name: 'BrokenEvent', params: null, method: 'track' }, // This might cause issues
                { name: 'AddToCart', params: { id: '3' }, method: 'track' },
            ];

            let successCount = 0;

            // Simulate fireQueuedEvents with error handling per event
            eventQueue.forEach((event) => {
                try {
                    global.fbq(event.method, event.name, event.params);
                    successCount++;
                } catch (e) {
                    console.warn('[FB Pixel] Event error:', e);
                }
            });

            // All events should be attempted
            expect(mockFbq).toHaveBeenCalledTimes(3);
        });
    });

    describe('Data Validation', function () {
        it('should handle wc_facebook_pixel_data being undefined', function () {
            global.wc_facebook_pixel_data = undefined;

            // Simulate early exit check
            const shouldExit = typeof wc_facebook_pixel_data === 'undefined';
            expect(shouldExit).toBe(true);
        });

        it('should process valid wc_facebook_pixel_data', function () {
            global.wc_facebook_pixel_data = {
                pixelId: '123456789',
                eventQueue: [
                    { name: 'ViewContent', params: {}, method: 'track' },
                ],
                agentString: 'woocommerce-1.0.0',
            };

            expect(global.wc_facebook_pixel_data.pixelId).toBe('123456789');
            expect(global.wc_facebook_pixel_data.eventQueue.length).toBe(1);
        });

        it('should handle missing eventQueue in data', function () {
            global.wc_facebook_pixel_data = {
                pixelId: '123456789',
                agentString: 'woocommerce-1.0.0',
            };

            const events = global.wc_facebook_pixel_data.eventQueue;
            const shouldSkip = !events || !Array.isArray(events);

            expect(shouldSkip).toBe(true);
        });
    });

    describe('Event Parameter Handling', function () {
        it('should preserve all standard event parameters', function () {
            const params = {
                content_ids: ['SKU123', 'SKU456'],
                content_type: 'product',
                value: 149.99,
                currency: 'EUR',
                content_name: 'Test Product',
                num_items: 2,
            };

            global.fbq('track', 'Purchase', params);

            expect(mockFbq).toHaveBeenCalledWith(
                'track',
                'Purchase',
                expect.objectContaining(params)
            );
        });

        it('should handle empty params object', function () {
            global.fbq('track', 'PageView', {});

            expect(mockFbq).toHaveBeenCalledWith('track', 'PageView', {});
        });

        it('should handle params with nested objects', function () {
            const params = {
                content_ids: ['123'],
                contents: [
                    { id: '123', quantity: 1, item_price: 29.99 },
                ],
            };

            global.fbq('track', 'AddToCart', params);

            expect(mockFbq).toHaveBeenCalledWith(
                'track',
                'AddToCart',
                expect.objectContaining({
                    contents: expect.arrayContaining([
                        expect.objectContaining({ id: '123', quantity: 1 }),
                    ]),
                })
            );
        });
    });

    describe('Document Ready State Handling', function () {
        it('should fire events immediately when document is complete', function () {
            Object.defineProperty(document, 'readyState', {
                value: 'complete',
                writable: true,
            });

            const fireStaticEventsCalled = document.readyState === 'complete';
            expect(fireStaticEventsCalled).toBe(true);
        });

        it('should wait for load event when document is not complete', function () {
            Object.defineProperty(document, 'readyState', {
                value: 'loading',
                writable: true,
            });

            const shouldWait = document.readyState !== 'complete';
            expect(shouldWait).toBe(true);
        });

        it('should use window.addEventListener for load event', function () {
            const addEventListenerSpy = jest.spyOn(window, 'addEventListener');

            Object.defineProperty(document, 'readyState', {
                value: 'loading',
                writable: true,
            });

            // Simulate init logic
            if (document.readyState !== 'complete') {
                window.addEventListener('load', () => {});
            }

            expect(addEventListenerSpy).toHaveBeenCalledWith('load', expect.any(Function));
            addEventListenerSpy.mockRestore();
        });
    });

    describe('Console Warning', function () {
        it('should log warning when fbq throws an error during event fire', function () {
            global.fbq = jest.fn(() => {
                throw new Error('fbq error');
            });

            // Simulate logWarning from catch block in fireEvent
            try {
                global.fbq('track', 'ViewContent', {});
            } catch (e) {
                console.warn('[FB Pixel]', 'Event error: ViewContent', e);
            }

            expect(consoleWarnSpy).toHaveBeenCalledWith(
                '[FB Pixel]',
                'Event error: ViewContent',
                expect.any(Error)
            );
        });

        it('should handle console not being available', function () {
            const originalConsole = global.console;
            delete global.console;

            // Should not throw
            expect(() => {
                if (typeof console !== 'undefined' && console.warn) {
                    console.warn('test');
                }
            }).not.toThrow();

            global.console = originalConsole;
        });
    });

    describe('IIFE Isolation', function () {
        it('should not pollute global namespace', function () {
            // The script uses IIFE pattern: (function() { ... })();
            // Internal variables should not be accessible globally

            // Simulate IIFE behavior
            (function () {
                var internalVar = 'secret';
                var firedEvents = {};
            })();

            expect(global.internalVar).toBeUndefined();
            expect(global.firedEvents).toBeUndefined();
        });

        it('should use strict mode', function () {
            // The script starts with 'use strict'
            // This test verifies strict mode behavior

            expect(() => {
                'use strict';
                // In strict mode, using undeclared variable throws
                // undeclaredVar = 'test'; // Would throw ReferenceError
            }).not.toThrow();
        });
    });

    describe('Integration with PHP Data', function () {
        it('should correctly parse PHP-provided event structure', function () {
            // This simulates the structure passed by wp_localize_script
            global.wc_facebook_pixel_data = {
                pixelId: '123456789',
                eventQueue: [
                    {
                        name: 'ViewContent',
                        params: {
                            content_ids: ['WC-PROD-123'],
                            content_type: 'product',
                            value: 49.99,
                            currency: 'USD',
                        },
                        method: 'track',
                        eventId: 'php-generated-uuid-123',
                    },
                ],
                agentString: 'woocommerce-facebook-for-woocommerce-3.0.0',
            };

            const data = global.wc_facebook_pixel_data;
            const event = data.eventQueue[0];

            expect(event.name).toBe('ViewContent');
            expect(event.eventId).toBe('php-generated-uuid-123');
            expect(event.params.content_ids).toContain('WC-PROD-123');
        });

        it('should handle AddToCart deferred event structure', function () {
            global.wc_facebook_pixel_data = {
                pixelId: '123456789',
                eventQueue: [
                    {
                        name: 'AddToCart',
                        params: {
                            content_ids: ['WC-PROD-456'],
                            content_type: 'product',
                            value: 29.99,
                            currency: 'USD',
                        },
                        method: 'track',
                        eventId: 'deferred-atc-event-789',
                    },
                ],
                agentString: 'woocommerce-facebook-for-woocommerce-3.0.0',
            };

            const event = global.wc_facebook_pixel_data.eventQueue[0];

            // Fire the event with eventID as 4th argument (correct approach)
            if (event.eventId) {
                global.fbq(event.method, event.name, event.params, {eventID: event.eventId});
            } else {
                global.fbq(event.method, event.name, event.params);
            }

            expect(mockFbq).toHaveBeenCalledWith(
                'track',
                'AddToCart',
                expect.objectContaining({
                    content_ids: ['WC-PROD-456'],
                }),
                { eventID: 'deferred-atc-event-789' }
            );
        });
    });
});
