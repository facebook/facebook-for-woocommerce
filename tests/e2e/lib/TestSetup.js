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

        // Dump cookies after login
        await this.dumpCookies(page, 'After Login');

        // Set cookies for CAPI logging
        await page.context().addCookies([
            {
                name: 'facebook_test_id',
                value: testId,
                url: config.WORDPRESS_URL
            }
        ]);

        // Dump cookies after setting test cookie
        await this.dumpCookies(page, 'After Setting Test Cookie');

        // await this.verifyPluginActive(page);

        // Initialize Pixel capture (will start when waitForEvent is called)
        const pixelCapture = new PixelCapture(page, testId, eventName);
        // await pixelCapture.start();

        return { testId, pixelCapture };
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
        await page.fill('#user_login', config.WP_CUSTOMER_USERNAME);
        await page.fill('#user_pass', config.WP_CUSTOMER_PASSWORD);
        await page.click('#wp-submit');
        await page.waitForLoadState('networkidle');

        console.log('  ✅ Logged In as customer (non-admin)');
    }

    /**
     * Dump all cookies for debugging
     */
    static async dumpCookies(page, context = 'Current') {
        try {
            const cookies = await page.context().cookies();
            console.log(`\n🍪 Cookies [${context}]: ${cookies.length} total`);

            // Filter for Facebook-related cookies
            const fbCookies = cookies.filter(c =>
                c.name.includes('_fb') ||
                c.name.includes('fb') ||
                c.domain.includes('facebook')
            );

            if (fbCookies.length > 0) {
                console.log(`   Facebook cookies: ${fbCookies.length}`);
                fbCookies.forEach(c => {
                    console.log(`   - ${c.name}: ${c.value.substring(0, 50)}${c.value.length > 50 ? '...' : ''} (domain: ${c.domain})`);
                });
            } else {
                console.log(`   ⚠️  No Facebook cookies found`);
            }

            // Show test cookie
            const testCookie = cookies.find(c => c.name === 'facebook_test_id');
            if (testCookie) {
                console.log(`   ✅ Test cookie: ${testCookie.value}`);
            } else {
                console.log(`   ⚠️  No test cookie found`);
            }

            // Show WordPress auth cookies
            const wpCookies = cookies.filter(c => 
                c.name.includes('wordpress') || 
                c.name.includes('wp-') || 
                c.name === 'wp_lang'
            );
            if (wpCookies.length > 0) {
                console.log(`   WordPress cookies: ${wpCookies.length} (${wpCookies.map(c => c.name).join(', ')})`);
            } else {
                console.log(`   ⚠️  No WordPress cookies found`);
            }

        } catch (err) {
            console.error(`   ❌ Error dumping cookies: ${err.message}`);
        }
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
