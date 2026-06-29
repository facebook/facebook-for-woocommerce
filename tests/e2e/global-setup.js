const { chromium } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const { TIMEOUTS } = require('./helpers/js');
const { execWP } = require('./helpers/js/wordpress/exec');

const baseURL = process.env.WORDPRESS_URL;
const adminUsername = process.env.WP_USERNAME;
const adminPassword = process.env.WP_PASSWORD;
const customerUsername = process.env.WP_CUSTOMER_USERNAME;

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

/**
 * Establish a logged-in session without a UI login by generating a real
 * WordPress auth cookie server-side via WP-CLI and injecting it into the browser
 * context. This avoids the flaky storefront form login (form-render races and
 * post-submit navigation timing) entirely.
 */
async function saveAuthViaWpCli(browser, { userType, username, authPath }) {
  console.log(`\n📋 Seeding ${userType} session via WP-CLI (no UI login)...`);

  // Generate a 'logged_in' auth cookie for the user. This is the cookie the
  // WordPress front-end uses to recognize a logged-in user, which is all the
  // storefront/customer test projects need.
  const php = `
    $user = get_user_by('login', ${JSON.stringify(username)});
    if (!$user) { echo 'NO_USER:' . ${JSON.stringify(username)}; return; }
    $expiration = time() + 2 * DAY_IN_SECONDS;
    $token = WP_Session_Tokens::get_instance($user->ID)->create($expiration);
    echo json_encode([
      'name' => 'wordpress_logged_in_' . COOKIEHASH,
      'value' => wp_generate_auth_cookie($user->ID, $expiration, 'logged_in', $token),
      'expiration' => $expiration,
    ]);
  `;

  const { stdout } = await execWP(php);
  const start = stdout.indexOf('{');
  const end = stdout.lastIndexOf('}');
  if (start === -1 || end === -1) {
    throw new Error(`Failed to generate ${userType} auth cookie via WP-CLI. Output: ${stdout.trim()}`);
  }
  const { name, value, expiration } = JSON.parse(stdout.slice(start, end + 1));

  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  await context.addCookies([{
    name,
    value,
    domain: new URL(baseURL).hostname,
    path: '/',
    httpOnly: true,
    secure: false,
    expires: expiration,
  }]);

  // Verify the cookie is accepted before saving the state.
  const page = await context.newPage();
  await page.goto(`${baseURL}/`, { waitUntil: 'domcontentloaded', timeout: TIMEOUTS.MAX });
  await page.locator('body.logged-in').waitFor({ state: 'attached', timeout: TIMEOUTS.MAX });

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
      await saveAuthViaWpCli(browser, {
        userType: 'CUSTOMER',
        username: customerUsername,
        authPath: customerAuthPath,
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
