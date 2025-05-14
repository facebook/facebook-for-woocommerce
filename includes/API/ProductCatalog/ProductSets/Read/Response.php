<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\ProductCatalog\ProductSets\Read;

use WooCommerce\Facebook\API\Response as ApiResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Response object for Product Catalog > Product Groups > Get Graph Api.
 *
 * @link https://developers.facebook.com/docs/marketing-api/reference/product-catalog/product_sets/
 * @property-read string id Facebook Product Set ID.
 *
 * @since 3.4.9
 */
class Response extends ApiResponse {

	/**
	 * Returns the fb product set ID.
	 *
	 * @return ?string
	 * @since 3.4.9
	 */
	public function get_product_set_id(): ?string {
		return $this->data[0]['id'] ?? null;
	}
}
