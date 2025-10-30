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
      console.log('⚠️ Cannot take screenshot - page is closed');
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
  return `Test ${productType} Product E2E ${timestamp}-${runId}`;
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
    console.log('⚠️ Publish step may be slow, continuing with error check');
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

// Helper function to validate Facebook sync
async function validateFacebookSync(productId, productName, waitSeconds = 10) {
  if (!productId) {
    console.log('⚠️ No product ID provided for Facebook sync validation');
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
  validateFacebookSync
};
