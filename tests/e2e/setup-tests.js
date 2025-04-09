// Mock WordPress dependencies
global.wp = {
  apiFetch: jest.fn(),
  hooks: {
    addFilter: jest.fn(),
    addAction: jest.fn(),
    doAction: jest.fn()
  },
  data: {
    select: jest.fn(),
    dispatch: jest.fn()
  }
};

// Mock WooCommerce global object
global.wc = {
  wcSettings: {
    adminUrl: 'http://localhost/wp-admin',
    siteUrl: 'http://localhost',
    wcAdminSettings: {
      productEditor: {
        defaultSettings: {},
      },
    },
  },
};

// Mock Facebook for WooCommerce specific globals
global.facebookForWooCommerce = {
  sync: {
    syncProduct: jest.fn(),
    getProductStatus: jest.fn()
  },
  api: {
    sendProductToFacebook: jest.fn().mockResolvedValue({ success: true }),
    getProductFromFacebook: jest.fn()
  }
};

// Reset all mocks before each test
beforeEach(() => {
  jest.clearAllMocks();
}); 