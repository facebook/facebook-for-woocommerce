<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\Ad;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API;

/**
 * Request object for the User API.
 */
class Request extends API\Request {
	/**
	 * API request constructor.
	 *
	 * @param string $campaign_id Facebook Ad Campaign Id
	 */
	public function __construct( $campaign_id ) {
		$path = "/{$campaign_id}/ads?fields=name,id,configured_status,effective_status,status,adcreatives";
		parent::__construct( $path, 'GET' );
	}
}
