<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\API\ProductCatalog\LocalizedItemsBatch\Create;

use WooCommerce\Facebook\API\Response as BaseResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Response object for Product Catalog localized items batch API.
 *
 * @since 3.6.0
 */
class Response extends BaseResponse {

	/**
	 * Gets the handles from the response.
	 *
	 * @since 3.6.0
	 *
	 * @return array
	 */
	public function get_handles() {
		return $this->handles ?? array();
	}
}
