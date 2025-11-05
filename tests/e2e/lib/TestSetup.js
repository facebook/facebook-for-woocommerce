/**
 * TestSetup - Test initialization and utilities
 */

const PixelCapture = require('./PixelCapture');
const config = require('../config/test-config');
// After login, before starting pixel capture

class TestSetup {
    static async init(page, eventName) {
        const testName = eventName.toLowerCase();
        const testId = `${testName}-${Date.now()}`;

        console.log(`\n Testing: ${eventName.toUpperCase()}`);

        // Login first
        await this.login(page);

        // Set cookies for CAPI logging
        await page.context().addCookies([
            {
                name: 'facebook_test_id',
                value: testId,
                url: config.WORDPRESS_URL
            }
        ]);

        // await this.verifyPluginActive(page);

        // Start Pixel capture
        const pixelCapture = new PixelCapture(page, testId, eventName);
        await pixelCapture.start();

        return { testId, pixelCapture };
    }

    static async login(page) {
        await page.goto('/wp-login.php');

        // Check if already logged in
        const loginForm = await page.locator('#loginform').count();
        if (loginForm === 0) {
            // await page.goto('/');
            return;
        }

        // Login
        await page.fill('#user_login', config.WP_USERNAME);
        await page.fill('#user_pass', config.WP_PASSWORD);
        await page.click('#wp-submit');
        await page.waitForLoadState('networkidle');

        console.log('  ✅ Logged In');
    }

    static async wait(ms = 2000) {
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
    // static async verifyPluginActive(page) {
    // // Check if pixel script is in HTML (on current page)
    // const pixelScript = await page.evaluate(() => {
    //     return document.documentElement.innerHTML.includes('facebook.com/tr');
    // });

    // console.log(`  Plugin Active: ${pixelScript ? '✅ YES' : '❌ NO - Pixel script not found!'}`);

    // if (!pixelScript) {
    //     throw new Error('Facebook for WooCommerce plugin is not active or configured');
    //      }
    // }

}

module.exports = TestSetup;

// TODO: create one product before all tests . delete it after all tests
