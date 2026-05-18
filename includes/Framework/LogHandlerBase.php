<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package MetaCommerce
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
	 * Gets plugin version safely during early bootstrap.
	 *
	 * @return string
	 */
	protected static function get_plugin_version() {
		if ( class_exists( '\\WC_Facebookcommerce' ) && defined( '\\WC_Facebookcommerce::VERSION' ) ) {
			return (string) \WC_Facebookcommerce::VERSION;
		}

		return '';
	}

	/**
	 * Prefill the log context with basic information.
	 *
	 * @since 3.5.0
	 *
	 * @param array $context log context
	 */
	public static function set_core_log_context( array $context ) {
		$request_data = [
			'commerce_merchant_settings_id'   => '',
			'commerce_partner_integration_id' => '',
			'external_business_id'            => '',
			'catalog_id'                      => '',
			'page_id'                         => '',
			'pixel_id'                        => '',
			'seller_platform_app_version'     => self::get_plugin_version(),
		];

		try {
			if ( function_exists( 'facebook_for_woocommerce' ) ) {
				$plugin = facebook_for_woocommerce();
				if ( $plugin && method_exists( $plugin, 'get_connection_handler' ) ) {
					$connection_handler = $plugin->get_connection_handler();
					if ( $connection_handler ) {
						$request_data['commerce_merchant_settings_id']   = $connection_handler->get_commerce_merchant_settings_id();
						$request_data['commerce_partner_integration_id'] = $connection_handler->get_commerce_partner_integration_id();
						$request_data['external_business_id']            = $connection_handler->get_external_business_id();
					}
				}
				if ( $plugin && method_exists( $plugin, 'get_integration' ) ) {
					$integration = $plugin->get_integration();
					if ( $integration ) {
						$request_data['catalog_id'] = $integration->get_product_catalog_id();
						$request_data['page_id']    = $integration->get_facebook_page_id();
						$request_data['pixel_id']   = $integration->get_facebook_pixel_id();
					}
				}
			}
		} catch ( \Throwable $e ) {
			unset( $e );
		}

		return array_merge( $request_data, $context );
	}
}
