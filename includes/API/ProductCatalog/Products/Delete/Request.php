<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\API\ProductCatalog\Products\Delete;

use WooCommerce\Facebook\API\Request as ApiRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Request object for Product Catalog > Products > Delete Graph Api.
 *
 * @link https://developers.facebook.com/docs/marketing-api/reference/product-item/#Deleting
 */
class Request extends ApiRequest {

	/**
	 * @param string $facebook_product_id Facebook Product Group ID.
	 */
	public function __construct( string $facebook_product_id ) {
		parent::__construct( "/{$facebook_product_id}", 'DELETE' );
	}
}
