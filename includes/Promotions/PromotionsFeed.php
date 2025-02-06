<?php
/** Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Promotions;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\Feed\AbstractFeed;

/**
 * Promotions Feed class
 *
 * Extends Abstract Feed class to handle promotions feed requests and generation for Facebook integration.
 *
 * @package WooCommerce\Facebook\ProductFeed
 * @since 1.11.0
 */
class PromotionsFeed extends AbstractFeed {
	/**
	 * Schedules the recurring feed generation.
	 *
	 * This method must be implemented by the concrete feed class, usually by providing a recurring interval
	 */
	public function schedule_feed_generation() {
		// TODO: Implement schedule_feed_generation() method.
	}

	/**
	 * Regenerates the product feed.
	 *
	 * This method is responsible for initiating the regeneration of the product feed.
	 * The method ensures that the feed is regenerated based on the defined schedule.
	 */
	public function regenerate_feed() {
		// Maybe use new ( experimental ), feed generation framework.
		if ( facebook_for_woocommerce()->get_integration()->is_new_style_feed_generation_enabled() ) {
			$generate_factory = facebook_for_woocommerce()->job_manager->generator_factory;
			$generator        = $generate_factory->get_feed_generator( 'PromotionsFeedGenerator' );
			$generator->queue_start();
		} else {
			$feed_handler = new \WC_Facebook_Product_Feed();
			$feed_handler->generate_feed();
		}
	}

	/**
	 * Trigger the upload flow
	 *
	 * Once feed regenerated, trigger upload via create_upload API and trigger the action for handling the upload
	 */
	public function send_request_to_upload_feed() {
		// TODO: Implement send_request_to_upload_feed() method.
	}

	/**
	 * Handles the feed data request.
	 *
	 * This method must be implemented by the concrete feed class.
	 */
	public function handle_feed_data_request() {
		// TODO: Implement handle_feed_data_request() method.
	}

	/**
	 * Gets the URL for retrieving the product feed data.
	 *
	 * This method must be implemented by the concrete feed class.
	 *
	 * @return string The URL for retrieving the product feed data.
	 */
	public static function get_feed_data_url(): string {
		// TODO: Implement get_feed_data_url() method.
		return '';
	}

	/**
	 * Gets the secret value that should be included in the ProductFeed URL.
	 *
	 * This method must be implemented by the concrete feed class.
	 *
	 * @return string The secret value for the ProductFeed URL.
	 */
	public static function get_feed_secret(): string {
		// TODO: Implement get_feed_secret() method.
		return '';
	}
}
