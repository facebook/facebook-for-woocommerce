const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');
const { TIMEOUTS } = require('./time-constants');

const {loginToWordPress,logTestStart} = require('./test-helpers');

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
    console.log('🔍 Checking debug mode status...');

    // Navigate to Facebook settings page
    await page.goto(`${process.env.WORDPRESS_URL}/wp-admin/admin.php?page=wc-facebook`, {
      waitUntil: 'domcontentloaded',
      timeout: TIMEOUTS.EXTRA_LONG
    });

    // Click Troubleshooting toggle to expand drawer
    const troubleshootingToggle = page.locator('#toggle-troubleshooting-drawer');
    await troubleshootingToggle.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
    await troubleshootingToggle.click();
    await page.waitForTimeout(TIMEOUTS.INSTANT);

    // Check debug mode checkbox status
    const debugModeCheckbox = page.locator('#wc_facebook_enable_debug_mode');
    await debugModeCheckbox.waitFor({ state: 'visible', timeout: TIMEOUTS.MEDIUM });

    const isChecked = await debugModeCheckbox.isChecked();

    if (!isChecked) {
      console.log('⚙️ Enabling debug mode...');
      await debugModeCheckbox.check();

      // Save changes
      const saveButton = page.locator('input[name="save_shops_settings"]');
      await saveButton.click();
      await page.waitForLoadState('domcontentloaded');
      console.log('✅ Debug mode enabled');
    } else {
      console.log('✅ Debug mode already enabled');
    }

    // Verify options visibility
    await page.goto(`${process.env.WORDPRESS_URL}/wp-admin/options.php`);

    const label = page.locator('label[for="wc_facebook_external_business_id"]');
    await label.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });

    const input = page.locator('#wc_facebook_external_business_id');
    const value = await input.inputValue();

    expect(value).toBeTruthy();
    expect(value).toBe(process.env.FB_EXTERNAL_BUSINESS_ID);

    console.log('✅ WooCommerce Debug log checks passed');
    console.log(`   - Option exists: wc_facebook_external_business_id`);
    console.log(`   - Value is non-null: YES`);
    console.log(`   - Matches expected: YES`);
  });


  test('Clear background sync jobs and verify cleanup', async ({ page }) => {
    console.log('🔍 Testing background sync job cleanup...');

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

  test('Check WooCommerce logs for fatal errors and non-200 responses', async () => {
    console.log('🔍 Checking WooCommerce logs for errors...');

    const today = new Date().toISOString().split('T')[0];
    const logsDir = process.env.WC_LOG_PATH;
    
    if (!logsDir) {
      throw new Error('❌ WC_LOG_PATH environment variable not set');
    }
    
    console.log(`📁 Looking for logs in: ${logsDir}`);

    // Find today's log file
    const logFile = execSync(
      `find "${logsDir}" -name "facebook_for_woocommerce-${today}*.log" 2>/dev/null | head -1`,
      { encoding: 'utf8' }
    ).trim();

    if (!logFile) {
      console.log(`⚠️ No log file found for today (${today}) - plugin may not have logged yet`);
      return;
    }

    console.log(`📄 Checking: ${logFile}`);
    const errors = [];

    // Check for fatal errors (case insensitive)
    const fatalCount = execSync(
      `grep -ic "fatal" "${logFile}" || echo 0`,
      { encoding: 'utf8' }
    ).trim();
    
    if (parseInt(fatalCount) > 0) {
      const fatalLines = execSync(`grep -i "fatal" "${logFile}"`, { encoding: 'utf8' });
      errors.push(`❌ Found ${fatalCount} fatal error(s):\n${fatalLines}`);
    }

    // Check for non-200 response codes with context
    const nonOkCodesCheck = execSync(
      `grep -n "^code: " "${logFile}" | grep -v "^code: 200" || true`,
      { encoding: 'utf8' }
    ).trim();
    
    if (nonOkCodesCheck) {
      console.log(`\n⚠️ Found non-200 response codes. Showing context:\n`);
      
      // Extract line numbers and show context (5 lines before and after each)
      const lines = nonOkCodesCheck.split('\n');
      for (const line of lines) {
        const lineNum = line.split(':')[0];
        if (lineNum) {
          console.log(`\n========== Around line ${lineNum} ==========`);
          const context = execSync(
            `sed -n '${Math.max(1, parseInt(lineNum) - 5)},${parseInt(lineNum) + 5}p' "${logFile}"`,
            { encoding: 'utf8' }
          );
          console.log(context);
        }
      }
      
      errors.push(`❌ Found non-200 response codes (see context above)`);
    }

    if (errors.length > 0) {
      console.log('\n' + errors.join('\n\n'));
      throw new Error('Log validation failed');
    }

    console.log('✅ Log validation PASSED');
    console.log('   - No fatal errors');
    console.log('   - All response codes are 200 OK');
  });

});
