/**
 * Facebook options UI helpers for E2E tests
 */

const { TIMEOUTS } = require('../constants/timeouts');
const { safeScreenshot } = require('../utils/logging');

/**
 * Open Facebook options tab in product data
 * @param {import('@playwright/test').Page} page - Playwright page
 */
async function openFacebookOptions(page) {
  console.log('🔵 Clicking on Facebook tab...');
  const facebookTab = page.locator('.wc-tabs li.fb_commerce_tab_options a, a[href="#fb_commerce_tab"]');

  await page.locator('#woocommerce-product-data').scrollIntoViewIfNeeded();

  const facebookTabExists = await facebookTab.isVisible({ timeout: TIMEOUTS.LONG }).catch(() => false);

  if (!facebookTabExists) {
    console.warn('⚠️ Facebook tab not found. This might indicate:');
    console.warn('   - Facebook for WooCommerce plugin not properly activated');
    console.warn('   - Plugin not connected to Facebook catalog');

    await safeScreenshot(page, 'facebook-tab-not-found.png');

    const allTabs = await page.locator('.wc-tabs li a').all();
    console.log(`Found ${allTabs.length} tabs in product data`);

    for (let i = 0; i < allTabs.length; i++) {
      const tabText = await allTabs[i].textContent();
      console.log(`  Tab ${i}: ${tabText}`);
    }

    throw new Error('Facebook tab not found in product data metabox');
  }

  await facebookTab.click();
  const facebookSyncField = page.locator('#wc_facebook_sync_mode');
  await facebookSyncField.waitFor({ state: 'visible', timeout: TIMEOUTS.MEDIUM });
  console.log('✅ Opened Product Facebook options tab');
}

module.exports = {
  openFacebookOptions
};
