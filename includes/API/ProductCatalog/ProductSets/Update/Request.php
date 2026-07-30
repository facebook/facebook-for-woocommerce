<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\API\ProductCatalog\ProductSets\Update;

use WooCommerce\Facebook\API\Request as ApiRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Request object for Product Catalog > Product Sets > Update Graph Api.
 *
 * @link https://developers.facebook.com/docs/marketing-api/reference/product-catalog/product_sets/
 */
class Request extends ApiRequest {

	/**
	 * @param string $product_set_id Facebook Product Set ID.
	 * @param array  $data Facebook Product Set Data.
	 */
	public function __construct( string $product_set_id, array $data ) {
		parent::__construct( "/{$product_set_id}", 'POST' );
		parent::set_data( $data );
	}
}
