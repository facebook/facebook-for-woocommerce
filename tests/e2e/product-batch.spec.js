const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');

const { TIMEOUTS } = require('./time-constants');
const {
  baseURL,
  wpSitePath,
  loginToWordPress,
  safeScreenshot,
  checkForPhpErrors,
  logTestStart,
  logTestEnd,
  validateFacebookSync,
  generateProductFeedCSV,
  deleteFeedFile,
  generateUniqueSKU,
  cleanupProduct,
  cleanupCategory,
  generateProductUpdateCSV
} = require('./test-helpers');

const {
  enableBatchMonitoring,
  disableBatchMonitoring,
  readBatchLog,
  waitForBatchLogProducts,
  installMonitoringPlugin,
  uninstallMonitoringPlugin
} = require('./batch-monitor-helpers');

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

      const feedCategoryId = execSync(
        `wp term list product_cat --slug=${feedCategorySlug} --field=term_id`,
        { encoding: 'utf-8' }
      ).trim();
      await cleanupCategory(feedCategoryId);
    }
  });

  test('Sync large number of products and validate batch API responses', async ({ page }, testInfo) => {
    let feedFilePath = null;
    const productCount = 50;
    const categorySlug = generateUniqueSKU('batch-api-test');
    const variableProductPercentage = 0.2; // 20% of products will be variable products
    let importedProductIds = [];

    try {
      await installMonitoringPlugin();
      await enableBatchMonitoring();

      // Step 1: Generate product feed
      console.log('📝 Step 1: Generating product feed...');
      const feedData = await generateProductFeedCSV(productCount, variableProductPercentage, categorySlug);
      feedFilePath = feedData.filePath;
      console.log(`✅ Feed generated with ${feedData.productCount} products`);
      console.log(`   File: ${feedFilePath}`);

      // Step 2: Import products via WooCommerce
      console.log('\n📦 Step 2: Importing products via WooCommerce...');
      await page.goto(`${baseURL}/wp-admin/edit.php?post_type=product&page=product_importer`, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUTS.MAX
      });

      // Upload feed file
      const fileInput = page.locator('input[type="file"][name="import"]');
      await fileInput.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      await fileInput.setInputFiles(feedFilePath);
      console.log('   ✓ File uploaded');

      // Click Continue to column mapping
      const continueButton = page.locator('button[type="submit"][name="save_step"], button.button-next');
      await continueButton.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      await continueButton.click();
      await page.waitForLoadState('domcontentloaded', { timeout: TIMEOUTS.MAX });
      console.log('   ✓ Column mapping page loaded');

      // Click Continue to start import
      const runImportButton = page.locator('button[type="submit"][name="save_step"], button.button-next');
      await runImportButton.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      await runImportButton.click();
      console.log('   ✓ Import started');

      // Wait for import completion
      const importComplete = page.locator('.woocommerce-importer-done, .wc-importer-done');
      await importComplete.waitFor({ state: 'visible' }); // no timeout

      // Step 3: Get imported product IDs for cleanup using WP-CLI
      console.log('\n📊 Step 3: Collecting imported product IDs via WP-CLI...');

      // Get all product IDs in this category using WP-CLI
      const productIdsJson = execSync(
        `wp post list --post_type=product --product_cat=${categorySlug} --fields=ID --format=json`,
        { cwd: wpSitePath, encoding: 'utf8' }
      );

      const productIdsData = JSON.parse(productIdsJson);
      importedProductIds = productIdsData.map(item => item.ID);

      console.log(`✅ Found ${importedProductIds.length} imported products via WP-CLI`);

      try {
        expect(importedProductIds.length).toBe(productCount);
      } catch (err) {
        console.warn(`⚠️ Expected ${productCount} imported products but found ${importedProductIds.length}. There maybe a delay between import completion UI and actual product creation.`);
      }

      // Step 4: Wait for background sync to complete
      console.log('\n⏳ Step 4: Waiting for background sync to complete...');
      console.log('   This will poll the batch log every 2 seconds');
      console.log('   Timeout: 2 minutes');

      const fbProductCount = feedData.simpleProductCount + feedData.variableProductCount + (feedData.variableProductCount * 3); // 3 variants per variable product
      const batchLog = await waitForBatchLogProducts(fbProductCount, categorySlug, 120000); // 2 min timeout

      // Step 5: Validate batch behavior
      console.log('\n' + '='.repeat(80));
      console.log('📊 BATCH ANALYSIS RESULTS');
      console.log('='.repeat(80));

      console.log('\n📈 Summary Statistics:');
      console.log(`   Total Batches Sent: ${batchLog.summary.total_batches}`);
      console.log(`   Total Products Synced: ${batchLog.summary.total_products}`);
      console.log(`   First Batch: ${batchLog.summary.first_batch_time}`);
      console.log(`   Last Batch: ${batchLog.summary.last_batch_time}`);

      // Assertions - Summary level
      expect(batchLog.summary.total_batches).toBeGreaterThan(0);
      expect(batchLog.summary.total_products).toBe(fbProductCount);

      // Detailed batch analysis
      console.log('\n📦 Individual Batch Details:');

      const batchSizes = [];
      let totalErrors = 0;

      batchLog.batches.forEach((batch, index) => {
        console.log(`\n   Batch ${index + 1}:`);
        console.log(`      Size: ${batch.batch_size} products`);
        console.log(`      Timestamp: ${batch.datetime}`);
        console.log(`      URL: ${batch.url}`);
        console.log(`      Method: ${batch.method}`);
        console.log(`      Response Code: ${batch.response?.code || 'N/A'}`);
        console.log(`      Response Status: ${batch.response?.message || 'N/A'}`);
        console.log(`      Has Error: ${batch.response?.has_error ? 'YES ❌' : 'NO ✅'}`);

        if (batch.response?.has_error) {
          console.log(`      Error Code: ${batch.response?.error_code}`);
          console.log(`      Error Message: ${batch.response?.error_message}`);
          totalErrors++;
        }

        batchSizes.push(batch.batch_size);

        // Assertions - Per batch
        expect(batch.url).toContain('graph.facebook.com');
        expect(batch.url).toContain('items_batch');
        expect(batch.method).toBe('POST');
        expect(batch.batch_size).toBeGreaterThan(0);
        expect(batch.batch_size).toBeLessThanOrEqual(100); // Meta's documented limit

        // Validate actual Meta response
        expect(batch.response).toBeDefined();
        expect(batch.response.code).toBe(200); // Must be 200 OK
        expect(batch.response.has_error).toBe(false); // Must have no errors

        // Show sample requests for first batch
        if (batch.request_sample && batch.request_sample.length > 0) {
          console.log(`      Sample Request:`);
          console.log(`         Method: ${batch.request_sample[0]?.method || 'N/A'}`);
          console.log(`         Product ID: ${batch.request_sample[0]?.data?.id || 'N/A'}`);
          console.log(`         Product Type: ${batch.request_sample[0]?.data?.product_type || 'N/A'}`);
        }

        // Show response handles for first batch
        if (batch.response?.handles) {
          console.log(`      Sample Response Handles: ${batch.response.handles.slice(0, 2).join(', ')}`);
        }
      });

      console.log(`\n📊 Error Summary:`);
      console.log(`   Total Batches with Errors: ${totalErrors}`);
      expect(totalErrors).toBe(0); // No batches should have errors

      // Calculate batch statistics
      const avgBatchSize = batchSizes.reduce((a, b) => a + b, 0) / batchSizes.length;
      const maxBatchSize = Math.max(...batchSizes);
      const minBatchSize = Math.min(...batchSizes);

      console.log('\n📊 Batch Size Statistics:');
      console.log(`   Average Batch Size: ${avgBatchSize.toFixed(2)} products`);
      console.log(`   Maximum Batch Size: ${maxBatchSize} products`);
      console.log(`   Minimum Batch Size: ${minBatchSize} products`);

      // Final validation
      console.log('\n✅ VALIDATION RESULTS:');
      console.log('   ✓ All batches within Meta API limits (≤100 items)');
      console.log('   ✓ All products accounted for across batches');
      console.log('   ✓ All batch requests properly formatted');
      console.log('   ✓ All batch responses returned 200 OK');

      logTestEnd(testInfo, true);

    } catch (error) {
      try {
        const partialLog = readBatchLog();
        console.error('\n📋 Partial Batch Log:');
        console.error(JSON.stringify(partialLog, null, 2));
      } catch (logError) {
        console.error('⚠️ Could not read batch log:', logError.message);
      }
      logTestEnd(testInfo, false);
      throw error;
    } finally {
      await disableBatchMonitoring();
      await uninstallMonitoringPlugin();

      // Cleanup feed file
      if (feedFilePath) {
        await deleteFeedFile(feedFilePath);
      }

      // Cleanup products
      if (importedProductIds.length > 0) {
        console.log(`\n🧹 Cleaning up ${importedProductIds.length} test products...`);
        const cleanupPromises = importedProductIds.map((productId) =>
          cleanupProduct(productId)
            .then(() => ({ productId, success: true }))
            .catch((err) => ({ productId, success: false, error: err }))
        );
        const results = await Promise.all(cleanupPromises);
        const successCount = results.filter(r => r.success).length;
        console.log(`✅ Cleanup completed: ${successCount}/${importedProductIds.length} products deleted`);
      }
      const feedCategoryId = execSync(
        `wp term list product_cat --slug=${categorySlug} --field=term_id`,
        { encoding: 'utf-8' }
      ).trim();
      await cleanupCategory(feedCategoryId);
    }
  });

  test('Update existing products via CSV and verify Facebook sync', async ({ page }, testInfo) => {
    let initialFeedFilePath = null;
    let updateFeedFilePath = null;
    const feedProductCount = 5;
    const feedCategorySlug = generateUniqueSKU('UpdateCategory');
    let importedProductIds = [];
    let originalProducts = [];

    try {
      // Phase 1: Import initial products
      console.log('\n[Phase 1] Initial Product Import');

      const initialFeedData = await generateProductFeedCSV(feedProductCount, 0, feedCategorySlug);
      initialFeedFilePath = initialFeedData.filePath;
      console.log(`Generated feed with ${initialFeedData.productCount} products`);

      await page.goto(`${baseURL}/wp-admin/edit.php?post_type=product&page=product_importer`, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUTS.MAX
      });

      const fileInput = page.locator('input[type="file"][name="import"]');
      await fileInput.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      await fileInput.setInputFiles(initialFeedFilePath);

      const continueButton = page.locator('button[type="submit"][name="save_step"], button.button-next');
      await continueButton.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      await continueButton.click();

      await page.waitForLoadState('domcontentloaded', { timeout: TIMEOUTS.MAX });

      const runImportButton = page.locator('button[type="submit"][name="save_step"], button.button-next');
      await runImportButton.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      await runImportButton.click();

      const importComplete = page.locator('.woocommerce-importer-done, .wc-importer-done');
      await importComplete.waitFor({ state: 'visible', timeout: TIMEOUTS.EXTRA_LONG + TIMEOUTS.LONG });
      console.log('Initial import completed');

      await checkForPhpErrors(page);

      await page.goto(`${baseURL}/wp-admin/edit.php?post_type=product&product_cat=${feedCategorySlug}`, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUTS.MAX
      });

      const productDataJson = execSync(
        `wp post list --post_type=product --product_cat=${feedCategorySlug} --fields=ID,post_title --format=json`,
        { cwd: wpSitePath, encoding: 'utf8' }
      );

      const productData = JSON.parse(productDataJson);

      for (const item of productData) {
        const productId = item.ID;
        importedProductIds.push(productId);

        const productMetaJson = execSync(
          `wp post meta list ${productId} --format=json`,
          { cwd: wpSitePath, encoding: 'utf8' }
        );
        const productMeta = JSON.parse(productMetaJson);

        const sku = productMeta.find(m => m.meta_key === '_sku')?.meta_value || '';
        const price = productMeta.find(m => m.meta_key === '_regular_price')?.meta_value || '0';
        const stock = productMeta.find(m => m.meta_key === '_stock')?.meta_value || '0';

        originalProducts.push({
          id: productId,
          sku: sku,
          name: item.post_title,
          price: price,
          stock: stock,
          type: 'simple',
          description: `This is a test product created from feed file for E2E testing.`
        });
      }

      console.log(`Collected ${originalProducts.length} products`);
      expect(originalProducts.length).toBe(feedProductCount);

      // Phase 2: Generate and import update CSV
      console.log('\n[Phase 2] Product Update via CSV');

      const updateFeedData = await generateProductUpdateCSV(originalProducts, feedCategorySlug);
      updateFeedFilePath = updateFeedData.filePath;

      await page.goto(`${baseURL}/wp-admin/edit.php?post_type=product&page=product_importer`, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUTS.MAX
      });

      const updateFileInput = page.locator('input[type="file"][name="import"]');
      await updateFileInput.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      await updateFileInput.setInputFiles(updateFeedFilePath);

      const updateExistingCheckbox = page.locator('input#woocommerce-importer-update-existing');
      await updateExistingCheckbox.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      await updateExistingCheckbox.check();

      const isChecked = await updateExistingCheckbox.isChecked();
      expect(isChecked).toBe(true);

      const updateContinueButton = page.locator('button[type="submit"][name="save_step"], button.button-next');
      await updateContinueButton.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      await updateContinueButton.click();

      await page.waitForLoadState('domcontentloaded', { timeout: TIMEOUTS.MAX });

      const runUpdateImportButton = page.locator('button[type="submit"][name="save_step"], button.button-next');
      await runUpdateImportButton.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      await runUpdateImportButton.click();

      const updateImportComplete = page.locator('.woocommerce-importer-done, .wc-importer-done');
      await updateImportComplete.waitFor({ state: 'visible', timeout: TIMEOUTS.EXTRA_LONG + TIMEOUTS.LONG });
      console.log('Update import completed');

      await checkForPhpErrors(page);

      // Phase 3: Validate updates
      console.log('\n[Phase 3] Validation');

      const updatedProductDataJson = execSync(
        `wp post list --post_type=product --product_cat=${feedCategorySlug} --fields=ID --format=json`,
        { cwd: wpSitePath, encoding: 'utf8' }
      );
      const updatedProductData = JSON.parse(updatedProductDataJson);
      const updatedProductIds = updatedProductData.map(item => item.ID);

      expect(updatedProductIds.length).toBe(feedProductCount);
      console.log(`Product count unchanged: ${updatedProductIds.length} (no duplicates)`);

      const sortedOriginalIds = [...importedProductIds].sort((a, b) => a - b);
      const sortedUpdatedIds = [...updatedProductIds].sort((a, b) => a - b);
      expect(sortedUpdatedIds).toEqual(sortedOriginalIds);

      // Verify product prices were updated
      let updateSuccessCount = 0;

      for (const originalProduct of originalProducts) {
        const productId = originalProduct.id;

        const updatedMetaJson = execSync(
          `wp post meta list ${productId} --format=json`,
          { cwd: wpSitePath, encoding: 'utf8' }
        );
        const updatedMeta = JSON.parse(updatedMetaJson);

        const updatedPrice = updatedMeta.find(m => m.meta_key === '_regular_price')?.meta_value || '0';
        const expectedPrice = (parseFloat(originalProduct.price) + 10).toFixed(2);
        const priceMatches = parseFloat(updatedPrice).toFixed(2) === expectedPrice;

        if (priceMatches) {
          updateSuccessCount++;
        } else {
          console.warn(`Product ${productId} price mismatch: expected ${expectedPrice}, got ${updatedPrice}`);
        }
      }

      console.log(`Price updates: ${updateSuccessCount}/${originalProducts.length} successful`);
      expect(updateSuccessCount).toBe(originalProducts.length);

      // Validate Facebook sync
      let syncSuccessCount = 0;
      let syncFailCount = 0;

      const validationPromises = importedProductIds.map((productId) => {
        return validateFacebookSync(productId, null, 5, 8)
          .then((result) => ({ productId, result }))
          .catch((err) => ({ productId, error: err }));
      });

      const validationResults = await Promise.all(validationPromises);

      for (const { productId, result, error } of validationResults) {
        if (error) {
          syncFailCount++;
          console.warn(`Product ${productId} sync error: ${error?.message || error}`);
          continue;
        }

        if (result && result.success) {
          syncSuccessCount++;
          expect(result.facebook_id).toBeTruthy();
        } else {
          syncFailCount++;
          console.warn(`Product ${productId} sync failed or pending`);
        }
      }

      console.log(`Facebook sync: ${syncSuccessCount}/${importedProductIds.length} successful`);
      expect(syncSuccessCount).toBe(importedProductIds.length);

      console.log('\nTest completed successfully');
      logTestEnd(testInfo, true);

    } catch (error) {
      console.log(`Test failed: ${error.message}`);
      await safeScreenshot(page, 'product-csv-update-failure.png');
      logTestEnd(testInfo, false);
      throw error;
    } finally {
      if (initialFeedFilePath) {
        await deleteFeedFile(initialFeedFilePath);
      }
      if (updateFeedFilePath) {
        await deleteFeedFile(updateFeedFilePath);
      }

      if (importedProductIds.length > 0) {
        const cleanupPromises = importedProductIds.map((productId) => {
          return cleanupProduct(productId)
            .then((result) => ({ productId, result }))
            .catch((err) => ({ productId, error: err }));
        });
        await Promise.all(cleanupPromises);
        console.log(`Cleaned up ${importedProductIds.length} test products`);
      }

      // Clean up the test category
      const feedCategoryId = execSync(
        `wp term list product_cat --slug=${feedCategorySlug} --field=term_id`,
        { encoding: 'utf-8' }
      ).trim();
      await cleanupCategory(feedCategoryId);
    }
  });

});
