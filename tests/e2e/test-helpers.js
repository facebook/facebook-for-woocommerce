const { expect } = require('@playwright/test');

// Test configuration from environment variables
const baseURL = process.env.WORDPRESS_URL || 'http://localhost:8080';
const username = process.env.WP_USERNAME || 'admin';
const password = process.env.WP_PASSWORD || 'admin';

// Helper function for reliable login
async function loginToWordPress(page) {
  // Navigate to login page
  await page.goto(`${baseURL}/wp-admin/`, { waitUntil: 'networkidle', timeout: 120000 });

  // Check if we're already logged in
  const isLoggedIn = await page.locator('#wpcontent').isVisible({ timeout: 5000 });
  if (isLoggedIn) {
    console.log('✅ Already logged in');
    return;
  }

  // Fill login form - wait longer for login elements
  console.log('🔐 Logging in to WordPress...');
  await page.waitForSelector('#user_login', { timeout: 120000 });
  await page.fill('#user_login', username);
  await page.fill('#user_pass', password);
  await page.click('#wp-submit');

  // Wait for login to complete
  await page.waitForLoadState('networkidle', { timeout: 120000 });
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
    const { exec } = require('child_process');
    const { promisify } = require('util');
    const execAsync = promisify(exec);

    const { stdout } = await execAsync(
      `php -r "require_once('/tmp/wordpress/wp-load.php'); wp_delete_post(${productId}, true);"`,
      { cwd: __dirname }
    );

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
    if (await publishButton.isVisible({ timeout: 120000 })) {
      await publishButton.click();
      await page.waitForTimeout(3000);
      console.log('✅ Published product');
      return true;
    }
  } catch (error) {
    console.warn('⚠️ Publish step may be slow, continuing with error check');
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
  // Try to add description - handle different editor types
  try {
    console.log('🔄 Attempting to add product description...');

    // First, try the visual/TinyMCE editor
    const visualTab = page.locator('#content-tmce');
    if (await visualTab.isVisible({ timeout: 5000 })) {
      await visualTab.click();
      await page.waitForTimeout(2000);

      // Check if TinyMCE iframe exists
      const tinyMCEFrame = page.locator('#content_ifr');
      if (await tinyMCEFrame.isVisible({ timeout: 5000 })) {
        // This is an iframe-based editor (TinyMCE)
        const frameContent = tinyMCEFrame.contentFrame();
        const bodyElement = frameContent.locator('body');
        if (await bodyElement.isVisible({ timeout: 5000 })) {
          await bodyElement.fill(newDescription);
          console.log('✅ Added description via TinyMCE editor');
        }
      }
    } else {
      // Try text/HTML tab
      const textTab = page.locator('#content-html');
      if (await textTab.isVisible({ timeout: 5000 })) {
        await textTab.click();
        await page.waitForTimeout(1000);

        // Regular textarea
        const contentTextarea = page.locator('#content');
        if (await contentTextarea.isVisible({ timeout: 5000 })) {
          await contentTextarea.fill(newDescription);
          console.log('✅ Added description via text editor');
        }
      } else {
        // Try block editor if present
        const blockEditor = page.locator('.wp-block-post-content, .block-editor-writing-flow');
        if (await blockEditor.isVisible({ timeout: 5000 })) {
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


// Helper function to validate Facebook sync
async function validateFacebookSync(productId, productName, waitSeconds = 10) {
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
      `php e2e-facebook-sync-validator.php ${productId} ${waitSeconds}`,
      { cwd: __dirname }
    );

    // 📄 DUMP RAW JSON OUTPUT FROM VALIDATOR
    console.log('📄 OUTPUT FROM FACEBOOK SYNC VALIDATOR:');
    console.log(stdout);

    const result = JSON.parse(stdout);

    // Display results
    if (result.success) {
      console.log(`🎉 Facebook Sync Validation Succeeded for ${displayName}:`);
    } else {
      console.log(`❌ Facebook sync validation Failed: ${result.error}. Check debug logs above.`);
    }

    return result;

  } catch (error) {
    console.log(`⚠️ Facebook sync validation error: ${error.message}`);
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
      console.log(`   Price: ${result.price}`);
      console.log(`   Stock: ${result.stock}`);

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

// Helper function to quick edit product price from products list page
async function quickEditProductPrice(page, productId, productName, newPrice) {
  console.log(`✏️ Quick editing product price to ${newPrice}...`);

  try {
    // Navigate to products list page if not already there
    const currentUrl = page.url();
    if (!currentUrl.includes('edit.php?post_type=product')) {
      await page.goto(`${baseURL}/wp-admin/edit.php?post_type=product`, {
        waitUntil: 'networkidle',
        timeout: 120000
      });
    }

    // Wait for the products table to load
    await page.waitForSelector('.wp-list-table', { timeout: 120000 });

    // Locate the product row by ID
    const productRow = page.locator(`#post-${productId}`);

    // Check if product exists
    const isVisible = await productRow.isVisible({ timeout: 10000 });
    if (!isVisible) {
      throw new Error(`Product with ID ${productId} not found in products list`);
    }

    console.log(`✅ Found product row for ID ${productId}`);

    // Hover over the row to reveal action links
    await productRow.hover();
    await page.waitForTimeout(1000);

    // Click the "Quick Edit" link
    const quickEditLink = productRow.locator('.editinline');
    await quickEditLink.click();
    console.log('✅ Clicked Quick Edit link');

    // Wait for the inline edit form to appear
    await page.waitForSelector('.inline-edit-row', { state: 'visible', timeout: 10000 });
    await page.waitForTimeout(1000);
    console.log('✅ Quick Edit form appeared');

    // Fill in the new regular price in the Quick Edit form
    // Use .first() to ensure we only target the visible inline edit row
    const priceField = page.locator('.inline-edit-row input[name="_regular_price"]').first();
    await priceField.waitFor({ state: 'visible', timeout: 10000 });
    await priceField.clear();
    await priceField.fill(newPrice);
    console.log(`✅ Entered new price: ${newPrice}`);

    // Click the "Update" button
    const updateButton = page.locator('.inline-edit-row button.save').first();
    await updateButton.click();
    console.log('✅ Clicked Update button');

    // Wait for the update to complete - look for the row to refresh
    await page.waitForTimeout(3000);

    // Verify the inline edit form has closed
    const isFormClosed = await page.locator('.inline-edit-row').isHidden({ timeout: 10000 });
    if (isFormClosed) {
      console.log('✅ Quick Edit form closed - update completed');
      return true;
    } else {
      console.warn('⚠️ Quick Edit form still visible - update may have failed');
      return false;
    }

  } catch (error) {
    console.log(`❌ Quick Edit failed: ${error.message}`);
    throw error;
  }
}

// Helper function to verify product price from WooCommerce database
async function verifyProductPrice(productId, expectedPrice) {
  if (!productId) {
    console.warn('⚠️ No product ID provided for price verification');
    return null;
  }

  console.log(`🔍 Verifying product price for ID ${productId}...`);

  try {
    const { exec } = require('child_process');
    const { promisify } = require('util');
    const execAsync = promisify(exec);

    // Query WooCommerce database for the product price
    const phpScript = `
      require_once('/tmp/wordpress/wp-load.php');
      $product = wc_get_product(${productId});
      if ($product) {
        echo json_encode([
          'success' => true,
          'product_id' => ${productId},
          'regular_price' => $product->get_regular_price(),
          'price' => $product->get_price()
        ]);
      } else {
        echo json_encode([
          'success' => false,
          'error' => 'Product not found'
        ]);
      }
    `;

    const { stdout } = await execAsync(
      `php -r "${phpScript.replace(/"/g, '\\"').replace(/\n/g, ' ')}"`,
      { cwd: __dirname }
    );

    const result = JSON.parse(stdout);

    if (result.success) {
      const actualPrice = result.regular_price;
      console.log(`📊 Product ID ${productId}:`);
      console.log(`   Regular Price: ${actualPrice}`);
      console.log(`   Expected Price: ${expectedPrice}`);

      const pricesMatch = parseFloat(actualPrice) === parseFloat(expectedPrice);
      if (pricesMatch) {
        console.log('✅ Price verification successful - prices match');
      } else {
        console.log(`❌ Price mismatch - expected ${expectedPrice}, got ${actualPrice}`);
      }

      return {
        success: pricesMatch,
        actualPrice: actualPrice,
        expectedPrice: expectedPrice
      };
    } else {
      console.log(`❌ Price verification failed: ${result.error}`);
      return {
        success: false,
        error: result.error
      };
    }

  } catch (error) {
    console.log(`⚠️ Price verification error: ${error.message}`);
    return null;
  }
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
  quickEditProductPrice,
  verifyProductPrice
};
