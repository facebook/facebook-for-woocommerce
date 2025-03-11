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
 * Extends the FeedHandler interface to handle ratings and reviews feed file generation.
 *
 * @package WooCommerce\Facebook\Feed
 * @since 3.5.0
 */
class RatingsAndReviewsFeedHandler implements FeedHandler {
	/**
	 * The feed writer instance for the given feed.
	 *
	 * @var FeedFileWriter
	 * @since 3.5.0
	 */
	private FeedFileWriter $feed_writer;

	/**
	 * Constructor.
	 *
	 * @param FeedFileWriter $feed_writer An instance of csv feed writer.
	 *
	 * @since 3.5.0
	 */
	public function __construct( FeedFileWriter $feed_writer ) {
		$this->feed_writer = $feed_writer;
	}

	/**
	 * Generate the feed file.
	 *
	 * This method is responsible for generating a feed file.
	 *
	 * @since 3.5.0
	 */
	public function generate_feed_file(): void {
		$this->feed_writer->write_feed_file( $this->get_feed_data() );
		/**
		 * Trigger upload from RatingsAndReviewsFeed instance
		 *
		 * @since 3.5.0
		 */
		do_action( AbstractFeed::FEED_GEN_COMPLETE_ACTION . FeedManager::RATINGS_AND_REVIEWS );
	}

	/**
	 * Get the feed file writer instance.
	 *
	 * @return FeedFileWriter
	 * @since 3.5.0
	 */
	public function get_feed_writer(): FeedFileWriter {
		return $this->feed_writer;
	}

	/**
	 * Get the feed data and return as array of objects.
	 *
	 * @return array
	 * @since 3.5.0
	 */
	public function get_feed_data(): array {
		$query_args = array(
			'status' => 'approve',
		);

		return FeedUploadUtils::get_ratings_and_reviews_data( $query_args );
	}
}
