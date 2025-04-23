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

use WP_Error;

/**
 * Handles WhatsApp Utility GET and POST APIs Graph API requests.
 *
 * @since 2.3.0
 */
class WhatsAppUtilityConnection {

	/** @var string API version */
	const API_VERSION = 'v22.0';

	/** @var string Graph API base URL */
	const GRAPH_API_BASE_URL = 'https://graph.facebook.com';


	/**
	 * Makes an API call to Whatsapp Utility Message Connect API
	 *
	 * @param string $waba_id, string $wacs_id, string $external_business_id, string $bisu_token
	 */
	public static function wc_facebook_whatsapp_connect_utility_messages_call( $waba_id, $wacs_id, $external_business_id, $bisu_token ) {
		$base_url     = array( self::GRAPH_API_BASE_URL, self::API_VERSION, $waba_id, 'connect_utility_messages' );
		$base_url     = esc_url( implode( '/', $base_url ) );
		$query_params = array(
			'external_integration_id' => $external_business_id,
			'wacs_id'                 => $wacs_id,
			'access_token'            => $bisu_token,
		);
		$base_url     = add_query_arg( $query_params, $base_url );
		$options      = array(
			'headers' => array(
				'Authorization' => $bisu_token,
			),
			'body'    => array(),
		);
		$response     = wp_remote_post( $base_url, $options );
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 ) {
			$error_data    = explode( "\n", wp_remote_retrieve_body( $response ) );
			$error_message = $error_data[0];
			wc_get_logger()->info(
				sprintf(
					/* translators: %s $error_message */
					__( 'Finish Onboarding Button Click Failure %1$s ', 'facebook-for-woocommerce' ),
					$error_message,
				)
			);
			wp_send_json_error( $response, 'Finish Onboarding Failure' );
		} else {
				wc_get_logger()->info(
					sprintf(
						__( 'Finish Onboarding Button Click Success!!!', 'facebook-for-woocommerce' )
					)
				);
			wp_send_json_success( $response, 'Finish Onboarding Success' );
		}
	}
}
