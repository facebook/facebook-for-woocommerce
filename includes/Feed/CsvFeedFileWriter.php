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
 *
 * CsvFeedFileWriter class
 * To be used by the feed handler to write its updates to its feed file.
 * To be used by any feed handler whose feed requires a csv file.
 */
class CsvFeedFileWriter implements FeedFileWriter {
	/**
	 * Use the feed name to distinguish which folder to write to.
	 *
	 * @var string
	 */
	private $feed_name;

	/**
	 * Constructor.
	 *
	 * @param string $feed_name The name of the feed.
	 */
	public function __construct( string $feed_name ) {
		$this->feed_name = $feed_name;
	}

	/**
	 * Write the feed file.
	 *
	 * @return void
	 */
	public function write_feed_file() {
		// TODO: Implement write_feed_file() method.
	}

	/**
	 * Creates files in the catalog feed directory to prevent directory listing and hotlinking.
	 *
	 * @since 1.11.0
	 */
	public function create_files_to_protect_product_feed_directory() {
	}

	/**
	 * Gets the feed file path of given feed.
	 *
	 * @return string
	 * @since 1.11.0
	 */
	public function get_file_path(): string {
		return '';
	}


	/**
	 * Gets the temporary feed file path.
	 *
	 * @return string
	 * @since 1.11.3
	 */
	public function get_temp_file_path(): string {
		return '';
	}

	/**
	 * Gets the feed file directory.
	 *
	 * @return string
	 * @since 1.11.0
	 */
	public function get_file_directory(): string {
		return '';
	}


	/**
	 * Gets the feed file name.
	 *
	 * @return string
	 * @since 1.11.0
	 */
	public function get_file_name(): string {
		return '';
	}

	/**
	 * Gets the temporary feed file name.
	 *
	 * @param string $secret The secret used to generate the file name.
	 *
	 * @return string
	 * @since 1.11.3
	 */
	public function get_temp_file_name( string $secret ): string {
		return $secret;
	}
}
