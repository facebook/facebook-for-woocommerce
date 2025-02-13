<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\AdAccount\Currency;

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
	 */
	public function __construct( $account_id ) {
		$path = "/act_{$account_id}?fields=currency";
		parent::__construct( $path, 'GET' );
	}
}
