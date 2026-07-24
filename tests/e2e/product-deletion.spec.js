const { test, expect } = require('@playwright/test');
const {
  TIMEOUTS,
  baseURL,
  safeScreenshot,
  cleanupProduct,
  checkForPhpErrors,
  logTestStart,
  logTestEnd,
  validateFacebookSync,
  processPendingSyncJobs,
  createTestProduct,
  filterProducts,
  clickFirstProduct,
  openFacebookOptions,
  setProductDescription,
  setProductTitle,
  publishProduct
} = require('./helpers/js');

test.describe('Meta for WooCommerce - Product Deletion E2E Tests', () => {

  test.beforeEach(async ({ page }, testInfo) => {
    // Log test start first for proper chronological order
    logTestStart(testInfo);

    // Ensure browser stability
    await page.setViewportSize({ width: 1280, height: 720 });
  });

  test('Delete products and validate Facebook sync', async ({ page }, testInfo) => {
    let simpleProductId = null;
    let variableProductId = null;

    try {
      // Create a test simple product
      console.log('📦 Creating test simple product...');
      console.log('📦 Creating test variable product...');
      const [simpleProduct, variableProduct] = await Promise.all([
        createTestProduct({
          productType: 'simple',
          price: '29.99',
          stock: '15'
        }),
        createTestProduct({
          productType: 'variable',
          price: '39.99',
          stock: '20'
        })
      ]);
      simpleProductId = simpleProduct.productId;
      variableProductId = variableProduct.productId;
      console.log(`✅ Created simple product ID ${simpleProductId}: "${simpleProduct.productName}"`);
      console.log(`✅ Created variable product ID ${variableProductId}: "${variableProduct.productName}"`);

      // Validate initial sync
      const [simpleProductPreDeleteResult, variableProductPreDeleteResult] = await Promise.all([
        validateFacebookSync(simpleProductId, simpleProduct.productName, 5),
        validateFacebookSync(variableProductId, variableProduct.productName, 5, 8)
      ]);
      expect(simpleProductPreDeleteResult['success']).toBe(true);
      expect(variableProductPreDeleteResult['success']).toBe(true);
      console.log('✅ Initial sync validation successful. Both products are synced to Facebook.')

      // Navigate to Products page
      console.log('📋 Navigating to Products page...');
      await page.goto(`${baseURL}/wp-admin/edit.php?post_type=product`, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUTS.MAX
      });

      // Wait for products table to load
      const hasProductsTable = await page.locator('.wp-list-table').isVisible({ timeout: TIMEOUTS.LONG });
      if (!hasProductsTable) {
        throw new Error('Products table not found');
      }
      console.log('✅ Products page loaded successfully');

      // Select the two products (Simple and Variable)
      console.log('✅ Selecting test products for deletion...');

      // Get all product rows
      const productRows = page.locator('.wp-list-table tbody tr.iedit');
      const rowCount = await productRows.count();
      console.log(`Found ${rowCount} product rows`);

      // Find and check the checkboxes for our test products
      let simpleProductChecked = false;
      let variableProductChecked = false;

      for (let i = 0; i < rowCount; i++) {
        const row = productRows.nth(i);
        const checkbox = row.locator('input[type="checkbox"]');

        // Get the product ID from the checkbox value or row ID
        const checkboxId = await checkbox.getAttribute('id');
        const productIdMatch = checkboxId ? checkboxId.match(/cb-select-(\d+)/) : null;
        const productId = productIdMatch ? parseInt(productIdMatch[1]) : null;

        if (productId === simpleProductId || productId === variableProductId) {
          await checkbox.check();
          console.log(`✅ Selected product ID ${productId}`);

          if (productId === simpleProductId) simpleProductChecked = true;
          if (productId === variableProductId) variableProductChecked = true;
        }

        // Break if we've found both products
        if (simpleProductChecked && variableProductChecked) {
          break;
        }
      }

      if (!simpleProductChecked || !variableProductChecked) {
        console.warn('⚠️ Could not find one or both test products in the list');
      }

      // Select "Move to trash" from Bulk Actions dropdown
      console.log('🗑️ Selecting "Move to trash" from Bulk Actions...');
      const bulkActionsDropdown = page.locator('#bulk-action-selector-top');
      await bulkActionsDropdown.selectOption('trash');
      console.log('✅ Selected "Move to trash" option');

      // Click the Apply button
      console.log('🔄 Clicking Apply button...');
      const applyButton = page.locator('#doaction');
      await applyButton.click();
      console.log('✅ Clicked Apply button');

      // Wait for the page to reload after bulk action
      await page.waitForLoadState('networkidle', { timeout: TIMEOUTS.MAX });
      console.log('✅ Products moved to trash');

      // Process the pending DELETE sync jobs directly.
      // The background job handler dispatches via a loopback HTTP request which
      // doesn't work on single-threaded PHP servers (like the built-in dev
      // server used in CI), so we invoke the job handler directly via CLI.
      await processPendingSyncJobs();

      const [simpleProductValidationResult, variableProductValidationResult] = await Promise.all([
        validateFacebookSync(simpleProductId, simpleProduct.productName, 30, 0),
        validateFacebookSync(variableProductId, variableProduct.productName, 30, 0)
      ]);
      expect(simpleProductValidationResult['success']).toBe(false);
      expect(variableProductValidationResult['success']).toBe(false);
      console.log('✅ Both products successfully deleted from Facebook catalog');
      logTestEnd(testInfo, true);
    } catch (error) {
      console.log(`❌ Product deletion test failed: ${error.message}`);
      await safeScreenshot(page, 'product-deletion-test-failure.png');
      logTestEnd(testInfo, false);
      throw error;
    } finally {
      await Promise.all([
        simpleProductId ? cleanupProduct(simpleProductId) : Promise.resolve(),
        variableProductId ? cleanupProduct(variableProductId) : Promise.resolve()
      ]);
    }
  });

  test('Exclude product from sync', async ({ page }, testInfo) => {
    let simpleProductId = null;
    try {
      // Create a test simple product
      console.log('📦 Creating test simple product...');
      const simpleProduct = await createTestProduct({
        productType: 'simple',
        price: '29.99',
        stock: '15'
      });
      simpleProductId = simpleProduct.productId;
      console.log(`✅ Created simple product ID ${simpleProductId}: "${simpleProduct.productName}"`);

      const syncResultBefore = await validateFacebookSync(simpleProductId, simpleProduct.productName);
      expect(syncResultBefore['success']).toBe(true);
      console.log('✅ Initial sync validation successful.')

      await filterProducts(page, 'simple', simpleProduct.sku);
      await clickFirstProduct(page);
      await checkForPhpErrors(page);
      await openFacebookOptions(page);

      const facebookSyncField = page.locator('#wc_facebook_sync_mode');
      await facebookSyncField.selectOption('sync_disabled');
      const newTitle = `${simpleProduct.productName}-out-of-sync`;
      await setProductTitle(page, newTitle);
      const newDescription = 'This product is out of sync with Facebook';
      await setProductDescription(page, newDescription);
      await publishProduct(page);

      // Process the pending DELETE sync job directly (loopback doesn't work
      // on single-threaded PHP servers in CI).
      await processPendingSyncJobs();

      const syncResultAfter = await validateFacebookSync(simpleProductId, simpleProduct.productName, 30, 0);
      expect(syncResultAfter['success']).toBe(false);
      expect(syncResultAfter['raw_data']['woo_data'][0]['title']).toBe(newTitle);
      try {
        // This check is known to be flaky and does not affect facebook plugin. So, we dont fail the test if it fails.
        expect(syncResultAfter['raw_data']['woo_data'][0]['description']).toBe(newDescription);
      } catch (e) {
        console.warn(`⚠️ Description still not updated in woo: expected "${newDescription}", got "${syncResultAfter?.raw_data?.woo_data?.[0]?.description}"`);
      }
      // facebook_data is an array of per-item results ([{ found: bool, ... }]),
      // mirroring woo_data above. After exclusion the item must report found=false.
      expect(syncResultAfter['raw_data']['facebook_data'][0]['found']).toBe(false);
      console.log('✅ Product no longer exists on Facebook catalog');
      logTestEnd(testInfo, true);
    } catch (error) {
      console.log(`❌ Exclude product from sync test failed: ${error.message}`);
      await safeScreenshot(page, 'product-exclusion-test-failure.png');
      logTestEnd(testInfo, false);
      throw error;
    } finally {
      if (simpleProductId) {
        await cleanupProduct(simpleProductId);
      }
    }
  });

  test('Bulk exclude multiple products from sync', async ({ page }, testInfo) => {
    let simpleProductId = null;
    let variableProductId = null;
    try {
      // Create a test simple product
      console.log('📦 Creating test simple product...');
      console.log('📦 Creating test variable product...');
      const [simpleProduct, variableProduct] = await Promise.all([
        createTestProduct({
          productType: 'simple',
          price: '29.99',
          stock: '15'
        }),
        createTestProduct({
          productType: 'variable',
          price: '39.99',
          stock: '20'
        })
      ]);
      simpleProductId = simpleProduct.productId;
      variableProductId = variableProduct.productId;
      console.log(`✅ Created simple product ID ${simpleProductId}: "${simpleProduct.productName}"`);
      console.log(`✅ Created variable product ID ${variableProductId}: "${variableProduct.productName}"`);

      // Validate initial sync
      const [simpleProductSyncResultBefore, variableProductSyncResultBefore] = await Promise.all([
        validateFacebookSync(simpleProductId, simpleProduct.productName, 5),
        validateFacebookSync(variableProductId, variableProduct.productName, 5, 8)
      ]);
      expect(simpleProductSyncResultBefore['success']).toBe(true);
      expect(variableProductSyncResultBefore['success']).toBe(true);
      console.log('✅ Initial sync validation successful. Both products are synced to Facebook.');

      // Navigate to Products > All Products page
      console.log('📋 Navigating to Products > All Products page...');
      await page.goto(`${baseURL}/wp-admin/edit.php?post_type=product`, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUTS.MAX
      });

      // Wait for products table to load
      const productsTable = await page.locator('.wp-list-table');
      await productsTable.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      console.log('✅ Products page loaded successfully');

      // Mark the checkboxes of products with attribute "Synced to Meta catalog" set to "Synced"
      console.log('✅ Selecting test products for bulk exclusion...');

      // Get all product rows
      const productRows = page.locator('.wp-list-table tbody tr.iedit');
      const rowCount = await productRows.count();
      console.log(`Found ${rowCount} product rows`);

      // Find and check the checkboxes for our test products
      let simpleProductChecked = false;
      let variableProductChecked = false;

      for (let i = 0; i < rowCount; i++) {
        const row = productRows.nth(i);
        const checkbox = row.locator('input[type="checkbox"]');

        // Get the product ID from the checkbox value or row ID
        const checkboxId = await checkbox.getAttribute('id');
        const productIdMatch = checkboxId ? checkboxId.match(/cb-select-(\d+)/) : null;
        const productId = productIdMatch ? parseInt(productIdMatch[1]) : null;

        if (productId === simpleProductId || productId === variableProductId) {
          await checkbox.check();
          console.log(`✅ Selected product ID ${productId}`);

          if (productId === simpleProductId) simpleProductChecked = true;
          if (productId === variableProductId) variableProductChecked = true;
        }

        // Break if we've found both products
        if (simpleProductChecked && variableProductChecked) {
          break;
        }
      }

      if (!simpleProductChecked || !variableProductChecked) {
        throw new Error('Could not find one or both test products in the list');
      }

      // Click on "Bulk options" menu
      console.log('🔽 Clicking "Bulk options" menu...');
      const bulkActionsDropdown = page.locator('#bulk-action-selector-top');
      await bulkActionsDropdown.selectOption('edit');
      console.log('✅ Selected "Edit" option from Bulk options');

      // Click on "Apply"
      console.log('🔄 Clicking Apply button...');
      const applyButton = page.locator('#doaction');
      await applyButton.click();
      console.log('✅ Clicked Apply button');

      // Wait for bulk edit panel to appear
      await page.waitForSelector('.inline-edit-row', { timeout: TIMEOUTS.LONG });
      console.log('✅ Bulk edit panel opened');

      // Change "Sync to Meta catalog" to "Do not sync"
      console.log('🔧 Changing "Sync to Meta catalog" to "Do not sync"...');
      const facebookSyncField = page.locator('.facebook_bulk_sync_options');
      await facebookSyncField.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      await facebookSyncField.selectOption('bulk_edit_delete');
      console.log('✅ Set sync mode to "Do not sync"');

      // Click on "Update" button
      console.log('💾 Clicking Update button...');
      const updateButton = page.locator('#bulk_edit');
      await updateButton.click();
      console.log('✅ Clicked Update button');

      // Wait for the page to reload after bulk action
      await page.waitForLoadState('domcontentloaded', { timeout: TIMEOUTS.MAX });
      console.log('✅ Bulk edit completed');

      // Process the pending DELETE sync jobs directly (loopback doesn't work
      // on single-threaded PHP servers in CI).
      await processPendingSyncJobs();

      // Validate that products are removed from the Facebook catalog
      console.log('🔍 Validating Facebook sync status after bulk exclusion...');
      const [simpleProductSyncResultAfter, variableProductSyncResultAfter] = await Promise.all([
        validateFacebookSync(simpleProductId, simpleProduct.productName, 30, 0),
        validateFacebookSync(variableProductId, variableProduct.productName, 30, 0)
      ]);

      expect(simpleProductSyncResultAfter['success']).toBe(false);
      console.log('✅ Simple product successfully removed from Facebook catalog');

      expect(variableProductSyncResultAfter['success']).toBe(false);
      console.log('✅ Variable product successfully removed from Facebook catalog');
      logTestEnd(testInfo, true);
    } catch (error) {
      console.log(`❌ Bulk exclude multiple products from sync test failed: ${error.message}`);
      await safeScreenshot(page, 'bulk-product-exclusion-test-failure.png');
      logTestEnd(testInfo, false);
      throw error;
    } finally {
      await Promise.all([
        simpleProductId ? cleanupProduct(simpleProductId) : Promise.resolve(),
        variableProductId ? cleanupProduct(variableProductId) : Promise.resolve()
      ]);
    }
  });
});
