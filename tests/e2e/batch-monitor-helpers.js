const { execSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const wpPath = process.env.WORDPRESS_PATH;
const logFilePath = '/tmp/fb-batch-monitor.json';

/**
 * Enable batch monitoring via WP-CLI
 */
async function enableBatchMonitoring() {
    console.log('🔍 Enabling batch monitoring...');
    try {
        execSync('wp fb-monitor enable', { cwd: wpPath, stdio: 'inherit' });
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
        execSync('wp fb-monitor disable', { cwd: wpPath, stdio: 'inherit' });
        console.log('✅ Monitoring disabled');
    } catch (error) {
        console.warn('⚠️ Failed to disable monitoring:', error.message);
    }
}

/**
 * Read the batch monitor log file
 */
function readBatchLog() {
    if (!fs.existsSync(logFilePath)) {
        throw new Error(`Batch log file not found at ${logFilePath}`);
    }

    const content = fs.readFileSync(logFilePath, 'utf8');
    return JSON.parse(content);
}

/**
 * Check if log file exists and has data
 */
function hasBatchLog() {
    return fs.existsSync(logFilePath);
}

/**
 * Wait for batch log to have expected number of products
 */
async function waitForBatchLogProducts(expectedCount, timeoutMs = 60000) {
    const startTime = Date.now();
    const checkInterval = 2000; // Check every 2 seconds

    console.log(`⏳ Waiting for ${expectedCount} products in batch log...`);
    console.log(`   Timeout: ${timeoutMs / 1000}s`);
    console.log(`   Log file: ${logFilePath}`);

    while (Date.now() - startTime < timeoutMs) {
        if (fs.existsSync(logFilePath)) {
            try {
                const log = readBatchLog();
                const totalProducts = log.summary?.total_products || 0;

                if (totalProducts >= expectedCount) {
                    console.log(`✅ Found ${totalProducts} products in log (expected ${expectedCount})`);
                    return log;
                }

                console.log(`   Current: ${totalProducts}/${expectedCount} products (${log.summary?.total_batches || 0} batches)`);
            } catch (error) {
                console.log(`   Error reading log: ${error.message}`);
            }
        } else {
            console.log(`   Log file does not exist yet...`);
        }

        await new Promise(resolve => setTimeout(resolve, checkInterval));
    }

    // Timeout reached
    const elapsed = Date.now() - startTime;
    let currentCount = 0;
    if (fs.existsSync(logFilePath)) {
        const log = readBatchLog();
        currentCount = log.summary?.total_products || 0;
    }

    throw new Error(
        `Timeout after ${elapsed}ms: Expected ${expectedCount} products, but only found ${currentCount}`
    );
}

/**
 * Install and activate the monitoring plugin
 */
async function installMonitoringPlugin() {
    console.log('📦 Installing batch monitoring plugin...');

    const pluginSource = path.join(__dirname, 'fb-e2e-batch-monitor.php');
    const pluginDest = path.join(wpPath, 'wp-content/plugins/fb-e2e-batch-monitor.php');

    // Check if source file exists
    if (!fs.existsSync(pluginSource)) {
        throw new Error(`Plugin source not found: ${pluginSource}`);
    }

    // Copy plugin file
    fs.copyFileSync(pluginSource, pluginDest);
    console.log('📦 Monitoring plugin copied to plugins directory');

    // Activate plugin
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
        // Deactivate plugin
        execSync('wp plugin deactivate fb-e2e-batch-monitor', {
            cwd: wpPath,
            stdio: 'inherit'
        });

        // Remove plugin file
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
 * Clear the batch log file
 */
function clearBatchLog() {
    if (fs.existsSync(logFilePath)) {
        fs.unlinkSync(logFilePath);
        console.log('🧹 Batch log file cleared');
    }
}

/**
 * Get the path to the batch log file
 */
function getBatchLogPath() {
    return logFilePath;
}

/**
 * Check monitoring status via WP-CLI
 */
function getMonitoringStatus() {
    try {
        const output = execSync('wp fb-monitor status', {
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
    hasBatchLog,
    waitForBatchLogProducts,
    installMonitoringPlugin,
    uninstallMonitoringPlugin,
    clearBatchLog,
    getBatchLogPath,
    getMonitoringStatus,
    logFilePath
};
