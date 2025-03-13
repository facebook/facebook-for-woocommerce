<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Framework;

defined( 'ABSPATH' ) || exit;


/**
 * Log handler Base class
 *
 * @since 3.5.0
 */
class LogHandlerBase {

	/**
	 * Plugin version.
	 */
	const PLUGIN_VERSION = \WC_Facebookcommerce::VERSION;

	/**
	 * Prefill the log context with basic information.
	 *
	 * @since 3.5.0
	 *
	 * @param array $context log context
	 */
	public static function set_core_log_context( array $context ) {
		$request_data = [
			'commerce_merchant_settings_id'   => facebook_for_woocommerce()->get_connection_handler()->get_commerce_merchant_settings_id(),
			'commerce_partner_integration_id' => facebook_for_woocommerce()->get_connection_handler()->get_commerce_partner_integration_id(),
			'external_business_id'            => facebook_for_woocommerce()->get_connection_handler()->get_external_business_id(),
			'catalog_id'                      => facebook_for_woocommerce()->get_integration()->get_product_catalog_id(),
			'page_id'                         => facebook_for_woocommerce()->get_integration()->get_facebook_page_id(),
			'pixel_id'                        => facebook_for_woocommerce()->get_integration()->get_facebook_pixel_id(),
			'seller_platform_app_version'     => self::PLUGIN_VERSION,
		];

		return array_merge( $request_data, $context );
	}
}
