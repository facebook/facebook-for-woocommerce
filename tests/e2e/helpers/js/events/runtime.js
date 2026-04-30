/**
 * Event runtime helpers (captured payloads, cart/session cleanup, temp customers, checkout flow)
 */

const fs = require('fs').promises;
const path = require('path');
const { exec } = require('child_process');
const { promisify } = require('util');
const { expect } = require('@playwright/test');
const { TIMEOUTS } = require('../constants/timeouts');
const TestSetup = require('./setup');

const execAsync = promisify(exec);

function shellEscape(value) {
  return `'${String(value).replace(/'/g, `'"'"'`)}'`;
}

async function runWpCli(rawArgs) {
  const wpPath = process.env.WORDPRESS_PATH;
  if (!wpPath) throw new Error('WORDPRESS_PATH not set');

  const command = `php -n "$(which wp)" --path=${shellEscape(wpPath)} --allow-root ${rawArgs}`;
  const { stdout } = await execAsync(command, { cwd: __dirname, env: process.env });
  return stdout;
}

async function loadCapturedEvents(testId) {
  const baseDir = path.join(__dirname, '../../captured-events');
  const pixelPath = path.join(baseDir, `pixel-${testId}.json`);
  const capiPath = path.join(baseDir, `capi-${testId}.json`);

  const [pixelRaw, capiRaw] = await Promise.all([
    fs.readFile(pixelPath, 'utf8').catch(() => '[]'),
    fs.readFile(capiPath, 'utf8').catch(() => '[]')
  ]);

  return {
    pixel: JSON.parse(pixelRaw),
    capi: JSON.parse(capiRaw)
  };
}

function getLatestEvent(events, eventName) {
  const matching = (events || []).filter(event => event.event_name === eventName);
  return matching[matching.length - 1] || null;
}

function asArray(value) {
  if (Array.isArray(value)) return value;
  if (typeof value === 'string') {
    try {
      const parsed = JSON.parse(value);
      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return [];
    }
  }
  return [];
}

function assertEventContainsRetailerId(event, retailerId) {
  const expected = String(retailerId);
  const contentIds = asArray(event?.custom_data?.content_ids).map(String);
  const contents = asArray(event?.custom_data?.contents);

  expect(contentIds).toContain(expected);
  expect(contents.some(entry => String(entry.id) === expected)).toBe(true);
}

function ignoreKnownPurchaseUserDataGap(result) {
  const filteredErrors = (result?.errors || []).filter(error => error !== 'capi user_data.external_id missing');
  return {
    ...result,
    errors: filteredErrors,
    passed: filteredErrors.length === 0
  };
}

async function createTempCustomerUser() {
  const stamp = `${Date.now()}_${Math.random().toString(36).slice(2, 7)}`;
  const username = `e2e_customer_${stamp}`;
  const password = `E2Epass!${stamp}`;
  const email = `${username}@example.test`;

  const userIdRaw = await runWpCli(`user create ${shellEscape(username)} ${shellEscape(email)} --role=customer --user_pass=${shellEscape(password)} --porcelain`);
  const userId = Number(String(userIdRaw || '').trim());

  if (!userId) {
    throw new Error('Failed to create temporary customer user');
  }

  return {
    user_id: userId,
    username,
    password
  };
}

async function deleteTempCustomerUser(userId) {
  if (!userId) return;
  await runWpCli(`user delete ${Number(userId)} --yes`).catch(() => {});
}

async function getCartItemsViaStoreApi(page) {
  try {
    const payload = await page.evaluate(async () => {
      const response = await fetch('/wp-json/wc/store/v1/cart', {
        method: 'GET',
        credentials: 'include',
        headers: { Accept: 'application/json' }
      });

      if (!response.ok) {
        return { ok: false, status: response.status, items: [] };
      }

      const data = await response.json();
      return { ok: true, items: data?.items || [] };
    });

    return payload?.items || [];
  } catch {
    return [];
  }
}

async function loginWithCredentials(page, username, password) {
  await page.goto('/wp-login.php');

  const loginForm = page.locator('#loginform');
  await loginForm.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });

  await page.fill('#user_login', username);
  await page.fill('#user_pass', password);
  await page.click('#wp-submit');
  await page.waitForLoadState('networkidle');

  const isLoggedIn = await page.locator('#wpadminbar').count() > 0 || await page.locator('body.logged-in').count() > 0;
  if (!isLoggedIn) {
    throw new Error(`Failed login for temporary customer user: ${username}`);
  }
}

async function clearServerSideCartState(username) {
  if (!username) return;

  const escapedUsername = shellEscape(username);
  const php = [
    `$u = get_user_by('login', ${escapedUsername});`,
    'if ( $u ) {',
    '  $all = get_user_meta( $u->ID );',
    '  foreach ( array_keys( $all ) as $key ) {',
    "    if ( strpos( $key, '_woocommerce_persistent_cart_' ) === 0 ) {",
    '      delete_user_meta( $u->ID, $key );',
    '    }',
    '  }',
    '  global $wpdb;',
    "  $table = $wpdb->prefix . 'woocommerce_sessions';",
    '  $wpdb->delete( $table, array( "session_key" => (string) $u->ID ) );',
    '}',
    "echo 'cart_server_state_cleared';"
  ].join(' ');

  await runWpCli(`eval ${shellEscape(php)} --skip-plugins --skip-themes`).catch(() => {});
}

async function clearCart(page, credentials = null) {
  const context = page.context();

  await context.clearCookies();

  const username = credentials?.username || process.env.WP_CUSTOMER_USERNAME;
  await clearServerSideCartState(username);

  if (credentials?.username && credentials?.password) {
    await loginWithCredentials(page, credentials.username, credentials.password);
  } else {
    await TestSetup.login(page);
  }

  await page.goto('/');
  await page.evaluate(() => {
    try { window.localStorage.clear(); } catch (_) {}
    try { window.sessionStorage.clear(); } catch (_) {}
  });

  const hardClearViaStoreApi = async () => {
    await page.evaluate(async () => {
      try {
        await fetch('/wp-json/wc/store/v1/cart/items', {
          method: 'DELETE',
          credentials: 'include',
          headers: { Accept: 'application/json' }
        });
      } catch (_) {
        // ignore
      }
    });

    await page.waitForTimeout(TIMEOUTS.SHORT);
  };

  // Try multiple strategies because theme/cart implementations vary.
  for (let attempt = 0; attempt < 3; attempt++) {
    await page.goto('/cart?empty-cart=1');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(TIMEOUTS.INSTANT);

    for (let i = 0; i < 12; i++) {
      const removeButtons = page.locator(
        '.woocommerce a.remove, .wc-block-cart-item__remove-link, .wc-block-components-product-remove-button, [aria-label*="Remove"]'
      );

      const count = await removeButtons.count();
      if (count === 0) {
        break;
      }

      await removeButtons.first().click({ force: true });
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(TIMEOUTS.SHORT);
    }

    await hardClearViaStoreApi();

    const storeItems = await getCartItemsViaStoreApi(page);
    if (storeItems.length === 0) {
      return;
    }
  }

  const storeItems = await getCartItemsViaStoreApi(page);
  if (storeItems.length > 0) {
    const ids = storeItems.map(item => item.id).join(', ');
    throw new Error(`Unable to clear cart before running product-type event test. Remaining cart item IDs: ${ids}`);
  }
}

async function completeCheckoutFromCart(page) {
  await page.goto('/checkout');
  await TestSetup.waitForPageReady(page);

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
      await page.waitForTimeout(TIMEOUTS.INSTANT);
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

  await page.evaluate(() => window.scrollBy(0, 500));
  await page.waitForTimeout(TIMEOUTS.SHORT);

  await page.waitForSelector('.wc-block-components-radio-control__option[for="radio-control-wc-payment-method-options-cod"]', { state: 'visible', timeout: TIMEOUTS.LONG });
  await page.click('label[for="radio-control-wc-payment-method-options-cod"]');
  await page.waitForTimeout(TIMEOUTS.INSTANT);

  const termsCheckbox = page.locator('#wc-terms-and-conditions-checkbox-text').first();
  if (await termsCheckbox.isVisible({ timeout: TIMEOUTS.SHORT }).catch(() => false)) {
    await termsCheckbox.click();
  }

  const placeOrderButton = page.locator('.wc-block-components-checkout-place-order-button').first();
  await placeOrderButton.scrollIntoViewIfNeeded();
  await placeOrderButton.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
  await expect(placeOrderButton).toBeEnabled({ timeout: TIMEOUTS.LONG });

  console.log(`   ✅ Clicking place order`);
  await placeOrderButton.click();

  try {
    await page.waitForURL('**/checkout/order-received/**', { timeout: TIMEOUTS.EXTRA_LONG * 2 });
    await page.waitForTimeout(TIMEOUTS.NORMAL);
  } catch {
    const errorTexts = await page.locator('.wc-block-components-validation-error, .wc-block-components-notice-banner.is-error, .woocommerce-error').allTextContents();
    const trimmed = errorTexts.map(t => t.trim()).filter(Boolean).slice(0, 6);
    const buttonEnabled = await placeOrderButton.isEnabled().catch(() => false);
    const currentUrl = page.url();

    throw new Error(`Checkout did not complete. URL=${currentUrl}, placeOrderEnabled=${buttonEnabled}, errors=${JSON.stringify(trimmed)}`);
  }
}

module.exports = {
  loadCapturedEvents,
  getLatestEvent,
  asArray,
  assertEventContainsRetailerId,
  ignoreKnownPurchaseUserDataGap,
  createTempCustomerUser,
  deleteTempCustomerUser,
  getCartItemsViaStoreApi,
  clearCart,
  completeCheckoutFromCart
};
