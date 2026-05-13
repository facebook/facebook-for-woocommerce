/**
 * Facebook Events Test - Validates Pixel + CAPI events
 */

const { test, expect } = require('@playwright/test');
const {
  TIMEOUTS,
  TestSetup,
  EventValidator,
  cleanupProduct,
  cleanupProducts,
  deactivatePlugin,
  activatePlugin,
  installPixelBlockerMuPlugin,
  removePixelBlockerMuPlugin,
  installJsErrorSimulatorMuPlugin,
  removeJsErrorSimulatorMuPlugin,
  reconnectAndVerify,
  getVisibleSearchInput,
  createVariableProductEventFixture,
  createGroupedProductEventFixture,
  selectVariationByLabel,
  setGroupedProductQuantity,
  loadCapturedEvents,
  getLatestEvent,
  asArray,
  assertEventContainsRetailerId,
  ignoreKnownPurchaseUserDataGap,
  getCartItemsViaStoreApi,
  clearCart,
  completeCheckoutFromCart,
  triggerAjaxAddToCartFromShop,
  submitSearch,
  getActiveThemeStatus,
  switchThemeBySlug,
  acquireThemeLock,
  releaseThemeLock
} = require('./helpers/js');

const STOREFRONT_THEME_SLUG = 'storefront';
const THEME_PROJECT_TO_SLUG = {
  'chromium-wp-customer-classic-theme': 'twentytwentyone',
  'chromium-wp-customer-block-theme': 'twentytwentyfive',
};

let themeLockToken = null;
let originalThemeSlug = null;
let activeThemeProjectSlug = null;

function resolveThemeForProject(projectName) {
  return THEME_PROJECT_TO_SLUG[projectName] || null;
}

test.beforeAll(async ({}, workerInfo) => {
  const targetThemeSlug = resolveThemeForProject(workerInfo.project.name);
  if (!targetThemeSlug) {
    return;
  }

  themeLockToken = await acquireThemeLock();

  const status = await getActiveThemeStatus();
  originalThemeSlug = status.activeStylesheet || STOREFRONT_THEME_SLUG;

  if (status.activeStylesheet !== targetThemeSlug) {
    const switchResult = await switchThemeBySlug(targetThemeSlug);
    if (!switchResult.success) {
      throw new Error(`Failed to activate theme '${targetThemeSlug}' for project '${workerInfo.project.name}': ${switchResult.error || 'unknown error'}`);
    }
  }

  activeThemeProjectSlug = targetThemeSlug;
});

test.afterAll(async () => {
  if (!themeLockToken) {
    return;
  }

  try {
    const restoreTarget = originalThemeSlug || STOREFRONT_THEME_SLUG;
    const restoreResult = await switchThemeBySlug(restoreTarget);
    if (!restoreResult.success) {
      throw new Error(`Failed to restore theme '${restoreTarget}' after '${activeThemeProjectSlug || 'unknown'}': ${restoreResult.error || 'unknown error'}`);
    }
  } finally {
    await releaseThemeLock(themeLockToken);
    themeLockToken = null;
    originalThemeSlug = null;
    activeThemeProjectSlug = null;
  }
});


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

    const fbclid = process.env.TEST_FBCLID;
    const isBraveProject = testInfo?.project?.name?.includes('brave');

    // Keep original behavior for all browsers except Brave.
    // Brave can strip fbclid via WebRequest redirect, so seed _fbc only there.
    if (isBraveProject) {
      const seededFbc = `fb.1.${Date.now()}.${fbclid}`;
      await page.context().addCookies([
        {
          name: '_fbc',
          value: seededFbc,
          url: process.env.WORDPRESS_URL
        }
      ]);
    }

    console.log(`   🌐 Navigating to homepage with fbclid`);
    const eventPromise = pixelCapture.waitForEvent();
    await page.goto(`/?fbclid=${fbclid}`);
    await TestSetup.waitForPageReady(page);
    await eventPromise;

    const validator = new EventValidator(testId, true, false, { allowBraveFbcNormalization: isBraveProject }); // expects fbc
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

    // Wait for page to reload (form submission) and become ready
    await page.waitForLoadState('networkidle');
    await eventPromise;

    const validator = new EventValidator(testId);
    await validator.checkDebugLog();
    const result = await validator.validate('AddToCart', page);

    TestSetup.logResult('AddToCart', result);
    expect(result.passed).toBe(true);
});

test('AddToCart - AJAX (shop loop parity with PDP)', async ({ page }, testInfo) => {
    await clearCart(page);

    const baseline = await TestSetup.init(page, 'AddToCart', testInfo);
    await page.goto(process.env.TEST_PRODUCT_URL);
    await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

    const pdpEventPromise = baseline.pixelCapture.waitForEvent();
    await page.click('.single_add_to_cart_button');
    await page.waitForLoadState('networkidle');
    await pdpEventPromise;

    const baselineValidator = new EventValidator(baseline.testId);
    await baselineValidator.checkDebugLog();
    const baselineResult = await baselineValidator.validate('AddToCart', page);
    expect(baselineResult.passed).toBe(true);

    const baselineCaptured = await loadCapturedEvents(baseline.testId);
    const baselinePixel = getLatestEvent(baselineCaptured.pixel, 'AddToCart');
    const baselineCapi = getLatestEvent(baselineCaptured.capi, 'AddToCart');

    expect(baselinePixel).toBeTruthy();
    expect(baselineCapi).toBeTruthy();

    const baselineProductId = String(
      baselinePixel?.custom_data?.contents?.[0]?.id ||
      baselinePixel?.custom_data?.content_ids?.[0] ||
      baselineCapi?.custom_data?.contents?.[0]?.id ||
      baselineCapi?.custom_data?.content_ids?.[0] ||
      ''
    );

    await clearCart(page);

    const ajaxRun = await TestSetup.init(page, 'AddToCart', testInfo);

    const ajaxEventPromise = ajaxRun.pixelCapture.waitForEvent();
    const ajaxTrace = await triggerAjaxAddToCartFromShop(page, {
      productUrl: process.env.TEST_PRODUCT_URL,
      expectedProductId: baselineProductId || undefined
    });
    await ajaxEventPromise;

    expect(ajaxTrace.usedAjax).toBe(true);
    expect(ajaxTrace.mainFrameNavigated).toBe(false);

    const ajaxValidator = new EventValidator(ajaxRun.testId);
    await ajaxValidator.checkDebugLog();
    const ajaxResult = await ajaxValidator.validate('AddToCart', page);
    expect(ajaxResult.passed).toBe(true);

    const ajaxCaptured = await loadCapturedEvents(ajaxRun.testId);
    const ajaxPixel = getLatestEvent(ajaxCaptured.pixel, 'AddToCart');
    const ajaxCapi = getLatestEvent(ajaxCaptured.capi, 'AddToCart');

    expect(ajaxPixel).toBeTruthy();
    expect(ajaxCapi).toBeTruthy();

    const pickComparable = (event) => ({
      content_ids: asArray(event?.custom_data?.content_ids),
      contents: asArray(event?.custom_data?.contents),
      content_name: event?.custom_data?.content_name,
      content_type: event?.custom_data?.content_type,
      value: Number(event?.custom_data?.value),
      currency: event?.custom_data?.currency
    });

    const normalizeContents = (items) => items
      .map(item => ({ id: String(item?.id), quantity: Number(item?.quantity) }))
      .sort((a, b) => `${a.id}:${a.quantity}`.localeCompare(`${b.id}:${b.quantity}`));

    const normalizeComparable = (data) => ({
      ...data,
      content_ids: data.content_ids.map(String).sort(),
      contents: normalizeContents(data.contents)
    });

    expect(normalizeComparable(pickComparable(ajaxPixel))).toEqual(normalizeComparable(pickComparable(baselinePixel)));
    expect(normalizeComparable(pickComparable(ajaxCapi))).toEqual(normalizeComparable(pickComparable(baselineCapi)));
});

test('ViewContent - Variable Product', async ({ page }, testInfo) => {
    let fixture;

    try {
      fixture = await createVariableProductEventFixture();
      const { testId, pixelCapture } = await TestSetup.init(page, 'ViewContent', testInfo);

      console.log(`   📦 Navigating to variable product page: ${fixture.parentUrl}`);
      const eventPromise = pixelCapture.waitForEvent();
      await page.goto(fixture.parentUrl);
      await TestSetup.waitForPageReady(page);
      await eventPromise;

      const validator = new EventValidator(testId);
      await validator.checkDebugLog();
      const rawResult = await validator.validate('ViewContent', page);
      const result = ignoreKnownPurchaseUserDataGap(rawResult);

      const captured = await loadCapturedEvents(testId);
      const pixelEvent = getLatestEvent(captured.pixel, 'ViewContent');
      const capiEvent = getLatestEvent(captured.capi, 'ViewContent');

      assertEventContainsRetailerId(pixelEvent, fixture.parentRetailerId);
      assertEventContainsRetailerId(capiEvent, fixture.parentRetailerId);
      expect(pixelEvent?.custom_data?.content_type).toBe('product_group');
      expect(capiEvent?.custom_data?.content_type).toBe('product_group');

      TestSetup.logResult('ViewContent (Variable Product)', result);
      expect(result.passed).toBe(true);
    } finally {
      if (fixture?.cleanupProductIds?.length) {
        await cleanupProducts(fixture.cleanupProductIds);
      }
    }
});

test('AddToCart - Variable Product (selected variation)', async ({ page }, testInfo) => {
    let fixture;

    try {
      fixture = await createVariableProductEventFixture();
      const targetVariation = fixture.variations.find(v => v.option === 'Large') || fixture.variations[0];
      const { testId, pixelCapture } = await TestSetup.init(page, 'AddToCart', testInfo);

      await page.goto(fixture.parentUrl);
      await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

      const selected = await selectVariationByLabel(page, {
        attributeSlug: fixture.attributeSlug,
        label: targetVariation.option
      });
      expect(selected.variationId).toBe(Number(targetVariation.id));

      console.log(`   🛒 Clicking Add to Cart for variation ${targetVariation.option}`);
      const eventPromise = pixelCapture.waitForEvent();
      await page.click('.single_add_to_cart_button');
      await page.waitForLoadState('networkidle');
      await eventPromise;

      const validator = new EventValidator(testId);
      await validator.checkDebugLog();
      const rawResult = await validator.validate('AddToCart', page);
      const result = ignoreKnownPurchaseUserDataGap(rawResult);

      const captured = await loadCapturedEvents(testId);
      const pixelEvent = getLatestEvent(captured.pixel, 'AddToCart');
      const capiEvent = getLatestEvent(captured.capi, 'AddToCart');

      assertEventContainsRetailerId(pixelEvent, targetVariation.retailer_id);
      assertEventContainsRetailerId(capiEvent, targetVariation.retailer_id);

      // Explicit SKU assertion when retailer ID mode is configured to use SKU.
      const isSkuMode = String(targetVariation.retailer_id) === String(targetVariation.sku);
      if (isSkuMode) {
        const pixelIds = [
          ...asArray(pixelEvent?.custom_data?.content_ids).map(String),
          ...asArray(pixelEvent?.custom_data?.contents).map(entry => String(entry?.id)).filter(Boolean)
        ];
        const capiIds = [
          ...asArray(capiEvent?.custom_data?.content_ids).map(String),
          ...asArray(capiEvent?.custom_data?.contents).map(entry => String(entry?.id)).filter(Boolean)
        ];

        expect(pixelIds).toContain(String(targetVariation.sku));
        expect(capiIds).toContain(String(targetVariation.sku));
      }

      TestSetup.logResult('AddToCart (Variable Product)', result);
      expect(result.passed).toBe(true);
    } finally {
      if (fixture?.cleanupProductIds?.length) {
        await cleanupProducts(fixture.cleanupProductIds);
      }
    }
});

test('Purchase - Variable Product', async ({ page }, testInfo) => {
    let fixture;

    try {
      fixture = await createVariableProductEventFixture();

      await clearCart(page);
      const targetVariation = fixture.variations.find(v => v.option === 'Large') || fixture.variations[0];
      const { testId } = await TestSetup.init(page, 'Purchase', testInfo);

      await page.goto(fixture.parentUrl);
      await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

      await selectVariationByLabel(page, {
        attributeSlug: fixture.attributeSlug,
        label: targetVariation.option
      });

      await page.click('.single_add_to_cart_button');
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(TIMEOUTS.SHORT);

      const cartItems = await getCartItemsViaStoreApi(page);
      expect(cartItems.length).toBe(1);
      expect(String(cartItems[0].id)).toBe(String(targetVariation.id));

      await completeCheckoutFromCart(page);

      const validator = new EventValidator(testId);
      await validator.checkDebugLog();
      const rawResult = await validator.validate('Purchase', page);
      const result = ignoreKnownPurchaseUserDataGap(rawResult);

      const captured = await loadCapturedEvents(testId);
      const capiPurchase = getLatestEvent(captured.capi, 'Purchase');
      const contents = asArray(capiPurchase?.custom_data?.contents);

      assertEventContainsRetailerId(capiPurchase, targetVariation.retailer_id);
      expect(contents.some(item => String(item.id) === String(targetVariation.retailer_id) && Number(item.quantity) >= 1)).toBe(true);

      TestSetup.logResult('Purchase (Variable Product)', result);
      expect(result.passed).toBe(true);
    } finally {
      if (fixture?.cleanupProductIds?.length) {
        await cleanupProducts(fixture.cleanupProductIds);
      }
    }
});

test('ViewContent - Grouped Product', async ({ page }, testInfo) => {
    let fixture;

    try {
      fixture = await createGroupedProductEventFixture();
      const { testId, pixelCapture } = await TestSetup.init(page, 'ViewContent', testInfo);

      console.log(`   📦 Navigating to grouped product page: ${fixture.groupedUrl}`);
      const eventPromise = pixelCapture.waitForEvent();
      await page.goto(fixture.groupedUrl);
      await TestSetup.waitForPageReady(page);
      await eventPromise;

      const validator = new EventValidator(testId);
      await validator.checkDebugLog();
      const rawResult = await validator.validate('ViewContent', page);
      const result = ignoreKnownPurchaseUserDataGap(rawResult);

      const captured = await loadCapturedEvents(testId);
      const pixelEvent = getLatestEvent(captured.pixel, 'ViewContent');
      const capiEvent = getLatestEvent(captured.capi, 'ViewContent');

      assertEventContainsRetailerId(pixelEvent, fixture.groupedRetailerId);
      assertEventContainsRetailerId(capiEvent, fixture.groupedRetailerId);
      expect(pixelEvent?.custom_data?.content_type).toBe('product_group');
      expect(capiEvent?.custom_data?.content_type).toBe('product_group');

      TestSetup.logResult('ViewContent (Grouped Product)', result);
      expect(result.passed).toBe(true);
    } finally {
      if (fixture?.cleanupProductIds?.length) {
        await cleanupProducts(fixture.cleanupProductIds);
      }
    }
});

test('Purchase - Grouped Product', async ({ page }, testInfo) => {
    let fixture;

    try {
      fixture = await createGroupedProductEventFixture();

      await clearCart(page);
      const child = fixture.children[0];
      const { testId } = await TestSetup.init(page, 'Purchase', testInfo);

      await page.goto(fixture.groupedUrl);
      await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

      await setGroupedProductQuantity(page, child.id, 1);
      await page.click('.single_add_to_cart_button');
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(TIMEOUTS.SHORT);

      const cartItems = await getCartItemsViaStoreApi(page);
      expect(cartItems.length).toBe(1);
      expect(String(cartItems[0].id)).toBe(String(child.id));

      await completeCheckoutFromCart(page);

      const validator = new EventValidator(testId);
      await validator.checkDebugLog();
      const rawResult = await validator.validate('Purchase', page);
      const result = ignoreKnownPurchaseUserDataGap(rawResult);

      const captured = await loadCapturedEvents(testId);
      const capiPurchase = getLatestEvent(captured.capi, 'Purchase');
      const contents = asArray(capiPurchase?.custom_data?.contents);

      assertEventContainsRetailerId(capiPurchase, child.retailer_id);
      expect(contents.some(item => String(item.id) === String(child.retailer_id) && Number(item.quantity) >= 1)).toBe(true);

      TestSetup.logResult('Purchase (Grouped Product)', result);
      expect(result.passed).toBe(true);
    } finally {
      if (fixture?.cleanupProductIds?.length) {
        await cleanupProducts(fixture.cleanupProductIds);
      }
    }
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
    // Keep cart deterministic so single-item category expectations are stable.
    await clearCart(page);

    // init() sets the test cookie used by PHP-side CAPI event logging.
    const { testId, pixelCapture } = await TestSetup.init(page, 'InitiateCheckout',  testInfo);

    await page.goto(process.env.TEST_PRODUCT_URL);
    await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

    console.log(`   🛒 Adding product to cart`);
    await page.click('.single_add_to_cart_button');
    await page.waitForTimeout(TIMEOUTS.SHORT);

    const cartItems = await getCartItemsViaStoreApi(page);
    expect(cartItems.length).toBe(1);

    console.log(`   💳 Navigating to checkout`);
    // Set up listener BEFORE triggering the action (prevents race condition)
    const eventPromise = pixelCapture.waitForEvent();
    await page.goto('/checkout');
    await TestSetup.waitForPageReady(page);
    await eventPromise;
    await page.waitForTimeout(TIMEOUTS.SHORT); // allow CAPI logger write to settle

    const validator = new EventValidator(testId);
    await validator.checkDebugLog();
    const result = await validator.validate('InitiateCheckout', page);

    TestSetup.logResult('InitiateCheckout', result);
    expect(result.passed).toBe(true);
});

test('Purchase', async ({ page }, testInfo) => {
    const { testId } = await TestSetup.init(page, 'Purchase',  testInfo);

    await page.goto(process.env.TEST_PRODUCT_URL);
    await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

    console.log(`   🛒 Adding product to cart`);
    await page.click('.single_add_to_cart_button');
    await page.waitForTimeout(TIMEOUTS.SHORT);

    console.log(`   💳 Completing checkout as guest`);
    await completeCheckoutFromCart(page);

    const validator = new EventValidator(testId);
    await validator.checkDebugLog();
    const rawResult = await validator.validate('Purchase', page);
    const result = ignoreKnownPurchaseUserDataGap(rawResult);

    TestSetup.logResult('Purchase', result);
    expect(result.passed).toBe(true);
});

test('Purchase - Multiple Place Order Clicks', async ({ page }, testInfo) => {
    const { testId } = await TestSetup.init(page, 'Purchase',  testInfo);

    await page.goto(process.env.TEST_PRODUCT_URL);
    await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

    console.log(`   🛒 Adding product to cart`);
    await page.click('.single_add_to_cart_button');
    await page.waitForTimeout(TIMEOUTS.SHORT);

    console.log(`   💳 Navigating to checkout`);
    await page.goto('/checkout');
    await TestSetup.waitForPageReady(page);

    // Woo Blocks can show saved address summary with hidden fields for logged-in customers.
    // Reveal editable form before attempting to fill values.
    for (let i = 0; i < 3; i++) {
      const editButton = page.getByRole('button', { name: /edit/i }).first();
      const isVisible = await editButton.isVisible({ timeout: TIMEOUTS.SHORT }).catch(() => false);
      if (!isVisible) {
        break;
      }
      await editButton.click().catch(() => {});
      await page.waitForTimeout(TIMEOUTS.INSTANT);
    }

    // Fill checkout fields
    const defaults = {
      email: process.env.TEST_USER_EMAIL || `e2e+${Date.now()}@example.test`,
      firstName: process.env.TEST_USER_FIRST_NAME || 'E2E',
      lastName: process.env.TEST_USER_LAST_NAME || 'Customer',
      address1: process.env.TEST_USER_ADDRESS_1 || '1 Test Street',
      city: process.env.TEST_USER_CITY || 'London',
      country: process.env.TEST_USER_COUNTRY || 'GB',
      state: process.env.TEST_USER_STATE || 'LND',
      postcode: process.env.TEST_USER_POSTCODE || 'EC1A1BB',
      phone: process.env.TEST_USER_PHONE || '0123456789'
    };

    const fillIfVisible = async (selector, value) => {
      const field = page.locator(selector).first();
      if (await field.isVisible({ timeout: TIMEOUTS.SHORT }).catch(() => false)) {
        await field.fill(value);
      }
    };

    const selectIfVisible = async (selector, value) => {
      const field = page.locator(selector).first();
      if (await field.isVisible({ timeout: TIMEOUTS.SHORT }).catch(() => false)) {
        await field.selectOption(value).catch(async () => {
          await field.fill(value);
        });
      }
    };

    await fillIfVisible('#email', defaults.email);
    for (const prefix of ['shipping', 'billing']) {
      await fillIfVisible(`#${prefix}-first_name`, defaults.firstName);
      await fillIfVisible(`#${prefix}-last_name`, defaults.lastName);
      await fillIfVisible(`#${prefix}-address_1`, defaults.address1);
      await fillIfVisible(`#${prefix}-city`, defaults.city);
      await selectIfVisible(`#${prefix}-country`, defaults.country);
      await selectIfVisible(`#${prefix}-state`, defaults.state);
      await fillIfVisible(`#${prefix}-postcode`, defaults.postcode);
      await fillIfVisible(`#${prefix}-phone`, defaults.phone);
    }

    // Scroll down to see checkout form in video
    await page.evaluate(() => window.scrollBy(0, 400));
    await page.waitForTimeout(TIMEOUTS.SHORT);

    console.log(`   💰 Selecting Cash on Delivery`);
    await page.waitForSelector('.wc-block-components-radio-control__option[for="radio-control-wc-payment-method-options-cod"]', { state: 'visible', timeout: TIMEOUTS.LONG });
    await page.click('label[for="radio-control-wc-payment-method-options-cod"]');
    await page.waitForTimeout(TIMEOUTS.INSTANT);

    const termsCheckbox = page.locator('#wc-terms-and-conditions-checkbox-text').first();
    if (await termsCheckbox.isVisible({ timeout: TIMEOUTS.SHORT }).catch(() => false)) {
      await termsCheckbox.click();
    }

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
    const rawResult = await validator.validate('Purchase', page);
    const result = ignoreKnownPurchaseUserDataGap(rawResult);

    // Should still only have 1 Purchase event despite multiple clicks
    TestSetup.logResult('Purchase (Deduplication)', result);
    expect(result.passed).toBe(true);
});

test('Search', async ({ page }, testInfo) => {
    test.skip(
      ['chromium-wp-customer-classic-theme', 'chromium-wp-customer-block-theme'].includes(testInfo.project.name),
      'Search is not validated on non-Storefront theme projects because search UI is not guaranteed.'
    );

    const { testId, pixelCapture } = await TestSetup.init(page, 'Search',  testInfo);

    console.log(`   🏠 Navigating to homepage`);
    await page.goto('/');
    await TestSetup.waitForPageReady(page);

    console.log(`   🔍 Typing search query in search box`);
    const searchInput = await getVisibleSearchInput(page);
    if (!searchInput && testInfo.project.name.includes('brave')) {
      throw new Error('Search UI was not found for brave-wp-customer on Storefront; this should be present and must not be skipped.');
    }
    test.skip(!searchInput, 'Search UI is not available in this browser/theme fixture.');
    await searchInput.fill('test');

    console.log(`   🔎 Submitting search form`);
    // Set up listener BEFORE triggering the action
    const eventPromise = pixelCapture.waitForEvent();

    await submitSearch(page, searchInput);
    await TestSetup.waitForPageReady(page);
    await eventPromise;

    const validator = new EventValidator(testId);
    await validator.checkDebugLog();
    const result = await validator.validate('Search', page);

    if (!result.passed) {
      const diagnostics = {
        project: testInfo.project.name,
        currentUrl: page.url(),
        testId,
        errors: result.errors,
        pixelCustomData: result.pixel?.custom_data,
        capiCustomData: result.capi?.custom_data,
      };
      console.log(`🔎 Search validation diagnostics: ${JSON.stringify(diagnostics)}`);
    }

    TestSetup.logResult('Search', result);
    expect(result.passed).toBe(true);
});

test('Search - No Results', async ({ page }, testInfo) => {
    test.skip(
      ['chromium-wp-customer-classic-theme', 'chromium-wp-customer-block-theme'].includes(testInfo.project.name),
      'Search is not validated on non-Storefront theme projects because search UI is not guaranteed.'
    );

    const { testId, pixelCapture } = await TestSetup.init(page, 'Search', testInfo, true); // expectZeroEvents=true

    console.log(`   🏠 Navigating to homepage`);
    await page.goto('/');
    await TestSetup.waitForPageReady(page);

    // Generate random string that won't match any products
    const randomString = 'xyzabc239nfjsdn' + Date.now();
    console.log(`   🔍 Typing random search query: ${randomString}`);

    const searchInput = await getVisibleSearchInput(page);
    if (!searchInput && testInfo.project.name.includes('brave')) {
      throw new Error('Search UI was not found for brave-wp-customer on Storefront; this should be present and must not be skipped.');
    }
    test.skip(!searchInput, 'Search UI is not available in this browser/theme fixture.');
    await searchInput.fill(randomString);

    console.log(`   🔎 Submitting search form (expecting no events)`);
    const eventPromise = pixelCapture.waitForEvent(); // Will succeed if no event fires

    await submitSearch(page, searchInput);
    await TestSetup.waitForPageReady(page);
    await eventPromise;

    const validator = new EventValidator(testId, false, true); // expectZeroEvents=true
    await validator.checkDebugLog();
    const result = await validator.validate('Search', page);

    TestSetup.logResult('Search (No Results)', result);
    expect(result.passed).toBe(true);
});


test('ViewContent - No Consent (pixel disabled)', async ({ page }, testInfo) => {
    try {
        // 1. Clear any existing _fbp/_fbc cookies from previous tests
        console.log('🍪 Clearing existing FB cookies...');
        await page.context().clearCookies();

        // 2. Deactivate plugin
        await deactivatePlugin();

        // 3. Install mu-plugin (filter returns false)
        await installPixelBlockerMuPlugin();

        // 4. Reactivate plugin (filter now in place before EventsTracker constructor runs)
        await activatePlugin();

        // 5. Run test - expect NO events
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

        // 6. Validate cookies - _fbp and _fbc should NOT exist after page load
        console.log('🍪 Checking cookies were not set...');
        const cookies = await page.context().cookies();
        const fbp = cookies.find(c => c.name === '_fbp');
        const fbc = cookies.find(c => c.name === '_fbc');

        expect(fbp).toBeUndefined();
        expect(fbc).toBeUndefined();
        console.log('✅ No _fbp or _fbc cookies (correct - fbevents.js did not load)');
    } finally {
        // 7. Cleanup - always restore environment, even if assertions fail
        console.log('🧹 Cleaning up...');
        await removePixelBlockerMuPlugin().catch(() => {});
        await reconnectAndVerify({ enablePixel: 'yes', enableS2S: 'yes' }).catch(() => {});
        console.log('✅ Consent test cleanup complete');
    }
});

// Lead event is not tested as it needs an SMTP server etc
// NOTE: Subscribe test is skipped because it requires WooCommerce Paid Subscriptions
// Free alternatives (YITH, Subscriptio) use different APIs incompatible with facebook-for-woocommerce
// The plugin specifically checks for wcs_get_subscriptions_for_order() which only exists in the official plugin

// =============================================================================
// Isolated Execution Context Tests (Gap 1 Fix)
// =============================================================================
// These tests verify that pixel events fire even when other plugins have JS errors.
// This validates the fix for Gap 1: Shared JavaScript Execution Context.

test('ViewContent - Isolated Execution (with JS errors from other plugins)', async ({ page }, testInfo) => {
    console.log('🧪 Testing isolated event execution with simulated plugin errors...');

    // 1. Install the JS error simulator mu-plugin
    await installJsErrorSimulatorMuPlugin();

    try {
        // 2. Initialize test
        const { testId, pixelCapture } = await TestSetup.init(page, 'ViewContent', testInfo);

        // 3. Set up console error listener to verify errors are being thrown
        const consoleErrors = [];
        page.on('console', msg => {
            if (msg.type() === 'error' || msg.text().includes('[E2E Test]')) {
                consoleErrors.push(msg.text());
            }
        });

        // 4. Navigate to product page
        console.log('   📦 Navigating to product page (with 3 simulated JS errors active)...');
        const eventPromise = pixelCapture.waitForEvent();
        await page.goto(process.env.TEST_PRODUCT_URL);
        await TestSetup.waitForPageReady(page);
        await eventPromise;

        // 5. Verify that errors were indeed thrown (simulator is working)
        console.log(`   🔍 Console messages captured: ${consoleErrors.length}`);
        const simulatorErrors = consoleErrors.filter(msg => msg.includes('[E2E Test]'));
        console.log(`   ⚠️ Simulator errors detected: ${simulatorErrors.length}`);

        if (simulatorErrors.length === 0) {
            console.warn('   ⚠️ Warning: No simulator errors detected - simulator may not be active');
        }

        // 6. Validate that ViewContent event STILL fired despite JS errors
        console.log('   ✅ Validating ViewContent event fired despite JS errors...');
        const validator = new EventValidator(testId);
        await validator.checkDebugLog();
        const result = await validator.validate('ViewContent', page);

        TestSetup.logResult('ViewContent (Isolated Execution)', result);
        expect(result.passed).toBe(true);

        console.log('   🎉 SUCCESS: Pixel events fire even with other plugins\' JS errors!');

    } finally {
        // 7. Always cleanup - remove the error simulator
        console.log('   🧹 Cleaning up JS error simulator...');
        await removeJsErrorSimulatorMuPlugin();
    }
});

test('AddToCart - Isolated Execution (with JS errors from other plugins)', async ({ page }, testInfo) => {
    console.log('🧪 Testing AddToCart isolated execution with simulated plugin errors...');

    // 1. Install the JS error simulator mu-plugin
    await installJsErrorSimulatorMuPlugin();

    try {
        // 2. Initialize test
        const { testId, pixelCapture } = await TestSetup.init(page, 'AddToCart', testInfo);

        // 3. Navigate to product page first
        await page.goto(process.env.TEST_PRODUCT_URL);
        await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

        // 4. Click Add to Cart (with 3 simulated JS errors active)
        console.log('   🛒 Clicking Add to Cart (with 3 simulated JS errors active)...');
        const eventPromise = pixelCapture.waitForEvent();
        await page.click('.single_add_to_cart_button');

        // Wait for page to reload (form submission) and become ready
        await page.waitForLoadState('networkidle');
        await eventPromise;

        // 5. Validate that AddToCart event fired despite JS errors
        console.log('   ✅ Validating AddToCart event fired despite JS errors...');
        const validator = new EventValidator(testId);
        await validator.checkDebugLog();
        const result = await validator.validate('AddToCart', page);

        TestSetup.logResult('AddToCart (Isolated Execution)', result);
        expect(result.passed).toBe(true);

        console.log('   🎉 SUCCESS: AddToCart fires even with other plugins\' JS errors!');

    } finally {
        // 6. Always cleanup
        console.log('   🧹 Cleaning up JS error simulator...');
        await removeJsErrorSimulatorMuPlugin();
    }
});

function getMajorBrowserVersion(versionString) {
    const match = String(versionString || '').match(/^(\d+)/);
    return match ? parseInt(match[1], 10) : NaN;
}

test('Privacy Sandbox - Topics API available in Chromium', async ({ page, browser }) => {
    test.skip(!test.info().project.name.includes('privacy-sandbox'), 'Privacy Sandbox test only runs on privacy-sandbox project');

    const chromiumMajor = getMajorBrowserVersion(browser.version());
    test.skip(Number.isNaN(chromiumMajor) || chromiumMajor < 115, `Privacy Sandbox requires Chrome/Chromium >= 115 (detected ${browser.version()})`);

    await page.goto('/');
    await TestSetup.waitForPageReady(page);

    const topicsResult = await page.evaluate(async () => {
        const hasTopicsApi = typeof document !== 'undefined' && typeof document.browsingTopics === 'function';
        if (!hasTopicsApi) {
            return { hasTopicsApi, topicsCount: null, error: null };
        }

        try {
            const topics = await document.browsingTopics();
            return { hasTopicsApi, topicsCount: Array.isArray(topics) ? topics.length : 0, error: null };
        } catch (error) {
            return { hasTopicsApi, topicsCount: null, error: String(error?.message || error) };
        }
    });

    expect(topicsResult.hasTopicsApi).toBe(true);
    expect(topicsResult.error).toBeNull();
    expect(typeof topicsResult.topicsCount).toBe('number');
});

test('Privacy Sandbox - Protected Audience API shape in Chromium', async ({ page, browser }) => {
    test.skip(!test.info().project.name.includes('privacy-sandbox'), 'Privacy Sandbox test only runs on privacy-sandbox project');

    const chromiumMajor = getMajorBrowserVersion(browser.version());
    test.skip(Number.isNaN(chromiumMajor) || chromiumMajor < 115, `Privacy Sandbox requires Chrome/Chromium >= 115 (detected ${browser.version()})`);

    await page.goto('/');
    await TestSetup.waitForPageReady(page);

    const apiShape = await page.evaluate(() => {
        return {
            hasNavigator: typeof navigator !== 'undefined',
            hasJoinAdInterestGroup: typeof navigator?.joinAdInterestGroup === 'function',
            hasRunAdAuction: typeof navigator?.runAdAuction === 'function',
            hasLeaveAdInterestGroup: typeof navigator?.leaveAdInterestGroup === 'function',
        };
    });

    const hasAnyProtectedAudienceApi =
      apiShape.hasJoinAdInterestGroup
      || apiShape.hasRunAdAuction
      || apiShape.hasLeaveAdInterestGroup;

    expect(apiShape.hasNavigator).toBe(true);
    expect(hasAnyProtectedAudienceApi).toBe(true);
});

// Cleanup is handled by GitHub workflow after all tests complete
// This ensures product exists for all tests even if some fail
