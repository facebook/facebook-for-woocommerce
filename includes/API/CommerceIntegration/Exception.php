<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\API\CommerceIntegration;

defined( 'ABSPATH' ) || exit;

/**
 * Exception raised when a finalize-install request is unsuccessful.
 */
class Exception extends \Exception {

	/** @var string Machine-readable failure reason for monitoring. */
	private $failure_reason;

	/**
	 * Constructor.
	 *
	 * @param string $message Human-readable error message.
	 * @param string $failure_reason Machine-readable failure reason.
	 * @param int    $http_status HTTP response status, or zero for client-side failures.
	 */
	public function __construct( string $message, string $failure_reason, int $http_status = 0 ) {
		parent::__construct( $message, $http_status );
		$this->failure_reason = $failure_reason;
	}

	/**
	 * Gets the machine-readable failure reason.
	 *
	 * @return string
	 */
	public function get_failure_reason(): string {
		return $this->failure_reason;
	}
}
