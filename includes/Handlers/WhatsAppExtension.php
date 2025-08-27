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
 * Handles Meta WhatsApp Utility Extension functionality and configuration.
 *
 * @since 3.5.0
 */
class WhatsAppExtension {



	/** @var string Commerce Hub base URL */
	const COMMERCE_HUB_URL = 'https://www.commercepartnerhub.com/';
	/** @var string Client token */
	const CLIENT_TOKEN = '474166926521348|92e978eb27baf47f9df578b48d430a2e';
	/** @var string Whatsapp Integration app ID */
	const APP_ID = '474166926521348';
	/** @var string Whatsapp Tech Provider Business ID */
	const TP_BUSINESS_ID = '1145282100241487';
	/** @var string base url for meta stefi endpoint */
	const BASE_STEFI_ENDPOINT_URL = 'https://api.facebook.com';
	/** @var string Default language for Library Template */
	const DEFAULT_LANGUAGE = 'en';


	// ==========================
	// = IFrame Management      =
	// ==========================

	/**
	 * Generates the Commerce Hub whatsapp iframe splash page URL.
	 *
	 * @param object $plugin The plugin instance.
	 * @param string $external_wa_id External business ID.
	 *
	 * @return string
	 * @since 3.5.0
	 */
	public static function generate_wa_iframe_splash_url( $plugin, $external_wa_id ): string {
		$whatsapp_connection = $plugin->get_whatsapp_connection_handler();

		return add_query_arg(
			array(
				'access_client_token'  => self::CLIENT_TOKEN,
				'app_id'               => self::APP_ID,
				'tp_business_id'       => self::TP_BUSINESS_ID,
				'external_business_id' => $external_wa_id,
			),
			self::COMMERCE_HUB_URL . 'whatsapp_utility_integration/splash/'
		);
	}

	/**
	 * Generates the Commerce Hub whatsApp iframe management page URL.
	 *
	 * @return string
	 * @since 3.5.0
	 */
	public static function generate_wa_iframe_management_url( $plugin ) {
		$whatsapp_connection = $plugin->get_whatsapp_connection_handler();
		$is_connected       = $whatsapp_connection->is_connected();
		wc_get_logger()->info(
			sprintf(
					/* translators: %s $response */
				__( 'WA Installation Response: %1$s ', 'facebook-for-woocommerce' ),
				(string)$is_connected,
			)
		);
		if( ! $is_connected ) {
			return '';
		}
		$bisu_token = $whatsapp_connection->get_access_token();
		$params  = array(
			'locale'         => get_user_locale() ?? self::DEFAULT_LANGUAGE,
			'access_token' => $bisu_token,
		);
		$wa_installation_id = $whatsapp_connection->get_wa_installation_id();
		$base_url     = array( self::BASE_STEFI_ENDPOINT_URL, 'whatsapp/business', '24013123858389116', 'utility_message_iframe_management_uri' );
		$base_url     = esc_url( implode( '/', $base_url ) );
		$url     = add_query_arg( $params, $base_url );
		$options = array(
			'headers' => array(
				'Authorization' => $bisu_token,
			),
			'body'    => array(),
			'timeout' => 300, // 5 minutes
		);
		wc_get_logger()->info(
			sprintf(
					/* translators: %s $response */
				__( 'WA Installation Response: %1$s , %2$s', 'facebook-for-woocommerce' ),
				$base_url,
				wp_json_encode($params),
			)
		);
		$response     = wp_remote_post( $base_url, $options );
		wc_get_logger()->info(
			sprintf(
					/* translators: %s $response */
				__( 'WA Installation Response: %1$s ', 'facebook-for-woocommerce' ),
				wp_json_encode( $response ),
			)
		);
		// TODO: Call the iframe management stefi endpoint to fetch the iframe management url
		return 'https://www.commercepartnerhub.com/whatsapp_utility_integration/overview/';
	}
}
