<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\ProductCatalog\Products\Id;

use WooCommerce\Facebook\API\Response as ApiResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Response object for Product Catalog > Product Groups > Update Graph Api.
 *
 * @link https://developers.facebook.com/docs/marketing-api/reference/product-catalog/products/
 * @property-read string id Either request was successful or not.
 * @property-read array product_group Product group data container containing facebook product group id
 *                                    e.g. product_group => [ id => <facebook product group id>]
 */
class Response extends ApiResponse {
	/**
	 * Returns product's Facebook product group id.
	 *
	 * @return string
	 */
	public function get_facebook_product_group_id(): string {
		return $this->product_group['id'] ?? '';
	}
}
