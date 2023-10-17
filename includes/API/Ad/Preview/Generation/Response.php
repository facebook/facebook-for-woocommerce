<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\Ad\Preview\Generation;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API\Response as ApiResponse;

/**
 * Response object for Facebook Ad Preview request.
 *
 * @since x.x.x
 */
class Response extends ApiResponse {

	public function get_preview() {
		$data = $this->response_data['data'][0]['body'];
		return $data;
	}
}
