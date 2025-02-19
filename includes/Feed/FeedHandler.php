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
	 * Responsible for generating a feed file.
	 *
	 * @since 3.5.0
	 */
	public function generate_feed_file();
}
