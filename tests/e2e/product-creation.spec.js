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
        waitUntil: 'networkidle',
        timeout: 120000
      });

      // Wait for the product editor to load
      await page.waitForSelector('#title', { timeout: 120000 });

      const productName = generateProductName('Simple');
      await page.fill('#title', productName);

      await setProductDescription(page, "This is a test simple product.");
      console.log('✅ Basic product details filled');

      // Scroll to product data section
      await page.locator('#woocommerce-product-data').scrollIntoViewIfNeeded();

      // Click on Inventory tab
      await page.click('li.inventory_tab a');
      await page.waitForTimeout(1000); // Wait for tab content to load

      // Set SKU to ensure unique retailer ID
      const skuField = page.locator('#_sku');
      if (await skuField.isVisible({ timeout: 120000 })) {
        const uniqueSku = generateUniqueSKU('simple');
        await skuField.fill(uniqueSku);
        console.log(`✅ Set unique SKU: ${uniqueSku}`);
      }

      // Set regular price
      const regularPriceField = page.locator('#_regular_price');
      if (await regularPriceField.isVisible({ timeout: 120000 })) {
        await regularPriceField.fill('19.99');
        console.log('✅ Set regular price');
      }

      // Look for Facebook-specific fields if plugin is active
      try {
        // Check various possible Facebook field selectors
        const facebookSyncField = page.locator('#_facebook_sync_enabled, input[name*="facebook"], input[id*="facebook"]').first();
        const facebookPriceField = page.locator('label:has-text("Facebook Price"), input[name*="facebook_price"]').first();
        const facebookImageField = page.locator('legend:has-text("Facebook Product Image"), input[name*="facebook_image"]').first();

        if (await facebookSyncField.isVisible({ timeout: 10000 })) {
          console.log('✅ Facebook for WooCommerce fields detected');
        } else if (await facebookPriceField.isVisible({ timeout: 10000 })) {
          console.log('✅ Facebook price field found');
        } else if (await facebookImageField.isVisible({ timeout: 10000 })) {
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
      const result = await validateFacebookSync(productId, productName);
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
      waitUntil: 'networkidle',
      timeout: 120000
    });

    // Step 2: Fill product title
    await page.waitForSelector('#title', { timeout: 120000 });
    const productName = generateProductName('Variable');
    await page.fill('#title', productName);

    // Step 2.1: Add product description (human-like interaction)
    await page.click('#content-tmce'); // Click Visual tab
    await page.waitForTimeout(1000);
    const frameContent = page.locator('#content_ifr').contentFrame();
    await frameContent.locator('body').click(); // Click in the editor
    await frameContent.locator('body').type('This is a test variable product with multiple variations.');

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
    await page.click('li.attribute_tab a[href="#product_attributes"]');
    await page.waitForTimeout(2000);
    // Add name & value
    await page.fill('input.attribute_name[name="attribute_names[0]"]', 'Color');
    await page.fill('textarea[name="attribute_values[0]"]', 'Red|Blue|Green');
    // Use tab to enable Save Attributes button
    await page.locator('#product_attributes .woocommerce_attribute textarea[name^="attribute_values"]').press('Tab');
    await page.click('button.save_attributes.button-primary');
    await page.waitForTimeout(5000);
    console.log('✅ Saved attributes');

    // Step 6: Generate variations
    // Go to Variations tab
    await page.click('a[href="#variable_product_options"]');
    await page.waitForTimeout(2000);
    // Click "Generate variations" button
    await page.click('button.generate_variations');
    await page.waitForTimeout(8000);
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

      // Wait for price input field to appear
      await page.waitForTimeout(2000);

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
      await page.waitForTimeout(3000);
      console.log('✅ Bulk price added successfully');
    }

    //  Step 8: Publish product
    await page.click('#publish');
    await page.waitForTimeout(5000);
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
    const result = await validateFacebookSync(productId, productName, 20);
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

});
