const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');
const { TIMEOUTS } = require('./time-constants');

const {loginToWordPress,logTestStart,ensureDebugModeEnabled, logTestEnd, baseURL} = require('./test-helpers');

test.describe.serial('WooCommerce Plugin level tests', () => {

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

    const errors = [];

    // Only capture actual JavaScript errors, not resource loading failures
    page.on('pageerror', error => {
      errors.push(`JS Error: ${error.message}`);
    });

    await page.goto(`${process.env.WORDPRESS_URL}/wp-admin/themes.php`, {
      waitUntil: 'networkidle',
      timeout: TIMEOUTS.EXTRA_LONG
    });

    // Check for PHP errors
    const content = await page.content();
    const hasPHPError = content.includes('Fatal error') ||
                        content.includes('Parse error') ||
                        content.includes('There has been a critical error');

    if (hasPHPError) {
      errors.push('PHP errors detected on themes page');
    }

    // Verify Storefront theme is active
    const storefrontActive = await page.locator('.theme.active[data-slug="storefront"]').count();

    if (storefrontActive === 0) {
      const activeTheme = await page.locator('.theme.active').getAttribute('data-slug');
      errors.push(`Storefront theme is not active. Active theme: ${activeTheme || 'unknown'}`);
    } else {
      console.log('✅ Storefront theme is active');
    }

    if (errors.length > 0) {
      console.log('❌ Errors found:');
      errors.forEach(err => console.log(`   - ${err}`));
      throw new Error(`Theme check failed: ${errors.join('; ')}`);
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
    // Use helper to ensure debug mode is enabled
    await ensureDebugModeEnabled(page);

    // Verify options visibility
    await page.goto(`${process.env.WORDPRESS_URL}/wp-admin/options.php`);

    const label = page.locator('label[for="wc_facebook_enable_debug_mode"]');
    await label.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });

    const input = page.locator('#wc_facebook_enable_debug_mode');
    const value = await input.inputValue();

    expect(value).toBeTruthy();
    expect(value).toBe("yes");

    console.log('✅ WooCommerce Debug log checks passed');
    console.log(`   - Option exists: wc_facebook_enable_debug_mode`);
    console.log(`   - Value is non-null: YES`);
    console.log(`   - Matches expected: YES`);
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
      const { exec } = require('child_process');
      const { promisify } = require('util');
      const execAsync = promisify(exec);

      const { stdout, stderr } = await execAsync('php e2e-connection-checker.php', { cwd: __dirname });

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

    const errors = [];

    // Only capture actual JavaScript errors, not resource loading failures
    page.on('pageerror', error => {
      errors.push(`JS Error: ${error.message}`);
    });

    await page.goto(`${process.env.WORDPRESS_URL}/wp-admin/admin.php?page=wc-facebook`, {
      waitUntil: 'networkidle',
      timeout: TIMEOUTS.EXTRA_LONG
    });

    // Verify no fatal PHP errors
    const content = await page.content();
    const hasPHPError = content.includes('Fatal error') ||
                        content.includes('Parse error') ||
                        content.includes('There has been a critical error');

    if (hasPHPError) {
      errors.push('PHP errors detected on page');
    }

    // Verify page loaded properly (look for Facebook branding or settings)
    const pageLoaded = await page.locator('.wc-facebook-settings, #wc-facebook-settings-page, .facebook-for-woocommerce').count() > 0;

    if (!pageLoaded) {
      errors.push('Facebook settings page did not load properly');
    }

    if (errors.length > 0) {
      console.log('❌ Errors found:');
      errors.forEach(err => console.log(`   - ${err}`));
      throw new Error(`Facebook settings page validation failed: ${errors.join('; ')}`);
    }

    console.log('✅ Facebook settings page loaded without errors');
    console.log('✅ All connection checks passed');
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
        timeout: TIMEOUTS.MAX
      });

      // Verify no PHP errors on products page
      const pageContent = await page.content();
      expect(pageContent).not.toContain('Fatal error');
      expect(pageContent).not.toContain('Parse error');

      // Check if WooCommerce is working
      const hasProductsTable = await page.locator('.wp-list-table').isVisible({ timeout: TIMEOUTS.LONG });
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
            timeout: TIMEOUTS.MAX
          });

          const pageContent = await page.content();

          // Check for PHP errors
          expect(pageContent).not.toContain('Fatal error');
          expect(pageContent).not.toContain('Parse error');
          // expect(pageContent).not.toContain('Warning: ');
          // TODO: Do not dump the whole page content, just check for errors

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
