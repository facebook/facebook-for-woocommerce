<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\API\REST;

defined( 'ABSPATH' ) || exit;

// Include API helper functions
require_once __DIR__ . '/api-helpers.php';

/**
 * Initialize the REST API controller.
 *
 * @since 2.3.5
 *
 * @return Controller
 */
function init() {
	static $controller = null;

	if ( null === $controller ) {
		$controller = new Controller();
	}

	return $controller;
} 