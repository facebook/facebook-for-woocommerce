<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Integrations;

use WooCommerce\Facebook\API;
use WooCommerce\Facebook\Framework\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Main logging orchestrator class for integration availability data.
 *
 * Handles monthly scheduling via WordPress transients, collects data from all
 * localization integrations, and sends data to Meta via existing API infrastructure.
 */
class IntegrationAvailabilityLogger {

	/**
	 * Transient key for tracking when integration availability was last logged
	 */
	const TRANSIENT_KEY = 'wc_facebook_integration_availability_logged';

	/**
	 * Option key for storing integration availability data
	 */
	const OPTION_KEY = 'wc_facebook_integration_availability_data';

	/**
	 * How often to log integration availability data (30 days in seconds)
	 */
	const LOG_INTERVAL = 30 * DAY_IN_SECONDS;

	/**
	 * Initialize the integration availability logger
	 *
	 * Sets up the monthly logging schedule if needed
	 */
	public static function init(): void {
		// Check if we need to log integration availability data
		if ( self::should_log_integration_availability() ) {
			self::log_integration_availability();
		}
	}

	/**
	 * Check if integration availability data should be logged
	 *
	 * @return bool True if data should be logged, false otherwise
	 */
	private static function should_log_integration_availability(): bool {
		// Check if WordPress functions are available
		if ( ! function_exists( 'get_transient' ) || ! function_exists( 'set_transient' ) ) {
			return false;
		}

		// Check if we've already logged within the interval
		$last_logged = get_transient( self::TRANSIENT_KEY );
		if ( false !== $last_logged ) {
			return false;
		}

		// Check if we have a valid Facebook connection
		if ( ! self::has_valid_facebook_connection() ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if there's a valid Facebook connection
	 *
	 * @return bool True if connection is valid, false otherwise
	 */
	private static function has_valid_facebook_connection(): bool {
		// Check if the main plugin is available
		if ( ! function_exists( 'facebook_for_woocommerce' ) ) {
			return false;
		}

		$plugin = facebook_for_woocommerce();
		if ( ! $plugin ) {
			return false;
		}

		// Check if we have a valid connection manager
		$connection_handler = $plugin->get_connection_handler();
		if ( ! $connection_handler ) {
			return false;
		}

		// Check if we're connected to Facebook
		if ( ! $connection_handler->is_connected() ) {
			return false;
		}

		return true;
	}

	/**
	 * Log integration availability data to Meta
	 */
	public static function log_integration_availability(): void {
		try {
			Logger::log(
				'Starting integration availability logging',
				[
					'event' => 'integration_availability_start',
				],
				[
					'should_send_log_to_meta' => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level' => \WC_Log_Levels::DEBUG,
				]
			);

			// Collect integration availability data
			$data = self::collect_integration_availability_data();

			// Store the data locally for debugging/reference
			if ( function_exists( 'update_option' ) ) {
				update_option( self::OPTION_KEY, $data );
			}

			// Send data to Meta via unified logger (no manual API call needed)
			Logger::log(
				'Integration availability data collected and ready to send',
				[
					'event' => 'integration_availability_success',
					'event_type' => 'integration_availability',
					'event_data' => $data,
					'data_size' => count( $data ),
				],
				[
					'should_send_log_to_meta' => true,  // This automatically sends to Meta
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level' => \WC_Log_Levels::INFO,
				]
			);

			// Set transient to prevent logging again for the interval
			if ( function_exists( 'set_transient' ) ) {
				set_transient( self::TRANSIENT_KEY, time(), self::LOG_INTERVAL );
			}

		} catch ( \Exception $e ) {
			Logger::log(
				'Failed to log integration availability data',
				[
					'event' => 'integration_availability_error',
					'error_message' => $e->getMessage(),
					'error_code' => $e->getCode(),
				],
				[
					'should_send_log_to_meta' => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level' => \WC_Log_Levels::ERROR,
				],
				$e
			);
		}
	}

	/**
	 * Collect integration availability data
	 *
	 * @return array Integration availability data
	 */
	private static function collect_integration_availability_data(): array {
		$data = [
			'timestamp' => time(),
			'site_info' => self::get_site_info(),
			'integrations' => [
				'localization' => IntegrationRegistry::get_all_localization_availability_data(),
			],
		];

		return $data;
	}

	/**
	 * Get site information
	 *
	 * @return array Site information
	 */
	private static function get_site_info(): array {
		$site_info = [
			'is_multisite' => is_multisite(),
		];

		// Add WordPress version if available
		if ( function_exists( 'get_bloginfo' ) ) {
			$site_info['wp_version'] = get_bloginfo( 'version' );
		}

		// Add WooCommerce version if available
		if ( class_exists( 'WC' ) && defined( 'WC_VERSION' ) ) {
			$site_info['wc_version'] = WC_VERSION;
		}

		// Add plugin version if available
		if ( function_exists( 'facebook_for_woocommerce' ) ) {
			$plugin = facebook_for_woocommerce();
			if ( $plugin && method_exists( $plugin, 'get_version' ) ) {
				$site_info['plugin_version'] = $plugin->get_version();
			}
		}

		return $site_info;
	}


	/**
	 * Get the last logged integration availability data
	 *
	 * @return array|null Last logged data or null if not available
	 */
	public static function get_last_logged_data(): ?array {
		if ( ! function_exists( 'get_option' ) ) {
			return null;
		}

		$data = get_option( self::OPTION_KEY );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Get the timestamp of when integration availability was last logged
	 *
	 * @return int|null Timestamp or null if never logged
	 */
	public static function get_last_logged_timestamp(): ?int {
		if ( ! function_exists( 'get_transient' ) ) {
			return null;
		}

		$timestamp = get_transient( self::TRANSIENT_KEY );
		return is_numeric( $timestamp ) ? (int) $timestamp : null;
	}

	/**
	 * Force log integration availability data (bypasses interval check)
	 *
	 * Useful for testing or manual triggering
	 */
	public static function force_log_integration_availability(): void {
		// Clear the transient to bypass interval check
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( self::TRANSIENT_KEY );
		}

		// Log the data
		self::log_integration_availability();
	}

	/**
	 * Clear all stored integration availability data
	 *
	 * Useful for testing or cleanup
	 */
	public static function clear_data(): void {
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( self::TRANSIENT_KEY );
		}

		if ( function_exists( 'delete_option' ) ) {
			delete_option( self::OPTION_KEY );
		}
	}
}
