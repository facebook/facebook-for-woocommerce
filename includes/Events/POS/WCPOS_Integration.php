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
 * Integration with WCPOS (WooCommerce POS).
 *
 * @see https://wordpress.org/plugins/woocommerce-pos/
 */
class WCPOS_Integration implements POS_Integration_Interface {

	/** @var string the slug identifying this integration */
	const SLUG = 'wcpos';

	/**
	 * Gets the unique slug identifying this integration.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return self::SLUG;
	}

	/**
	 * Gets the human-readable name of the point of sale.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'WCPOS', 'facebook-for-woocommerce' );
	}

	/**
	 * Determines whether WCPOS is present and loaded.
	 *
	 * WCPOS declares wcpos_is_pos_order() behind its own function_exists() guard,
	 * so its presence is a reliable signal that the plugin is loaded far enough
	 * to answer questions about orders.
	 *
	 * @return bool
	 */
	public function is_supported(): bool {
		return function_exists( 'wcpos_is_pos_order' );
	}

	/**
	 * Determines whether the given order was created by WCPOS.
	 *
	 * Defers to WCPOS's own helper rather than reimplementing the check, so this
	 * keeps working if they change how POS orders are flagged.
	 *
	 * @param \WC_Order $order order object.
	 * @return bool
	 */
	public function is_pos_order( \WC_Order $order ): bool {
		return $this->is_supported() && wcpos_is_pos_order( $order );
	}

	/**
	 * Gets WCPOS-specific custom data to merge into the offline event.
	 *
	 * WCPOS records the till and cashier as `_pos_store` and `_pos_user` order
	 * meta. Neither is reported yet — that needs store configuration on the Meta
	 * side first.
	 *
	 * @param \WC_Order $order order object.
	 * @return array
	 */
	public function get_event_data( \WC_Order $order ): array {
		return array();
	}
}
