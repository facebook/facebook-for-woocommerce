const { chromium } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const { TIMEOUTS } = require('./helpers/js');
const { execWP } = require('./helpers/js/wordpress/exec');

const baseURL = process.env.WORDPRESS_URL;
const adminUsername = process.env.WP_USERNAME;
const adminPassword = process.env.WP_PASSWORD;
const customerUsername = process.env.WP_CUSTOMER_USERNAME;
const customerPassword = process.env.WP_CUSTOMER_PASSWORD;

async function loginAndSaveAuth(browser, {
  userType,
  username,
  password,
  loginUrl,
  authPath,
  postLoginCheck,
}) {
  if (!baseURL || !username || !password) {
    throw new Error(`Missing required env vars for ${userType} auth`);
  }

  console.log(`\n📋 Logging in as ${userType}...`);

  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await context.newPage();

  await page.goto(loginUrl, {
    waitUntil: 'domcontentloaded',
    timeout: TIMEOUTS.MAX,
  });

  const loginForm = page.locator('#user_login');
  const isLoginVisible = await loginForm.isVisible({ timeout: TIMEOUTS.SHORT }).catch(() => false);

  if (isLoginVisible) {
    await loginForm.fill(username);
    await page.locator('#user_pass').fill(password);
    await page.locator('#wp-submit').click();
    await page.waitForLoadState('networkidle', { timeout: TIMEOUTS.MAX });
  }

  await postLoginCheck(page);
  await context.storageState({ path: authPath });
  console.log(`✅ ${userType} state saved to ${authPath}`);

  await context.close();
}

async function globalSetup() {
  const adminAuthPath = './tests/e2e/.auth/admin.json';
  const customerAuthPath = './tests/e2e/.auth/customer.json';
  const authDir = path.dirname(adminAuthPath);

  // Ensure auth directory exists
  fs.mkdirSync(authDir, { recursive: true });

  // Bump auto-increment to random high numbers so each test run creates
  // products and categories with unique IDs that won't collide with stale Facebook catalog data.
  const startId = Math.floor(Math.random() * 900000) + 100000;
  const termStartId = Math.floor(Math.random() * 900000) + 100000;
  await execWP(`global \\$wpdb; \\$wpdb->query('ALTER TABLE ' . \\$wpdb->posts . ' AUTO_INCREMENT = ${startId}');`);
  console.log(`🔢 Set wp_posts AUTO_INCREMENT to ${startId}`);
  await execWP(`global \\$wpdb; \\$wpdb->query('ALTER TABLE ' . \\$wpdb->terms . ' AUTO_INCREMENT = ${termStartId}');`);
  await execWP(`global \\$wpdb; \\$wpdb->query('ALTER TABLE ' . \\$wpdb->term_taxonomy . ' AUTO_INCREMENT = ${termStartId}');`);
  console.log(`🔢 Set wp_terms/wp_term_taxonomy AUTO_INCREMENT to ${termStartId}`);

  const adminExists = fs.existsSync(adminAuthPath);
  const customerExists = fs.existsSync(customerAuthPath);

  if (adminExists && customerExists) {
    console.log('✅ Auth files already exist - skipping login setup');
    console.log(`   Admin: ${path.resolve(adminAuthPath)}`);
    console.log(`   Customer: ${path.resolve(customerAuthPath)}`);
    return;
  }

  const browser = await chromium.launch();

  try {
    console.log('🔐 Global Setup: Authenticating users...');

    if (!adminExists) {
      await loginAndSaveAuth(browser, {
        userType: 'ADMIN',
        username: adminUsername,
        password: adminPassword,
        loginUrl: `${baseURL}/wp-admin/`,
        authPath: adminAuthPath,
        postLoginCheck: async (page) => {
          await page.locator('#wpcontent').waitFor({ state: 'visible', timeout: TIMEOUTS.MAX });
        },
      });
    }

    if (!customerExists) {
      await loginAndSaveAuth(browser, {
        userType: 'CUSTOMER',
        username: customerUsername,
        password: customerPassword,
        loginUrl: `${baseURL}/wp-login.php`,
        authPath: customerAuthPath,
        postLoginCheck: async (page) => {
          const isCustomerLoggedIn = await page.locator('#wpadminbar').count() > 0 || await page.locator('body.logged-in').count() > 0;
          if (!isCustomerLoggedIn) {
            throw new Error('Customer login failed: missing logged-in indicators');
          }
        },
      });
    }

    console.log('🎉 Global Setup Complete! Admin and customer auth states saved.');
  } catch (error) {
    console.error('❌ Global Setup failed:', error.message);
    throw error;
  } finally {
    await browser.close();
  }
}

module.exports = globalSetup;
