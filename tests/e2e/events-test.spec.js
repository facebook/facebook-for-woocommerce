/**
 * Facebook Events Test - Validates Pixel + CAPI events
 */

const { test, expect } = require('@playwright/test');
const TestSetup = require('./lib/TestSetup');
const EventValidator = require('./lib/EventValidator');
const {
  cleanupProduct
} = require('./test-helpers');

test('PageView', async ({ page }) => {
    const { testId, pixelCapture } = await TestSetup.init(page, 'PageView');

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

test('PageView with fbclid', async ({ page }) => {
    const { testId, pixelCapture } = await TestSetup.init(page, 'PageView');

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

test('ViewContent', async ({ page }) => {
    const { testId, pixelCapture } = await TestSetup.init(page, 'ViewContent');

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

test('AddToCart', async ({ page }) => {
    const { testId, pixelCapture } = await TestSetup.init(page, 'AddToCart');

    await page.goto(process.env.TEST_PRODUCT_URL);
    await TestSetup.waitForPageReady(page, 500);

    console.log(`   🛒 Clicking Add to Cart`);
    // Set up listener BEFORE triggering the action (prevents race condition)
    const eventPromise = pixelCapture.waitForEvent();
    await page.click('.single_add_to_cart_button');
    await page.waitForTimeout(1000);
    await eventPromise;

    const validator = new EventValidator(testId);
    await validator.checkDebugLog();
    const result = await validator.validate('AddToCart', page);

    TestSetup.logResult('AddToCart', result);
    expect(result.passed).toBe(true);
});

test('ViewCategory', async ({ page }) => {
    const { testId, pixelCapture } = await TestSetup.init(page, 'ViewCategory');

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

test('InitiateCheckout', async ({ page }) => {
    const { testId, pixelCapture } = await TestSetup.init(page, 'InitiateCheckout');

    await page.goto(process.env.TEST_PRODUCT_URL);
    await TestSetup.waitForPageReady(page, 500);

    console.log(`   🛒 Adding product to cart`);
    await page.click('.single_add_to_cart_button');
    await page.waitForTimeout(1000);

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

test('Purchase', async ({ page }) => {
  // maybe clear cart before the test?
    const { testId, pixelCapture } = await TestSetup.init(page, 'Purchase');

    await page.goto(process.env.TEST_PRODUCT_URL);
    await TestSetup.waitForPageReady(page, 500);

    console.log(`   🛒 Adding product to cart`);
    await page.click('.single_add_to_cart_button');
    await page.waitForTimeout(1000);

    console.log(`   💳 Navigating to checkout`);
    await page.goto('/checkout');
    await TestSetup.waitForPageReady(page);

    console.log(`   📝 Filling checkout form`);

    // Check if Edit button exists (address already saved) and click it
    const editButton = page.locator('.wc-block-components-address-card__edit[aria-controls="shipping"]');
    if (await editButton.isVisible()) {
        console.log(`   ✏️ Clicking Edit button to reveal address fields`);
        await editButton.click();
        await page.waitForTimeout(500);
    }

    await page.fill('#email', 'test@example.com');
    await page.waitForSelector('#shipping-country', { state: 'visible', timeout: 5000 });
    await page.selectOption('#shipping-country', 'US');
    await page.fill('#shipping-first_name', 'Test');
    await page.fill('#shipping-last_name', 'User');
    await page.fill('#shipping-address_1', '123 Main Street');
    await page.fill('#shipping-city', 'Los Angeles');
    await page.waitForSelector('#shipping-state', { state: 'visible', timeout: 5000 });
    await page.selectOption('#shipping-state', 'CA');
    await page.waitForTimeout(1000); // Wait for WooCommerce to validate state selection
    await page.fill('#shipping-postcode', '90210');
    await page.fill('#shipping-phone', '3105551234');

    console.log(`   🚚 Waiting for checkout to process address`);
    await page.waitForTimeout(2000); // Give WooCommerce time to validate address and load shipping

    console.log(`   💰 Selecting Cash on Delivery`);
    // Wait for the payment methods section to load, then click the label (the input is hidden by CSS)
    await page.waitForSelector('.wc-block-components-radio-control__option[for="radio-control-wc-payment-method-options-cod"]', { state: 'visible', timeout: 10000 });
    await page.click('label[for="radio-control-wc-payment-method-options-cod"]');
    await page.waitForTimeout(500);

    console.log(`   ✅ Placing order`);
    // Scroll to place order button to ensure it's visible
    await page.locator('.wc-block-components-checkout-place-order-button').scrollIntoViewIfNeeded();

    // Purchase event is CAPI-only (server-side) for now
    await page.click('.wc-block-components-checkout-place-order-button');

    // Wait for order processing and redirect (can take time with payment processing)
    console.log(`   ⏳ Waiting for order to process...`);
    await page.waitForURL('**/checkout/order-received/**', { timeout: 30000 });


    await page.waitForTimeout(3000); // Give time for order to process and CAPI event to fire

    const validator = new EventValidator(testId);
    await validator.checkDebugLog();
    const result = await validator.validate('Purchase', page);

    TestSetup.logResult('Purchase', result);
    expect(result.passed).toBe(true);
});

// Cleanup is handled by GitHub workflow after all tests complete
// This ensures product exists for all tests even if some fail
