/**
 * UI interaction helpers for E2E tests
 */

const { TIMEOUTS } = require('../constants/timeouts');

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

module.exports = {
  setProductTitle,
  setProductDescription,
  exactSearchSelect2Container
};
