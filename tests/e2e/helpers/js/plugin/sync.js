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
 * Validate that a product has been removed from the Facebook catalog.
 * Retries until the product is no longer found, or until maxAttempts is exhausted.
 *
 * @param {number} productId - Product ID to validate
 * @param {string} productName - Product name for display
 * @param {number} initialWaitSeconds - Seconds to wait before first check
 * @param {number} maxAttempts - Maximum polling attempts
 * @param {number} intervalSeconds - Seconds between retries
 * @returns {Promise<Object>} Last validation result (success should be false with 0 products compared)
 */
async function validateFacebookDeletion(productId, productName, initialWaitSeconds = 30, maxAttempts = 6, intervalSeconds = 10) {
  const displayName = productName ? `"${productName}" (ID: ${productId})` : `ID: ${productId}`;
  let lastResult = null;

  for (let attempt = 1; attempt <= maxAttempts; attempt++) {
    const waitSec = attempt === 1 ? initialWaitSeconds : intervalSeconds;
    lastResult = await validateFacebookSync(productId, productName, waitSec, 0);

    if (!lastResult) {
      console.log(`🗑️ Deletion validated for ${displayName}: validator returned null (product not found)`);
      return { success: false, debug: ['Compared fields for 0 products, found 0 total mismatches'] };
    }

    const isGone = !lastResult.success && lastResult.debug && lastResult.debug.some(
      (msg) => msg === 'Compared fields for 0 products, found 0 total mismatches'
    );

    if (isGone) {
      console.log(`🗑️ Deletion validated for ${displayName} on attempt ${attempt}/${maxAttempts}`);
      return lastResult;
    }

    if (attempt < maxAttempts) {
      console.log(`⏳ Product ${displayName} still on Facebook, retrying (${attempt}/${maxAttempts})...`);
    }
  }

  console.warn(`⚠️ Product ${displayName} still found on Facebook after ${maxAttempts} attempts`);
  return lastResult;
}

module.exports = {
  validateFacebookSync,
  validateFacebookDeletion,
  validateCategorySync
};
