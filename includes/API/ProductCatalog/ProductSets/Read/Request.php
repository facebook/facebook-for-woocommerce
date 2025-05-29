<?php

declare(strict_types=1);

namespace WooCommerce\Facebook\API\ProductCatalog\ProductSets\Read;

use WooCommerce\Facebook\API\Request as ApiRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Request object for Product Catalog > Product Sets > Get Graph Api.
 *
 * @link https://developers.facebook.com/docs/marketing-api/reference/product-catalog/product_sets/
 */
class Request extends ApiRequest {

	/**
	 * @param string $product_catalog_id Facebook Product Catalog ID.
	 * @param string $retailer_id Facebook Product Set Retailer ID.
	 */
	public function __construct( string $product_catalog_id, string $retailer_id ) {
		parent::__construct( "/{$product_catalog_id}/product_sets", 'GET' );
		parent::set_params(
			array( 'retailer_id' => $retailer_id )
		);
	}
}
