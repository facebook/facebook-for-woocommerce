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
  generateProductFeedCSV,
  deleteFeedFile,
  generateUniqueSKU,
  cleanupProduct
} = require('./test-helpers');

test.describe('Facebook for WooCommerce - Product Batch Import E2E Tests', () => {

  test.beforeEach(async ({ page }, testInfo) => {
    // Log test start first for proper chronological order
    logTestStart(testInfo);

    // Ensure browser stability
    await page.setViewportSize({ width: 1280, height: 720 });
    await loginToWordPress(page);
  });

  test('Import products via feed file and verify Facebook sync', async ({ page }, testInfo) => {
    let feedFilePath = null;
    let feedProductCount = 5;
    const feedCategorySlug = generateUniqueSKU('FeedCategory');
    let importedProductIds = [];

    try {
      // Generate product feed CSV file
      console.log('📝 Generating product feed CSV file...');
      const feedData = await generateProductFeedCSV(feedProductCount, 0.2, feedCategorySlug);
      feedFilePath = feedData.filePath;
      console.log(`✅ Feed file generated with ${feedData.productCount} products`);

      // Navigate to WooCommerce import page
      console.log('📦 Navigating to WooCommerce import page...');
      await page.goto(`${baseURL}/wp-admin/edit.php?post_type=product&page=product_importer`, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUTS.MAX
      });
      console.log('✅ Navigated to import page');

      // Upload feed file
      console.log('📤 Uploading feed file...');

      // Wait for file input to be available
      const fileInput = page.locator('input[type="file"][name="import"]');
      await fileInput.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });

      // Set the file input
      await fileInput.setInputFiles(feedFilePath);
      console.log('✅ File selected');

      // Click "Continue" button to proceed with import
      const continueButton = page.locator('button[type="submit"][name="save_step"], button.button-next');
      await continueButton.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      await continueButton.click();
      console.log('✅ Clicked Continue button');

      // Wait for column mapping page
      await page.waitForLoadState('domcontentloaded', { timeout: TIMEOUTS.MAX });
      console.log('✅ Column mapping page loaded');

      // Step 4: Map columns and continue
      console.log('🗺️ Mapping columns...');

      // The WooCommerce importer should auto-map columns based on header names
      // Click "Continue" to proceed with the mapped columns
      const runImportButton = page.locator('button[type="submit"][name="save_step"], button.button-next');
      await runImportButton.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      await runImportButton.click();
      console.log('✅ Started import process');

      // Wait for import to complete
      console.log('⏳ Waiting for import to complete...');

      // Wait for import completion message or progress indicator
      const importComplete = page.locator('.woocommerce-importer-done, .wc-importer-done');
      await importComplete.waitFor({ state: 'visible', timeout: TIMEOUTS.EXTRA_LONG + TIMEOUTS.LONG });
      console.log('✅ Import completed');

      // Verify no PHP errors on import completion page
      await checkForPhpErrors(page);

      //  Navigate to imported products
      console.log('📋 Navigating to imported products...');
      await page.goto(`${baseURL}/wp-admin/edit.php?post_type=product&product_cat=${feedCategorySlug}`, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUTS.MAX
      });

      // Get list of imported product IDs
      const productRows = page.locator('.wp-list-table tbody tr.iedit');
      const productCount = await productRows.count();
      console.log(`📊 Found ${productCount} imported products in WooCommerce`);

      // Extract product IDs from URLs
      for (let i = 0; i < Math.min(productCount, 5); i++) { // Test first 5 products
        const row = productRows.nth(i);
        const editLink = row.locator('.row-actions .edit a');
        const href = await editLink.getAttribute('href');
        const productIdMatch = href.match(/post=(\d+)/);
        if (productIdMatch) {
          importedProductIds.push(parseInt(productIdMatch[1]));
        }
      }

      // Verify we imported the expected number of products
      expect(productCount).toBe(feedProductCount);
      expect(importedProductIds.length).toBe(feedProductCount);
      console.log(`✅ Extracted ${importedProductIds.length} product IDs for validation`);

      // Validate Facebook sync for imported products
      console.log('🔍 Validating Facebook sync for imported products...');

      // Validate sync for a sample of imported products
      const productsToValidate = importedProductIds
      let syncSuccessCount = 0;
      let syncFailCount = 0;

      // Run validations in parallel and process results after all have settled
      const validationPromises = productsToValidate.map((productId) => {
        return validateFacebookSync(productId, null, 5, 8)
          .then((result) => ({ productId, result }))
          .catch((err) => ({ productId, error: err }));
      });

      const validationResults = await Promise.all(validationPromises);

      for (const { productId, result, error } of validationResults) {
        if (error) {
          syncFailCount++;
          console.warn(`⚠️ Product ${productId} sync validation errored: ${error?.message || error}`);
          continue;
        }

        if (result && result.success) {
          syncSuccessCount++;
          console.log(`✅ Product ${productId} synced successfully to Facebook`);

          // Verify product data matches
          expect(result.facebook_id).toBeTruthy();
          console.log(`   Facebook Product ID: ${result.facebook_id}`);
        } else {
          syncFailCount++;
          console.warn(`⚠️ Product ${productId} sync validation failed or pending`);
        }
      }

      console.log(`\n📊 Sync Validation Summary:`);
      console.log(`   ✅ Successful: ${syncSuccessCount}`);
      console.log(`   ⚠️ Failed/Pending: ${syncFailCount}`);
      console.log(`   ⏳ Total: ${productsToValidate.length}`);

      expect(syncSuccessCount).toBe(productsToValidate.length);

      console.log('\n✅ Product batch import and Facebook sync test completed successfully');
      logTestEnd(testInfo, true);

    } catch (error) {
      console.log(`❌ Product batch import test failed: ${error.message}`);
      await safeScreenshot(page, 'product-batch-import-failure.png');
      logTestEnd(testInfo, false);
      throw error;
    } finally {
      // Cleanup: Delete feed file
      if (feedFilePath) {
        await deleteFeedFile(feedFilePath);
      }

      if (importedProductIds.length > 0) {
        console.log(`\n📝 ${importedProductIds.length} test products were imported`);
        console.log(`  Category: ${feedCategorySlug}`);

        const cleanupPromises = importedProductIds.map((productId) => {
            return cleanupProduct(productId)
            .then((result) => ({ productId, result }))
            .catch((err) => ({ productId, error: err }));
        });
        await Promise.all(cleanupPromises);
        console.log(`✅ Cleaned up ${importedProductIds.length} feed test products`);
      }
    }
  });

});
