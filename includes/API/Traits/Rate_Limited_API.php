<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\API\Traits;

defined( 'ABSPATH' ) || exit;

/**
 * Rate limited API trait.
 *
 * @since 2.0.0
 */
trait Rate_Limited_API {


	/**
	 * Stores the delay, in seconds, for requests with the given rate limit ID.
	 *
	 * This uses a transient, set to expire after the delay duration or after 24 hours, whichever is sooner.
	 *
	 * @since 2.0.0
	 *
	 * @param string $rate_limit_id request ID for rate limiting
	 * @param int    $delay delay in seconds
	 */
	public function set_rate_limit_delay( $rate_limit_id, $delay ) {
		if ( ! empty( $delay ) ) {
			$expiration = min( $delay, 24 * HOUR_IN_SECONDS );
			set_transient( "wc_facebook_rate_limit_{$rate_limit_id}", $delay, $expiration );
		} else {
			delete_transient( "wc_facebook_rate_limit_{$rate_limit_id}" );
		}
	}


	/**
	 * Gets the number of seconds before a new request with the given rate limit ID can be made.
	 *
	 * @since 2.0.0
	 *
	 * @param string $rate_limit_id request ID for rate limiting
	 * @return int
	 */
	public function get_rate_limit_delay( $rate_limit_id ) {
		return (int) get_transient( "wc_facebook_rate_limit_{$rate_limit_id}" );
	}


	/**
	 * Uses the response object and the array of headers to get information about the API usage
	 * and calculate the next delay for requests of the same type.
	 *
	 * @since 2.0.0
	 *
	 * @param Rate_Limited_Response $response API response object
	 * @param array                 $headers API response headers
	 * @return int delay in seconds
	 */
	protected function calculate_rate_limit_delay( $response, $headers ) {
		return $response->get_rate_limit_estimated_time_to_regain_access( $headers ) * MINUTE_IN_SECONDS;
	}
}
