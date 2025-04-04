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
	 * @return bool
	 * @internal
	 */
	private static function update_settings( $settings ) {
		$updated = array();
		foreach ( $settings as $key => $value ) {
			if ( ! empty( $key ) ) {
				$updated[ $key ] = update_option( $key, $value );
			}
		}
		// if any of setting updates failed, return false
		return ! in_array( false, $updated, true );
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
		$waba_id        = sanitize_text_field( $request_params['waba_id'] );
		$access_token   = sanitize_text_field( $request_params['access_token'] );
		$business_id    = sanitize_text_field( $request_params['business_id'] );
		// TODO: Request authentication

		if ( empty( $waba_id ) || empty( $access_token ) || empty( $business_id ) ) {
			return new \WP_REST_Response( null, 400 );
		}

		wc_get_logger()->info(
			sprintf(
						/* translators: %d user ID. */
				__( 'Whatsapp Account WebHook Event received. WABA ID: %1$s, Business ID: %2$s ', 'facebook-for-woocommerce' ),
				$waba_id,
				$business_id
			)
		);

		$options_setting_fields = array(
			'wc_facebook_wa_integration_waba_id'           => $waba_id,
			'wc_facebook_wa_integration_bisu_access_token' => $access_token,
			'wc_facebook_wa_integration_business_id'       => $business_id,
		);

		$result = self::update_settings( $options_setting_fields );

		// TODO: add validation for wp_options table update
		wc_get_logger()->info(
			sprintf(
						/* translators: %d $waba_id, %d $business_id. */
				__( 'Whatsapp Integration Setting Fields stored successfully in wp_options. wc_facebook_wa_integration_waba_id: %1$s, wc_facebook_wa_integration_business_id: %2$s ', 'facebook-for-woocommerce' ),
				$waba_id,
				$business_id
			)
		);

		return new \WP_REST_Response( null, 200 );
	}
}
