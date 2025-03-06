<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\Adset\Update;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API\Response as ApiResponse;

/**
 * Response object for Facebook Adset Update request.
 *
 * @since x.x.x
 */
class Response extends ApiResponse {

	/**
	 * Gets the name of the adset.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->response_data['name'];
	}

	/**
	 * Gets the adset id.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->response_data['id'];
	}

	/**
	 * Gets the countries for the adset.
	 *
	 * @since 2.0.0
	 *
	 * @return mixed
	 */
	public function get_targeting() {
		return $this->response_data['targeting'];
	}
}
