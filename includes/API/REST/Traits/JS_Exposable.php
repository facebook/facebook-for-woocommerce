<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\API\REST\Traits;

defined( 'ABSPATH' ) || exit;

/**
 * Trait for request classes that should be exposed to JavaScript.
 *
 * @since 2.3.5
 */
trait JS_Exposable {

	/**
	 * Indicates that this request should be exposed to JavaScript.
	 *
	 * @since 2.3.5
	 *
	 * @return bool
	 */
	public static function is_js_exposable() {
		return true;
	}
} 