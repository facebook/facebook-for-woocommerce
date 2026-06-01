/**
 * Authentication helpers for E2E tests
 */

const { TIMEOUTS } = require('../constants/timeouts');

// Test configuration from environment variables
const baseURL = process.env.WORDPRESS_URL;
const username = process.env.WP_USERNAME;
const password = process.env.WP_PASSWORD;

/**
 * Login to WordPress admin
 * @param {import('@playwright/test').Page} page - Playwright page
 */
async function loginToWordPress(page) {
  if (!baseURL || !username || !password) {
    throw new Error('Missing required auth env vars: WORDPRESS_URL, WP_USERNAME, WP_PASSWORD');
  }

  await page.goto(`${baseURL}/wp-admin/`, { waitUntil: 'domcontentloaded', timeout: TIMEOUTS.MAX });

  const loggedInContent = page.locator('#wpcontent');
  const loginForm = page.locator('#user_login');

  const isLoggedIn = await loggedInContent.isVisible({ timeout: TIMEOUTS.NORMAL }).catch(() => false);
  if (isLoggedIn) {
    console.log('✅ Already logged in');
    return;
  }

  console.log('🔐 Playwright global login to WordPress may have failed. Attempting to login again...');

  // In customer-storage projects, /wp-admin can redirect to a non-login page.
  // Force wp-login endpoint to make login deterministic.
  const isLoginVisible = await loginForm.isVisible({ timeout: TIMEOUTS.SHORT }).catch(() => false);
  if (!isLoginVisible) {
    await page.goto(`${baseURL}/wp-login.php?redirect_to=${encodeURIComponent(`${baseURL}/wp-admin/`)}`, {
      waitUntil: 'domcontentloaded',
      timeout: TIMEOUTS.MAX,
    });
  }

  // If we landed in wp-admin directly after redirect, no need to submit login.
  const becameLoggedIn = await loggedInContent.isVisible({ timeout: TIMEOUTS.SHORT }).catch(() => false);
  if (becameLoggedIn) {
    console.log('✅ Logged in after redirect');
    return;
  }

  console.log('🔐 Logging in to WordPress...');
  await loginForm.waitFor({ state: 'visible', timeout: TIMEOUTS.MAX });
  await loginForm.fill(username);
  console.log('✅ Filled username');
  await page.locator('#user_pass').fill(password);
  console.log('✅ Filled password');
  const loginButton = page.locator('#wp-submit');
  await loginButton.waitFor({ state: 'visible', timeout: TIMEOUTS.MAX });
  console.log('✅ Found login button');
  await loginButton.waitFor({ state: 'attached', timeout: TIMEOUTS.MAX });
  console.log('✅ Login button is attached');
  await loginButton.click();
  console.log('✅ Clicked login button');

  await page.waitForLoadState('domcontentloaded', { timeout: TIMEOUTS.MAX });

  await loggedInContent.waitFor({ state: 'visible', timeout: TIMEOUTS.MAX }).catch(() => {
    console.warn('⚠️ Login failed - could not find admin content ' + page.url());
  });
  console.log('✅ Login completed ' + page.url());
}

module.exports = {
  baseURL,
  username,
  password,
  loginToWordPress
};
