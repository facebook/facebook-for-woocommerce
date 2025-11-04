/**
 * Facebook Events Test - Validates Pixel + CAPI deduplication
 */

const { test, expect } = require('@playwright/test');
const TestSetup = require('./lib/TestSetup');
const EventValidator = require('./lib/EventValidator');

test('PageView', async ({ page }) => {
    const { testId } = await TestSetup.init(page, 'pageview');

    await page.goto('/');
    await TestSetup.wait();

    const validator = new EventValidator(testId);
    const result = await validator.validate('PageView', page);

    TestSetup.logResult('PageView', result);
    expect(result.passed).toBe(true);
});

test('ViewContent', async ({ page }) => {
    // TODO needs to have an existing product
    const { testId } = await TestSetup.init(page, 'viewcontent');

    await page.goto('/product/testp/');
    await TestSetup.wait();

    const validator = new EventValidator(testId);
    const result = await validator.validate('ViewContent', page);

    TestSetup.logResult('ViewContent', result);
    expect(result.passed).toBe(true);
});

test('AddToCart', async ({ page }) => {
    // TODO needs to have an existing product
    const { testId } = await TestSetup.init(page, 'addtocart');

    await page.goto('/product/testp/');
    await page.click('.single_add_to_cart_button');
    await TestSetup.wait();

    const validator = new EventValidator(testId);
    const result = await validator.validate('AddToCart', page);

    TestSetup.logResult('AddToCart', result);
    expect(result.passed).toBe(true);
});

test('ViewCategory', async ({ page }) => {
    const { testId } = await TestSetup.init(page, 'viewcategory');

    await page.goto('/product-category/uncategorized/');
    await TestSetup.wait();

    const validator = new EventValidator(testId);
    const result = await validator.validate('ViewCategory', page);

    TestSetup.logResult('ViewCategory', result);
    expect(result.passed).toBe(true);
});
