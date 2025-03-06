<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\Campaign;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API\Response as ApiResponse;

/**
 * Response object for Facebook Ad Campaign Creation/Update request.
 *
 * @since x.x.x
 */
class Response extends ApiResponse {

	public function get_data() {
		return $this->response_data['data'];
	}

	public function get_campaign() {
		return array(
			'id'   => $this->response_data['id'],
			'name' => $this->response_data['name'],
		);
	}
}
