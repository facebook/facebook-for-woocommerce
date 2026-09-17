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
 * Contract for a point-of-sale integration.
 *
 * An implementation recognises orders created by one POS plugin so they can be
 * reported to Meta as offline (physical store) Purchase events instead of web
 * ones. Register new implementations in POS_Integration_Registry::INTEGRATIONS.
 */
interface POS_Integration_Interface {

	/**
	 * Gets the unique slug identifying this integration.
	 *
	 * @return string
	 */
	public function get_slug(): string;

	/**
	 * Gets the human-readable name of the point of sale, for display in settings.
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Determines whether the POS plugin this integration targets is present and loaded.
	 *
	 * @return bool
	 */
	public function is_supported(): bool;

	/**
	 * Determines whether the given order was created by this POS.
	 *
	 * @param \WC_Order $order order object.
	 * @return bool
	 */
	public function is_pos_order( \WC_Order $order ): bool;

	/**
	 * Gets POS-specific custom data to merge into the offline event.
	 *
	 * @param \WC_Order $order order object.
	 * @return array
	 */
	public function get_event_data( \WC_Order $order ): array;
}
