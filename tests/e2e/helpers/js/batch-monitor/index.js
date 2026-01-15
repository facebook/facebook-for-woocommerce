/**
 * Batch monitoring helpers for E2E tests
 */

const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const wpPath = process.env.WORDPRESS_PATH;

/**
 * Enable batch monitoring via WP-CLI
 */
async function enableBatchMonitoring() {
  console.log('🔍 Enabling batch monitoring...');
  try {
    execSync('wp fb-batch-api-monitor enable', { cwd: wpPath, stdio: 'inherit' });
    console.log('✅ Monitoring enabled');
  } catch (error) {
    console.error('❌ Failed to enable monitoring:', error.message);
    throw error;
  }
}

/**
 * Disable batch monitoring via WP-CLI
 */
async function disableBatchMonitoring() {
  console.log('🔇 Disabling batch monitoring...');
  try {
    execSync('wp fb-batch-api-monitor disable', { cwd: wpPath, stdio: 'inherit' });
    console.log('✅ Monitoring disabled');
  } catch (error) {
    console.warn('⚠️ Failed to disable monitoring:', error.message);
  }
}

/**
 * Read the batch monitor log via WP-CLI
 * @returns {Object} Parsed log data
 */
function readBatchLog() {
  try {
    const output = execSync('wp fb-batch-api-monitor get-log', {
      cwd: wpPath,
      encoding: 'utf8'
    });
    return JSON.parse(output);
  } catch (error) {
    throw new Error(`Failed to read batch log: ${error.message}`);
  }
}

/**
 * Wait for batch log to have expected number of products
 * @param {number} expectedCount - Expected product count
 * @param {string} expectedProductType - Product type to filter
 * @param {number} timeoutMs - Timeout in milliseconds
 * @returns {Promise<Object>} Filtered log data
 */
async function waitForBatchLogProducts(expectedCount, expectedProductType, timeoutMs = 60000) {
  const startTime = Date.now();
  const checkInterval = 2000;

  console.log(`⏳ Waiting for ${expectedCount} products in batch log...`);
  console.log(`   Product type filter: ${expectedProductType}`);
  console.log(`   Timeout: ${timeoutMs / 1000}s`);

  while (Date.now() - startTime < timeoutMs) {
    try {
      const log = readBatchLog();

      const filteredBatches = log.batches.filter(batch => {
        if (!batch.request_sample || !Array.isArray(batch.request_sample)) {
          return false;
        }
        return batch.request_sample.some(sample =>
          sample?.data?.product_type === expectedProductType
        );
      });

      const totalProducts = filteredBatches.reduce((sum, batch) => sum + (batch.batch_size || 0), 0);

      if (totalProducts >= expectedCount) {
        console.log(`✅ Found ${totalProducts} products in log (expected ${expectedCount})`);

        return {
          batches: filteredBatches,
          summary: {
            total_batches: filteredBatches.length,
            total_products: totalProducts,
            first_batch_time: filteredBatches[0]?.datetime || null,
            last_batch_time: filteredBatches[filteredBatches.length - 1]?.datetime || null
          }
        };
      }

      console.log(`   Current: ${totalProducts}/${expectedCount} products (${filteredBatches.length} batches)`);
    } catch (error) {
      console.log(`   Waiting for log file... (${error.message})`);
    }

    await new Promise(resolve => setTimeout(resolve, checkInterval));
  }

  // Timeout reached
  const elapsed = Date.now() - startTime;
  let currentCount = 0;
  try {
    const log = readBatchLog();

    const filteredBatches = log.batches.filter(batch => {
      if (!batch.request_sample || !Array.isArray(batch.request_sample)) {
        return false;
      }
      return batch.request_sample.some(sample =>
        sample?.data?.product_type === expectedProductType
      );
    });

    currentCount = filteredBatches.reduce((sum, batch) => sum + (batch.batch_size || 0), 0);
  } catch (error) {
    // Log file doesn't exist yet
  }

  throw new Error(
    `Timeout after ${elapsed}ms: Expected ${expectedCount} products with type "${expectedProductType}", but only found ${currentCount}`
  );
}

/**
 * Install and activate the monitoring plugin
 */
async function installMonitoringPlugin() {
  console.log('📦 Installing batch monitoring plugin...');

  const pluginSource = path.join(__dirname, '../../php/batch-monitor-plugin.php');
  const pluginDest = path.join(wpPath, 'wp-content/plugins/fb-e2e-batch-monitor.php');

  if (!fs.existsSync(pluginSource)) {
    throw new Error(`Plugin source not found: ${pluginSource}`);
  }

  fs.copyFileSync(pluginSource, pluginDest);
  console.log('📦 Monitoring plugin copied to plugins directory');

  try {
    execSync('wp plugin activate fb-e2e-batch-monitor', { cwd: wpPath, stdio: 'inherit' });
    console.log('✅ Monitoring plugin activated');
  } catch (error) {
    console.error('❌ Failed to activate plugin:', error.message);
    throw error;
  }
}

/**
 * Deactivate and remove the monitoring plugin
 */
async function uninstallMonitoringPlugin() {
  console.log('🧹 Uninstalling batch monitoring plugin...');

  try {
    execSync('wp plugin deactivate fb-e2e-batch-monitor', {
      cwd: wpPath,
      stdio: 'inherit'
    });

    const pluginPath = path.join(wpPath, 'wp-content/plugins/fb-e2e-batch-monitor.php');
    if (fs.existsSync(pluginPath)) {
      fs.unlinkSync(pluginPath);
      console.log('✅ Monitoring plugin removed');
    }
  } catch (error) {
    console.warn('⚠️ Failed to uninstall monitoring plugin:', error.message);
  }
}

/**
 * Check monitoring status via WP-CLI
 * @returns {string} Status message
 */
function getMonitoringStatus() {
  try {
    const output = execSync('wp fb-batch-api-monitor status', {
      cwd: wpPath,
      encoding: 'utf8'
    });
    return output.trim();
  } catch (error) {
    return `Error: ${error.message}`;
  }
}

module.exports = {
  enableBatchMonitoring,
  disableBatchMonitoring,
  readBatchLog,
  waitForBatchLogProducts,
  installMonitoringPlugin,
  uninstallMonitoringPlugin,
  getMonitoringStatus
};
