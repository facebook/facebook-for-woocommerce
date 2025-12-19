/**
 * Facebook Events Test - Validates Pixel + CAPI events
 */

const { test, expect } = require('@playwright/test');
const { TIMEOUTS } = require('./time-constants');
const TestSetup = require('./lib/TestSetup');
const EventValidator = require('./lib/EventValidator');
const {
  cleanupProduct
} = require('./test-helpers');

test('PageView', async ({ page }, testInfo) => {
    const { testId, pixelCapture } = await TestSetup.init(page, 'PageView', testInfo);

    console.log(`   🌐 Navigating to homepage`);
    // Set up listener BEFORE triggering the action (prevents race condition)
    const eventPromise = pixelCapture.waitForEvent();
    await page.goto('/');
    await TestSetup.waitForPageReady(page);
    await eventPromise;

    const validator = new EventValidator(testId);
    await validator.checkDebugLog();
    const result = await validator.validate('PageView', page);

    TestSetup.logResult('PageView', result);
    expect(result.passed).toBe(true);
});

test('PageView with fbclid', async ({ page }, testInfo) => {
    const { testId, pixelCapture } = await TestSetup.init(page, 'PageView',  testInfo);

    console.log(`   🌐 Navigating to homepage`);
    // Set up listener BEFORE triggering the action (prevents race condition)
    const eventPromise = pixelCapture.waitForEvent();
    await page.goto(`/?fbclid=${process.env.TEST_FBCLID}`);
    await TestSetup.waitForPageReady(page);
    await eventPromise;

    const validator = new EventValidator(testId, true); // expects fbc
    await validator.checkDebugLog();
    const result = await validator.validate('PageView', page);

    TestSetup.logResult('PageView', result);
    expect(result.passed).toBe(true);
});

test('ViewContent', async ({ page }, testInfo) => {
    const { testId, pixelCapture } = await TestSetup.init(page, 'ViewContent',  testInfo);

    console.log(`   📦 Navigating to product page`);
    // Set up listener BEFORE triggering the action (prevents race condition)
    const eventPromise = pixelCapture.waitForEvent();
    await page.goto(process.env.TEST_PRODUCT_URL);
    await TestSetup.waitForPageReady(page);
    await eventPromise;

    const validator = new EventValidator(testId);
    await validator.checkDebugLog();
    const result = await validator.validate('ViewContent', page);

    TestSetup.logResult('ViewContent', result);
    expect(result.passed).toBe(true);
});

test('AddToCart', async ({ page }, testInfo) => {
    const { testId, pixelCapture } = await TestSetup.init(page, 'AddToCart',  testInfo);

    await page.goto(process.env.TEST_PRODUCT_URL);
    await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

    console.log(`   🛒 Clicking Add to Cart`);
    // Set up listener BEFORE triggering the action (prevents race condition)
    const eventPromise = pixelCapture.waitForEvent();
    await page.click('.single_add_to_cart_button');
    await page.waitForTimeout(TIMEOUTS.SHORT);
    await eventPromise;

    const validator = new EventValidator(testId);
    await validator.checkDebugLog();
    const result = await validator.validate('AddToCart', page);

    TestSetup.logResult('AddToCart', result);
    expect(result.passed).toBe(true);
});

test('ViewCategory', async ({ page }, testInfo) => {
    const { testId, pixelCapture } = await TestSetup.init(page, 'ViewCategory',  testInfo);

    console.log(`   📂 Navigating to category page`);
    // Set up listener BEFORE triggering the action (prevents race condition)
    const eventPromise = pixelCapture.waitForEvent();
    await page.goto(process.env.TEST_CATEGORY_URL);
    await TestSetup.waitForPageReady(page);
    await eventPromise;

    const validator = new EventValidator(testId);
    await validator.checkDebugLog();
    const result = await validator.validate('ViewCategory', page);

    TestSetup.logResult('ViewCategory', result);
    expect(result.passed).toBe(true);
});

test('InitiateCheckout', async ({ page }, testInfo) => {
    const { testId, pixelCapture } = await TestSetup.init(page, 'InitiateCheckout',  testInfo);

    await page.goto(process.env.TEST_PRODUCT_URL);
    await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

    console.log(`   🛒 Adding product to cart`);
    await page.click('.single_add_to_cart_button');
    await page.waitForTimeout(TIMEOUTS.SHORT);

    console.log(`   💳 Navigating to checkout`);
    // Set up listener BEFORE triggering the action (prevents race condition)
    const eventPromise = pixelCapture.waitForEvent();
    await page.goto('/checkout');
    await TestSetup.waitForPageReady(page);
    await eventPromise;

    const validator = new EventValidator(testId);
    await validator.checkDebugLog();
    const result = await validator.validate('InitiateCheckout', page);

    TestSetup.logResult('InitiateCheckout', result);
    expect(result.passed).toBe(true);
});

test('Purchase', async ({ page }, testInfo) => {
  // maybe clear cart before the test?
    const { testId, pixelCapture } = await TestSetup.init(page, 'Purchase',  testInfo);

    await page.goto(process.env.TEST_PRODUCT_URL);
    await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

    console.log(`   🛒 Adding product to cart`);
    await page.click('.single_add_to_cart_button');
    await page.waitForTimeout(TIMEOUTS.SHORT);

    console.log(`   💳 Navigating to checkout`);
    await page.goto('/checkout');
    await TestSetup.waitForPageReady(page);

    // Scroll down to see checkout form in video
    await page.evaluate(() => window.scrollBy(0, 400));
    await page.waitForTimeout(TIMEOUTS.SHORT);

    console.log(`   ℹ️ Using saved billing address (no need to fill)`);
    // Customer already has billing address saved from workflow setup
    // WooCommerce automatically uses it - no need to edit or fill anything

    console.log(`   💰 Selecting Cash on Delivery`);
    // Wait for the payment methods section to load, then click the label (the input is hidden by CSS)
    await page.waitForSelector('.wc-block-components-radio-control__option[for="radio-control-wc-payment-method-options-cod"]', { state: 'visible', timeout: TIMEOUTS.LONG });
    await page.click('label[for="radio-control-wc-payment-method-options-cod"]');
    await page.waitForTimeout(TIMEOUTS.INSTANT);

    console.log(`   ✅ Placing order`);
    // Scroll to place order button to ensure it's visible
    await page.locator('.wc-block-components-checkout-place-order-button').scrollIntoViewIfNeeded();

    // Purchase event is CAPI-only (server-side) for now
    await page.click('.wc-block-components-checkout-place-order-button');

    // Wait for order processing and redirect (can take time with payment processing)
    console.log(`   ⏳ Waiting for order to process...`);
    await page.waitForURL('**/checkout/order-received/**', { timeout: TIMEOUTS.EXTRA_LONG });


    await page.waitForTimeout(TIMEOUTS.NORMAL); // Give time for order to process and CAPI event to fire

    const validator = new EventValidator(testId);
    await validator.checkDebugLog();
    const result = await validator.validate('Purchase', page);

    TestSetup.logResult('Purchase', result);
    expect(result.passed).toBe(true);
});

test('Purchase - Multiple Place Order Clicks', async ({ page }, testInfo) => {
    const { testId, pixelCapture } = await TestSetup.init(page, 'Purchase',  testInfo);

    await page.goto(process.env.TEST_PRODUCT_URL);
    await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

    console.log(`   🛒 Adding product to cart`);
    await page.click('.single_add_to_cart_button');
    await page.waitForTimeout(TIMEOUTS.SHORT);

    console.log(`   💳 Navigating to checkout`);
    await page.goto('/checkout');
    await TestSetup.waitForPageReady(page);

    // Scroll down to see checkout form in video
    await page.evaluate(() => window.scrollBy(0, 400));
    await page.waitForTimeout(TIMEOUTS.SHORT);

    console.log(`   ℹ️ Using saved billing address (no need to fill)`);
    // Customer already has billing address saved from workflow setup
    // WooCommerce automatically uses it - no need to edit or fill anything

    console.log(`   💰 Selecting Cash on Delivery`);
    await page.waitForSelector('.wc-block-components-radio-control__option[for="radio-control-wc-payment-method-options-cod"]', { state: 'visible', timeout: TIMEOUTS.LONG });
    await page.click('label[for="radio-control-wc-payment-method-options-cod"]');
    await page.waitForTimeout(TIMEOUTS.INSTANT);

    console.log(`   ✅ Clicking Place Order button multiple times (testing deduplication)`);
    await page.locator('.wc-block-components-checkout-place-order-button').scrollIntoViewIfNeeded();

    // Click Place Order button 3 times rapidly
    const placeOrderButton = page.locator('.wc-block-components-checkout-place-order-button');
    console.log(`   🔄 Click #1`);
    await placeOrderButton.click();
    await page.waitForTimeout(100);
    console.log(`   🔄 Click #2`);
    await placeOrderButton.click({force: true}).catch(() => {}); // Might fail if already processing
    await page.waitForTimeout(100);
    console.log(`   🔄 Click #3`);
    await placeOrderButton.click({force: true}).catch(() => {}); // Might fail if already processing

    console.log(`   ⏳ Waiting for order to process...`);
    await page.waitForURL('**/checkout/order-received/**', { timeout: TIMEOUTS.EXTRA_LONG });
    await page.waitForTimeout(TIMEOUTS.NORMAL);

    const validator = new EventValidator(testId);
    await validator.checkDebugLog();
    const result = await validator.validate('Purchase', page);

    // Should still only have 1 Purchase event despite multiple clicks
    TestSetup.logResult('Purchase (Deduplication)', result);
    expect(result.passed).toBe(true);
});

test('Search', async ({ page }, testInfo) => {
    const { testId, pixelCapture } = await TestSetup.init(page, 'Search',  testInfo);

    console.log(`   🏠 Navigating to homepage`);
    await page.goto('/');
    await TestSetup.waitForPageReady(page);

    console.log(`   🔍 Typing search query in search box`);
    const searchInput = page.locator('.search-field').first();
    await searchInput.fill('test');

    console.log(`   🔎 Submitting search form`);
    // Set up listener BEFORE triggering the action
    const eventPromise = pixelCapture.waitForEvent();

    await searchInput.press('Enter');
    await TestSetup.waitForPageReady(page);
    await eventPromise;

    const validator = new EventValidator(testId);
    await validator.checkDebugLog();
    const result = await validator.validate('Search', page);

    TestSetup.logResult('Search', result);
    expect(result.passed).toBe(true);
});

test('Search - No Results', async ({ page }, testInfo) => {
    const { testId, pixelCapture } = await TestSetup.init(page, 'Search', testInfo, true); // expectZeroEvents=true

    console.log(`   🏠 Navigating to homepage`);
    await page.goto('/');
    await TestSetup.waitForPageReady(page);

    // Generate random string that won't match any products
    const randomString = 'xyzabc239nfjsdn' + Date.now();
    console.log(`   🔍 Typing random search query: ${randomString}`);

    const searchInput = page.locator('.search-field').first();
    await searchInput.fill(randomString);

    console.log(`   🔎 Submitting search form (expecting no events)`);
    const eventPromise = pixelCapture.waitForEvent(); // Will succeed if no event fires

    await searchInput.press('Enter');
    await TestSetup.waitForPageReady(page);
    await eventPromise;

    const validator = new EventValidator(testId, false, true); // expectZeroEvents=true
    await validator.checkDebugLog();
    const result = await validator.validate('Search', page);

    TestSetup.logResult('Search (No Results)', result);
    expect(result.passed).toBe(true);
});

// Lead event is not tested as it needs an SMTP server etc
// NOTE: Subscribe test is skipped because it requires WooCommerce Paid Subscriptions
// Free alternatives (YITH, Subscriptio) use different APIs incompatible with facebook-for-woocommerce
// The plugin specifically checks for wcs_get_subscriptions_for_order() which only exists in the official plugin

// Cleanup is handled by GitHub workflow after all tests complete
// This ensures product exists for all tests even if some fail

// IMPORTANT: This test MUST run last and serially (not in parallel) because it uninstalls the plugin
test.describe.serial('Consent Tests (runs last)', () => {
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
});
