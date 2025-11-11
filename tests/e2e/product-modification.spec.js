const { test, expect } = require('@playwright/test');
const {
  baseURL,
  loginToWordPress,
  safeScreenshot,
  cleanupProduct,
  extractProductIdFromUrl,
  checkForPhpErrors,
  logTestStart,
  logTestEnd,
  validateFacebookSync,
  createTestProduct,
  setProductDescription,
  publishProduct
} = require('./test-helpers');

test.describe('Facebook for WooCommerce - Product Modification E2E Tests', () => {

  test.beforeEach(async ({ page }, testInfo) => {
    // Log test start first for proper chronological order
    logTestStart(testInfo);

    // Ensure browser stability
    await page.setViewportSize({ width: 1280, height: 720 });
    await loginToWordPress(page);
  });

  test('Edit simple product and verify Facebook sync', async ({ page }, testInfo) => {
    let productId = null;
    let createdProductId = null;
    let originalName = '';
    let originalPrice = '';
    let originalDescription = '';
    let originalStock = '';

    try {
      const createdProduct = await createTestProduct({
        type: 'simple',
        price: '25.00',
        stock: '20'
      });

      createdProductId = createdProduct.productId;
      console.log(`✅ Created product ID ${createdProductId} for editing test`);

      // Go to Products page
      console.log('📋 Navigating to Products page...');
      await page.goto(`${baseURL}/wp-admin/edit.php?post_type=product`, {
        waitUntil: 'networkidle',
        timeout: 120000
      });

      // Filter by Simple product type
      console.log('🔍 Filtering by Simple product type...');
      const productTypeFilter = page.locator('select#dropdown_product_type');
      if (await productTypeFilter.isVisible({ timeout: 10000 })) {
        const filterButton = page.locator("#post-query-submit");
        await productTypeFilter.selectOption('simple');
        await filterButton.click();

        await page.waitForTimeout(2000);
        console.log('✅ Filtered by Simple product type');
      } else {
        console.warn('⚠️ Product type filter not found, proceeding without filter');
      }

      // Wait for products table to load
      const hasProductsTable = await page.locator('.wp-list-table').isVisible({ timeout: 120000 });
      if (hasProductsTable) {
        console.log('✅ WooCommerce products page loaded successfully');
      } else {
        console.warn('⚠️ Products table not found');
      }

      // Find and click on first simple product
      console.log('🔍 Looking for simple product...');

      // Get the first product row
      const firstProductRow = page.locator('.wp-list-table tbody tr.iedit').first();
      await firstProductRow.isVisible({ timeout: 10000 });
      // Extract product name from the row
      const productNameElement = firstProductRow.locator('.row-title');
      originalName = await productNameElement.textContent();
      console.log(`✅ Found existing product: "${originalName}"`);

      // Click on product name to edit
      await productNameElement.click();
      await page.waitForLoadState('networkidle', { timeout: 120000 });
      console.log('✅ Opened product editor');

      // Extract product ID from URL
      const currentUrl = page.url();
      productId = extractProductIdFromUrl(currentUrl);

      if (productId !== createdProductId) {
        console.warn(`⚠️ Selected Product ID from URL: (${productId}) does not match created test product ID: (${createdProductId}). This could indicate failure to cleanup previous test run.`);
      }

      console.log(`✅ Editing product ID: ${productId}`);

      // Step 4: Store original values before editing
      console.log('📝 Storing original product values...');

      // Get original title
      const titleField = page.locator('#title');
      if (await titleField.isVisible({ timeout: 5000 })) {
        originalName = await titleField.inputValue();
        console.log(`Original title: "${originalName}"`);
      }

      // Get original price
      const regularPriceField = page.locator('#_regular_price');
      if (await regularPriceField.isVisible({ timeout: 5000 })) {
        originalPrice = await regularPriceField.inputValue();
        console.log(`Original price: "${originalPrice}"`);
      }

      // Get original stock quantity
      await page.click('li.inventory_tab a');
      await page.waitForTimeout(1000);

      const stockField = page.locator('#_stock');
      const trackStockCheckBox = page.locator('#_manage_stock');
      if (await trackStockCheckBox.isVisible({ timeout: 2000 })) {
        if (!(await trackStockCheckBox.isChecked())) {
          await trackStockCheckBox.check();
        }
        if (await stockField.isVisible({ timeout: 2000 })) {
          originalStock = await stockField.inputValue();
          console.log(`Original stock: "${originalStock}"`);
        }
      }

      // Step 5: Edit product attributes
      console.log('✏️ Editing product attributes...');

      // Generate unique values for editing
      const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
      const newTitle = `${originalName} - EDITED ${timestamp}`;
      const newPrice = (parseFloat(originalPrice || '10.00') + 5).toFixed(2);
      const newStock = (parseInt(originalStock || '10', 10) + 5).toString();
      const newDescription = `This is a test product with a description updated on: ${timestamp}`;

      // Edit title
      await titleField.scrollIntoViewIfNeeded();
      await titleField.fill(newTitle);
      console.log(`✅ Updated title to: "${newTitle}"`);

      // Edit price
      await page.click('li.general_tab a');
      await page.waitForTimeout(1000);
      await regularPriceField.scrollIntoViewIfNeeded();
      await regularPriceField.fill(newPrice);
      console.log(`✅ Updated price to: ${newPrice}`);

      // Edit description
      await setProductDescription(page, newDescription);

      // Edit stock quantity
      await page.click('li.inventory_tab a');
      await page.waitForTimeout(1000);

      if (await stockField.isVisible({ timeout: 5000 })) {
        await stockField.scrollIntoViewIfNeeded();
        await stockField.fill(newStock);
        console.log(`✅ Updated stock to: ${newStock}`);
      }

      // Step 6: Click Update button
      await publishProduct(page);

      // Verify no PHP errors after update
      await checkForPhpErrors(page);
      console.log('✅ No PHP errors detected after update');

      // Validate Facebook sync after editing
      console.log('🔄 Validating Facebook sync after edit...');
      const result = await validateFacebookSync(productId, newTitle, 60);
      expect(result['success']).toBe(true);

      // Verify the changes were saved
      console.log('🔍 Verifying changes were saved...');
      await page.reload({ waitUntil: 'networkidle', timeout: 120000 });

      const updatedTitle = await titleField.inputValue();
      expect(updatedTitle).toBe(newTitle);
      console.log('✅ Title change verified');

      const updatedPrice = await regularPriceField.inputValue();
      expect(updatedPrice).toBe(newPrice);
      console.log('✅ Price change verified');

      const updatedStock = await stockField.inputValue();
      expect(updatedStock).toBe(newStock);
      console.log('✅ Stock change verified');

      console.log('✅ Simple product edit test completed successfully');
      logTestEnd(testInfo, true);

    } catch (error) {
      console.log(`❌ Simple product edit test failed: ${error.message}`);
      await safeScreenshot(page, 'simple-product-edit-test-failure.png');
      logTestEnd(testInfo, false);
      throw error;
    } finally {
      // Cleanup created product if we created one for this test
      if (createdProductId) {
        await cleanupProduct(createdProductId);
      }
    }
  });

  test('Quick Edit simple product price and verify Facebook sync', async ({ page }, testInfo) => {
    let createdProductId = null;
    let originalPrice = '50.00';
    let newPrice = '75.00';

    try {
      // Step 1: Create test product with initial price
      console.log('🔧 Creating test product for Quick Edit test...');
      const createdProduct = await createTestProduct({
        type: 'simple',
        price: originalPrice,
        stock: '100'
      });

      createdProductId = createdProduct.productId;
      console.log(`✅ Created product ID ${createdProductId} with price $${originalPrice}`);

      // Step 2: Navigate to Products page
      console.log('📋 Navigating to Products page...');
      await page.goto(`${baseURL}/wp-admin/edit.php?post_type=product`, {
        waitUntil: 'networkidle',
        timeout: 120000
      });

      // Step 3: Filter by Simple product type
      console.log('🔍 Filtering by Simple product type...');
      const productTypeFilter = page.locator('select#dropdown_product_type');
      if (await productTypeFilter.isVisible({ timeout: 10000 })) {
        const filterButton = page.locator("#post-query-submit");
        await productTypeFilter.selectOption('simple');
        await filterButton.click();

        await page.waitForTimeout(2000);
        console.log('✅ Filtered by Simple product type');
      } else {
        console.warn('⚠️ Product type filter not found, proceeding without filter');
      }

      // Step 4: Wait for products table to load
      const hasProductsTable = await page.locator('.wp-list-table').isVisible({ timeout: 120000 });
      if (!hasProductsTable) {
        throw new Error('Products table not found');
      }
      console.log('✅ Products table loaded successfully');

      // Step 5: Find the product row
      console.log('🔍 Looking for test product...');
      const productRow = page.locator('.wp-list-table tbody tr.iedit').first();
      await productRow.waitFor({ state: 'visible', timeout: 10000 });

      const productNameElement = productRow.locator('.row-title');
      const productName = await productNameElement.textContent();
      console.log(`✅ Found product: "${productName}"`);

      // Step 6: Trigger Quick Edit
      console.log('📝 Triggering Quick Edit...');
      await productRow.hover();

      // Click the Quick Edit button
      const quickEditLink = productRow.locator('.row-actions .editinline');
      await quickEditLink.waitFor({ state: 'visible', timeout: 5000 });
      await quickEditLink.click();
      console.log('✅ Clicked Quick Edit link');

      // Step 7: Wait for Quick Edit form to appear
      console.log('⏳ Waiting for Quick Edit form...');
      const quickEditRow = page.locator('.inline-edit-row').first();
      await quickEditRow.waitFor({ state: 'visible', timeout: 10000 });
      console.log('✅ Quick Edit form appeared');

      // Step 8: Update the price
      console.log(`💰 Updating price from $${originalPrice} to $${newPrice}...`);
      const regularPriceField = quickEditRow.locator('input[name="_regular_price"]');
      await regularPriceField.waitFor({ state: 'visible', timeout: 5000 });

      // Clear the field and enter new price
      await regularPriceField.clear();
      await regularPriceField.fill(newPrice);
      console.log(`✅ Entered new price: $${newPrice}`);

      // Step 9: Save changes by clicking Update button
      console.log('💾 Saving changes...');
      const updateButton = quickEditRow.locator('.inline-edit-save .button-primary');
      await updateButton.waitFor({ state: 'visible', timeout: 5000 });
      await updateButton.click();
      console.log('✅ Clicked Update button');

      // Step 10: Wait for the inline editor to close
      console.log('⏳ Waiting for Quick Edit form to close...');
      await quickEditRow.waitFor({ state: 'hidden', timeout: 10000 });
      console.log('✅ Quick Edit form closed');

      // Wait a moment for the table row to update
      await page.waitForTimeout(2000);

      // Step 11: Verify price change in UI
      console.log('🔍 Verifying price change in products table...');
      // Reload the page to ensure we see the updated data
      await page.reload({ waitUntil: 'networkidle', timeout: 120000 });

      // Find the product row again and check the price column
      const updatedProductRow = page.locator('.wp-list-table tbody tr.iedit').first();
      const priceColumn = updatedProductRow.locator('.price');

      if (await priceColumn.isVisible({ timeout: 5000 })) {
        const displayedPrice = await priceColumn.textContent();
        console.log(`✅ Price column shows: ${displayedPrice}`);
        // The price might be formatted with currency symbol, so we just check it contains our new price
        expect(displayedPrice).toContain(newPrice);
        console.log('✅ Price change verified in UI');
      } else {
        console.log('⚠️ Price column not visible, skipping UI verification');
      }

      // Step 12: Validate Facebook sync and verify price was updated
      console.log('🔄 Validating Facebook sync after Quick Edit...');
      const result = await validateFacebookSync(createdProductId, null, 60);

      // Verify the price field specifically - should have NO mismatches for price
      const priceMismatches = Object.values(result['mismatches'] || {}).filter(
        mismatch => mismatch.field === 'price'
      );

      if (priceMismatches.length > 0) {
        const mismatch = priceMismatches[0];
        console.error(`❌ Price mismatch detected!`);
        console.error(`   WooCommerce price: ${mismatch.woocommerce_value}`);
        console.error(`   Facebook price: ${mismatch.facebook_value}`);
        throw new Error(`Price not synced correctly to Facebook. Expected ${newPrice} but Facebook has ${mismatch.facebook_value}`);
      }

      // Check overall sync success
      expect(result['success']).toBe(true);
      console.log('✅ Facebook sync validated successfully');

      console.log(`📊 Facebook price after Quick Edit: $${newPrice}`);
      console.log(`✅ Price change ($${originalPrice} → $${newPrice}) successfully synced to Facebook`);

      // Step 13: Check for PHP errors
      await checkForPhpErrors(page);
      console.log('✅ No PHP errors detected');

      console.log('✅ Quick Edit product price test completed successfully');
      logTestEnd(testInfo, true);

    } catch (error) {
      console.log(`❌ Quick Edit product price test failed: ${error.message}`);
      await safeScreenshot(page, 'quick-edit-price-test-failure.png');
      logTestEnd(testInfo, false);
      throw error;
    } finally {
      // Step 14: Cleanup
      if (createdProductId) {
        await cleanupProduct(createdProductId);
      }
    }
  });
});
