<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook;

defined( 'ABSPATH' ) || exit;

/**
 * WPML Language Status enum-like class.
 *
 * Defines visibility status constants for WPML language integration.
 *
 * @since 1.9.10
 */
class WPMLLanguageStatus {

	/**
	 * Language is visible in Facebook catalog.
	 */
	const VISIBLE = 1;

	/**
	 * Language is hidden from Facebook catalog.
	 */
	const HIDDEN = 2;

	/**
	 * Language has not been synced.
	 */
	const NOT_SYNCED = 0;
}
