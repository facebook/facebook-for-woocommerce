<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Feed;

defined( 'ABSPATH' ) || exit;

/**
 * Navigation Menu Feed Generator Class
 *
 * Navigation Menu Feed Generator Class. This file is responsible for the new-style feed generation for navigation menu.
 *
 * @package WooCommerce\Facebook\Feed
 * @since 3.5.0
 */
class NavigationMenuFeedGenerator extends FeedGenerator {
	/**
	 * Retrieves items for a specific batch.
	 *
	 * @param int   $batch_number The batch number.
	 * @param array $args Additional arguments.
	 *
	 * @return array The items for the batch.
	 * @inheritdoc
	 * @since 3.5.0
	 */
	protected function get_items_for_batch( int $batch_number, array $args ): array {
		// Internal category query APIs don't provide batching, so we just return everything on the first batch
		if ( 1 === $batch_number ) {
			return FeedUploadUtils::get_navigation_menu_data();
		}
		// Return empty array after the first batch to trigger the next action
		return [];
	}
}
