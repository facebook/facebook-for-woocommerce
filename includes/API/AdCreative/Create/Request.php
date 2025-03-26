<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\AdCreative\Create;

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
	 * @param array  $data Post data for Adcreative Creation Request
	 */
	public function __construct( $account_id, $data ) {
		$path = "/act_{$account_id}/adcreatives?fields=id,name,body,status,product_set_id";
		parent::__construct( $path, 'POST' );

		parent::set_data( $data );
	}
}
