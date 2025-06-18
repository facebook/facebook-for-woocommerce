<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\API\Exceptions;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\Framework\Api\Exception as ApiException;

/**
 * Exception thrown in response to a rate limiting error.
 *
 * @since 2.0.0
 */
class Request_Limit_Reached extends ApiException {

	/** @var \DateTime date & time representing when the request limit will be lifted */
	protected $throttle_end;

	/**
	 * Gets the estimated throttle end.
	 *
	 * @since 2.1.0
	 *
	 * @return \DateTime|null
	 */
	public function get_throttle_end() {
		return $this->throttle_end;
	}

	/**
	 * Sets the estimated throttle end.
	 *
	 * @since 2.1.0
	 *
	 * @param \DateTime $date_time date time object representing when the throttle will end
	 */
	public function set_throttle_end( \DateTime $date_time ) {
		$this->throttle_end = clone $date_time;
	}
}
