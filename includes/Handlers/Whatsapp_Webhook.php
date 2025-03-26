<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Handlers;

defined( 'ABSPATH' ) || exit;

/**
 * The Whatsapp WebHook handler to receive POST request from Meta Hosted Connectbridge.
 *
 * @since 2.3.0
 */
class Whatsapp_Webhook {

	/**
	 * Constructs a new Whatsapp WebHook.
	 *
	 * @param \WC_Facebookcommerce $plugin Plugin instance.
	 *
	 * @since 2.3.0
	 */
	public function __construct( \WC_Facebookcommerce $plugin ) {
		add_action( 'rest_api_init', array( $this, 'init_whatsapp_webhook_endpoint' ) );
	}


	/**
	 * Register Whatsapp WebHook REST API endpoint
	 *
	 * @since 2.3.0
	 */
	public function init_whatsapp_webhook_endpoint() {
		register_rest_route(
			'wc-facebook/v1',
			'whatsapp_webhook',
			array(
				array(
					'methods'             => array( 'POST' ),
					'callback'            => array( $this, 'whatsapp_webhook_callback' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Updates Facebook settings options.
	 *
	 * @param array $settings Array of settings to update.
	 *
	 * @return void
	 * @internal
	 */
	private static function update_settings( $settings ) {
		foreach ( $settings as $key => $value ) {
			if ( ! empty( $key ) ) {
				update_option( $key, $value );
			}
		}
	}



	/**
	 * Whatsapp Webhook Listener
	 *
	 * @since 2.3.0
	 * @see Connection
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function whatsapp_webhook_callback( \WP_REST_Request $request ) {
		$request_params = $request->get_query_params();
		$waba_id        = $request_params['waba_id'];
		$access_token   = $request_params['access_token'];
		$business_id    = $request_params['business_id'];
		// TODO: Request authentication

		if ( empty( $waba_id ) || empty( $access_token ) || empty( $business_id ) ) {
			return new \WP_REST_Response( null, 204 );
		}

		wc_get_logger()->info( 'Whatsapp Account WebHook Event received' );
		wc_get_logger()->info( print_r( json_encode( $request_params ) ) );

		$options_setting_fields = array(
			'wc_facebook_wa_integration_waba_id'           => $waba_id,
			'wc_facebook_wa_integration_bisu_access_token' => $access_token,
			'wc_facebook_wa_integration_business_id'       => $business_id,
		);

		// update the params in the DB
		self::update_settings( $options_setting_fields );

		wc_get_logger()->info( 'Whatsapp Integration Setting Fields stored successfully' );
		wc_get_logger()->info( print_r( json_encode( $options_setting_fields ) ) );

		return new \WP_REST_Response( null, 200 );
	}
}
