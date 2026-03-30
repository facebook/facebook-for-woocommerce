const { test, expect } = require('@playwright/test');
const {
  TIMEOUTS,
  baseURL,
  loginToWordPress,
  logTestStart,
  logTestEnd,
  checkForPhpErrors,
  TestSetup,
  EventValidator,
} = require('./helpers/js');

/**
 * Theme compatibility checks are intentionally isolated.
 *
 * Switching themes mutates global site state, so this suite should run serially.
 * The goal here is not to re-test the event system itself, but to confirm that
 * the event system still works when the active theme is switched from Storefront
 * to a known WooCommerce Blocks theme.
 */
test.describe.serial('Theme compatibility checks', () => {
  const BASE_THEME_SLUG = 'storefront';
  const BLOCK_THEME_SLUG = process.env.E2E_TARGET_THEME_SLUG || 'twentytwentyfive';

  test.beforeAll(async ({ browser }) => {
    const page = await browser.newPage();

    try {
      await page.goto(`${baseURL}/wp-login.php`, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUTS.EXTRA_LONG,
      });

      await loginToWordPress(page);
      await activateThemeBySlug(page, BLOCK_THEME_SLUG);
    } finally {
      await page.close();
    }
  });

	test.beforeEach(async ({ page }, testInfo) => {
	logTestStart(testInfo);
	await page.setViewportSize({ width: 1280, height: 720 });

	// Admin context for admin checks
	await loginToWordPress(page);
	});
  test.afterAll(async ({ browser }) => {
    const page = await browser.newPage();

    try {
      await page.goto(`${baseURL}/wp-login.php`, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUTS.EXTRA_LONG,
      });

      await loginToWordPress(page);
      await activateThemeBySlug(page, BASE_THEME_SLUG);
    } catch (error) {
      console.warn(`⚠️ Could not restore ${BASE_THEME_SLUG}: ${error.message}`);
    } finally {
      await page.close();
    }
  });

  test('Block theme is active', async ({ page }) => {
    await page.goto(`${baseURL}/wp-admin/themes.php`, {
      waitUntil: 'domcontentloaded',
      timeout: TIMEOUTS.EXTRA_LONG,
    });

    await checkForPhpErrors(page);

    const activeTheme = page.locator(`.theme.active[data-slug="${BLOCK_THEME_SLUG}"]`);
    await expect(activeTheme).toHaveCount(1);
  });

  test('Shop and checkout stay healthy', async ({ page }) => {
    await page.goto(`${baseURL}/shop`, {
      waitUntil: 'domcontentloaded',
      timeout: TIMEOUTS.EXTRA_LONG,
    });
    await checkForPhpErrors(page);
    await expect(page.locator('body')).toBeVisible();

    await page.goto(`${baseURL}/checkout`, {
      waitUntil: 'domcontentloaded',
      timeout: TIMEOUTS.EXTRA_LONG,
    });
    await checkForPhpErrors(page);
    await expect(page.locator('body')).toBeVisible();
  });

  test('PageView still fires after theme switch', async ({ page }, testInfo) => {
    await runTrackedEventTest({
      page,
      testInfo,
      eventName: 'PageView',
      action: async ({ page }) => {
        await page.goto(`${baseURL}/`, {
          waitUntil: 'domcontentloaded',
          timeout: TIMEOUTS.EXTRA_LONG,
        });
      },
    });
  });

  test('ViewContent still fires after theme switch', async ({ page }, testInfo) => {
    const productUrl = process.env.TEST_PRODUCT_URL;
    if (!productUrl) {
      throw new Error('TEST_PRODUCT_URL is not set');
    }

    await runTrackedEventTest({
      page,
      testInfo,
      eventName: 'ViewContent',
      action: async ({ page }) => {
        await page.goto(productUrl, {
          waitUntil: 'domcontentloaded',
          timeout: TIMEOUTS.EXTRA_LONG,
        });
      },
    });
  });

  test('AddToCart still fires after theme switch', async ({ page }, testInfo) => {
    const productUrl = process.env.TEST_PRODUCT_URL;
    if (!productUrl) {
      throw new Error('TEST_PRODUCT_URL is not set');
    }

    await runTrackedEventTest({
      page,
      testInfo,
      eventName: 'AddToCart',
      action: async ({ page, pixelCapture }) => {
        await page.goto(productUrl, {
          waitUntil: 'domcontentloaded',
          timeout: TIMEOUTS.EXTRA_LONG,
        });
        await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

        const eventPromise = pixelCapture.waitForEvent();
        await page.click('.single_add_to_cart_button');
        await page.waitForLoadState('networkidle');
        await eventPromise;
      },
    });
  });

  test('InitiateCheckout still fires after theme switch', async ({ page }, testInfo) => {
    const productUrl = process.env.TEST_PRODUCT_URL;
    if (!productUrl) {
      throw new Error('TEST_PRODUCT_URL is not set');
    }

    await runTrackedEventTest({
      page,
      testInfo,
      eventName: 'InitiateCheckout',
      action: async ({ page, pixelCapture }) => {
        await page.goto(productUrl, {
          waitUntil: 'domcontentloaded',
          timeout: TIMEOUTS.EXTRA_LONG,
        });
        await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

        await page.click('.single_add_to_cart_button');
        await page.waitForTimeout(TIMEOUTS.SHORT);

        const eventPromise = pixelCapture.waitForEvent();
        await page.goto(`${baseURL}/checkout`, {
          waitUntil: 'domcontentloaded',
          timeout: TIMEOUTS.EXTRA_LONG,
        });
        await TestSetup.waitForPageReady(page);
        await eventPromise;
      },
    });
  });

  test('Purchase still fires after theme switch', async ({ page }, testInfo) => {
    const productUrl = process.env.TEST_PRODUCT_URL;
    if (!productUrl) {
      throw new Error('TEST_PRODUCT_URL is not set');
    }

    await runTrackedEventTest({
      page,
      testInfo,
      eventName: 'Purchase',
      action: async ({ page }) => {
        await page.goto(productUrl, {
          waitUntil: 'domcontentloaded',
          timeout: TIMEOUTS.EXTRA_LONG,
        });
        await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

        await page.click('.single_add_to_cart_button');
        await page.waitForTimeout(TIMEOUTS.SHORT);

        await page.goto(`${baseURL}/checkout`, {
          waitUntil: 'domcontentloaded',
          timeout: TIMEOUTS.EXTRA_LONG,
        });
        await TestSetup.waitForPageReady(page);

        await page.evaluate(() => window.scrollBy(0, 400));
        await page.waitForTimeout(TIMEOUTS.SHORT);

        await page.waitForSelector(
          '.wc-block-components-radio-control__option[for="radio-control-wc-payment-method-options-cod"]',
          {
            state: 'visible',
            timeout: TIMEOUTS.LONG,
          }
        );
        await page.click('label[for="radio-control-wc-payment-method-options-cod"]');
        await page.waitForTimeout(TIMEOUTS.INSTANT);

        await page.locator('.wc-block-components-checkout-place-order-button').scrollIntoViewIfNeeded();
        await page.click('.wc-block-components-checkout-place-order-button');

        await page.waitForURL('**/checkout/order-received/**', {
          timeout: TIMEOUTS.EXTRA_LONG,
        });
        await page.waitForTimeout(TIMEOUTS.NORMAL);
      },
    });
  });
});

async function runTrackedEventTest({ page, testInfo, eventName, action, expectFbc = false }) {
  // Switch to customer context only for event validation
  await TestSetup.login(page);

  const { testId, pixelCapture } = await TestSetup.init(page, eventName, testInfo);

  const eventPromise = pixelCapture.waitForEvent();
  await action({ page, pixelCapture, testId });

  await TestSetup.waitForPageReady(page);
  await eventPromise;

  const validator = new EventValidator(testId, expectFbc);
  await validator.checkDebugLog();
  const result = await validator.validate(eventName, page);

  TestSetup.logResult(`${eventName} (Theme Compatibility)`, result);
  expect(result.passed).toBe(true);
}

async function activateThemeBySlug(page, slug) {
  await page.goto(`${baseURL}/wp-admin/themes.php`, {
    waitUntil: 'domcontentloaded',
    timeout: TIMEOUTS.EXTRA_LONG,
  });

  const alreadyActive = page.locator(`.theme.active[data-slug="${slug}"]`);
  if (await alreadyActive.count() > 0) {
    console.log(`✅ Theme '${slug}' is already active`);
    return;
  }

  const themeCard = page.locator(`.theme[data-slug="${slug}"]`);
  await expect(themeCard).toBeVisible({ timeout: TIMEOUTS.LONG });

  await themeCard.hover();

  const activateLink = themeCard.locator('a.button.activate');
  await expect(activateLink).toBeVisible();
  await activateLink.click();

  await page.waitForLoadState('domcontentloaded');
  await expect(page.locator(`.theme.active[data-slug="${slug}"]`)).toBeVisible({
    timeout: TIMEOUTS.LONG,
  });

  console.log(`✅ Theme '${slug}' activated successfully`);
}
