<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\GenericFeedUploads\Create;

use WooCommerce\Facebook\API\Request as ApiRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Request object for the Common Feed Upload.
 *
 */
class Request extends ApiRequest {

	/**
	 * Constructs the request.
	 *
	 * @param string $cpi_id Customer Partner Integration ID.
	 * @param array $data Facebook Product Feed Data.
	 */
	public function __construct( string $cpi_id, array $data ) {
		parent::__construct( $cpi_id . '/file_update', 'POST' );
		parent::set_data( $data );
	}
}
