<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\GenericFeedUploads\Read;

use WooCommerce\Facebook\API\Request as ApiRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Request object for reading upload status.
 * NOTE: This is a theoretical endpoint. Current endpoint does not support this.
 */
class Request extends ApiRequest {

	/**
	 * Constructor for the theoretical upload read request
	 *
	 * @param string $feed_upload_id The upload id for a given upload, distinct form the CPI ID.
	 */
	public function __construct( string $feed_upload_id ) {
		parent::__construct( $feed_upload_id . '/file_update', 'GET' );
	}
}
