/**
 * Facebook Events Test - Validates Pixel + CAPI deduplication
 */

const { test, expect } = require('@playwright/test');
const TestSetup = require('./lib/TestSetup');
const EventValidator = require('./lib/EventValidator');

test('PageView', async ({ page }) => {
    const { testId, pixelCapture } = await TestSetup.init(page, 'PageView');

    // Start navigation and wait for Pixel event
    await Promise.all([
        pixelCapture.waitForEvent(), // Wait for the event
        page.goto('/')                // Trigger the event
    ]);

    const validator = new EventValidator(testId);
    const result = await validator.validate('PageView', page);

    TestSetup.logResult('PageView', result);
    expect(result.passed).toBe(true);
});

test('ViewContent', async ({ page }) => {
    // TODO needs to have an existing product
    const { testId, pixelCapture } = await TestSetup.init(page, 'ViewContent');

    await Promise.all([
        pixelCapture.waitForEvent(),
        page.goto('/product/testp/')
    ]);

    const validator = new EventValidator(testId);
    const result = await validator.validate('ViewContent', page);

    TestSetup.logResult('ViewContent', result);
    expect(result.passed).toBe(true);
});

test('AddToCart', async ({ page }) => {
    // TODO needs to have an existing product
    const { testId, pixelCapture } = await TestSetup.init(page, 'AddToCart');

    await page.goto('/product/testp/');

    await Promise.all([
        pixelCapture.waitForEvent(),
        page.click('.single_add_to_cart_button')
    ]);

    const validator = new EventValidator(testId);
    const result = await validator.validate('AddToCart', page);

    TestSetup.logResult('AddToCart', result);
    expect(result.passed).toBe(true);
});

test('ViewCategory', async ({ page }) => {
    const { testId, pixelCapture } = await TestSetup.init(page, 'ViewCategory');

    await Promise.all([
        pixelCapture.waitForEvent(),
        page.goto('/product-category/uncategorized/')
    ]);
    const validator = new EventValidator(testId);
    const result = await validator.validate('ViewCategory', page);

    TestSetup.logResult('ViewCategory', result);
    expect(result.passed).toBe(true);
});
