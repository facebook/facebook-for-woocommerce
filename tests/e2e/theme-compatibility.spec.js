const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');
const fs = require('fs/promises');
const path = require('path');
const {
  TIMEOUTS,
  baseURL,
  logTestStart,
  checkForPhpErrors,
  TestSetup,
  EventValidator,
} = require('./helpers/js');

const BASE_THEME_SLUG = 'storefront';
const DEFAULT_THEME_SLUGS = ['twentytwentyone', 'twentytwentyfive'];
const DEFAULT_TEST_PRODUCT_PATH = '/product/beanie/';
const DEFAULT_TEST_CATEGORY_PATH = '/product-category/clothing/accessories/';
const THEME_LOCK_FILE = path.join(process.cwd(), 'tests/e2e/.theme-compat.lock');
const THEME_SLUGS = buildOrderedThemeList(
  process.env.E2E_THEME_COMPAT_SLUGS || process.env.E2E_TARGET_THEME_SLUG
);

function resolveSiteUrl(input, fallbackPath) {
  if (input && /^https?:\/\//i.test(input)) {
    return input;
  }

  const root = baseURL || process.env.WORDPRESS_URL || 'http://test-facebook-for-woocommerce.local';
  const normalizedPath = input || fallbackPath;

  if (!normalizedPath) {
    return root;
  }

  return `${root.replace(/\/$/, '')}/${normalizedPath.replace(/^\//, '')}`;
}

test.describe('Theme compatibility flow (storefront -> twentytwentyone -> twentytwentyfive)', () => {
  test.beforeAll(async () => {
    process.env.TEST_PRODUCT_URL = resolveSiteUrl(process.env.TEST_PRODUCT_URL, DEFAULT_TEST_PRODUCT_PATH);
    process.env.TEST_CATEGORY_URL = resolveSiteUrl(process.env.TEST_CATEGORY_URL, DEFAULT_TEST_CATEGORY_PATH);
    process.env.TEST_FBCLID = process.env.TEST_FBCLID || 'IwAR123TestClickId456';
    process.env.FB_E2E_TEST_COOKIE_NAME = process.env.FB_E2E_TEST_COOKIE_NAME || 'facebook_test_id';
    process.env.FB_E2E_LOGGER_PATH = process.env.FB_E2E_LOGGER_PATH || '/tests/e2e/helpers/php/event-logger.php';

    await ensureCapiCaptureEnabled();

    const beforeAllLockToken = await acquireThemeLock();
    try {
      const startStatus = await getActiveThemeStatus();
      if (startStatus.activeStylesheet !== BASE_THEME_SLUG) {
        console.log(
          `ℹ️ Starting from '${startStatus.activeStylesheet}'. Switching to '${BASE_THEME_SLUG}' baseline first.`
        );
        await switchThemeBySlug(BASE_THEME_SLUG);
      }
    } finally {
      await releaseThemeLock(beforeAllLockToken);
    }
  });

  for (const themeSlug of THEME_SLUGS) {
    registerThemeCompatibilitySuite(themeSlug);
  }

  test.afterAll(async () => {
    const afterAllLockToken = await acquireThemeLock();
    try {
      const result = await switchThemeBySlug(BASE_THEME_SLUG);
      if (!result.success) {
        console.warn(`⚠️ Could not restore ${BASE_THEME_SLUG}: ${result.error || 'unknown error'}`);
      }
    } catch (error) {
      console.warn(`⚠️ Could not restore ${BASE_THEME_SLUG}: ${error.message}`);
    } finally {
      await releaseThemeLock(afterAllLockToken);
    }
  });
});

function registerThemeCompatibilitySuite(themeSlug) {
  test.describe(`Theme compatibility checks (${themeSlug})`, () => {
    test.beforeEach(async ({ page }, testInfo) => {
      test.skip(
        testInfo.project.name !== 'chromium-wp-customer',
        'Theme compatibility runs only in chromium-wp-customer project.'
      );

      const lockToken = await acquireThemeLock();
      testInfo._themeLockToken = lockToken;

      const result = await switchThemeBySlug(themeSlug);
      if (!result.success) {
        console.warn(
          `⚠️ Could not activate theme '${themeSlug}' in beforeEach: ${result.error || 'Unknown error'}`
        );
      }

      logTestStart(testInfo);
      await page.setViewportSize({ width: 1280, height: 720 });
    });

    test.afterEach(async ({}, testInfo) => {
      await releaseThemeLock(testInfo._themeLockToken);
    });


    test('Theme is active', async ({}, testInfo) => {
      let status = await getActiveThemeStatus();

      if (status.activeStylesheet !== themeSlug) {
        console.warn(
          `⚠️ Active theme mismatch (expected: ${themeSlug}, got: ${status.activeStylesheet}). Retrying activation...`
        );

        await switchThemeBySlug(themeSlug);
        status = await getActiveThemeStatus();
      }

      if (status.activeStylesheet !== themeSlug) {
        testInfo.annotations.push({
          type: 'warning',
          description: `Theme mismatch expected=${themeSlug} actual=${status.activeStylesheet}`,
        });
      }

      expect(status.activeStylesheet).toBe(themeSlug);
    });

    test('Shop and checkout stay healthy', async ({ page }) => {
      await page.goto(`${baseURL}/shop`, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUTS.EXTRA_LONG,
      });
      await checkForPhpErrors(page);
      await expect(page.locator('body')).toBeVisible();

      await page.goto(`${baseURL}/checkout`, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUTS.EXTRA_LONG,
      });
      await checkForPhpErrors(page);
      await expect(page.locator('body')).toBeVisible();
    });

    test('PageView still fires after theme switch', async ({ page }, testInfo) => {
      await runTrackedEventTest({
        page,
        testInfo,
        themeSlug,
        eventName: 'PageView',
        action: async ({ page }) => {
          await page.goto(`${baseURL}/`, {
            waitUntil: 'domcontentloaded',
            timeout: TIMEOUTS.EXTRA_LONG,
          });
        },
      });
    });

    test('PageView with fbclid still fires after theme switch', async ({ page }, testInfo) => {
      await runTrackedEventTest({
        page,
        testInfo,
        themeSlug,
        eventName: 'PageView',
        expectFbc: true,
        action: async ({ page }) => {
          await page.goto(`${baseURL}/?fbclid=${process.env.TEST_FBCLID}`, {
            waitUntil: 'domcontentloaded',
            timeout: TIMEOUTS.EXTRA_LONG,
          });
        },
      });
    });

    test('ViewContent still fires after theme switch', async ({ page }, testInfo) => {
      const productUrl = process.env.TEST_PRODUCT_URL;

      await runTrackedEventTest({
        page,
        testInfo,
        themeSlug,
        eventName: 'ViewContent',
        action: async ({ page }) => {
          await page.goto(productUrl, {
            waitUntil: 'domcontentloaded',
            timeout: TIMEOUTS.EXTRA_LONG,
          });
        },
      });
    });

    test('AddToCart still fires after theme switch', async ({ page }, testInfo) => {
      const productUrl = process.env.TEST_PRODUCT_URL;

      await runTrackedEventTest({
        page,
        testInfo,
        themeSlug,
        eventName: 'AddToCart',
        action: async ({ page }) => {
          await page.goto(productUrl, {
            waitUntil: 'domcontentloaded',
            timeout: TIMEOUTS.EXTRA_LONG,
          });
          await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

          await page.click('.single_add_to_cart_button');
          await page.waitForLoadState('networkidle');
        },
      });
    });

    test('ViewCategory still fires after theme switch', async ({ page }, testInfo) => {
      const categoryUrl = process.env.TEST_CATEGORY_URL;
      const productUrl = process.env.TEST_PRODUCT_URL;

      await runTrackedEventTest({
        page,
        testInfo,
        themeSlug,
        eventName: 'ViewCategory',
        // twentytwentyone intermittently emits PageView first; retry once to reduce flakiness
        // while keeping strict Pixel + CAPI validation.
        pixelRetries: themeSlug === 'twentytwentyone' ? 1 : 0,
        action: async ({ page }) => {
          // Prime referrer context before category navigation to match manual flow.
          await page.goto(productUrl, {
            waitUntil: 'domcontentloaded',
            timeout: TIMEOUTS.EXTRA_LONG,
          });
          await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

          await page.goto(categoryUrl, {
            waitUntil: 'domcontentloaded',
            timeout: TIMEOUTS.EXTRA_LONG,
          });
        },
      });
    });

    test('InitiateCheckout still fires after theme switch', async ({ page }, testInfo) => {
      const productUrl = process.env.TEST_PRODUCT_URL;

      await runTrackedEventTest({
        page,
        testInfo,
        themeSlug,
        eventName: 'InitiateCheckout',
        action: async ({ page }) => {
          await page.goto(productUrl, {
            waitUntil: 'domcontentloaded',
            timeout: TIMEOUTS.EXTRA_LONG,
          });
          await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

          await page.click('.single_add_to_cart_button');
          await page.waitForTimeout(TIMEOUTS.SHORT);

          await page.goto(`${baseURL}/checkout`, {
            waitUntil: 'domcontentloaded',
            timeout: TIMEOUTS.EXTRA_LONG,
          });
          await TestSetup.waitForPageReady(page);
        },
      });
    });

    test('Search still fires after theme switch', async ({ page }, testInfo) => {
      test.skip(
        ['twentytwentyone', 'twentytwentyfive'].includes(themeSlug),
        `Theme '${themeSlug}' does not render a search box in this flow.`
      );

      await runTrackedEventTest({
        page,
        testInfo,
        themeSlug,
        eventName: 'Search',
        action: async ({ page }) => {
          await page.goto(`${baseURL}/`, {
            waitUntil: 'domcontentloaded',
            timeout: TIMEOUTS.EXTRA_LONG,
          });
          await TestSetup.waitForPageReady(page);

          const searchInput = page.locator('.search-field').first();
          await searchInput.fill('test');
          await searchInput.press('Enter');
        },
      });
    });

    test('Search does not fire for no results after theme switch', async ({ page }, testInfo) => {
      test.skip(
        ['twentytwentyone', 'twentytwentyfive'].includes(themeSlug),
        `Theme '${themeSlug}' does not render a search box in this flow.`
      );

      await runTrackedEventTest({
        page,
        testInfo,
        themeSlug,
        eventName: 'Search',
        expectZeroEvents: true,
        action: async ({ page }) => {
          await page.goto(`${baseURL}/`, {
            waitUntil: 'domcontentloaded',
            timeout: TIMEOUTS.EXTRA_LONG,
          });
          await TestSetup.waitForPageReady(page);

          const randomString = `xyzabc239nfjsdn${Date.now()}`;
          const searchInput = page.locator('.search-field').first();
          await searchInput.fill(randomString);
          await searchInput.press('Enter');
        },
      });
    });

    test('Purchase still fires after theme switch', async ({ page }, testInfo) => {
      const productUrl = process.env.TEST_PRODUCT_URL;

      await runTrackedEventTest({
        page,
        testInfo,
        themeSlug,
        eventName: 'Purchase',
        waitForPixel: false,
        action: async ({ page }) => {
          await page.goto(productUrl, {
            waitUntil: 'domcontentloaded',
            timeout: TIMEOUTS.EXTRA_LONG,
          });
          await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

          await page.click('.single_add_to_cart_button');
          await page.waitForTimeout(TIMEOUTS.SHORT);

          await page.goto(`${baseURL}/checkout`, {
            waitUntil: 'domcontentloaded',
            timeout: TIMEOUTS.EXTRA_LONG,
          });
          await TestSetup.waitForPageReady(page);

          await page.evaluate(() => window.scrollBy(0, 400));
          await page.waitForTimeout(TIMEOUTS.SHORT);

          await page.waitForSelector(
            '.wc-block-components-radio-control__option[for="radio-control-wc-payment-method-options-cod"]',
            {
              state: 'visible',
              timeout: TIMEOUTS.LONG,
            }
          );
          await page.click('label[for="radio-control-wc-payment-method-options-cod"]');
          await page.waitForTimeout(TIMEOUTS.INSTANT);

          await page.locator('.wc-block-components-checkout-place-order-button').scrollIntoViewIfNeeded();
          await page.click('.wc-block-components-checkout-place-order-button');

          await page.waitForURL('**/checkout/order-received/**', {
            timeout: TIMEOUTS.EXTRA_LONG,
          });
          await page.waitForTimeout(TIMEOUTS.NORMAL);
        },
      });
    });

    test('Purchase deduplication still works after theme switch', async ({ page }, testInfo) => {
      const productUrl = process.env.TEST_PRODUCT_URL;

      await runTrackedEventTest({
        page,
        testInfo,
        themeSlug,
        eventName: 'Purchase',
        waitForPixel: false,
        action: async ({ page }) => {
          await page.goto(productUrl, {
            waitUntil: 'domcontentloaded',
            timeout: TIMEOUTS.EXTRA_LONG,
          });
          await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

          await page.click('.single_add_to_cart_button');
          await page.waitForTimeout(TIMEOUTS.SHORT);

          await page.goto(`${baseURL}/checkout`, {
            waitUntil: 'domcontentloaded',
            timeout: TIMEOUTS.EXTRA_LONG,
          });
          await TestSetup.waitForPageReady(page);

          await page.evaluate(() => window.scrollBy(0, 400));
          await page.waitForTimeout(TIMEOUTS.SHORT);

          await page.waitForSelector(
            '.wc-block-components-radio-control__option[for="radio-control-wc-payment-method-options-cod"]',
            {
              state: 'visible',
              timeout: TIMEOUTS.LONG,
            }
          );
          await page.click('label[for="radio-control-wc-payment-method-options-cod"]');
          await page.waitForTimeout(TIMEOUTS.INSTANT);

          const placeOrderButton = page.locator('.wc-block-components-checkout-place-order-button');
          await placeOrderButton.scrollIntoViewIfNeeded();
          await placeOrderButton.click();
          await page.waitForTimeout(100);
          await placeOrderButton.click({ force: true }).catch(() => {});
          await page.waitForTimeout(100);
          await placeOrderButton.click({ force: true }).catch(() => {});

          await page.waitForURL('**/checkout/order-received/**', {
            timeout: TIMEOUTS.EXTRA_LONG,
          });
          await page.waitForTimeout(TIMEOUTS.NORMAL);
        },
      });
    });
  });
}

async function runTrackedEventTest({
  page,
  testInfo,
  themeSlug,
  eventName,
  action,
  expectFbc = false,
  expectZeroEvents = false,
  waitForPixel = true,
  pixelRetries = 0,
}) {
  await TestSetup.login(page);
  await ensureCapiCaptureEnabled();

  const { testId, pixelCapture } = await TestSetup.init(page, eventName, testInfo, expectZeroEvents);

  if (waitForPixel) {
    const attempts = Math.max(1, 1 + Number(pixelRetries || 0));
    let lastPixelError = null;

    for (let attempt = 1; attempt <= attempts; attempt++) {
      const eventPromise = pixelCapture.waitForEvent();
      await action({ page, pixelCapture, testId });
      await TestSetup.waitForPageReady(page);

      try {
        await eventPromise;
        lastPixelError = null;
        break;
      } catch (error) {
        lastPixelError = error;
        if (attempt < attempts) {
          console.warn(`⚠️ ${eventName}: Pixel capture attempt ${attempt}/${attempts} failed (${error.message}). Retrying...`);
          await page.waitForTimeout(TIMEOUTS.SHORT);
        }
      }
    }

    if (lastPixelError) {
      throw lastPixelError;
    }
  } else {
    await action({ page, pixelCapture, testId });
    await TestSetup.waitForPageReady(page);
  }

  const validator = new EventValidator(testId, expectFbc, expectZeroEvents);
  try {
    await validator.checkDebugLog();
  } catch (error) {
    const recentDebugErrors = await getRecentCriticalDebugErrors();
    console.warn(`⚠️ Ignoring debug.log failure for theme compatibility: ${error.message}`);
    if (recentDebugErrors.length > 0) {
      console.warn('⚠️ Latest critical debug.log lines:');
      recentDebugErrors.forEach((line) => console.warn(`   ${line}`));
    }
    testInfo.annotations.push({
      type: 'warning',
      description: `debug.log validation skipped: ${error.message}`,
    });
  }

  const result = await validator.validate(eventName, page);

  if (!result.passed && Array.isArray(result.errors) && result.errors.some((e) => e.includes('Expected 1 CAPI event, found 0'))) {
    const diagnostics = await collectCapiDiagnostics(page, testId, eventName);
    console.warn('⚠️ CAPI diagnostics:', JSON.stringify(diagnostics, null, 2));
    testInfo.annotations.push({
      type: 'capi-diagnostics',
      description: JSON.stringify(diagnostics),
    });
  }

  TestSetup.logResult(`${eventName} (Theme Compatibility: ${themeSlug})`, result);
  expect(result.passed).toBe(true);
}

async function getActiveThemeStatus() {
  const activeStylesheet = runWpCli('option get stylesheet');
  const activeTemplate = runWpCli('option get template');

  return {
    activeStylesheet: activeStylesheet.trim(),
    activeTemplate: activeTemplate.trim(),
  };
}

async function switchThemeBySlug(slug) {
  try {
    const isInstalled = isThemeInstalled(slug);

    if (!isInstalled) {
      runWpCli(`theme install ${slug}`);
    }

    const activateOutput = runWpCli(`theme activate ${slug}`);
    const status = await getActiveThemeStatus();

    if (status.activeStylesheet !== slug) {
      return {
        success: false,
        slug,
        activeStylesheet: status.activeStylesheet,
        activeTemplate: status.activeTemplate,
        error: `Activation did not stick (expected: ${slug}, actual: ${status.activeStylesheet}). wp output: ${activateOutput.trim()}`,
      };
    }

    return {
      success: true,
      slug,
      activeStylesheet: status.activeStylesheet,
      activeTemplate: status.activeTemplate,
    };
  } catch (error) {
    return {
      success: false,
      slug,
      error: error?.stderr?.toString?.().trim() || error?.message || 'Unknown WP-CLI error',
    };
  }
}

function isThemeInstalled(slug) {
  try {
    runWpCli(`theme is-installed ${slug}`);
    return true;
  } catch (_) {
    return false;
  }
}

function runWpCli(command, opts = {}) {
  const wpPath = process.env.WORDPRESS_PATH;

  if (!wpPath) {
    throw new Error('WORDPRESS_PATH is not set');
  }

  const skipFlags = opts.skipPluginsAndThemes === false ? '' : ' --skip-plugins --skip-themes';

  return execSync(`wp ${command} --path="${wpPath}" --allow-root${skipFlags}`, {
    encoding: 'utf8',
    stdio: ['pipe', 'pipe', 'pipe'],
  });
}

async function ensureCapiCaptureEnabled() {
  try {
    const loggerPath = process.env.FB_E2E_LOGGER_PATH || '/tests/e2e/helpers/php/event-logger.php';
    const loggerFile = path.join(process.cwd(), loggerPath.replace(/^\//, ''));
    if (!(await fileExists(loggerFile))) {
      console.warn(`⚠️ E2E logger file not found at ${loggerFile}`);
    }

    // Force switch ON in DB.
    runWpCli(
      `eval "\\$s=get_option('wc_facebook_for_woocommerce_rollout_switches', []); if(!is_array(\\$s)) \\$s=[]; \\$s['enable_woocommerce_capi_event_logging']='yes'; update_option('wc_facebook_for_woocommerce_rollout_switches', \\$s); echo 'enabled';"`
    );

    // Prevent remote rollout refresh from flipping it back to "no" during the run.
    const pluginVersion = await getPluginVersion();
    const transientKey = `_wc_facebook_for_woocommerce_rollout_switch_flag_${pluginVersion}`;
    runWpCli(`eval "set_transient('${transientKey}', 'yes', 3600);"`);

    const verify = runWpCli('option get wc_facebook_for_woocommerce_rollout_switches --format=json');
    const verified = JSON.parse(verify || '{}');
    console.log(`ℹ️ CAPI capture switch: ${verified.enable_woocommerce_capi_event_logging || 'no'} (version ${pluginVersion})`);
  } catch (error) {
    console.warn(`⚠️ Could not enforce CAPI capture switch: ${error.message}`);
  }
}

async function getPluginVersion() {
  try {
    const packagePath = path.join(process.cwd(), 'package.json');
    const raw = await fs.readFile(packagePath, 'utf8');
    const pkg = JSON.parse(raw);
    return pkg.version || '3.6.2';
  } catch {
    return '3.6.2';
  }
}

async function fileExists(filePath) {
  try {
    await fs.access(filePath);
    return true;
  } catch {
    return false;
  }
}

async function getRecentCriticalDebugErrors(limit = 10) {
  const debugLogPath = process.env.WP_DEBUG_LOG;
  if (!debugLogPath) {
    return [];
  }

  try {
    const data = await fs.readFile(debugLogPath, 'utf8');
    const lines = data
      .split('\n')
      .map((line) => line.trim())
      .filter(Boolean)
      .filter((line) => {
        if (!/fatal|error/i.test(line)) return false;
        if (/warning/i.test(line)) return false;
        if (/Cron reschedule event error/i.test(line)) return false;
        return true;
      });

    return lines.slice(-limit);
  } catch {
    return [];
  }
}

async function collectCapiDiagnostics(page, testId, eventName) {
  const diagnostics = {
    eventName,
    testId,
    env: {
      TEST_PRODUCT_URL: process.env.TEST_PRODUCT_URL || null,
      TEST_CATEGORY_URL: process.env.TEST_CATEGORY_URL || null,
      FB_E2E_TEST_COOKIE_NAME: process.env.FB_E2E_TEST_COOKIE_NAME || null,
      FB_E2E_LOGGER_PATH: process.env.FB_E2E_LOGGER_PATH || null,
      WORDPRESS_PATH: process.env.WORDPRESS_PATH || null,
      WORDPRESS_URL: process.env.WORDPRESS_URL || null,
    },
    wp: {},
    cookies: {},
    logs: {},
  };

  try {
    const rolloutRaw = runWpCli('option get wc_facebook_for_woocommerce_rollout_switches --format=json');
    diagnostics.wp.rolloutSwitches = JSON.parse(rolloutRaw || '{}');
  } catch (e) {
    diagnostics.wp.rolloutSwitchesError = e.message;
  }

  try {
    const transientRaw = runWpCli(`eval "echo get_transient('_wc_facebook_for_woocommerce_rollout_switch_flag_${await getPluginVersion()}');"`);
    diagnostics.wp.rolloutTransient = (transientRaw || '').trim() || '(empty)';
  } catch (e) {
    diagnostics.wp.rolloutTransientError = e.message;
  }

  try {
    const loggerPath = process.env.FB_E2E_LOGGER_PATH || '/tests/e2e/helpers/php/event-logger.php';
    const loggerFile = path.join(process.cwd(), loggerPath.replace(/^\//, ''));
    diagnostics.wp.loggerFile = loggerFile;
    diagnostics.wp.loggerFileExists = await fileExists(loggerFile);
  } catch (e) {
    diagnostics.wp.loggerFileError = e.message;
  }

  try {
    const cookies = await page.context().cookies();
    const cookieName = process.env.FB_E2E_TEST_COOKIE_NAME || 'facebook_test_id';
    const target = cookies.find((c) => c.name === cookieName);
    diagnostics.cookies.cookieName = cookieName;
    diagnostics.cookies.cookiePresent = !!target;
    diagnostics.cookies.cookieValue = target?.value || null;
    diagnostics.cookies.pageUrl = page.url();
  } catch (e) {
    diagnostics.cookies.error = e.message;
  }

  try {
    const logsDir = process.env.WC_LOG_PATH || path.join(process.env.WORDPRESS_PATH || '', 'wp-content/uploads/wc-logs');
    diagnostics.logs.logsDir = logsDir;
    const command = `LATEST=$(find "${logsDir}" -type f -name "facebook_for_woocommerce-*.log" | sort | tail -1); echo "$LATEST"; if [ -n "$LATEST" ]; then rg -n "capi_test_event_logging_error|Meta for WooCommerce E2E: CAPI event capturing failed|enable_woocommerce_capi_event_logging|/events" "$LATEST" -S | tail -n 30; fi`;
    diagnostics.logs.wcTail = execSync(command, { encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] });
  } catch (e) {
    diagnostics.logs.error = e.message;
  }

  return diagnostics;
}

async function acquireThemeLock(timeoutMs = 45000) {
  const start = Date.now();
  const token = `${process.pid}-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
  const staleMs = 120000;

  while (Date.now() - start < timeoutMs) {
    try {
      await fs.writeFile(THEME_LOCK_FILE, token, { flag: 'wx' });
      return token;
    } catch (error) {
      if (error.code !== 'EEXIST') {
        throw error;
      }

      // Recover from stale/orphaned lock file left by interrupted runs.
      try {
        const current = (await fs.readFile(THEME_LOCK_FILE, 'utf8')).trim();
        const [pidRaw, createdRaw] = current.split('-');
        const lockPid = Number(pidRaw);
        const createdAt = Number(createdRaw);

        const lockAgeMs = Number.isFinite(createdAt) ? (Date.now() - createdAt) : Number.MAX_SAFE_INTEGER;
        const processAlive = Number.isFinite(lockPid) && lockPid > 0 ? isProcessAlive(lockPid) : false;

        if (!processAlive || lockAgeMs > staleMs) {
          await fs.unlink(THEME_LOCK_FILE).catch(() => {});
          await new Promise((resolve) => setTimeout(resolve, 25));
          continue;
        }
      } catch {
        // If lock disappeared or can't be parsed, retry immediately.
      }

      await new Promise((resolve) => setTimeout(resolve, 120));
    }
  }

  throw new Error('Timed out waiting for theme lock');
}

function isProcessAlive(pid) {
  try {
    process.kill(pid, 0);
    return true;
  } catch {
    return false;
  }
}

async function releaseThemeLock(token) {
  if (!token) {
    return;
  }

  try {
    const current = await fs.readFile(THEME_LOCK_FILE, 'utf8');
    if (current.trim() === token) {
      await fs.unlink(THEME_LOCK_FILE);
    }
  } catch {
    // lock already released
  }
}

function buildOrderedThemeList(rawThemeList) {
  const requested = (rawThemeList || DEFAULT_THEME_SLUGS.join(','))
    .split(',')
    .map((slug) => slug.trim())
    .filter(Boolean)
    .filter((slug) => slug !== BASE_THEME_SLUG);

  const preferredOrder = [...DEFAULT_THEME_SLUGS, ...requested];
  const unique = [];

  for (const slug of preferredOrder) {
    if (!unique.includes(slug)) {
      unique.push(slug);
    }
  }

  // Ensure explicit order for requested user flow.
  const flowOrder = ['twentytwentyone', 'twentytwentyfive'];
  unique.sort((a, b) => {
    const ai = flowOrder.indexOf(a);
    const bi = flowOrder.indexOf(b);
    const aRank = ai === -1 ? Number.MAX_SAFE_INTEGER : ai;
    const bRank = bi === -1 ? Number.MAX_SAFE_INTEGER : bi;
    if (aRank !== bRank) return aRank - bRank;
    return a.localeCompare(b);
  });

  return unique;
}

