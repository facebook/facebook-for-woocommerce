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

use WooCommerce\Facebook\Feed\AbstractFeed;
use WooCommerce\Facebook\Utilities\Heartbeat;


/**
 * Promotions Feed Class
 *
 * Extends Abstract Feed class to handle promotion/coupon/discount feed requests and generation for Facebook integration.
 *
 * @package WooCommerce\Facebook\Feed
 * @since 3.5.0
 */
class PromotionsFeed extends AbstractFeed {
	/** Header for the ratings and reviews feed file. @var string */
	const PROMOTIONS_FEED_HEADER = 'retailer_id,title' . PHP_EOL;

	/**
	 * Constructor.
	 *
	 * @since 3.5.0
	 */
	public function __construct() {
		$data_stream_name  = FeedManager::PROMOTIONS;
		$gen_feed_interval = DAY_IN_SECONDS;
		$feed_type         = 'PROMOTIONS'; // CatalogPartnerPlatformFileFeedType
		$file_writer       = new CsvFeedFileWriter( $data_stream_name, self::PROMOTIONS_FEED_HEADER );
		$feed_handler      = new PromotionsFeedHandler( $file_writer );

		$this->init(
			$data_stream_name,
			$feed_type,
			$gen_feed_interval,
			$feed_handler
		);
	}

	/**
	 * Regenerates the ratings and reviews feed based on the defined schedule.
	 * Override to only use the FeedHandler to generate the feed file as batch is not needed.
	 *
	 * @since 3.5.0
	 * @override
	 */
	public function regenerate_feed(): void {
		$this->feed_handler->generate_feed_file();
	}
}
