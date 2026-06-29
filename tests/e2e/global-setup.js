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

  // Logging in against a freshly-booted WordPress on a busy CI runner can be slow,
  // so make this resilient instead of racing a short visibility check. Retry the
  // whole flow, and within each attempt explicitly wait for the login form (rather
  // than skipping login if it isn't visible within a second, which previously left
  // us waiting 60s for an admin page that never loads).
  const MAX_ATTEMPTS = 3;

  for (let attempt = 1; attempt <= MAX_ATTEMPTS; attempt++) {
    try {
      await page.goto(loginUrl, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUTS.MAX,
      });

      // If a prior attempt already authenticated us, the login form won't render.
      const loginForm = page.locator('#user_login');
      const needsLogin = await loginForm
        .isVisible({ timeout: TIMEOUTS.NORMAL })
        .catch(() => false);

      if (needsLogin) {
        await loginForm.waitFor({ state: 'visible', timeout: TIMEOUTS.MAX });
        await loginForm.fill(username);
        await page.locator('#user_pass').fill(password);
        await page.locator('#wp-submit').click();
      }

      // The real signal that login succeeded is the post-login check (e.g. the
      // admin #wpcontent wrapper). Rely on that rather than 'networkidle', which
      // can hang on pages that keep polling in the background.
      await postLoginCheck(page);
      break;
    } catch (error) {
      console.warn(`⚠️ ${userType} login attempt ${attempt}/${MAX_ATTEMPTS} failed: ${error.message}`);
      if (attempt === MAX_ATTEMPTS) {
        throw error;
      }
      await page.waitForTimeout(TIMEOUTS.NORMAL);
    }
  }

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
