/**
 * TestSetup - Test initialization and utilities
 */

const { TIMEOUTS } = require('../time-constants');
const PixelCapture = require('./PixelCapture');

class TestSetup {
    static async init(page, eventName) {
        const testName = eventName.toLowerCase();
        const testId = `${testName}-${Date.now()}`;

        console.log(`\n Testing: ${eventName.toUpperCase()}`);

        await this.login(page);

        // Set test cookie for CAPI logging
        await page.context().addCookies([{
            name: process.env.FB_E2E_TEST_COOKIE_NAME,
            value: testId,
            url: process.env.WORDPRESS_URL
        }]);

        const pixelCapture = new PixelCapture(page, testId, eventName);
        this.setupBrowserLogging(page);

        return { testId, pixelCapture };
    }

    /**
     * Setup browser console and error logging (filters out noise)
     */
    static setupBrowserLogging(page) {
        page.on('console', msg => {
            const text = msg.text();
            if (!text.includes('traffic permission') && !text.includes('JQMIGRATE')) {
                console.log(`   [Browser ${msg.type()}] ${text}`);
            }
        });
        page.on('pageerror', err => console.error(`   [Browser Error] ${err.message}`));
    }

    /**
     * Wait for page to be fully loaded and ready
     */
    static async waitForPageReady(page, timeout = TIMEOUTS.SHORT) {
        await page.waitForLoadState('networkidle');
        await page.waitForFunction(() => typeof jQuery !== 'undefined' && jQuery.isReady);
        await page.waitForTimeout(timeout);
    }

    /**
     * Login as customer (non-admin) user
     * Pixel tracking is disabled for admin users, so we need to test as a customer
     */
    static async login(page) {
        await page.goto('/wp-login.php');

        // Check if already logged in
        const loginForm = await page.locator('#loginform').count();
        if (loginForm === 0) {
            // await page.goto('/');
            return;
        }

        // Login as customer (not admin!) because pixel excludes admin users
        await page.fill('#user_login', process.env.WP_CUSTOMER_USERNAME );
        await page.fill('#user_pass', process.env.WP_CUSTOMER_PASSWORD);
        await page.click('#wp-submit');
        await page.waitForLoadState('networkidle');

        console.log('  ✅ Logged In as customer (non-admin)');
    }

    static async wait(ms = TIMEOUTS.NORMAL) {
        await new Promise(resolve => setTimeout(resolve, ms));
    }

    static logResult(eventName, result) {
        if (result.passed) {
            console.log(`\n✅ ${eventName}: PASSED\n`);
        } else {
            console.log(`\n❌ ${eventName}: FAILED`);
            console.log(`\nErrors:`);
            result.errors.forEach(err => console.log(`  - ${err}`));

            // Dump event data on failure
            console.log(`\n📊 Event Data:`);
            console.log(`\nPixel Event:`, JSON.stringify(result.pixel, null, 2));
            console.log(`\nCAPI Event:`, JSON.stringify(result.capi, null, 2));
            console.log('\n');
        }
    }
}

module.exports = TestSetup;

// TODO: create one product before all tests . delete it after all tests
