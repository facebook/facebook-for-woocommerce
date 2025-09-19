<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Feed\Localization;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\Framework\Logger;

/**
 * Country Feed Management Trait
 *
 * Handles creating and managing country override feeds on Meta/Facebook.
 * Similar to LanguageFeedManagementTrait but for country-specific feeds.
 *
 * @since 3.0.18
 */
trait CountryFeedManagementTrait {

	/**
	 * Retrieve or create a country override feed ID for the given country.
	 *
	 * @param string $country_code Two-letter country code (e.g., 'GB', 'DE')
	 * @return string|null Feed ID or null on failure
	 * @since 3.0.18
	 */
	public function retrieve_or_create_country_feed_id( string $country_code ): ?string {
		$option_name = $this->get_country_feed_id_option_name( $country_code );
		$feed_id = get_option( $option_name );

		if ( $feed_id ) {
			// Validate that the feed still exists on Facebook
			if ( $this->validate_country_feed_exists( $feed_id, $country_code ) ) {
				return $feed_id;
			}
			// Feed no longer exists, clear the option and create a new one
			delete_option( $option_name );
		}

		// Create a new country override feed
		$new_feed_id = $this->create_country_override_feed( $country_code );
		if ( $new_feed_id ) {
			update_option( $option_name, $new_feed_id );
		}

		return $new_feed_id;
	}

	/**
	 * Create a new country override feed on Facebook.
	 *
	 * @param string $country_code Two-letter country code
	 * @return string|null Feed ID or null on failure
	 * @since 3.0.18
	 */
	private function create_country_override_feed( string $country_code ): ?string {
		try {
			$connection_handler = facebook_for_woocommerce()->get_connection_handler();
			$catalog_id = $connection_handler->get_product_catalog_id();

			if ( empty( $catalog_id ) ) {
				Logger::log(
					'Cannot create country override feed: No product catalog ID',
					[],
					array(
						'should_send_log_to_meta'        => false,
						'should_save_log_in_woocommerce' => true,
						'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
					)
				);
				return null;
			}

			$country_name = \WooCommerce\Facebook\Locale::get_country_name( $country_code );
			$site_name = get_bloginfo( 'name' );

			$feed_name = sprintf(
				static::FEED_NAME_TEMPLATE,
				$site_name,
				$country_name ?: $country_code
			);

			$feed_data = [
				'name' => $feed_name,
				'schedule' => [
					'interval' => 'WEEKLY', // Country feeds update less frequently
					'url' => $this->get_country_feed_url( $country_code ),
				],
			];

			$api = facebook_for_woocommerce()->get_api();
			$response = $api->create_product_feed( $catalog_id, $feed_data );

			if ( $response && isset( $response['id'] ) ) {
				Logger::log(
					'Country override feed created successfully',
					array(
						'event' => 'country_feed_creation',
						'event_type' => 'create_country_override_feed',
						'extra_data' => [
							'country_code' => $country_code,
							'feed_id' => $response['id'],
							'feed_name' => $feed_name,
						],
					),
					array(
						'should_send_log_to_meta'        => true,
						'should_save_log_in_woocommerce' => false,
						'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
					)
				);
				return $response['id'];
			}

		} catch ( \Exception $exception ) {
			Logger::log(
				'Failed to create country override feed',
				array(
					'event' => 'country_feed_creation',
					'event_type' => 'create_country_override_feed',
					'extra_data' => [
						'country_code' => $country_code,
					],
				),
				array(
					'should_send_log_to_meta'        => true,
					'should_save_log_in_woocommerce' => false,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				),
				$exception,
			);
		}

		return null;
	}

	/**
	 * Validate that a country override feed still exists on Facebook.
	 *
	 * @param string $feed_id Feed ID to validate
	 * @param string $country_code Two-letter country code
	 * @return bool True if feed exists, false otherwise
	 * @since 3.0.18
	 */
	private function validate_country_feed_exists( string $feed_id, string $country_code ): bool {
		try {
			$api = facebook_for_woocommerce()->get_api();
			$response = $api->get_product_feed( $feed_id );

			// If we get a response with an ID, the feed exists
			return isset( $response['id'] ) && $response['id'] === $feed_id;

		} catch ( \Exception $exception ) {
			Logger::log(
				'Country override feed validation failed',
				array(
					'event' => 'country_feed_validation',
					'event_type' => 'validate_country_feed_exists',
					'extra_data' => [
						'country_code' => $country_code,
						'feed_id' => $feed_id,
					],
				),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::WARNING,
				),
				$exception,
			);
			return false;
		}
	}

	/**
	 * Get the option name for storing a country feed ID.
	 *
	 * @param string $country_code Two-letter country code
	 * @return string Option name
	 * @since 3.0.18
	 */
	private function get_country_feed_id_option_name( string $country_code ): string {
		return 'wc_facebook_country_override_feed_id_' . strtolower( $country_code );
	}

	/**
	 * Delete country override feed from Facebook and remove stored feed ID.
	 *
	 * @param string $country_code Two-letter country code
	 * @return bool True on success, false on failure
	 * @since 3.0.18
	 */
	public function delete_country_override_feed( string $country_code ): bool {
		$option_name = $this->get_country_feed_id_option_name( $country_code );
		$feed_id = get_option( $option_name );

		if ( ! $feed_id ) {
			return true; // No feed to delete
		}

		try {
			$api = facebook_for_woocommerce()->get_api();
			$response = $api->delete_product_feed( $feed_id );

			delete_option( $option_name );

			Logger::log(
				'Country override feed deleted successfully',
				array(
					'event' => 'country_feed_deletion',
					'event_type' => 'delete_country_override_feed',
					'extra_data' => [
						'country_code' => $country_code,
						'feed_id' => $feed_id,
					],
				),
				array(
					'should_send_log_to_meta'        => true,
					'should_save_log_in_woocommerce' => false,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

			return true;

		} catch ( \Exception $exception ) {
			Logger::log(
				'Failed to delete country override feed',
				array(
					'event' => 'country_feed_deletion',
					'event_type' => 'delete_country_override_feed',
					'extra_data' => [
						'country_code' => $country_code,
						'feed_id' => $feed_id,
					],
				),
				array(
					'should_send_log_to_meta'        => true,
					'should_save_log_in_woocommerce' => false,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				),
				$exception,
			);
			return false;
		}
	}

	/**
	 * Get all stored country override feed IDs.
	 *
	 * @return array Array of country_code => feed_id pairs
	 * @since 3.0.18
	 */
	public function get_all_country_feed_ids(): array {
		global $wpdb;

		$country_feeds = array();
		$option_prefix = 'wc_facebook_country_override_feed_id_';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
				$option_prefix . '%'
			)
		);

		foreach ( $results as $result ) {
			$country_code = strtoupper( str_replace( $option_prefix, '', $result->option_name ) );
			$country_feeds[ $country_code ] = $result->option_value;
		}

		return $country_feeds;
	}

	/**
	 * Clean up all country override feeds.
	 * Useful for plugin deactivation or reset functionality.
	 *
	 * @return int Number of feeds successfully deleted
	 * @since 3.0.18
	 */
	public function cleanup_all_country_feeds(): int {
		$country_feeds = $this->get_all_country_feed_ids();
		$deleted_count = 0;

		foreach ( $country_feeds as $country_code => $feed_id ) {
			if ( $this->delete_country_override_feed( $country_code ) ) {
				$deleted_count++;
			}
		}

		Logger::log(
			'Country override feeds cleanup completed',
			array(
				'event' => 'country_feed_cleanup',
				'event_type' => 'cleanup_all_country_feeds',
				'extra_data' => [
					'total_feeds' => count( $country_feeds ),
					'deleted_count' => $deleted_count,
				],
			),
			array(
				'should_send_log_to_meta'        => true,
				'should_save_log_in_woocommerce' => false,
				'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
			)
		);

		return $deleted_count;
	}
}
