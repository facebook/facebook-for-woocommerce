<?php
/** Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
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
	const RATINGS_AND_REVIEWS_FEED_HEADER = 'aggregator,store.name,store.id,store.storeUrls,review_id,rating,title,content,created_at,reviewer.name,reviewer.reviewerID,reviewer.isAnonymous,product.name,product.url,product.productIdentifiers.skus' . PHP_EOL;

	/**
	 * Constructor.
	 *
	 * @since 3.5.0
	 */
	public function __construct() {
		$data_stream_name  = FeedManager::RATINGS_AND_REVIEWS;
		$gen_feed_interval = WEEK_IN_SECONDS;
		$feed_type         = 'PRODUCT_RATINGS_AND_REVIEWS';

		$file_writer    = new CsvFeedFileWriter( $data_stream_name, self::RATINGS_AND_REVIEWS_FEED_HEADER );
		$feed_handler   = new RatingsAndReviewsFeedHandler( $file_writer );
		$scheduler      = new ActionScheduler();
		$feed_generator = new RatingsAndReviewsFeedGenerator( $scheduler, $file_writer, $data_stream_name );

		$this->init(
			$data_stream_name,
			$feed_type,
			$gen_feed_interval,
			$file_writer,
			$feed_handler,
			$feed_generator,
		);
	}
}
