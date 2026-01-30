/**
 * Plugin management helpers for E2E tests
 */

const { exec } = require('child_process');
const { promisify } = require('util');
const fs = require('fs');

const execAsync = promisify(exec);
const { execWP, wpSitePath } = require('./exec');

/**
 * Install and activate a plugin from wordpress.org
 * @param {string} slug - Plugin slug
 */
async function installPlugin(slug) {
  console.log(`📦 Installing plugin: ${slug}...`);

  await execAsync(
    `cd ${wpSitePath} && wp plugin install ${slug} --activate --allow-root 2>&1`,
    { cwd: __dirname }
  );

  console.log(`   Verifying activation...`);
  const { stdout } = await execWP(
    `echo is_plugin_active('${slug}/${slug}.php') ? '1' : '0';`
  );

  if (stdout.trim() !== '1') {
    throw new Error(`Plugin ${slug} failed to activate`);
  }

  console.log(`✅ ${slug} installed and active`);
}

/**
 * Uninstall a plugin
 * @param {string} slug - Plugin slug
 */
async function uninstallPlugin(slug) {
  console.log(`🗑️ Uninstalling plugin: ${slug}...`);
  await execAsync(
    `cd ${wpSitePath} && wp plugin deactivate ${slug} --allow-root 2>&1 || true`,
    { cwd: __dirname }
  );
  await execAsync(
    `cd ${wpSitePath} && wp plugin uninstall ${slug} --allow-root 2>&1 || true`,
    { cwd: __dirname }
  );
  console.log(`✅ ${slug} uninstalled`);
}

/**
 * Deactivate the Facebook for WooCommerce plugin
 */
async function deactivatePlugin() {
  console.log('🔌 Deactivating plugin...');
  await execWP(`deactivate_plugins('facebook-for-woocommerce/facebook-for-woocommerce.php');`);
  console.log('✅ Plugin deactivated');
}

/**
 * Activate the Facebook for WooCommerce plugin
 */
async function activatePlugin() {
  console.log('🔌 Activating plugin...');
  await execWP(`activate_plugins('facebook-for-woocommerce/facebook-for-woocommerce.php');`);
  console.log('✅ Plugin activated');
}

/**
 * Install mu-plugin that disables pixel tracking
 */
async function installPixelBlockerMuPlugin() {
  console.log('🔧 Installing pixel blocker mu-plugin...');
  const muPluginDir = `${wpSitePath}/wp-content/mu-plugins`;
  const muPluginFile = `${muPluginDir}/e2e-pixel-blocker.php`;
  const code = `<?php\nadd_filter('facebook_for_woocommerce_integration_pixel_enabled', '__return_false', 999);\n`;

  console.log(`   Creating dir: ${muPluginDir}`);
  fs.mkdirSync(muPluginDir, { recursive: true });

  console.log(`   Writing: ${muPluginFile}`);
  fs.writeFileSync(muPluginFile, code);

  console.log('✅ Pixel blocker mu-plugin installed');
}

/**
 * Remove the pixel blocker mu-plugin
 */
async function removePixelBlockerMuPlugin() {
  console.log('🧹 Removing pixel blocker mu-plugin...');
  const muPluginFile = `${wpSitePath}/wp-content/mu-plugins/e2e-pixel-blocker.php`;

  if (fs.existsSync(muPluginFile)) {
    fs.unlinkSync(muPluginFile);
  }
  console.log('✅ Pixel blocker mu-plugin removed');
}

/**
 * Install mu-plugin that simulates JS errors from other plugins.
 * Tests that our isolated pixel event execution still works when other plugins break.
 */
async function installJsErrorSimulatorMuPlugin() {
  console.log('🔧 Installing JS error simulator mu-plugin...');
  const muPluginDir = `${wpSitePath}/wp-content/mu-plugins`;
  const muPluginFile = `${muPluginDir}/e2e-js-error-simulator.php`;

  const code = `<?php
/**
 * Plugin Name: E2E JS Error Simulator
 * Description: Simulates JS errors from other plugins to test isolated pixel event execution.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ERROR 1: Broken JS in wc_enqueue_js (WooCommerce's shared queue)
// This is the MAIN scenario our isolated approach protects against.
add_action( 'wp_footer', function() {
    if ( ! function_exists( 'wc_enqueue_js' ) ) {
        return;
    }
    wc_enqueue_js( '
        console.log("[E2E Test] ERROR 1: Broken JS in wc_queued_js");
        throw new Error("Simulated error in wc_queued_js - tests isolated execution");
    ' );
}, 5 );

// ERROR 2: Broken inline JS in footer (common in poorly coded plugins)
add_action( 'wp_footer', function() {
    ?>
    <script>
        console.log("[E2E Test] ERROR 2: Broken inline script in wp_footer");
        throw new Error("Simulated error from another plugin's inline script");
    </script>
    <?php
}, 15 );

// ERROR 3: Broken JS in jQuery document ready
add_action( 'wp_footer', function() {
    ?>
    <script>
        jQuery(document).ready(function($) {
            console.log("[E2E Test] ERROR 3: Broken jQuery document.ready handler");
            throw new Error("Simulated error in jQuery document.ready");
        });
    </script>
    <?php
}, 20 );
`;

  console.log(`   Creating dir: ${muPluginDir}`);
  fs.mkdirSync(muPluginDir, { recursive: true });

  console.log(`   Writing: ${muPluginFile}`);
  fs.writeFileSync(muPluginFile, code);

  console.log('✅ JS error simulator mu-plugin installed');
}

/**
 * Remove the JS error simulator mu-plugin
 */
async function removeJsErrorSimulatorMuPlugin() {
  console.log('🧹 Removing JS error simulator mu-plugin...');
  const muPluginFile = `${wpSitePath}/wp-content/mu-plugins/e2e-js-error-simulator.php`;

  if (fs.existsSync(muPluginFile)) {
    fs.unlinkSync(muPluginFile);
  }
  console.log('✅ JS error simulator mu-plugin removed');
}

module.exports = {
  installPlugin,
  uninstallPlugin,
  deactivatePlugin,
  activatePlugin,
  installPixelBlockerMuPlugin,
  removePixelBlockerMuPlugin,
  installJsErrorSimulatorMuPlugin,
  removeJsErrorSimulatorMuPlugin
};
