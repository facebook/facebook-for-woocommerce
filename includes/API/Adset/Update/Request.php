<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\Adset\Update;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API;

/**
 * Request object for the User API.
 */
class Request extends API\Request {
	/**
	 * API request constructor.
	 *
	 * @param string $adset_id Facebook Adset Id
	 * @param array  $props the POST data for updating the Facebook Ad
	 */
	public function __construct( $adset_id, $props ) {
		$path = "/{$adset_id}?fields=name,id,configured_status,effective_status,campaign_id,status,daily_budget,targeting,promoted_object";
		parent::__construct( $path, 'POST' );

		parent::set_data( $props );
	}
}
