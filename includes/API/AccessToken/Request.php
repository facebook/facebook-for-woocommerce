<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\API\AccessToken;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API;

/**
 * FBE Access Token API read request object.
 *
 * @since 2.0.0
 */
class Request extends API\Request {
	/**
	 * API request constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param string business_manager_id as business_manager_id
	 */
	public function __construct( $business_manager_id, $external_business_id, $app_id, $scope ) {
    parent::__construct( "/{$business_manager_id}/access_token", 'POST' );
		$this->data['fbe_external_business_id'] = $external_business_id;
		$this->data['app_id'] = $app_id;
		$this->data['scope'] = $scope;
	}
}
