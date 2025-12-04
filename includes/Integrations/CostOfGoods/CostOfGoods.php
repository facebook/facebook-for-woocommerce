<?php
/**
 * Copyright (c) Meta, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Integrations\CostOfGoods;

defined( 'ABSPATH' ) || exit;

/**
 * Integration with Cost-of-Goods Plugins.
 *
 * @since 2.0.0-dev.1
 */
class CostOfGoods {

	/** @var array to cache the available cogs integrations. */
	private static $available_integrations = array();

	/** @var bool to cache whether provider availability has been evaluated or not. */
	private static $already_fetched = false;

	public static function calculate_cogs_for_products( $products ) {

		if ( ! self::is_cogs_provider_available() ) {
			// return false;
			return 1;
		}

		if ( empty( $products ) ) {
			// return false;
			return 2;
		}

		if (count( self::get_cogs_providers() ) == 0) {
			return 4;
		}

		if (count(self::$available_integrations ) != count( self::get_cogs_providers() ) ) {
			return 5;
		}

		$order_cogs = 0;
		foreach ( $products as $product ) {

			$cogs = self::get_cogs_for_product( $product );
			if ($cogs == -1) {
				return 6;
			}
			// If cogs was 0 for one product, the value is invalid for the order
			if ( ! $cogs || $cogs < 0 ) {
				return 3;
				// return false;
			}
			$order_cogs += $cogs;
		}

		return $order_cogs;
	}

	public static function get_supported_integrations() {

		return array(
			'WooC'      => 'WooCCogsProvider',
			'WPFactory' => 'WPFactoryCogsProvider',
		);
	}

	private static function get_cogs_providers() {
		if ( ! self::$already_fetched ) {
			self::$available_integrations = array();
			foreach ( self::get_supported_integrations() as $integration => $class_name ) {
				$class = 'WooCommerce\\Facebook\\Integrations\\CostOfGoods\\' . $class_name;
				if ( $class::is_available() ) {
					self::$available_integrations[] = new $class();
				}
			}
			self::$already_fetched = true;
		}
		return self::$available_integrations;
	}

	private static function get_cogs_for_product( $product ) {

		$cogs_providers = self::get_cogs_providers();
		$test = false;
		foreach ( $cogs_providers as $provider ) {
			$test = true;
			$cogs = $provider->get_cogs_value( $product );
			if ( is_numeric( $cogs ) && $cogs > 0 ) {
				return $cogs;
			}
		}

		if ( ! $test ) {
			return -1;
		}
		return false;
	}

	private static function is_cogs_provider_available() {
		return count( self::get_cogs_providers() ) > 0;
	}
}
