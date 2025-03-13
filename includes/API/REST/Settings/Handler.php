<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\API\REST\Settings;

use WooCommerce\Facebook\API\REST\AbstractRESTEndpoint;
use WooCommerce\Facebook\API\REST\Settings\Update\Request as UpdateRequest;
use WooCommerce\Facebook\API\REST\Settings\Uninstall\Request as UninstallRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Settings REST API endpoint handler.
 *
 * @since 2.3.5
 */
class Handler extends AbstractRESTEndpoint {

	/**
	 * Register routes for this endpoint.
	 *
	 * @since 2.3.5
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
	 * @since 2.3.5
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
	 * @since 2.3.5
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
	 * @since 2.3.5
	 *
	 * @param array $params Request parameters.
	 * @return array Mapped options.
	 */
	private function map_params_to_options( $params ) {
		$options = [];

		// Map access tokens
		if ( ! empty( $params['access_token'] ) ) {
			$options['wc_facebook_access_token'] = $params['access_token'];
		}

		if ( ! empty( $params['merchant_access_token'] ) ) {
			$options['wc_facebook_merchant_access_token'] = $params['merchant_access_token'];
		}

		if ( ! empty( $params['page_access_token'] ) ) {
			$options['wc_facebook_page_access_token'] = $params['page_access_token'];
		}

		// Map IDs
		if ( ! empty( $params['product_catalog_id'] ) ) {
			$options['wc_facebook_catalog_id'] = $params['product_catalog_id'];
		}

		if ( ! empty( $params['pixel_id'] ) ) {
			$options['wc_facebook_pixel_id'] = $params['pixel_id'];
			update_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PIXEL_ID, $params['pixel_id'] );
		}

		if ( ! empty( $params['page_id'] ) ) {
			update_option( \WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PAGE_ID, $params['page_id'] );
		}

		if ( ! empty( $params['commerce_partner_integration_id'] ) ) {
			$options['wc_facebook_commerce_partner_integration_id'] = $params['commerce_partner_integration_id'];
		}

		// Map profiles and features
		if ( ! empty( $params['profiles'] ) ) {
			$options['wc_facebook_profiles'] = $params['profiles'];
		}

		if ( ! empty( $params['installed_features'] ) ) {
			$options['wc_facebook_installed_features'] = $params['installed_features'];
		}

		return $options;
	}

	/**
	 * Updates Facebook settings options.
	 *
	 * @since 2.3.5
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
	 * @since 2.3.5
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
	 * @since 2.3.5
	 *
	 * @return void
	 */
	private function clear_integration_options() {
		$options = [
			'wc_facebook_access_token',
			'wc_facebook_merchant_access_token',
			'wc_facebook_page_access_token',
			'wc_facebook_catalog_id',
			'wc_facebook_pixel_id',
			'wc_facebook_commerce_partner_integration_id',
			'wc_facebook_profiles',
			'wc_facebook_installed_features',
			'wc_facebook_has_connected_fbe_2',
			'wc_facebook_has_authorized_pages_read_engagement',
			'wc_facebook_enable_messenger',
			\WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PAGE_ID,
			\WC_Facebookcommerce_Integration::SETTING_FACEBOOK_PIXEL_ID,
		];

		foreach ( $options as $option ) {
			delete_option( $option );
		}
	}
}
