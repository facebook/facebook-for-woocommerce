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

use WooCommerce\Facebook\Utilities\Heartbeat;

/**
 * Abstract class AbstractFeed
 *
 * Provides the base functionality for handling product feed requests and generation for Facebook integration.
 * This class defines the structure and common methods that must be implemented by any concrete feed class.
 *
 * @package WooCommerce\Facebook\ProductFeed
 * @since 1.11.0
 */
abstract class AbstractFeed {
	/** The action callback for generating a feed */
	const GENERATE_FEED_ACTION = 'wc_facebook_regenerate_feed_';

	/** The action slug for getting the product feed */
	const REQUEST_FEED_ACTION = 'wc_facebook_get_feed_data_';
	/** The action slug for triggering file upload */
	const FEED_GEN_COMPLETE_ACTION = 'wc_facebook_feed_generation_completed_';

	/**
	 * The name of the data stream to be synced via this feed.
	 *
	 * @var string
	 */
	private static string $data_stream_name;

	/**
	 * The feed generator instance for the given feed.
	 *
	 * @var FeedGenerator
	 */
	protected FeedGenerator $feed_generator;

	/**
	 * The feed handler instance for the given feed.
	 *
	 * @var FeedHandler
	 */
	protected FeedHandler $feed_handler;

	/**
	 * Constructor.
	 *
	 * Initializes the feed with the given data stream name and adds the necessary hooks.
	 *
	 * @param string $data_stream_name The name of the data stream.
	 * @param string $heartbeat The heartbeat interval for the feed generation.
	 */
	public function __construct( string $data_stream_name, string $heartbeat ) {
		self::$data_stream_name = $data_stream_name;
		$this->add_hooks( $heartbeat );
	}

	/**
	 * Adds the necessary hooks for feed generation and data request handling.
	 *
	 * @param string $heartbeat The heartbeat interval for the feed generation.
	 */
	private function add_hooks( string $heartbeat ) {
		add_action( $heartbeat, $this->schedule_feed_generation() );
		add_action( self::modify_action_name( self::GENERATE_FEED_ACTION ), $this->regenerate_feed() );
		add_action( self::modify_action_name( self::FEED_GEN_COMPLETE_ACTION ), $this->send_request_to_upload_feed() );
		add_action( 'woocommerce_api_' . self::modify_action_name( self::REQUEST_FEED_ACTION ), $this->handle_feed_data_request() );
	}

	/**
	 * Schedules the recurring feed generation.
	 *
	 * This method must be implemented by the concrete feed class, usually by providing a recurring interval
	 */
	abstract public function schedule_feed_generation();

	/**
	 * Regenerates the product feed.
	 *
	 * This method is responsible for initiating the regeneration of the product feed.
	 * The method ensures that the feed is regenerated based on the defined schedule.
	 */
	abstract public function regenerate_feed();

	/**
	 * Trigger the upload flow
	 *
	 * Once feed regenerated, trigger upload via create_upload API and trigger the action for handling the upload
	 */
	abstract public function send_request_to_upload_feed();

	/**
	 * Handles the feed data request.
	 *
	 * This method must be implemented by the concrete feed class.
	 */
	abstract public function handle_feed_data_request();

	/**
	 * Gets the URL for retrieving the product feed data.
	 *
	 * This method must be implemented by the concrete feed class.
	 *
	 * @return string The URL for retrieving the product feed data.
	 */
	abstract public static function get_feed_data_url(): string;

	/**
	 * Gets the secret value that should be included in the ProductFeed URL.
	 *
	 * This method must be implemented by the concrete feed class.
	 *
	 * @return string The secret value for the ProductFeed URL.
	 */
	abstract public static function get_feed_secret(): string;

	/**
	 * Modifies the action name by appending the data stream name.
	 *
	 * @param string $feed_name The base feed name.
	 *
	 * @return string The modified action name.
	 */
	protected static function modify_action_name( string $feed_name ): string {
		return $feed_name . self::$data_stream_name;
	}
}
