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
 * Ratings and Reviews Feed Handler class
 *
 * Extends the AbstractFeedHandler class to handle ratings and reviews feed file generation.
 *
 * @package WooCommerce\Facebook\Feed
 * @since 3.5.0
 */
class RatingsAndReviewsFeedHandler extends AbstractFeedHandler {

	/**
	 * Constructor.
	 *
	 * @param AbstractFeedFileWriter $feed_writer An instance of the CSV feed file writer.
	 */
	public function __construct( AbstractFeedFileWriter $feed_writer ) {
		$this->feed_writer = $feed_writer;
		$this->feed_type   = FeedManager::RATINGS_AND_REVIEWS;
	}

	/**
	 * Get the feed data and return as an array.
	 *
	 * @return array
	 */
	public function get_feed_data(): array {
		$query_args = array(
			'status' => 'approve',
		);

		return FeedUploadUtils::get_ratings_and_reviews_data( $query_args );
	}
}
