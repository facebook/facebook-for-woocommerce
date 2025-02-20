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

/**
 * Example Feed Handler class
 *
 * Extends the FeedHandler interface to handle example feed file generation.
 *
 * @package WooCommerce\Facebook\Feed
 * @since 3.5.0
 */
class ExampleFeedHandler implements FeedHandler {
	/**
	 * The feed writer instance for the given feed.
	 *
	 * @var FeedFileWriter
	 * @since 3.5.0
	 */
	private FeedFileWriter $feed_writer;

	/**
	 * Constructor.
	 *
	 * @param FeedFileWriter $feed_writer An instance of csv feed writer.
	 * @since 3.5.0
	 */
	public function __construct( FeedFileWriter $feed_writer ) {
		$this->feed_writer = $feed_writer;
	}

	/**
	 * Generate the feed file.
	 *
	 * This method is responsible for generating a feed file.
	 *
	 * @since 3.5.0
	 */
	public function generate_feed_file() {
		// TODO: Implement generate_feed_file() method.
	}
}
