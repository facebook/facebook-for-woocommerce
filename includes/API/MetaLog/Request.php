<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\MetaLog;

use WooCommerce\Facebook\API\Request as ApiRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Request object for MetaLog Graph Api.
 */
class Request extends ApiRequest {

	/**
	 * Gets the dedicated rate limit ID for crash/log reporting.
	 *
	 * @return string
	 */
	public static function get_rate_limit_id(): string {
		return 'meta_log_reporting';
	}

	/**
	 * @param array $context log data
	 */
	public function __construct( $context ) {
		parent::__construct( '/commerce_seller_logs', 'POST' );
		$data = $context;
		parent::set_data( $data );
	}
}
