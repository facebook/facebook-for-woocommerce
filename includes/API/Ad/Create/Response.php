<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\Ad\Create;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API\Response as ApiResponse;

/**
 * Response object for Facebook Ad Creation request.
 *
 * @since x.x.x
 */
class Response extends ApiResponse {

	public function get_data() {
		return $this->response_data;
	}
}
