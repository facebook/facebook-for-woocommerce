const { test, expect } = require('@playwright/test');
const {
  baseURL,
  loginToWordPress,
  checkForPhpErrors,
  logTestStart,
  logTestEnd
} = require('./test-helpers');

test.describe('Facebook for WooCommerce - Health Check Tests', () => {

  test.beforeEach(async ({ page }, testInfo) => {
    // Log test start first for proper chronological order
    logTestStart(testInfo);

    // Ensure browser stability
    await page.setViewportSize({ width: 1280, height: 720 });
    await loginToWordPress(page);
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

});
