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
class PromotionsFeedHandler implements FeedHandler {
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
		do_action( AbstractFeed::FEED_GEN_COMPLETE_ACTION . FeedManager::PROMOTIONS );
	}

	/**
	 * Get the feed data and return as array of objects.
	 * Array contents should match headers in PromotionsFeed::PROMOTIONS_FEED_HEADER
	 *
	 * @return array
	 * @since 3.5.0
	 */
	public function get_feed_data(): array {
		$promos_data = array();

		$promos_data[] = array(
			'retailer_id' => '99',
			'title'       => '10% Off',
		);
		return $promos_data;
	}
}
