<?php
/** Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Feed;

defined( 'ABSPATH' ) || exit;

/**
 * FeedType for use as an enum
 * Add types as they are onboarded.
 */
class FeedType {
	const PROMOTIONS = 'promotions';
	const PRODUCTS = 'products';
}
