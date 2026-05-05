/**
 * AJAX cart helpers for E2E event tests.
 */

const { TIMEOUTS } = require('../constants/timeouts');

function isAjaxCartEndpoint(rawUrl, method = 'GET') {
  try {
    const url = new URL(rawUrl);
    const path = url.pathname || '';
    const wcAjax = url.searchParams.get('wc-ajax') || '';
    const httpMethod = String(method || 'GET').toUpperCase();

    // Classic WooCommerce AJAX endpoint.
    if (wcAjax === 'add_to_cart' || path.includes('/wc-ajax/add_to_cart')) {
      return true;
    }

    // Blocks Store API AJAX add-to-cart endpoint.
    if (path.includes('/wp-json/wc/store/v1/cart/add-item') && httpMethod === 'POST') {
      return true;
    }

    return false;
  } catch (_) {
    return false;
  }
}

function getProductSlug(productUrl) {
  if (!productUrl) return null;

  try {
    const parsed = new URL(productUrl);
    const segments = parsed.pathname.split('/').filter(Boolean);
    const productIdx = segments.findIndex(segment => segment === 'product');
    if (productIdx >= 0 && segments[productIdx + 1]) {
      return segments[productIdx + 1];
    }

    return segments[segments.length - 1] || null;
  } catch (_) {
    return null;
  }
}

/**
 * Triggers add-to-cart from the shop loop and captures whether AJAX transport was used.
 *
 * @param {import('@playwright/test').Page} page
 * @param {{
 *   productUrl?: string,
 *   shopUrl?: string,
 * }} options
 * @returns {Promise<{
 *   usedAjax: boolean,
 *   mainFrameNavigated: boolean,
 *   beforeUrl: string,
 *   afterUrl: string,
 *   ajaxRequests: Array<{method: string, url: string}>,
 *   ajaxResponses: Array<{status: number, ok: boolean, url: string}>
 * }>}
 */
async function triggerAjaxAddToCartFromShop(page, options = {}) {
  const productUrl = options.productUrl || process.env.TEST_PRODUCT_URL;
  const shopUrl = options.shopUrl || '/shop';
  const productSlug = getProductSlug(productUrl);

  await page.goto(shopUrl);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(TIMEOUTS.INSTANT);

  const loopButtonSelector = 'a.add_to_cart_button.ajax_add_to_cart';
  const shopFallbackSelector = `li.product ${loopButtonSelector}`;

  let button = null;

  if (productSlug) {
    const cardBySlug = page.locator(
      `li.product:has(a[href*="/product/${productSlug}/"])`
    ).first();

    if (await cardBySlug.count() > 0) {
      const preferredButton = cardBySlug.locator(loopButtonSelector).first();

      if (await preferredButton.count() > 0) {
        button = preferredButton;
      }
    }
  }

  if (!button) {
    button = page.locator(shopFallbackSelector).first();
  }

  if (await button.count() === 0) {
    throw new Error(
      [
        'AJAX AddToCart test fixture is invalid: no shop-loop Add to cart button was found.',
        `shopUrl=${shopUrl}`,
        `productUrl=${productUrl || '(not provided)'}`,
        `productSlug=${productSlug || '(not derivable)'}`,
        `selector=${shopFallbackSelector}`
      ].join(' ')
    );
  }

  await button.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });

  const ajaxRequests = [];
  const ajaxResponses = [];
  const mainFrameNavigations = [];

  const onRequest = (request) => {
    if (!isAjaxCartEndpoint(request.url(), request.method())) return;
    ajaxRequests.push({ method: request.method(), url: request.url() });
  };

  const onResponse = (response) => {
    const method = response.request()?.method?.() || 'GET';
    if (!isAjaxCartEndpoint(response.url(), method)) return;
    ajaxResponses.push({ status: response.status(), ok: response.ok(), url: response.url() });
  };

  const onFrameNavigated = (frame) => {
    if (frame === page.mainFrame()) {
      mainFrameNavigations.push(frame.url());
    }
  };

  page.on('request', onRequest);
  page.on('response', onResponse);
  page.on('framenavigated', onFrameNavigated);

  const beforeUrl = page.url();

  try {
    await button.click({ force: true });
    await page.waitForTimeout(TIMEOUTS.NORMAL);
    await page.waitForLoadState('networkidle').catch(() => {});
  } finally {
    page.off('request', onRequest);
    page.off('response', onResponse);
    page.off('framenavigated', onFrameNavigated);
  }

  const afterUrl = page.url();

  return {
    usedAjax: ajaxRequests.length > 0 || ajaxResponses.length > 0,
    mainFrameNavigated: mainFrameNavigations.length > 0 || afterUrl !== beforeUrl,
    beforeUrl,
    afterUrl,
    ajaxRequests,
    ajaxResponses
  };
}

module.exports = {
  triggerAjaxAddToCartFromShop,
  getProductSlug,
  isAjaxCartEndpoint
};
