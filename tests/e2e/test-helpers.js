const { expect } = require('@playwright/test');
const { TIMEOUTS } = require('./time-constants');
const { exec } = require('child_process');
const { promisify } = require('util');

// Promisified exec for async/await usage
const execAsync = promisify(exec);

// Test configuration from environment variables
const baseURL = process.env.WORDPRESS_URL;
const username = process.env.WP_USERNAME;
const password = process.env.WP_PASSWORD;
const wpSitePath = process.env.WORDPRESS_PATH;

// Whitelist of allowed errors (non-critical) - read from environment
const ERROR_WHITELIST = process.env.ERROR_WHITELIST
  ? process.env.ERROR_WHITELIST.split('|').map(s => s.trim())
  : [];

// Helper function to execute WordPress PHP commands
async function execWP(phpCode) {
  return execAsync(
    `php -r "require_once('${wpSitePath}/wp-load.php'); ${phpCode}"`,
    { cwd: __dirname }
  );
}

// Helper function for reliable login
async function loginToWordPress(page) {
  // Navigate to login page
  await page.goto(`${baseURL}/wp-admin/`, { waitUntil: 'domcontentloaded', timeout: TIMEOUTS.MAX });

  // Check if we're already logged in by waiting for either login form or admin content
  const loggedInContent = page.locator('#wpcontent');
  const loginForm = page.locator('#user_login');

  const isLoggedIn = await loggedInContent.isVisible({ timeout: TIMEOUTS.NORMAL }).catch(() => false);
  if (isLoggedIn) {
    console.log('✅ Already logged in');
    return;
  }

  console.log('🔐 Playwright global login to WordPress may have failed. Attempting to login again...');

  // Fill login form
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

  // Wait for login to complete by waiting for admin content
  await loggedInContent.waitFor({ state: 'visible', timeout: TIMEOUTS.MAX }).catch(() => {
    console.warn('⚠️ Login failed - could not find admin content ' +  page.url());
  });
  console.log('✅ Login completed ' + page.url());
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
    await execWP(`wp_delete_post(${productId}, true);`);
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
  console.log(`🔍 Extracting Product ID from URL: ${url}`);
  const urlMatch = url.match(/post=(\d+)/);
  const productId = urlMatch ? parseInt(urlMatch[1]) : null;
  console.log(`✅ Extracted Product ID: ${productId}`);
  return productId;
}

// Helper function to publish product
async function publishProduct(page) {
  await page.locator('#publishing-action').scrollIntoViewIfNeeded();
  const publishButton = page.locator('#publish');
  await publishButton.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
  await publishButton.waitFor({ state: 'attached', timeout: TIMEOUTS.LONG });
  await publishButton.click();
  console.log('Clicked Publish button');
  let publishSuccess = true;
  await page.waitForURL(/\/wp-admin\/post\.php\?post=\d+/, { timeout: TIMEOUTS.EXTRA_LONG }).catch(() => {
    console.warn('⚠️ URL did not change after publishing. Current URL: ' + page.url())
    publishSuccess = false;
  }
  );

  if (!publishSuccess) {
    console.warn(`⚠️ Encountered Wordpress Publish button bug. Clicking Publish did not change url. Current url: ${page.url()} Clicking Publish button again`);
    await publishButton.click();
    await page.waitForTimeout(TIMEOUTS.MEDIUM);
  }

  let updateButton = page.getByRole('button', { name: 'Update' });
  await updateButton.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });

  await page.waitForLoadState('domcontentloaded', { timeout: TIMEOUTS.MAX });
  console.log('✅ Published product');
  return true;
}

// Helper function to check for PHP errors
async function checkForPhpErrors(page) {
  const pageContent = await page.content();
  expect(pageContent).not.toContain('Fatal error');
  expect(pageContent).not.toContain('Parse error');
}

// Helper function to check for JavaScript errors
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
    const isVisualTabVisible = await visualTab.isVisible({ timeout: TIMEOUTS.NORMAL }).catch(() => false);

    if (isVisualTabVisible) {
      await visualTab.click();

      // Wait for TinyMCE iframe to be ready
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

        // Wait for textarea to be ready
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

// Helper function to route to products table and filter by product type
async function filterProducts(page, productType, productSKU = null) {
  // Go to Products page
  console.log('📋 Navigating to Products page...');
  await page.goto(`${baseURL}/wp-admin/edit.php?post_type=product`, {
    waitUntil: 'domcontentloaded',
    timeout: TIMEOUTS.MAX
  });

  // Filter by product type
  console.log('🔍 Filtering by Simple product type...');
  const productTypeFilter = page.locator('select#dropdown_product_type');
  if (await productTypeFilter.isVisible({ timeout: TIMEOUTS.LONG })) {
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
    if (await searchBox.isVisible({ timeout: TIMEOUTS.LONG })) {
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
  await page.locator('.wp-list-table').waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
}

// Helper function to click the first visible product from products table
async function clickFirstProduct(page) {
  const firstProductRow = page.locator('.wp-list-table tbody tr.iedit').first();
  await firstProductRow.isVisible({ timeout: TIMEOUTS.LONG });
  // Extract product name from the row
  const productNameElement = firstProductRow.locator('.row-title');
  const productName = await productNameElement.textContent();
  console.log(`✅ Found product: "${productName}"`);

  // Click on product name to edit
  await productNameElement.click();
  await page.waitForLoadState('domcontentloaded', { timeout: TIMEOUTS.MAX });
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

// Helper function to validate category sync to Facebook product set
async function validateCategorySync(categoryId, categoryName = null, waitSeconds = 10, maxRetries = 6) {
  if (!categoryId) {
    console.warn('⚠️ No category ID provided for sync validation');
    return null;
  }

  const displayName = categoryName
    ? `"${categoryName}" (ID: ${categoryId})`
    : `ID: ${categoryId}`;
  console.log(`🔍 Validating category sync for ${displayName}...`);

  try {
    // Call the validator with --type=category flag
    const { stdout, stderr } = await execAsync(
      `php e2e-facebook-sync-validator.php --type=category ${categoryId} ${waitSeconds} ${maxRetries}`,
      { cwd: __dirname }
    );

    const result = JSON.parse(stdout);

    // Log results
    console.log('📄 OUTPUT FROM CATEGORY SYNC VALIDATOR:');
    const { debug, raw_data, ...resultWithoutDebug } = result;
    console.log(JSON.stringify(resultWithoutDebug, null, 2));

    if (result.success) {
      console.log(`🎉 Category Sync Validation Succeeded for ${displayName}`);
      console.log(`   Product Set ID: ${result.facebook_product_set_id}`);
      console.log(`   Retailer ID: ${result.retailer_id}`);
    } else {
      console.warn(`⚠️ Category Sync Validation Failed for ${displayName}`);
      if (result.error) {
        console.warn(`   Error: ${result.error}`);
      }
      if (result.mismatches && Object.keys(result.mismatches).length > 0) {
        console.warn(`   Mismatches: ${Object.keys(result.mismatches).length}`);
      }
    }

    return result;

  } catch (error) {
    console.warn(`⚠️ Category sync validation error: ${error.message}`);
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
  const categoryIds = options.categoryIds || [];

  console.log(`📦 Creating "${productType}" product via WooCommerce API: "${productName}"...`);

  try {
    const startTime = Date.now();

    // Call the product creator PHP script
    const categoryIdsJson = JSON.stringify(categoryIds);
    const { stdout } = await execAsync(
      `php e2e-product-creator.php "${productType}" "${productName}" ${price} ${stock} "${sku}" '${categoryIdsJson}'`,
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

      if (categoryIds.length > 0) {
        console.log(`   Categories: ${categoryIds.join(', ')}`);
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
  const facebookTabExists = await facebookTab.isVisible({ timeout: TIMEOUTS.LONG }).catch(() => false);

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
  facebookSyncField.waitFor({ state: 'visible', timeout: TIMEOUTS.MEDIUM });
  console.log('✅ Opened Product Facebook options tab');
}

// Helper function to quickly edit title and description of a product
async function setProductTitle(page, newTitle) {
  const titleField = page.locator('#title');
  titleField.waitFor({ state: 'visible', timeout: TIMEOUTS.MEDIUM });
  await titleField.scrollIntoViewIfNeeded();
  await titleField.fill(newTitle);
  console.log(`✅ Updated title to: "${newTitle}"`);
}

// Click on the Select2 container to open the dropdown
async function exactSearchSelect2Container(page, locator, searchValue) {
  await locator.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
  await locator.scrollIntoViewIfNeeded();
  await locator.focus();
  await page.waitForTimeout(TIMEOUTS.MEDIUM); // Wait for Select2 dropdown to open
  await locator.click();
  // Wait for 1 second to allow the Select2 dropdown to fully render after clicking.
  // Cannot use waitForLoadState('domcontentloaded') here because Select2 dropdown
  // is rendered dynamically via JavaScript without triggering a page load event.
  // The dropdown animation and DOM insertion happen asynchronously within the same page.
  await page.waitForTimeout(TIMEOUTS.SHORT);

  // Now locate and fill the search field
  await locator.pressSequentially(searchValue, { delay: 100 });

  // Select first result if available
  const firstResult = page.getByRole('option', { name: searchValue }).first();
  await firstResult.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG + TIMEOUTS.MEDIUM });
  await firstResult.click();
  await page.waitForLoadState('domcontentloaded');
  console.log(`✅ Selected ${searchValue} from Select2 dropdown`);
}

async function cleanupCategory(categoryId) {
  console.log(`🧹 Cleaning up category ${categoryId}...`);
  try {
    const startTime = new Date();
    await execWP(`wp_delete_term(${categoryId}, 'product_cat');`);
    console.log(`⏱️ Cleanup took ${new Date() - startTime}ms`);
    console.log(`✅ Category ${categoryId} deleted`);
  } catch (error) {
    console.log(`⚠️ Category cleanup failed: ${error.message}`);
  }
}

// Helper function to create a test category programmatically via WooCommerce API
async function createTestCategory(options = {}) {
  const categoryName = options.categoryName || generateUniqueSKU('Category');
  const categoryDescription = options.description || 'Test category for E2E testing';

  console.log(`📁 Creating category via WooCommerce API: "${categoryName}"...`);

  try {
    const startTime = Date.now();

    // Create category using WP CLI
    const { stdout } = await execWP(
      `\\$term = wp_insert_term('${categoryName}', 'product_cat', array('description' => '${categoryDescription}')); ` +
      `if (is_wp_error(\\$term)) { echo json_encode(array('success' => false, 'error' => \\$term->get_error_message())); } ` +
      `else { ` +
      `  \\$category_id = \\$term['term_id']; ` +
      `  \\$category = get_term(\\$category_id, 'product_cat'); ` +
      `  echo json_encode(array('success' => true, 'category_id' => \\$category_id, 'category_name' => \\$category->name, 'message' => 'Category created successfully')); ` +
      `}`
    );

    const result = JSON.parse(stdout);

    if (result.success) {
      console.log(`✅ ${result.message}`);
      console.log(`   Name: ${result.category_name}`);
      console.log(`   ID: ${result.category_id}`);

      const endTime = Date.now();
      console.log(`⏱️ Category creation took ${endTime - startTime}ms`);

      return {
        categoryId: result.category_id,
        categoryName: result.category_name
      };
    } else {
      throw new Error(`Category creation failed: ${result.error}`);
    }

  } catch (error) {
    console.log(`❌ Failed to create test category: ${error.message}`);
    throw error;
  }
}

// Helper function to generate product feed CSV file
async function generateProductFeedCSV(productCount = 10, variableProductPercentage = 0.3, categoryName = "feed-test-products") {
  const fs = require('fs');
  const path = require('path');
  const os = require('os');

  console.log(`📝 Generating product feed CSV with ${productCount} products (${Math.round(variableProductPercentage * 100)}% variable)...`);

  // CSV header
  const headers = [
    'ID', 'Type', 'SKU', 'Name', 'Published', 'Is featured?', 'Visibility in catalog',
    'Short description', 'Description', 'Date sale price starts', 'Date sale price ends',
    'Tax status', 'Tax class', 'In stock?', 'Stock', 'Low stock amount', 'Backorders allowed?',
    'Sold individually?', 'Weight (kg)', 'Length (cm)', 'Width (cm)', 'Height (cm)',
    'Allow customer reviews?', 'Purchase note', 'Sale price', 'Regular price', 'Categories',
    'Tags', 'Shipping class', 'Images', 'Download limit', 'Download expiry days', 'Parent',
    'Grouped products', 'Upsells', 'Cross-sells', 'External URL', 'Button text', 'Position',
    'Attribute 1 name', 'Attribute 1 value(s)', 'Attribute 1 visible', 'Attribute 1 global',
    'Attribute 2 name', 'Attribute 2 value(s)', 'Attribute 2 visible', 'Attribute 2 global'
  ];

  const rows = [headers];
  let productId = 1000; // Start from a high ID to avoid conflicts
  const runId = process.env.GITHUB_RUN_ID || 'local';
  const timestamp = new Date().getTime();

  // Calculate number of variable products
  const variableProductCount = Math.floor(productCount * variableProductPercentage);
  const simpleProductCount = productCount - variableProductCount;

  console.log(`   - ${simpleProductCount} simple products`);
  console.log(`   - ${variableProductCount} variable products`);

  // Generate simple products
  for (let i = 0; i < simpleProductCount; i++) {
    productId++;
    const sku = generateUniqueSKU('Simple');
    const name = `Feed Test Simple Product ${i + 1}`;
    const price = (Math.random() * 50 + 10).toFixed(2); // Random price between 10 and 60

    rows.push([
      productId, // ID
      'simple', // Type
      sku, // SKU
      name, // Name
      '1', // Published
      '0', // Is featured?
      'visible', // Visibility in catalog
      `Short description for ${name}`, // Short description
      `This is a test product created from feed file for E2E testing. Product number ${i + 1}.`, // Description
      '', // Date sale price starts
      '', // Date sale price ends
      'taxable', // Tax status
      '', // Tax class
      '1', // In stock?
      Math.floor(Math.random() * 100 + 10), // Stock (random 10-110)
      '', // Low stock amount
      '0', // Backorders allowed?
      '0', // Sold individually?
      '', // Weight (kg)
      '', // Length (cm)
      '', // Width (cm)
      '', // Height (cm)
      '1', // Allow customer reviews?
      '', // Purchase note
      '', // Sale price
      price, // Regular price
      categoryName, // Categories
      '', // Tags
      '', // Shipping class
      '', // Images
      '', // Download limit
      '', // Download expiry days
      '', // Parent
      '', // Grouped products
      '', // Upsells
      '', // Cross-sells
      '', // External URL
      '', // Button text
      '0', // Position
      '', // Attribute 1 name
      '', // Attribute 1 value(s)
      '', // Attribute 1 visible
      '', // Attribute 1 global
      '', // Attribute 2 name
      '', // Attribute 2 value(s)
      '', // Attribute 2 visible
      '' // Attribute 2 global
    ]);
  }

  // Generate variable products with variations
  for (let i = 0; i < variableProductCount; i++) {
    productId++;
    const parentId = productId;
    const sku = generateUniqueSKU('Variable');
    const name = `Feed Test Variable Product ${i + 1}`;
    const basePrice = (Math.random() * 50 + 20).toFixed(2);

    // Parent variable product
    rows.push([
      parentId, // ID
      'variable', // Type
      sku, // SKU
      name, // Name
      '1', // Published
      '0', // Is featured?
      'visible', // Visibility in catalog
      `Short description for ${name}`, // Short description
      `This is a variable test product created from feed file for E2E testing. Product number ${i + 1}.`, // Description
      '', // Date sale price starts
      '', // Date sale price ends
      'taxable', // Tax status
      '', // Tax class
      '1', // In stock?
      '', // Stock
      '', // Low stock amount
      '0', // Backorders allowed?
      '0', // Sold individually?
      '', // Weight (kg)
      '', // Length (cm)
      '', // Width (cm)
      '', // Height (cm)
      '1', // Allow customer reviews?
      '', // Purchase note
      '', // Sale price
      '', // Regular price
      categoryName, // Categories
      '', // Tags
      '', // Shipping class
      '', // Images
      '', // Download limit
      '', // Download expiry days
      '', // Parent
      '', // Grouped products
      '', // Upsells
      '', // Cross-sells
      '', // External URL
      '', // Button text
      '0', // Position
      'Color', // Attribute 1 name
      'Red, Blue, Green', // Attribute 1 value(s)
      '1', // Attribute 1 visible
      '1', // Attribute 1 global
      '', // Attribute 2 name
      '', // Attribute 2 value(s)
      '', // Attribute 2 visible
      '' // Attribute 2 global
    ]);

    // Generate variations for this variable product
    const colors = ['Red', 'Blue', 'Green'];
    let position = 1;

    for (const color of colors) {
      productId++;
      const variationPrice = (parseFloat(basePrice) + Math.random() * 10).toFixed(2);

      rows.push([
        productId, // ID
        'variation', // Type
        `${sku}-${color}`, // SKU
        `${name} - ${color}`, // Name
        '1', // Published
        '0', // Is featured?
        'visible', // Visibility in catalog
        '', // Short description
        `Variation: ${color}`, // Description
        '', // Date sale price starts
        '', // Date sale price ends
        'taxable', // Tax status
        'parent', // Tax class
        '1', // In stock?
        Math.floor(Math.random() * 50 + 5), // Stock
        '', // Low stock amount
        '0', // Backorders allowed?
        '0', // Sold individually?
        '', // Weight (kg)
        '', // Length (cm)
        '', // Width (cm)
        '', // Height (cm)
        '0', // Allow customer reviews?
        '', // Purchase note
        '', // Sale price
        variationPrice, // Regular price
        '', // Categories
        '', // Tags
        '', // Shipping class
        '', // Images
        '', // Download limit
        '', // Download expiry days
        `id:${parentId}`, // Parent
        '', // Grouped products
        '', // Upsells
        '', // Cross-sells
        '', // External URL
        '', // Button text
        position, // Position
        'Color', // Attribute 1 name
        color, // Attribute 1 value(s)
        '', // Attribute 1 visible
        '1', // Attribute 1 global
        '', // Attribute 2 name
        '', // Attribute 2 value(s)
        '', // Attribute 2 visible
        '' // Attribute 2 global
      ]);
      position++;
    }
  }

  // Convert to CSV format
  const csvContent = rows.map(row =>
    row.map(cell => {
      // Escape quotes and wrap in quotes if contains comma, quote, or newline
      const cellStr = String(cell);
      if (cellStr.includes(',') || cellStr.includes('"') || cellStr.includes('\n')) {
        return `"${cellStr.replace(/"/g, '""')}"`;
      }
      return cellStr;
    }).join(',')
  ).join('\n');

  // Save to temp directory
  const tempDir = os.tmpdir();
  const fileName = `product-feed-${runId}-${timestamp}.csv`;
  const filePath = path.join(tempDir, fileName);

  fs.writeFileSync(filePath, csvContent, 'utf8');

  console.log(`✅ Generated feed file: ${filePath}`);
  console.log(`   Total rows: ${rows.length} (including header)`);

  return {
    filePath,
    fileName,
    productCount: rows.length - 1, // Exclude header
    simpleProductCount,
    variableProductCount
  };
}

// Helper function to delete feed file
async function deleteFeedFile(filePath) {
  try {
    const fs = require('fs');
    if (fs.existsSync(filePath)) {
      fs.unlinkSync(filePath);
      console.log(`✅ Deleted feed file: ${filePath}`);
    }
  } catch (error) {
    console.log(`⚠️ Failed to delete feed file: ${error.message}`);
  }
}

// Helper function to generate product update CSV based on existing SKUs
async function generateProductUpdateCSV(existingProducts, categoryName = "feed-test-products") {
  const fs = require('fs');
  const path = require('path');
  const os = require('os');

  console.log(`📝 Generating product update CSV with ${existingProducts.length} products...`);

  // CSV header
  const headers = [
    'ID', 'Type', 'SKU', 'Name', 'Published', 'Is featured?', 'Visibility in catalog',
    'Short description', 'Description', 'Date sale price starts', 'Date sale price ends',
    'Tax status', 'Tax class', 'In stock?', 'Stock', 'Low stock amount', 'Backorders allowed?',
    'Sold individually?', 'Weight (kg)', 'Length (cm)', 'Width (cm)', 'Height (cm)',
    'Allow customer reviews?', 'Purchase note', 'Sale price', 'Regular price', 'Categories',
    'Tags', 'Shipping class', 'Images', 'Download limit', 'Download expiry days', 'Parent',
    'Grouped products', 'Upsells', 'Cross-sells', 'External URL', 'Button text', 'Position',
    'Attribute 1 name', 'Attribute 1 value(s)', 'Attribute 1 visible', 'Attribute 1 global',
    'Attribute 2 name', 'Attribute 2 value(s)', 'Attribute 2 visible', 'Attribute 2 global'
  ];

  const rows = [headers];
  const runId = process.env.GITHUB_RUN_ID || 'local';
  const timestamp = new Date().getTime();

  // Generate updated products using the same SKUs (only update price)
  for (const product of existingProducts) {
    const updatedPrice = (parseFloat(product.price) + 10).toFixed(2);

    rows.push([
      '', // ID (empty to match by SKU)
      product.type, // Type
      product.sku, // SKU (same as original)
      product.name, // Name (unchanged)
      '1', // Published
      '0', // Is featured?
      'visible', // Visibility in catalog
      `Short description for ${product.name}`, // Short description (unchanged)
      product.description, // Description (unchanged)
      '', // Date sale price starts
      '', // Date sale price ends
      'taxable', // Tax status
      '', // Tax class
      '1', // In stock?
      product.stock, // Stock (unchanged)
      '', // Low stock amount
      '0', // Backorders allowed?
      '0', // Sold individually?
      '', // Weight (kg)
      '', // Length (cm)
      '', // Width (cm)
      '', // Height (cm)
      '1', // Allow customer reviews?
      '', // Purchase note
      '', // Sale price
      updatedPrice, // Regular price (updated +10)
      categoryName, // Categories
      '', // Tags
      '', // Shipping class
      '', // Images
      '', // Download limit
      '', // Download expiry days
      '', // Parent
      '', // Grouped products
      '', // Upsells
      '', // Cross-sells
      '', // External URL
      '', // Button text
      '0', // Position
      '', // Attribute 1 name
      '', // Attribute 1 value(s)
      '', // Attribute 1 visible
      '', // Attribute 1 global
      '', // Attribute 2 name
      '', // Attribute 2 value(s)
      '', // Attribute 2 visible
      '' // Attribute 2 global
    ]);
  }

  // Convert to CSV format
  const csvContent = rows.map(row =>
    row.map(cell => {
      // Escape quotes and wrap in quotes if contains comma, quote, or newline
      const cellStr = String(cell);
      if (cellStr.includes(',') || cellStr.includes('"') || cellStr.includes('\n')) {
        return `"${cellStr.replace(/"/g, '""')}"`;
      }
      return cellStr;
    }).join(',')
  ).join('\n');

  // Save to temp directory
  const tempDir = os.tmpdir();
  const fileName = `product-update-${runId}-${timestamp}.csv`;
  const filePath = path.join(tempDir, fileName);

  fs.writeFileSync(filePath, csvContent, 'utf8');

  console.log(`✅ Generated update feed file: ${filePath}`);
  console.log(`   Total rows: ${rows.length} (including header)`);

  return {
    filePath,
    fileName,
    productCount: rows.length - 1 // Exclude header
  };
}

// Helper function to ensure debug mode is enabled
async function ensureDebugModeEnabled(page) {
  try {
    await page.goto(`${process.env.WORDPRESS_URL}/wp-admin/options.php`, {
      waitUntil: 'domcontentloaded',
      timeout: TIMEOUTS.EXTRA_LONG
    });

    const input = page.locator('#wc_facebook_enable_debug_mode');
    const inputExists = await input.count();

    // Get current value - empty string if option doesn't exist
    const currentValue = inputExists > 0 ? await input.inputValue() : '';

    if (currentValue !== 'yes') {
      console.log('🔧 Debug mode is not enabled, enabling it...');
      await execWP(`update_option('wc_facebook_enable_debug_mode', 'yes');`);
      console.log('✅ Debug mode enabled');
    } else {
      console.log('✅ Debug mode already enabled');
    }

    return true;
  } catch (error) {
    console.error(`❌ Error ensuring debug mode: ${error.message}`);
    return false;
  }
}

// Helper function to check WooCommerce logs for errors
async function checkWooCommerceLogs() {
  const { execSync } = require('child_process');
  console.log('🔍 Checking WooCommerce logs for errors...');

  const today = new Date().toISOString().split('T')[0];
  const logsDir = process.env.WC_LOG_PATH;

  if (!logsDir) {
    throw new Error('❌ WC_LOG_PATH environment variable not set');
  }

  const logFile = execSync(
    `find "${logsDir}" -name "facebook_for_woocommerce-${today}*.log" 2>/dev/null | head -1`,
    { encoding: 'utf8' }
  ).trim();

  if (!logFile) {
    console.log(`ℹ️ No log file found for today - ${today}`);
    return { success: true };
  }

  console.log(`📄 Checking: ${logFile}`);

  const non200Lines = execSync(
    `grep -n "code: " "${logFile}" | grep -v "code: 200" || true`,
    { encoding: 'utf8' }
  ).trim();

  if (non200Lines) {
    console.log(`❌ Found non-200 response codes in log file: ${logFile}`);
    console.log('Please check WooCommerce logs in Github Artifacts');

    // Check for critical log levels (ERROR, CRITICAL, ALERT, EMERGENCY)
    const criticalLogs = execSync(
      `grep -E "^[0-9T:+-]+ (ERROR|CRITICAL|ALERT|EMERGENCY) " "${logFile}" || true`,
      { encoding: 'utf8' }
    ).trim();

    if (criticalLogs) {
      console.log('\n❌ CRITICAL ERRORS FOUND IN LOGS:');
      console.log(criticalLogs);
    }

    return { success: false, error: 'Non-200 response codes found' };
  }

  console.log('✅ All response codes are 200');
  return { success: true };
}

// Helper function to complete a purchase flow
async function completePurchaseFlow(page, productUrl = null) {
  const url = productUrl || process.env.TEST_PRODUCT_URL;

  console.log(`   📦 Navigating to product page`);
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: TIMEOUTS.EXTRA_LONG });

  console.log(`   🛒 Adding product to cart`);
  await page.click('.single_add_to_cart_button');
  await page.waitForTimeout(TIMEOUTS.SHORT);

  console.log(`   💳 Navigating to checkout`);
  await page.goto('/checkout', { waitUntil: 'domcontentloaded', timeout: TIMEOUTS.EXTRA_LONG });
  await page.evaluate(() => window.scrollBy(0, 400));

  console.log(`   📝 Filling billing address from environment variables`);

  // Skip form fill if billing address already saved (Edit button visible)
  const editButton = page.locator('.wc-block-components-address-card__edit[aria-label="Edit billing address"]');
  if (await editButton.isVisible({ timeout: TIMEOUTS.SHORT }).catch(() => false)) {
    console.log(`   ✅ Billing address already saved, skipping form fill`);
  } else {
  // Fill in billing details from environment variables
  await page.fill('#billing-first_name', process.env.TEST_USER_FIRST_NAME );
  await page.fill('#billing-last_name', process.env.TEST_USER_LAST_NAME);
  await page.fill('#billing-address_1', process.env.TEST_USER_ADDRESS_1 );
  await page.fill('#billing-city', process.env.TEST_USER_CITY );

  // Select country (US)
  await page.selectOption('#billing-country', process.env.TEST_USER_COUNTRY);
  await page.waitForTimeout(TIMEOUTS.INSTANT); // Wait for state dropdown to populate

  // Select state (CA)
  await page.selectOption('#billing-state', process.env.TEST_USER_STATE  );
  await page.waitForTimeout(TIMEOUTS.INSTANT); // Wait for postcode field to be ready

  // Fill postcode (wait for it to be ready first)
  const postcodeField = page.locator('#billing-postcode');
  await postcodeField.waitFor({ state: 'visible', timeout: TIMEOUTS.NORMAL });
  await postcodeField.fill(process.env.TEST_USER_POSTCODE );

  // Fill phone (optional)
  if (process.env.TEST_USER_PHONE) {
    await page.fill('#billing-phone', process.env.TEST_USER_PHONE);
  }

  console.log(`   ✅ Billing address filled`);
  }

  console.log(`   💰 Selecting Cash on Delivery`);
  await page.waitForSelector('.wc-block-components-radio-control__option[for="radio-control-wc-payment-method-options-cod"]', {
    state: 'visible',
    timeout: TIMEOUTS.LONG
  });
  await page.click('label[for="radio-control-wc-payment-method-options-cod"]');
  await page.waitForTimeout(TIMEOUTS.INSTANT);

  console.log(`   ✅ Placing order`);
  await page.locator('.wc-block-components-checkout-place-order-button').scrollIntoViewIfNeeded();

  console.log(`   ⏳ Waiting for order to process...`);
  await page.click('.wc-block-components-checkout-place-order-button');
  await page.waitForURL(/\/checkout\/order-received\/\d+/, { timeout: TIMEOUTS.EXTRA_LONG });

  const orderReceivedUrl = page.url();
  console.log(`   ✅ Order completed: ${orderReceivedUrl}`);

  // Extract order ID from URL
  const orderIdMatch = orderReceivedUrl.match(/order-received\/(\d+)/);
  const orderId = orderIdMatch ? orderIdMatch[1] : null;

  return { orderReceivedUrl, orderId };
}

// Helper to get connection status
async function getConnectionStatus() {
  const { stdout } = await execWP(
    `\\$conn = facebook_for_woocommerce()->get_connection_handler();
    echo json_encode([
      'connected' => \\$conn->is_connected(),
      'pixel_id' => get_option('wc_facebook_pixel_id'),
      'catalog_id' => get_option('wc_facebook_product_catalog_id'),
      'facebook_config' => get_option('facebook_config'),
      'access_token' => get_option('wc_facebook_access_token'),
      'merchant_access_token' => get_option('wc_facebook_merchant_access_token'),
      'external_business_id' => \\$conn->get_external_business_id()
    ]);`
  );
  return JSON.parse(stdout);
}

// Helper function to disconnect from Facebook and verify cleanup
async function disconnectAndVerify() {
  console.log('🔌 Disconnecting from Facebook...');

  const before = await getConnectionStatus();
  if (!before.connected) {
    console.log('⚠️ Already disconnected');
    return { before, after: before, success: true, skipped: true };
  }

  // Execute disconnect
  await execWP(`facebook_for_woocommerce()->get_connection_handler()->disconnect();`);

  // Poll until disconnected (max 5 attempts)
  let after;
  for (let i = 0; i < 5; i++) {
    await new Promise(resolve => setTimeout(resolve, TIMEOUTS.LONG));
    after = await getConnectionStatus();

    const isDisconnected = !after.connected && !after.pixel_id && !after.access_token && !after.facebook_config && !after.merchant_access_token;
    if (isDisconnected) {
      console.log(`✅ Disconnected after ${i + 1} attempt(s)`);
      break;
    }
  }

  // Validate disconnection - check for any issues
  const failures = [];

  if (after.connected) failures.push('Still connected');
  if (after.pixel_id) failures.push('Pixel ID not cleared');
  if (after.facebook_config) failures.push('Config not deleted');
  if (after.access_token) failures.push('Access token not cleared');
  if (after.merchant_access_token) failures.push('Merchant token not cleared');
  if (after.external_business_id === before.external_business_id) failures.push('External ID not changed');

  if (failures.length > 0) {
    throw new Error('❌ Disconnection failed:\n   - ' + failures.join('\n   - '));
  }

  console.log('✅ Disconnection verified');
  return { before, after, success: true };
}

// Helper function to reconnect to Facebook (mimics workflow setup)
// enablePixel and enableS2S default to 'yes', pass 'no' to disable
async function reconnectAndVerify(options = {}) {
  const enablePixel = options.enablePixel ?? 'yes';
  const enableS2S = options.enableS2S ?? 'yes';

  console.log(`🔄 Reconnecting to Facebook (pixel=${enablePixel}, s2s=${enableS2S})...`);

  const before = await getConnectionStatus();
  if (before.connected) {
    console.log('⚠️ Already connected');
    return { before, after: before, success: true, skipped: true };
  }

  const creds = {
    accessToken: process.env.FB_ACCESS_TOKEN,
    businessManagerId: process.env.FB_BUSINESS_MANAGER_ID,
    externalBusinessId: process.env.FB_EXTERNAL_BUSINESS_ID,
    productCatalogId: process.env.FB_PRODUCT_CATALOG_ID,
    pixelId: process.env.FB_PIXEL_ID,
    pageId: process.env.FB_PAGE_ID
  };

  // Deactivate, set options, reactivate
  console.log('   Deactivating plugin...');
  await execWP(`deactivate_plugins('facebook-for-woocommerce/facebook-for-woocommerce.php');`);

  console.log('   Setting options...');
  const dbOptions = [
    ['wc_facebook_access_token', creds.accessToken],
    ['wc_facebook_merchant_access_token', creds.accessToken],
    ['wc_facebook_business_manager_id', creds.businessManagerId],
    ['wc_facebook_external_business_id', creds.externalBusinessId],
    ['wc_facebook_product_catalog_id', creds.productCatalogId],
    ['wc_facebook_pixel_id', creds.pixelId],
    ['wc_facebook_page_id', creds.pageId],
    ['wc_facebook_enable_server_to_server', enableS2S],
    ['wc_facebook_enable_pixel', enablePixel],
    ['wc_facebook_enable_advanced_matching', 'yes'],
    ['wc_facebook_debug_mode', 'yes'],
    ['wc_facebook_enable_debug_mode', 'yes'],
    ['wc_facebook_has_connected_fbe_2', 'yes'],
    ['wc_facebook_has_authorized_pages_read_engagement', 'yes'],
    ['wc_facebook_enable_product_sync', 'yes']
  ];

  for (const [name, value] of dbOptions) {
    await execWP(`update_option('${name}', '${value}');`);
  }

  // Step 4: Activate plugin to trigger initialization with new options
  console.log('🔄 Activating plugin to initialize connection...');
  await execWP(`activate_plugin('facebook-for-woocommerce/facebook-for-woocommerce.php');`);

  // Verify reconnection
  const after = await getConnectionStatus();
  const failures = [];

  if (!after.connected) failures.push('Not connected');
  if (after.pixel_id !== creds.pixelId) failures.push(`Pixel ID mismatch (expected: ${creds.pixelId}, got: ${after.pixel_id})`);
  if (!after.access_token) failures.push('Access token missing');
  if (after.external_business_id !== creds.externalBusinessId) failures.push(`External ID mismatch (expected: ${creds.externalBusinessId}, got: ${after.external_business_id})`);
  if (after.catalog_id !== creds.productCatalogId) failures.push(`Catalog ID mismatch (expected: ${creds.productCatalogId}, got: ${after.catalog_id})`);

  if (failures.length > 0) {
    throw new Error('❌ Reconnection failed:\n   - ' + failures.join('\n   - '));
  }

  console.log('✅ Reconnection verified');
  return { before, after, success: true, credentials: creds };
}

// Helper function to verify all products have Facebook fields cleared
async function verifyProductsFacebookFieldsCleared() {
  console.log('🔍 Verifying all product Facebook fields are cleared...');

  const { stdout } = await execWP(`
    \\$products = get_posts([
      'post_type' => ['product', 'product_variation'],
      'posts_per_page' => -1,
      'post_status' => 'any'
    ]);

    \\$issues = [];

    foreach (\\$products as \\$product) {
      \\$meta = get_post_meta(\\$product->ID);
      \\$fb_fields = [];

      foreach (\\$meta as \\$key => \\$value) {
        if (strpos(\\$key, 'fb_') === 0 || strpos(\\$key, '_fb_') === 0 || strpos(\\$key, 'facebook_') === 0) {
          if (!empty(\\$value[0])) {
            \\$fb_fields[\\$key] = \\$value[0];
          }
        }
      }

      if (!empty(\\$fb_fields)) {
        \\$issues[] = [
          'id' => \\$product->ID,
          'type' => \\$product->post_type,
          'fields' => \\$fb_fields
        ];
      }
    }

    echo json_encode([
      'success' => empty(\\$issues),
      'total' => count(\\$products),
      'issues' => \\$issues
    ]);
  `);

  const result = JSON.parse(stdout);

  console.log(`✅ Checked ${result.total} products and variations`);

  if (result.issues.length > 0) {
    console.log(`❌ Found ${result.issues.length} products with Facebook fields not cleared:`);
    result.issues.forEach(issue => {
      console.log(`   - Product ID ${issue.id} (${issue.type}):`);
      Object.entries(issue.fields).forEach(([key, value]) => {
        console.log(`     • ${key}: ${value}`);
      });
    });
    throw new Error(`${result.issues.length} products still have Facebook fields`);
  }

  console.log('✅ All product Facebook fields cleared');
  return { success: true, total: result.total };
}



// Helper function to install and activate a plugin from wordpress.org
async function installPlugin(slug) {
  console.log(`📦 Installing plugin: ${slug}...`);

  // Install and activate via WP-CLI
  await execAsync(
    `cd ${wpSitePath} && wp plugin install ${slug} --activate --allow-root 2>&1`,
    { cwd: __dirname }
  );

  // Verify plugin is active
  console.log(`   Verifying activation...`);
  const { stdout } = await execWP(
    `echo is_plugin_active('${slug}/${slug}.php') ? '1' : '0';`
  );

  if (stdout.trim() !== '1') {
    throw new Error(`Plugin ${slug} failed to activate`);
  }

  console.log(`✅ ${slug} installed and active`);
}

// Helper function to uninstall a plugin
async function uninstallPlugin(slug) {
  console.log(`🗑️ Uninstalling plugin: ${slug}...`);
  await execAsync(
    `cd ${wpSitePath} && wp plugin deactivate ${slug} --allow-root 2>&1 || true`,
    { cwd: __dirname }
  );
  await execAsync(
    `cd ${wpSitePath} && wp plugin uninstall ${slug} --allow-root 2>&1 || true`,
    { cwd: __dirname }
  );
  console.log(`✅ ${slug} uninstalled`);
}

// Helper to install mu-plugin that disables pixel tracking
async function installPixelBlockerMuPlugin() {
  const fs = require('fs');
  const path = require('path');
  
  console.log('🔧 Installing pixel blocker mu-plugin...');
  const muPluginDir = `${wpSitePath}/wp-content/mu-plugins`;
  const muPluginFile = `${muPluginDir}/e2e-pixel-blocker.php`;
  const code = `<?php\nadd_filter('facebook_for_woocommerce_integration_pixel_enabled', '__return_false', 999);\n`;

  console.log(`   Creating dir: ${muPluginDir}`);
  fs.mkdirSync(muPluginDir, { recursive: true });
  
  console.log(`   Writing: ${muPluginFile}`);
  fs.writeFileSync(muPluginFile, code);
  
  console.log('✅ Pixel blocker mu-plugin installed');
}

// Helper to remove the pixel blocker mu-plugin
async function removePixelBlockerMuPlugin() {
  const fs = require('fs');
  
  console.log('🧹 Removing pixel blocker mu-plugin...');
  const muPluginFile = `${wpSitePath}/wp-content/mu-plugins/e2e-pixel-blocker.php`;
  
  if (fs.existsSync(muPluginFile)) {
    fs.unlinkSync(muPluginFile);
  }
  console.log('✅ Pixel blocker mu-plugin removed');
}

module.exports = {
  baseURL,
  username,
  password,
  ERROR_WHITELIST,
  execWP,
  installPlugin,
  uninstallPlugin,
  installPixelBlockerMuPlugin,
  removePixelBlockerMuPlugin,
  loginToWordPress,
  safeScreenshot,
  cleanupProduct,
  cleanupCategory,
  generateProductName,
  generateUniqueSKU,
  extractProductIdFromUrl,
  publishProduct,
  checkForPhpErrors,
  logTestStart,
  logTestEnd,
  validateFacebookSync,
  validateCategorySync,
  createTestProduct,
  createTestCategory,
  setProductDescription,
  filterProducts,
  clickFirstProduct,
  openFacebookOptions,
  setProductTitle,
  exactSearchSelect2Container,
  generateProductFeedCSV,
  deleteFeedFile,
  generateProductUpdateCSV,
  ensureDebugModeEnabled,
  checkWooCommerceLogs,
  completePurchaseFlow,
  checkForJsErrors,
  disconnectAndVerify,
  reconnectAndVerify,
  verifyProductsFacebookFieldsCleared
};
