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
 *
 * This contract is documented for integration authors in docs/offline-events.md
 * ("Adding a point-of-sale integration"), with an example implementation of every
 * method. When you add, remove or change a method here, update that example and
 * its explanation too, or third-party authors will copy a class that no longer
 * satisfies the interface.
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
	 * Determines whether the current request renders one of this POS's own pages.
	 *
	 * Point of sale pages — the till itself, and checkout screens it opens — are
	 * used by staff, not shoppers, so no storefront (website) events are sent from
	 * them. This should cover pages only: the POS's API calls, where offline events
	 * are sent, must not match.
	 *
	 * @return bool
	 */
	public function is_pos_page_request(): bool;

	/**
	 * Determines whether the given order was created by this POS.
	 *
	 * @param \WC_Order $order order object.
	 * @return bool
	 */
	public function is_pos_order( \WC_Order $order ): bool;

	/**
	 * Gets the user ID of the staff member who rang up the given order.
	 *
	 * Used to avoid reporting the cashier's details as the customer's, since point
	 * of sale plugins commonly attach the operator to walk-in sales.
	 *
	 * @param \WC_Order $order order object.
	 * @return int the cashier's user ID, or 0 if the POS does not record one.
	 */
	public function get_cashier_id( \WC_Order $order ): int;

	/**
	 * Gets the physical store the order was sold in, as the event's `store_data`.
	 *
	 * Keys follow Meta's store_data object: `store_page_id` and `brand_page_id`
	 * (Facebook Page IDs) and `store_code` (a string of up to 64 characters). Meta
	 * matches the code against the store locations configured on the merchant's
	 * side. Unknown keys and invalid values are dropped before sending.
	 *
	 * @param \WC_Order $order order object.
	 * @return array the store data, or an empty array if the POS does not record a store.
	 */
	public function get_store_data( \WC_Order $order ): array;

	/**
	 * Gets POS-specific custom data to merge into the offline event.
	 *
	 * @param \WC_Order $order order object.
	 * @return array
	 */
	public function get_event_data( \WC_Order $order ): array;
}
