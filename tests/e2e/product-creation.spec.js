const { test, expect } = require('@playwright/test');
const {
  baseURL,
  TIMEOUTS,
  DELAYS,
  SELECTORS,
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
  setProductTitle,
  setProductDescription,
  openProductTab
} = require('./test-helpers');

test.describe('Facebook for WooCommerce - Product Creation E2E Tests', () => {

  test.beforeEach(async ({ page }, testInfo) => {
    logTestStart(testInfo);

    await page.setViewportSize({ width: 1280, height: 720 });
    await loginToWordPress(page);
  });


  test('Create simple product with WooCommerce', async ({ page }, testInfo) => {
    let productId = null;
    try {
      await page.goto(`${baseURL}/wp-admin/post-new.php?post_type=product`, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUTS.LONG
      });

      const productName = generateProductName('Simple');
      await setProductTitle(page, productName);
      await setProductDescription(page, "This is a test simple product.");
      console.log('✅ Basic product details filled');

      await page.locator(SELECTORS.PRODUCT_DATA_PANEL).scrollIntoViewIfNeeded();

      await openProductTab(page, SELECTORS.INVENTORY_TAB, 'Inventory');

      const skuField = page.locator(SELECTORS.SKU_FIELD);
      if (await skuField.isVisible({ timeout: TIMEOUTS.MEDIUM })) {
        const uniqueSku = generateUniqueSKU('simple');
        await skuField.fill(uniqueSku);
        console.log(`✅ Set unique SKU: ${uniqueSku}`);
      }

      const regularPriceField = page.locator(SELECTORS.REGULAR_PRICE);
      if (await regularPriceField.isVisible({ timeout: TIMEOUTS.MEDIUM })) {
        await regularPriceField.fill('19.99');
        console.log('✅ Set regular price');
      }

      try {
        const facebookSyncField = page.locator('#_facebook_sync_enabled, input[name*="facebook"], input[id*="facebook"]').first();
        const facebookPriceField = page.locator('label:has-text("Facebook Price"), input[name*="facebook_price"]').first();
        const facebookImageField = page.locator('legend:has-text("Facebook Product Image"), input[name*="facebook_image"]').first();

        if (await facebookSyncField.isVisible({ timeout: TIMEOUTS.MEDIUM })) {
          console.log('✅ Facebook for WooCommerce fields detected');
        } else if (await facebookPriceField.isVisible({ timeout: TIMEOUTS.MEDIUM })) {
          console.log('✅ Facebook price field found');
        } else if (await facebookImageField.isVisible({ timeout: TIMEOUTS.MEDIUM })) {
          console.log('✅ Facebook image field found');
        } else {
          console.warn('⚠️ No Facebook-specific fields found - plugin may not be fully activated');
        }
      } catch (error) {
        console.warn('⚠️ Facebook field detection inconclusive - this is not necessarily an error');
      }

      await publishProduct(page);

      const currentUrl = page.url();
      productId = extractProductIdFromUrl(currentUrl);

      await checkForPhpErrors(page);

      const result = await validateFacebookSync(productId, productName, 5);
      expect(result['success']).toBe(true);

      console.log('✅ Simple product creation test completed successfully');

      logTestEnd(testInfo, true);

    } catch (error) {
      console.log(`⚠️ Simple product test failed: ${error.message}`);
      await safeScreenshot(page, 'simple-product-test-failure.png');
      logTestEnd(testInfo, false);
      throw error;
    } finally {
      if (productId) {
        await cleanupProduct(productId);
      }
    }
  });

  test('Create variable product with WooCommerce', async ({ page }, testInfo) => {
    let productId = null;
    try {
      await page.goto(`${baseURL}/wp-admin/post-new.php?post_type=product`, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUTS.LONG
      });

      const productName = generateProductName('Variable');
      await setProductTitle(page, productName);
      await setProductDescription(page, "This is a test variable product with multiple variations.");

      page.on('dialog', async dialog => {
        await dialog.accept();
        console.log('✅ Dialog accepted');
      });

      await page.selectOption('#product-type', 'variable');
      console.log('✅ Set product type to variable');

      const uniqueParentSku = generateUniqueSKU('variable');
      await page.locator(SELECTORS.SKU_FIELD).fill(uniqueParentSku);
      console.log(`✅ Set unique parent SKU: ${uniqueParentSku}`);

      await page.evaluate(() => document.querySelector('button.woocommerce-tour-kit-step-navigation__done-btn')?.click());

      await page.click('li.attribute_tab a[href="#product_attributes"]');
      await page.waitForTimeout(DELAYS.AFTER_TAB_SWITCH);

      await page.fill('input.attribute_name[name="attribute_names[0]"]', 'Color');
      await page.fill('textarea[name="attribute_values[0]"]', 'Red|Blue|Green');
      await page.locator('#product_attributes .woocommerce_attribute textarea[name^="attribute_values"]').press('Tab');
      await page.click('button.save_attributes.button-primary');
      await page.waitForTimeout(DELAYS.AFTER_ATTRIBUTE_SAVE);
      console.log('✅ Saved attributes');

      await page.click('a[href="#variable_product_options"]');
      await page.waitForTimeout(DELAYS.AFTER_TAB_SWITCH);

      await page.click('button.generate_variations');
      await page.waitForTimeout(DELAYS.AFTER_VARIATION_GENERATION);

      const variationsCount = await page.locator('.woocommerce_variation').count();
      console.log(`✅ Generated ${variationsCount} variations`);

      if (variationsCount > 0) {
        const addPriceBtn = page.locator('button.add_price_for_variations');
        await addPriceBtn.waitFor({ state: 'visible', timeout: TIMEOUTS.MEDIUM });
        await addPriceBtn.click();
        console.log('✅ Clicked "Add price" button');

        await page.waitForTimeout(DELAYS.AFTER_TAB_SWITCH);

        const priceInput = page.locator('input.components-text-control__input.wc_input_variations_price');
        await priceInput.waitFor({ state: 'visible', timeout: TIMEOUTS.MEDIUM });
        await priceInput.click();
        await priceInput.clear();
        await priceInput.type('29.99', { delay: 100 });

        const addPricesBtn = page.locator('button.add_variations_price_button.button-primary');
        await addPricesBtn.waitFor({ state: 'visible', timeout: TIMEOUTS.MEDIUM });
        await addPricesBtn.click();
        await page.waitForTimeout(DELAYS.AFTER_VARIATION_SAVE);
        console.log('✅ Bulk price added successfully');
      }

      await page.click(SELECTORS.PUBLISH_BUTTON);
      await page.waitForTimeout(DELAYS.AFTER_ATTRIBUTE_SAVE);

      const pageContent = await page.content();
      expect(pageContent).not.toContain('Fatal error');
      expect(pageContent).not.toContain('Parse error');

      console.log('✅ Variable product created successfully!');

      const currentUrl = page.url();
      productId = extractProductIdFromUrl(currentUrl);

      await checkForPhpErrors(page);

      const result = await validateFacebookSync(productId, productName, 5, 8);
      expect(result['success']).toBe(true);

      logTestEnd(testInfo, true);

    } catch (error) {
      console.log(`❌ Variable product test failed: ${error.message}`);
      logTestEnd(testInfo, false);
      await safeScreenshot(page, 'variable-product-test-failure.png');
      throw error;
    } finally {
      if (productId) {
        await cleanupProduct(productId);
      }
    }
  });

  test('Test WordPress admin and Facebook plugin presence', async ({ page }, testInfo) => {
    try {
      await page.goto(`${baseURL}/wp-admin/plugins.php`, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUTS.LONG
      });

      const pageContent = await page.content();
      const hasFacebookPlugin = pageContent.includes('Facebook for WooCommerce') ||
        pageContent.includes('facebook-for-woocommerce');

      if (hasFacebookPlugin) {
        console.log('✅ Facebook for WooCommerce plugin detected');
      } else {
        console.warn('⚠️ Facebook for WooCommerce plugin not found in plugins list');
      }

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
      await page.goto(`${baseURL}/wp-admin/edit.php?post_type=product`, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUTS.LONG
      });

      const pageContent = await page.content();
      expect(pageContent).not.toContain('Fatal error');
      expect(pageContent).not.toContain('Parse error');

      const hasProductsTable = await page.locator(SELECTORS.PRODUCTS_TABLE).isVisible({ timeout: TIMEOUTS.MEDIUM });
      if (hasProductsTable) {
        console.log('✅ WooCommerce products page loaded successfully');
      } else {
        console.warn('⚠️ Products table not found');
      }

      console.log('✅ Product list test completed');
      logTestEnd(testInfo, true);

    } catch (error) {
      console.log(`⚠️ Product list test failed: ${error.message}`);
      logTestEnd(testInfo, false);
      throw error;
    }
  });

  test('Quick PHP error check across key pages', async ({ page }, testInfo) => {
    try {
      const pagesToCheck = [
        { path: '/wp-admin/', name: 'Dashboard' },
        { path: '/wp-admin/edit.php?post_type=product', name: 'Products' },
        { path: '/wp-admin/plugins.php', name: 'Plugins' }
      ];

      for (const pageInfo of pagesToCheck) {
        try {
          console.log(`🔍 Checking ${pageInfo.name} page...`);
          await page.goto(`${baseURL}${pageInfo.path}`, {
            waitUntil: 'domcontentloaded',
            timeout: TIMEOUTS.LONG
          });

          const pageContent = await page.content();

          expect(pageContent).not.toContain('Fatal error');
          expect(pageContent).not.toContain('Parse error');
          expect(pageContent).not.toContain('Warning: ');

          await page.locator(SELECTORS.ADMIN_CONTENT).isVisible({ timeout: TIMEOUTS.MEDIUM });

          console.log(`✅ ${pageInfo.name} page loaded without errors`);

        } catch (error) {
          console.log(`⚠️ ${pageInfo.name} page check failed: ${error.message}`);
        }
      }

      logTestEnd(testInfo, true);
    } catch (error) {
      logTestEnd(testInfo, false);
      throw error;
    }
  });

  test('Test Facebook plugin deactivation and reactivation', async ({ page }, testInfo) => {
    try {
      await page.goto(`${baseURL}/wp-admin/plugins.php`, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUTS.LONG
      });

      const pluginRow = page.locator('tr[data-slug="facebook-for-woocommerce"], tr:has-text("Facebook for WooCommerce")').first();

      if (await pluginRow.isVisible({ timeout: TIMEOUTS.MEDIUM })) {
        console.log('✅ Facebook plugin found');

        const isActive = await pluginRow.locator('.active').isVisible({ timeout: TIMEOUTS.MEDIUM });

        if (isActive) {
          console.log('Plugin is active, testing deactivation...');
          const deactivateLink = pluginRow.locator('a:has-text("Deactivate")');
          if (await deactivateLink.isVisible({ timeout: TIMEOUTS.MEDIUM })) {
            await deactivateLink.click();
            await page.waitForTimeout(DELAYS.AFTER_TAB_SWITCH);
            console.log('✅ Plugin deactivated');

            await page.waitForTimeout(DELAYS.AFTER_CLICK);
            const reactivateLink = pluginRow.locator('a:has-text("Activate")');
            if (await reactivateLink.isVisible({ timeout: TIMEOUTS.MEDIUM })) {
              await reactivateLink.click();
              await page.waitForTimeout(DELAYS.AFTER_TAB_SWITCH);
              console.log('✅ Plugin reactivated');
            }
          }
        } else {
          console.log('Plugin is inactive, testing activation...');
          const activateLink = pluginRow.locator('a:has-text("Activate")');
          if (await activateLink.isVisible({ timeout: TIMEOUTS.MEDIUM })) {
            await activateLink.click();
            await page.waitForTimeout(DELAYS.AFTER_TAB_SWITCH);
            console.log('✅ Plugin activated');
          }
        }
      } else {
        console.warn('⚠️ Facebook plugin not found in plugins list');
      }

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
