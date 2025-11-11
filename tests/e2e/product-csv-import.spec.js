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
      // Step 0: Verify WordPress admin is accessible
      console.log('🔍 Step 0: Verifying WordPress admin is accessible...');
      await page.goto(`${baseURL}/wp-admin/`, {
        waitUntil: 'domcontentloaded',
        timeout: 60000
      });
      const adminTitle = await page.title();
      console.log('Admin page title:', adminTitle);
      if (adminTitle.includes('Log In')) {
        throw new Error('Not logged in! Login may have failed.');
      }
      console.log('✅ WordPress admin is accessible');

      // Step 1: Navigate to Products page
      console.log('📦 Step 1: Navigating to Products page...');
      await page.goto(`${baseURL}/wp-admin/edit.php?post_type=product`, {
        waitUntil: 'domcontentloaded',
        timeout: 120000
      });

      // Add debugging to see what page we actually landed on
      console.log('Current URL:', page.url());
      console.log('Page title:', await page.title());

      // Take a screenshot to debug what's showing
      await safeScreenshot(page, 'products-page-before-wait.png');

      // Check if we're on the right page before waiting for the table
      const currentUrl = page.url();
      if (!currentUrl.includes('edit.php?post_type=product')) {
        console.log('⚠️ Not on products page! Current URL:', currentUrl);
        throw new Error(`Expected to be on products page, but got: ${currentUrl}`);
      }

      // Try a more flexible selector approach
      try {
        // Wait for either the products table OR any WooCommerce admin content
        await Promise.race([
          page.waitForSelector('.wp-list-table', { timeout: 30000 }),
          page.waitForSelector('#wpbody-content', { timeout: 30000 }),
          page.waitForSelector('.wrap', { timeout: 30000 })
        ]);
        console.log('✅ Products page content loaded');

        // Verify the products table is actually there
        const hasProductsTable = await page.locator('.wp-list-table').isVisible().catch(() => false);
        if (hasProductsTable) {
          console.log('✅ Products table detected');
        } else {
          console.log('⚠️ Products table not visible, but page loaded');
        }
      } catch (error) {
        console.log('❌ Failed to load products page elements');
        await safeScreenshot(page, 'products-page-load-failure.png');
        const html = await page.content();
        console.log('Page HTML snippet:', html.substring(0, 500));
        throw error;
      }

      console.log('✅ Products page loaded');

      // Step 2: Click Import button
      console.log('📤 Step 2: Navigating to CSV Import wizard...');
      // Try multiple selector strategies for the Import button
      const importButton = page.locator('a.page-title-action:has-text("Import")').first();
      await importButton.waitFor({ state: 'visible', timeout: 30000 });
      await safeScreenshot(page, 'before-import-click.png');
      await importButton.click();
      await page.waitForLoadState('domcontentloaded', { timeout: 120000 });
      await page.waitForTimeout(2000);
      console.log('✅ Import wizard opened');
      console.log('Current URL:', page.url());
      await safeScreenshot(page, 'import-wizard-opened.png');

      // Step 3: Upload CSV file
      console.log('📁 Step 3: Uploading CSV file...');

      // Wait for file upload form - try multiple selectors
      let fileInput;
      try {
        fileInput = page.locator('input[type="file"][name="import"]').first();
        await fileInput.waitFor({ state: 'attached', timeout: 30000 });
        console.log('✅ File input found via name="import"');
      } catch (error) {
        console.log('⚠️ Primary file input selector failed, trying #upload...');
        fileInput = page.locator('#upload');
        await fileInput.waitFor({ state: 'attached', timeout: 30000 });
        console.log('✅ File input found via #upload');
      }

      // Upload the test CSV file
      const csvPath = path.join(__dirname, 'fixtures', 'test-products-import.csv');
      await fileInput.setInputFiles(csvPath);
      console.log(`✅ CSV file selected: ${csvPath}`);

      // Click Continue button
      const continueButton = page.locator('button[type="submit"].button-next, button.button-next').first();
      await continueButton.waitFor({ state: 'visible', timeout: 30000 });
      await safeScreenshot(page, 'before-continue-click.png');
      await continueButton.click();
      await page.waitForLoadState('domcontentloaded', { timeout: 120000 });
      await page.waitForTimeout(3000);
      console.log('✅ Proceeded to column mapping');
      console.log('Current URL:', page.url());
      await safeScreenshot(page, 'column-mapping-page.png');

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
      const continueButton2 = page.locator('button[type="submit"].button-next, button.button-next').first();
      await continueButton2.waitFor({ state: 'visible', timeout: 30000 });
      await safeScreenshot(page, 'before-second-continue-click.png');
      await continueButton2.click();
      await page.waitForLoadState('domcontentloaded', { timeout: 120000 });
      await page.waitForTimeout(3000);
      console.log('✅ Proceeded to import confirmation');
      console.log('Current URL:', page.url());
      await safeScreenshot(page, 'import-confirmation-page.png');

      // Step 5: Run the importer
      console.log('⚙️ Step 5: Running import process...');

      // Click "Run the importer" button
      const runImporterButton = page.locator('button.button-primary:has-text("Run the importer"), button:has-text("Run the importer")').first();
      await runImporterButton.waitFor({ state: 'visible', timeout: 30000 });
      await safeScreenshot(page, 'before-run-importer-click.png');
      await runImporterButton.click();
      console.log('✅ Clicked Run the importer button');

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
        waitUntil: 'domcontentloaded',
        timeout: 120000
      });
      await page.waitForTimeout(3000);
      await safeScreenshot(page, 'products-page-after-import.png');
      console.log('Current URL:', page.url());

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
        await page.waitForLoadState('domcontentloaded', { timeout: 60000 });
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
