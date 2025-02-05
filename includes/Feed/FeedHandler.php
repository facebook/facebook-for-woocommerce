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

interface FeedHandler {
	/**
	 * Generate the feed file.
	 *
	 * This method is responsible for generating a feed file.
	 */
	public function generate_feed_file();

	/**
	 * Get the feed upload status.
	 *
	 * @param array $settings the settings of the facebook integration.
	 *
	 * @return string the status of the feed upload.
	 */
	public function get_feed_upload_status( array $settings ): string;

	/**
	 * Get the item ids to sync for the feed writer.
	 *
	 * @return array
	 */
	public function get_item_ids_to_sync(): array;
}
