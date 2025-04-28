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
 * Navigation Menu Feed Generator Class
 *
 * Navigation Menu Feed Generator Class. This file is responsible for the new-style feed generation for navigation menu
 *
 * @package WooCommerce\Facebook\Feed
 * @since 3.5.0
 */
class NavigationMenuFeedGenerator extends FeedGenerator {
	/**
	 * Flag to track if data has been returned.
	 *
	 * @var bool
	 * @since 3.5.0
	 */
	private bool $data_returned = false;

	/**
	 * Retrieves items for a specific batch.
	 *
	 * @param int   $batch_number The batch number.
	 * @param array $args Additional arguments.
	 *
	 * @return array The items for the batch.
	 * @inheritdoc
	 * @since 3.5.0
	 */
	protected function get_items_for_batch( int $batch_number, array $args ): array {
		if ( $this->data_returned ) {
			return [];
		}
		$navigation_menu_data = FeedUploadUtils::get_navigation_menu_data();
		$this->data_returned  = true;
		return $navigation_menu_data;
	}

	/**
	 * Handles the start of the feed generation process.
	 *
	 * @inheritdoc
	 * @since 3.5.0
	 */
	protected function handle_start(): void {
		// Create directory if not available and then the files to protect the directory.
		$this->feed_writer->create_files_to_protect_feed_directory();
		$this->feed_writer->prepare_temporary_feed_file();
		$this->data_returned = false;
	}
}
