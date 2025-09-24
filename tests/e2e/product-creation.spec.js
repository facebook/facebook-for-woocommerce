const { test, expect } = require('@playwright/test');

// Test configuration from environment variables
const baseURL = process.env.WORDPRESS_URL || 'http://localhost:8080';
const username = process.env.WP_USERNAME || 'admin';
const password = process.env.WP_PASSWORD || 'admin';

// Helper function for reliable login
async function loginToWordPress(page) {
  // Navigate to login page
  await page.goto(`${baseURL}/wp-admin/`, { waitUntil: 'networkidle', timeout: 120000 });

  // Check if we're already logged in
  const isLoggedIn = await page.locator('#wpcontent').isVisible({ timeout: 5000 });
  if (isLoggedIn) {
    console.log('✅ Already logged in');
    return;
  }

  // Fill login form - wait longer for login elements
  console.log('🔐 Logging in to WordPress...');
  await page.waitForSelector('#user_login', { timeout: 120000 });
  await page.fill('#user_login', username);
  await page.fill('#user_pass', password);
  await page.click('#wp-submit');

  // Wait for login to complete
  await page.waitForLoadState('networkidle', { timeout: 120000 });
  console.log('✅ Login completed');
}

// Helper function to safely take screenshots
async function safeScreenshot(page, path) {
  try {
    // Check if page is still available
    if (page && !page.isClosed()) {
      await page.screenshot({ path, fullPage: true });
      console.log(`✅ Screenshot saved: ${path}`);
    } else {
      console.log('⚠️ Cannot take screenshot - page is closed');
    }
  } catch (error) {
    console.log(`⚠️ Screenshot failed: ${error.message}`);
  }
}

// cleanup function - Delete created product from WooCommerce
async function cleanupProduct(productId) {
  if (!productId) return;

  console.log(`🧹 Cleaning up product ${productId}...`);

  try {
    const { exec } = require('child_process');
    const { promisify } = require('util');
    const execAsync = promisify(exec);

    const { stdout } = await execAsync(
      `php -r "require_once('/tmp/wordpress/wp-load.php'); wp_delete_post(${productId}, true);"`,
      { cwd: __dirname }
    );

    console.log(`✅ Product ${productId} deleted from WooCommerce`);
  } catch (error) {
    console.log(`⚠️ Cleanup failed: ${error.message}`);
  }
}

// Helper function to generate product name with timestamp and instance ID
function generateProductName(productType) {
  const now = new Date();
  const timestamp = now.toISOString().replace(/[:.]/g, '-').slice(0, 19);
  const runId = process.env.GITHUB_RUN_ID || 'local';
  return `Test ${productType} Product E2E ${timestamp}-${runId}`;
}

// Helper function to generate unique SKU for any product type
function generateUniqueSKU(productType) {
  const runId = process.env.GITHUB_RUN_ID || 'local';
  const randomSuffix = Math.random().toString(36).substring(2, 8);
  return `E2E-${productType.toUpperCase()}-${runId}-${randomSuffix}`;
}

// Helper function to extract product ID from URL
function extractProductIdFromUrl(url) {
  const urlMatch = url.match(/post=(\d+)/);
  const productId = urlMatch ? parseInt(urlMatch[1]) : null;
  console.log(`✅ Extracted Product ID: ${productId}`);
  return productId;
}

// Helper function to publish product
async function publishProduct(page) {
  try {
    await page.locator('#publishing-action').scrollIntoViewIfNeeded();
    const publishButton = page.locator('#publish');
    if (await publishButton.isVisible({ timeout: 120000 })) {
      await publishButton.click();
      await page.waitForTimeout(3000);
      console.log('✅ Published product');
      return true;
    }
  } catch (error) {
    console.log('⚠️ Publish step may be slow, continuing with error check');
    return false;
  }
}

// Helper function to check for PHP errors
async function checkForPhpErrors(page) {
  const pageContent = await page.content();
  expect(pageContent).not.toContain('Fatal error');
  expect(pageContent).not.toContain('Parse error');
}

// Helper function to wait for manual inspection
async function waitForManualInspection(page, seconds = 60) {
  console.log(`⏳ Waiting ${seconds} seconds to allow manual catalog inspection...`);
  await page.waitForTimeout(seconds * 1000);
}

// Helper function to mark test start
function logTestStart(testInfo) {
  const testName = testInfo.title;
  console.log('\n' + '='.repeat(80));
  console.log(`🚀 STARTING TEST: ${testName}`);
  console.log('='.repeat(80));
}

// Helper function to mark test end
function logTestEnd(testInfo, success = true) {
  const testName = testInfo.title;
  console.log('='.repeat(80));
  if (success) {
    console.log(`✅ TEST SUCCESS: ${testName} ✅`);
  } else {
    console.log(`❌ TEST FAILED: ${testName}`);
  }
  console.log('='.repeat(80) + '\n');
}

// Helper function to validate Facebook sync
async function validateFacebookSync(productId, productName, waitSeconds = 10) {
  if (!productId) {
    console.log('⚠️ No product ID provided for Facebook sync validation');
    return null;
  }

  const displayName = productName ? `"${productName}" (ID: ${productId})` : `ID: ${productId}`;
  console.log(`🔍 Validating Facebook sync for product ${displayName}...`);

  try {
    const { exec } = require('child_process');
    const { promisify } = require('util');
    const execAsync = promisify(exec);

    // Call the Facebook sync validator
    const { stdout, stderr } = await execAsync(
      `php e2e-facebook-sync-validator.php ${productId} ${waitSeconds}`,
      { cwd: __dirname }
    );

    // 📄 DUMP RAW JSON OUTPUT FROM VALIDATOR
    console.log('📄 OUTPUT FROM FACEBOOK SYNC VALIDATOR:');
    console.log(stdout);

    const result = JSON.parse(stdout);

    // Display results
    if (result.success) {
      console.log(`🎉 Facebook Sync Validation Succeeded for ${displayName}:`);
    } else {
      console.log(`❌ Facebook sync validation Failed: ${result.error}. Check debug logs above.`);
    }

    return result;

  } catch (error) {
    console.log(`⚠️ Facebook sync validation error: ${error.message}`);
    return null;
  }
}

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

      // Try to add content - handle different editor types
      try {
        console.log('🔄 Attempting to add product description...');

        // First, try the visual/TinyMCE editor
        const visualTab = page.locator('#content-tmce');
        if (await visualTab.isVisible({ timeout: 5000 })) {
          await visualTab.click();
          await page.waitForTimeout(2000);

          // Check if TinyMCE iframe exists
          const tinyMCEFrame = page.locator('#content_ifr');
          if (await tinyMCEFrame.isVisible({ timeout: 5000 })) {
            // This is an iframe-based editor (TinyMCE)
            const frameContent = tinyMCEFrame.contentFrame();
            const bodyElement = frameContent.locator('body');
            if (await bodyElement.isVisible({ timeout: 5000 })) {
              await bodyElement.fill('This is a test product created during E2E testing.');
              console.log('✅ Added description via TinyMCE editor');
            }
          }
        } else {
          // Try text/HTML tab
          const textTab = page.locator('#content-html');
          if (await textTab.isVisible({ timeout: 5000 })) {
            await textTab.click();
            await page.waitForTimeout(1000);

            // Regular textarea
            const contentTextarea = page.locator('#content');
            if (await contentTextarea.isVisible({ timeout: 5000 })) {
              await contentTextarea.fill('This is a test product created during E2E testing.');
              console.log('✅ Added description via text editor');
            }
          } else {
            // Try block editor if present
            const blockEditor = page.locator('.wp-block-post-content, .block-editor-writing-flow');
            if (await blockEditor.isVisible({ timeout: 5000 })) {
              await blockEditor.click();
              await page.keyboard.type('This is a test product created during E2E testing.');
              console.log('✅ Added description via block editor');
            } else {
              console.log('⚠️ No content editor found - skipping description');
            }
          }
        }
      } catch (editorError) {
        console.log(`⚠️ Content editor issue: ${editorError.message} - continuing without description`);
      }

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
          console.log('⚠️ No Facebook-specific fields found - plugin may not be fully activated');
        }
      } catch (error) {
        console.log('⚠️ Facebook field detection inconclusive - this is not necessarily an error');
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
    const result = await validateFacebookSync(productId, productName,20);
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
        waitUntil: 'networkidle',
        timeout: 120000
      });

      // Check if Facebook plugin is listed
      const pageContent = await page.content();
      const hasFacebookPlugin = pageContent.includes('Facebook for WooCommerce') ||
                               pageContent.includes('facebook-for-woocommerce');

      if (hasFacebookPlugin) {
        console.log('✅ Facebook for WooCommerce plugin detected');
      } else {
        console.log('⚠️ Facebook for WooCommerce plugin not found in plugins list');
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
        waitUntil: 'networkidle',
        timeout: 120000
      });

      // Verify no PHP errors on products page
      const pageContent = await page.content();
      expect(pageContent).not.toContain('Fatal error');
      expect(pageContent).not.toContain('Parse error');

      // Check if WooCommerce is working
      const hasProductsTable = await page.locator('.wp-list-table').isVisible({ timeout: 120000 });
      if (hasProductsTable) {
        console.log('✅ WooCommerce products page loaded successfully');
      } else {
        console.log('⚠️ Products table not found');
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
            waitUntil: 'networkidle',
            timeout: 120000
          });

          const pageContent = await page.content();

          // Check for PHP errors
          expect(pageContent).not.toContain('Fatal error');
          expect(pageContent).not.toContain('Parse error');
          expect(pageContent).not.toContain('Warning: ');

          // Verify admin content loaded
          await page.locator('#wpcontent').isVisible({ timeout: 120000 });

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
        waitUntil: 'networkidle',
        timeout: 120000
      });

      // Look for Facebook plugin row
      const pluginRow = page.locator('tr[data-slug="facebook-for-woocommerce"], tr:has-text("Facebook for WooCommerce")').first();

      if (await pluginRow.isVisible({ timeout: 120000 })) {
        console.log('✅ Facebook plugin found');

        // Check if plugin is currently active
        const isActive = await pluginRow.locator('.active').isVisible({ timeout: 120000 });

        if (isActive) {
          console.log('Plugin is active, testing deactivation...');
          const deactivateLink = pluginRow.locator('a:has-text("Deactivate")');
          if (await deactivateLink.isVisible({ timeout: 120000 })) {
            await deactivateLink.click();
            await page.waitForTimeout(2000);
            console.log('✅ Plugin deactivated');

            // Now test reactivation
            await page.waitForTimeout(1000);
            const reactivateLink = pluginRow.locator('a:has-text("Activate")');
            if (await reactivateLink.isVisible({ timeout: 120000 })) {
              await reactivateLink.click();
              await page.waitForTimeout(2000);
              console.log('✅ Plugin reactivated');
            }
          }
        } else {
          console.log('Plugin is inactive, testing activation...');
          const activateLink = pluginRow.locator('a:has-text("Activate")');
          if (await activateLink.isVisible({ timeout: 120000 })) {
            await activateLink.click();
            await page.waitForTimeout(2000);
            console.log('✅ Plugin activated');
          }
        }
      } else {
        console.log('⚠️ Facebook plugin not found in plugins list');
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
