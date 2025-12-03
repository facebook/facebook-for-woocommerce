/**
 * Event Schemas - Field definitions for Pixel and CAPI events
 */

module.exports = {
    PageView: {
        required: {
            pixel: ['eventName', 'eventId', 'pixelId', 'timestamp'],
            capi: ['event_name', 'event_id', 'event_time', 'action_source', 'user_data']
        },
        custom_data: []
    }
};
