<?php
/**
 * Plugin Name: E2E CAPI Event Logger
 * Description: Logs CAPI events for E2E testing
 * Version: 1.0.0
 */

// Load the Logger class from the plugin tests directory
require_once WP_CONTENT_DIR . '/plugins/facebook-for-woocommerce/tests/e2e/lib/Logger.php';

/**
 * Global function that API.php calls to log CAPI events
 *
 * Called from includes/API.php:651
 */
function log_event_for_tests($test_id, $event_name, $request_data) {
    error_log("DEBUG_E2E: log_event_for_tests called - test_id: $test_id, event: $event_name");

    E2E_Event_Logger::log_event($test_id, 'capi', [
        'event' => $event_name,
        'data' => $request_data
    ]);
}
