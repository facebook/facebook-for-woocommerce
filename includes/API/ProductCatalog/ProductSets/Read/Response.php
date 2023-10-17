<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\ProductCatalog\ProductSets\Read;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API\Response as ApiResponse;

/**
 * Response for the ProductSet Read Request.
 */
class Response extends ApiResponse {

	public function get_data() {
		$data = $this->response_data['data'];
		return $data;
	}
}
