const { expect } = require('@playwright/test');
const { TIMEOUTS } = require('./time-constants');

// Test configuration from environment variables
const baseURL = process.env.WORDPRESS_URL;
const username = process.env.WP_USERNAME;
const password = process.env.WP_PASSWORD;
const wpSitePath = process.env.WORDPRESS_PATH;

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
  await page.waitForURL(/\/wp-admin\/post\.php\?post=\d+/, { timeout: TIMEOUTS.LONG }).catch(() => {
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
    const { exec } = require('child_process');
    const { promisify } = require('util');
    const execAsync = promisify(exec);

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
    const { exec } = require('child_process');
    const { promisify } = require('util');
    const execAsync = promisify(exec);

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
  await locator.click();
  await locator.focus();
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
    const { exec } = require('child_process');
    const { promisify } = require('util');
    const execAsync = promisify(exec);
    await execAsync(
      `php -r "require_once('${wpSitePath}/wp-load.php'); wp_delete_term(${categoryId}, 'product_cat');"`,
      { cwd: __dirname }
    );
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
    const { exec } = require('child_process');
    const { promisify } = require('util');
    const execAsync = promisify(exec);

    // Create category using WP CLI
    const { stdout } = await execAsync(
      `php -r "require_once('${wpSitePath}/wp-load.php'); ` +
      `\\$term = wp_insert_term('${categoryName}', 'product_cat', array('description' => '${categoryDescription}')); ` +
      `if (is_wp_error(\\$term)) { echo json_encode(array('success' => false, 'error' => \\$term->get_error_message())); } ` +
      `else { ` +
      `  \\$category_id = \\$term['term_id']; ` +
      `  \\$category = get_term(\\$category_id, 'product_cat'); ` +
      `  echo json_encode(array('success' => true, 'category_id' => \\$category_id, 'category_name' => \\$category->name, 'message' => 'Category created successfully')); ` +
      `}"`,
      { cwd: __dirname }
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

// Helper function to ensure debug mode is enabled
async function ensureDebugModeEnabled(page) {
  console.log('🔍 Checking debug mode status...');

  // Navigate to Facebook settings page
  await page.goto(`${process.env.WORDPRESS_URL}/wp-admin/admin.php?page=wc-facebook`, {
    waitUntil: 'domcontentloaded',
    timeout: TIMEOUTS.EXTRA_LONG
  });

  // Click Troubleshooting toggle to expand drawer
  const troubleshootingToggle = page.locator('#toggle-troubleshooting-drawer');
  await troubleshootingToggle.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
  await troubleshootingToggle.click();
  await page.waitForTimeout(TIMEOUTS.INSTANT);

  // Check debug mode checkbox status
  const debugModeCheckbox = page.locator('#wc_facebook_enable_debug_mode');
  await debugModeCheckbox.waitFor({ state: 'visible', timeout: TIMEOUTS.MEDIUM });

  const isChecked = await debugModeCheckbox.isChecked();

  if (!isChecked) {
    console.log('⚙️ Enabling debug mode...');
    await debugModeCheckbox.check();

    // Save changes
    const saveButton = page.locator('input[name="save_shops_settings"]');
    await saveButton.click();
    await page.waitForLoadState('domcontentloaded');
    console.log('✅ Debug mode enabled');
    return false; // Was not enabled, now is
  } else {
    console.log('✅ Debug mode already enabled');
    return true; // Was already enabled
  }
}

module.exports = {
  baseURL,
  username,
  password,
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
  ensureDebugModeEnabled
};
