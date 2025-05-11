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

use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionScheduler;

/**
 * Ratings and Reviews Feed class
 *
 * Extends Abstract Feed class to handle ratings and reviews feed requests and generation for Facebook integration.
 *
 * @package WooCommerce\Facebook\Feed
 * @since 3.5.0
 */
class RatingsAndReviewsFeed extends AbstractFeed {
	/** Header for the ratings and reviews feed file. @var string */
	const RATINGS_AND_REVIEWS_FEED_HEADER = 'aggregator,store.name,store.id,store.storeUrls,review_id,rating,title,content,created_at,updated_at,review_image_urls,incentivized,has_verified_purchase,reviewer.name,reviewer.reviewerID,reviewer.isAnonymous,product.name,product.url,product.productIdentifiers.skus' . PHP_EOL;

	/**
	 * Constructor.
	 *
	 * @since 3.5.0
	 */
	public function __construct() {
		$file_writer  = new CsvFeedFileWriter( self::get_data_stream_name(), self::RATINGS_AND_REVIEWS_FEED_HEADER );
		$feed_handler = new RatingsAndReviewsFeedHandler( $file_writer );

		$scheduler      = new ActionScheduler();
		$feed_generator = new RatingsAndReviewsFeedGenerator( $scheduler, $file_writer, self::get_data_stream_name() );

		$this->init(
			$file_writer,
			$feed_handler,
			$feed_generator,
		);
	}

	protected static function get_feed_type(): string {
		return 'PRODUCT_RATINGS_AND_REVIEWS';
	}

	protected static function get_data_stream_name(): string {
		return FeedManager::RATINGS_AND_REVIEWS;
	}

	protected static function get_feed_gen_interval(): int {
		return WEEK_IN_SECONDS;
	}
}
