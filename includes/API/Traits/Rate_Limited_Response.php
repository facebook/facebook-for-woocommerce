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
 * Rate limited response trait.
 *
 * @since 2.0.0
 */
trait Rate_Limited_Response {


	/**
	 * Gets usage information from the response headers.
	 *
	 * @see https://developers.facebook.com/docs/graph-api/overview/rate-limiting#headers-2
	 * @see https://developers.facebook.com/docs/graph-api/overview/rate-limiting#headers
	 *
	 * @since 2.0.0
	 *
	 * @param array $headers response headers
	 * @return array
	 */
	private function get_usage_data( $headers ) {

		$usage_data = array();

		if ( ! empty( $headers['X-Business-Use-Case-Usage'] ) ) {

			$usage_data = $headers['X-Business-Use-Case-Usage'];

		} elseif ( ! empty( $headers['x-business-use-case-usage'] ) ) {

			$usage_data = $headers['x-business-use-case-usage'];

		} elseif ( ! empty( $headers['X-App-Usage'] ) ) {

			$usage_data = $headers['X-App-Usage'];

		} elseif ( ! empty( $headers['x-app-usage'] ) ) {

			$usage_data = $headers['x-app-usage'];
		}

		return $usage_data;
	}


	/**
	 * Gets the percentage of calls made by the app over a rolling one hour period.
	 *
	 * @since 2.0.0
	 *
	 * @param array $headers response headers
	 * @return int
	 */
	public function get_rate_limit_usage( $headers ) {

		$usage_data = $this->get_usage_data( $headers );

		return isset( $usage_data['call_count'] ) ? (int) $usage_data['call_count'] : 0;
	}


	/**
	 * Gets the percentage of total time allotted for query processing.
	 *
	 * @since 2.0.0
	 *
	 * @param array $headers response headers
	 * @return int
	 */
	public function get_rate_limit_total_time( $headers ) {

		$usage_data = $this->get_usage_data( $headers );

		return isset( $usage_data['total_time'] ) ? (int) $usage_data['total_time'] : 0;
	}


	/**
	 * Gets the percentage of CPU time allotted for query processing.
	 *
	 * @since 2.0.0
	 *
	 * @param array $headers response headers
	 * @return int
	 */
	public function get_rate_limit_total_cpu_time( $headers ) {

		$usage_data = $this->get_usage_data( $headers );

		return isset( $usage_data['total_cputime'] ) ? (int) $usage_data['total_cputime'] : 0;
	}


	/**
	 * Gets the number of seconds until calls will no longer be throttled.
	 *
	 * @since 2.0.0
	 *
	 * @param array $headers response headers
	 * @return int|null
	 */
	public function get_rate_limit_estimated_time_to_regain_access( $headers ) {

		$usage_data = $this->get_usage_data( $headers );

		return ! empty( $usage_data['estimated_time_to_regain_access'] ) ? (int) $usage_data['estimated_time_to_regain_access'] : null;
	}
}
