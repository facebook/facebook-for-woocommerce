/**
 * Test Configuration
 *
 * Environment variables can be set via:
 * - Command line: WORDPRESS_URL=http://... WP_USERNAME=admin WP_PASSWORD=admin npm run test:e2e
 */

const WORDPRESS_URL = process.env.WORDPRESS_URL || || 'http://localhost:8080';
const WP_USERNAME = process.env.WP_USERNAME || 'admin';
const WP_PASSWORD = process.env.WP_PASSWORD || 'admin';
const WORDPRESS_PATH = '/tmp/wordpress/wp-load.php';
module.exports = {
    WORDPRESS_URL,
    WP_USERNAME,
    WP_PASSWORD,
    WORDPRESS_PATH
};
