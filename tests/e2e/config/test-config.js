/**
 * Test Configuration
 *
 * Environment variables can be set via:
 * - Command line: WORDPRESS_URL=http://... WP_USERNAME=admin WP_PASSWORD=admin npm run test:e2e
 * - GitHub Actions workflow (configured in .github/workflows)
 */

module.exports = {
    // WordPress URLs and paths
    WORDPRESS_URL: process.env.WORDPRESS_URL || 'http://localhost:8080',
    WORDPRESS_PATH: process.env.WORDPRESS_PATH || '/tmp/wordpress',

    // WordPress credentials
    WP_ADMIN_USERNAME: process.env.WP_ADMIN_USERNAME || 'admin',
    WP_ADMIN_PASSWORD: process.env.WP_ADMIN_PASSWORD || 'admin',
    WP_CUSTOMER_USERNAME: process.env.WP_CUSTOMER_USERNAME || 'customer',
    WP_CUSTOMER_PASSWORD: process.env.WP_CUSTOMER_PASSWORD || 'Password@54321',

    // Test product/category URLs (relative to WORDPRESS_URL)
    // TEST_PRODUCT_URL: '/product/testp/',
    TEST_CATEGORY_URL: '/product-category/uncategorized/',

    // Test event identifier cookie name
    TEST_COOKIE_NAME: 'facebook_test_id',

    // Timeouts (in milliseconds)
    PIXEL_EVENT_TIMEOUT: 15000,
    PAGE_LOAD_TIMEOUT: 120000,
};
