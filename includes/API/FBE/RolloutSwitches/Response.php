<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\API\FBE\RolloutSwitches;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API;

/**
 * FBE RolloutSwitches API read response object.
 */
class Response extends API\Response {

	/**
	 * Gets the response data.
	 *
	 * @return array
	 */
	public function get_data() {
		return $this->response_data['data'] ?? [];
	}
}
