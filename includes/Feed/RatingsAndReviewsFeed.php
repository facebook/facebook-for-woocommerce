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
use WooCommerce\Facebook\Feed\AbstractFeed;
use WooCommerce\Facebook\Feed\CsvFeedFileWriter;
use WooCommerce\Facebook\Feed\RatingsAndReviewsFeedHandler;
use WooCommerce\Facebook\Feed\FeedManager;
use WooCommerce\Facebook\Framework\Api\Exception;
use WooCommerce\Facebook\Utilities\Heartbeat;

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
		$this->data_stream_name            = FeedManager::RATINGS_AND_REVIEWS;
		$this->gen_feed_interval           = WEEK_IN_SECONDS;
		$this->feed_type                   = 'PRODUCT_RATINGS_AND_REVIEWS';
		$this->feed_url_secret_option_name = self::OPTION_FEED_URL_SECRET . $this->data_stream_name;

		$this->feed_handler   = new RatingsAndReviewsFeedHandler( new CsvFeedFileWriter( $this->data_stream_name, self::RATINGS_AND_REVIEWS_FEED_HEADER ) );
		$scheduler            = new ActionScheduler();
		$this->feed_generator = new RatingsAndReviewsFeedGenerator( $scheduler, $this->feed_handler->get_feed_writer(), $this->data_stream_name );
		$this->feed_generator->init();
		$this->add_hooks( Heartbeat::HOURLY );
	}

	/**
	 * Adds the necessary hooks for feed generation and data request handling.
	 *
	 * @param string $heartbeat The heartbeat interval for the feed generation.
	 *
	 * @since 3.5.0
	 */
	protected function add_hooks( string $heartbeat ): void {
		add_action( $heartbeat, array( $this, self::SCHEDULE_CALL_BACK ) );
		add_action( self::GENERATE_FEED_ACTION . $this->data_stream_name, array( $this, self::REGENERATE_CALL_BACK ) );
		add_action( self::FEED_GEN_COMPLETE_ACTION . $this->data_stream_name, array( $this, self::UPLOAD_CALL_BACK ) );
		add_action( self::LEGACY_API_PREFIX . self::REQUEST_FEED_ACTION . $this->data_stream_name, array(
			$this,
			self::STREAM_CALL_BACK
		) );
	}
}
