<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\API\ProductCatalog\ItemsBatch\Create;

use WooCommerce\Facebook\API\Request as ApiRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Request object for Product Catalog > Items Batch > Create Graph Api.
 *
 * @link https://developers.facebook.com/docs/marketing-api/reference/product-catalog/items_batch/
 */
class Request extends ApiRequest {

	/**
	 * @param string $product_catalog_id Facebook Product Catalog ID.
	 * @param array  $requests Array of JSON objects containing batch requests. Each batch request consists of method and data fields.
	 */
	public function __construct( string $product_catalog_id, array $requests ) {
		parent::__construct( "/{$product_catalog_id}/items_batch", 'POST' );
		$data = [
			'allow_upsert' => true,
			'requests'     => $requests,
			'item_type'    => 'PRODUCT_ITEM',
		];
		parent::set_data( $data );
	}
}
