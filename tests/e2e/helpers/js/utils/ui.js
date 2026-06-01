/**
 * UI interaction helpers for E2E tests
 */

const { TIMEOUTS } = require('../constants/timeouts');

/**
 * Dismiss WooCommerce onboarding/tour overlays that can intercept clicks in fresh installs.
 * Safe no-op when overlays are absent.
 * @param {import('@playwright/test').Page} page
 */
async function dismissWooInterferingOverlays(page) {
  try {
    // Close "Start by adding attributes" / tour popovers.
    const popoverCloseButtons = page.locator(
      '.woocommerce-tour-kit-step-navigation__done-btn:visible, '
      + '.components-popover button[aria-label="Close"]:visible, '
      + '.components-popover__content button[aria-label="Close"]:visible'
    );
    const closeCount = await popoverCloseButtons.count();
    for (let i = 0; i < closeCount; i++) {
      await popoverCloseButtons.nth(i).click({ force: true }).catch(() => {});
    }

    // Close top Woo blue setup banner if present.
    const setupBannerDismiss = page.locator(
      '.woocommerce-layout__header-tasks-reminder button[aria-label="Dismiss"], '
      + '.woocommerce-layout__header-tasks-reminder .components-button[aria-label="Dismiss"], '
      + '.woocommerce-layout__header button[aria-label="Dismiss"]'
    ).first();
    if (await setupBannerDismiss.count()) {
      await setupBannerDismiss.click({ force: true }).catch(() => {});
    }

    // Final escape to close any lingering popovers/modals.
    await page.keyboard.press('Escape').catch(() => {});
  } catch {
    // Best-effort only.
  }
}

/**
 * Set product title
 * @param {import('@playwright/test').Page} page - Playwright page
 * @param {string} newTitle - New title
 */
async function setProductTitle(page, newTitle) {
  const titleField = page.locator('#title');
  titleField.waitFor({ state: 'visible', timeout: TIMEOUTS.MEDIUM });
  await titleField.scrollIntoViewIfNeeded();
  await titleField.fill(newTitle);
  await dismissWooInterferingOverlays(page);
  console.log(`✅ Updated title to: "${newTitle}"`);
}

/**
 * Set product description using TinyMCE or text editor
 * @param {import('@playwright/test').Page} page - Playwright page
 * @param {string} newDescription - New description
 */
async function setProductDescription(page, newDescription) {
  try {
    console.log('🔄 Attempting to set product description...');

    // First, try the visual/TinyMCE editor
    const visualTab = page.locator('#content-tmce');
    const isVisualTabVisible = await visualTab.isVisible({ timeout: TIMEOUTS.NORMAL }).catch(() => false);

    if (isVisualTabVisible) {
      await visualTab.click();

      const tinyMCEFrame = page.locator('#content_ifr');
      await tinyMCEFrame.waitFor({ state: 'visible', timeout: TIMEOUTS.MEDIUM });

      const frameContent = tinyMCEFrame.contentFrame();
      const bodyElement = frameContent.locator('body');
      await bodyElement.waitFor({ state: 'visible', timeout: TIMEOUTS.MEDIUM });
      await bodyElement.fill(newDescription);
      console.log('✅ Added description via TinyMCE editor');
    } else {
      // Try text/HTML tab
      const textTab = page.locator('#content-html');
      const isTextTabVisible = await textTab.isVisible({ timeout: TIMEOUTS.NORMAL }).catch(() => false);

      if (isTextTabVisible) {
        await textTab.click();

        const contentTextarea = page.locator('#content');
        await contentTextarea.waitFor({ state: 'visible', timeout: TIMEOUTS.NORMAL + TIMEOUTS.SHORT });
        await contentTextarea.fill(newDescription);
        console.log('✅ Added description via text editor');
      } else {
        // Try block editor if present
        const blockEditor = page.locator('.wp-block-post-content, .block-editor-writing-flow');
        const isBlockEditorVisible = await blockEditor.isVisible({ timeout: TIMEOUTS.NORMAL }).catch(() => false);

        if (isBlockEditorVisible) {
          await blockEditor.click();
          await page.keyboard.type(newDescription);
          console.log('✅ Added description via block editor');
        } else {
          console.warn('⚠️ No content editor found - skipping description');
        }
      }
    }
  } catch (editorError) {
    console.warn(`⚠️ Content editor issue: ${editorError.message} - continuing without description`);
  }
}

/**
 * Search and select from a Select2 dropdown with retry logic
 * @param {import('@playwright/test').Page} page - Playwright page
 * @param {Object} locator - Playwright locator for Select2 input
 * @param {string} searchValue - Value to search for
 */
async function exactSearchSelect2Container(page, locator, searchValue) {
  const maxRetries = 3;
  let lastError;

  for (let attempt = 1; attempt <= maxRetries; attempt++) {
    try {
      await locator.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });

      await locator.evaluate((el) => {
        el.scrollIntoView({ block: 'center', behavior: 'instant' });
      });

      await locator.focus();
      await locator.click();

      const dropdownContainer = page.locator('.select2-dropdown, .select2-results').first();
      await dropdownContainer.waitFor({ state: 'visible', timeout: TIMEOUTS.MEDIUM });

      await locator.pressSequentially(searchValue, { delay: 100 });

      // Wait for AJAX search to complete
      const loadingIndicator = page.locator('.select2-results__option--loading, .select2-searching');
      try {
        await loadingIndicator.waitFor({ state: 'visible', timeout: TIMEOUTS.SHORT });
        await loadingIndicator.waitFor({ state: 'hidden', timeout: TIMEOUTS.LONG });
      } catch {
        // Loading indicator might not appear if results are cached
      }

      const anyOption = page.locator('.select2-results__option:not(.select2-results__option--loading)').first();
      await anyOption.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });

      // Select the matching result
      let firstResult = page.getByRole('option', { name: searchValue, exact: true }).first();
      let isVisible = await firstResult.isVisible().catch(() => false);

      if (!isVisible) {
        firstResult = page.locator(`.select2-results__option`).filter({ hasText: searchValue }).first();
        isVisible = await firstResult.isVisible().catch(() => false);
      }

      if (!isVisible) {
        firstResult = page.locator('.select2-results__option--selectable, .select2-results__option[role="option"]').first();
      }

      await firstResult.waitFor({ state: 'visible', timeout: TIMEOUTS.MEDIUM });
      await firstResult.click();
      await page.waitForLoadState('domcontentloaded');
      console.log(`✅ Selected ${searchValue} from Select2 dropdown`);
      return;
    } catch (error) {
      lastError = error;
      const isRetryableError = error.message.includes('not attached to the DOM') ||
                               error.message.includes('Timeout');
      if (isRetryableError && attempt < maxRetries) {
        console.log(`⚠️ Select2 interaction failed (${error.message.split('\n')[0]}), retrying (attempt ${attempt}/${maxRetries})...`);
        await page.keyboard.press('Escape');
        await page.waitForTimeout(TIMEOUTS.INSTANT);
        continue;
      }
      throw error;
    }
  }
  throw lastError;
}

/**
 * Resolve a visible frontend search input across desktop/mobile layouts.
 * Tries direct selectors first, then opens common mobile menu toggles.
 * @param {import('@playwright/test').Page} page - Playwright page
 * @returns {Promise<import('@playwright/test').Locator>}
 */
async function getVisibleSearchInput(page) {
  // Prefer Woo product-search forms first (they include post_type=product and trigger Search event logic).
  const productSearchInput = page
    .locator('form.woocommerce-product-search input[type="search"]:visible, form.woocommerce-product-search .search-field:visible')
    .first();
  if (await productSearchInput.count() > 0) {
    return productSearchInput;
  }

  const productSearchByHiddenInput = page
    .locator('form:has(input[name="post_type"][value="product"]) input[type="search"]:visible, form:has(input[name="post_type"][value="product"]) .search-field:visible')
    .first();
  if (await productSearchByHiddenInput.count() > 0) {
    return productSearchByHiddenInput;
  }

  const directSearchInput = page.locator('.search-field:visible, input[type="search"]:visible').first();
  if (await directSearchInput.count() > 0) {
    return directSearchInput;
  }

  const mobileMenuToggle = page.locator(
    '.menu-toggle:visible, button.menu-toggle:visible, .wc-block-mini-cart__button:visible'
  ).first();

  if (await mobileMenuToggle.count() > 0) {
    await mobileMenuToggle.click().catch(() => {});
    await page.waitForTimeout(TIMEOUTS.INSTANT);
  }

  // Keep this non-throwing so callers can skip when theme/browser layout has no search UI.
  const fallbackVisible = page.locator('.search-field:visible, input[type="search"]:visible').first();
  if (await fallbackVisible.count() > 0) {
    return fallbackVisible;
  }

  return null;
}

/**
 * Submit storefront search in a cross-browser-safe way.
 * WebKit/Safari can occasionally miss keypress-based submit handlers,
 * so prefer form submit button click when available.
 */
async function submitSearch(page, searchInput) {
  const initialUrl = page.url();
  const isSearchUrl = () => /[?&]s=/.test(page.url());
  const hasNavigated = () => page.url() !== initialUrl;

  // Ensure the form submits as a product search so plugin Search event hooks execute.
  await searchInput.evaluate((input) => {
    const form = input.closest('form');
    if (!form) {
      return;
    }

    let postType = form.querySelector('input[name="post_type"]');
    if (!postType) {
      postType = document.createElement('input');
      postType.type = 'hidden';
      postType.name = 'post_type';
      form.appendChild(postType);
    }
    postType.value = 'product';
  }).catch(() => {});

  // Capture typed query for deterministic fallback navigation.
  const query = await searchInput.inputValue().catch(() => '');

  // First try standard Enter key flow.
  await searchInput.press('Enter').catch(() => {});
  if (isSearchUrl()) {
    return;
  }

  // If URL still not a search URL, submit the nearest form in-page.
  const submitted = await searchInput.evaluate((input) => {
    try {
      const form = input.closest('form');
      if (!form) {
        return false;
      }

      // Prefer requestSubmit/submit to avoid pointer interception issues on WebKit.
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
        return true;
      }

      if (typeof form.submit === 'function') {
        form.submit();
        return true;
      }

      const evt = new Event('submit', { bubbles: true, cancelable: true });
      form.dispatchEvent(evt);
      return true;
    } catch (_) {
      return false;
    }
  }).catch(() => false);

  if (!submitted) {
    // Last chance: click submit control with force to bypass overlay/pointer intercept.
    const form = searchInput.locator('xpath=ancestor::form[1]');
    const submitButton = form.locator('button[type="submit"], input[type="submit"], .search-submit').first();
    if (await submitButton.count().catch(() => 0)) {
      await submitButton.click({ force: true }).catch(() => {});
    }
  }

  if (isSearchUrl() || hasNavigated()) {
    return;
  }

  // Give native submit/navigation a brief chance to settle before forcing fallback.
  // Without this, fast/async form submissions can still be in flight and we may
  // trigger the same search route twice, producing duplicate Search CAPI events.
  await page.waitForURL(/\?s=.*(?:&|\?)post_type=product/, { timeout: TIMEOUTS.NORMAL }).catch(() => {});
  if (isSearchUrl() || hasNavigated()) {
    return;
  }

  // WebKit/iOS fallback: if UI submit fails to navigate at all,
  // force deterministic product search URL so Search hook conditions are met.
  if (query && query.trim()) {
    await page.goto(`/?s=${encodeURIComponent(query.trim())}&post_type=product`);
  }
}

module.exports = {
  setProductTitle,
  setProductDescription,
  exactSearchSelect2Container,
  getVisibleSearchInput,
  submitSearch,
  dismissWooInterferingOverlays
};
