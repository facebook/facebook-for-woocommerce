/**
 * Consent Test - Validates pixel is blocked when filter returns false
 *
 * This test runs SEPARATELY after all other events tests because it
 * deactivates/reactivates the plugin which would break parallel tests.
 */

const { test, expect } = require('@playwright/test');
const TestSetup = require('./lib/TestSetup');
const EventValidator = require('./lib/EventValidator');

test('ViewContent - No Consent (pixel disabled)', async ({ page }, testInfo) => {
    const {
      deactivatePlugin,
      activatePlugin,
      installPixelBlockerMuPlugin,
      removePixelBlockerMuPlugin,
      reconnectAndVerify
    } = require('./test-helpers');

    // 1. Deactivate plugin
    await deactivatePlugin();

    // 2. Install mu-plugin (filter returns false)
    console.log('🔧 Installing pixel blocker mu-plugin...');
    await installPixelBlockerMuPlugin();

    // 3. Reactivate plugin (filter now in place before EventsTracker constructor runs)
    await activatePlugin();

    // 4. Run test - expect NO events
    console.log('🧪 Initializing test...');
    const { testId, pixelCapture } = await TestSetup.init(page, 'ViewContent', testInfo, true);

    console.log('📦 Navigating to product (expecting NO events)...');
    const eventPromise = pixelCapture.waitForEvent();
    await page.goto(process.env.TEST_PRODUCT_URL);
    await TestSetup.waitForPageReady(page);
    await eventPromise;

    console.log('🔍 Validating no events fired...');
    const validator = new EventValidator(testId, false, true);
    await validator.checkDebugLog();
    const result = await validator.validate('ViewContent', page);

    TestSetup.logResult('ViewContent (No Consent)', result);
    expect(result.passed).toBe(true);

    // 5. Validate cookies - _fbp and _fbc should NOT exist
    console.log('🍪 Checking cookies...');
    const cookies = await page.context().cookies();
    const fbp = cookies.find(c => c.name === '_fbp');
    const fbc = cookies.find(c => c.name === '_fbc');

    expect(fbp).toBeUndefined();
    expect(fbc).toBeUndefined();
    console.log('✅ No _fbp or _fbc cookies (correct)');

    // 6. Cleanup - remove mu-plugin and restore connection
    console.log('🧹 Cleaning up...');
    await removePixelBlockerMuPlugin();
    await reconnectAndVerify({ enablePixel: 'yes', enableS2S: 'yes' });
    console.log('✅ Consent test complete');
});
