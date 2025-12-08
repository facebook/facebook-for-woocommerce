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
    const feedCategorySlug = generateUniqueSKU('FeedCategory');
    let importedProductIds = [];

    try {
      // Step 1: Generate product feed CSV file
      console.log('📝 Step 1: Generating product feed CSV file...');
      const feedData = await generateProductFeedCSV(5, 0.2, feedCategorySlug); // 10 products, 30% variable
      feedFilePath = feedData.filePath;
      console.log(`✅ Feed file generated with ${feedData.productCount} products`);

      // Step 2: Navigate to WooCommerce import page
      console.log('📦 Step 2: Navigating to WooCommerce import page...');
      await page.goto(`${baseURL}/wp-admin/edit.php?post_type=product&page=product_importer`, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUTS.MAX
      });
      console.log('✅ Navigated to import page');

      // Step 3: Upload feed file
      console.log('📤 Step 3: Uploading feed file...');

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
      console.log('🗺️ Step 4: Mapping columns...');

      // The WooCommerce importer should auto-map columns based on header names
      // Click "Continue" to proceed with the mapped columns
      const runImportButton = page.locator('button[type="submit"][name="save_step"], button.button-next');
      await runImportButton.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      await runImportButton.click();
      console.log('✅ Started import process');

      // Step 5: Wait for import to complete
      console.log('⏳ Step 5: Waiting for import to complete...');

      // Wait for import completion message or progress indicator
      const importComplete = page.locator('.woocommerce-importer-done, .wc-importer-done');
      await importComplete.waitFor({ state: 'visible', timeout: TIMEOUTS.EXTRA_LONG + TIMEOUTS.LONG });
      console.log('✅ Import completed');

      // Verify no PHP errors on import completion page
      await checkForPhpErrors(page);

      // Step 6: Navigate to imported products
      console.log('📋 Step 6: Navigating to imported products...');
      await page.goto(`${baseURL}/wp-admin/edit.php?post_type=product&product_cat=${feedCategorySlug}`, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUTS.MAX
      });

      // Get list of imported product IDs
      const productRows = page.locator('.wp-list-table tbody tr.iedit');
      const productCount = await productRows.count();
      console.log(`📊 Found ${productCount} imported products in WooCommerce`);

      // Verify we imported the expected number of products
      expect(productCount).toBeGreaterThan(0);

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

      console.log(`✅ Extracted ${importedProductIds.length} product IDs for validation`);

      // Step 7: Open Facebook settings through Marketing tab
      console.log('🔵 Step 7: Opening Facebook settings through Marketing tab...');

      // Navigate to Marketing > Facebook
      await page.goto(`${baseURL}/wp-admin/admin.php?page=wc-admin&path=/marketing`, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUTS.MAX
      });

      // Look for Facebook menu item
      const facebookMenuItem = page.locator('a[href*="facebook"], li:has-text("Facebook")').first();
      if (await facebookMenuItem.isVisible({ timeout: TIMEOUTS.LONG })) {
        await facebookMenuItem.click();
        await page.waitForLoadState('domcontentloaded', { timeout: TIMEOUTS.MAX });
        console.log('✅ Opened Facebook settings');
      } else {
        // Alternative: Direct navigation to Facebook settings
        await page.goto(`${baseURL}/wp-admin/admin.php?page=wc-facebook`, {
          waitUntil: 'domcontentloaded',
          timeout: TIMEOUTS.MAX
        });
        console.log('✅ Navigated directly to Facebook settings');
      }

      // Step 8: Validate Facebook sync for imported products
      console.log('🔍 Step 9: Validating Facebook sync for imported products...');

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

      // Step 9: Navigate to Facebook Catalog (informational - actual validation done above)
      console.log('\n📱 Step 10: Facebook Catalog verification (completed via API above)');
      console.log('   Note: Products are verified in Facebook catalog via API calls');
      console.log('   Manual verification: Visit https://business.facebook.com/commerce/catalogs/');

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
        console.log(`\n📝 Note: ${importedProductIds.length} test products were imported`);
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
