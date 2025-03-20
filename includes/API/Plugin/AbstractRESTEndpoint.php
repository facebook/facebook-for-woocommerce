<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\API\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract REST API Endpoint.
 *
 * Provides common functionality for all REST API endpoints.
 *
 * @since 3.5.0
 */
abstract class AbstractRESTEndpoint {

	/**
	 * Register routes for this endpoint.
	 *
	 * @since 3.5.0
	 *
	 * @return void
	 */
	abstract public function register_routes();

	/**
	 * Check if the current user has permission to access this endpoint.
	 *
	 * @since 3.5.0
	 *
	 * @return bool
	 */
	public function permission_callback() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Get the REST API namespace.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	protected function get_namespace() {
		return Controller::get_namespace();
	}

	/**
	 * Format a successful response.
	 *
	 * @since 3.5.0
	 *
	 * @param mixed $data Response data.
	 * @param int   $status HTTP status code.
	 * @return \WP_REST_Response
	 */
	protected function success_response( $data = null, $status = 200 ) {
		return new \WP_REST_Response(
			[
				'success' => true,
				'data'    => $data,
			],
			$status
		);
	}

	/**
	 * Format an error response.
	 *
	 * @since 3.5.0
	 *
	 * @param string $message Error message.
	 * @param int    $status HTTP status code.
	 * @param array  $additional_data Additional data to include in the response.
	 * @return \WP_REST_Response
	 */
	protected function error_response( $message, $status = 400, $additional_data = [] ) {
		$response = [
			'success' => false,
			'message' => $message,
		];

		if ( ! empty( $additional_data ) ) {
			$response['data'] = $additional_data;
		}

		return new \WP_REST_Response( $response, $status );
	}
}
