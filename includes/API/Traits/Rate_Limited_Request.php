<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\API\Traits;

defined( 'ABSPATH' ) || exit;

/**
 * Rate limited request trait.
 *
 * @since 2.0.0
 */
trait Rate_Limited_Request {

	/**
	 * Gets the ID of this request for rate limiting purposes.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public static function get_rate_limit_id() {
		return 'graph_api_request';
	}
}
