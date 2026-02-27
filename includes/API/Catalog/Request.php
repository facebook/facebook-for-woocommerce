<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\API\Catalog;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API\Request as ApiRequest;

/**
 * Request object for the Catalog API.
 *
 * @link https://developers.facebook.com/docs/marketing-api/reference/product-catalog/v13.0
 */
class Request extends ApiRequest {
	/**
	 * Gets the rate limit ID.
	 *
	 * @return string
	 */
	public static function get_rate_limit_id(): string {
		return 'ads_management';
	}


	/**
	 * API request constructor.
	 *
	 * @param string $catalog_id catalog ID
	 */
	public function __construct( string $catalog_id ) {
		parent::__construct( "/{$catalog_id}?fields=name", 'GET' );
	}
}
