<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\ProductCatalog\ProductSets\Read;

use WooCommerce\Facebook\API\Request as ApiRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Request object for Product Catalog > Product Sets > Read Graph Api.
 *
 * @link https://developers.facebook.com/docs/marketing-api/reference/product-catalog/product_sets/
 */
class Request extends ApiRequest {

	/**
	 * @param string $product_catalog_id Facebook Product Catalog Id.
	 */
	public function __construct( $product_catalog_id ) {
		parent::__construct( "/{$product_catalog_id}/product_sets", 'GET' );
	}

	public function get_params() {
		return array( 'fields' => 'id,name,product_catalog,product_count' );
	}
}
