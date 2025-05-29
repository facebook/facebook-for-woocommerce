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
 * Shipping Profiles Feed Handler class
 *
 * Extends the AbstractFeedHandler class to handle ratings and reviews feed file generation.
 *
 * @package WooCommerce\Facebook\Feed
 * @since 3.5.0
 */
class ShippingProfilesFeedHandler extends AbstractFeedHandler {

	/**
	 * Constructor.
	 *
	 * @param AbstractFeedFileWriter $feed_writer An instance of the CSV feed file writer.
	 */
	public function __construct( AbstractFeedFileWriter $feed_writer ) {
		$this->feed_writer = $feed_writer;
		$this->feed_type   = FeedManager::SHIPPING_PROFILES;
	}

	/**
	 * Get the feed data and return as an array.
	 *
	 * @return array
	 */
	public function get_feed_data(): array {
		return ShippingProfilesFeed::get_shipping_profiles_data();
	}
}
