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

use Exception;
use WooCommerce\Facebook\Framework\Logger;

/**
 * Direct sync handler for Language Override Feeds.
 *
 * This class provides an alternative to the URL-based feed approach
 * for local development environments where Facebook can't access the site URLs.
 * It sends language override data directly to Facebook's API instead of
 * relying on Facebook fetching from feed URLs.
 *
 * @since 3.6.0
 */
class LanguageOverrideFeedDirectSync {

	use LanguageFeedManagementTrait;

	/** @var \WooCommerce\Facebook\Feed\Localization\LanguageFeedData */
	private $language_feed_data;

	/** @var \WooCommerce\Facebook\API */
	private $api;

	/**
	 * Constructor
	 *
	 * @since 3.6.0
	 */
	public function __construct() {
		$this->language_feed_data = new LanguageFeedData();
	}

	/**
	 * Gets the API instance.
	 *
	 * @since 3.6.0
	 * @return \WooCommerce\Facebook\API
	 */
	private function get_api() {
		if ( ! $this->api ) {
			$this->api = facebook_for_woocommerce()->get_api();
		}
		return $this->api;
	}

	/**
	 * Sync language override feeds directly via API calls.
	 * This bypasses the URL-based feed approach and sends data directly to Facebook.
	 *
	 * @since 3.6.0
	 * @return array Results of the sync operation for each language
	 */
	public function sync_language_override_feeds_directly(): array {
		if ( ! $this->language_feed_data->has_active_localization_plugin() ) {
			Logger::log(
				'Direct language feed sync skipped: No active localization plugin found.',
				[],
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::INFO,
				)
			);
			return array( 'error' => 'No active localization plugin found.' );
		}

		$languages = $this->language_feed_data->get_available_languages();
		$results = array();

		foreach ( $languages as $language_code ) {
			$results[ $language_code ] = $this->sync_single_language_directly( $language_code );
		}

		return $results;
	}

	/**
	 * Sync a single language override feed directly via API.
	 *
	 * @param string $language_code Language code (e.g., 'es_ES', 'fr_FR')
	 * @since 3.6.0
	 * @return array Result of the sync operation
	 */
	private function sync_single_language_directly( string $language_code ): array {
		try {
			Logger::log(
				"Starting direct sync for language: {$language_code}",
				array( 'language_code' => $language_code ),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

			// Get CSV data for this language
			$csv_data = $this->language_feed_data->get_language_csv_data( $language_code, 100, 0 );

			if ( empty( $csv_data['data'] ) ) {
				return array(
					'success' => false,
					'error' => "No translated products found for language: {$language_code}",
					'count' => 0,
				);
			}

			// Send data directly to Facebook via batch API
			$batch_requests = $this->prepare_batch_requests( $csv_data['data'], $language_code );

			if ( empty( $batch_requests ) ) {
				return array(
					'success' => false,
					'error' => "No valid batch requests prepared for language: {$language_code}",
					'count' => 0,
				);
			}

			// Execute batch requests
			$batch_results = $this->execute_batch_requests( $batch_requests );

			Logger::log(
				"Direct sync completed for language: {$language_code}",
				array(
					'language_code' => $language_code,
					'products_count' => count( $csv_data['data'] ),
					'batch_requests_count' => count( $batch_requests ),
					'batch_results' => $batch_results,
				),
				array(
					'should_send_log_to_meta'        => true,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

			return array(
				'success' => true,
				'count' => count( $csv_data['data'] ),
				'batch_results' => $batch_results,
			);

		} catch ( Exception $exception ) {
			Logger::log(
				"Direct sync failed for language: {$language_code}",
				array(
					'language_code' => $language_code,
					'error' => $exception->getMessage(),
				),
				array(
					'should_send_log_to_meta'        => true,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
				),
				$exception
			);

			return array(
				'success' => false,
				'error' => $exception->getMessage(),
				'count' => 0,
			);
		}
	}

	/**
	 * Prepare batch requests for Facebook's Batch API.
	 * Converts CSV data into Facebook API batch request format.
	 *
	 * @param array $csv_data CSV data rows
	 * @param string $language_code Language code
	 * @since 3.6.0
	 * @return array Batch requests ready for Facebook API
	 */
	private function prepare_batch_requests( array $csv_data, string $language_code ): array {
		$batch_requests = array();
		$catalog_id = facebook_for_woocommerce()->get_integration()->get_product_catalog_id();

		if ( empty( $catalog_id ) ) {
			throw new Exception( 'No catalog ID found' );
		}

		foreach ( $csv_data as $index => $row ) {
			if ( empty( $row['id'] ) ) {
				continue;
			}

			// Prepare the product data for Facebook API
			$product_data = $this->prepare_product_data_for_api( $row );

			// Create batch request
			$batch_requests[] = array(
				'method' => 'POST',
				'relative_url' => "{$catalog_id}/products",
				'body' => http_build_query( $product_data ),
			);
		}

		return $batch_requests;
	}

	/**
	 * Convert CSV row data to Facebook API format.
	 *
	 * @param array $row CSV row data
	 * @since 3.6.0
	 * @return array Product data formatted for Facebook API
	 */
	private function prepare_product_data_for_api( array $row ): array {
		$product_data = array();

		// Required fields
		if ( ! empty( $row['id'] ) ) {
			$product_data['retailer_id'] = $row['id'];
		}

		if ( ! empty( $row['override'] ) ) {
			$product_data['override_type'] = 'language';
			$product_data['override_value'] = $row['override'];
		}

		// Optional fields - only include if they have values
		$field_mapping = array(
			'title' => 'name',
			'description' => 'description',
			'link' => 'url',
			'brand' => 'brand',
			'price' => 'price',
			'product_type' => 'product_type',
			'image_link' => 'image_url',
			'additional_image_link' => 'additional_image_urls',
		);

		foreach ( $field_mapping as $csv_field => $api_field ) {
			if ( ! empty( $row[ $csv_field ] ) ) {
				$product_data[ $api_field ] = $row[ $csv_field ];
			}
		}

		// Handle additional image URLs (comma-separated to array)
		if ( ! empty( $product_data['additional_image_urls'] ) ) {
			$product_data['additional_image_urls'] = explode( ',', $product_data['additional_image_urls'] );
		}

		return $product_data;
	}

	/**
	 * Execute language override sync using the localized_items_batch API.
	 * This mirrors the exact approach used by the regular Product Sync button.
	 *
	 * @param array $batch_requests Array of batch requests (not used in this approach)
	 * @since 3.6.0
	 * @return array Results from API execution
	 */
	private function execute_batch_requests( array $batch_requests ): array {
		$all_results = array();
		$success_count = 0;
		$error_count = 0;

		Logger::log(
			"Starting language override sync using localized_items_batch API (mirroring Product Sync approach)",
			array( 'total_requests' => count( $batch_requests ) ),
			array(
				'should_send_log_to_meta'        => false,
				'should_save_log_in_woocommerce' => true,
				'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
			)
		);

		try {
			$languages = $this->language_feed_data->get_available_languages();

			foreach ( $languages as $language_code ) {
				try {
					Logger::log(
						"Processing language override for: {$language_code}",
						array( 'language_code' => $language_code ),
						array(
							'should_send_log_to_meta'        => false,
							'should_save_log_in_woocommerce' => true,
							'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
						)
					);

					// Step 1: Get CSV data for this language (equivalent to getting product IDs in Sync.php)
					$csv_result = $this->language_feed_data->get_language_csv_data( $language_code, 100, 0 );

					if ( empty( $csv_result ) || ! isset( $csv_result['data'] ) || empty( $csv_result['data'] ) ) {
						throw new Exception( "No translated products found for language: {$language_code}" );
					}

					$product_count = count( $csv_result['data'] );
					Logger::log(
						"Generated CSV data for {$language_code}: {$product_count} products",
						array(
							'language_code' => $language_code,
							'product_count' => $product_count
						),
						array(
							'should_send_log_to_meta'        => false,
							'should_save_log_in_woocommerce' => true,
							'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
						)
					);

					// Step 2: Process items (equivalent to Background.php process_items)
					$localized_requests = $this->process_language_items( $csv_result['data'], $language_code );

					if ( empty( $localized_requests ) ) {
						throw new Exception( "No valid localized requests prepared for language: {$language_code}" );
					}

					// Step 3: Send to Facebook (equivalent to Background.php send_item_updates)
					$handles = $this->send_localized_item_updates( $localized_requests, $language_code );

					$success_count++;
					$all_results[] = array(
						'success' => true,
						'language_code' => $language_code,
						'product_count' => count( $csv_result['data'] ),
						'handles' => $handles,
					);

					Logger::log(
						"Successfully sent localized_items_batch for {$language_code}",
						array(
							'language_code' => $language_code,
							'product_count' => count( $csv_result['data'] ),
							'handles_count' => is_array( $handles ) ? count( $handles ) : 0
						),
						array(
							'should_send_log_to_meta'        => true,
							'should_save_log_in_woocommerce' => true,
							'woocommerce_log_level'          => \WC_Log_Levels::INFO,
						)
					);

				} catch ( Exception $lang_exception ) {
					$error_count++;
					$all_results[] = array(
						'success' => false,
						'language_code' => $language_code,
						'error' => $lang_exception->getMessage(),
					);

					Logger::log(
						"Language override processing failed for {$language_code}: " . $lang_exception->getMessage(),
						array(
							'language_code' => $language_code,
							'error' => $lang_exception->getMessage()
						),
						array(
							'should_send_log_to_meta'        => false,
							'should_save_log_in_woocommerce' => true,
							'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
						),
						$lang_exception
					);
				}
			}

		} catch ( Exception $exception ) {
			$error_count++;
			$all_results[] = array(
				'success' => false,
				'error' => $exception->getMessage(),
			);

			Logger::log(
				"Language override sync failed: " . $exception->getMessage(),
				array( 'error' => $exception->getMessage() ),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
				),
				$exception
			);
		}

		Logger::log(
			"Language override sync completed: {$success_count} successful, {$error_count} failed",
			array(
				'success_count' => $success_count,
				'error_count' => $error_count,
				'total_languages' => count( $this->language_feed_data->get_available_languages() ),
			),
			array(
				'should_send_log_to_meta'        => false,
				'should_save_log_in_woocommerce' => true,
				'woocommerce_log_level'          => \WC_Log_Levels::INFO,
			)
		);

		return $all_results;
	}

	/**
	 * Process language items (equivalent to Background.php process_items).
	 * Converts CSV data to API request format, mirroring how regular sync works.
	 *
	 * @param array  $csv_data CSV data rows
	 * @param string $language_code Language code
	 * @since 3.6.0
	 * @return array Localized batch requests
	 */
	private function process_language_items( array $csv_data, string $language_code ): array {
		$requests = array();

		// Convert language code to Facebook's override value format
		try {
			Logger::log(
				"Converting language code to Facebook override value: {$language_code}",
				array( 'language_code' => $language_code ),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

			$override_value = \WooCommerce\Facebook\Locale::convert_to_facebook_override_value( $language_code );

			Logger::log(
				"Successfully converted {$language_code} to Facebook override value: {$override_value}",
				array( 'language_code' => $language_code, 'override_value' => $override_value ),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);
		} catch ( \Exception $e ) {
			Logger::log(
				"Unsupported language code for Facebook: {$language_code}",
				array(
					'language_code' => $language_code,
					'error' => $e->getMessage(),
					'error_class' => get_class($e),
					'error_file' => $e->getFile(),
					'error_line' => $e->getLine()
				),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
				)
			);
			return array();
		}

		foreach ( $csv_data as $row ) {
			try {
				// Process each item (equivalent to Background.php process_item)
				$request = $this->process_localized_item_update( $row, $override_value );
				if ( $request ) {
					$requests[] = $request;
				}
			} catch ( Exception $e ) {
				Logger::log(
					"Error processing localized item for {$language_code}: " . $e->getMessage(),
					array( 'language_code' => $language_code, 'row_id' => $row['id'] ?? 'unknown' ),
					array(
						'should_send_log_to_meta'        => false,
						'should_save_log_in_woocommerce' => true,
						'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
					)
				);
			}
		}

		return $requests;
	}

	/**
	 * Process a single localized item update (equivalent to Background.php process_item_update).
	 * Mirrors the exact data preparation approach used by regular product sync.
	 *
	 * @param array  $row CSV row data
	 * @param string $override_value Facebook override value
	 * @since 3.6.0
	 * @return array|null Request data or null if invalid
	 */
	private function process_localized_item_update( array $row, string $override_value ): ?array {
		if ( empty( $row['id'] ) ) {
			return null;
		}

		// Prepare the localized product data (mirroring Background.php approach)
		$product_data = array();

		// Map CSV fields to Facebook API fields (similar to prepare_product_data_items_batch)
		$field_mapping = array(
			'title' => 'title',
			'description' => 'description',
			'link' => 'link',
			'brand' => 'brand',
			'product_type' => 'product_type',
			'image_link' => 'image_link',
			'additional_image_link' => 'additional_image_link',
			'price' => 'price',
			'sale_price' => 'sale_price',
			'availability' => 'availability',
			'condition' => 'condition',
		);

		foreach ( $field_mapping as $csv_field => $api_field ) {
			if ( ! empty( $row[ $csv_field ] ) ) {
				$product_data[ $api_field ] = $row[ $csv_field ];
			}
		}

		// Set the retailer ID (mirroring Background.php line 199: $product_data['id'] = $retailer_id)
		$product_data['id'] = $row['id'];

		// Create the localized batch request (mirroring Background.php request format + localization)
		$request = array(
			'method' => 'UPDATE', // Same as Sync::ACTION_UPDATE
			'data' => $product_data,
			'localization' => array(
				'type' => 'language',
				'value' => $override_value,
			),
		);

		/**
		 * Filters the data that will be included in a localized UPDATE sync request.
		 * Mirrors the filter in Background.php line 214.
		 *
		 * @since 3.6.0
		 *
		 * @param array $request request data
		 * @param array $row CSV row data
		 * @param string $override_value Facebook override value
		 */
		return apply_filters( 'wc_facebook_sync_localized_item_update_request', $request, $row, $override_value );
	}

	/**
	 * Send localized item updates to Facebook (equivalent to Background.php send_item_updates).
	 * Uses the same API approach as regular product sync.
	 *
	 * @param array  $requests Localized batch requests
	 * @param string $language_code Language code for logging
	 * @since 3.6.0
	 * @return array Array of handles
	 * @throws Exception If API request fails
	 */
	private function send_localized_item_updates( array $requests, string $language_code ): array {
		try {
			Logger::log(
				"Starting send_localized_item_updates for {$language_code}",
				array(
					'language_code' => $language_code,
					'requests_count' => count( $requests )
				),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

			// Get catalog ID (same as Background.php line 251)
			$facebook_catalog_id = facebook_for_woocommerce()->get_integration()->get_product_catalog_id();
			if ( empty( $facebook_catalog_id ) ) {
				throw new Exception( 'No catalog ID found' );
			}

			Logger::log(
				"Using catalog ID: {$facebook_catalog_id}",
				array(
					'language_code' => $language_code,
					'catalog_id' => $facebook_catalog_id
				),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

			// Check if API is available
			$api = facebook_for_woocommerce()->get_api();
			if ( ! $api ) {
				throw new Exception( 'Facebook API instance not available' );
			}

			Logger::log(
				"API instance available, checking send_item_updates method",
				array(
					'language_code' => $language_code,
					'api_class' => get_class( $api )
				),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

			// Check if the method exists
			if ( ! method_exists( $api, 'send_item_updates' ) ) {
				throw new Exception( 'send_item_updates method not available on API class: ' . get_class( $api ) );
			}

			Logger::log(
				"Calling send_item_updates API method",
				array(
					'language_code' => $language_code,
					'catalog_id' => $facebook_catalog_id,
					'requests_sample' => array_slice( $requests, 0, 2 ) // Log first 2 requests for debugging
				),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

			// Use the localized_items_batch endpoint for language overrides
			$response = $api->send_localized_item_updates( $facebook_catalog_id, $requests );

			Logger::log(
				"API call completed successfully",
				array(
					'language_code' => $language_code,
					'response_type' => gettype( $response ),
					'response_class' => is_object( $response ) ? get_class( $response ) : 'not_object'
				),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

			// Extract handles (same as Background.php lines 253-254)
			$response_handles = isset( $response->handles ) ? $response->handles : array();
			$handles = ( isset( $response_handles ) && is_array( $response_handles ) ) ? $response_handles : array();

			Logger::log(
				"Sent localized item updates for {$language_code}",
				array(
					'language_code' => $language_code,
					'requests_count' => count( $requests ),
					'handles_count' => count( $handles )
				),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

			return $handles;

		} catch ( Exception $e ) {
			Logger::log(
				"Error in send_localized_item_updates for {$language_code}: " . $e->getMessage(),
				array(
					'language_code' => $language_code,
					'error' => $e->getMessage(),
					'error_class' => get_class( $e ),
					'error_file' => $e->getFile(),
					'error_line' => $e->getLine(),
					'stack_trace' => $e->getTraceAsString()
				),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
				)
			);
			throw $e;
		}
	}

	/**
	 * Check if direct sync should be used instead of URL-based sync.
	 * This can be used to automatically detect local development environments.
	 *
	 * @since 3.6.0
	 * @return bool True if direct sync should be used
	 */
	public static function should_use_direct_sync(): bool {
		$site_url = home_url();

		// Check for common local development indicators
		$local_indicators = array(
			'localhost',
			'127.0.0.1',
			'.local',
			'.test',
			'.dev',
			'192.168.',
			'10.0.',
			'172.16.',
		);

		foreach ( $local_indicators as $indicator ) {
			if ( strpos( $site_url, $indicator ) !== false ) {
				return true;
			}
		}

		// Allow manual override via filter
		return apply_filters( 'wc_facebook_use_direct_language_feed_sync', false );
	}

	/**
	 * Get or create a language override feed ID using public API methods.
	 *
	 * @param string $language_code Language code
	 * @since 3.6.0
	 * @return string Feed ID
	 */
	private function get_or_create_language_feed_id( string $language_code ): string {
		// First, try to get a stored feed ID
		$language_feed = facebook_for_woocommerce()->get_language_override_feed();
		$stored_feed_id = $language_feed->get_stored_language_feed_id( $language_code );

		if ( ! empty( $stored_feed_id ) ) {
			Logger::log(
				"Using stored language feed ID for {$language_code}: {$stored_feed_id}",
				array( 'language_code' => $language_code, 'feed_id' => $stored_feed_id ),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);
			return $stored_feed_id;
		}

		// If no stored feed ID, we need to create one manually using the API
		try {
			$catalog_id = facebook_for_woocommerce()->get_integration()->get_product_catalog_id();
			if ( empty( $catalog_id ) ) {
				throw new Exception( 'No catalog ID found' );
			}

			// Convert language code to Facebook format
			$fb_language_code = \WooCommerce\Facebook\Feed\Localization\LanguageFeedData::convert_to_facebook_language_code( $language_code );
			$override_value = \WooCommerce\Facebook\Locale::convert_to_facebook_override_value( $fb_language_code );

			$feed_data = array(
				'name' => sprintf( '%s Language Override Feed (%s)', get_bloginfo( 'name' ), strtoupper( $fb_language_code ) ),
				'file_name' => sprintf( 'language_override_%s.csv', $fb_language_code ),
				'override_type' => 'language',
				'override_value' => $override_value,
			);

			Logger::log(
				"Creating new language override feed for {$language_code}",
				array(
					'language_code' => $language_code,
					'fb_language_code' => $fb_language_code,
					'override_value' => $override_value,
					'feed_data' => $feed_data
				),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

			$response = $this->get_api()->create_feed( $catalog_id, $feed_data );

			if ( $response && isset( $response['id'] ) ) {
				$feed_id = $response['id'];

				// Store the feed ID for future use
				$stored_feeds = get_option( 'wc_facebook_language_feed_ids', array() );
				$stored_feeds[ $language_code ] = $feed_id;
				update_option( 'wc_facebook_language_feed_ids', $stored_feeds );

				Logger::log(
					"Successfully created language override feed for {$language_code}: {$feed_id}",
					array( 'language_code' => $language_code, 'feed_id' => $feed_id ),
					array(
						'should_send_log_to_meta'        => false,
						'should_save_log_in_woocommerce' => true,
						'woocommerce_log_level'          => \WC_Log_Levels::INFO,
					)
				);

				return $feed_id;
			}

		} catch ( Exception $exception ) {
			Logger::log(
				"Failed to create language override feed for {$language_code}: " . $exception->getMessage(),
				array(
					'language_code' => $language_code,
					'error' => $exception->getMessage()
				),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
				),
				$exception
			);
		}

		return '';
	}


	/**
	 * Convert CSV data array to CSV string format.
	 *
	 * @param array $csv_data Array of CSV data rows
	 * @since 3.6.0
	 * @return string CSV content as string
	 */
	private function convert_csv_data_to_string( array $csv_data ): string {
		if ( empty( $csv_data ) ) {
			return '';
		}

		$output = fopen( 'php://temp', 'r+' );

		// Write header row (use keys from first data row)
		$header = array_keys( $csv_data[0] );
		fputcsv( $output, $header );

		// Write data rows
		foreach ( $csv_data as $row ) {
			fputcsv( $output, $row );
		}

		rewind( $output );
		$csv_string = stream_get_contents( $output );
		fclose( $output );

		return $csv_string;
	}

	/**
	 * Sync a single language feed with provided CSV data.
	 * This method is used by debug scripts and manual sync operations.
	 *
	 * @param string $language_code Language code (e.g., 'es_MX', 'fr_FR')
	 * @param string $csv_string CSV data as string
	 * @since 3.6.0
	 * @return bool True if sync was successful, false otherwise
	 */
	public function sync_language_feed( string $language_code, string $csv_string ): bool {
		try {
			Logger::log(
				"Starting single language feed sync for: {$language_code}",
				array(
					'language_code' => $language_code,
					'csv_length' => strlen( $csv_string )
				),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

			// Parse CSV string back to array format for processing
			$csv_data = $this->parse_csv_string_to_array( $csv_string );

			if ( empty( $csv_data ) ) {
				Logger::log(
					"No valid CSV data found for language: {$language_code}",
					array( 'language_code' => $language_code ),
					array(
						'should_send_log_to_meta'        => false,
						'should_save_log_in_woocommerce' => true,
						'woocommerce_log_level'          => \WC_Log_Levels::WARNING,
					)
				);
				return false;
			}

			// Process the language items
			$localized_requests = $this->process_language_items( $csv_data, $language_code );

			if ( empty( $localized_requests ) ) {
				Logger::log(
					"No valid localized requests prepared for language: {$language_code}",
					array( 'language_code' => $language_code ),
					array(
						'should_send_log_to_meta'        => false,
						'should_save_log_in_woocommerce' => true,
						'woocommerce_log_level'          => \WC_Log_Levels::WARNING,
					)
				);
				return false;
			}

			// Send to Facebook
			$handles = $this->send_localized_item_updates( $localized_requests, $language_code );

			Logger::log(
				"Successfully synced language feed for: {$language_code}",
				array(
					'language_code' => $language_code,
					'products_count' => count( $csv_data ),
					'requests_count' => count( $localized_requests ),
					'handles_count' => count( $handles )
				),
				array(
					'should_send_log_to_meta'        => true,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::INFO,
				)
			);

			return true;

		} catch ( Exception $exception ) {
			Logger::log(
				"Language feed sync failed for: {$language_code}",
				array(
					'language_code' => $language_code,
					'error' => $exception->getMessage(),
				),
				array(
					'should_send_log_to_meta'        => true,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
				),
				$exception
			);

			return false;
		}
	}

	/**
	 * Parse CSV string back to array format for processing.
	 * Handles Facebook CSV format with comment headers.
	 *
	 * @param string $csv_string CSV data as string
	 * @since 3.6.0
	 * @return array Array of CSV data rows
	 */
	private function parse_csv_string_to_array( string $csv_string ): array {
		if ( empty( $csv_string ) ) {
			return array();
		}

		$lines = explode( "\n", trim( $csv_string ) );
		if ( empty( $lines ) ) {
			return array();
		}

		// Skip comment lines (lines starting with #) and find the actual header
		$header = null;
		$data_start_index = 0;

		foreach ( $lines as $index => $line ) {
			$line = trim( $line );
			if ( empty( $line ) || strpos( $line, '#' ) === 0 ) {
				// Skip empty lines and comment lines
				continue;
			}

			// This should be our header row
			$header = str_getcsv( $line );
			$data_start_index = $index + 1;
			break;
		}

		if ( empty( $header ) ) {
			return array();
		}

		$data = array();
		for ( $i = $data_start_index; $i < count( $lines ); $i++ ) {
			$line = trim( $lines[ $i ] );
			if ( empty( $line ) || strpos( $line, '#' ) === 0 ) {
				continue;
			}

			$row_data = str_getcsv( $line );
			if ( count( $row_data ) === count( $header ) ) {
				$data[] = array_combine( $header, $row_data );
			}
		}

		return $data;
	}

	/**
	 * Get sync status for language override feeds.
	 *
	 * @since 3.6.0
	 * @return array Status information
	 */
	public function get_sync_status(): array {
		$languages = $this->language_feed_data->get_available_languages();
		$status = array(
			'has_localization_plugin' => $this->language_feed_data->has_active_localization_plugin(),
			'available_languages' => $languages,
			'should_use_direct_sync' => self::should_use_direct_sync(),
			'languages_status' => array(),
		);

		foreach ( $languages as $language_code ) {
			$csv_data = $this->language_feed_data->get_language_csv_data( $language_code, 5, 0 );
			$status['languages_status'][ $language_code ] = array(
				'language_code' => $language_code,
				'sample_products_count' => count( $csv_data['data'] ?? array() ),
				'has_translated_content' => ! empty( $csv_data['data'] ),
			);
		}

		return $status;
	}
}
