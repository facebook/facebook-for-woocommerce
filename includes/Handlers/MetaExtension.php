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
	const OPTION_ACCESS_TOKEN                    = 'wc_facebook_access_token';
	const OPTION_MERCHANT_ACCESS_TOKEN           = 'wc_facebook_merchant_access_token';
	const OPTION_PAGE_ACCESS_TOKEN               = 'wc_facebook_page_access_token';
	const OPTION_SYSTEM_USER_ID                  = 'wc_facebook_system_user_id';
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
	const OPTION_HAS_CONNECTED_FBE_2             = 'wc_facebook_has_connected_fbe_2';
	const OPTION_HAS_AUTHORIZED_PAGES            = 'wc_facebook_has_authorized_pages_read_engagement';

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
	 * @param string $external_business_id External business ID.
	 *
	 * @return string
	 * @throws \Exception If the URL generation fails or if external_business_id is invalid.
	 * @since 3.5.0
	 */
	public static function generate_iframe_management_url( $external_business_id ) {
		$access_token = get_option( self::OPTION_ACCESS_TOKEN, '' );

		if ( empty( $access_token ) || empty( $external_business_id ) ) {
			return '';
		}

		try {
			$response = facebook_for_woocommerce()->get_api()->get_business_configuration(
				$external_business_id,
				$access_token,
				[ 'commerce_extension' ]
			);
			$uri      = $response->get_commerce_extension_uri();
			if ( empty( $uri ) ) {
				throw new \Exception( 'Commerce extension URI not found' );
			}
			return $response->get_commerce_extension_uri();
		} catch ( \Exception $e ) {
			facebook_for_woocommerce()->log( 'Facebook Commerce Extension URL Error: ' . $e->getMessage() );
		}

		return '';
	}
}
