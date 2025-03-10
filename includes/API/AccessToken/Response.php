<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\AccessToken;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API;

/**
 * FBE Access Token API read response object.
 */
class Response extends API\Response {
	/**
	 * Gets the access token.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_system_user_access_token() {
		return $this->response_data['access_token'] ?? '';
	}
}
