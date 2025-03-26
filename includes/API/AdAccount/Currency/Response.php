<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\AdAccount\Currency;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API\Response as ApiResponse;

/**
 * Response object for Facebook Ad Account Currency request.
 *
 * @since x.x.x
 */
class Response extends ApiResponse {

	public function get_currency() {
		return $this->response_data['currency'];
	}
}
