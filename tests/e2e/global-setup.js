const { chromium } = require('@playwright/test');
const { TIMEOUTS } = require('./time-constants');

const baseURL = process.env.WORDPRESS_URL;

// Admin credentials
const adminUsername = process.env.WP_USERNAME;
const adminPassword = process.env.WP_PASSWORD;

// Customer credentials
const customerUsername = process.env.WP_CUSTOMER_USERNAME;
const customerPassword = process.env.WP_CUSTOMER_PASSWORD;

async function globalSetup() {
  console.log('🔐 Global Setup: Authenticating users...');

  const browser = await chromium.launch();

  try {
    // =================================================================
    // STEP 1: Login as ADMIN and save state
    // =================================================================
    console.log('\n📋 Step 1: Logging in as ADMIN...');
    const adminContext = await browser.newContext({ ignoreHTTPSErrors: true });
    const adminPage = await adminContext.newPage();

    await adminPage.goto(`${baseURL}/wp-admin/`, {
      waitUntil: 'domcontentloaded',
      timeout: TIMEOUTS.MAX
    });

    // Check if already on admin page
    const loggedInContent = adminPage.locator('#wpcontent');
    const isLoggedIn = await loggedInContent.isVisible({ timeout: 5000 }).catch(() => false);

    if (!isLoggedIn) {
      // Fill admin login form
      const loginForm = adminPage.locator('#user_login');
      await loginForm.waitFor({ state: 'visible', timeout: TIMEOUTS.MAX });
      await loginForm.fill(adminUsername);

      const passwordField = adminPage.locator('#user_pass');
      await passwordField.fill(adminPassword);

      await adminPage.waitForTimeout(TIMEOUTS.SHORT);

      const loginButton = adminPage.locator('#wp-submit');
      await loginButton.click();

      await loggedInContent.waitFor({ state: 'visible', timeout: TIMEOUTS.MAX });
    }

    console.log('✅ Admin logged in successfully');

    // Save admin authentication state
    await adminContext.storageState({ path: './tests/e2e/.auth/admin.json' });
    console.log('✅ Admin state saved to admin.json');

    // Close admin context (logout/forget session)
    await adminContext.close();
    console.log('✅ Admin session closed');

    // =================================================================
    // STEP 2: Login as CUSTOMER and save state
    // =================================================================
    console.log('\n📋 Step 2: Logging in as CUSTOMER...');
    const customerContext = await browser.newContext({ ignoreHTTPSErrors: true });
    const customerPage = await customerContext.newPage();

    await customerPage.goto(`${baseURL}/wp-login.php`, {
      waitUntil: 'domcontentloaded',
      timeout: TIMEOUTS.MAX
    });

    // Fill customer login form
    const customerLoginForm = customerPage.locator('#user_login');
    await customerLoginForm.waitFor({ state: 'visible', timeout: TIMEOUTS.MAX });
    await customerLoginForm.fill(customerUsername);

    const customerPasswordField = customerPage.locator('#user_pass');
    await customerPasswordField.fill(customerPassword);

    await customerPage.waitForTimeout(TIMEOUTS.SHORT);

    const customerLoginButton = customerPage.locator('#wp-submit');
    await customerLoginButton.click();

    // Wait for navigation - customers are redirected to front page
    await customerPage.waitForLoadState('networkidle', { timeout: TIMEOUTS.MAX });

    // Verify customer is logged in
    const isCustomerLoggedIn = await customerPage.locator('#wpadminbar').count() > 0 ||
                      await customerPage.locator('body.logged-in').count() > 0;

    if (!isCustomerLoggedIn) {
      throw new Error('Customer login verification failed');
    }

    console.log('✅ Customer logged in successfully');

    // Save customer authentication state
    await customerContext.storageState({ path: './tests/e2e/.auth/customer.json' });
    console.log('✅ Customer state saved to customer.json');

    // Close customer context (logout/forget session)
    await customerContext.close();
    console.log('✅ Customer session closed');

    console.log('\n🎉 Global Setup Complete! Both auth states saved.');

  } catch (error) {
    console.error('❌ Global Setup failed:', error.message);
    throw error;
  } finally {
    await browser.close();
  }
}

module.exports = globalSetup;
