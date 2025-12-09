const { test, expect } = require('@playwright/test');
const { TIMEOUTS } = require('./time-constants');
const {
  baseURL,
  loginToWordPress,
  safeScreenshot,
  checkForPhpErrors,
  logTestStart,
  logTestEnd,
  validateFacebookSync,
  generateTestProductCSV,
  importProductCSV,
  cleanupProductsBySkuPattern,
  getProductIdsBySku
} = require('./test-helpers');

test.describe('Facebook for WooCommerce - CSV Product Import E2E Tests', () => {

  test.beforeEach(async ({ page }, testInfo) => {
    logTestStart(testInfo);
    await page.setViewportSize({ width: 1280, height: 720 });
    await loginToWordPress(page);
  });

  test('Import products via CSV and validate Facebook sync', async ({ page }, testInfo) => {
    let csvData = null;
    let skuPattern = null;

    try {
      // Step 1: Generate test CSV file
      console.log('📄 Generating test CSV file...');
      csvData = await generateTestProductCSV(2); // 2 simple products
      skuPattern = csvData.skuPattern; // For cleanup
      console.log(`✅ Generated CSV: ${csvData.csvPath}`);
      console.log(`   Products: ${csvData.products.length}`);

      // Step 2: Navigate to import page and upload CSV
      console.log('📤 Uploading CSV file...');
      const importResult = await importProductCSV(page, csvData.csvPath);
      console.log(`✅ Import completed: ${importResult.importedCount} products imported`);

      // Verify import count matches expected
      expect(importResult.importedCount).toBe(csvData.products.length);

      // Step 3: Verify no PHP errors
      await checkForPhpErrors(page);

      // Step 4: Wait for Facebook sync (60 seconds)
      console.log('⏳ Waiting 60 seconds for Facebook sync...');
      await page.waitForTimeout(60000);

      // Step 5: Query WooCommerce to get product IDs by SKU
      console.log('🔍 Fetching imported product IDs...');
      const productIds = await getProductIdsBySku(csvData.products.map(p => p.sku));
      expect(productIds.length).toBe(csvData.products.length);
      console.log(`✅ Found ${productIds.length} imported products`);

      // Step 6: Validate each product synced to Facebook
      console.log('🔍 Validating Facebook sync for imported products...');
      for (let i = 0; i < productIds.length; i++) {
        const productId = productIds[i];
        const productName = csvData.products[i].name;

        console.log(`   Validating product ${i + 1}/${productIds.length}: ${productName} (ID: ${productId})`);

        const result = await validateFacebookSync(productId, productName, 5, 8);
        expect(result['success']).toBe(true);

        console.log(`   ✅ Product ${productId} synced successfully`);
      }

      console.log('✅ CSV import and Facebook sync test completed successfully');
      logTestEnd(testInfo, true);

    } catch (error) {
      console.log(`❌ CSV import test failed: ${error.message}`);
      await safeScreenshot(page, 'csv-import-test-failure.png');
      logTestEnd(testInfo, false);
      throw error;
    } finally {
      // Cleanup: Delete imported products and CSV file
      if (skuPattern) {
        console.log('🧹 Cleaning up imported products...');
        const deletedCount = await cleanupProductsBySkuPattern(skuPattern);
        console.log(`✅ Cleaned up ${deletedCount} products`);
      }
      if (csvData && csvData.csvPath) {
        // Delete CSV file
        const fs = require('fs');
        if (fs.existsSync(csvData.csvPath)) {
          fs.unlinkSync(csvData.csvPath);
          console.log(`✅ Deleted CSV file: ${csvData.csvPath}`);
        }
      }
    }
  });
});
