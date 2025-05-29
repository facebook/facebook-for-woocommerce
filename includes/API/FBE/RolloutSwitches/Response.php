<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\FBE\RolloutSwitches;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API;

/**
 * FBE RolloutSwitches API read response object.
 */
class Response extends API\Response {

	/**
	 * Gets the response data.
	 *
	 * @return array
	 */
	public function get_data() {
		return $this->response_data['data'] ?? [];
	}
}
