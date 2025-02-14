<?php
// phpcs:ignoreFile
/**
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Handlers;

defined( 'ABSPATH' ) or exit;

use WooCommerce\Facebook\Handlers\Connection;

class MetaExtension {

	/** @var string Client token */
	const CLIENT_TOKEN = '195311308289826|52dcd04d6c7ed113121b5eb4be23b4a7';
    const APP_ID = '474166926521348';
	/** @var string Business name */
	const BUSINESS_NAME = 'WooCommerce';

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {
	}

	/**
	 * Generates the Commerce Hub iframe splash page URL.
	 *
	 * @param bool   $is_connected         Whether the plugin is currently connected.
	 * @param object $plugin               The plugin instance.
	 * @param string $external_business_id External business ID.
	 * @return string
	 */
	public static function generateIframeSplashUrl( $is_connected, $plugin, $external_business_id ) {
		$external_client_metadata = array(
			'shop_domain'      => wc_get_page_permalink('shop') ?: home_url(),
			'admin_url'        => admin_url(),
			'client_version'   => $plugin->get_version(),
			'commerce_partner_seller_platform_type' => 'MAGENTO_OPEN_SOURCE',
			'country_code'     => WC()->countries->get_base_country(),
		);
		return add_query_arg(
			array(
				'access_client_token'      => self::CLIENT_TOKEN,
				'business_vertical'        => 'ECOMMERCE',
				'channel'                  => 'COMMERCE',
				'app_id'                   => Connection::CLIENT_ID,
				'business_name'            => self::BUSINESS_NAME,
				'currency'                 => get_woocommerce_currency(),
				'timezone'                 => 'America/Los_Angeles',
				'external_business_id'     => $external_business_id,
				'installed'                => $is_connected,
				'external_client_metadata' => rawurlencode( json_encode( $external_client_metadata ) ),
			),
			'https://www.commercepartnerhub.com/commerce_extension/splash/'
		);
	}
} 