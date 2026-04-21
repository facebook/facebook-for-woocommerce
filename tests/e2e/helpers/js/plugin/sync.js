/**
 * Facebook sync validation helpers for E2E tests
 */

const { exec } = require('child_process');
const { promisify } = require('util');
const path = require('path');

const execAsync = promisify(exec);

/**
 * Validate Facebook sync for a product
 * @param {number} productId - Product ID to validate
 * @param {string} productName - Product name for display
 * @param {number} waitSeconds - Seconds to wait before validation
 * @param {number} maxRetries - Maximum retry attempts
 * @returns {Promise<Object>} Validation result
 */
async function validateFacebookSync(productId, productName, waitSeconds = 10, maxRetries = 6) {
  if (!productId) {
    console.warn('⚠️ No product ID provided for Facebook sync validation');
    return null;
  }

  const displayName = productName ? `"${productName}" (ID: ${productId})` : `ID: ${productId}`;
  console.log(`🔍 Validating Facebook sync for product ${displayName}...`);

  try {
    const phpDir = path.resolve(__dirname, '../../php');
    const { stdout, stderr } = await execAsync(
      `php sync-validator.php ${productId} ${waitSeconds} ${maxRetries}`,
      { cwd: phpDir }
    );

    const result = JSON.parse(stdout);
    console.log('📄 OUTPUT FROM FACEBOOK SYNC VALIDATOR:');
    const { raw_data, ...resultWithoutRawData } = result;
    console.log(JSON.stringify(resultWithoutRawData, null, 2));

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

/**
 * Validate category sync to Facebook product set
 * @param {number} categoryId - Category ID to validate
 * @param {string} categoryName - Category name for display
 * @param {number} waitSeconds - Seconds to wait before validation
 * @param {number} maxRetries - Maximum retry attempts
 * @returns {Promise<Object>} Validation result
 */
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
    const phpDir = path.resolve(__dirname, '../../php');
    const { stdout, stderr } = await execAsync(
      `php sync-validator.php --type=category ${categoryId} ${waitSeconds} ${maxRetries}`,
      { cwd: phpDir }
    );

    const result = JSON.parse(stdout);

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

/**
 * Process pending Facebook sync background jobs directly.
 *
 * The background job handler normally dispatches via a loopback HTTP request
 * to admin-ajax.php, which doesn't work on single-threaded PHP servers (like
 * the built-in dev server used in CI). This function bypasses the loopback by
 * invoking the job handler directly via CLI.
 *
 * @returns {Promise<Object>} Processing result
 */
async function processPendingSyncJobs() {
  console.log('🔄 Processing pending Facebook sync background jobs...');

  try {
    const phpDir = path.resolve(__dirname, '../../php');
    const { stdout, stderr } = await execAsync(
      'php process-sync-jobs.php',
      { cwd: phpDir, timeout: 120000 }
    );

    const result = JSON.parse(stdout);
    if (result.success) {
      console.log(`✅ Processed ${result.jobs_processed} sync job(s)`);
    } else {
      console.warn(`⚠️ Sync job processing issue: ${result.message}`);
    }
    return result;

  } catch (error) {
    console.warn(`⚠️ Sync job processing error: ${error.message}`);
    return { success: false, error: error.message };
  }
}

module.exports = {
  validateFacebookSync,
  processPendingSyncJobs,
  validateCategorySync
};
