<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\API\REST\Settings\Uninstall;

use WooCommerce\Facebook\API\REST\Request as RESTRequest;
use WooCommerce\Facebook\API\REST\Traits\JS_Exposable;

defined( 'ABSPATH' ) || exit;

/**
 * Settings Uninstall REST API Request.
 *
 * @since 2.3.5
 */
class Request extends RESTRequest {

	use JS_Exposable;

	/**
	 * Validate the request.
	 *
	 * @since 2.3.5
	 *
	 * @return true|\WP_Error True if valid, WP_Error otherwise.
	 */
	public function validate() {
		// No specific validation needed for uninstall
		return true;
	}
} 