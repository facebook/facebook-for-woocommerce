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
  validateCategorySync,
  setProductTitle,
  setProductDescription,
  createTestProduct,
  createTestCategory,
  cleanupCategory,
  exactSearchSelect2Container
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
        timeout: 60000
      });

      // Wait for the product editor to load
      const productName = generateProductName('Simple');
      await setProductTitle(page, productName);

      await setProductDescription(page, "This is a test simple product.");
      console.log('✅ Basic product details filled');

      // Scroll to product data section
      await page.locator('#woocommerce-product-data').scrollIntoViewIfNeeded();

      // Set regular price
      await page.click('li.general_tab a');
      const regularPriceField = page.locator('#_regular_price');
      await regularPriceField.waitFor({ state: 'visible', timeout: 10000 });
      await regularPriceField.fill('19.99');
      console.log('✅ Set regular price');

      // Click on Inventory tab
      await page.click('li.inventory_tab a');
      // Set SKU to ensure unique retailer ID
      const skuField = page.locator('#_sku');
      await skuField.waitFor({ state: 'visible', timeout: 10000 });
      const uniqueSku = generateUniqueSKU('simple');
      await skuField.fill(uniqueSku);
      console.log(`✅ Set unique SKU: ${uniqueSku}`);

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
        timeout: 60000
      });

      // Step 2: Fill product title
      const productName = generateProductName('Variable');
      await setProductTitle(page, productName);
      // Step 2.1: Add product description (human-like interaction)
      await setProductDescription(page, "This is a test variable product with multiple variations.");

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
      await page.locator('input.attribute_name[name="attribute_names[0]"]').waitFor({ state: 'visible', timeout: 10000 });
      // Add name & value
      await page.fill('input.attribute_name[name="attribute_names[0]"]', 'Color');
      await page.fill('textarea[name="attribute_values[0]"]', 'Red|Blue|Green');
      // Use tab to enable Save Attributes button
      await page.locator('#product_attributes .woocommerce_attribute textarea[name^="attribute_values"]').press('Tab');
      await page.click('button.save_attributes.button-primary');
      await page.waitForFunction(() => {
        return document.querySelector('.woocommerce_attribute.wc-metabox.postbox.closed') !== null;
      }, { timeout: 5000 });
      console.log('✅ Saved attributes');

      // Step 6: Generate variations
      // Go to Variations tab
      await page.click('a[href="#variable_product_options"]');
      // Click "Generate variations" button
      await page.locator('button.generate_variations').waitFor({ state: 'visible', timeout: 10000 });
      await page.click('button.generate_variations');
      // Wait until at least one variation is present
      await page.waitForFunction(() => {
        return document.querySelectorAll('.woocommerce_variation').length === 3;
      }, { timeout: 15000 });
      // Verify variations were created
      const variationsCount = await page.locator('.woocommerce_variation').count();
      expect(variationsCount).toBe(3);
      console.log(`✅ Generated ${variationsCount} variations`);

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
      await priceInput.pressSequentially('29.99', { delay: 100 }); // ✅ Type with delays = triggers all JS events

      // Click "Add prices" button to apply the price
      const addPricesBtn = page.locator('button.add_variations_price_button.button-primary');
      await addPricesBtn.waitFor({ state: 'visible', timeout: 10000 });
      await addPricesBtn.click();
      console.log('✅ Bulk price added successfully');

      await publishProduct(page);
      await checkForPhpErrors(page);

      // Extract product ID from URL after publish
      const currentUrl = page.url();
      productId = extractProductIdFromUrl(currentUrl);

      // Validate sync to Meta catalog and fields from Meta
      const result = await validateFacebookSync(productId, productName, 5, 8);
      expect(result['success']).toBe(true);

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
        timeout: 60000
      });

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
        timeout: 60000
      });

      // Verify no PHP errors on products page
      const pageContent = await page.content();
      expect(pageContent).not.toContain('Fatal error');
      expect(pageContent).not.toContain('Parse error');

      // Check if WooCommerce is working
      const hasProductsTable = await page.locator('.wp-list-table').isVisible({ timeout: 10000 });
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
            timeout: 60000
          });

          const pageContent = await page.content();

          // Check for PHP errors
          expect(pageContent).not.toContain('Fatal error');
          expect(pageContent).not.toContain('Parse error');
          // expect(pageContent).not.toContain('Warning: ');
          // TODO: Do not dump the whole page content, just check for errors

          // Verify admin content loaded
          await page.locator('#wpcontent').isVisible({ timeout: 10000 });

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
      // Navigate to plugins page
      await page.goto(`${baseURL}/wp-admin/plugins.php`, {
        waitUntil: 'domcontentloaded',
        timeout: 60000
      });

      // Look for Facebook plugin row
      const pluginRow = page.locator('tr[data-slug="facebook-for-woocommerce"], tr:has-text("Facebook for WooCommerce")').first();

      await pluginRow.waitFor({ state: 'visible', timeout: 10000 });
      console.log('✅ Facebook plugin found');

      // Check if plugin is currently active
      const isActive = await pluginRow.locator('.active').isVisible({ timeout: 10000 });
      const deactivateLink = pluginRow.locator('a:has-text("Deactivate")');
      const reactivateLink = pluginRow.locator('a:has-text("Activate")');

      if (isActive) {
        console.log('Plugin is active, testing deactivation...');
        await deactivateLink.waitFor({ state: 'visible', timeout: 10000 });
        await deactivateLink.click();
        await reactivateLink.waitFor({ state: 'visible', timeout: 10000 });
        console.log('✅ Plugin deactivated');
        await reactivateLink.click();
        await deactivateLink.waitFor({ state: 'visible', timeout: 10000 });
        console.log('✅ Plugin reactivated');
      } else {
        console.log('Plugin is inactive, testing activation...');
        const activateLink = pluginRow.locator('a:has-text("Activate")');
        await activateLink.waitFor({ state: 'visible', timeout: 10000 });
        await activateLink.click();
        await deactivateLink.waitFor({ state: 'visible', timeout: 10000 });
        console.log('✅ Plugin activated');
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

  test('Create Composite product with WPC plugin', async ({ page }, testInfo) => {
    let simpleProductId = null;
    let variableProductId = null;
    let compositeProductId = null;
    // Notice the Unicode horizontal ellipsis (U+2026) at the end, which is a single character ("…") instead of three periods ("..."). This is commonly used in UI text to indicate continuation or expectation.
    const hintText = 'Search for a product…';
    try {
      console.log('📦 Creating test components...');
      const [simpleProduct, variableProduct] = await Promise.all([
        createTestProduct({
          productType: 'simple',
          price: '29.99',
          stock: '15'
        }),
        createTestProduct({
          productType: 'variable',
          price: '49.99',
          stock: '20'
        })
      ]);
      simpleProductId = simpleProduct.productId;
      variableProductId = variableProduct.productId;

      // Navigate to add new product page
      await page.goto(`${baseURL}/wp-admin/post-new.php?post_type=product`, {
        waitUntil: 'domcontentloaded',
        timeout: 60000
      });

      // Add product name
      const productName = generateProductName('Composite');
      await setProductTitle(page, productName);
      await setProductDescription(page, "This is a test composite product with multiple components.");

      // Set product type to "Smart composite"
      await page.selectOption('#product-type', { label: 'Smart composite' });
      const componentsTab = await page.locator('li.composite_options');
      await componentsTab.waitFor({ state: 'visible', timeout: 10000 });
      console.log('✅ Product type set to Smart composite');

      // Wait for product data section to load
      await page.locator('#woocommerce-product-data').scrollIntoViewIfNeeded();

      // In "General" tab add "Regular price"
      await page.click('li.general_tab a');
      const regularPriceField = page.locator('#_regular_price');
      await regularPriceField.waitFor({ state: 'visible', timeout: 10000 });
      await regularPriceField.fill('49.99');
      console.log('✅ Set regular price');

      await page.click('li.inventory_tab a');
      // Set SKU to ensure unique retailer ID
      const skuField = page.locator('#_sku');
      await skuField.waitFor({ state: 'visible', timeout: 10000 });
      const uniqueSku = generateUniqueSKU('simple');
      await skuField.fill(uniqueSku);
      console.log(`✅ Set unique SKU: ${uniqueSku}`);

      // Go to "Components" tab
      await componentsTab.click();
      const addComponentBtn = page.locator('.wooco_add_component');
      await addComponentBtn.waitFor({ state: 'visible', timeout: 10000 });
      console.log('✅ Switched to Components tab');

      const simpleComponentNameField = page.locator('.wooco_component_name_val').first();
      const variableComponentNameField = page.locator('.wooco_component_name_val').nth(1);
      console.log('✅ Make room for two components');
      await addComponentBtn.click();
      await simpleComponentNameField.waitFor({ state: 'visible', timeout: 10000 });
      await variableComponentNameField.waitFor({ state: 'visible', timeout: 10000 });

      // Enter product name in component
      await simpleComponentNameField.fill('Simple Component');
      console.log('✅ Simple Component name entered');

      const simpleComponentDescField = page.locator('.wooco_component_desc_val').first();
      await simpleComponentDescField.waitFor({ state: 'visible', timeout: 10000 });
      await simpleComponentDescField.fill('This is the simple component description');
      console.log('✅ Simple Component description entered');

      const simpleComponentSourceField = page.locator('.wooco_component_type.wooco_component_type_val').first();
      await simpleComponentSourceField.selectOption('products');
      const simpleComponentSelect2Container = page.getByRole('textbox', { name: hintText }).first();
      await exactSearchSelect2Container(page, simpleComponentSelect2Container, simpleProduct.sku);

      await variableComponentNameField.scrollIntoViewIfNeeded();
      await variableComponentNameField.fill('Variable Component');
      console.log('✅ Variable Component name entered');

      const variableComponentDescField = page.locator('.wooco_component_desc_val').nth(1);
      await variableComponentDescField.waitFor({ state: 'visible', timeout: 10000 });
      await variableComponentDescField.fill('This is the variable component description');
      console.log('✅ Variable Component description entered');

      const variableComponentSourceField = page.locator('.wooco_component_type.wooco_component_type_val').nth(1);
      await variableComponentSourceField.selectOption('products');
      const variableComponentSelect2Container = page.getByRole('textbox', { name: hintText }).first();
      await exactSearchSelect2Container(page, variableComponentSelect2Container, variableProduct.sku);

      // Click on "Save components" button
      const saveComponentsBtn = page.locator('button:has-text("Save components"), .save_composite_data, #publish').first();
      await saveComponentsBtn.waitFor({ state: 'visible', timeout: 10000 });
      await saveComponentsBtn.click();
      console.log('✅ Components saved');

      const pricingStrategy = page.locator('#wooco_pricing');
      await pricingStrategy.waitFor({ state: 'visible', timeout: 10000 });
      await pricingStrategy.selectOption('include');

      // Publish product
      await publishProduct(page);
      console.log('✅ Product published');

      // Extract product ID from URL after publish
      const currentUrl = page.url();
      compositeProductId = extractProductIdFromUrl(currentUrl);

      // Verify no PHP fatal errors
      await checkForPhpErrors(page);

      // Validate sync to Meta catalog and fields from Meta
      const result = await validateFacebookSync(compositeProductId, productName, 5, 8);
      expect(result['success']).toBe(true);

      console.log('✅ Composite product creation test completed successfully');
      logTestEnd(testInfo, true);

    } catch (error) {
      console.log(`⚠️ Composite product test failed: ${error.message}`);
      await safeScreenshot(page, 'composite-product-test-failure.png');
      logTestEnd(testInfo, false);
      throw error;
    } finally {
      // Cleanup irrespective of test result
      await Promise.all([
        simpleProductId ? cleanupProduct(simpleProductId) : Promise.resolve(),
        variableProductId ? cleanupProduct(variableProductId) : Promise.resolve(),
        compositeProductId ? cleanupProduct(compositeProductId) : Promise.resolve()
      ]);
    }
  });

  test('Create category and sync products to catalog as set', async ({ page }, testInfo) => {
    let product1Id = null;
    let product2Id = null;
    let categoryId = null;

    try {
      // Create test products using createTestProduct function
      console.log('📦 Creating test products...');
      const [product1, product2] = await Promise.all([
        createTestProduct({
          productType: 'simple',
          price: '24.99',
          stock: '10'
        }),
        createTestProduct({
          productType: 'variable',
          price: '34.99',
          stock: '15'
        })
      ]);
      product1Id = product1.productId;
      product2Id = product2.productId;
      console.log(`✅ Created test products: ${product1Id}, ${product2Id}`);

      // Navigate to Categories page
      await page.goto(`${baseURL}/wp-admin/edit-tags.php?taxonomy=product_cat&post_type=product`, {
        waitUntil: 'domcontentloaded',
        timeout: 60000
      });
      console.log('✅ Navigated to Categories page');

      // Generate unique category name
      const categoryName = generateUniqueSKU('Category');
      const categoryDescription = 'This is a test category for E2E testing';

      // Enter category data
      const categoryNameField = page.locator('#tag-name');
      await categoryNameField.waitFor({ state: 'visible', timeout: 10000 });
      await categoryNameField.fill(categoryName);
      console.log(`✅ Entered category name: ${categoryName}`);

      // Enter category description
      const categoryDescField = page.locator('#tag-description');
      await categoryDescField.waitFor({ state: 'visible', timeout: 10000 });
      await categoryDescField.fill(categoryDescription);
      console.log('✅ Entered category description');

      // Click 'Add new category' button
      const addCategoryBtn = page.locator('#submit');
      await addCategoryBtn.click();
      await page.waitForLoadState('domcontentloaded');
      console.log('✅ Clicked Add new category button');

      // Extract category ID from the page
      const categoryRow = page.locator(`tr:has-text("${categoryName}")`).first();
      await categoryRow.waitFor({ state: 'visible', timeout: 10000 });
      const categoryLink = categoryRow.locator('a.row-title').first();
      const categoryHref = await categoryLink.getAttribute('href');
      const categoryIdMatch = categoryHref.match(/tag_ID=(\d+)/);
      categoryId = categoryIdMatch ? parseInt(categoryIdMatch[1]) : null;
      console.log(`✅ Category created with ID: ${categoryId}`);

      // Navigate to All Products tab
      await page.goto(`${baseURL}/wp-admin/edit.php?post_type=product`, {
        waitUntil: 'domcontentloaded',
        timeout: 60000
      });
      console.log('✅ Navigated to All Products page');

      // Select the products using checkboxes
      const product1Checkbox = page.locator(`#cb-select-${product1Id}`);
      const product2Checkbox = page.locator(`#cb-select-${product2Id}`);

      await product1Checkbox.waitFor({ state: 'visible', timeout: 10000 });
      await product1Checkbox.check();
      console.log(`✅ Selected product ${product1Id}`);

      await product2Checkbox.waitFor({ state: 'visible', timeout: 10000 });
      await product2Checkbox.check();
      console.log(`✅ Selected product ${product2Id}`);

      // Choose 'Edit' from Bulk Actions dropdown
      const bulkActionsDropdown = page.locator('#bulk-action-selector-top');
      await bulkActionsDropdown.selectOption('edit');
      console.log('✅ Selected Edit from Bulk Actions');

      // Click Apply button
      const applyBtn = page.locator('#doaction');
      await applyBtn.click();
      console.log('✅ Clicked Apply button');

      // Wait for bulk edit interface to appear
      const bulkEditRow = page.locator('#bulk-edit');
      await bulkEditRow.waitFor({ state: 'visible', timeout: 10000 });
      console.log('✅ Bulk edit interface opened');

      // Click the newly created category checkbox in Product categories section
      const categoryCheckbox = page.getByRole('checkbox', { name: categoryName });
      await categoryCheckbox.waitFor({ state: 'visible', timeout: 10000 });
      await categoryCheckbox.check();
      console.log(`✅ Checked category ${categoryId} checkbox`);

      // Click Update button in bulk edit
      const updateBtn = page.locator('#bulk_edit');
      await updateBtn.click();
      await page.waitForLoadState('domcontentloaded');
      console.log('✅ Clicked Update button');

      // Validate that the category has been synced as a set
      // verify that the products are still synced and belong to the category
      const [product1Result, product2Result, categoryResult] = await Promise.all([
        validateFacebookSync(product1Id, product1.productName, 5),
        validateFacebookSync(product2Id, product2.productName, 5, 8),
        validateCategorySync(categoryId, categoryName, 5)
      ]);

      expect(categoryResult['success']).toBe(true);
      console.log(categoryResult['raw_data']['facebook_data']);
      console.log('✅ Category sync validated');
      expect(product1Result['success']).toBe(true);
      console.log(product1Result['raw_data']['facebook_data'][0]['product_sets']);
      const isProduct1InCorrectProductSet = product1Result['raw_data']['facebook_data'][0]['product_sets'].some(
        set => {
          return Number(categoryId) === Number(set.retailer_id) && Number(set.id) === Number(categoryResult['facebook_product_set_id'])
        }
      );
      expect(isProduct1InCorrectProductSet).toBe(true);
      console.log('✅ Product 1 sync validated');
      expect(product2Result['success']).toBe(true);
      const isProduct2InCorrectProductSet = product2Result['raw_data']['facebook_data'].some(
        product => {
          return product['product_sets'].some(
            set => {
              console.log(set);
              return Number(categoryId) === Number(set.retailer_id) && Number(set.id) === Number(categoryResult['facebook_product_set_id'])
            }
          )
        }
      );
      expect(isProduct2InCorrectProductSet).toBe(true);
      console.log('✅ Product 2 sync validated');

      await page.goto(`${baseURL}/wp-admin/post.php?post=${product1Id}&action=edit`, {
        waitUntil: 'domcontentloaded',
        timeout: 60000
      });

      const isCategoryChecked = await categoryCheckbox.isChecked();
      expect(isCategoryChecked).toBe(true);
      console.log('✅ Verified product 1 has category assigned');

      console.log('✅ Category and product sync test completed successfully');
      logTestEnd(testInfo, true);

    } catch (error) {
      console.log(`⚠️ Category sync test failed: ${error.message}`);
      await safeScreenshot(page, 'category-sync-test-failure.png');
      logTestEnd(testInfo, false);
      throw error;
    } finally {
      await Promise.all([
        product1Id ? cleanupProduct(product1Id) : Promise.resolve(),
        product2Id ? cleanupProduct(product2Id) : Promise.resolve(),
        categoryId ? cleanupCategory(categoryId) : Promise.resolve()
      ]);
    }
  });

  test('Update existing category and verify Facebook sync', async ({ page }, testInfo) => {
    let product1Id = null;
    let product2Id = null;
    let categoryId = null;

    try {
      // Step 1: Create a test category via API
      console.log('📁 Creating test category via API...');
      const categoryData = await createTestCategory({
        description: 'Test category for name update testing'
      });
      categoryId = categoryData.categoryId;
      const originalCategoryName = categoryData.categoryName;

      // Step 2: Create first product and attach it to the category via API
      console.log('📦 Creating first product and attaching to category via API...');
      const product1 = await createTestProduct({
        productType: 'simple',
        price: '19.99',
        stock: '12',
        categoryIds: [categoryId]
      });
      product1Id = product1.productId;
      console.log(`✅ Created product 1: ${product1.productName} (ID: ${product1Id}) with category ${categoryId}`);

      // Step 3: Validate category sync with one product
      console.log('🔍 Validating initial sync...');
      const initialCategoryResult = await validateCategorySync(categoryId, originalCategoryName, 5);
      expect(initialCategoryResult['success']).toBe(true);
      console.log('✅ Initial category sync validated with one product');

      // Store the Facebook product set ID for later verification
      const facebookProductSetId = initialCategoryResult['facebook_product_set_id'];
      console.log(`📊 Facebook Product Set ID: ${facebookProductSetId}`);

      // Validate product 1 is in the category
      const product1InitialResult = await validateFacebookSync(product1Id, product1.productName, 5);
      expect(product1InitialResult['success']).toBe(true);
      const isProduct1InInitialSet = product1InitialResult['raw_data']['facebook_data'][0]['product_sets'].some(
        set => Number(categoryId) === Number(set.retailer_id) && Number(set.id) === Number(facebookProductSetId)
      );
      expect(isProduct1InInitialSet).toBe(true);
      console.log('✅ Product 1 validated in category product set');

      // Step 4: Update category name via UI
      console.log(`📝 Updating category name via UI...`);
      const updatedCategoryName = generateUniqueSKU('UpdatedCategory');

      // Navigate to Categories page
      await page.goto(`${baseURL}/wp-admin/edit-tags.php?taxonomy=product_cat&post_type=product`, {
        waitUntil: 'domcontentloaded',
        timeout: 60000
      });
      console.log('✅ Navigated to Categories page');

      // Click on the category row to edit
      const categoryRow = page.locator(`tr#tag-${categoryId}`);
      await categoryRow.waitFor({ state: 'visible', timeout: 10000 });
      const editLink = categoryRow.locator('a.row-title').first();
      await editLink.click();
      await page.waitForLoadState('domcontentloaded');
      console.log('✅ Opened category edit page');

      // Update the category name
      const categoryNameField = page.locator('#name');
      await categoryNameField.waitFor({ state: 'visible', timeout: 10000 });
      await categoryNameField.clear();
      await categoryNameField.fill(updatedCategoryName);
      console.log(`✅ Entered new category name: ${updatedCategoryName}`);

      // Click Update button to save changes
      const updateBtn = page.getByRole('button', { name: 'Update' });
      await updateBtn.click();
      await page.waitForLoadState('domcontentloaded');
      console.log('✅ Category name updated in UI');

      // Step 5: Create second product and add it to the category
      console.log('📦 Creating second product and adding to category...');
      const product2 = await createTestProduct({
        productType: 'simple',
        price: '29.99',
        stock: '8',
        categoryIds: [categoryId]
      });
      product2Id = product2.productId;

      // Step 6: Validate the name change synced to Facebook AND both products are in the category
      console.log('🔍 Step 6: Validating category name change synced to Facebook...');
      const updatedCategoryResult = await validateCategorySync(categoryId, updatedCategoryName, 5);

      // Verify category sync with updated name
      expect(updatedCategoryResult['success']).toBe(true);
      console.log('📊 Updated Category Facebook data:');
      console.log(updatedCategoryResult['raw_data']['facebook_data']);
      console.log('✅ Updated category name sync validated');

      // Verify the product set ID remains the same
      expect(updatedCategoryResult['facebook_product_set_id']).toBe(facebookProductSetId);
      console.log('✅ Verified product set ID remained consistent after name change');

      // Verify the name is updated in Facebook
      const facebookCategoryName = updatedCategoryResult['raw_data']['facebook_data']['name'];
      expect(facebookCategoryName).toBe(updatedCategoryName);
      console.log(`✅ Verified category name updated in Facebook: "${facebookCategoryName}"`);

      // Verify both products are in the updated category
      console.log('🔍 Verifying both products are in the updated category...');
      const [finalProduct1Result, finalProduct2Result] = await Promise.all([
        validateFacebookSync(product1Id, product1.productName, 5),
        validateFacebookSync(product2Id, product2.productName, 5)
      ]);

      // Verify product 1 is still in the category
      expect(finalProduct1Result['success']).toBe(true);
      const isProduct1InSet = finalProduct1Result['raw_data']['facebook_data'][0]['product_sets'].some(
        set => {
          return Number(categoryId) === Number(set.retailer_id) && Number(set.id) === Number(facebookProductSetId)
        }
      );
      expect(isProduct1InSet).toBe(true);
      console.log('✅ Product 1 still in the updated category');

      // Verify product 2 is in the category
      expect(finalProduct2Result['success']).toBe(true);
      const isProduct2InSet = finalProduct2Result['raw_data']['facebook_data'][0]['product_sets'].some(
        set => {
          return Number(categoryId) === Number(set.retailer_id) && Number(set.id) === Number(facebookProductSetId)
        }
      );
      expect(isProduct2InSet).toBe(true);
      console.log('✅ Product 2 is now in the updated category');
      logTestEnd(testInfo, true);
    } catch (error) {
      console.log(`⚠️ Update category test failed: ${error.message}`);
      await safeScreenshot(page, 'update-category-name-test-failure.png');
      logTestEnd(testInfo, false);
      throw error;
    } finally {
      await Promise.all([
        product1Id ? cleanupProduct(product1Id) : Promise.resolve(),
        product2Id ? cleanupProduct(product2Id) : Promise.resolve(),
        categoryId ? cleanupCategory(categoryId) : Promise.resolve()
      ]);
      console.log('🧹 Cleanup completed');
    }
  });

  test('Delete category and verify removal from Facebook catalog', async ({ page }, testInfo) => {
    let productId = null;
    let categoryId = null;

    try {
      // Step 1: Create a test category via API
      console.log('📁 Step 1: Creating test category via API...');
      const categoryData = await createTestCategory({
        description: 'Test category for deletion testing'
      });
      categoryId = categoryData.categoryId;
      const categoryName = categoryData.categoryName;

      // Step 2: Create a product and attach it to the category via API
      console.log('📦 Step 2: Creating product and attaching to category via API...');
      const product = await createTestProduct({
        productType: 'simple',
        price: '24.99',
        stock: '15',
        categoryIds: [categoryId]
      });
      productId = product.productId;
      console.log(`✅ Created product: ${product.productName} (ID: ${productId}) with category ${categoryId}`);

      // Step 3: Perform initial validation
      console.log('🔍 Step 3: Performing initial validation...');
      const initialCategoryResult = await validateCategorySync(categoryId, categoryName, 5);
      expect(initialCategoryResult['success']).toBe(true);
      console.log('✅ Initial category sync validated');

      const facebookProductSetId = initialCategoryResult['facebook_product_set_id'];
      console.log(`📊 Facebook Product Set ID: ${facebookProductSetId}`);

      // Validate product is in the category
      const initialProductResult = await validateFacebookSync(productId, product.productName, 5);
      expect(initialProductResult['success']).toBe(true);
      const isProductInInitialSet = initialProductResult['raw_data']['facebook_data'][0]['product_sets'].some(
        set => Number(categoryId) === Number(set.retailer_id)
      );
      expect(isProductInInitialSet).toBe(true);
      console.log('✅ Product validated in category product set');

      // Step 4: Delete the test category via UI
      console.log('🗑️ Step 4: Deleting category via UI...');

      // Navigate to Categories page
      await page.goto(`${baseURL}/wp-admin/edit-tags.php?taxonomy=product_cat&post_type=product`, {
        waitUntil: 'domcontentloaded',
        timeout: 60000
      });
      console.log('✅ Navigated to Categories page');

      // Find the category row
      const categoryRow = page.locator(`tr#tag-${categoryId}`);
      await categoryRow.waitFor({ state: 'visible', timeout: 10000 });

      // Hover over the category row to reveal the delete link
      await categoryRow.hover();

      // Click the Delete link
      const deleteLink = categoryRow.locator('a.delete-tag');
      await deleteLink.waitFor({ state: 'visible', timeout: 10000 });

      // Set up dialog handler to confirm deletion
      page.on('dialog', async dialog => {
        console.log(`📝 Dialog message: ${dialog.message()}`);
        await dialog.accept();
        console.log('✅ Confirmed deletion dialog');
      });

      await deleteLink.click();
      await page.waitForLoadState('domcontentloaded');
      console.log(`✅ Deleted category ${categoryId} from UI`);

      // Step 5: Validate the category no longer exists in Facebook catalog
      console.log('🔍 Step 5: Validating category removal from Facebook...');
      const deletedCategoryResult = await validateCategorySync(categoryId, categoryName, 5);
      expect(deletedCategoryResult['success']).toBe(false);
      console.log('✅ Verified category no longer syncs to Facebook');

      // Step 6: Check the product no longer belongs to the category/product set
      console.log('🔍 Step 6: Verifying product no longer belongs to the category...');
      const finalProductResult = await validateFacebookSync(productId, product.productName, 5);
      expect(finalProductResult['success']).toBe(true);

      // Check if product still has the deleted category in its product sets
      const isProductStillInSet = finalProductResult['raw_data']['facebook_data'][0]['product_sets'].some(
        set => Number(categoryId) === Number(set.retailer_id)
      );
      expect(isProductStillInSet).toBe(false);
      console.log('✅ Verified product no longer belongs to the deleted category');

      // Verify in the UI that the category is no longer assigned to the product
      await page.goto(`${baseURL}/wp-admin/post.php?post=${productId}&action=edit`, {
        waitUntil: 'domcontentloaded',
        timeout: 60000
      });

      const categoryCheckbox = page.getByRole('checkbox', { name: categoryName });
      const categoryExists = await categoryCheckbox.isVisible({ timeout: 5000 }).catch(() => false);
      expect(categoryExists).toBe(false);
      console.log('✅ Verified category no longer appears in product edit UI');

      console.log('✅ Category deletion test completed successfully');
      logTestEnd(testInfo, true);

    } catch (error) {
      console.log(`⚠️ Category deletion test failed: ${error.message}`);
      await safeScreenshot(page, 'category-deletion-test-failure.png');
      logTestEnd(testInfo, false);
      throw error;
    } finally {
      // Cleanup product (category already deleted in the test)
      await Promise.all([
        productId ? cleanupProduct(productId) : Promise.resolve()
      ]);
      console.log('🧹 Cleanup completed');
    }
  });

});
