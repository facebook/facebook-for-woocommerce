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
 * Promotions Feed Handler Class. This file is responsible for the old-style feed generation for promotions
 *
 * @package WooCommerce\Facebook\Feed
 * @since 3.5.0
 */
class PromotionsFeedHandler extends AbstractFeedHandler {

	/**
	 * Constructor.
	 *
	 * @param AbstractFeedFileWriter $feed_writer An instance of the CSV feed file writer.
	 */
	public function __construct( AbstractFeedFileWriter $feed_writer ) {
		$this->feed_writer = $feed_writer;
		$this->feed_type   = FeedManager::PROMOTIONS;
	}

	/**
	 * Get the feed data and return as array of objects.
	 * Array contents should match headers in PromotionsFeed::PROMOTIONS_FEED_HEADER
	 *
	 * @return array
	 * @since 3.5.0
	 */
	public function get_feed_data(): array {
		$query_args = array(
			'post_type'      => 'shop_coupon',
			'post_status'    => 'publish',
			'posts_per_page' => - 1, // retrieve all items
		);

		return FeedUploadUtils::get_coupons_data( $query_args );
	}
}
