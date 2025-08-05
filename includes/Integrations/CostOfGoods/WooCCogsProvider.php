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

	const INTEGRATION_NAME = 'WooCommerce Cost of Goods';

	public function __construct() {

		if ( ! self::is_available() ) {
			throw new IntegrationIsNotAvailableException( self::INTEGRATION_NAME );
		}
	}

	public function get_cogs_value( $product ) {

		// TODO: Check if this function is available on older WooC versions
		// We must use cogs_total as that'll have the correct value for Simple & Variable products
		return $product->get_cogs_total_value();
	}

	public static function is_available() {

		if ( ! \WC_Facebookcommerce_Utils::is_woocommerce_integration() || ! class_exists( 'WC_Product' ) ) {
			return false;
		}
		$reflection = new ReflectionClass( 'WC_Product' );
		$instance 	= $reflection->newInstanceWithoutConstructor();

		if ( ! method_exists( $instance, 'get_cogs_total_value' ) ) {
			return false;
		}
		// Double check if this is absolutely necessary ( Disable COGS on WooC and see if get_cogs_total_value still exists )
		return function_exists( 'get_option ' ) && ( 'yes' === get_option( 'woocommerce_feature_cost_of_goods_sold_enabled' ) );
	}
}
