<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\Ad\Create;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API;

/**
 * Request object for the User API.
 */
class Request extends API\Request {
	/**
	 * API request constructor.
	 *
	 * @param string $account_id Facebook Ad Account Id.
	 * @param array  $data Post data for Ad Creation Request
	 */
	public function __construct( $account_id, $data ) {
		$path = "/act_{$account_id}/ads?fields=id,name,status,adcreatives";
		parent::__construct( $path, 'POST' );

		$this->set_data( $data );
	}
}
