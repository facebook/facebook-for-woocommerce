<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\CommonFeedUploads\Create;

use WooCommerce\Facebook\API\Request as ApiRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Request object for the Common Feed Upload.
 */
class Request extends ApiRequest {

	/**
	 * Constructs the request.
	 *
	 * @param string $cpi_id Commerce Partner Integration ID.
	 * @param array  $data Feed Metadata for File Update Post endpoint.
	 * @since 3.5.0
	 */
	public function __construct( array $data ) {
		parent::__construct( '24316596247984028/file_update', 'POST' );
		parent::set_data( $data );
	}
}
