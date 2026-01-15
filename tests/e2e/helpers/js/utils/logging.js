/**
 * Logging and screenshot helpers for E2E tests
 */

/**
 * Safely take a screenshot
 * @param {import('@playwright/test').Page} page - Playwright page
 * @param {string} path - Screenshot path
 */
async function safeScreenshot(page, path) {
  try {
    if (page && !page.isClosed()) {
      await page.screenshot({ path, fullPage: true });
      console.log(`✅ Screenshot saved: ${path}`);
    } else {
      console.warn('⚠️ Cannot take screenshot - page is closed');
    }
  } catch (error) {
    console.log(`⚠️ Screenshot failed: ${error.message}`);
  }
}

/**
 * Log test start marker
 * @param {Object} testInfo - Playwright test info
 */
function logTestStart(testInfo) {
  const testName = testInfo.title;
  console.log('\n' + '='.repeat(80));
  console.log(`🚀 STARTING TEST: ${testName}`);
  console.log('='.repeat(80));
}

/**
 * Log test end marker
 * @param {Object} testInfo - Playwright test info
 * @param {boolean} success - Whether test passed
 */
function logTestEnd(testInfo, success = true) {
  const testName = testInfo.title;
  console.log('='.repeat(80));
  if (success) {
    console.log(`✅ TEST SUCCESS: ${testName} ✅`);
  } else {
    console.log(`❌ TEST FAILED: ${testName}`);
  }
  console.log('='.repeat(80) + '\n');
}

module.exports = {
  safeScreenshot,
  logTestStart,
  logTestEnd
};
