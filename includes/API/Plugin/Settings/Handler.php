<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\API\Plugin\Settings;

use WooCommerce\Facebook\API\Plugin\AbstractRESTEndpoint;
use WooCommerce\Facebook\API\Plugin\Settings\Update\Request as UpdateRequest;
use WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request as UninstallRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Settings REST API endpoint handler.
 *
 * @since 3.5.0
 */
class Handler extends AbstractRESTEndpoint {

	/**
	 * Register routes for this endpoint.
	 *
	 * @since 3.5.0
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->get_namespace(),
			'/settings/update',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_update' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);

		register_rest_route(
			$this->get_namespace(),
			'/settings/uninstall',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_uninstall' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);
	}

	/**
	 * Handle the update settings request.
	 *
	 * @since 3.5.0
	 * @http_method POST
	 * @description Update Facebook settings
	 *
	 * @param \WP_REST_Request $wp_request The WordPress request object.
	 * @return \WP_REST_Response
	 */
	public function handle_update( \WP_REST_Request $wp_request ): \WP_REST_Response {
		try {
			$request           = new UpdateRequest( $wp_request );
			$request_data      = $request->get_data();
			$validation_result = $request->validate();

			if ( is_wp_error( $validation_result ) ) {
				return $this->error_response(
					$validation_result->get_error_message(),
					400
				);
			}

			// Check if we should trigger product sync and/or metadata feed uploads for this update
			// Only trigger products and sets sync if catalog id is being updated
			$should_trigger_products_and_sets_sync = ! empty( $request_data['product_catalog_id'] ) && facebook_for_woocommerce()->get_integration()->get_product_catalog_id() !== $request_data['product_catalog_id'];
			// Only trigger metadata feed uploads if CPI id is being updated
			$should_trigger_metadata_feed_uploads = ! empty( $request_data['commerce_partner_integration_id'] ) && facebook_for_woocommerce()->get_connection_handler()->get_commerce_partner_integration_id() !== $request_data['commerce_partner_integration_id'];

			// Map parameters to options and update settings
			$options = $this->map_params_to_options( $request_data );
			$this->update_settings( $options );

			// Update connection status flags
			$this->update_connection_status( $request_data );

			// Maybe trigger products sync and/or metadata feed uploads
			$this->maybe_trigger_feed_uploads( $should_trigger_products_and_sets_sync, $should_trigger_metadata_feed_uploads, $request_data );

			return $this->success_response(
				[
					'message' => __( 'Facebook settings updated successfully', 'facebook-for-woocommerce' ),
				]
			);
		} catch ( \Exception $e ) {
			return $this->error_response(
				$e->getMessage(),
				500
			);
		}
	}

	/**
	 * Handle the uninstall request.
	 *
	 * @since 3.5.0
	 * @http_method POST
	 * @description Uninstall Facebook integration
	 *
	 * @param \WP_REST_Request $wp_request The WordPress request object.
	 * @return \WP_REST_Response
	 */
	public function handle_uninstall( \WP_REST_Request $wp_request ): \WP_REST_Response {
		try {
			$request           = new UninstallRequest( $wp_request );
			$validation_result = $request->validate();

			if ( is_wp_error( $validation_result ) ) {
				return $this->error_response(
					$validation_result->get_error_message(),
					400
				);
			}

			// Clear integration options
			$this->clear_integration_options();

			return $this->success_response(
				[
					'message' => __( 'Facebook integration successfully uninstalled', 'facebook-for-woocommerce' ),
				]
			);
		} catch ( \Exception $e ) {
			return $this->error_response(
				$e->getMessage(),
				500
			);
		}
	}

	/**
	 * Maps request parameters to WooCommerce options.
	 *
	 * @since 3.5.0
	 *
	 * @param array $params Request parameters.
	 * @return array Mapped options.
	 */
	private function map_params_to_options( array $params ): array {
		$options = [];

		// Map access tokens
		if ( ! empty( $params['access_token'] ) ) {
			$options[ \WC_Facebookcommerce_Integration::OPTION_ACCESS_TOKEN ] = $params['access_token'];
		}

		if ( ! empty( $params['commerce_merchant_settings_id'] ) ) {
			$options[ \WC_Facebookcommerce_Integration::OPTION_COMMERCE_MERCHANT_SETTINGS_ID ] = $params['commerce_merchant_settings_id'];
		}

		if ( ! empty( $params['commerce_partner_integration_id'] ) ) {
			$options[ \WC_Facebookcommerce_Integration::OPTION_COMMERCE_PARTNER_INTEGRATION_ID ] = $params['commerce_partner_integration_id'];
		}

		if ( ! empty( $params['installed_features'] ) ) {
			$options[ \WC_Facebookcommerce_Integration::OPTION_INSTALLED_FEATURES ] = $params['installed_features'];
		}

		if ( ! empty( $params['merchant_access_token'] ) ) {
			$options[ \WC_Facebookcommerce_Integration::OPTION_MERCHANT_ACCESS_TOKEN ] = $params['merchant_access_token'];
		}

		if ( ! empty( $params['page_id'] ) ) {
			update_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PAGE_ID, $params['page_id'] );
		}

		if ( ! empty( $params['pixel_id'] ) ) {
			$options[ \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PIXEL_ID ] = $params['pixel_id'];
			update_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PIXEL_ID, $params['pixel_id'] );
		}

		if ( ! empty( $params['product_catalog_id'] ) ) {
			$options[ \WC_Facebookcommerce_Integration::OPTION_PRODUCT_CATALOG_ID ] = $params['product_catalog_id'];
		}

		if ( ! empty( $params['profiles'] ) ) {
			$options[ \WC_Facebookcommerce_Integration::OPTION_PROFILES ] = $params['profiles'];
		}

		if ( ! empty( $params['business_manager_id'] ) ) {
			$options[ \WC_Facebookcommerce_Integration::OPTION_BUSINESS_MANAGER_ID ] = $params['business_manager_id'];
		}

		return $options;
	}

	/**
	 * Updates Facebook settings options.
	 *
	 * @since 3.5.0
	 *
	 * @param array $settings Array of settings to update.
	 * @return void
	 */
	private function update_settings( array $settings ) {
		foreach ( $settings as $key => $value ) {
			if ( ! empty( $key ) ) {
				update_option( $key, $value );
			}
		}
	}

	/**
	 * Updates connection status flags.
	 *
	 * @since 3.5.0
	 *
	 * @param array $params Request parameters.
	 * @return void
	 */
	private function update_connection_status( array $params ) {
		// Set the connection is complete
		update_option( 'wc_facebook_has_connected_fbe_2', 'yes' );
		update_option( 'wc_facebook_has_authorized_pages_read_engagement', 'yes' );

		// Set the Messenger chat visibility
		if ( ! empty( $params['msger_chat'] ) ) {
			update_option( 'wc_facebook_enable_messenger', wc_bool_to_string( 'yes' === $params['msger_chat'] ) );
		}
	}

	/**
	 * Clears all integration options.
	 *
	 * @since 3.5.0
	 *
	 * @return void
	 */
	private function clear_integration_options() {
		$options = [
			\WC_Facebookcommerce_Integration::OPTION_ACCESS_TOKEN,
			\WC_Facebookcommerce_Integration::OPTION_BUSINESS_MANAGER_ID,
			\WC_Facebookcommerce_Integration::OPTION_AD_ACCOUNT_ID,
			\WC_Facebookcommerce_Integration::OPTION_SYSTEM_USER_ID,
			\WC_Facebookcommerce_Integration::OPTION_FEED_ID,
			\WC_Facebookcommerce_Integration::OPTION_COMMERCE_MERCHANT_SETTINGS_ID,
			\WC_Facebookcommerce_Integration::OPTION_COMMERCE_PARTNER_INTEGRATION_ID,
			\WC_Facebookcommerce_Integration::OPTION_ENABLE_MESSENGER,
			\WC_Facebookcommerce_Integration::OPTION_HAS_AUTHORIZED_PAGES_READ_ENGAGEMENT,
			\WC_Facebookcommerce_Integration::OPTION_HAS_CONNECTED_FBE_2,
			\WC_Facebookcommerce_Integration::OPTION_INSTALLED_FEATURES,
			\WC_Facebookcommerce_Integration::OPTION_MERCHANT_ACCESS_TOKEN,
			\WC_Facebookcommerce_Integration::OPTION_PRODUCT_CATALOG_ID,
			\WC_Facebookcommerce_Integration::OPTION_PROFILES,
			\WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PAGE_ID,
			\WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PIXEL_ID,
		];

		foreach ( $options as $option ) {
			delete_option( $option );
		}
	}

	/**
	 * Triggers products sync if catalog id is being set to a different value.
	 * Triggers metadata feed uploads if CPI id is being set to a different value.
	 *
	 * @since 3.5.0
	 *
	 * @param bool  $should_trigger_products_and_sets_sync
	 * @param bool  $should_trigger_metadata_feed_uploads
	 * @param array $params
	 * @return void
	 */
	private function maybe_trigger_feed_uploads( bool $should_trigger_products_and_sets_sync, bool $should_trigger_metadata_feed_uploads, array $params ) {
		try {
			if ( $should_trigger_products_and_sets_sync ) {
				// Allow opt-out of full batch-API sync, for example if store has a large number of products.
				if ( facebook_for_woocommerce()->get_integration()->allow_full_batch_api_sync() ) {
					facebook_for_woocommerce()->get_products_sync_handler()->create_or_update_all_products();
				} else {
					\WC_Facebookcommerce_Utils::log_to_meta(
						'Initial full product sync disabled by filter hook `facebook_for_woocommerce_allow_full_batch_api_sync`',
						[
							'flow_name' => 'product_sync',
							'flow_step' => 'initial_sync',
						]
					);
				}
			}
		} catch ( \Exception $exception ) {
			\WC_Facebookcommerce_Utils::log_exception_immediately_to_meta(
				$exception,
				[
					'event'      => 'product_sync',
					'event_type' => 'sync_products_after_settings_update',
					'extra_data' => [
						'params' => wp_json_encode( $params ),
					],
				]
			);
		}

		try {
			if ( $should_trigger_products_and_sets_sync ) {
				facebook_for_woocommerce()->get_product_sets_sync_handler()->sync_all_product_sets();
			}
		} catch ( \Exception $exception ) {
			\WC_Facebookcommerce_Utils::log_exception_immediately_to_meta(
				$exception,
				[
					'event'      => 'product_sets_sync',
					'event_type' => 'sync_product_sets_after_settings_update',
					'extra_data' => [
						'params' => wp_json_encode( $params ),
					],
				]
			);
		}

		try {
			if ( $should_trigger_metadata_feed_uploads ) {
				facebook_for_woocommerce()->feed_manager->run_all_feed_uploads();
			}
		} catch ( \Exception $exception ) {
			\WC_Facebookcommerce_Utils::log_exception_immediately_to_meta(
				$exception,
				[
					'event'      => 'feed_upload',
					'event_type' => 'trigger_feed_uploads_after_settings_update',
					'extra_data' => [
						'params' => wp_json_encode( $params ),
					],
				]
			);
		}
	}
}
