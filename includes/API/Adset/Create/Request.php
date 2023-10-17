<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\Adset\Create;

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
	 * @param array  $data POST data for Adset Creation Request.
	 */
	public function __construct( $account_id, $data ) {
		$path = "/act_{$account_id}/adsets?fields=id,name,daily_budget,targeting";
		parent::__construct( $path, 'POST' );

		$this->set_data( $data );
	}
}
