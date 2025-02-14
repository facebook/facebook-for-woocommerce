<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Promotions;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\Feed\FeedFileWriter;
use WooCommerce\Facebook\Feed\FeedHandler;

/**
 * Promotions Feed Handler class
 *
 * Extends the FeedHandler interface to handle promotions feed file generation.
 *
 * @package WooCommerce\Facebook\ProductFeed
 * @since 1.11.0
 */
class PromotionsFeedHandler implements FeedHandler {
	const FEED_NAME = 'Promotions Feed';

	/**
	 * The feed writer instance for the given feed.
	 *
	 * @var FeedFileWriter
	 */
	private FeedFileWriter $feed_writer;

	/**
	 * Constructor.
	 *
	 * @param FeedFileWriter $feed_writer An instance of csv feed writer.
	 */
	public function __construct( FeedFileWriter $feed_writer ) {
		$this->feed_writer = $feed_writer;
	}

	/**
	 * Generate the feed file.
	 *
	 * This method is responsible for generating a feed file.
	 */
	public function generate_feed_file() {
		// TODO: Implement generate_feed_file() method.
	}

	/**
	 * Get the feed upload status.
	 *
	 * @param array $settings the settings of the facebook integration.
	 *
	 * @return string the status of the feed upload.
	 */
	public function get_feed_upload_status( array $settings ): string {
		// TODO: Implement get_feed_upload_status() method.
		return '';
	}

	/**
	 * Get the item ids to sync for the feed writer.
	 *
	 * @return array
	 */
	public function get_item_ids_to_sync(): array {
		// TODO: Implement get_item_ids_to_sync() method.
		return array();
	}
}
