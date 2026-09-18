<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved.
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\API\CommerceIntegration\Node;

use WooCommerce\Facebook\API\Response as APIResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Response from the legacy Graph Commerce Partner Integration node.
 *
 * The node exposes linked assets as edges ({catalog: {id}, ...}), unlike the
 * flat STEFI read response.
 *
 * @deprecated For normal operation -- prefer the STEFI integration read.
 *
 *             Retained deliberately, not transitional. When the FBE install read
 *             (the last rung) is removed, this becomes the only non-STEFI fallback,
 *             so it must outlive the FBE cleanup rather than go with it.
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
		return $this->get_edge_id( 'pixel' );
	}

	/**
	 * Gets the linked product catalog ID.
	 *
	 * @return string
	 */
	public function get_catalog_id(): string {
		return $this->get_edge_id( 'catalog' );
	}

	/**
	 * Gets the linked Commerce Merchant Settings ID.
	 *
	 * @return string
	 */
	public function get_commerce_merchant_settings_id(): string {
		return $this->get_edge_id( 'commerce_merchant_settings' );
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

	/**
	 * Gets the ID of a linked edge object ({edge: {id}}).
	 *
	 * @param string $key Response edge key.
	 * @return string
	 */
	private function get_edge_id( string $key ): string {
		if ( ! is_array( $this->response_data ) || ! is_array( $this->response_data[ $key ] ?? null ) ) {
			return '';
		}

		$value = $this->response_data[ $key ]['id'] ?? '';

		return is_string( $value ) || is_int( $value ) ? (string) $value : '';
	}
}
