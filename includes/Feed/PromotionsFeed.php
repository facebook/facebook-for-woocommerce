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

		$file_writer  = new CsvFeedFileWriter( $data_stream_name, self::PROMOTIONS_FEED_HEADER );
		$feed_handler = new PromotionsFeedHandler( $file_writer );

		$scheduler      = new ActionScheduler();
		$feed_generator = new PromotionsFeedGenerator( $scheduler, $file_writer, $data_stream_name );

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
