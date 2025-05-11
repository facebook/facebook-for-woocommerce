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
 * Abstract class AbstractFeedHandler
 *
 * Provides the base functionality for feed handlers.
 *
 * @package WooCommerce\Facebook\Feed
 * @since 3.5.0
 */
abstract class AbstractFeedHandler {

	/**
	 * The feed file writer instance.
	 *
	 * @var AbstractFeedFileWriter
	 * @since 3.5.0
	 */
	protected AbstractFeedFileWriter $feed_writer;

	/**
	 * The feed type identifier.
	 *
	 * @var string
	 * @since 3.5.0
	 */
	protected string $feed_type;

	/**
	 * Generate the feed file.
	 *
	 * This common implementation calls the feed writer with the feed data
	 * and then triggers an action hook with the feed type.
	 *
	 * @return void
	 * @since 3.5.0
	 */
	public function generate_feed_file(): void {
		$this->feed_writer->write_feed_file( $this->get_feed_data() );
		do_action( AbstractFeed::FEED_GEN_COMPLETE_ACTION . $this->feed_type );
	}

	/**
	 * Get the feed file writer instance.
	 *
	 * @return AbstractFeedFileWriter
	 * @since 3.5.0
	 */
	public function get_feed_writer(): AbstractFeedFileWriter {
		return $this->feed_writer;
	}

	/**
	 * Get the feed data as an array.
	 *
	 * Concrete classes must implement this method to return their unique data.
	 *
	 * @return array
	 * @since 3.5.0
	 */
	abstract public function get_feed_data(): array;
}
