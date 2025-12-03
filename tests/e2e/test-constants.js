/**
 * E2E Test Timeout Constants
 *
 * Centralized timeout values for consistent E2E test behavior.
 * All values are in milliseconds.
 */

// Navigation and page load timeouts
const TIMEOUTS = {
  // Navigation timeouts - for page.goto() and page.waitForLoadState()
  NAVIGATION: 60000,          // 60 seconds - WordPress admin page loads

  // Element visibility timeouts - for waitFor(), isVisible()
  ELEMENT_LONG: 10000,        // 10 seconds - for elements that may take time to appear
  ELEMENT_STANDARD: 5000,     // 5 seconds - standard wait for most elements
  ELEMENT_QUICK: 2000,        // 2 seconds - quick checks for already loaded elements

  // Explicit wait timeouts - for page.waitForTimeout()
  WAIT_EXTRA_LONG: 8000,      // 8 seconds - for complex operations (e.g., variation generation)
  WAIT_LONG: 5000,            // 5 seconds - for slow operations (e.g., save attributes, product publish)
  WAIT_STANDARD: 3000,        // 3 seconds - for standard operations (e.g., bulk price updates)
  WAIT_MEDIUM: 2000,          // 2 seconds - for medium operations (e.g., tab switches, form updates)
  WAIT_SHORT: 1000,           // 1 second - for quick UI updates (e.g., tab content load)
};

module.exports = { TIMEOUTS };
