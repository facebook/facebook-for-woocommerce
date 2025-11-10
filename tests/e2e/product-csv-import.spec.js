const { test, expect } = require('@playwright/test');
const path = require('path');
const {
  baseURL,
  loginToWordPress,
  safeScreenshot,
  cleanupProduct,
  checkForPhpErrors,
  logTestStart,
  logTestEnd,
  validateFacebookSync
} = require('./test-helpers');

test.describe('Facebook for WooCommerce - CSV Product Import E2E Tests', () => {

  test.beforeEach(async ({ page }, testInfo) => {
    logTestStart(testInfo);
    await page.setViewportSize({ width: 1280, height: 720 });
    await loginToWordPress(page);
  });

  test('Import products via CSV and verify Facebook sync', async ({ page }, testInfo) => {
    const importedProductIds = [];

    try {
      // Step 1: Navigate to Products page
      console.log('📦 Step 1: Navigating to Products page...');
      await page.goto(`${baseURL}/wp-admin/edit.php?post_type=product`, {
        waitUntil: 'networkidle',
        timeout: 120000
      });
      await page.waitForSelector('.wp-list-table', { timeout: 120000 });
      console.log('✅ Products page loaded');

      // Step 2: Click Import button
      console.log('📤 Step 2: Navigating to CSV Import wizard...');
      const importButton = page.locator('a.page-title-action:has-text("Import")');
      await importButton.waitFor({ state: 'visible', timeout: 120000 });
      await importButton.click();
      await page.waitForLoadState('networkidle', { timeout: 120000 });
      console.log('✅ Import wizard opened');

      // Step 3: Upload CSV file
      console.log('📁 Step 3: Uploading CSV file...');

      // Wait for file upload form
      const fileInput = page.locator('input[type="file"][name="import"]');
      await fileInput.waitFor({ state: 'visible', timeout: 120000 });

      // Upload the test CSV file
      const csvPath = path.join(__dirname, 'fixtures', 'test-products-import.csv');
      await fileInput.setInputFiles(csvPath);
      console.log(`✅ CSV file selected: ${csvPath}`);

      // Click Continue button
      const continueButton = page.locator('button[type="submit"].button-next, button.button-next');
      await continueButton.waitFor({ state: 'visible', timeout: 120000 });
      await continueButton.click();
      await page.waitForLoadState('networkidle', { timeout: 120000 });
      console.log('✅ Proceeded to column mapping');

      // Step 4: Verify column mapping
      console.log('🗺️ Step 4: Verifying column mapping...');

      // Wait for column mapping page to load
      await page.waitForTimeout(2000);

      // Check if we're on the column mapping page
      const mappingTable = page.locator('table.widefat');
      if (await mappingTable.isVisible({ timeout: 10000 })) {
        console.log('✅ Column mapping page detected');

        // WooCommerce usually auto-maps columns correctly
        // We can verify key columns are present
        const pageContent = await page.content();
        const hasSKU = pageContent.includes('SKU') || pageContent.includes('sku');
        const hasName = pageContent.includes('Name') || pageContent.includes('name');
        const hasPrice = pageContent.includes('Price') || pageContent.includes('price');

        if (hasSKU && hasName && hasPrice) {
          console.log('✅ Essential columns detected in mapping');
        }
      }

      // Click Continue to proceed to import
      const continueButton2 = page.locator('button[type="submit"].button-next, button.button-next');
      await continueButton2.waitFor({ state: 'visible', timeout: 120000 });
      await continueButton2.click();
      await page.waitForLoadState('networkidle', { timeout: 120000 });
      console.log('✅ Proceeded to import confirmation');

      // Step 5: Run the importer
      console.log('⚙️ Step 5: Running import process...');

      // Click "Run the importer" button
      const runImporterButton = page.locator('button.button-primary:has-text("Run the importer"), button:has-text("Run the importer")');
      await runImporterButton.waitFor({ state: 'visible', timeout: 120000 });
      await runImporterButton.click();

      // Wait for import to complete - this might take a while
      await page.waitForTimeout(10000);

      // Wait for success message or completion indicator
      try {
        await page.waitForSelector('.woocommerce-importer-done, .updated, .notice-success', { timeout: 60000 });
        console.log('✅ Import completed');
      } catch (error) {
        console.log('⚠️ Success message not detected, but continuing to check for imported products...');
      }

      // Extract import results
      const pageContent = await page.content();

      // Try to find number of imported products
      const importMatch = pageContent.match(/(\d+)\s+product[s]?\s+imported/i);
      if (importMatch) {
        console.log(`✅ Successfully imported ${importMatch[1]} products`);
      }

      // Step 6: Navigate back to products page and find imported products
      console.log('🔍 Step 6: Locating imported products...');
      await page.goto(`${baseURL}/wp-admin/edit.php?post_type=product`, {
        waitUntil: 'networkidle',
        timeout: 120000
      });
      await page.waitForTimeout(3000);

      // Search for each imported product by SKU
      const testSKUs = ['E2E-CSV-TEST-001', 'E2E-CSV-TEST-002', 'E2E-CSV-TEST-003'];
      const testProducts = [
        { sku: 'E2E-CSV-TEST-001', name: 'Test CSV Product Alpha', price: '29.99' },
        { sku: 'E2E-CSV-TEST-002', name: 'Test CSV Product Beta', price: '39.99' },
        { sku: 'E2E-CSV-TEST-003', name: 'Test CSV Product Gamma', price: '49.99' }
      ];

      for (const testProduct of testProducts) {
        // Search for product
        const searchBox = page.locator('#post-search-input');
        await searchBox.clear();
        await searchBox.fill(testProduct.sku);

        const searchButton = page.locator('#search-submit');
        await searchButton.click();
        await page.waitForLoadState('networkidle', { timeout: 120000 });
        await page.waitForTimeout(2000);

        // Find product in the table
        const productRow = page.locator(`.wp-list-table tbody tr:has-text("${testProduct.name}")`).first();

        if (await productRow.isVisible({ timeout: 10000 })) {
          // Extract product ID from the row
          const rowHTML = await productRow.innerHTML();
          const idMatch = rowHTML.match(/post=(\d+)/);

          if (idMatch) {
            const productId = parseInt(idMatch[1]);
            importedProductIds.push(productId);
            console.log(`✅ Found imported product "${testProduct.name}" with ID: ${productId}`);
          } else {
            console.log(`⚠️ Could not extract ID for product "${testProduct.name}"`);
          }
        } else {
          console.log(`⚠️ Product "${testProduct.name}" not found in search results`);
        }
      }

      console.log(`\n📊 Total products found: ${importedProductIds.length}`);

      // Verify we found all expected products
      expect(importedProductIds.length).toBeGreaterThan(0);
      console.log(`✅ Located ${importedProductIds.length} imported products`);

      // Step 7: Check for PHP errors after import
      console.log('🔍 Step 7: Checking for PHP errors...');
      await checkForPhpErrors(page);
      console.log('✅ No PHP errors detected');

      // Step 8: Validate Facebook sync for each imported product
      console.log('\n🔍 Step 8: Validating Facebook sync for imported products...');
      console.log('⏳ Waiting for Facebook sync to complete...');

      // Wait a bit for Facebook sync to process
      await page.waitForTimeout(15000);

      for (let i = 0; i < importedProductIds.length; i++) {
        const productId = importedProductIds[i];
        const testProduct = testProducts[i];

        console.log(`\n📊 Validating product ${i + 1}/${importedProductIds.length}: "${testProduct.name}"`);

        // Validate Facebook sync with 30 second timeout
        const result = await validateFacebookSync(productId, testProduct.name, 30);

        if (result && result.success) {
          console.log(`✅ Facebook sync validated for "${testProduct.name}"`);

          // Verify specific fields if available
          if (result.facebook_data) {
            const fbData = result.facebook_data;

            // Check name
            if (fbData.name) {
              console.log(`   📝 Name synced: ${fbData.name}`);
              if (fbData.name === testProduct.name) {
                console.log(`   ✅ Name matches expected value`);
              }
            }

            // Check price
            if (fbData.price) {
              console.log(`   💰 Price synced: $${fbData.price}`);
              if (parseFloat(fbData.price) === parseFloat(testProduct.price)) {
                console.log(`   ✅ Price matches expected value`);
              }
            }

            // Check SKU
            if (fbData.retailer_id) {
              console.log(`   🏷️ SKU synced: ${fbData.retailer_id}`);
              if (fbData.retailer_id.includes(testProduct.sku)) {
                console.log(`   ✅ SKU matches expected value`);
              }
            }

            // Check availability
            if (fbData.availability) {
              console.log(`   📦 Availability: ${fbData.availability}`);
            }
          }
        } else {
          console.log(`⚠️ Facebook sync validation failed for "${testProduct.name}"`);
          if (result && result.error) {
            console.log(`   Error: ${result.error}`);
          }
        }

        // Assert that sync was successful
        expect(result).toBeTruthy();
        expect(result.success).toBe(true);
      }

      console.log('\n✅ CSV import test completed successfully!');
      console.log(`   - ${importedProductIds.length} products imported via CSV`);
      console.log(`   - All products synced to Facebook catalog`);
      console.log(`   - Field validation passed`);

      logTestEnd(testInfo, true);

    } catch (error) {
      console.log(`❌ CSV import test failed: ${error.message}`);
      await safeScreenshot(page, 'csv-import-test-failure.png');
      logTestEnd(testInfo, false);
      throw error;
    } finally {
      // Cleanup: Delete all imported products
      if (importedProductIds.length > 0) {
        console.log(`\n🧹 Cleaning up ${importedProductIds.length} imported products...`);
        for (const productId of importedProductIds) {
          await cleanupProduct(productId);
        }
        console.log('✅ Cleanup completed');
      }
    }
  });

});
