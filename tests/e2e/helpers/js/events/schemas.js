/**
 * Event Schemas - Field definitions for Pixel and CAPI events
 */

module.exports = {
  PageView: {
    channels: ['pixel', 'capi'],

    pixel: {
      user_data: ['em', 'external_id', 'ct', 'zp', 'cn', 'fbp'],
      custom_data: ['source', 'version', 'pluginVersion', 'user_data']
    },

    capi: {
      user_data: ['em', 'external_id', 'ct', 'zp', 'country', 'fbp', 'client_ip_address', 'client_user_agent'],
      custom_data: []
    }
  },

  ViewContent: {
    channels: ['pixel', 'capi'],

    pixel: {
      user_data: ['em', 'external_id', 'ct', 'zp', 'cn', 'fbp'],
      custom_data: ['source', 'version', 'pluginVersion', 'content_ids', 'content_type', 'content_name', 'value', 'currency', 'contents', 'content_category']
    },

    capi: {
      user_data: ['em', 'external_id', 'ct', 'zp', 'country', 'fbp', 'client_ip_address', 'client_user_agent'],
      custom_data: ['content_ids', 'content_type', 'content_name', 'value', 'currency', 'contents', 'content_category']
    }
  },

  ViewCategory: {
    channels: ['pixel', 'capi'],

    pixel: {
      user_data: ['em', 'external_id', 'ct', 'zp', 'cn', 'fbp'],
      custom_data: ['source', 'version', 'pluginVersion', 'content_name', 'content_category', 'content_ids', 'content_type', 'contents']
    },

    capi: {
      user_data: ['em', 'external_id', 'ct', 'zp', 'country', 'fbp', 'client_ip_address', 'client_user_agent'],
      custom_data: ['content_name', 'content_category', 'content_ids', 'content_type', 'contents']
    }
  },

  AddToCart: {
    channels: ['pixel', 'capi'],

    pixel: {
      user_data: ['em', 'external_id', 'ct', 'zp', 'cn', 'fbp'],
      custom_data: ['source', 'version', 'pluginVersion', 'content_ids', 'content_type', 'content_name', 'value', 'currency', 'contents']
    },

    capi: {
      user_data: ['em', 'external_id', 'ct', 'zp', 'country', 'fbp', 'client_ip_address', 'client_user_agent'],
      custom_data: ['content_ids', 'content_type', 'content_name', 'value', 'currency', 'contents']
    }
  },

  InitiateCheckout: {
    channels: ['pixel', 'capi'],

    pixel: {
      user_data: ['em', 'external_id', 'ct', 'zp', 'cn', 'fbp'],
      custom_data: ['source', 'version', 'pluginVersion', 'content_ids', 'content_type', 'content_name', 'num_items', 'value', 'currency', 'contents', 'content_category']
    },

    capi: {
      user_data: ['em', 'external_id', 'ct', 'zp', 'country', 'fbp', 'client_ip_address', 'client_user_agent'],
      custom_data: ['content_ids', 'content_type', 'content_name', 'num_items', 'value', 'currency', 'contents', 'content_category']
    }
  },

  Purchase: {
    channels: ['capi'],

    capi: {
      user_data: ['em', 'external_id', 'ct', 'zp', 'country', 'fbp', 'client_ip_address', 'client_user_agent'],
      custom_data: ['content_ids', 'content_type', 'content_name', 'value', 'currency', 'contents', 'order_id']
    }
  },

  Search: {
    channels: ['pixel', 'capi'],

    pixel: {
      user_data: ['em', 'external_id', 'ct', 'zp', 'cn', 'fbp'],
      custom_data: ['source', 'version', 'pluginVersion', 'content_type', 'content_ids', 'contents', 'search_string', 'value', 'currency']
    },

    capi: {
      user_data: ['em', 'external_id', 'ct', 'zp', 'country', 'fbp', 'client_ip_address', 'client_user_agent'],
      custom_data: ['content_type', 'content_ids', 'contents', 'search_string', 'value', 'currency']
    }
  },

  Subscribe: {
    channels: ['pixel', 'capi'],

    pixel: {
      user_data: ['em', 'external_id', 'ct', 'zp', 'cn', 'fbp'],
      custom_data: ['source', 'version', 'pluginVersion', 'sign_up_fee', 'value', 'currency']
    },

    capi: {
      user_data: ['em', 'external_id', 'ct', 'zp', 'country', 'fbp', 'client_ip_address', 'client_user_agent'],
      custom_data: ['sign_up_fee', 'value', 'currency']
    }
  },

  Lead: {
    channels: ['pixel'],

    pixel: {
      user_data: ['em'],
      custom_data: ['source', 'version', 'pluginVersion']
    }
  }
};
