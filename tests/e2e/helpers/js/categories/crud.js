/**
 * Category CRUD helpers for E2E tests
 */

const { execWP } = require('../wordpress/exec');

/**
 * Create a test category programmatically via WooCommerce API
 * @param {Object} options - Category options
 * @returns {Promise<Object>} Created category details
 */
async function createTestCategory(options = {}) {
  const runId = process.env.GITHUB_RUN_ID || 'local';
  const randomSuffix = Math.random().toString(36).substring(2, 8);
  const categoryName = options.categoryName || `E2E-Category-${runId}-${randomSuffix}`;
  const categoryDescription = options.description || 'Test category for E2E testing';

  console.log(`📁 Creating category via WooCommerce API: "${categoryName}"...`);

  try {
    const startTime = Date.now();

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

/**
 * Cleanup/delete a category from WooCommerce
 * @param {number} categoryId - Category ID to delete
 */
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

module.exports = {
  createTestCategory,
  cleanupCategory
};
