/**
 * Consent Test - Validates pixel is blocked when filter returns false
 * 
 * This test runs SEPARATELY after all other events tests because it
 * uninstalls/reinstalls the plugin which would break parallel tests.
 */

const { test, expect } = require('@playwright/test');
const TestSetup = require('./lib/TestSetup');
const EventValidator = require('./lib/EventValidator');

test('ViewContent - No Consent (pixel disabled)', async ({ page }, testInfo) => {
    const {
      uninstallPlugin,
      installPlugin,
      installPixelBlockerMuPlugin,
      removePixelBlockerMuPlugin,
      reconnectAndVerify
    } = require('./test-helpers');

    // 1. Uninstall plugin
    await uninstallPlugin('facebook-for-woocommerce');

    // 2. Install mu-plugin (filter returns false)
    await installPixelBlockerMuPlugin();

    // 3. Reinstall plugin
    await installPlugin('facebook-for-woocommerce');
    await reconnectAndVerify({ enablePixel: 'no', enableS2S: 'no' });

    // 4. Run test - expect NO events
    const { testId, pixelCapture } = await TestSetup.init(page, 'ViewContent', testInfo, true);

    console.log(`   📦 Navigating to product (expecting NO events)`);
    const eventPromise = pixelCapture.waitForEvent();
    await page.goto(process.env.TEST_PRODUCT_URL);
    await TestSetup.waitForPageReady(page);
    await eventPromise;

    const validator = new EventValidator(testId, false, true);
    await validator.checkDebugLog();
    const result = await validator.validate('ViewContent', page);

    TestSetup.logResult('ViewContent (No Consent)', result);
    expect(result.passed).toBe(true);

    // 5. Validate cookies - _fbp and _fbc should NOT exist
    console.log(`   🍪 Checking cookies...`);
    const cookies = await page.context().cookies();
    const fbp = cookies.find(c => c.name === '_fbp');
    const fbc = cookies.find(c => c.name === '_fbc');

    expect(fbp).toBeUndefined();
    expect(fbc).toBeUndefined();
    console.log(`   ✅ No _fbp or _fbc cookies (correct)`);

    // 6. Cleanup
    await removePixelBlockerMuPlugin();
    await reconnectAndVerify({ enablePixel: 'yes', enableS2S: 'yes' });
});
