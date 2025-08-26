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
	public static function generate_wa_iframe_management_url() {
		// TODO: Call the iframe management stefi endpoint to fetch the iframe management url
		return 'https://www.commercepartnerhub.com/whatsapp_utility_integration/overview/';
	}
}
