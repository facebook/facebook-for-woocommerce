/**
 * Event Schemas - Field definitions for Pixel and CAPI events
 */

module.exports = {
    PageView: {
        required: {
            pixel: ['event_name', 'event_id', 'pixel_id', 'timestamp'],
            capi: ['event_name', 'event_id', 'event_time', 'action_source', 'user_data']
        },
        custom_data: []
    },

    ViewContent: {
        required: {
            pixel: ['event_name', 'event_id', 'pixel_id', 'timestamp', 'custom_data'],
            capi: ['event_name', 'event_id', 'event_time', 'action_source', 'user_data', 'custom_data']
        },
        custom_data: ['content_ids', 'content_type', 'content_name', 'value','contents' , 'content_category','currency']
    },

    AddToCart: {
        required: {
            pixel: ['event_name', 'event_id', 'pixel_id', 'timestamp', 'custom_data'],
            capi: ['event_name', 'event_id', 'event_time', 'action_source', 'user_data', 'custom_data']
        },
        custom_data: ['content_ids', 'content_type', 'content_name', 'value', 'currency']
    },

    InitiateCheckout: {
        required: {
            pixel: ['event_name', 'event_id', 'pixel_id', 'timestamp', 'custom_data'],
            capi: ['event_name', 'event_id', 'event_time', 'action_source', 'user_data', 'custom_data']
        },
        custom_data: ['content_ids', 'content_type', 'num_items', 'value', 'currency']
    },

    Purchase: {
        required: {
            pixel: ['event_name', 'event_id', 'pixel_id', 'timestamp', 'custom_data'],
            capi: ['event_name', 'event_id', 'event_time', 'action_source', 'user_data', 'custom_data']
        },
        custom_data: ['content_ids', 'content_type', 'value', 'currency']
        // Note: order_id is optional/custom field
    },

    ViewCategory: {
        required: {
            pixel: ['event_name', 'event_id', 'pixel_id', 'timestamp', 'custom_data'],
            capi: ['event_name', 'event_id', 'event_time', 'action_source', 'user_data', 'custom_data']
        },
        custom_data: ['content_name', 'content_category','content_ids','content_type', 'contents' ]
    }
    // TODO add Search, Subscribe, Lead
};
