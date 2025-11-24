const { test, expect } = require('@playwright/test');
const {
  baseURL,
  loginToWordPress,
  safeScreenshot,
  cleanupProduct,
  generateProductName,
  generateUniqueSKU,
  extractProductIdFromUrl,
  publishProduct,
  checkForPhpErrors,
  logTestStart,
  logTestEnd,
  validateFacebookSync,
  setProductDescription
} = require('./test-helpers');

test.describe('Facebook for WooCommerce - Product Creation E2E Tests', () => {

  test.beforeEach(async ({ page }, testInfo) => {
    // Log test start first for proper chronological order
    logTestStart(testInfo);

    // Ensure browser stability
    await page.setViewportSize({ width: 1280, height: 720 });
    await loginToWordPress(page);
  });


  test('Create simple product with WooCommerce', async ({ page }, testInfo) => {
    let productId = null;
    try {

      // Navigate to add new product page
      await page.goto(`${baseURL}/wp-admin/post-new.php?post_type=product`, {
        waitUntil: 'domcontentloaded',
        timeout: 120000
      });

      // Wait for the product editor to load
      const titleField = page.locator('#title');
      await titleField.waitFor({ state: 'visible', timeout: 120000 });

      const productName = generateProductName('Simple');
      await titleField.fill(productName);

      await setProductDescription(page, "This is a test simple product.");
      console.log('✅ Basic product details filled');

      // Scroll to product data section
      await page.locator('#woocommerce-product-data').scrollIntoViewIfNeeded();

      // Set regular price
      const regularPriceField = page.locator('#_regular_price');
      await regularPriceField.waitFor({ state: 'visible', timeout: 120000 });
      await regularPriceField.fill('19.99');
      console.log('✅ Set regular price');

      // Click on Inventory tab
      const inventoryTab = page.locator('li.inventory_tab a');
      await inventoryTab.click();

      // Set SKU to ensure unique retailer ID
      const skuField = page.locator('#_sku');
      await skuField.waitFor({ state: 'visible', timeout: 120000 });
      const uniqueSku = generateUniqueSKU('simple');
      await skuField.fill(uniqueSku);
      console.log(`✅ Set unique SKU: ${uniqueSku}`);

      // Look for Facebook-specific fields if plugin is active
      try {
        // Check various possible Facebook field selectors
        const facebookSyncField = page.locator('#_facebook_sync_enabled, input[name*="facebook"], input[id*="facebook"]').first();
        const facebookPriceField = page.locator('label:has-text("Facebook Price"), input[name*="facebook_price"]').first();
        const facebookImageField = page.locator('legend:has-text("Facebook Product Image"), input[name*="facebook_image"]').first();

        const isSyncFieldVisible = await facebookSyncField.isVisible({ timeout: 5000 }).catch(() => false);
        const isPriceFieldVisible = await facebookPriceField.isVisible({ timeout: 5000 }).catch(() => false);
        const isImageFieldVisible = await facebookImageField.isVisible({ timeout: 5000 }).catch(() => false);

        if (isSyncFieldVisible) {
          console.log('✅ Facebook for WooCommerce fields detected');
        } else if (isPriceFieldVisible) {
          console.log('✅ Facebook price field found');
        } else if (isImageFieldVisible) {
          console.log('✅ Facebook image field found');
        } else {
          console.warn('⚠️ No Facebook-specific fields found - plugin may not be fully activated');
        }
      } catch (error) {
        console.warn('⚠️ Facebook field detection inconclusive - this is not necessarily an error');
      }

      // Set product status to published and save
      // Publish product
      await publishProduct(page);

      // Extract product ID from URL after publish
      const currentUrl = page.url();
      productId = extractProductIdFromUrl(currentUrl);

      // Verify no PHP fatal errors
      await checkForPhpErrors(page);

      // Validate sync to Meta catalog and fields from Meta
      const result = await validateFacebookSync(productId, productName, 5);
      expect(result['success']).toBe(true);

      console.log('✅ Simple product creation test completed successfully');
      // await waitForManualInspection(page);

      logTestEnd(testInfo, true);

    } catch (error) {
      console.log(`⚠️ Simple product test failed: ${error.message}`);
      // Take screenshot for debugging
      await safeScreenshot(page, 'simple-product-test-failure.png');
      logTestEnd(testInfo, false);
      throw error;
    } finally {
      // Cleanup irrespective of test result
      if (productId) {
        await cleanupProduct(productId);
      }
    }
  });

  test('Create variable product with WooCommerce', async ({ page }, testInfo) => {
    let productId = null;
    try {

      // Step 1: Navigate to add new product
      await page.goto(`${baseURL}/wp-admin/post-new.php?post_type=product`, {
        waitUntil: 'domcontentloaded',
        timeout: 120000
      });

      // Step 2: Fill product title
      const titleField = page.locator('#title');
      await titleField.waitFor({ state: 'visible', timeout: 120000 });
      const productName = generateProductName('Variable');
      await titleField.fill(productName);

      // Step 2.1: Add product description (human-like interaction)
      await setProductDescription(page, 'This is a test variable product with multiple variations.');

      // Set up dialog handler for WooCommerce tour popup
      page.on('dialog', async dialog => {
        await dialog.accept();
        console.log('✅ Dialog accepted');
      });

      // Step 3: Set product type to variable
      await page.selectOption('#product-type', 'variable');
      console.log('✅ Set product type to variable');

      // Step 3.5: Set unique SKU for parent product
      const uniqueParentSku = generateUniqueSKU('variable');
      await page.locator('#_sku').fill(uniqueParentSku);
      console.log(`✅ Set unique parent SKU: ${uniqueParentSku}`);

      // Step 4: Tell browser to directly click popup
      await page.evaluate(() => document.querySelector('button.woocommerce-tour-kit-step-navigation__done-btn')?.click());

      // Step 5: Add attributes
      // Go to Attributes tab
      const attributesTab = page.locator('li.attribute_tab a[href="#product_attributes"]');
      await attributesTab.click();

      // Wait for attributes panel to be visible
      const attributesPanel = page.locator('#product_attributes');
      await attributesPanel.waitFor({ state: 'visible', timeout: 5000 });
      // Add name & value
      const attributeNameField = page.locator('input.attribute_name[name="attribute_names[0]"]');
      await attributeNameField.waitFor({ state: 'visible', timeout: 5000 });
      await attributeNameField.fill('Color');

      const attributeValuesField = page.locator('textarea[name="attribute_values[0]"]');
      await attributeValuesField.fill('Red|Blue|Green');

      // Use tab to enable Save Attributes button
      await attributeValuesField.press('Tab');

      const saveAttributesButton = page.locator('button.save_attributes.button-primary');
      await saveAttributesButton.click();

      // Wait for save to complete by checking for the attributes being updated
      await page.waitForLoadState('domcontentloaded');
      console.log('✅ Saved attributes');

      // Step 6: Generate variations
      // Go to Variations tab
      const variationsTab = page.locator('a[href="#variable_product_options"]');
      await variationsTab.click();

      // Wait for variations panel to be visible
      const variationsPanel = page.locator('#variable_product_options');
      await variationsPanel.waitFor({ state: 'visible', timeout: 5000 });

      // Click "Generate variations" button
      const generateVariationsButton = page.locator('button.generate_variations');
      await generateVariationsButton.click();

      // Wait for variations to be generated by checking for first variation element
      await page.locator('.woocommerce_variation').first().waitFor({ state: 'visible', timeout: 15000 });

      // Verify variations were created
      const variationsCount = await page.locator('.woocommerce_variation').count();
      console.log(`✅ Generated ${variationsCount} variations`);

      if (variationsCount > 0) {
        // Step 7: Set prices for variations
        // Click "Add price" button first
        const addPriceBtn = page.locator('button.add_price_for_variations');
        await addPriceBtn.waitFor({ state: 'visible', timeout: 10000 });
        await addPriceBtn.click();
        console.log('✅ Clicked "Add price" button');

        // Add bulk price
        const priceInput = page.locator('input.components-text-control__input.wc_input_variations_price');
        await priceInput.waitFor({ state: 'visible', timeout: 10000 });
        await priceInput.click();        // ✅ Focus the field
        await priceInput.clear();        // ✅ Clear existing content
        await priceInput.type('29.99', { delay: 100 }); // ✅ Type with delays = triggers all JS events

        // Click "Add prices" button to apply the price
        const addPricesBtn = page.locator('button.add_variations_price_button.button-primary');
        await addPricesBtn.waitFor({ state: 'visible', timeout: 10000 });
        await addPricesBtn.click();

        // Wait for the price operation to complete
        await page.waitForLoadState('domcontentloaded');
        console.log('✅ Bulk price added successfully');
      }

      //  Step 8: Publish product
      await publishProduct(page);
      // Verify success
      const pageContent = await page.content();
      expect(pageContent).not.toContain('Fatal error');
      expect(pageContent).not.toContain('Parse error');

      console.log('✅ Variable product created successfully!');

      // Extract product ID from URL after publish
      const currentUrl = page.url();
      productId = extractProductIdFromUrl(currentUrl);

      // Verify no PHP fatal errors
      await checkForPhpErrors(page);

      // Validate sync to Meta catalog and fields from Meta
      const result = await validateFacebookSync(productId, productName, 5);
      expect(result['success']).toBe(true);

      // await waitForManualInspection(page);

      logTestEnd(testInfo, true);

    } catch (error) {
      console.log(`❌ Variable product test failed: ${error.message}`);
      logTestEnd(testInfo, false);
      await safeScreenshot(page, 'variable-product-test-failure.png');
      throw error;
    }
    finally {
      // Cleanup irrespective of test result
      if (productId) {
        await cleanupProduct(productId);
      }
    }
  });

  test('Test WordPress admin and Facebook plugin presence', async ({ page }, testInfo) => {

    try {
      // Navigate to plugins page with increased timeout
      await page.goto(`${baseURL}/wp-admin/plugins.php`, {
        waitUntil: 'domcontentloaded',
        timeout: 120000
      });

      // Wait for plugins table to be visible
      await page.locator('#the-list').waitFor({ state: 'visible', timeout: 10000 });

      // Check if Facebook plugin is listed
      const pageContent = await page.content();
      const hasFacebookPlugin = pageContent.includes('Facebook for WooCommerce') ||
        pageContent.includes('facebook-for-woocommerce');

      if (hasFacebookPlugin) {
        console.log('✅ Facebook for WooCommerce plugin detected');
      } else {
        console.warn('⚠️ Facebook for WooCommerce plugin not found in plugins list');
      }

      // Verify no PHP errors
      expect(pageContent).not.toContain('Fatal error');
      expect(pageContent).not.toContain('Parse error');

      console.log('✅ Plugin detection test completed');
      logTestEnd(testInfo, true);

    } catch (error) {
      console.log(`⚠️ Plugin detection test failed: ${error.message}`);
      logTestEnd(testInfo, false);
      throw error;
    }
  });

  test('Test basic WooCommerce product list', async ({ page }, testInfo) => {

    try {
      // Go to Products list with increased timeout
      await page.goto(`${baseURL}/wp-admin/edit.php?post_type=product`, {
        waitUntil: 'domcontentloaded',
        timeout: 120000
      });

      // Wait for products table to be visible
      await page.locator('.wp-list-table').waitFor({ state: 'visible', timeout: 120000 });
      console.log('✅ WooCommerce products page loaded successfully');

      // Verify no PHP errors on products page
      const pageContent = await page.content();
      expect(pageContent).not.toContain('Fatal error');
      expect(pageContent).not.toContain('Parse error');

      console.log('✅ Product list test completed');
      logTestEnd(testInfo, true);

    } catch (error) {
      console.log(`⚠️ Product list test failed: ${error.message}`);
      logTestEnd(testInfo, false);
      throw error;
    }
  });

  test('Quick PHP error check across key pages', async ({ page }, testInfo) => {

    const allErrors = []; // Collect all errors here

    try {
      const pagesToCheck = [
        { path: '/wp-admin/', name: 'Dashboard' },
        { path: '/wp-admin/edit.php?post_type=product', name: 'Products' },
        { path: '/wp-admin/plugins.php', name: 'Plugins' }
      ];

      // Check each page
      for (const pageInfo of pagesToCheck) {
        try {
          console.log(`🔍 Checking ${pageInfo.path} page...`);

          // Navigate to the page
          const response = await page.goto(`${baseURL}${pageInfo.path}`, {
            waitUntil: 'domcontentloaded',
            timeout: 120000
          });

          // Check HTTP status
          const status = response.status();
          if (status >= 500) {
            allErrors.push({
              page: pageInfo.name,
              pagePath: pageInfo.path,
              error: `HTTP ${status} - Server error`
            });
            console.error(`❌ [${pageInfo.path}] HTTP Status: ${status}`);
          }

          // Use the helper function to check for PHP errors
          await checkForPhpErrors(page);

          console.log(`✅ [${pageInfo.path}] No errors detected`);

        } catch (error) {
          // Collect error instead of throwing immediately
          allErrors.push({
            page: pageInfo.name,
            pagePath: pageInfo.path,
            error: error.message
          });
          console.error(`❌ [${pageInfo.path}] ${error.message}`);
          await safeScreenshot(page, `php-error-check-${pageInfo.name.toLowerCase()}-failure.png`);
        }
      }

      // After checking all pages, throw if any errors were found
      if (allErrors.length > 0) {
        // Create detailed error report
        allErrors.forEach((errorInfo, index) => {
          console.error(`\n${index + 1}. ${errorInfo.pagePath}:`);
          console.error(`${errorInfo.error}`);
        });

        logTestEnd(testInfo, false);
        throw new Error(`❌ Found ${allErrors.length} errors on ${pagesToCheck.length} page(s). See detailed report above.`);
      }

      console.log('✅ All pages passed PHP error checks');
      logTestEnd(testInfo, true);

    } catch (error) {
      if (allErrors.length === 0) {
        // Only log as failed if we haven't already logged
        logTestEnd(testInfo, false);
      }
      throw error;
    }
  });

  test('Test Facebook plugin deactivation and reactivation', async ({ page }, testInfo) => {

    try {

      // Navigate to plugins page
      await page.goto(`${baseURL}/wp-admin/plugins.php`, {
        waitUntil: 'domcontentloaded',
        timeout: 120000
      });

      // Wait for plugins table to load
      await page.locator('#the-list').waitFor({ state: 'visible', timeout: 10000 });

      // Look for Facebook plugin row
      const pluginRow = page.locator('tr[data-slug="facebook-for-woocommerce"], tr:has-text("Facebook for WooCommerce")').first();

      const isPluginRowVisible = await pluginRow.isVisible({ timeout: 10000 }).catch(() => false);
      if (isPluginRowVisible) {
        console.log('✅ Facebook plugin found');

        // Check if plugin is currently active
        const isActive = await pluginRow.locator('.active').isVisible({ timeout: 5000 }).catch(() => false);

        if (isActive) {
          console.log('Plugin is active, testing deactivation...');
          const deactivateLink = pluginRow.locator('a:has-text("Deactivate")');
          const isDeactivateLinkVisible = await deactivateLink.isVisible({ timeout: 5000 }).catch(() => false);

          if (isDeactivateLinkVisible) {
            await deactivateLink.click();

            // Wait for page to reload after deactivation
            await page.waitForLoadState('domcontentloaded');
            console.log('✅ Plugin deactivated');

            // Now test reactivation
            const reactivateLink = pluginRow.locator('a:has-text("Activate")');
            await reactivateLink.waitFor({ state: 'visible', timeout: 10000 });
            await reactivateLink.click();

            // Wait for page to reload after activation
            await page.waitForLoadState('domcontentloaded');
            console.log('✅ Plugin reactivated');
          }
        } else {
          console.log('Plugin is inactive, testing activation...');
          const activateLink = pluginRow.locator('a:has-text("Activate")');
          const isActivateLinkVisible = await activateLink.isVisible({ timeout: 5000 }).catch(() => false);

          if (isActivateLinkVisible) {
            await activateLink.click();

            // Wait for page to reload after activation
            await page.waitForLoadState('domcontentloaded');
            console.log('✅ Plugin activated');
          }
        }
      } else {
        console.warn('⚠️ Facebook plugin not found in plugins list');
      }

      // Verify no PHP errors after plugin operations
      const pageContent = await page.content();
      expect(pageContent).not.toContain('Fatal error');
      expect(pageContent).not.toContain('Parse error');

      console.log('✅ Plugin activation test completed');
      logTestEnd(testInfo, true);

    } catch (error) {
      console.log(`⚠️ Plugin activation test failed: ${error.message}`);
      logTestEnd(testInfo, false);
      throw error;
    }
  });

});
