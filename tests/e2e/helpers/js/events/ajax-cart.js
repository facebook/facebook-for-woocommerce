/**
 * AJAX cart helpers for E2E event tests.
 */

const { TIMEOUTS } = require('../constants/timeouts');

function isAjaxCartEndpoint(rawUrl, method = 'GET') {
  try {
    const url = new URL(rawUrl);
    const path = url.pathname || '';
    const wcAjax = url.searchParams.get('wc-ajax') || '';
    const restRoute = url.searchParams.get('rest_route') || '';
    const httpMethod = String(method || 'GET').toUpperCase();

    // Classic WooCommerce AJAX endpoint.
    if (wcAjax === 'add_to_cart' || path.includes('/wc-ajax/add_to_cart')) {
      return true;
    }

    // Blocks Store API add-to-cart (direct and query-style rest_route forms).
    if (
      (path.includes('/wp-json/wc/store/v1/cart/add-item') ||
        restRoute.includes('/wc/store/v1/cart/add-item')) &&
      httpMethod === 'POST'
    ) {
      return true;
    }

    // Blocks Store API batch (direct and query-style rest_route forms).
    if (
      (path.includes('/wp-json/wc/store/v1/batch') ||
        restRoute.includes('/wc/store/v1/batch')) &&
      httpMethod === 'POST'
    ) {
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
 *   expectedProductId?: string|number,
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
async function resolveShopLoopAjaxButton(page, options = {}) {
  const productUrl = options.productUrl || process.env.TEST_PRODUCT_URL;
  const shopUrl = options.shopUrl || '/shop';
  const productSlug = getProductSlug(productUrl);
  const expectedProductId = options.expectedProductId != null ? String(options.expectedProductId) : null;

  await page.goto(shopUrl);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(TIMEOUTS.INSTANT);

  const loopButtonSelector = [
    'a.add_to_cart_button.ajax_add_to_cart',
    'button.add_to_cart_button.ajax_add_to_cart',
    '.add_to_cart_button.ajax_add_to_cart'
  ].join(', ');

  const productCardSelector = [
    'li.product',
    'li.wc-block-grid__product',
    '.wc-block-grid__product',
    '.wc-block-product'
  ].join(', ');

  const shopFallbackSelector = `${productCardSelector} ${loopButtonSelector}, ${loopButtonSelector}`;

  let button = null;

  if (expectedProductId) {
    const productIdSelector = [
      `a.add_to_cart_button.ajax_add_to_cart[data-product_id="${expectedProductId}"]`,
      `button.add_to_cart_button.ajax_add_to_cart[data-product_id="${expectedProductId}"]`,
      `.add_to_cart_button.ajax_add_to_cart[data-product_id="${expectedProductId}"]`
    ].join(', ');

    const buttonByProductId = page.locator(productIdSelector).first();
    if (await buttonByProductId.count() > 0) {
      button = buttonByProductId;
    } else {
      return {
        available: false,
        button: null,
        details: {
          shopUrl,
          productUrl,
          productSlug,
          expectedProductId,
          selector: productIdSelector
        }
      };
    }
  }

  if (!button && productSlug) {
    const cardBySlug = page.locator(
      `${productCardSelector}:has(a[href*="/product/${productSlug}/"])`
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
    return {
      available: false,
      button: null,
      details: {
        shopUrl,
        productUrl,
        productSlug,
        expectedProductId,
        selector: shopFallbackSelector
      }
    };
  }

  await button.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });

  return {
    available: true,
    button,
    details: {
      shopUrl,
      productUrl,
      productSlug,
      expectedProductId,
      selector: shopFallbackSelector
    }
  };
}

async function isAjaxAddToCartAvailableOnShop(page, options = {}) {
  const resolved = await resolveShopLoopAjaxButton(page, options);
  return resolved.available;
}

async function triggerAjaxAddToCartFromShop(page, options = {}) {
  const resolved = await resolveShopLoopAjaxButton(page, options);

  if (!resolved.available) {
    const { shopUrl, productUrl, productSlug, expectedProductId, selector } = resolved.details;
    throw new Error(
      [
        'AJAX AddToCart test fixture is invalid: no shop-loop Add to cart button was found.',
        `shopUrl=${shopUrl}`,
        `productUrl=${productUrl || '(not provided)'}`,
        `productSlug=${productSlug || '(not derivable)'}`,
        `expectedProductId=${expectedProductId || '(not provided)'}`,
        `selector=${selector}`
      ].join(' ')
    );
  }

  const { button } = resolved;

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
  isAjaxAddToCartAvailableOnShop,
  getProductSlug,
  isAjaxCartEndpoint
};
