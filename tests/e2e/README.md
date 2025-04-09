# Facebook for WooCommerce E2E Tests

This directory contains end-to-end tests for the Facebook for WooCommerce plugin, focusing on product creation, attribute management, and Facebook synchronization.

## Test Coverage

The tests cover:
- Creating simple products with attributes and synchronizing to Facebook
- Creating variable products with attributes and synchronizing to Facebook
- Fetching product data from Facebook
- Updating product attributes and synchronizing changes
- Toggling product sync status (enable/disable)
- Error handling for Facebook API failures

## Running Tests

### Prerequisites
- Node.js (v14 or newer)
- npm or yarn

### Setup

1. Navigate to the e2e test directory:
```bash
cd wp-content/plugins/facebook-for-woocommerce/tests/e2e
```

2. Install dependencies:
```bash
npm install
```

### Run Tests

Run all tests:
```bash
npm test
```

Run tests in watch mode:
```bash
npm run test:watch
```

Generate coverage report:
```bash
npm run test:coverage
```

## Adding New Tests

To add new tests:
1. Create a new test file in the `tests` directory with a `.test.js` extension
2. Follow the existing test patterns for mocking and assertions
3. Run the tests to verify they work correctly

## Test Structure

The tests use Jest's mocking capabilities to simulate interactions with:
- WordPress REST API (via wp.apiFetch)
- WooCommerce product management
- Facebook for WooCommerce synchronization functions

Each test follows a pattern of:
1. Setting up test data
2. Mocking API responses
3. Executing the functionality under test
4. Verifying the correct API calls were made
5. Verifying the expected responses/behaviors 