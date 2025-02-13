<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\AdCreative\Update;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API;

/**
 * Request object for the User API.
 */
class Request extends API\Request {
	/**
	 * API request constructor.
	 *
	 * @param string $adcreative_id Facebook Adcreative Id
	 * @param array  $props the POST data for updating the Facebook Ad
	 */
	public function __construct( $adcreative_id, $props ) {
		$path = "/{$adcreative_id}?fields=id,name,body,status,product_set_id";
		parent::__construct( $path, 'POST' );

		parent::set_data( $props );
	}
}
