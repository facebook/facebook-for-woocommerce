/**
 * E2E Test Timeout Constants
 *
 * Centralized timeout values for consistent E2E test behavior.
 * All values are in milliseconds.
 */

const TIMEOUTS = {
  INSTANT: 500,        // 0.5 seconds - instant UI feedback
  SHORT: 1000,         // 1 second - quick UI updates
  NORMAL: 2000,        // 2 seconds - standard operations
  MEDIUM: 5000,        // 5 seconds - moderate operations
  LONG: 10000,         // 10 seconds - slow operations
  EXTRA_LONG: 30000,   // 30 seconds - very slow operations
  MAX: 120000,         // 120 seconds - maximum timeout (navigation, page loads)
};

module.exports = { TIMEOUTS };
