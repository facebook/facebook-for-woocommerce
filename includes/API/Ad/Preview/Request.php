<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\Ad\Preview;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API;

/**
 * Request object for the User API.
 */
class Request extends API\Request {
	/**
	 * API request constructor.
	 *
	 * @param string $ad_id Facebook Ad id.
	 * @param string $ad_format Facebook Ad Format.
	 */
	public function __construct( $ad_id, $ad_format ) {
		$path = "/{$ad_id}/previews";
		parent::__construct( $path, 'GET' );

		$this->set_params(
			array(
				'ad_format' => $ad_format,
			)
		);
	}
}
