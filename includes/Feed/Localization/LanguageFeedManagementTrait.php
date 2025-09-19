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
 * Language Feed Management Trait
 *
 * Consolidates common functionality for language override feed management
 * including API instance management, feed ID storage/retrieval, and upload status.
 * This eliminates duplication across multiple language feed classes.
 *
 * @since 3.6.0
 */
trait LanguageFeedManagementTrait {

	/** @var \WooCommerce\Facebook\API */
	private $api;

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
	 * Store the Facebook feed ID for a language.
	 *
	 * @param string $language_code Language code
	 * @param string $feed_id Facebook feed ID
	 * @since 3.6.0
	 */
	private function store_language_feed_id( string $language_code, string $feed_id ): void {
		$stored_feeds = get_option( 'wc_facebook_language_feed_ids', [] );
		$stored_feeds[ $language_code ] = $feed_id;
		update_option( 'wc_facebook_language_feed_ids', $stored_feeds );
	}

	/**
	 * Get stored language feed ID.
	 *
	 * @param string $language_code Language code
	 * @return string|null Feed ID or null if not found
	 * @since 3.6.0
	 */
	public function get_stored_language_feed_id( string $language_code ): ?string {
		$stored_feeds = get_option( 'wc_facebook_language_feed_ids', [] );
		return $stored_feeds[ $language_code ] ?? null;
	}

	/**
	 * Check the upload status of language override feeds.
	 *
	 * @return array Status information for all language feeds
	 * @since 3.6.0
	 */
	public function get_upload_status(): array {
		if ( ! isset( $this->language_feed_data ) ) {
			return [];
		}

		$languages = $this->language_feed_data->get_available_languages();
		$status = [];

		foreach ( $languages as $language_code ) {
			$feed_id = $this->get_stored_language_feed_id( $language_code );

			if ( $feed_id ) {
				try {
					$response = $this->get_api()->read_feed( $feed_id );
					$status[ $language_code ] = [
						'feed_id' => $feed_id,
						'status'  => 'active',
						'data'    => $response->response_data ?? [],
					];
				} catch ( \Exception $exception ) {
					$status[ $language_code ] = [
						'feed_id' => $feed_id,
						'status'  => 'error',
						'error'   => $exception->getMessage(),
					];
				}
			} else {
				$status[ $language_code ] = [
					'feed_id' => null,
					'status'  => 'not_created',
				];
			}
		}

		return $status;
	}

	/**
	 * Retrieves or creates a language override feed ID.
	 *
	 * @param string $language_code Language code
	 * @return string Feed ID
	 * @since 3.6.0
	 */
	public function retrieve_or_create_language_feed_id( string $language_code ): string {
		// Attempt 1. Request feeds data from Meta and filter the right one
		$feed_id = $this->request_and_filter_language_feed_id( $language_code );
		if ( $feed_id ) {
			$this->store_language_feed_id( $language_code, $feed_id );
			Logger::log(
				'Language Feed: feed_id = ' . $feed_id . ', queried and selected from Meta API.',
				[],
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);
			return $feed_id;
		}

		// Attempt 2. Create a new feed
		$feed_id = $this->create_language_feed_id( $language_code );
		if ( $feed_id ) {
			$this->store_language_feed_id( $language_code, $feed_id );
			Logger::log(
				'Language Feed: feed_id = ' . $feed_id . ', created a new feed via Meta API.',
				[],
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);
			return $feed_id;
		}

		return '';
	}

	/**
	 * Queries existing feeds for the integration catalog and filters
	 * the language override feed ID for a specific language.
	 *
	 * @param string $language_code Language code
	 * @return string Feed ID
	 * @since 3.6.0
	 */
	private function request_and_filter_language_feed_id( string $language_code ): string {
		try {
			$catalog_id = facebook_for_woocommerce()->get_integration()->get_product_catalog_id();
			if ( '' === $catalog_id ) {
				throw new \Exception( 'No catalog ID' );
			}
			$feed_nodes = $this->get_api()->read_feeds( $catalog_id )->data;
		} catch ( \Exception $e ) {
			$message = sprintf( 'There was an error trying to get feed nodes for catalog: %s', $e->getMessage() );
			Logger::log(
				$message,
				[],
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
				)
			);
			return '';
		}

		if ( empty( $feed_nodes ) ) {
			return '';
		}

		$fb_language_code = \WooCommerce\Facebook\Locale::convert_to_facebook_language_code( $language_code );
		$expected_feed_name = sprintf( '%s Language Override Feed (%s)', get_bloginfo( 'name' ), strtoupper( $fb_language_code ) );

		foreach ( $feed_nodes as $feed ) {
			try {
				$feed_metadata = $this->get_api()->read_feed( $feed['id'] );
			} catch ( \Exception $e ) {
				$message = sprintf( 'There was an error trying to get feed metadata: %s', $e->getMessage() );
				Logger::log(
					$message,
					[],
					array(
						'should_send_log_to_meta'        => false,
						'should_save_log_in_woocommerce' => true,
						'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
					)
				);
				continue;
			}

			if ( $expected_feed_name === $feed_metadata['name'] ) {
				return $feed['id'];
			}
		}

		return '';
	}

	/**
	 * Creates a new language override feed on Facebook.
	 *
	 * @param string $language_code Language code
	 * @return string Feed ID
	 * @since 3.6.0
	 */
	private function create_language_feed_id( string $language_code ): string {
		try {
			$catalog_id = facebook_for_woocommerce()->get_integration()->get_product_catalog_id();
			if ( '' === $catalog_id ) {
				throw new \Exception( 'No catalog ID' );
			}

			$fb_language_code = \WooCommerce\Facebook\Locale::convert_to_facebook_language_code( $language_code );
			$override_value = \WooCommerce\Facebook\Locale::convert_to_facebook_override_value( $fb_language_code );

			$feed_data = [
				'name' => sprintf( '%s Language Override Feed (%s)', get_bloginfo( 'name' ), strtoupper( $fb_language_code ) ),
				'file_name' => sprintf( 'language_override_%s.csv', $fb_language_code ),
				'override_type' => 'language',
				'override_value' => $override_value,
			];

			$response = $this->get_api()->create_feed( $catalog_id, $feed_data );

			if ( $response && isset( $response['id'] ) ) {
				return $response['id'];
			}

		} catch ( \Exception $exception ) {
			Logger::log(
				'Could not create language override feed: ' . $exception->getMessage(),
				array(
					'language_code' => $language_code,
				),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
				)
			);
		}

		return '';
	}

	/**
	 * Get the feed secret.
	 * Uses the same secret as the main product feed for consistency.
	 *
	 * @return string
	 * @since 3.6.0
	 */
	protected function get_feed_secret(): string {
		return \WooCommerce\Facebook\Products\Feed::get_feed_secret();
	}
}
