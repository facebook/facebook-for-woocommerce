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

// Cleanup is handled by GitHub workflow after all tests complete
// This ensures product exists for all tests even if some test fails in between
