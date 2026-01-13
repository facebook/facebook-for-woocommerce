/**
 * Error checking helpers for E2E tests
 */

const { expect } = require('@playwright/test');

// Whitelist of allowed errors (non-critical) - read from environment
const ERROR_WHITELIST = process.env.ERROR_WHITELIST
  ? process.env.ERROR_WHITELIST.split('|').map(s => s.trim())
  : [];

/**
 * Check page for PHP errors
 * @param {import('@playwright/test').Page} page - Playwright page
 */
async function checkForPhpErrors(page) {
  const pageContent = await page.content();
  expect(pageContent).not.toContain('Fatal error');
  expect(pageContent).not.toContain('Parse error');
}

/**
 * Set up JavaScript error monitoring
 * @param {import('@playwright/test').Page} page - Playwright page
 * @returns {string[]} Array to collect errors
 */
function checkForJsErrors(page) {
  const errors = [];
  page.on('pageerror', error => {
    const errorMsg = `JS Error: ${error.message}`;
    // Only add if not in whitelist
    if (!ERROR_WHITELIST.some(whitelisted => errorMsg.includes(whitelisted))) {
      errors.push(errorMsg);
    }
  });
  return errors;
}

module.exports = {
  ERROR_WHITELIST,
  checkForPhpErrors,
  checkForJsErrors
};
