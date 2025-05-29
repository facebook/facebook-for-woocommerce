<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\PublicKeyGet;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API;

/**
 * Page API request object.
 *
 * @since 2.0.0
 */
class Request extends API\Request {
	const API_REQUEST_PATH = 'shops_public_key';
	const API_METHOD       = 'GET';
	const API_VERSION      = '1.0.0';

	public function __construct( string $project ) {
		$path_with_param = sprintf( '%s/%s', self::API_REQUEST_PATH, $project );
		parent::__construct( $path_with_param, self::API_METHOD );
	}

	public function get_base_path_override(): string {
		return 'https://api.facebook.com/';
	}

	public function get_request_specific_headers(): array {
		return [
			'X-API-Version' => self::API_VERSION,
		];
	}
}
