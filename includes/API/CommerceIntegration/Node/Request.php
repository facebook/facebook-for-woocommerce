<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved.
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\API\CommerceIntegration\Node;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API;

/**
 * Reads a Commerce Partner Integration node with its linked asset edges.
 *
 * @deprecated For normal operation -- prefer the STEFI integration read.
 *
 *             Retained deliberately, not transitional. When the FBE install read
 *             (the last rung) is removed, this becomes the only non-STEFI fallback,
 *             so it must outlive the FBE cleanup rather than go with it.
 */
class Request extends API\Request {

	/**
	 * API request constructor.
	 *
	 * @param string $commerce_partner_integration_id The Commerce Partner Integration entity ID.
	 */
	public function __construct( string $commerce_partner_integration_id ) {
		parent::__construct( "/$commerce_partner_integration_id", 'GET' );

		$this->set_params(
			array(
				'fields' => 'catalog,pixel,commerce_merchant_settings',
			)
		);
	}
}
