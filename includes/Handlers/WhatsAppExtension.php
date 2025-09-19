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
	const CLIENT_TOKEN = '753591807210902|489b438e3f0d9ba44504eccd5ce8fe94';
	/** @var string Whatsapp Integration app ID */
	const APP_ID = '753591807210902';
	/** @var string Whatsapp Tech Provider Business ID */
	const TP_BUSINESS_ID = '1421860479064677';
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
		wc_get_logger()->info(
			sprintf(
				__( 'WhatsApp Utility Messages Iframe Splash Url Fetched.', 'facebook-for-woocommerce' ),
			)
		);

		return add_query_arg(
			array(
				'access_client_token'   => self::CLIENT_TOKEN,
				'app_id'                => self::APP_ID,
				'app_owner_business_id' => self::TP_BUSINESS_ID,
				'external_business_id'  => $external_wa_id,
			),
			self::COMMERCE_HUB_URL . 'whatsapp_utility_integration/splash/'
		);
	}

	/**
	 * Generates the Commerce Hub whatsApp iframe management page URL.
	 *
	 * @param object $plugin The plugin instance.
	 *
	 * @return string
	 * @since 3.5.0
	 */
	public static function generate_wa_iframe_management_url( $plugin ) {
		$whatsapp_connection = $plugin->get_whatsapp_connection_handler();
		$is_connected        = $whatsapp_connection->is_connected();
		if ( ! $is_connected ) {
			wc_get_logger()->info(
				sprintf(
					__( 'WhatsApp Utility Messages Iframe Management Url failed to fetch due to failed WhatsApp connection', 'facebook-for-woocommerce' ),
				)
			);
			return '';
		}

		$wa_installation_id = $whatsapp_connection->get_wa_installation_id();
		$base_url           = array( self::BASE_STEFI_ENDPOINT_URL, 'whatsapp/business', $wa_installation_id, 'utility_message_iframe_management_uri' );
		$base_url           = esc_url( implode( '/', $base_url ) );
		$params             = array(
			'locale' => get_user_locale() ?? self::DEFAULT_LANGUAGE,
		);
		$url                = add_query_arg( $params, $base_url );

		$bisu_token      = $whatsapp_connection->get_access_token();
		$options         = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $bisu_token,
			),
			'body'    => array(),
			'timeout' => 3000, // 5 minutes
		);
		$response        = wp_remote_get( $url, $options );
		$status_code     = wp_remote_retrieve_response_code( $response );
		$data            = explode( "\n", wp_remote_retrieve_body( $response ) );
		$response_object = json_decode( $data[0] );
		if ( is_wp_error( $response ) || 200 !== $status_code ) {
			$error_message = $response_object->error->error_user_title ?? $response_object->error->message ?? 'Something went wrong. Please try again later!';
			wc_get_logger()->info(
				sprintf(
				/* translators: %s $wa_installation_id %s $error_message */
					__( 'Failed to fetch iframe Management URI. wa_installation_id: %1$s, error message: %2$s', 'facebook-for-woocommerce' ),
					$wa_installation_id,
					$error_message,
				)
			);
			return '';
		} else {
			wc_get_logger()->info(
				sprintf(
					__( 'WhatsApp Utility Messages Iframe Management Url successfully fetched', 'facebook-for-woocommerce' ),
				)
			);
		}
		return $response_object->iframe_management_uri;
	}
}
