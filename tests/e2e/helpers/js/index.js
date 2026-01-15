/**
 * E2E Test Helpers - Barrel Export
 *
 * Location: tests/e2e/helpers/js/index.js
 *
 * This file re-exports all helper modules for clean imports in spec files.
 *
 * Usage:
 *   const { loginToWordPress, createTestProduct, TIMEOUTS } = require('./helpers/js');
 *
 * Or import specific modules:
 *   const { auth, products, facebook } = require('./helpers/js');
 */

// =============================================================================
// Constants
// =============================================================================
const { TIMEOUTS } = require('./constants/timeouts');

// =============================================================================
// Auth
// =============================================================================
const {
  baseURL,
  username,
  password,
  loginToWordPress
} = require('./auth/login');

// =============================================================================
// Products
// =============================================================================
const {
  generateProductName,
  generateUniqueSKU,
  extractProductIdFromUrl,
  cleanupProduct,
  createTestProduct
} = require('./products/crud');

const {
  filterProducts,
  clickFirstProduct,
  publishProduct
} = require('./products/navigation');

const {
  generateProductFeedCSV,
  deleteFeedFile,
  generateProductUpdateCSV
} = require('./products/feed');

// =============================================================================
// Categories
// =============================================================================
const {
  createTestCategory,
  cleanupCategory
} = require('./categories/crud');

// =============================================================================
// Facebook
// =============================================================================
const {
  validateFacebookSync,
  validateCategorySync
} = require('./facebook/sync');

const {
  getConnectionStatus,
  disconnectAndVerify,
  reconnectAndVerify,
  verifyProductsFacebookFieldsCleared
} = require('./facebook/connection');

const {
  openFacebookOptions
} = require('./facebook/options');

// =============================================================================
// WordPress
// =============================================================================
const {
  wpSitePath,
  execWP,
  ensureDebugModeEnabled,
  checkWooCommerceLogs
} = require('./wordpress/exec');

const {
  installPlugin,
  uninstallPlugin,
  deactivatePlugin,
  activatePlugin,
  installPixelBlockerMuPlugin,
  removePixelBlockerMuPlugin
} = require('./wordpress/plugins');

// =============================================================================
// Checkout
// =============================================================================
const {
  completePurchaseFlow
} = require('./checkout/purchase');

// =============================================================================
// Batch Monitor
// =============================================================================
const {
  enableBatchMonitoring,
  disableBatchMonitoring,
  readBatchLog,
  waitForBatchLogProducts,
  installMonitoringPlugin,
  uninstallMonitoringPlugin,
  getMonitoringStatus
} = require('./batch-monitor');

// =============================================================================
// Utils
// =============================================================================
const {
  safeScreenshot,
  logTestStart,
  logTestEnd
} = require('./utils/logging');

const {
  ERROR_WHITELIST,
  checkForPhpErrors,
  checkForJsErrors
} = require('./utils/errors');

const {
  setProductTitle,
  setProductDescription,
  exactSearchSelect2Container
} = require('./utils/ui');

// =============================================================================
// Events
// =============================================================================
const EventValidator = require('./events/validator');
const PixelCapture = require('./events/capture');
const EVENT_SCHEMAS = require('./events/schemas');
const TestSetup = require('./events/setup');

// =============================================================================
// Module Exports (Grouped)
// =============================================================================

// Export grouped modules for namespace imports
const auth = {
  baseURL,
  username,
  password,
  loginToWordPress
};

const products = {
  generateProductName,
  generateUniqueSKU,
  extractProductIdFromUrl,
  cleanupProduct,
  createTestProduct,
  filterProducts,
  clickFirstProduct,
  publishProduct,
  generateProductFeedCSV,
  deleteFeedFile,
  generateProductUpdateCSV
};

const categories = {
  createTestCategory,
  cleanupCategory
};

const facebook = {
  validateFacebookSync,
  validateCategorySync,
  getConnectionStatus,
  disconnectAndVerify,
  reconnectAndVerify,
  verifyProductsFacebookFieldsCleared,
  openFacebookOptions
};

const wordpress = {
  wpSitePath,
  execWP,
  ensureDebugModeEnabled,
  checkWooCommerceLogs,
  installPlugin,
  uninstallPlugin,
  deactivatePlugin,
  activatePlugin,
  installPixelBlockerMuPlugin,
  removePixelBlockerMuPlugin
};

const checkout = {
  completePurchaseFlow
};

const batchMonitor = {
  enableBatchMonitoring,
  disableBatchMonitoring,
  readBatchLog,
  waitForBatchLogProducts,
  installMonitoringPlugin,
  uninstallMonitoringPlugin,
  getMonitoringStatus
};

const utils = {
  safeScreenshot,
  logTestStart,
  logTestEnd,
  ERROR_WHITELIST,
  checkForPhpErrors,
  checkForJsErrors,
  setProductTitle,
  setProductDescription,
  exactSearchSelect2Container
};

const events = {
  EventValidator,
  PixelCapture,
  EVENT_SCHEMAS,
  TestSetup
};

// =============================================================================
// Flat Exports (for destructuring imports)
// =============================================================================
module.exports = {
  // Constants
  TIMEOUTS,

  // Auth
  baseURL,
  username,
  password,
  loginToWordPress,

  // Products - CRUD
  generateProductName,
  generateUniqueSKU,
  extractProductIdFromUrl,
  cleanupProduct,
  createTestProduct,

  // Products - Navigation
  filterProducts,
  clickFirstProduct,
  publishProduct,

  // Products - Feed
  generateProductFeedCSV,
  deleteFeedFile,
  generateProductUpdateCSV,

  // Categories
  createTestCategory,
  cleanupCategory,

  // Facebook - Sync
  validateFacebookSync,
  validateCategorySync,

  // Facebook - Connection
  getConnectionStatus,
  disconnectAndVerify,
  reconnectAndVerify,
  verifyProductsFacebookFieldsCleared,

  // Facebook - Options
  openFacebookOptions,

  // WordPress - Exec
  wpSitePath,
  execWP,
  ensureDebugModeEnabled,
  checkWooCommerceLogs,

  // WordPress - Plugins
  installPlugin,
  uninstallPlugin,
  deactivatePlugin,
  activatePlugin,
  installPixelBlockerMuPlugin,
  removePixelBlockerMuPlugin,

  // Checkout
  completePurchaseFlow,

  // Batch Monitor
  enableBatchMonitoring,
  disableBatchMonitoring,
  readBatchLog,
  waitForBatchLogProducts,
  installMonitoringPlugin,
  uninstallMonitoringPlugin,
  getMonitoringStatus,

  // Utils - Logging
  safeScreenshot,
  logTestStart,
  logTestEnd,

  // Utils - Errors
  ERROR_WHITELIST,
  checkForPhpErrors,
  checkForJsErrors,

  // Utils - UI
  setProductTitle,
  setProductDescription,
  exactSearchSelect2Container,

  // Events
  EventValidator,
  PixelCapture,
  EVENT_SCHEMAS,
  TestSetup,

  // Grouped module exports (for namespace imports)
  auth,
  products,
  categories,
  facebook,
  wordpress,
  checkout,
  batchMonitor,
  utils,
  events
};
