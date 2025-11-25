<?php
/**
 * E2E Test Configuration for PHP
 *
 * Centralized configuration for E2E testing PHP components.
 * This file defines constants used by both the plugin and test framework.
 */

// Define the logger file path relative to plugin root
if ( ! defined( 'FB_E2E_LOGGER_PATH' ) ) {
	define( 'FB_E2E_LOGGER_PATH', '/tests/e2e/lib/Logger.php' );
}

// Define the captured events directory relative to plugin root
if ( ! defined( 'FB_E2E_CAPTURED_EVENTS_DIR' ) ) {
	define( 'FB_E2E_CAPTURED_EVENTS_DIR', '/tests/e2e/captured-events' );
}

// Define the test cookie name
if ( ! defined( 'FB_E2E_TEST_COOKIE_NAME' ) ) {
	define( 'FB_E2E_TEST_COOKIE_NAME', 'facebook_test_id' );
}

// Define the test event code from environment variable
if ( ! defined( 'FB_TEST_EVENT_CODE' ) ) {
	$test_event_code = getenv( 'FB_TEST_EVENT_CODE' );
	if ( $test_event_code !== false && ! empty( $test_event_code ) ) {
		define( 'FB_TEST_EVENT_CODE', $test_event_code );
	}
}
