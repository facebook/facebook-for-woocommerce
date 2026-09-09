<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved.
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\API\CommerceIntegration\CommerceExtensionToken;

use WooCommerce\Facebook\API\Response as APIResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Response from the Commerce Partner Integration commerce-extension-token endpoint.
 */
class Response extends APIResponse {

	/**
	 * Determines whether the endpoint returned a delegated access token.
	 *
	 * @return bool
	 */
	public function is_successful(): bool {
		return '' !== $this->get_access_token();
	}

	/**
	 * Gets the short-lived delegated access token.
	 *
	 * @return string
	 */
	public function get_access_token(): string {
		return $this->get_string_value( 'access_token' );
	}

	/**
	 * Gets a string response value, rejecting arrays and objects.
	 *
	 * @param string $key Response field key.
	 * @return string
	 */
	private function get_string_value( string $key ): string {
		if ( ! is_array( $this->response_data ) ) {
			return '';
		}

		$value = $this->response_data[ $key ] ?? '';

		return is_string( $value ) ? $value : '';
	}
}
