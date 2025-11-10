/**
 * Test Configuration
 *
 * Environment variables can be set via:
 * - Command line: WORDPRESS_URL=http://... WP_USERNAME=admin WP_PASSWORD=admin npm run test:e2e
 */

const WORDPRESS_URL = process.env.WORDPRESS_URL || 'http://localhost:8080';
const WP_ADMIN_USERNAME = process.env.WP_ADMIN_USERNAME || 'admin';
const WP_ADMIN_PASSWORD = process.env.WP_ADMIN_PASSWORD || 'admin';
const WP_CUSTOMER_USERNAME = process.env.WP_CUSTOMER_USERNAME || 'customer';
const WP_CUSTOMER_PASSWORD = process.env.WP_CUSTOMER_PASSWORD || 'Password@54321';
const WORDPRESS_PATH = '/tmp/wordpress/wp-load.php';
module.exports = {
    WORDPRESS_URL,
    WP_ADMIN_USERNAME,
    WP_ADMIN_PASSWORD,
    WP_CUSTOMER_USERNAME,
    WP_CUSTOMER_PASSWORD,
    WORDPRESS_PATH
};
