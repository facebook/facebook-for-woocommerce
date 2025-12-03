const { expect } = require('@playwright/test');

// Test configuration from environment variables
const baseURL = process.env.WORDPRESS_URL || 'http://localhost:8080';
const username = process.env.WP_USERNAME || 'admin';
const password = process.env.WP_PASSWORD || 'admin';
const wpSitePath = process.env.WP_SITE_PATH || '/tmp/wordpress';

// Timeout constants (in milliseconds)
const TIMEOUTS = {
  VERY_SHORT: 2000,
  SHORT: 5000,
  MEDIUM: 10000,
  LONG: 60000,
};

// Delay constants (in milliseconds)
const DELAYS = {
  AFTER_CLICK: 1000,
  AFTER_TAB_SWITCH: 2000,
  AFTER_PUBLISH: 3000,
  AFTER_SYNC: 5000,
  AFTER_VARIATION_SAVE: 3000,
  AFTER_ATTRIBUTE_SAVE: 5000,
  AFTER_VARIATION_GENERATION: 8000,
};

// CSS selectors
const SELECTORS = {
  ADMIN_CONTENT: '#wpcontent',
  LOGIN_USERNAME: '#user_login',
  LOGIN_PASSWORD: '#user_pass',
  LOGIN_SUBMIT: '#wp-submit',
  PRODUCT_TITLE: '#title',
  PRODUCT_DATA_PANEL: '#woocommerce-product-data',
  PUBLISH_BUTTON: '#publish',
  PUBLISH_ACTION: '#publishing-action',
  REGULAR_PRICE: '#_regular_price',
  SKU_FIELD: '#_sku',
  STOCK_FIELD: '#_stock',
  MANAGE_STOCK_CHECKBOX: '#_manage_stock',
  PRODUCTS_TABLE: '.wp-list-table',
  PRODUCT_ROW: '.wp-list-table tbody tr.iedit',
  ROW_TITLE: '.row-title',
  GENERAL_TAB: 'li.general_tab a',
  INVENTORY_TAB: 'li.inventory_tab a',
  VARIATIONS_TAB: 'li.variations_tab a',
  FACEBOOK_TAB: '.wc-tabs li.fb_commerce_tab_options a, a[href="#fb_commerce_tab"]',
  FACEBOOK_SYNC_MODE: '#wc_facebook_sync_mode',
  CONTENT_VISUAL_TAB: '#content-tmce',
  CONTENT_TEXT_TAB: '#content-html',
  CONTENT_IFRAME: '#content_ifr',
  CONTENT_TEXTAREA: '#content',
};

// Helper function for reliable login
async function loginToWordPress(page) {
  await page.goto(`${baseURL}/wp-admin/`, {
    waitUntil: 'domcontentloaded',
    timeout: TIMEOUTS.LONG
  });

  const loggedInContent = page.locator(SELECTORS.ADMIN_CONTENT);
  const loginForm = page.locator(SELECTORS.LOGIN_USERNAME);

  const isLoggedIn = await loggedInContent.isVisible({ timeout: TIMEOUTS.VERY_SHORT }).catch(() => false);
  if (isLoggedIn) {
    console.log('✅ Already logged in');
    return;
  }

  console.log('🔐 Logging in to WordPress...');
  await loginForm.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
  await loginForm.fill(username);
  await page.locator(SELECTORS.LOGIN_PASSWORD).fill(password);
  await page.locator(SELECTORS.LOGIN_SUBMIT).click();

  await loggedInContent.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
  console.log('✅ Login completed');
}

// Helper function to safely take screenshots
async function safeScreenshot(page, path) {
  try {
    // Check if page is still available
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

// cleanup function - Delete created product from WooCommerce
async function cleanupProduct(productId) {
  if (!productId) return;

  console.log(`🧹 Cleaning up product ${productId}...`);

  try {
    const startTime = new Date();
    const { exec } = require('child_process');
    const { promisify } = require('util');
    const execAsync = promisify(exec);

    const { stdout } = await execAsync(
      `php -r "require_once('${wpSitePath}/wp-load.php'); wp_delete_post(${productId}, true);"`,
      { cwd: __dirname }
    );
    const endTime = new Date();
    console.log(`⏱️ Cleanup took ${endTime - startTime}ms`);
    console.log(`✅ Product ${productId} deleted from WooCommerce`);
  } catch (error) {
    console.log(`⚠️ Cleanup failed: ${error.message}`);
  }
}

// Helper function to generate product name with timestamp and instance ID
function generateProductName(productType) {
  const now = new Date();
  const timestamp = now.toISOString().replace(/[:.]/g, '-').slice(0, 19);
  const runId = process.env.GITHUB_RUN_ID || 'local';
  return `Test ${productType.toUpperCase()} Product E2E ${timestamp}-${runId}`;
}

// Helper function to generate unique SKU for any product type
function generateUniqueSKU(productType) {
  const runId = process.env.GITHUB_RUN_ID || 'local';
  const randomSuffix = Math.random().toString(36).substring(2, 8);
  return `E2E-${productType.toUpperCase()}-${runId}-${randomSuffix}`;
}

// Helper function to extract product ID from URL
function extractProductIdFromUrl(url) {
  const urlMatch = url.match(/post=(\d+)/);
  const productId = urlMatch ? parseInt(urlMatch[1]) : null;
  console.log(`✅ Extracted Product ID: ${productId}`);
  return productId;
}

// Helper function to publish product
async function publishProduct(page) {
  try {
    await page.locator(SELECTORS.PUBLISH_ACTION).scrollIntoViewIfNeeded();
    const publishButton = page.locator(SELECTORS.PUBLISH_BUTTON);
    if (await publishButton.isVisible({ timeout: TIMEOUTS.MEDIUM })) {
      await publishButton.click();
      await page.waitForTimeout(DELAYS.AFTER_PUBLISH);
      console.log('✅ Published product');
      return true;
    }
  } catch (error) {
    console.warn(`⚠️ Publish step may be slow, continuing with error check ${error.message}`);
    return false;
  }
}

// Helper function to check for PHP errors
async function checkForPhpErrors(page) {
  const pageContent = await page.content();
  expect(pageContent).not.toContain('Fatal error');
  expect(pageContent).not.toContain('Parse error');
}

// Helper function to mark test start
function logTestStart(testInfo) {
  const testName = testInfo.title;
  console.log('\n' + '='.repeat(80));
  console.log(`🚀 STARTING TEST: ${testName}`);
  console.log('='.repeat(80));
}

// Helper function to mark test end
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

// Helper function to reliably set product description
async function setProductDescription(page, newDescription) {
  try {
    console.log('🔄 Attempting to set product description...');

    const visualTab = page.locator(SELECTORS.CONTENT_VISUAL_TAB);
    const isVisualTabVisible = await visualTab.isVisible({ timeout: TIMEOUTS.VERY_SHORT }).catch(() => false);

    if (isVisualTabVisible) {
      await visualTab.click();

      const tinyMCEFrame = page.locator(SELECTORS.CONTENT_IFRAME);
      await tinyMCEFrame.waitFor({ state: 'visible', timeout: TIMEOUTS.SHORT });

      const frameContent = tinyMCEFrame.contentFrame();
      const bodyElement = frameContent.locator('body');
      await bodyElement.waitFor({ state: 'visible', timeout: TIMEOUTS.SHORT });
      await bodyElement.fill(newDescription);
      console.log('✅ Added description via TinyMCE editor');
    } else {
      const textTab = page.locator(SELECTORS.CONTENT_TEXT_TAB);
      const isTextTabVisible = await textTab.isVisible({ timeout: TIMEOUTS.VERY_SHORT }).catch(() => false);

      if (isTextTabVisible) {
        await textTab.click();

        const contentTextarea = page.locator(SELECTORS.CONTENT_TEXTAREA);
        await contentTextarea.waitFor({ state: 'visible', timeout: TIMEOUTS.SHORT });
        await contentTextarea.fill(newDescription);
        console.log('✅ Added description via text editor');
      } else {
        const blockEditor = page.locator('.wp-block-post-content, .block-editor-writing-flow');
        const isBlockEditorVisible = await blockEditor.isVisible({ timeout: TIMEOUTS.VERY_SHORT }).catch(() => false);

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

// Helper function to route to products table and filter by product type
async function filterProducts(page, productType, productSKU = null) {
  console.log('📋 Navigating to Products page...');
  await page.goto(`${baseURL}/wp-admin/edit.php?post_type=product`, {
    waitUntil: 'domcontentloaded',
    timeout: TIMEOUTS.LONG
  });

  console.log('🔍 Filtering by Simple product type...');
  const productTypeFilter = page.locator('select#dropdown_product_type');
  if (await productTypeFilter.isVisible({ timeout: TIMEOUTS.MEDIUM })) {
    const filterButton = page.locator("#post-query-submit");
    await productTypeFilter.selectOption(productType.toLowerCase());
    await filterButton.click();
    await page.waitForLoadState('domcontentloaded');
    console.log('✅ Filtered by product type');
  } else {
    console.warn('⚠️ Product type filter not found, proceeding without filter');
  }

  if (productSKU) {
    console.log(`🔍 Searching for product with SKU: ${productSKU}`);
    const searchBox = page.locator('#post-search-input');
    if (await searchBox.isVisible({ timeout: TIMEOUTS.MEDIUM })) {
      await searchBox.fill(productSKU);
      const searchButton = page.locator('#search-submit');
      await searchButton.click();
      await page.waitForLoadState('domcontentloaded');
      console.log('✅ Searched for product by SKU');
    } else {
      console.warn('⚠️ Search box not found, cannot search by SKU');
    }
  }

  await page.locator(SELECTORS.PRODUCTS_TABLE).waitFor({ state: 'visible', timeout: TIMEOUTS.MEDIUM });
}

// Helper function to click the first visible product from products table
async function clickFirstProduct(page) {
  const firstProductRow = page.locator(SELECTORS.PRODUCT_ROW).first();
  await firstProductRow.isVisible({ timeout: TIMEOUTS.MEDIUM });

  const productNameElement = firstProductRow.locator(SELECTORS.ROW_TITLE);
  const productName = await productNameElement.textContent();
  console.log(`✅ Found product: "${productName}"`);

  await productNameElement.click();
  await page.waitForLoadState('domcontentloaded', { timeout: TIMEOUTS.LONG });
  console.log('✅ Opened product editor');
}

// Helper function to validate Facebook sync
async function validateFacebookSync(productId, productName, waitSeconds = 10, maxRetries = 6) {
  if (!productId) {
    console.warn('⚠️ No product ID provided for Facebook sync validation');
    return null;
  }

  const displayName = productName ? `"${productName}" (ID: ${productId})` : `ID: ${productId}`;
  console.log(`🔍 Validating Facebook sync for product ${displayName}...`);

  try {
    const { exec } = require('child_process');
    const { promisify } = require('util');
    const execAsync = promisify(exec);

    // Call the Facebook sync validator
    const { stdout, stderr } = await execAsync(
      `php e2e-facebook-sync-validator.php ${productId} ${waitSeconds} ${maxRetries}`,
      { cwd: __dirname }
    );

    const result = JSON.parse(stdout);
    // 📄 DUMP RAW JSON OUTPUT FROM VALIDATOR
    console.log('📄 OUTPUT FROM FACEBOOK SYNC VALIDATOR:');
    // Log everything in result except result["raw_data"]
    const { raw_data, ...resultWithoutRawData } = result;
    console.log(JSON.stringify(resultWithoutRawData, null, 2));

    // Display results
    if (result.success) {
      console.log(`🎉 Facebook Sync Validation Succeeded for ${displayName}:`);
    } else {
      console.warn(`⚠️ Facebook Sync Validation Failed.\nDepending on the test case, this may or may not be an actual error. Check the debug logs above.`);
    }

    return result;

  } catch (error) {
    console.warn(`⚠️ Facebook sync validation error: ${error.message}`);
    return null;
  }
}

// Helper function to create a test product programmatically via WooCommerce API (much faster than UI)
async function createTestProduct(options = {}) {
  const productType = options.productType || options.type || 'simple';
  const productName = options.productName || generateProductName(productType);
  const sku = options.sku || generateUniqueSKU(productType);
  const price = options.price || '19.99';
  const stock = options.stock || '10';

  console.log(`📦 Creating "${productType}" product via WooCommerce API: "${productName}"...`);

  try {
    const startTime = Date.now();
    const { exec } = require('child_process');
    const { promisify } = require('util');
    const execAsync = promisify(exec);

    // Call the product creator PHP script
    const { stdout } = await execAsync(
      `php e2e-product-creator.php "${productType}" "${productName}" ${price} ${stock} "${sku}"`,
      { cwd: __dirname }
    );

    const result = JSON.parse(stdout);

    if (result.success) {
      console.log(`✅ ${result.message}`);
      console.log(`   Name: ${result.product_name}`);
      console.log(`   SKU: ${result.sku}`);

      if (productType === 'simple') {
        console.log(`   Price: ${result.price}`);
        console.log(`   Stock: ${result.stock}`);
      }
      else {
        console.log(`   Variations: ${result.variation_count}`);
        console.log(`   VariationIds: ${result.variation_ids}`);
      }

      const endTime = Date.now();
      console.log(`⏱️ Test Product creation took ${endTime - startTime}ms`);

      return {
        productId: result.product_id,
        productName: result.product_name,
        price: result.price,
        stock: result.stock,
        sku: result.sku
      };
    } else {
      throw new Error(`Product creation failed: ${result.error}`);
    }

  } catch (error) {
    console.log(`❌ Failed to create test product: ${error.message}`);
    throw error;
  }
}

// Helper function to open Facebook options tab in product data
async function openFacebookOptions(page) {
  console.log('🔵 Clicking on Facebook tab...');
  const facebookTab = page.locator(SELECTORS.FACEBOOK_TAB);

  await page.locator(SELECTORS.PRODUCT_DATA_PANEL).scrollIntoViewIfNeeded();

  const facebookTabExists = await facebookTab.isVisible({ timeout: TIMEOUTS.MEDIUM }).catch(() => false);

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
  const facebookSyncField = page.locator(SELECTORS.FACEBOOK_SYNC_MODE);
  facebookSyncField.waitFor({ state: 'visible', timeout: TIMEOUTS.SHORT });
  console.log('✅ Opened Product Facebook options tab');
}

// Helper function to quickly edit title and description of a product
async function setProductTitle(page, newTitle) {
  const titleField = page.locator(SELECTORS.PRODUCT_TITLE);
  titleField.waitFor({ state: 'visible', timeout: TIMEOUTS.SHORT });
  await titleField.scrollIntoViewIfNeeded();
  await titleField.fill(newTitle);
  console.log(`✅ Updated title to: "${newTitle}"`);
}

// Helper function to wait for an element with visibility check
async function waitForElement(page, selector, timeout = TIMEOUTS.MEDIUM) {
  try {
    const element = page.locator(selector);
    await element.waitFor({ state: 'visible', timeout });
    return element;
  } catch (error) {
    console.warn(`⚠️ Element not found: ${selector}`);
    throw error;
  }
}

// Helper function to navigate to a product data tab
async function openProductTab(page, tabSelector, tabName) {
  console.log(`📑 Opening ${tabName} tab...`);
  const tab = page.locator(tabSelector);
  await tab.waitFor({ state: 'visible', timeout: TIMEOUTS.SHORT });
  await tab.click();
  await page.waitForTimeout(DELAYS.AFTER_TAB_SWITCH);
  console.log(`✅ Opened ${tabName} tab`);
}

// Helper function to select products in bulk by their IDs
async function selectProductsByIds(page, productIds) {
  const productRows = page.locator(SELECTORS.PRODUCT_ROW);
  const rowCount = await productRows.count();
  const selectedIds = [];

  for (let i = 0; i < rowCount; i++) {
    const row = productRows.nth(i);
    const checkbox = row.locator('input[type="checkbox"]');
    const checkboxId = await checkbox.getAttribute('id');
    const productIdMatch = checkboxId ? checkboxId.match(/cb-select-(\d+)/) : null;
    const productId = productIdMatch ? parseInt(productIdMatch[1]) : null;

    if (productId && productIds.includes(productId)) {
      await checkbox.check();
      selectedIds.push(productId);
      console.log(`✅ Selected product ID ${productId}`);

      if (selectedIds.length === productIds.length) {
        break;
      }
    }
  }

  return selectedIds;
}

module.exports = {
  baseURL,
  username,
  password,
  TIMEOUTS,
  DELAYS,
  SELECTORS,
  loginToWordPress,
  safeScreenshot,
  cleanupProduct,
  generateProductName,
  generateUniqueSKU,
  extractProductIdFromUrl,
  publishProduct,
  checkForPhpErrors,
  logTestStart,
  logTestEnd,
  validateFacebookSync,
  createTestProduct,
  setProductDescription,
  filterProducts,
  clickFirstProduct,
  openFacebookOptions,
  setProductTitle,
  waitForElement,
  openProductTab,
  selectProductsByIds
};
