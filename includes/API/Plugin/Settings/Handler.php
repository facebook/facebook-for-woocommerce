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
	public function handle_update( \WP_REST_Request $wp_request ) {
		try {
			$request           = new UpdateRequest( $wp_request );
			$validation_result = $request->validate();

			if ( is_wp_error( $validation_result ) ) {
				return $this->error_response(
					$validation_result->get_error_message(),
					400
				);
			}

			// Map parameters to options and update settings
			$options = $this->map_params_to_options( $request->get_data() );
			$this->update_settings( $options );

			// Update connection status flags
			$this->update_connection_status( $request->get_data() );

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
	public function handle_uninstall( \WP_REST_Request $wp_request ) {
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
	private function map_params_to_options( $params ) {
		$options = [];

		// Map access tokens
		if ( ! empty( $params['access_token'] ) ) {
			$options[\WC_Facebookcommerce_Integration::OPTION_ACCESS_TOKEN] = $params['access_token'];
		}

		if ( ! empty( $params['commerce_merchant_settings_id'] ) ) {
			$options[\WC_Facebookcommerce_Integration::OPTION_COMMERCE_MERCHANT_SETTINGS_ID] = $params['commerce_merchant_settings_id'];
		}

		if ( ! empty( $params['commerce_partner_integration_id'] ) ) {
			$options[\WC_Facebookcommerce_Integration::OPTION_COMMERCE_PARTNER_INTEGRATION_ID] = $params['commerce_partner_integration_id'];
		}

		if ( ! empty( $params['installed_features'] ) ) {
			$options[\WC_Facebookcommerce_Integration::OPTION_INSTALLED_FEATURES] = $params['installed_features'];
		}

		if ( ! empty( $params['merchant_access_token'] ) ) {
			$options[\WC_Facebookcommerce_Integration::OPTION_MERCHANT_ACCESS_TOKEN] = $params['merchant_access_token'];
		}

		if ( ! empty( $params['page_access_token'] ) ) {
			$options[\WC_Facebookcommerce_Integration::OPTION_PAGE_ACCESS_TOKEN] = $params['page_access_token'];
		}

		if ( ! empty( $params['page_id'] ) ) {
			update_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PAGE_ID, $params['page_id'] );
		}

		if ( ! empty( $params['pixel_id'] ) ) {
			$options[\WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PIXEL_ID] = $params['pixel_id'];
			update_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PIXEL_ID, $params['pixel_id'] );
		}

		if ( ! empty( $params['product_catalog_id'] ) ) {
			$options[\WC_Facebookcommerce_Integration::OPTION_PRODUCT_CATALOG_ID] = $params['product_catalog_id'];
		}

		if ( ! empty( $params['profiles'] ) ) {
			$options[\WC_Facebookcommerce_Integration::OPTION_PROFILES] = $params['profiles'];
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
	private function update_settings( $settings ) {
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
	private function update_connection_status( $params ) {
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
			\WC_Facebookcommerce_Integration::OPTION_COMMERCE_MERCHANT_SETTINGS_ID,
			\WC_Facebookcommerce_Integration::OPTION_COMMERCE_PARTNER_INTEGRATION_ID,
			\WC_Facebookcommerce_Integration::OPTION_ENABLE_MESSENGER,
			\WC_Facebookcommerce_Integration::OPTION_HAS_AUTHORIZED_PAGES_READ_ENGAGEMENT,
			\WC_Facebookcommerce_Integration::OPTION_HAS_CONNECTED_FBE_2,
			\WC_Facebookcommerce_Integration::OPTION_INSTALLED_FEATURES,
			\WC_Facebookcommerce_Integration::OPTION_MERCHANT_ACCESS_TOKEN,
			\WC_Facebookcommerce_Integration::OPTION_PAGE_ACCESS_TOKEN,
			\WC_Facebookcommerce_Integration::OPTION_PRODUCT_CATALOG_ID,
			\WC_Facebookcommerce_Integration::OPTION_PROFILES,
			\WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PAGE_ID,
			\WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PIXEL_ID,
		];

		foreach ( $options as $option ) {
			delete_option( $option );
		}
	}
}
