/**
 * WordPress execution helpers for E2E tests
 */

const { exec, execSync } = require('child_process');
const { promisify } = require('util');

const execAsync = promisify(exec);
const wpSitePath = process.env.WORDPRESS_PATH;

/**
 * Execute WordPress PHP code
 * @param {string} phpCode - PHP code to execute
 */
async function execWP(phpCode) {
  return execAsync(
    `php -r "require_once('${wpSitePath}/wp-load.php'); ${phpCode}"`,
    { cwd: __dirname }
  );
}

/**
 * Ensure debug mode is enabled for Meta for WooCommerce
 * @param {import('@playwright/test').Page} page - Playwright page
 * @returns {Promise<boolean>} Success status
 */
async function ensureDebugModeEnabled(page) {
  const { TIMEOUTS } = require('../constants/timeouts');

  try {
    await page.goto(`${process.env.WORDPRESS_URL}/wp-admin/options.php`, {
      waitUntil: 'domcontentloaded',
      timeout: TIMEOUTS.EXTRA_LONG
    });

    const input = page.locator('#wc_facebook_enable_debug_mode');
    const inputExists = await input.count();

    const currentValue = inputExists > 0 ? await input.inputValue() : '';

    if (currentValue !== 'yes') {
      console.log('🔧 Debug mode is not enabled, enabling it...');
      await execWP(`update_option('wc_facebook_enable_debug_mode', 'yes');`);
      console.log('✅ Debug mode enabled');
    } else {
      console.log('✅ Debug mode already enabled');
    }

    return true;
  } catch (error) {
    console.error(`❌ Error ensuring debug mode: ${error.message}`);
    return false;
  }
}

/**
 * Check WooCommerce logs for errors
 * @returns {Promise<Object>} Check result
 */
async function checkWooCommerceLogs() {
  console.log('🔍 Checking WooCommerce logs for errors...');

  const today = new Date().toISOString().split('T')[0];
  const logsDir = process.env.WC_LOG_PATH;

  if (!logsDir) {
    throw new Error('❌ WC_LOG_PATH environment variable not set');
  }

  const logFile = execSync(
    `find "${logsDir}" -name "facebook_for_woocommerce-${today}*.log" 2>/dev/null | head -1`,
    { encoding: 'utf8' }
  ).trim();

  if (!logFile) {
    console.log(`ℹ️ No log file found for today - ${today}`);
    return { success: true };
  }

  console.log(`📄 Checking: ${logFile}`);

  const non200Lines = execSync(
    `grep -n "code: " "${logFile}" | grep -v "code: 200" || true`,
    { encoding: 'utf8' }
  ).trim();

  if (non200Lines) {
    console.log(`❌ Found non-200 response codes in log file: ${logFile}`);
    console.log('Please check WooCommerce logs in Github Artifacts');

    const criticalLogs = execSync(
      `grep -E "^[0-9T:+-]+ (ERROR|CRITICAL|ALERT|EMERGENCY) " "${logFile}" || true`,
      { encoding: 'utf8' }
    ).trim();

    if (criticalLogs) {
      console.log('\n❌ CRITICAL ERRORS FOUND IN LOGS:');
      console.log(criticalLogs);
    }

    return { success: false, error: 'Non-200 response codes found' };
  }

  console.log('✅ All response codes are 200');
  return { success: true };
}

module.exports = {
  wpSitePath,
  execWP,
  ensureDebugModeEnabled,
  checkWooCommerceLogs
};
