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

interface FeedFileWriter {
	/**
	 * Write the feed file.
	 */
	public function write_feed_file();

	/**
	 * Creates files in the catalog feed directory to prevent directory listing and hotlinking.
	 *
	 * * Todo: add since
	 */
	public function create_files_to_protect_feed_directory();

	/**
	 * Gets the feed file path of given feed.
	 *
	 * @return string
	 * * Todo: add since
	 */
	public function get_file_path(): string;


	/**
	 * Gets the temporary feed file path.
	 *
	 * @return string
	 * * Todo: add since
	 */
	public function get_temp_file_path(): string;

	/**
	 * Gets the feed file directory.
	 *
	 * @return string
	 * * Todo: add since
	 */
	public function get_file_directory(): string;


	/**
	 * Gets the feed file name.
	 *
	 * @return string
	 * * Todo: add since
	 */
	public function get_file_name(): string;

	/**
	 * Gets the temporary feed file name.
	 *
	 * @param string $secret The secret to use for the temporary file name.
	 *
	 * @return string
	 * * Todo: add since
	 */
	public function get_temp_file_name( string $secret ): string;
}
