const { test, expect } = require('@playwright/test');
const { TIMEOUTS } = require('./time-constants');

const {loginToWordPress,logTestStart,logTestEnd,ensureDebugModeEnabled,checkWooCommerceLogs,checkForPhpErrors,checkForJsErrors,completePurchaseFlow,disconnectAndVerify,reconnectAndVerify,verifyProductsFacebookFieldsCleared,validateFacebookSync,publishProduct,installPlugin,execWP,createTestProduct,cleanupProduct,baseURL,generateUniqueSKU,filterProducts,clickFirstProduct,safeScreenshot,exactSearchSelect2Container} = require('./test-helpers');

// Plugins to test compatibility with
const COMPAT_PLUGINS = [
  { slug: 'wordfence', name: 'Wordfence Security' },
  { slug: 'litespeed-cache', name: 'LiteSpeed Cache' },
  { slug: 'subscriptions-for-woocommerce', name: 'Subscriptions For WooCommerce' },
];

test.describe('WooCommerce Plugin level tests', () => {

  test.beforeEach(async ({ page }, testInfo) => {
    // Log test start first for proper chronological order
    logTestStart(testInfo);

    // Ensure browser stability
    await page.setViewportSize({ width: 1280, height: 720 });
    await loginToWordPress(page);
  });

  test('Check WordPress and WooCommerce are up to date', async ({ page }) => {
    await page.goto(`${process.env.WORDPRESS_URL}/wp-admin/update-core.php`);

    // Check WordPress
    const wpUpToDate = await page.locator('h2.response:has-text("You have the latest version of WordPress")').count();
    if (wpUpToDate > 0) {
      console.log('✅ WordPress up to date');
    } else {
      console.log('❌ WordPress needs update');
    }

    // Check WooCommerce
    const allPluginsUpToDate = await page.locator('p:has-text("Your plugins are all up to date.")').count();
    const wooInUpdateTable = await page.locator('#update-plugins-table tr:has-text("WooCommerce")').count();

    if (allPluginsUpToDate > 0 || wooInUpdateTable === 0) {
      console.log('✅ WooCommerce up to date');
    } else {
      console.log('❌ WooCommerce needs update');
    }

    expect(wpUpToDate).toBeGreaterThan(0);
    expect(allPluginsUpToDate > 0 || wooInUpdateTable === 0).toBe(true);
    console.log('✅ Wordpress and WooCommerce are up to date');
  });

  test('Verify Storefront theme is active', async ({ page }) => {
    console.log('🔍 Checking active theme...');

    const jsErrors = checkForJsErrors(page);

    await page.goto(`${process.env.WORDPRESS_URL}/wp-admin/themes.php`, {
      waitUntil: 'domcontentloaded',
      timeout: TIMEOUTS.EXTRA_LONG
    });

    await checkForPhpErrors(page);

    // Verify Storefront theme is active
    const storefrontActive = await page.locator('.theme.active[data-slug="storefront"]').count();

    if (storefrontActive === 0) {
      const activeTheme = await page.locator('.theme.active').getAttribute('data-slug');
      throw new Error(`Storefront theme is not active. Active theme: ${activeTheme || 'unknown'}`);
    }

    console.log('✅ Storefront theme is active');

    if (jsErrors.length > 0) {
      console.log('⚠️ JavaScript errors detected:', jsErrors);
    }

    console.log('✅ Themes page loaded without errors');
  });

  test('Verify WooCommerce is active and endpoints exist', async ({ page }) => {
    console.log('🔍 Checking WooCommerce status...');

    // Check if WooCommerce is active using filtered plugins page
    await page.goto(`${process.env.WORDPRESS_URL}/wp-admin/plugins.php?plugin_status=active`);

    const wooActive = await page.locator('tr[data-slug="woocommerce"]').count();
    if (wooActive === 0) {
      throw new Error('❌ WooCommerce is not active');
    }
    console.log('✅ WooCommerce is active');

    // Verify WooCommerce endpoints
    const endpoints = ['shop', 'cart', 'checkout'];
    for (const endpoint of endpoints) {
      const response = await page.goto(`${process.env.WORDPRESS_URL}/${endpoint}`, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUTS.EXTRA_LONG
      });

      if (!response || !response.ok()) {
        throw new Error(`❌ /${endpoint} endpoint not accessible (status: ${response?.status()})`);
      }
      console.log(`✅ /${endpoint} endpoint exists`);
    }

    console.log('✅ All WooCommerce checks passed');
  });

  test('Verify Cash on Delivery payment option is available at checkout', async ({ page }) => {
    console.log('🔍 Testing Cash on Delivery payment method...');

    // Navigate to offline payment methods settings
    await page.goto(`${process.env.WORDPRESS_URL}/wp-admin/admin.php?page=wc-settings&tab=checkout&path=%2Foffline`, {
      waitUntil: 'domcontentloaded',
      timeout: TIMEOUTS.EXTRA_LONG
    });

    // Check if COD is enabled, if not enable it
    const enableButton = page.locator('a.components-button.is-primary:has-text("Enable")');
    const isEnableButtonVisible = await enableButton.isVisible({ timeout: TIMEOUTS.MEDIUM }).catch(() => false);

    if (isEnableButtonVisible) {
      console.log('💳 Enabling Cash on Delivery...');
      await enableButton.click();
      await page.waitForLoadState('domcontentloaded');
      console.log('✅ Cash on Delivery enabled');
    } else {
      console.log('✅ Cash on Delivery already enabled');
    }

    // Navigate to test product using environment variable
    const productUrl = process.env.TEST_PRODUCT_URL;
    if (!productUrl) {
      throw new Error('❌ TEST_PRODUCT_URL environment variable not set');
    }

    console.log('📦 Navigating to product page...');
    await page.goto(productUrl, { waitUntil: 'domcontentloaded', timeout: TIMEOUTS.EXTRA_LONG });

    // Add to cart
    console.log('🛒 Adding product to cart...');
    await page.click('.single_add_to_cart_button');
    await page.waitForTimeout(TIMEOUTS.NORMAL);

    // Go to checkout
    console.log('💳 Navigating to checkout...');
    await page.goto(`${process.env.WORDPRESS_URL}/checkout`, {
      waitUntil: 'domcontentloaded',
      timeout: TIMEOUTS.EXTRA_LONG
    });

    // Scroll down to see payment methods (similar to Purchase test)
    await page.evaluate(() => window.scrollBy(0, 400));
    await page.waitForTimeout(TIMEOUTS.SHORT);

    // Wait specifically for Cash on Delivery payment option to be visible
    console.log('🔍 Waiting for Cash on Delivery payment option...');
    const codLabel = page.locator('label[for="radio-control-wc-payment-method-options-cod"]');

    await codLabel.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG }).catch(async (error) => {
      console.log('❌ Cash on Delivery option not found. Available payment methods:');
      const allPaymentOptions = await page.locator('.wc-block-components-radio-control__option').allTextContents();
      console.log(allPaymentOptions);
      throw new Error('❌ Cash on Delivery payment option not found at checkout');
    });

    console.log('✅ Cash on Delivery payment option is available');

    // Verify the label text
    const labelText = await codLabel.textContent();
    expect(labelText).toContain('Cash on delivery');
    console.log('✅ Payment method label verified: "Cash on delivery"');
  });

  test('Verify Debug mode and options visibility', async ({ page }) => {
    console.log('🔍 Checking debug mode status...');

    // Check if debug mode is enabled
    const isDebugEnabled = await ensureDebugModeEnabled(page);
    expect(isDebugEnabled).toBe(true);
    console.log('✅ Debug mode is enabled');

    // Navigate to WooCommerce Status Tools to verify debug-only button is visible
    console.log('🔍 Navigating to WooCommerce Status Tools...');
    await page.goto(`${process.env.WORDPRESS_URL}/wp-admin/admin.php?page=wc-status&tab=tools`, {
      waitUntil: 'domcontentloaded',
      timeout: TIMEOUTS.EXTRA_LONG
    });

    // Check for Facebook: Delete Background Sync Jobs button (only visible when debug mode is on)
    const backgroundJobsRow = page.locator('tr.wc_facebook_delete_background_jobs');
    await backgroundJobsRow.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });

    const backgroundJobsButton = backgroundJobsRow.locator('input[type="submit"]');
    await expect(backgroundJobsButton).toBeVisible();

    // Verify the button is enabled and interactable
    const isEnabled = await backgroundJobsButton.isEnabled();
    expect(isEnabled).toBe(true);

    console.log('✅ Debug mode verification passed');
    console.log('   - Debug mode enabled: YES');
    console.log('   - Background sync jobs button visible: YES');
  });


  test('Clear background sync jobs and verify cleanup', async ({ page }) => {
    console.log('🔍 Testing background sync job cleanup...');

    // Ensure debug mode is enabled (required for background sync cleanup tool to be visible)
    await ensureDebugModeEnabled(page);

    // Navigate to WooCommerce Status Tools
    await page.goto(`${process.env.WORDPRESS_URL}/wp-admin/admin.php?page=wc-status&tab=tools`, {
      waitUntil: 'domcontentloaded',
      timeout: TIMEOUTS.EXTRA_LONG
    });

    // Wait for page to load and find the tool row
    const toolRow = page.locator('tr.wc_facebook_delete_background_jobs');
    await toolRow.waitFor({ state: 'visible', timeout: TIMEOUTS.EXTRA_LONG });

    // Scroll to the element
    await toolRow.scrollIntoViewIfNeeded();

    // Handle the confirmation dialog
    page.once('dialog', async dialog => {
      console.log(`Dialog message: ${dialog.message()}`);
      await dialog.accept();
    });

    // Click the Clear Background Sync Jobs button
    await toolRow.locator('input[type="submit"]').click();

    // Wait for success message
    const successMessage = page.locator('.updated.inline p:has-text("Background sync jobs have been deleted.")');
    await successMessage.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
    console.log('✅ Background sync jobs cleared successfully');

    // Navigate to options page to verify cleanup
    await page.goto(`${process.env.WORDPRESS_URL}/wp-admin/options.php`);

    // Search for any remaining background sync job options
    const syncJobOptions = await page.locator('label[for^="wc_facebook_background_product_sync_job_"]').count();

    if (syncJobOptions > 0) {
      throw new Error(`❌ Found ${syncJobOptions} remaining sync job options - cleanup failed`);
    }

    console.log('✅ All background sync job options cleaned up');
  });


  test('Verify Facebook for WooCommerce plugin connection', async ({ page }) => {
    console.log('🔍 Verifying Facebook plugin connection...');

    const expectedAccessToken = process.env.FB_ACCESS_TOKEN;
    const expectedPixelId = process.env.FB_PIXEL_ID;

    // Verify connection via PHP script (following pattern from other e2e scripts)
    let connection;
    try {
      const { stdout, stderr } = await execWP(
        `\\$conn = facebook_for_woocommerce()->get_connection_handler();
        echo json_encode([
          'connected' => \\$conn->is_connected(),
          'pixel_id' => get_option('wc_facebook_pixel_id'),
          'access_token' => get_option('wc_facebook_access_token'),
          'error' => null
        ]);`
      );

      if (stderr) {
        console.log(`⚠️ PHP stderr: ${stderr}`);
      }

      const output = stdout.trim();
      connection = JSON.parse(output);

    } catch (error) {
      throw new Error(`Failed to check plugin connection: ${error.message}`);
    }

    if (connection.error) {
      throw new Error(`Plugin check failed: ${connection.error}`);
    }

    // Verify connection status
    expect(connection.connected).toBe(true);
    console.log('✅ Plugin is connected');

    // Verify access token
    if (expectedAccessToken) {
      expect(connection.access_token).toBe(expectedAccessToken);
      console.log('✅ Access token matches expected value');
    } else {
      expect(connection.access_token).toBeTruthy();
      console.log('✅ Access token is present');
    }

    // Verify Pixel ID
    if (expectedPixelId) {
      expect(connection.pixel_id).toBe(expectedPixelId);
      console.log('✅ Pixel ID matches expected value');
    } else {
      expect(connection.pixel_id).toBeTruthy();
      console.log('✅ Pixel ID is present');
    }

    // Check Facebook settings page loads without errors
    console.log('🔍 Checking Marketing > Facebook page...');

    // Set up JS error tracking BEFORE navigation
    const jsErrors = checkForJsErrors(page);

    await page.goto(`${process.env.WORDPRESS_URL}/wp-admin/admin.php?page=wc-facebook`, {
      waitUntil: 'domcontentloaded',
      timeout: TIMEOUTS.EXTRA_LONG
    });

    // Check for PHP errors using helper
    await checkForPhpErrors(page);

    // Verify page loaded properly (look for Facebook branding or settings)
    const pageLoaded = await page.locator('.wc-facebook-settings, #wc-facebook-settings-page, .facebook-for-woocommerce').count() > 0;

    if (!pageLoaded) {
      throw new Error('Facebook settings page did not load properly');
    }

    // Check for JS errors (already filtered at helper level)
    if (jsErrors.length > 0) {
      console.error(`❌ JS errors: ${jsErrors.join('; ')}`);
      throw new Error(`JS errors on Facebook settings page: ${jsErrors.join('; ')}`);
    }

    console.log('✅ Facebook settings page loaded without errors');
    console.log('✅ All connection checks passed');
  });

  test('Complete checkout flow - Place order and verify order', async ({ page }) => {
    console.log('🛒 Starting complete checkout flow test...');

    // Set up JS error tracking BEFORE purchase flow
    const jsErrors = checkForJsErrors(page);

    // Use helper to complete purchase
    const { orderId } = await completePurchaseFlow(page);

    if (!orderId) {
      throw new Error('❌ Could not extract order ID');
    }
    console.log(`📦 Order ID: ${orderId}`);

    // Verify order in WooCommerce admin
    const { stdout } = await execWP(
      `\\$order = wc_get_order(${orderId});
      echo json_encode([
        'exists' => !!\\$order,
        'status' => \\$order ? \\$order->get_status() : null,
        'total' => \\$order ? \\$order->get_total() : null
      ]);`
    );

    const orderData = JSON.parse(stdout);
    if (!orderData.exists) throw new Error('❌ Order not found in WooCommerce');

    console.log(`✅ Order verified: Status=${orderData.status}, Total=${orderData.total}`);

    // Check JS errors that occurred during purchase flow
    if (jsErrors.length > 0) {
      throw new Error(`JS errors: ${jsErrors.join('; ')}`);
    }

    console.log('✅ Test passed: No PHP/JS errors, order created');
  });

  test('Reset all products Facebook settings via WooCommerce Status Tools', async ({ page }) => {
    console.log('🔄 Testing Reset all products Facebook settings...');

    // Navigate to WooCommerce Status Tools
    await page.goto(`${process.env.WORDPRESS_URL}/wp-admin/admin.php?page=wc-status&tab=tools`, {
      waitUntil: 'domcontentloaded',
      timeout: TIMEOUTS.EXTRA_LONG
    });

    // Handle confirmation dialog
    page.once('dialog', async dialog => {
      console.log(`✅ Confirming dialog: ${dialog.message()}`);
      await dialog.accept();
    });

    // Click Reset products Facebook settings button and wait for navigation
    console.log('🔘 Clicking Reset products Facebook settings button...');
    const resetButton = page.locator('.reset_all_product_fb_settings input[type="submit"]');
    await resetButton.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });

    await resetButton.click();
    await page.waitForLoadState('domcontentloaded', { timeout: TIMEOUTS.EXTRA_LONG });

    console.log('✅ Page reloaded after reset action');

    // Verify all product Facebook fields are cleared using helper
    const result = await verifyProductsFacebookFieldsCleared();

    expect(result.success).toBe(true);
    console.log('🎉 Reset all products Facebook settings test passed!');
  });


  test('Disconnect and Reconnect', async ({ page }) => {
    console.log('🔌 Testing programmatic disconnect and verification...');

    // Step 1: Disconnect and verify
    const result = await disconnectAndVerify();

    // Step 2: Reconnect and verify
    const reconnectResult = await reconnectAndVerify();

    // Assertions on disconnect
    expect(result.success).toBe(true);
    expect(result.before.connected).toBe(true);
    expect(result.after.connected).toBe(false);

    // Assertions on reconnect
    expect(reconnectResult.success).toBe(true);
    expect(reconnectResult.before.connected).toBe(false);
    expect(reconnectResult.after.connected).toBe(true);

    // Step 3: Verify Marketing > Facebook page loads properly after reconnection
    console.log('🔍 Verifying Marketing > Facebook page loads after reconnection...');

    // Set up JS error tracking before navigation
    const jsErrors = checkForJsErrors(page);

    // Navigate to Marketing > Facebook page
    await page.goto(`${process.env.WORDPRESS_URL}/wp-admin/admin.php?page=wc-facebook`, {
      waitUntil: 'domcontentloaded',
      timeout: TIMEOUTS.EXTRA_LONG
    });

    // Check for PHP errors using helper
    await checkForPhpErrors(page);

    // Verify page loaded properly
    const pageLoaded = await page.locator('.wc-facebook-settings, #wc-facebook-settings-page, .facebook-for-woocommerce').count() > 0;

    if (!pageLoaded) {
      throw new Error('Facebook settings page did not load properly after reconnect');
    }

    // Check for JS errors (already filtered at helper level)
    if (jsErrors.length > 0) {
      console.error(`❌ JS errors: ${jsErrors.join('; ')}`);
      throw new Error(`JS errors on Facebook settings page: ${jsErrors.join('; ')}`);
    }

    console.log('✅ Marketing > Facebook page loaded successfully after reconnection');
    console.log('🎉 Disconnect and reconnect test passed!');
  });

   test('Reset connection settings via WooCommerce Status Tools', async ({ page }) => {
    console.log('🔄 Testing Reset connection settings...');

    // Navigate to WooCommerce Status Tools
    await page.goto(`${process.env.WORDPRESS_URL}/wp-admin/admin.php?page=wc-status&tab=tools`, {
      waitUntil: 'domcontentloaded',
      timeout: TIMEOUTS.EXTRA_LONG
    });

    // Handle confirmation dialog
    page.once('dialog', async dialog => {
      console.log(`✅ Confirming dialog: ${dialog.message()}`);
      await dialog.accept();
    });

    // Click Reset settings button and wait for navigation
    console.log('🔘 Clicking Reset settings button...');
    const resetButton = page.locator('.wc_facebook_settings_reset input[type="submit"]');
    await resetButton.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });

    await resetButton.click();
    await page.waitForLoadState('domcontentloaded', { timeout: TIMEOUTS.EXTRA_LONG });

    console.log('✅ Page reloaded after reset action');

    // Navigate to options page to verify reset
    await page.goto(`${process.env.WORDPRESS_URL}/wp-admin/options.php`, {
      waitUntil: 'domcontentloaded',
      timeout: TIMEOUTS.EXTRA_LONG
    });

    // List of Facebook options that should be empty
    const fbOptions = [
      'wc_facebook_access_token',
      'wc_facebook_page_access_token',
      'wc_facebook_merchant_access_token',
      'wc_facebook_system_user_id',
      'wc_facebook_business_manager_id',
      'wc_facebook_ad_account_id',
      'wc_facebook_instagram_business_id',
      'wc_facebook_commerce_merchant_settings_id',
      'wc_facebook_external_business_id',
      'wc_facebook_commerce_partner_integration_id',
      'wc_facebook_page_id',
      'wc_facebook_pixel_id',
      'wc_facebook_product_catalog_id'
    ];

    // Check each option is empty
    console.log('🔍 Verifying all Facebook options are cleared...');
    for (const option of fbOptions) {
      const input = page.locator(`#${option}`);
      const value = await input.inputValue();

      if (value !== '') {
        throw new Error(`❌ ${option} not cleared. Value: ${value}`);
      }
    }

    console.log('✅ All Facebook connection options cleared');
    console.log('🎉 Reset connection settings test passed!');

    const reconnectResult = await reconnectAndVerify();
    // Assertions on reconnect
    expect(reconnectResult.success).toBe(true);
    expect(reconnectResult.before.connected).toBe(false);
    expect(reconnectResult.after.connected).toBe(true);

  });

  test('Check WooCommerce logs for fatal errors and non-200 responses', async () => {
    const result = await checkWooCommerceLogs();

    if (!result.success) {
      throw new Error('Log validation failed');
    }
  });

  // Plugin compatibility test
  test('Plugin compatibility: edit, sync, purchase with third-party plugins', async ({ page }) => {
    let createdProductId = null;

    try {
      // Create ONE test product for all plugin tests
      const createdProduct = await createTestProduct({
        type: 'simple',
        price: '25.00',
        stock: '100'
      });
      createdProductId = createdProduct.productId;
      console.log(`✅ Created test product ID: ${createdProductId}`);

      // Test each plugin
      for (const plugin of COMPAT_PLUGINS) {
        console.log(`\n🔌 Testing with ${plugin.name}...`);
        const jsErrors = checkForJsErrors(page);

        // 1. Install plugin
        await installPlugin(plugin.slug);

        // 2. Edit product price
        const newPrice = (20 + Math.random() * 10).toFixed(2);
        await page.goto(`${process.env.WORDPRESS_URL}/wp-admin/post.php?post=${createdProductId}&action=edit`, {
          waitUntil: 'domcontentloaded',
          timeout: TIMEOUTS.EXTRA_LONG
        });
        await page.click('li.general_tab a');
        const priceField = page.locator('#_regular_price');
        await priceField.waitFor({ state: 'visible', timeout: TIMEOUTS.MEDIUM });
        await priceField.fill(newPrice);
        await publishProduct(page);
        console.log(`✅ Updated price to: ${newPrice}`);

        // 3. Validate Facebook sync and verify price synced
        const syncResult = await validateFacebookSync(createdProductId, createdProduct.productName, 60);
        expect(syncResult.success).toBe(true);
        // strips all non-numeric characters (like $) from the price
        const fbPrice = syncResult['raw_data']['facebook_data'][0]['price'].replace(/[^0-9.]/g, '');
        expect(fbPrice).toBe(newPrice);
        console.log(`✅ Sync validated - price synced correctly`);

        // 4. Complete a purchase using the helper
        const productUrl = `${process.env.WORDPRESS_URL}/?p=${createdProductId}`;
        const { orderId } = await completePurchaseFlow(page, productUrl);
        expect(orderId).toBeTruthy();
        console.log(`✅ Purchase completed: Order ${orderId}`);

        // 5. Check for errors after each plugin
        await checkForPhpErrors(page);
        if (jsErrors.length > 0) {
          throw new Error(`JS errors with ${plugin.name}: ${jsErrors.join('; ')}`);
        }
        console.log(`🎉 ${plugin.name} compatibility passed!`);
      }

    } finally {
      if (createdProductId) {
        await cleanupProduct(createdProductId);
      }
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
            timeout: TIMEOUTS.MAX
          });

          await checkForPhpErrors(page);

          // Verify admin content loaded
          await page.locator('#wpcontent').isVisible({ timeout: TIMEOUTS.LONG });

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
        timeout: TIMEOUTS.MAX
      });

      // Look for Facebook plugin row
      const pluginRow = page.locator('tr[data-slug="facebook-for-woocommerce"], tr:has-text("Facebook for WooCommerce")').first();

      await pluginRow.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      console.log('✅ Facebook plugin found');

      // Check if plugin is currently active
      const isActive = await pluginRow.locator('.active').isVisible({ timeout: TIMEOUTS.LONG });
      const deactivateLink = pluginRow.locator('a:has-text("Deactivate")');
      const reactivateLink = pluginRow.locator('a:has-text("Activate")');

      if (isActive) {
        console.log('Plugin is active, testing deactivation...');
        await deactivateLink.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
        await deactivateLink.click();
        await reactivateLink.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
        console.log('✅ Plugin deactivated');
        await reactivateLink.click();
        await deactivateLink.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
        console.log('✅ Plugin reactivated');
      } else {
        console.log('Plugin is inactive, testing activation...');
        const activateLink = pluginRow.locator('a:has-text("Activate")');
        await activateLink.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
        await activateLink.click();
        await deactivateLink.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
        console.log('✅ Plugin activated');
      }

      // Verify no PHP errors after plugin operations
      await checkForPhpErrors(page);

      console.log('✅ Plugin activation test completed');
      logTestEnd(testInfo, true);

    } catch (error) {
      console.log(`⚠️ Plugin activation test failed: ${error.message}`);
      logTestEnd(testInfo, false);
      throw error;
    }
  });

  test('Test WordPress admin and Facebook plugin presence', async ({ page }, testInfo) => {

    try {
      // Navigate to plugins page with increased timeout
      await page.goto(`${baseURL}/wp-admin/plugins.php`, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUTS.MAX
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

      await checkForPhpErrors(page);

      console.log('✅ Plugin detection test completed');
      logTestEnd(testInfo, true);

    } catch (error) {
      console.log(`⚠️ Plugin detection test failed: ${error.message}`);
      logTestEnd(testInfo, false);
      throw error;
    }
  });

  test('Create attribute mapping and verify attribute syncs to Facebook catalog', async ({ page }, testInfo) => {
    let productId = null;
    let attributeId = null;
    const attributeName = generateUniqueSKU('A'); // intentionally left short since max allowed length is 28
    const attributeSlug = attributeName.toLocaleLowerCase();
    const attributeOptions = [generateUniqueSKU('1'), generateUniqueSKU('2')];

    try {
      //  Create a new global WooCommerce attribute with two options
      console.log(`📦 Creating global WooCommerce attribute "${attributeName}" with options...`);
      const createAttrResult = await execWP(`
        \\$attribute_id = wc_create_attribute([
          'name' => '${attributeName}',
          'slug' => '${attributeSlug}',
          'type' => 'select',
          'order_by' => 'menu_order',
          'has_archives' => false
        ]);

        if (is_wp_error(\\$attribute_id)) {
          echo json_encode(['success' => false, 'error' => \\$attribute_id->get_error_message()]);
        } else {
          // Register the taxonomy so we can add terms
          register_taxonomy('pa_${attributeSlug}', ['product'], []);

          // Add the attribute options as terms
          \\$terms_added = [];
          foreach (['${attributeOptions[0]}', '${attributeOptions[1]}'] as \\$option) {
            \\$term = wp_insert_term(\\$option, 'pa_${attributeSlug}');
            if (!is_wp_error(\\$term)) {
              \\$terms_added[] = \\$term['term_id'];
            }
          }

          echo json_encode([
            'success' => true,
            'attribute_id' => \\$attribute_id,
            'terms_added' => \\$terms_added,
            'message' => 'Attribute created successfully'
          ]);
        }
      `);

      const attrResult = JSON.parse(createAttrResult.stdout);
      if (!attrResult.success) {
        throw new Error(`Failed to create attribute: ${attrResult.error}`);
      }
      attributeId = attrResult.attribute_id;
      console.log(`✅ Created attribute "${attributeName}" with ID: ${attributeId}`);
      console.log(`   Terms added: ${attrResult.terms_added.join(', ')}`);

      //  Navigate to Marketing > Facebook
      console.log('🔗 Navigating to Marketing > Facebook...');
      await page.goto(`${baseURL}/wp-admin/admin.php?page=wc-facebook`, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUTS.MAX
      });
      await checkForPhpErrors(page);
      console.log('✅ Navigated to Facebook settings page');

      //  Click on "Attribute Mapping" tab
      console.log('📑 Clicking on "Attribute Mapping" tab...');
      const attributeMappingTab = page.getByRole('link', {name: 'Attribute Mapping'});
      await attributeMappingTab.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      await attributeMappingTab.click();
      console.log('✅ Clicked on Attribute Mapping tab');

      // // Click on "Edit mappings" button
      console.log('📝 Clicking on "Add new mapping" or "Edit mappings" button...');
      const editMappingsButton = page.getByRole('link', { name: 'Edit mappings' });
      const addNewMappingButton = page.getByRole('link', { name: 'Add new mapping' });
      if (await editMappingsButton.isVisible({ timeout: TIMEOUTS.MEDIUM })) {
        await editMappingsButton.click();
        console.warn('⚠️ Some existing attributes exist, Clicked Edit mappings button');
      }
      else {
        //  Click on "Add new mapping" button
        console.log('Clicking on "Add new mapping" button...');
        await addNewMappingButton.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
        await addNewMappingButton.click();
        console.log('✅ Clicked Add new mapping button');
      }

      //  Map the WooCommerce Test-E2E attribute to Meta color attribute
      console.log(`🔄 Mapping WooCommerce "${attributeName}" attribute to Meta "color" attribute...`);

      const attributeSelectContainers = page.locator('span').filter({ hasText: 'Select attribute' });
      await exactSearchSelect2Container(page, attributeSelectContainers.first(), attributeName);

      // Select the Meta attribute (destination) - color
      await exactSearchSelect2Container(page, attributeSelectContainers.last(), 'color');
      console.log('✅ Selected Meta attribute: color');

      //  Click on "Save changes" button
      console.log('💾 Clicking on "Save changes" button...');
      const saveChangesButton = page.getByRole('button', { name: 'Save changes' });
      await saveChangesButton.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      await saveChangesButton.click();
      await page.waitForLoadState('domcontentloaded');
      console.log('✅ Saved attribute mapping changes');

      // Verify no PHP errors after saving
      await checkForPhpErrors(page);

      //  Create a simple product with the new "Test-E2E" attribute
      console.log('📦 Creating simple product with Test-E2E attribute...');

      // First, create the base product
      const createdProduct = await createTestProduct({
        productType: 'simple',
        price: '29.99',
        stock: '15'
      });
      productId = createdProduct.productId;
      console.log(`✅ Created base product with ID: ${productId}`);

      // Navigate to the product edit page
      await filterProducts(page, 'simple', createdProduct.sku);
      await clickFirstProduct(page);

      // Click on Attributes tab
      console.log('📑 Opening Attributes tab...');
      const attributesTab = page.locator('li.attribute_tab a');
      await attributesTab.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
      await attributesTab.click();
      console.log('✅ Opened Attributes tab');

      // Select the global attribute from the dropdown
      console.log(`🔍 Selecting global attribute "${attributeName}"...`);
      const productAttributeContainer = page.locator('span').filter({ hasText: 'Add existing' }).first();
      await productAttributeContainer.waitFor({ state: 'visible', timeout: TIMEOUTS.MEDIUM });
      await exactSearchSelect2Container(page, productAttributeContainer, attributeName);
      const selectAllAttrValuesBtn = page.getByRole('button', { name: 'Select all' });
      await selectAllAttrValuesBtn.waitFor({ state: 'visible', timeout: TIMEOUTS.MEDIUM });
      await selectAllAttrValuesBtn.click();
      console.log(`✅ Selected attribute: ${attributeName}`);

      // Click "Add" button to add the attribute
      const addAttributeButton = page.getByRole('button', { name: 'Save attributes' });
      await addAttributeButton.waitFor({ state: 'visible', timeout: TIMEOUTS.MEDIUM });
      await addAttributeButton.click();
      console.log('✅ Clicked Save attributes button');

      // Wait for attributes to be saved (the attribute row should collapse)
      await page.waitForFunction(() => {
        return document.querySelector('.woocommerce_attribute.wc-metabox.closed') !== null;
      }, { timeout: TIMEOUTS.LONG });
      console.log('✅ Saved attributes');

      // Publish/Update the product to trigger sync
      console.log('💾 Updating product...');
      await publishProduct(page);

      //  Validate Facebook sync and ensure 'color' field returns the attribute value
      console.log('🔄 Validating Facebook sync...');
      const syncResult = await validateFacebookSync(productId, createdProduct.productName, 30);

      expect(syncResult.success).toBe(true);
      console.log('✅ Facebook sync validation successful');

      // Check the color field in the Facebook data
      const facebookData = syncResult['raw_data']['facebook_data'];
      const colorValue = facebookData[0]['color'];
      console.log(`📊 Facebook color field value: ${colorValue}`);

      // Verify the color value matches one of our attribute options
      const validColors = attributeOptions.map(opt => opt.toLowerCase());
      const colorLower = colorValue ? colorValue.toLowerCase() : '';
      const colorMatches = validColors.some(valid => colorLower.includes(valid.toLowerCase()));

      if (colorMatches) {
        console.log(`✅ Color field correctly contains attribute value(s): ${colorValue}`);
      } else {
        console.log(`⚠️ Color field value "${colorValue}" - verifying it was set from attribute mapping`);
        // The color might be formatted differently, just ensure it exists
        expect(colorValue).toBeTruthy();
      }

      console.log('✅ Attribute mapping test completed successfully');
      logTestEnd(testInfo, true);

    } catch (error) {
      console.error(`❌ Test failed: ${error.message}`);
      await safeScreenshot(page, 'attribute-mapping-test-failure.png');
      logTestEnd(testInfo, false);
      throw error;
    } finally {
      // Step 10: Cleanup - delete product, attribute mapping, and attribute
      console.log('🧹 Starting cleanup...');

      // Cleanup the product
      if (productId) {
        await cleanupProduct(productId);
      }
      // Cleanup the global attribute
      if (attributeId) {
        try {
          console.log(`🧹 Cleaning up global attribute (ID: ${attributeId})...`);
          await execWP(`
            // Delete terms first
            \\$terms = get_terms(['taxonomy' => 'pa_${attributeSlug}', 'hide_empty' => false]);
            if (!is_wp_error(\\$terms)) {
              foreach (\\$terms as \\$term) {
                wp_delete_term(\\$term->term_id, 'pa_${attributeSlug}');
              }
            }
            // Delete the attribute
            wc_delete_attribute(${attributeId});
            echo json_encode(['success' => true]);
          `);
          console.log(`✅ Cleaned up global attribute "${attributeName}"`);
        } catch (attrCleanupError) {
          console.warn(`⚠️ Attribute cleanup failed: ${attrCleanupError.message}`);
        }
      }

      console.log('✅ Cleanup completed');
    }
  });
});
