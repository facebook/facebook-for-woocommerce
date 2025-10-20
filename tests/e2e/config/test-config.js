/**
 * E2E Test Configuration
 * 
 * Centralized configuration for all e2e tests.
 * Uses environment variables with fallback defaults.
 */

const path = require('path');

// Get project root (where package.json is)
const PROJECT_ROOT = path.resolve(__dirname, '../../..');

// WordPress site configuration
const WORDPRESS_URL = process.env.WORDPRESS_URL || 'http://wooc-local-test-sitecom.local';
const WP_USERNAME = process.env.WP_USERNAME || 'madhav';
const WP_PASSWORD = process.env.WP_PASSWORD || 'madhav-wooc';

// Parse WordPress path from URL
// For local sites, the path is typically in format: /Users/username/Local Sites/sitename/app/public
const getWordPressPath = () => {
    if (process.env.WORDPRESS_PATH) {
        return process.env.WORDPRESS_PATH;
    }
    
    // Default for Local by Flywheel sites
    const siteName = WORDPRESS_URL.replace('http://', '').replace('https://', '').replace('.local', '');
    return path.join(path.dirname(PROJECT_ROOT), 'public');
};

const WORDPRESS_PATH = getWordPressPath();

// Derived paths
const WP_CONTENT_PATH = path.join(WORDPRESS_PATH, 'wp-content');
const DEBUG_LOG_PATH = path.join(WP_CONTENT_PATH, 'debug.log');
const PLUGINS_PATH = path.join(WP_CONTENT_PATH, 'plugins');
const MU_PLUGINS_PATH = path.join(WP_CONTENT_PATH, 'mu-plugins');

// Facebook plugin paths
const FB_PLUGIN_PATH = path.join(PLUGINS_PATH, 'facebook-for-woocommerce');
const FB_API_PATH = path.join(FB_PLUGIN_PATH, 'includes', 'API.php');

// Test configuration
const TEST_TIMEOUT = 300000; // 5 minutes
const ACTION_TIMEOUT = 180000; // 3 minutes

// Parse domain from URL
const getDomain = () => {
    return WORDPRESS_URL.replace('http://', '').replace('https://', '').split(':')[0];
};

const SITE_DOMAIN = getDomain();

module.exports = {
    // WordPress Configuration
    WORDPRESS_URL,
    WORDPRESS_PATH,
    WP_USERNAME,
    WP_PASSWORD,
    SITE_DOMAIN,
    
    // Paths
    WP_CONTENT_PATH,
    DEBUG_LOG_PATH,
    PLUGINS_PATH,
    MU_PLUGINS_PATH,
    FB_PLUGIN_PATH,
    FB_API_PATH,
    PROJECT_ROOT,
    
    // Timeouts
    TEST_TIMEOUT,
    ACTION_TIMEOUT,
    
    // Helper functions
    getWordPressPath,
    getDomain,
};
