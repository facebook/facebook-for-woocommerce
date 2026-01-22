/**
 * Product CRUD helpers for E2E tests
 */

const { exec } = require('child_process');
const { promisify } = require('util');

const execAsync = promisify(exec);
const { execWP } = require('../wordpress/exec');

/**
 * Generate a product name with timestamp and instance ID
 * @param {string} productType - Type of product
 * @returns {string} Generated product name
 */
function generateProductName(productType) {
  const now = new Date();
  const timestamp = now.toISOString().replace(/[:.]/g, '-').slice(0, 19);
  const runId = process.env.GITHUB_RUN_ID || 'local';
  return `Test ${productType.toUpperCase()} Product E2E ${timestamp}-${runId}`;
}

/**
 * Generate unique SKU for any product type
 * @param {string} productType - Type of product
 * @returns {string} Generated SKU
 */
function generateUniqueSKU(productType) {
  const runId = process.env.GITHUB_RUN_ID || 'local';
  const randomSuffix = Math.random().toString(36).substring(2, 8);
  return `E2E-${productType.toUpperCase()}-${runId}-${randomSuffix}`;
}

/**
 * Extract product ID from URL
 * @param {string} url - URL to parse
 * @returns {number|null} Product ID
 */
function extractProductIdFromUrl(url) {
  console.log(`🔍 Extracting Product ID from URL: ${url}`);
  const urlMatch = url.match(/post=(\d+)/);
  const productId = urlMatch ? parseInt(urlMatch[1]) : null;
  console.log(`✅ Extracted Product ID: ${productId}`);
  return productId;
}

/**
 * Cleanup/delete a product from WooCommerce
 * @param {number} productId - Product ID to delete
 */
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

/**
 * Create a test product programmatically via WooCommerce API
 * @param {Object} options - Product options
 * @returns {Promise<Object>} Created product details
 */
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

    const categoryIdsJson = JSON.stringify(categoryIds);
    const { stdout } = await execAsync(
      `php ../../php/product-creator.php "${productType}" "${productName}" ${price} ${stock} "${sku}" '${categoryIdsJson}'`,
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
      } else {
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

module.exports = {
  generateProductName,
  generateUniqueSKU,
  extractProductIdFromUrl,
  cleanupProduct,
  createTestProduct
};
