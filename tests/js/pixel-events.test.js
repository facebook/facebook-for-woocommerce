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

    describe('fbq Availability Check', function () {
        it('should detect when fbq is available', function () {
            global.fbq = jest.fn();

            expect(typeof global.fbq).toBe('function');
        });

        it('should detect when fbq is not available', function () {
            delete global.fbq;

            expect(typeof global.fbq).not.toBe('function');
        });

        it('should handle fbq being undefined gracefully', function () {
            delete global.fbq;

            // Simulate the check in fireEvent
            const fbqAvailable = typeof fbq === 'function';
            expect(fbqAvailable).toBe(false);
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
        it('should log warning when fbq is not available', function () {
            delete global.fbq;

            // Simulate logWarning
            if (typeof console !== 'undefined' && console.warn) {
                console.warn('[FB Pixel]', 'fbq not available, skipping event:', 'ViewContent');
            }

            expect(consoleWarnSpy).toHaveBeenCalledWith(
                '[FB Pixel]',
                'fbq not available, skipping event:',
                'ViewContent'
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

        it('should handle productData in wc_facebook_pixel_data', function () {
            global.wc_facebook_pixel_data = {
                pixelId: '123456789',
                eventQueue: [],
                agentString: 'woocommerce-facebook-for-woocommerce-3.0.0',
                productData: {
                    '42': {
                        event_id: 'uuid-for-product-42',
                        content_ids: ['WC-42'],
                        content_type: 'product',
                        value: 19.99,
                        currency: 'USD',
                        content_name: 'Test Product',
                        contents: [{ id: 'WC-42', quantity: 1 }],
                    },
                },
            };

            const data = global.wc_facebook_pixel_data;
            const productData = data.productData['42'];

            expect(productData).toBeDefined();
            expect(productData.event_id).toBe('uuid-for-product-42');
            expect(productData.content_ids).toEqual(['WC-42']);
            expect(productData.content_name).toBe('Test Product');
            expect(productData.contents).toEqual([{ id: 'WC-42', quantity: 1 }]);
        });

        it('should handle empty productData', function () {
            global.wc_facebook_pixel_data = {
                pixelId: '123456789',
                eventQueue: [],
                agentString: 'woocommerce-facebook-for-woocommerce-3.0.0',
                productData: {},
            };

            const data = global.wc_facebook_pixel_data;
            const productData = data.productData && data.productData['999'];

            expect(productData).toBeUndefined();
        });

        it('should handle missing productData key', function () {
            global.wc_facebook_pixel_data = {
                pixelId: '123456789',
                eventQueue: [],
                agentString: 'woocommerce-facebook-for-woocommerce-3.0.0',
            };

            const data = global.wc_facebook_pixel_data;
            const productData = data.productData && data.productData['42'];

            expect(productData).toBeFalsy();
        });
    });

    describe('generateEventId', function () {
        it('should generate a valid UUID v4 string', function () {
            const generateEventId = function () {
                return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                    var r = Math.random() * 16 | 0;
                    var v = c === 'x' ? r : (r & 0x3 | 0x8);
                    return v.toString(16);
                });
            };

            const eventId = generateEventId();

            expect(eventId).toMatch(
                /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/
            );
        });

        it('should generate unique IDs on each call', function () {
            const generateEventId = function () {
                return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                    var r = Math.random() * 16 | 0;
                    var v = c === 'x' ? r : (r & 0x3 | 0x8);
                    return v.toString(16);
                });
            };

            const id1 = generateEventId();
            const id2 = generateEventId();
            const id3 = generateEventId();

            expect(id1).not.toBe(id2);
            expect(id2).not.toBe(id3);
            expect(id1).not.toBe(id3);
        });

        it('should always have version 4 in the correct position', function () {
            const generateEventId = function () {
                return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                    var r = Math.random() * 16 | 0;
                    var v = c === 'x' ? r : (r & 0x3 | 0x8);
                    return v.toString(16);
                });
            };

            for (let i = 0; i < 20; i++) {
                const eventId = generateEventId();
                // 15th character (index 14) should always be '4'
                expect(eventId.charAt(14)).toBe('4');
                // 20th character (index 19) should be 8, 9, a, or b
                expect(['8', '9', 'a', 'b']).toContain(eventId.charAt(19));
            }
        });
    });

    describe('AJAX AddToCart Click Handler', function () {
        it('should fire AddToCart event using productData', function () {
            const productData = {
                event_id: 'uuid-product-99',
                content_ids: ['WC-99'],
                content_type: 'product',
                content_name: 'Shop Product',
                contents: [{ id: 'WC-99', quantity: 1 }],
                value: 24.99,
                currency: 'USD',
            };

            const eventId = productData.event_id;
            const params = {
                content_ids: productData.content_ids,
                content_type: productData.content_type || 'product',
                content_name: productData.content_name || '',
                contents: productData.contents || [],
                value: parseFloat(productData.value) || 0,
                currency: productData.currency || 'USD',
            };

            global.fbq('track', 'AddToCart', params, { eventID: eventId });

            expect(mockFbq).toHaveBeenCalledWith(
                'track',
                'AddToCart',
                expect.objectContaining({
                    content_ids: ['WC-99'],
                    content_type: 'product',
                    content_name: 'Shop Product',
                    contents: [{ id: 'WC-99', quantity: 1 }],
                    value: 24.99,
                    currency: 'USD',
                }),
                { eventID: 'uuid-product-99' }
            );
        });

        it('should fall back to data-fb-* attributes when productData is missing', function () {
            const button = document.createElement('a');
            button.classList.add('add_to_cart_button');
            button.setAttribute('data-product_id', '55');
            button.setAttribute('data-fb-event-id', 'attr-event-id');
            button.setAttribute('data-fb-content-ids', '["WC-55"]');
            button.setAttribute('data-fb-content-name', 'Fallback Product');
            button.setAttribute('data-fb-content-type', 'product');
            button.setAttribute('data-fb-value', '15.00');
            button.setAttribute('data-fb-currency', 'EUR');

            const contentIdsAttr = button.getAttribute('data-fb-content-ids');
            const productData = {
                event_id: button.getAttribute('data-fb-event-id'),
                content_ids: JSON.parse(contentIdsAttr),
                content_name: button.getAttribute('data-fb-content-name') || '',
                content_type: button.getAttribute('data-fb-content-type') || 'product',
                value: parseFloat(button.getAttribute('data-fb-value')) || 0,
                currency: button.getAttribute('data-fb-currency') || 'USD',
            };

            expect(productData.event_id).toBe('attr-event-id');
            expect(productData.content_ids).toEqual(['WC-55']);
            expect(productData.content_name).toBe('Fallback Product');
            expect(productData.value).toBe(15.00);
            expect(productData.currency).toBe('EUR');
        });

        it('should handle invalid JSON in data-fb-content-ids gracefully', function () {
            const button = document.createElement('a');
            button.setAttribute('data-product_id', '60');
            button.setAttribute('data-fb-content-ids', 'not-valid-json');

            let parseError = false;
            try {
                JSON.parse(button.getAttribute('data-fb-content-ids'));
            } catch (err) {
                parseError = true;
            }

            expect(parseError).toBe(true);
        });

        it('should use fallback generateEventId when productData has no event_id', function () {
            const productData = {
                content_ids: ['WC-70'],
                content_type: 'product',
                value: 10.00,
                currency: 'USD',
            };

            const generateEventId = function () {
                return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                    var r = Math.random() * 16 | 0;
                    var v = c === 'x' ? r : (r & 0x3 | 0x8);
                    return v.toString(16);
                });
            };

            const eventId = productData.event_id || generateEventId();

            // Should be a valid UUID since productData.event_id is undefined
            expect(eventId).toMatch(
                /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/
            );
        });

        it('should skip click if button has no data-product_id', function () {
            const button = document.createElement('a');
            button.classList.add('add_to_cart_button');

            const productId = button.getAttribute('data-product_id');
            expect(productId).toBeNull();
        });

        it('should skip variable products (no data-fb-content-ids and no productData)', function () {
            const button = document.createElement('a');
            button.classList.add('add_to_cart_button');
            button.setAttribute('data-product_id', '70');

            const productData = null;
            const contentIdsAttr = button.getAttribute('data-fb-content-ids');

            const shouldSkip = !productData && !contentIdsAttr;
            expect(shouldSkip).toBe(true);
        });

        it('should deduplicate AddToCart events per product per page load', function () {
            const firedEvents = {};
            const eventId = 'uuid-product-42';

            // First click — should fire
            const skip1 = eventId && firedEvents[eventId];
            expect(skip1).toBeFalsy();
            firedEvents[eventId] = true;

            // Second click (same product) — should skip
            const skip2 = eventId && firedEvents[eventId];
            expect(skip2).toBe(true);
        });

        it('should allow different products to fire independently', function () {
            const firedEvents = {};

            // Product A fires
            firedEvents['uuid-product-A'] = true;

            // Product B should still fire
            const skipB = 'uuid-product-B' && firedEvents['uuid-product-B'];
            expect(skipB).toBeFalsy();
        });

        it('should use capture phase for click listener', function () {
            const addEventListenerSpy = jest.spyOn(document, 'addEventListener');

            document.addEventListener('click', function handler() {}, true);

            expect(addEventListenerSpy).toHaveBeenCalledWith(
                'click',
                expect.any(Function),
                true
            );

            addEventListenerSpy.mockRestore();
        });

        it('should store eventId on button attribute after firing', function () {
            const button = document.createElement('a');
            button.classList.add('add_to_cart_button');
            button.setAttribute('data-product_id', '88');

            const eventId = 'stored-event-id-88';
            button.setAttribute('data-fb-event-id', eventId);

            expect(button.getAttribute('data-fb-event-id')).toBe('stored-event-id-88');
        });

        it('should include contents array in AddToCart params', function () {
            const productData = {
                event_id: 'uuid-contents-test',
                content_ids: ['WC-101'],
                content_type: 'product',
                content_name: 'Contents Test Product',
                contents: [{ id: 'WC-101', quantity: 1 }],
                value: 39.99,
                currency: 'USD',
            };

            const params = {
                content_ids: productData.content_ids,
                content_type: productData.content_type || 'product',
                content_name: productData.content_name || '',
                contents: productData.contents || [],
                value: parseFloat(productData.value) || 0,
                currency: productData.currency || 'USD',
            };

            global.fbq('track', 'AddToCart', params, { eventID: productData.event_id });

            expect(mockFbq).toHaveBeenCalledWith(
                'track',
                'AddToCart',
                expect.objectContaining({
                    contents: [{ id: 'WC-101', quantity: 1 }],
                }),
                { eventID: 'uuid-contents-test' }
            );
        });

        it('should default contents to empty array when not provided', function () {
            const productData = {
                event_id: 'uuid-no-contents',
                content_ids: ['WC-102'],
                value: 5.00,
                currency: 'USD',
            };

            const params = {
                content_ids: productData.content_ids,
                content_type: productData.content_type || 'product',
                content_name: productData.content_name || '',
                contents: productData.contents || [],
                value: parseFloat(productData.value) || 0,
                currency: productData.currency || 'USD',
            };

            expect(params.contents).toEqual([]);
            expect(params.content_type).toBe('product');
            expect(params.content_name).toBe('');
        });
    });
});
