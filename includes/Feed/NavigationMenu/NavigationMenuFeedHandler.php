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
 * Site Navigation Feed Handler Class. This file is responsible for the old-style feed generation for site navigation
 *
 * @package WooCommerce\Facebook\Feed
 * @since 3.5.0
 */
class NavigationMenuFeedHandler extends AbstractFeedHandler {

	/**
	 * Constructor.
	 *
	 * @param AbstractFeedFileWriter $feed_writer An instance of the JSON feed file writer.
	 */
	public function __construct( AbstractFeedFileWriter $feed_writer ) {
		$this->feed_writer = $feed_writer;
		$this->feed_type   = FeedManager::NAVIGATION_MENU;
	}

	/**
	 * Get the feed data and return as array of objects.
	 *
	 * @return array
	 * @since 3.5.0
	 */
	public function get_feed_data(): array {
		return FeedUploadUtils::get_navigation_menu_data();
	}
}
