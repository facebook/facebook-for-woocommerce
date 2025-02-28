<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_Async_Request', false ) ) {
	// Do not attempt to create this class without WP_Async_Request
	return;
}

/**
 * FB Graph API async request
 */
class WC_Facebookcommerce_Async_Request extends WP_Async_Request {

	/**
	 * Action name used for the async request
	 *
	 * @var string
	 */
	protected $action = 'wc_facebook_async_request';

	/**
	 * Handle
	 *
	 * Override this method to perform any actions required
	 * during the async request.
	 */
	protected function handle() {
		// Actions to perform
	}
}
