<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\Ad\Preview\Generation;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API;

/**
 * Request object for the Ad Preview Generation API.
 */
class Request extends API\Request {
	/**
	 * API request constructor.
	 *
	 * @param string $account_id Facebook Ad account id.
	 * @param string $ad_format Facebook Ad Format.
	 * @param string $creative_spec Facebook Ad Creative spec.
	 */
	public function __construct( $account_id, $ad_format, $creative_spec ) {
		$path = "/act_{$account_id}/generatepreviews?ad_format=" . $ad_format . '&creative=' . urlencode( json_encode( $creative_spec ) );
		parent::__construct( $path, 'GET' );
	}
}
