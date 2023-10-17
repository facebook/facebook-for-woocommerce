<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\Campaign\Update;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API;

/**
 * Request object for the User API.
 */
class Request extends API\Request {
	/**
	 * API request constructor.
	 *
	 * @param string $campaign_id Facebook Ad Campaign Id.
	 * @param array  $data POST data for Ad Campaign Update Request.
	 */
	public function __construct( $campaign_id, $data ) {
		$path = "/$campaign_id?fields=id,name,status";
		parent::__construct( $path, 'POST' );

		$this->set_data( $data );
	}
}
