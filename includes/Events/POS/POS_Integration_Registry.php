<?php
/**
 * Copyright (c) Meta, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\Events\POS;

defined( 'ABSPATH' ) || exit;

/**
 * Holds the known point-of-sale integrations and matches orders against them.
 */
class POS_Integration_Registry {

	/**
	 * The known integrations.
	 *
	 * Add new POS integrations here.
	 *
	 * @var string[]
	 */
	const INTEGRATIONS = array(
		WCPOS_Integration::class,
	);

	/** @var POS_Integration_Interface[]|null cached integration instances */
	private $integrations = null;

	/**
	 * Gets every registered integration, whether or not its POS plugin is active.
	 *
	 * @return POS_Integration_Interface[]
	 */
	public function get_integrations(): array {
		if ( null !== $this->integrations ) {
			return $this->integrations;
		}

		$integrations = array();

		foreach ( self::INTEGRATIONS as $class_name ) {
			$integrations[] = new $class_name();
		}

		/**
		 * Filters the point-of-sale integrations used to detect offline orders.
		 *
		 * Entries that are not POS_Integration_Interface instances are discarded.
		 *
		 * @param POS_Integration_Interface[] $integrations the registered integrations
		 */
		$integrations = (array) apply_filters( 'wc_facebook_pos_integrations', $integrations );

		$this->integrations = array_values(
			array_filter(
				$integrations,
				function ( $integration ) {
					return $integration instanceof POS_Integration_Interface;
				}
			)
		);

		return $this->integrations;
	}

	/**
	 * Gets the integrations whose POS plugin is currently active.
	 *
	 * @return POS_Integration_Interface[]
	 */
	public function get_supported_integrations(): array {
		return array_values(
			array_filter(
				$this->get_integrations(),
				function ( POS_Integration_Interface $integration ) {
					return $integration->is_supported();
				}
			)
		);
	}

	/**
	 * Gets the first active integration that claims the given order.
	 *
	 * @param \WC_Order $order order object.
	 * @return POS_Integration_Interface|null the matching integration, or null if the order is not a POS order
	 */
	public function match( \WC_Order $order ): ?POS_Integration_Interface {
		foreach ( $this->get_supported_integrations() as $integration ) {
			if ( $integration->is_pos_order( $order ) ) {
				return $integration;
			}
		}

		return null;
	}
}
