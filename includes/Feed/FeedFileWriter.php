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
	 *
	 * @param array $data The data to write to the feed file.
	 * @since 3.5.0
	 */
	public function write_feed_file( array $data );
	// Shared logic between all file writers
	// Just need to handle setting the correct event_type

	/**
	 * Create feed directory.
	 *
	 * @since 3.5.0
	 */
	public function create_feed_directory();
	// Shared logic between all file writers

	/**
	 * Creates files in the catalog feed directory to prevent directory listing and hotlinking.
	 *
	 * @since 3.5.0
	 */
	public function create_files_to_protect_feed_directory();
	// Shared logic between all file writers

	/**
	 * Gets the feed file path of given feed.
	 *
	 * @return string
	 * @since 3.5.0
	 */
	public function get_file_path(): string;
	// Shared logic between all file writers


	/**
	 * Gets the temporary feed file path.
	 *
	 * @return string
	 * @since 3.5.0
	 */
	public function get_temp_file_path(): string;
	// Shared logic between all file writers

	/**
	 * Gets the feed file directory.
	 *
	 * @return string
	 * @since 3.5.0
	 */
	public function get_file_directory(): string;
	// Shared logic between all file writers


	/**
	 * Gets the feed file name.
	 *
	 * @return string
	 * @since 3.5.0
	 */
	public function get_file_name(): string;
	// Shared logic between all file writers

	/**
	 * Gets the temporary feed file name.
	 *
	 * @return string
	 * @since 3.5.0
	 */
	public function get_temp_file_name(): string;
	// Shared logic between all file writers

	/**
	 * Prepare a fresh empty temporary feed file with the header row.
	 *
	 * @return resource A file pointer resource.
	 * @since 3.5.0
	 */
	public function prepare_temporary_feed_file();
	// Shared logic between all file writers

	/**
	 * Promote the temporary feed file to the final feed file.
	 *
	 * @since 3.5.0
	 */
	public function promote_temp_file();
	// Shared logic between all file writers

	/**
	 * Write to the temp feed file.
	 *
	 * @param array $data The data to write to the feed file.
	 * @since 3.5.0
	 */
	public function write_temp_feed_file( array $data );
	// Logic specific to type of file writer
}
