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

use ReflectionClass;
use WooCommerce\Facebook\Integrations\IntegrationIsNotAvailableException;

/**
 * Integration for the Cost-of-Goods feature on WooCommerce plugin.
 *
 * @since 3.6.0-dev.1
 */
class WooCCogsProvider extends AbstractCogsProvider {

	/** @var string Name of the integration. */
	const INTEGRATION_NAME = 'WooCommerce Cost of Goods';

	/** @var bool to cache whether this provider is available. */
	private static $is_available = null;

	public function get_cogs_value( $product ) {

		if ( ! self::is_available() ) {
			throw new IntegrationIsNotAvailableException( self::INTEGRATION_NAME );
		}

		// We must use cogs_total as that'll have the correct value for Simple & Variable products
		return $product->get_cogs_total_value();
	}

	public static function is_available() {

		$func = function () {
			if ( ! \WC_Facebookcommerce_Utils::is_woocommerce_integration() || ! class_exists( 'WC_Product' ) ) {
				return false;
			}

			$reflection      = new ReflectionClass( 'WC_Product' );
			$set_prop_method = $reflection->getMethod( 'set_prop' );
			$instance        = $reflection->newInstanceWithoutConstructor();
			$set_prop_method->invokeArgs( $instance, array( 'cogs_value', '100.0' ) );

			if ( ! method_exists( $instance, 'get_cogs_total_value' ) || (
					null === $instance->get_cogs_total_value()
				)
			) {
				return false;
			}

			if ( function_exists( 'wc_get_container' ) && class_exists( 'Automattic\WooCommerce\Internal\Features\FeaturesController' ) ) {
				return wc_get_container()->get( 'Automattic\WooCommerce\Internal\Features\FeaturesController' )->feature_is_enabled( 'cost_of_goods_sold' );
			}

			return function_exists( 'get_option' ) && ( 'yes' === get_option( 'woocommerce_feature_cost_of_goods_sold_enabled' ) );
		};

		if ( null === self::$is_available ) {
			self::$is_available = $func();
		}
		return self::$is_available;
	}
}
