<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\API\FBE\Installation\Delete;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API\FBE\Installation;

/**
 * FBE installation API delete request object.
 *
 * @since 3.3.3
 */
class Request extends Installation\Request {
	/**
	 * API request constructor.
	 *
	 * @since 3.3.3
	 *
	 * @param string $external_business_id external business_id
	 */
	public function __construct( $external_business_id ) {
		// include the business ID in the request body
		parent::__construct( 'fbe_installs', 'DELETE' );
		$this->data['fbe_external_business_id'] = $external_business_id;
	}
}
