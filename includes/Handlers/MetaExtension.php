<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\Handlers;

use WooCommerce\Facebook\API\CommerceIntegration\Client as CommerceIntegrationClient;

defined( 'ABSPATH' ) || exit;

/**
 * Handles Meta Commerce Extension functionality and configuration.
 *
 * @since 3.5.0
 */
class MetaExtension {

	/** @var string Client token */
	const CLIENT_TOKEN = '474166926521348|92e978eb27baf47f9df578b48d430a2e';
	const APP_ID       = '474166926521348';

	/** @var string API version */
	const API_VERSION = 'v22.0';

	/** @var string Commerce Hub base URL */
	const COMMERCE_HUB_URL = 'https://www.commercepartnerhub.com/';

	/** @var string Option names for Facebook settings */
	const OPTION_ACCESS_TOKEN = 'wc_facebook_access_token';

	/** @deprecated Legacy FBE 2 leftover, unused here. Use self::OPTION_ACCESS_TOKEN instead. */
	const OPTION_MERCHANT_ACCESS_TOKEN = 'wc_facebook_merchant_access_token';

	const OPTION_BUSINESS_MANAGER_ID             = 'wc_facebook_business_manager_id';
	const OPTION_AD_ACCOUNT_ID                   = 'wc_facebook_ad_account_id';
	const OPTION_INSTAGRAM_BUSINESS_ID           = 'wc_facebook_instagram_business_id';
	const OPTION_COMMERCE_MERCHANT_SETTINGS_ID   = 'wc_facebook_commerce_merchant_settings_id';
	const OPTION_EXTERNAL_BUSINESS_ID            = 'wc_facebook_external_business_id';
	const OPTION_COMMERCE_PARTNER_INTEGRATION_ID = 'wc_facebook_commerce_partner_integration_id';
	const OPTION_PRODUCT_CATALOG_ID              = 'wc_facebook_product_catalog_id';
	const OPTION_PIXEL_ID                        = 'wc_facebook_pixel_id';
	const OPTION_PROFILES                        = 'wc_facebook_profiles';
	const OPTION_INSTALLED_FEATURES              = 'wc_facebook_installed_features';

	/** @deprecated Legacy FBE 2 leftover, no longer written. Nothing reads the stored ID. */
	const OPTION_SYSTEM_USER_ID = 'wc_facebook_system_user_id';

	/** @deprecated Legacy FBE 2 leftover, no longer written. Use Connection::is_connected() instead. */
	const OPTION_HAS_CONNECTED_FBE_2 = 'wc_facebook_has_connected_fbe_2';

	/** @deprecated Legacy FBE 2 leftover, no longer written. Nothing reads it. */
	const OPTION_HAS_AUTHORIZED_PAGES = 'wc_facebook_has_authorized_pages_read_engagement';

	/** @var string Nonce action */
	const NONCE_ACTION = 'wc_facebook_ajax_token_update';

	// ==========================
	// = IFrame Management      =
	// ==========================

	/**
	 * Generates the Commerce Hub iframe splash page URL.
	 *
	 * @param bool   $is_connected Whether the plugin is currently connected.
	 * @param object $plugin The plugin instance.
	 * @param string $external_business_id External business ID.
	 *
	 * @return string
	 * @since 3.5.0
	 */
	public static function generate_iframe_splash_url( $is_connected, $plugin, $external_business_id ): string {
		$connection_handler       = facebook_for_woocommerce()->get_connection_handler();
		$external_client_metadata = array(
			'shop_domain'                           => site_url( '/' ),
			'admin_url'                             => admin_url(),
			'client_version'                        => $plugin->get_version(),
			'commerce_partner_seller_platform_type' => 'SELF_SERVE_PLATFORM',
			'country_code'                          => WC()->countries->get_base_country(),
			'platform_store_id'                     => get_current_blog_id(),
		);

		return add_query_arg(
			array(
				'access_client_token'      => self::CLIENT_TOKEN,
				'business_vertical'        => 'ECOMMERCE',
				'channel'                  => 'COMMERCE',
				'app_id'                   => facebook_for_woocommerce()->get_connection_handler()->get_client_id(),
				'business_name'            => rawurlencode( $connection_handler->get_business_name() ),
				'currency'                 => get_woocommerce_currency(),
				'timezone'                 => $connection_handler->get_timezone_string(),
				'external_business_id'     => $external_business_id,
				'installed'                => $is_connected,
				'external_client_metadata' => rawurlencode( wp_json_encode( $external_client_metadata ) ),
			),
			self::COMMERCE_HUB_URL . 'commerce_extension/splash/'
		);
	}

	/**
	 * Generates the Commerce Hub iframe management page URL.
	 *
	 * Uses the Commerce Extension token endpoint first, then falls back to the
	 * legacy business configuration endpoint when the new flow is unavailable.
	 *
	 * @param string $external_business_id External business ID.
	 * @param string $commerce_partner_integration_id Optional Commerce Partner Integration ID.
	 *
	 * @return string
	 * @since 3.5.0
	 */
	public static function generate_iframe_management_url( $external_business_id, $commerce_partner_integration_id = '' ) {
		$access_token = get_option( self::OPTION_ACCESS_TOKEN, '' );

		if (
			! is_string( $access_token ) || empty( $access_token ) ||
			! is_string( $external_business_id ) || empty( $external_business_id )
		) {
			return '';
		}

		if ( empty( $commerce_partner_integration_id ) ) {
			$commerce_partner_integration_id = get_option( self::OPTION_COMMERCE_PARTNER_INTEGRATION_ID, '' );
		}

		if ( is_string( $commerce_partner_integration_id ) && ! empty( $commerce_partner_integration_id ) ) {
			try {
				$iframe_url = self::generate_iframe_management_url_with_commerce_extension_token(
					$external_business_id,
					$commerce_partner_integration_id,
					$access_token
				);
				delete_transient( 'wc_facebook_connection_invalid' );
				return $iframe_url;
			} catch ( \Throwable $e ) {
				facebook_for_woocommerce()->log( 'Facebook Commerce Extension token endpoint error: ' . $e->getMessage() );
			}
		}

		try {
			$iframe_url = self::generate_legacy_iframe_management_url( $external_business_id, $access_token );
			delete_transient( 'wc_facebook_connection_invalid' );
			return $iframe_url;
		} catch ( \Throwable $e ) {
			facebook_for_woocommerce()->log( 'Facebook Commerce Extension legacy URL error: ' . $e->getMessage() );
		}

		return '';
	}

	/**
	 * Generates the management URL using the Commerce Extension token endpoint.
	 *
	 * @param string $external_business_id External business ID.
	 * @param string $commerce_partner_integration_id Commerce Partner Integration ID.
	 * @param string $access_token Long-lived business integration system user token.
	 *
	 * @return string
	 * @throws \Exception If the endpoint request fails or does not return a delegated access token.
	 */
	private static function generate_iframe_management_url_with_commerce_extension_token( $external_business_id, $commerce_partner_integration_id, $access_token ) {
		$client         = new CommerceIntegrationClient();
		$token_response = $client->request_commerce_extension_token( $access_token, $commerce_partner_integration_id );

		return self::build_iframe_management_url( $token_response->get_access_token(), $external_business_id );
	}

	/**
	 * Generates the management URL using the legacy Graph API endpoint.
	 *
	 * @param string $external_business_id External business ID.
	 * @param string $access_token Long-lived business integration system user token.
	 *
	 * @return string
	 * @throws \Exception If the legacy endpoint does not return a management URL.
	 */
	private static function generate_legacy_iframe_management_url( $external_business_id, $access_token ) {
		$api        = facebook_for_woocommerce()->get_api( $access_token );
		$response   = $api->get_business_configuration(
			$external_business_id,
			'',
			array( 'commerce_extension' )
		);
		$iframe_url = $response->get_commerce_extension_uri();
		if ( empty( $iframe_url ) ) {
			throw new \Exception( 'Commerce extension URI not found' );
		}

		return $iframe_url;
	}

	/**
	 * Builds the Commerce Hub management URL from a delegated access token.
	 *
	 * @param string $delegate_access_token Short-lived delegated access token.
	 * @param string $external_business_id External business ID.
	 *
	 * @return string
	 */
	private static function build_iframe_management_url( $delegate_access_token, $external_business_id ) {
		return add_query_arg(
			array(
				'access_token'         => $delegate_access_token,
				'external_business_id' => $external_business_id,
				'locale'               => get_user_locale(),
			),
			self::COMMERCE_HUB_URL . 'commerce_extension/overview/'
		);
	}
}
