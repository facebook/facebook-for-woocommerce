<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved.
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\API\CommerceIntegration\Read;

use WooCommerce\Facebook\API\Response as APIResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Response from the Commerce Partner Integration read endpoint.
 *
 * Returns the Meta-managed asset mapping for an integration.
 */
class Response extends APIResponse {

	/**
	 * Determines whether the endpoint returned an integration.
	 *
	 * @return bool
	 */
	public function is_successful(): bool {
		return '' !== $this->get_commerce_partner_integration_id();
	}

	/**
	 * Gets the Commerce Partner Integration ID.
	 *
	 * @return string
	 */
	public function get_commerce_partner_integration_id(): string {
		return $this->get_string_value( 'id' );
	}

	/**
	 * Gets the linked Ads Pixel ID.
	 *
	 * @return string
	 */
	public function get_pixel_id(): string {
		return $this->get_string_value( 'pixel_id' );
	}

	/**
	 * Gets the linked product catalog ID.
	 *
	 * @return string
	 */
	public function get_catalog_id(): string {
		return $this->get_string_value( 'catalog_id' );
	}

	/**
	 * Gets the linked Commerce Merchant Settings ID.
	 *
	 * @return string
	 */
	public function get_commerce_merchant_settings_id(): string {
		return $this->get_string_value( 'commerce_merchant_settings_id' );
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
