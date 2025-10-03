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
	/** Header for the promotions feed file. @var string */
	const PROMOTIONS_FEED_HEADER = 'offer_id,title,value_type,percent_off,fixed_amount_off,application_type,target_type,target_shipping_option_types,target_granularity,target_selection,start_date_time,end_date_time,coupon_codes,public_coupon_code,target_filter,target_product_retailer_ids,target_product_group_retailer_ids,target_product_set_retailer_ids,redeem_limit_per_user,min_subtotal,min_quantity,offer_terms,target_quantity,prerequisite_filter,prerequisite_product_retailer_ids,prerequisite_product_group_retailer_ids,prerequisite_product_set_retailer_ids,exclude_sale_priced_products,usage_count,usage_limit' . PHP_EOL;

	/**
	 * Constructor for promotions feed.
	 *
	 * @since 3.5.0
	 */
	public function __construct() {
		$file_writer  = new CsvFeedFileWriter( self::get_data_stream_name(), self::PROMOTIONS_FEED_HEADER );
		$feed_handler = new PromotionsFeedHandler( $file_writer );

		$scheduler      = new ActionScheduler();
		$feed_generator = new PromotionsFeedGenerator( $scheduler, $file_writer, self::get_data_stream_name() );

		$this->init(
			$file_writer,
			$feed_handler,
			$feed_generator,
		);
	}

	protected static function get_feed_type(): string {
		return 'PROMOTIONS';
	}

	protected static function get_data_stream_name(): string {
		return FeedManager::PROMOTIONS;
	}

	protected static function get_feed_gen_interval(): int {
		return 2 * HOUR_IN_SECONDS;
	}
}
