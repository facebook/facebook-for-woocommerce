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
 * Site Navigation Feed Generator Class
 *
 * Sit Navigation Feed Generator Class. This file is responsible for the new-style feed generation for site navigation
 *
 * @package WooCommerce\Facebook\Feed
 * @since 3.5.0
 */
class SiteNavigationFeedGenerator extends FeedGenerator {
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
		return FeedUploadUtils::get_site_navigation_data();
	}
}
