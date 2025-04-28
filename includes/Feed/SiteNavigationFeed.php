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
 * Site Navigation Feed Class
 *
 * Extends Abstract Feed class to handle site navigation feed requests and generation for Facebook integration.
 *
 * @package WooCommerce\Facebook\Feed
 * @since 3.5.0
 */
class SiteNavigationFeed extends AbstractFeed {
	/**
	 * Constructor for site navigation feed.
	 *
	 * @since 3.5.0
	 */
	public function __construct() {
		$file_writer  = new JsonFeedFileWriter( self::get_data_stream_name(), '' );
		$feed_handler = new SiteNavigationFeedHandler( $file_writer );

		$scheduler      = new ActionScheduler();
		$feed_generator = new SiteNavigationFeedGenerator( $scheduler, $file_writer, self::get_data_stream_name() );

		$this->init(
			$file_writer,
			$feed_handler,
			$feed_generator,
		);
	}

	protected static function get_feed_type(): string {
		return 'SITE_NAVIGATION';
	}

	protected static function get_data_stream_name(): string {
		return FeedManager::SITE_NAVIGATION;
	}

	protected static function get_feed_gen_interval(): int {
		return DAY_IN_SECONDS;
	}
}
