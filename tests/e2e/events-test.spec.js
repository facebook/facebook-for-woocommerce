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
    await page.waitForTimeout(TIMEOUTS.SHORT);
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

// Cleanup is handled by GitHub workflow after all tests complete
// This ensures product exists for all tests even if some fail
