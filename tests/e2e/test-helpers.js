const { expect } = require('@playwright/test');

// Test configuration from environment variables
const baseURL = process.env.WORDPRESS_URL;
const username = process.env.WP_USERNAME;
const password = process.env.WP_PASSWORD;
const wpSitePath = process.env.WORDPRESS_PATH;

// Helper function for reliable login
async function loginToWordPress(page) {
  // Navigate to login page
  await page.goto(`${baseURL}/wp-admin/`, { waitUntil: 'domcontentloaded', timeout: 60000 });

  // Check if we're already logged in by waiting for either login form or admin content
  const loggedInContent = page.locator('#wpcontent');
  const loginForm = page.locator('#user_login');

  const isLoggedIn = await loggedInContent.isVisible({ timeout: 2000 }).catch(() => false);
  if (isLoggedIn) {
    console.log('✅ Already logged in');
    return;
  }

  // Fill login form
  console.log('🔐 Logging in to WordPress...');
  await loginForm.waitFor({ state: 'visible', timeout: 60000 });
  await loginForm.fill(username);
  await page.locator('#user_pass').fill(password);
  await page.locator('#wp-submit').click();

  // Wait for login to complete by waiting for admin content
  await loggedInContent.waitFor({ state: 'visible', timeout: 60000 });
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
    await page.locator('#publishing-action').scrollIntoViewIfNeeded();
    const publishButton = page.locator('#publish');
    if (await publishButton.isVisible({ timeout: 10000 })) {
      await publishButton.click();
      await page.waitForTimeout(3000); // Wait for publish to complete
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

    // First, try the visual/TinyMCE editor
    const visualTab = page.locator('#content-tmce');
    const isVisualTabVisible = await visualTab.isVisible({ timeout: 2000 }).catch(() => false);

    if (isVisualTabVisible) {
      await visualTab.click();

      // Wait for TinyMCE iframe to be ready
      const tinyMCEFrame = page.locator('#content_ifr');
      await tinyMCEFrame.waitFor({ state: 'visible', timeout: 5000 });

      const frameContent = tinyMCEFrame.contentFrame();
      const bodyElement = frameContent.locator('body');
      await bodyElement.waitFor({ state: 'visible', timeout: 5000 });
      await bodyElement.fill(newDescription);
      console.log('✅ Added description via TinyMCE editor');
    } else {
      // Try text/HTML tab
      const textTab = page.locator('#content-html');
      const isTextTabVisible = await textTab.isVisible({ timeout: 2000 }).catch(() => false);

      if (isTextTabVisible) {
        await textTab.click();

        // Wait for textarea to be ready
        const contentTextarea = page.locator('#content');
        await contentTextarea.waitFor({ state: 'visible', timeout: 3000 });
        await contentTextarea.fill(newDescription);
        console.log('✅ Added description via text editor');
      } else {
        // Try block editor if present
        const blockEditor = page.locator('.wp-block-post-content, .block-editor-writing-flow');
        const isBlockEditorVisible = await blockEditor.isVisible({ timeout: 2000 }).catch(() => false);

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
  // Go to Products page
  console.log('📋 Navigating to Products page...');
  await page.goto(`${baseURL}/wp-admin/edit.php?post_type=product`, {
    waitUntil: 'domcontentloaded',
    timeout: 60000
  });

  // Filter by product type
  console.log('🔍 Filtering by Simple product type...');
  const productTypeFilter = page.locator('select#dropdown_product_type');
  if (await productTypeFilter.isVisible({ timeout: 10000 })) {
    const filterButton = page.locator("#post-query-submit");
    await productTypeFilter.selectOption(productType.toLowerCase());
    await filterButton.click();
    await page.waitForLoadState('domcontentloaded');
    console.log('✅ Filtered by product type');
  } else {
    console.warn('⚠️ Product type filter not found, proceeding without filter');
  }

  // If productSKU is provided, search for it
  if (productSKU) {
    console.log(`🔍 Searching for product with SKU: ${productSKU}`);
    const searchBox = page.locator('#post-search-input');
    if (await searchBox.isVisible({ timeout: 10000 })) {
      await searchBox.fill(productSKU);
      const searchButton = page.locator('#search-submit');
      await searchButton.click();
      await page.waitForLoadState('domcontentloaded');
      console.log('✅ Searched for product by SKU');
    } else {
      console.warn('⚠️ Search box not found, cannot search by SKU');
    }
  }

  // Wait for products table to load
  await page.locator('.wp-list-table').waitFor({ state: 'visible', timeout: 10000 });
}

// Helper function to click the first visible product from products table
async function clickFirstProduct(page) {
  const firstProductRow = page.locator('.wp-list-table tbody tr.iedit').first();
  await firstProductRow.isVisible({ timeout: 10000 });
  // Extract product name from the row
  const productNameElement = firstProductRow.locator('.row-title');
  const productName = await productNameElement.textContent();
  console.log(`✅ Found product: "${productName}"`);

  // Click on product name to edit
  await productNameElement.click();
  await page.waitForLoadState('domcontentloaded', { timeout: 60000 });
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
  const productType = options.productType || 'simple';
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
  // Click on "Facebook" tab in product data
  console.log('🔵 Clicking on Facebook tab...');
  const facebookTab = page.locator('.wc-tabs li.fb_commerce_tab_options a, a[href="#fb_commerce_tab"]');

  // Scroll to product data section first
  await page.locator('#woocommerce-product-data').scrollIntoViewIfNeeded();

  // Check if Facebook tab exists
  const facebookTabExists = await facebookTab.isVisible({ timeout: 10000 }).catch(() => false);

  if (!facebookTabExists) {
    console.warn('⚠️ Facebook tab not found. This might indicate:');
    console.warn('   - Facebook for WooCommerce plugin not properly activated');
    console.warn('   - Plugin not connected to Facebook catalog');

    // Take screenshot for debugging
    await safeScreenshot(page, 'facebook-tab-not-found.png');

    // Try to find any tab that might be Facebook-related
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
  facebookSyncField.waitFor({ state: 'visible', timeout: 5000 });
  console.log('✅ Opened Product Facebook options tab');
}

// Helper function to quickly edit title and description of a product
async function setProductTitle(page, newTitle) {
  const titleField = page.locator('#title');
  titleField.waitFor({ state: 'visible', timeout: 5000 });
  await titleField.scrollIntoViewIfNeeded();
  await titleField.fill(newTitle);
  console.log(`✅ Updated title to: "${newTitle}"`);
}

// Click on the Select2 container to open the dropdown
async function exactSearchSelect2Container(page, locator, searchValue) {
  await locator.waitFor({ state: 'visible', timeout: 10000 });
  await locator.click();
  await locator.focus();
  // Wait for 1 second to allow the Select2 dropdown to fully render after clicking.
  // Cannot use waitForLoadState('domcontentloaded') here because Select2 dropdown
  // is rendered dynamically via JavaScript without triggering a page load event.
  // The dropdown animation and DOM insertion happen asynchronously within the same page.
  await page.waitForTimeout(1000);

  // Now locate and fill the search field
  await locator.pressSequentially(searchValue, { delay: 100 });

  // Select first result if available
  const firstResult = page.getByRole('option', { name: searchValue }).first();
  await firstResult.waitFor({ state: 'visible', timeout: 15000 });
  await firstResult.click();
  await page.waitForLoadState('domcontentloaded');
  console.log(`✅ Selected ${searchValue} from Select2 dropdown`);
}

module.exports = {
  baseURL,
  username,
  password,
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
  exactSearchSelect2Container
};
