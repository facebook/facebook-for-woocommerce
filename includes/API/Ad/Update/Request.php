<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\Ad\Update;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API;

/**
 * Request object for the User API.
 */
class Request extends API\Request {
	/**
	 * API request constructor.
	 *
	 * @param string $ad_id Facebook Ad Id
	 * @param array  $props the POST data for updating the Facebook Ad
	 */
	public function __construct( $ad_id, $props ) {
		$path = "/{$ad_id}?fields=name,id,status,adcreatives,targeting";
		parent::__construct( $path, 'POST' );

		parent::set_data( $props );
	}
}
