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
  createTestProduct
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
        console.log('⚠️ Product type filter not found, proceeding without filter');
      }

      // Wait for products table to load
      const hasProductsTable = await page.locator('.wp-list-table').isVisible({ timeout: 120000 });
      if (hasProductsTable) {
        console.log('✅ WooCommerce products page loaded successfully');
      } else {
        console.log('⚠️ Products table not found');
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

      // Edit description - wrapped in try-catch to handle editor variations across WordPress installations (Classic, Gutenberg, third-party editors)
      try {
        console.log('🔄 Attempting to update product description...');

        const visualTab = page.locator('#content-tmce');
        if (await visualTab.isVisible({ timeout: 5000 })) {
          await visualTab.click();
          await page.waitForTimeout(2000);

          const tinyMCEFrame = page.locator('#content_ifr');
          if (await tinyMCEFrame.isVisible({ timeout: 5000 })) {
            const frameContent = tinyMCEFrame.contentFrame();
            const bodyElement = frameContent.locator('body');
            if (await bodyElement.isVisible({ timeout: 5000 })) {
              originalDescription = await bodyElement.textContent();
              await bodyElement.fill(newDescription);
              console.log('✅ Updated description via TinyMCE editor');
            }
          }
        }
      } catch (editorError) {
        console.log(`⚠️ Could not update description: ${editorError.message} - continuing without description update`);
      }

      // Edit stock quantity
      await page.click('li.inventory_tab a');
      await page.waitForTimeout(1000);

      if (await stockField.isVisible({ timeout: 5000 })) {
        await stockField.scrollIntoViewIfNeeded();
        await stockField.fill(newStock);
        console.log(`✅ Updated stock to: ${newStock}`);
      }

      // Step 6: Click Update button
      console.log('💾 Saving product changes...');
      await page.locator('#publishing-action').scrollIntoViewIfNeeded();
      const updateButton = page.locator('#publish');

      if (await updateButton.isVisible({ timeout: 120000 })) {
        await updateButton.click();
        await page.waitForTimeout(3000);
        console.log('✅ Product updated successfully');
      } else {
        throw new Error('Update button not found');
      }

      // Verify no PHP errors after update
      await checkForPhpErrors(page);
      console.log('✅ No PHP errors detected after update');

      // Validate Facebook sync after editing
      console.log('🔄 Validating Facebook sync after edit...');
      const result = await validateFacebookSync(productId, newTitle, 15);

      if (result) {
        expect(result['success']).toBe(true);
        console.log('✅ Facebook sync validation passed after edit');
      } else {
        console.log('⚠️ Facebook sync validation returned no result');
      }

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
});
