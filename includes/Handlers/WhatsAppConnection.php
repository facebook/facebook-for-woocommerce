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

use WooCommerce\Facebook\Framework\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * The whatsapp utility connection handler.
 *
 * @since 2.0.0
 */
class WhatsAppConnection {

	/** @var string the system user access token option name */
	const OPTION_WA_UTILITY_ACCESS_TOKEN = 'wc_facebook_wa_utility_access_token';
	/** @var string the whatsapp external id option name */
	const OPTION_WA_EXTERNAL_BUSINESS_ID = 'wc_facebook_wa_external_business_id';
	/** @var string the whatsapp business id option name */
	const OPTION_WA_BUSINESS_ID = 'wc_facebook_wa_business_id';
	/** @var string the whatsapp business account id option name */
	const OPTION_WA_WABA_ID = 'wc_facebook_wa_waba_id';
	/** @var string the whatsapp phone number id option name */
	const OPTION_WA_PHONE_NUMBER_ID = 'wc_facebook_wa_phone_number_id';
	/** @var string the whatsapp installation id option name */
	const OPTION_WA_INSTALLATION_ID = 'wc_facebook_wa_installation_id';
	/** @var string the whatsapp integration config id option name */
	const OPTION_WA_INTEGRATION_CONFIG_ID = 'wc_facebook_wa_integration_config_id';



	/** @var \WC_Facebookcommerce */
	private $plugin;

	/** @var string|null the generated external whatsapp ID */
	private $wa_external_id;


	/**
	 * Constructs a new WA Utility Connection.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Facebookcommerce $plugin
	 */
	public function __construct( \WC_Facebookcommerce $plugin ) {

		$this->plugin = $plugin;
	}


	/**
	 * Gets the system user access token.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_access_token() {
		$access_token = get_option( self::OPTION_WA_UTILITY_ACCESS_TOKEN, '' );
		return $access_token;
	}

	/**
	 * Gets the WA installation ID.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_wa_installation_id() {
		$wa_installation_id = get_option( self::OPTION_WA_INSTALLATION_ID, '' );
		return $wa_installation_id;
	}


	/**
	 * Determines whether the site is integrated whatsapp utility.
	 *
	 * A site is connected if there is an access token stored.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_connected() {
		return (bool) $this->get_access_token();
	}

	/**
	 * Gets the stored whatsapp external ID.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_whatsapp_external_id() {
		if ( ! is_string( $this->wa_external_id ) ) {
			$external_id = get_option( self::OPTION_WA_EXTERNAL_BUSINESS_ID );
			if ( ! is_string( $external_id ) || empty( $external_id ) ) {
				/**
				 * Filters the whatsapp external ID.
				 *
				 * This is passed to Meta when Onboarding.
				 * Should be non-empty and without special characters, otherwise the ID will be obtained from the site URL as fallback.
				 *
				 * @since 2.0.0
				 *
				 * @param string $external_id the whatsapp external ID
				 */
				$external_id_prefix = sanitize_key( (string) get_bloginfo( 'name' ) );
				if ( empty( $external_id_prefix ) ) {
					$external_id_prefix = sanitize_key( str_replace( array( 'http', 'https', 'www' ), '', get_bloginfo( 'url' ) ) );
				}
				$external_id = uniqid( sprintf( '%s-', $external_id_prefix ), false );
				$this->update_whatsapp_external_business_id( $external_id );
			}
			$this->wa_external_id = $external_id;
		}

		return $external_id;
	}

	/**
	 * Stores the given wa external id.
	 *
	 * @since 2.6.13
	 *
	 * @param string $value external business id
	 */
	public function update_whatsapp_external_business_id( $value ) {
		update_option( self::OPTION_WA_EXTERNAL_BUSINESS_ID, is_string( $value ) ? $value : '' );
	}
}
