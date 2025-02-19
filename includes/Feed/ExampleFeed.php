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
use WooCommerce\Facebook\Utilities\Heartbeat;

/**
 * Example Feed class
 *
 * Extends Abstract Feed class to handle example feed requests and generation for Facebook integration.
 *
 * @package WooCommerce\Facebook\Feed
 * * Todo: add since
 */
class ExampleFeed extends AbstractFeed {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$data_stream_name     = FeedManager::EXAMPLE;
		$this->feed_handler   = new ExampleFeedHandler( new CsvFeedFileWriter( $data_stream_name ) );
		$scheduler            = new ActionScheduler();
		$this->feed_generator = new ExampleFeedGenerator( $scheduler, $this->feed_handler );
		$this->feed_generator->init();

		parent::__construct( $data_stream_name, Heartbeat::HOURLY );
	}

	/**
	 * Schedules the recurring feed generation.
	 *
	 * This method must be implemented by the concrete feed class, usually by providing a recurring interval
	 */
	public function schedule_feed_generation() {
		// TODO: Implement schedule_feed_generation() method.
	}

	/**
	 * Regenerates the example feed.
	 *
	 * This method is responsible for initiating the regeneration of the example feed.
	 * The method ensures that the feed is regenerated based on the defined schedule.
	 */
	public function regenerate_feed() {
		// Maybe use new ( experimental ), feed generation framework.
		if ( facebook_for_woocommerce()->get_integration()->is_new_style_feed_generation_enabled() ) {
			$this->feed_generator->queue_start();
		} else {
			$this->feed_handler->generate_feed_file();
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
	 * Allows an admin to schedule the feed generation immediately.
	 */
	public function schedule_feed_generation_immediately() {
		// TODO: Implement schedule_feed_generation_immediately() method.
	}

	/**
	 * Gets the URL for retrieving the feed data.
	 *
	 * This method must be implemented by the concrete feed class.
	 *
	 * @return string The URL for retrieving the feed data.
	 */
	public static function get_feed_data_url(): string {
		// TODO: Implement get_feed_data_url() method.
		return '';
	}

	/**
	 * Gets the secret value that should be included in the ExampleFeed URL.
	 *
	 * This method must be implemented by the concrete feed class.
	 *
	 * @return string The secret value for the ExampleFeed URL.
	 */
	public static function get_feed_secret(): string {
		// TODO: Implement get_feed_secret() method.
		return '';
	}
}
