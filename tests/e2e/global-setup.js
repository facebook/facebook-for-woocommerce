const { chromium } = require('@playwright/test');
const { TIMEOUTS } = require('./helpers/js');
const { execWP } = require('./helpers/js/wordpress/exec');

const baseURL = process.env.WORDPRESS_URL;

// Admin credentials
const adminUsername = process.env.WP_USERNAME;
const adminPassword = process.env.WP_PASSWORD;

// Customer credentials
const customerUsername = process.env.WP_CUSTOMER_USERNAME;
const customerPassword = process.env.WP_CUSTOMER_PASSWORD;

/**
 * Login to WordPress and save authentication state
 * @param {Browser} browser - Playwright browser instance
 * @param {Object} config - Login configuration
 * @param {string} config.username - Username
 * @param {string} config.password - Password
 * @param {string} config.authPath - Path to save auth state JSON
 * @param {string} config.userType - User type for logging (e.g., 'ADMIN', 'CUSTOMER')
 * @param {string} config.loginUrl - URL to navigate for login
 * @param {Function} [config.preLoginCheck] - Optional function to check if already logged in
 * @param {Function} config.postLoginVerify - Function to verify login succeeded
 */
async function loginAndSaveAuth(browser, config) {
  const { username, password, authPath, userType, loginUrl, preLoginCheck, postLoginVerify } = config;

  console.log(`\n📋 Logging in as ${userType}...`);

  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await context.newPage();

  await page.goto(loginUrl, {
    waitUntil: 'domcontentloaded',
    timeout: TIMEOUTS.MAX
  });

  // Check if already logged in (optional pre-check)
  if (preLoginCheck) {
    const isAlreadyLoggedIn = await preLoginCheck(page);
    if (isAlreadyLoggedIn) {
      console.log(`✅ ${userType} already logged in`);
      await context.storageState({ path: authPath });
      await context.close();
      return;
    }
  }

  // Fill login form
  const loginForm = page.locator('#user_login');
  await loginForm.waitFor({ state: 'visible', timeout: TIMEOUTS.MAX });
  await loginForm.fill(username);

  const passwordField = page.locator('#user_pass');
  await passwordField.fill(password);

  await page.waitForTimeout(TIMEOUTS.SHORT);

  const loginButton = page.locator('#wp-submit');
  await loginButton.click();

  // Verify login succeeded
  await postLoginVerify(page);

  console.log(`✅ ${userType} logged in successfully`);

  // Save authentication state
  await context.storageState({ path: authPath });
  console.log(`✅ ${userType} state saved to ${authPath}`);

  // Close context
  await context.close();
  console.log(`✅ ${userType} session closed`);
}

async function globalSetup() {
  const fs = require('fs');
  const path = require('path');

  const adminAuthPath = './tests/e2e/.auth/admin.json';
  const customerAuthPath = './tests/e2e/.auth/customer.json';

  // Bump auto-increment to random high numbers so each test run creates
  // products and categories with unique IDs that won't collide with stale Facebook catalog data.
  const startId = Math.floor(Math.random() * 900000) + 100000;
  const termStartId = Math.floor(Math.random() * 900000) + 100000;
  await execWP(`global \\$wpdb; \\$wpdb->query('ALTER TABLE ' . \\$wpdb->posts . ' AUTO_INCREMENT = ${startId}');`);
  console.log(`🔢 Set wp_posts AUTO_INCREMENT to ${startId}`);
  await execWP(`global \\$wpdb; \\$wpdb->query('ALTER TABLE ' . \\$wpdb->terms . ' AUTO_INCREMENT = ${termStartId}');`);
  await execWP(`global \\$wpdb; \\$wpdb->query('ALTER TABLE ' . \\$wpdb->term_taxonomy . ' AUTO_INCREMENT = ${termStartId}');`);
  console.log(`🔢 Set wp_terms/wp_term_taxonomy AUTO_INCREMENT to ${termStartId}`);

  // Check if both auth files already exist
  const adminExists = fs.existsSync(adminAuthPath);
  const customerExists = fs.existsSync(customerAuthPath);

  if (adminExists && customerExists) {
    console.log('✅ Auth files already exist - skipping global setup');
    console.log(`   Admin: ${path.resolve(adminAuthPath)}`);
    console.log(`   Customer: ${path.resolve(customerAuthPath)}`);
    return;
  }

  const browser = await chromium.launch();

  try {
    console.log('🔐 Global Setup: Authenticating users...');
    if (!adminExists) {
      console.log('⚠️ Admin auth missing - will create');

      // Login as ADMIN and save state
      await loginAndSaveAuth(browser, {
        username: adminUsername,
        password: adminPassword,
        authPath: adminAuthPath,
        userType: 'ADMIN',
        loginUrl: `${baseURL}/wp-admin/`,
        preLoginCheck: async (page) => {
          const loggedInContent = page.locator('#wpcontent');
          return await loggedInContent.isVisible({ timeout: 5000 }).catch(() => false);
        },
        postLoginVerify: async (page) => {
          await page.waitForLoadState('networkidle', { timeout: TIMEOUTS.MAX });
          const loggedInContent = page.locator('#wpcontent');
          await loggedInContent.waitFor({ state: 'visible', timeout: TIMEOUTS.MAX });
        }
      });
    }

    if (!customerExists) {
      console.log('⚠️ Customer auth missing - will create');

      // Login as CUSTOMER and save state
      await loginAndSaveAuth(browser, {
        username: customerUsername,
        password: customerPassword,
        authPath: customerAuthPath,
        userType: 'CUSTOMER',
        loginUrl: `${baseURL}/wp-login.php`,
        postLoginVerify: async (page) => {
          // Wait for navigation - customers are redirected to front page
          await page.waitForLoadState('networkidle', { timeout: TIMEOUTS.MAX });
          // Verify customer is logged in
          const isCustomerLoggedIn = await page.locator('#wpadminbar').count() > 0 || await page.locator('body.logged-in').count() > 0;
          if (!isCustomerLoggedIn) {
            throw new Error('Customer login failed due to #wpadminbar or body.logged-in not found');
          }
        }
      });
    }

    console.log('\n🎉 Global Setup Complete! Both auth states saved.');

  } catch (error) {
    console.error('❌ Global Setup failed:', error.message);
    throw error;
  } finally {
    await browser.close();
  }
}

module.exports = globalSetup;
