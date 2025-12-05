<?php
/**
 * Copyright (c) Meta, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Integrations;

use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * An Exception which indicates that a 3P integration is not available and accessible
 */
class IntegrationIsNotAvailableException extends \Exception {
	public function __construct( $integration_name, $code = 0, ?Throwable $previous = null ) {

		parent::__construct( "Integration \'{$integration_name}\' is not available. Make sure the 3P plugin is installed and active", $code, $previous );
	}
}
