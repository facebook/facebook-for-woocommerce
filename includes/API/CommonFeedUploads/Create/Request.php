<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\API\CommonFeedUploads\Create;

use WooCommerce\Facebook\API\Request as ApiRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Request object for the Common Feed Upload.
 */
class Request extends ApiRequest {
	const CPI_ENDPOINT = 'file_update';

	/**
	 * Constructs the request.
	 *
	 * @param string $cpi_id Commerce Partner Integration ID.
	 * @param array  $data Feed Metadata for File Update Post endpoint.
	 * @since 3.5.0
	 */
	public function __construct( string $cpi_id, array $data ) {
		$endpoint = self::CPI_ENDPOINT;
		parent::__construct( "/{$cpi_id}/{$endpoint}", 'POST' );
		parent::set_data( $data );
	}
}
