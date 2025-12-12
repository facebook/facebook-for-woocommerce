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
	protected $available_integrations = array();

	/** @var bool to cache whether provider availability has been evaluated or not. */
	protected $already_fetched = false;

	/** @var array used as an input of the supported integrations. */
	protected $supported_integrations;

	public function __construct( $supported_integrations = null ) {

		if ( null === $supported_integrations ) {
			$supported_integrations = self::get_supported_integrations();
		}

		$this->supported_integrations = $supported_integrations;
	}

	public function calculate_cogs_for_products( $product_quantities ) {

		if ( ! $this->is_cogs_provider_available() ) {
			return false;
		}

		if ( empty( $product_quantities ) ) {
			return false;
		}

		$order_cogs = 0;
		foreach ( $product_quantities as $tuple ) {
			if ( ! is_array( $tuple ) || ! isset( $tuple['product'], $tuple['qty'] ) ) {
				throw new IncorrectCogsInputStructure();
			}
			$product = $tuple['product'];
			$quantity = $tuple['qty'];
			$cogs = $this->get_cogs_for_product( $product );

			// If cogs was 0 for one product, the value is invalid for the order
			if ( ! $cogs || $cogs < 0 ) {
				return false;
			}
			$order_cogs += $cogs * $quantity;
		}

		return $order_cogs;
	}

	public static function get_supported_integrations() {

		return array(
			'WooC'      => 'WooCCogsProvider',
			'WPFactory' => 'WPFactoryCogsProvider',
		);
	}

	protected function get_cogs_providers() {
		if ( ! $this->already_fetched ) {
			$this->available_integrations = array();
			foreach ( $this->supported_integrations as $integration => $class_name ) {
				$class = 'WooCommerce\\Facebook\\Integrations\\CostOfGoods\\' . $class_name;
				$instance = new $class();
				if ( $instance->is_available() ) {
					$this->available_integrations[] = $instance;
				}
			}
			$this->already_fetched = true;
		}
		return $this->available_integrations;
	}

	protected function get_cogs_for_product( $product ) {

		$cogs_providers = $this->get_cogs_providers();
		foreach ( $cogs_providers as $provider ) {
			$cogs = $provider->get_cogs_value( $product );
			if ( is_numeric( $cogs ) && $cogs > 0 ) {
				return $cogs;
			}
		}

		return false;
	}

	protected function is_cogs_provider_available() {
		return count( $this->get_cogs_providers() ) > 0;
	}
}
