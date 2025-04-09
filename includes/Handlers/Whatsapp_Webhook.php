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
	 * Authenticates Whatsapp Webhook using the SHA1 of the external business ID and BISU token
	 *
	 * @param string $auth_key the auth key received in the webhook
	 * @param string $bisu_token the BISU token received in the webhook
	 *
	 * @return bool
	 * @internal
	 */
	private static function authenticate_request( $auth_key, $bisu_token ) {
		$external_business_id = get_option( 'wc_facebook_external_business_id' );

		$expected_auth_key = 'sha1=' . (string) hash_hmac( 'sha1', $bisu_token, $external_business_id );

		return hash_equals( $expected_auth_key, $auth_key );
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
		$request_params           = $request->get_params();
		$waba_id                  = sanitize_text_field( $request_params['waba_id'] );
		$wacs_id                  = sanitize_text_field( $request_params['wacs_id'] );
		$is_waba_payment_setup    = sanitize_text_field( $request_params['is_waba_payment_setup'] );
		$waba_profile_picture_url = sanitize_text_field( $request_params['waba_profile_picture_url'] );
		$bisu_token               = sanitize_text_field( $request_params['client_bisu_token'] );
		$business_id              = sanitize_text_field( $request_params['client_business_id'] );
		$wacs_phone_number        = sanitize_text_field( $request_params['wacs_phone_number'] );
		$auth_key                 = sanitize_text_field( $request_params['auth_key'] );

		// authentication is done via auth_key using sha_1 hash mac of BISU token and external business ID stored in woo DB
		$authentication_result = self::authenticate_request( $auth_key, $bisu_token );

		if ( false === $authentication_result ) {
			wc_get_logger()->info(
				sprintf(
					__( 'Authentication Failure on received Whatsapp Webhook', 'facebook-for-woocommerce' ),
				)
			);
			return new \WP_REST_Response( null, 400 );
		}

		if ( empty( $waba_id ) || empty( $bisu_token ) || empty( $business_id ) || empty( $wacs_phone_number ) || empty( $wacs_id ) ) {
				wc_get_logger()->info(
					sprintf(
						__( 'All required onboarding info not received in Whatsapp Webhook', 'facebook-for-woocommerce' ),
					)
				);
			return new \WP_REST_Response( null, 400 );
		}

		wc_get_logger()->info(
			sprintf(
						/* translators: %s waba ID %s business ID */
				__( 'Whatsapp Account WebHook Event received. WABA ID: %1$s, Business ID: %2$s ', 'facebook-for-woocommerce' ),
				$waba_id,
				$business_id
			)
		);

		$options_setting_fields = array(
			'wc_facebook_wa_integration_waba_id'           => $waba_id,
			'wc_facebook_wa_integration_bisu_access_token' => $bisu_token,
			'wc_facebook_wa_integration_business_id'       => $business_id,
			'wc_facebook_wa_integration_wacs_phone_number' => $wacs_phone_number,
			'wc_facebook_wa_integration_is_payment_setup'  => $is_waba_payment_setup,
			'wc_facebook_wa_integration_wacs_id'           => $wacs_id,
			'wc_facebook_wa_integration_waba_profile_picture_url' => $waba_profile_picture_url,

		);

		$result = self::update_settings( $options_setting_fields );

		if ( false === $result ) {
			wc_get_logger()->info(
				sprintf(
						/* translators: %d $waba_id, %d $business_id. */
					__( 'Whatsapp Integration Setting Fields Update Failure waba_id: %1$s, business_id: %2$s', 'facebook-for-woocommerce' ),
					$waba_id,
					$business_id,
				)
			);

			return new \WP_REST_Response( null, 400 );

		}

		wc_get_logger()->info(
			sprintf(
						/* translators: %d $waba_id, %d $business_id. */
				__( 'Whatsapp Integration Setting Fields stored successfully in wp_options. wc_facebook_wa_integration_waba_id: %1$s, wc_facebook_wa_integration_business_id: %2$s ', 'facebook-for-woocommerce' ),
				$waba_id,
				$business_id,
			)
		);

		return new \WP_REST_Response( null, 200 );
	}
}
