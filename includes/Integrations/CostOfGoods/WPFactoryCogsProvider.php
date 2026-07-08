<?php
/**
 * Copyright (c) Meta, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\Integrations\CostOfGoods;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\Integrations\IntegrationIsNotAvailableException;

/**
 * Integration for the WPFactory Cost-of-Goods feature on WooCommerce plugin (Free & Pro versions).
 *
 * @since 2.0.0-dev.1
 */
class WPFactoryCogsProvider extends AbstractCogsProvider {

	const INTEGRATION_NAME = 'WooCommerce Cost of Goods by WPFactory';

	public function get_cogs_value( $product ) {
		if ( ! self::is_available() ) {
			throw new IntegrationIsNotAvailableException( self::INTEGRATION_NAME );
		}
		// WPFactory renamed alg_wc_cog() to wpfcogs() in v4.1.6; prefer the new accessor
		// and fall back to the legacy one for older plugin versions. For WPFactory simple
		// & variable products the cost is retrieved by the same following method.
		$cogs = function_exists( 'wpfcogs' ) ? wpfcogs() : alg_wc_cog();
		return $cogs->core->products->get_product_cost( $product->get_id() );
	}

	public function get_availability(): bool {

		return function_exists( 'wpfcogs' ) || function_exists( 'alg_wc_cog' );
	}
}
