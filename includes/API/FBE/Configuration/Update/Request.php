<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\API\FBE\Configuration\Update;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API\FBE\Configuration;

/**
 * FBE Configuration update request object.
 *
 * @since 2.0.0
 */
class Request extends Configuration\Request {


	/**
	 * API request constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param string $external_business_id external business ID
	 */
	public function __construct( $external_business_id ) {
		parent::__construct( $external_business_id, 'POST' );
		// include the business ID in the request body
		$this->data['fbe_external_business_id'] = $external_business_id;
	}

	/**
	 * Sets the external client metadata for logging
	 *
	 * @since 3.4.4
	 *
	 * @param array $metadata map of metadata to include. Example: array ('version_id' => '0.0.0', 'is_multisite' => True)
	 *
	 * @return void
	 */
	public function set_external_client_metadata( array $metadata ) {
		$this->data['business_config'] = array(
			'external_client' => $metadata,
		);

		is_multisite();
	}
}
