/**
 * Event Schemas - Expected structure and validation rules for each event type
 */

const EVENT_SCHEMAS = {
    PageView: {
        eventName: 'PageView',
        required: {
            pixel: ['eventName', 'pixelId', 'timestamp'],
            capi: ['event_name', 'event_time', 'event_id', 'action_source', 'user_data']
        },
        optional: {
            pixel: ['eventId', 'customData', 'userData'],
            capi: ['custom_data', 'event_source_url', 'referrer_url']
        },
        validators: {
            // Event name must match
            eventName: (pixel, capi) => {
                if (pixel && capi) {
                    return pixel.eventName === capi.event_name;
                }
                return true;
            },

            // Event ID must match (for dedup)
            eventId: (pixel, capi) => {
                if (pixel && capi && pixel.eventId && capi.event_id) {
                    return pixel.eventId === capi.event_id;
                }
                return false; // If either is missing, dedup won't work
            },

            // Timestamp should be within 30 seconds
            timestamp: (pixel, capi) => {
                if (pixel && capi) {
                    const pixelTime = pixel.timestamp || Date.now();
                    const capiTime = (capi.event_time || 0) * 1000; // CAPI is in seconds
                    const diff = Math.abs(pixelTime - capiTime);
                    return diff < 30000; // 30 seconds tolerance
                }
                return true;
            },

            // FBP cookie should match
            fbp: (pixel, capi) => {
                const pixelFbp = pixel.userData?.fbp;
                const capiFbp = capi.user_data?.browser_id;

                if (pixelFbp && capiFbp) {
                    return pixelFbp === capiFbp;
                }
                return true; // Optional, so true if either missing
            }
        }
    },

    ViewContent: {
        eventName: 'ViewContent',
        required: {
            pixel: ['eventName', 'pixelId', 'timestamp', 'customData'],
            capi: ['event_name', 'event_time', 'event_id', 'action_source', 'user_data', 'custom_data']
        },
        optional: {
            pixel: ['eventId', 'userData'],
            capi: ['event_source_url', 'referrer_url']
        },
        validators: {
            eventName: (pixel, capi) => pixel?.eventName === capi?.event_name,
            eventId: (pixel, capi) => pixel?.eventId === capi?.event_id,

            // Content IDs should be present and match
            contentIds: (pixel, capi) => {
                const pixelIds = pixel?.customData?.content_ids;
                const capiIds = capi?.custom_data?.content_ids;

                if (!pixelIds || !capiIds) return false;

                // Both should be arrays
                if (!Array.isArray(pixelIds) || !Array.isArray(capiIds)) return false;

                // Same number of IDs
                if (pixelIds.length !== capiIds.length) return false;

                // Same IDs (order doesn't matter)
                return pixelIds.every(id => capiIds.includes(id));
            },

            // Value should match (if present)
            value: (pixel, capi) => {
                const pixelValue = pixel?.customData?.value;
                const capiValue = capi?.custom_data?.value;

                if (pixelValue !== undefined && capiValue !== undefined) {
                    return Math.abs(pixelValue - capiValue) < 0.01; // Allow for floating point
                }
                return true;
            },

            // Currency should match
            currency: (pixel, capi) => {
                const pixelCurrency = pixel?.customData?.currency;
                const capiCurrency = capi?.custom_data?.currency;

                if (pixelCurrency && capiCurrency) {
                    return pixelCurrency === capiCurrency;
                }
                return true;
            }
        }
    },

    AddToCart: {
        eventName: 'AddToCart',
        required: {
            pixel: ['eventName', 'pixelId', 'timestamp', 'customData'],
            capi: ['event_name', 'event_time', 'event_id', 'action_source', 'user_data', 'custom_data']
        },
        optional: {
            pixel: ['eventId', 'userData'],
            capi: ['event_source_url']
        },
        validators: {
            eventName: (pixel, capi) => pixel?.eventName === capi?.event_name,
            eventId: (pixel, capi) => pixel?.eventId === capi?.event_id,
            contentIds: (pixel, capi) => {
                const pixelIds = pixel?.customData?.content_ids;
                const capiIds = capi?.custom_data?.content_ids;
                return pixelIds && capiIds && JSON.stringify(pixelIds) === JSON.stringify(capiIds);
            },
            value: (pixel, capi) => {
                const pixelValue = pixel?.customData?.value;
                const capiValue = capi?.custom_data?.value;
                if (pixelValue !== undefined && capiValue !== undefined) {
                    return Math.abs(pixelValue - capiValue) < 0.01;
                }
                return true;
            }
        }
    },

    InitiateCheckout: {
        eventName: 'InitiateCheckout',
        required: {
            pixel: ['eventName', 'pixelId', 'timestamp', 'customData'],
            capi: ['event_name', 'event_time', 'event_id', 'action_source', 'user_data', 'custom_data']
        },
        optional: {
            pixel: ['eventId', 'userData'],
            capi: ['event_source_url']
        },
        validators: {
            eventName: (pixel, capi) => pixel?.eventName === capi?.event_name,
            eventId: (pixel, capi) => pixel?.eventId === capi?.event_id,
            value: (pixel, capi) => {
                const pixelValue = pixel?.customData?.value;
                const capiValue = capi?.custom_data?.value;
                if (pixelValue !== undefined && capiValue !== undefined) {
                    return Math.abs(pixelValue - capiValue) < 0.01;
                }
                return true;
            }
        }
    },

    Purchase: {
        eventName: 'Purchase',
        required: {
            pixel: ['eventName', 'pixelId', 'timestamp', 'customData'],
            capi: ['event_name', 'event_time', 'event_id', 'action_source', 'user_data', 'custom_data']
        },
        optional: {
            pixel: ['eventId', 'userData'],
            capi: ['event_source_url']
        },
        validators: {
            eventName: (pixel, capi) => pixel?.eventName === capi?.event_name,
            eventId: (pixel, capi) => pixel?.eventId === capi?.event_id,

            // Purchase MUST have value
            value: (pixel, capi) => {
                const pixelValue = pixel?.customData?.value;
                const capiValue = capi?.custom_data?.value;

                if (pixelValue === undefined || capiValue === undefined) return false;
                if (pixelValue <= 0 || capiValue <= 0) return false;

                return Math.abs(pixelValue - capiValue) < 0.01;
            },

            // Purchase MUST have currency
            currency: (pixel, capi) => {
                const pixelCurrency = pixel?.customData?.currency;
                const capiCurrency = capi?.custom_data?.currency;

                if (!pixelCurrency || !capiCurrency) return false;
                if (!/^[A-Z]{3}$/.test(pixelCurrency)) return false; // Must be 3-letter code

                return pixelCurrency === capiCurrency;
            },

            // Purchase should have content_ids
            contentIds: (pixel, capi) => {
                const pixelIds = pixel?.customData?.content_ids;
                const capiIds = capi?.custom_data?.content_ids;

                if (!pixelIds || !capiIds) return false;
                return JSON.stringify(pixelIds) === JSON.stringify(capiIds);
            }
        }
    }
};

module.exports = EVENT_SCHEMAS;
