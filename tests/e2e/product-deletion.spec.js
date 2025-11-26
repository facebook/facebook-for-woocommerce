const { test, expect } = require('@playwright/test');
const {
    baseURL,
    loginToWordPress,
    safeScreenshot,
    cleanupProduct,
    checkForPhpErrors,
    logTestStart,
    logTestEnd,
    validateFacebookSync,
    createTestProduct
} = require('./test-helpers');

test.describe('Facebook for WooCommerce - Product Deletion E2E Tests', () => {

  test.beforeEach(async ({ page }, testInfo) => {
    // Log test start first for proper chronological order
    logTestStart(testInfo);

    // Ensure browser stability
    await page.setViewportSize({ width: 1280, height: 720 });
    await loginToWordPress(page);
  });

  test('Delete products and validate Facebook sync', async ({ page }, testInfo) => {
    let simpleProductId = null;
    let variableProductId = null;

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

      // Create a test variable product
      console.log('📦 Creating test variable product...');
      const variableProduct = await createTestProduct({
          productType: 'variable',
          price: '39.99',
          stock: '20'
      });
      variableProductId = variableProduct.productId;
      console.log(`✅ Created variable product ID ${variableProductId}: "${variableProduct.productName}"`);

      // Navigate to Products page
      console.log('📋 Navigating to Products page...');
      await page.goto(`${baseURL}/wp-admin/edit.php?post_type=product`, {
        waitUntil: 'networkidle',
        timeout: 120000
      });

      // Wait for products table to load
      const hasProductsTable = await page.locator('.wp-list-table').isVisible({ timeout: 120000 });
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
      await page.waitForLoadState('networkidle', { timeout: 120000 });
      await page.waitForTimeout(2000);
      console.log('✅ Products moved to trash');

      // Navigate to Marketing > Facebook > Troubleshooting
      console.log('🔧 Navigating to Marketing > Facebook > Troubleshooting...');

      // First, navigate to Marketing > Facebook page
      await page.goto(`${baseURL}/wp-admin/admin.php?page=wc-facebook`, {
          waitUntil: 'networkidle',
          timeout: 120000
      });
      console.log('✅ Navigated to Facebook page');

      // Wait for the page to load
      await page.waitForTimeout(2000);

      // Click on Troubleshooting tab
      console.log('🔍 Looking for Troubleshooting tab...');
      const troubleshootingTab = page.locator('a:has-text("Troubleshooting"), button:has-text("Troubleshooting")');

      if (await troubleshootingTab.isVisible({ timeout: 10000 })){
        await troubleshootingTab.click();
        console.log('✅ Clicked Troubleshooting tab');
        await page.waitForTimeout(2000);
      }
      else {
        console.warn('⚠️ Troubleshooting tab not found');
      }

      // Click on Product Data Sync "Sync now" button
      console.log('🔄 Looking for Product Data Sync "Sync now" button...');
      const syncNowButton = page.locator('#wc-facebook-enhanced-settings-sync-products');

      if (await syncNowButton.isVisible({ timeout: 10000 })) {
          await syncNowButton.click();
          console.log('✅ Clicked "Sync now" button');

          // Wait for sync to process
          await page.waitForTimeout(5000);
          console.log('✅ Sync initiated');
      } else {
          console.warn('⚠️ "Sync now" button not found');
      }

      const simpleProductValidationResult = await validateFacebookSync(simpleProductId, simpleProduct.productName, 60);
      expect(simpleProductValidationResult['success']).toBe(false);
      // Check if any debug message contains the expected text about 0 products and 0 mismatches
      expect(
        simpleProductValidationResult['debug'].some(
          // For each message in the debug array, check if it includes the specific string
          (msg) => msg === 'Compared fields for 0 products, found 0 total mismatches'
        )
      ).toBe(true);

      const variableProductValidationResult = await validateFacebookSync(variableProductId, variableProduct.productName, 60);
      expect(variableProductValidationResult['success']).toBe(false);
      expect(
        variableProductValidationResult['debug'].some(
          (msg) => msg === 'Compared fields for 0 products, found 0 total mismatches'
        )
      ).toBe(true);

      // Verify no PHP errors occurred
      await checkForPhpErrors(page);
      console.log('✅ No PHP errors detected');

      console.log('✅ Product deletion test completed successfully');
      logTestEnd(testInfo, true);

    } catch (error) {
      console.log(`❌ Product deletion test failed: ${error.message}`);
      await safeScreenshot(page, 'product-deletion-test-failure.png');
      logTestEnd(testInfo, false);
      throw error;
    } finally {
      if (simpleProductId) {
        await cleanupProduct(simpleProductId);
      }
      if (variableProductId) {
        await cleanupProduct(variableProductId);
      }
    }
  });
});
