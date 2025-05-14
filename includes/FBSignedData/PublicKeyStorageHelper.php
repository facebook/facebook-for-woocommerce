<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\FBSignedData;

use WooCommerce\Facebook\API\Exceptions\Request_Limit_Reached;
use WooCommerce\Facebook\Framework\Api\Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Class to handle storage and retrieval of Meta's public key used to verify signed data.
 */
final class PublicKeyStorageHelper {

	const SETTING_CURRENT_PUBLIC_KEY   = 'wc_facebook_current_public_key';
	const SETTING_NEXT_PUBLIC_KEY      = 'wc_facebook_next_public_key';
	const SETTING_PREVIOUS_PUBLIC_KEYS = 'wc_facebook_previous_public_keys';


	/**
	 * Calls the API to retrieve public key from Meta
	 *
	 * @param \WC_Facebookcommerce $plugin facebook plugin instance
	 * @throws Request_Limit_Reached If the request to get the public key from Meta is rate limited.
	 * @throws Exception If there is an error retrieving the public key from Meta.
	 */
	public static function request_and_store_public_key( \WC_Facebookcommerce $plugin ) {
		$key_data_response = $plugin->get_api()->get_public_key();
		$key_response_data = $key_data_response->response_data;

		if ( key_exists( 'current', $key_response_data ) ) {
			self::update_single_key_option( self::SETTING_CURRENT_PUBLIC_KEY, $key_response_data['current'] ?? [] );
		}
		if ( key_exists( 'next', $key_response_data ) ) {
			self::update_single_key_option( self::SETTING_NEXT_PUBLIC_KEY, $key_response_data['next'] ?? [] );
		}
		if ( key_exists( 'previous', $key_response_data ) ) {
			self::update_previous_public_keys_option( $key_response_data['previous'] ?? [] );
		}
	}

	private static function update_single_key_option( string $option_name, array $key_data ): void {
		if ( self::is_valid_key_data( $key_data ) ) {
			update_option( $option_name, $key_data );
		}
	}

	private static function update_previous_public_keys_option( array $keys_data ): void {
		$valid_keys_data = array_filter(
			$keys_data,
			function ( $key_data ) {
				return self::is_valid_key_data( $key_data );
			}
		);

		if ( ! empty( $valid_keys_data ) ) {
			update_option( self::SETTING_PREVIOUS_PUBLIC_KEYS, $valid_keys_data );
		}
	}

	private static function is_valid_key_data( $key_data ): bool {
		if ( ! is_array( $key_data ) ) {
			return false;
		}

		$key             = $key_data['key'] ?? null;
		$alg             = $key_data['algorithm'] ?? null;
		$encoding_format = $key_data['encoding_format'] ?? null;

		// Valid key needs to have all values.
		return array_all(
			[ $key, $alg, $encoding_format ],
			function ( $element ) {
				return ! empty( $element );
			}
		);
	}

	public static function get_current_public_key(): ?FBPublicKey {
		$current_key_data = get_option( self::SETTING_CURRENT_PUBLIC_KEY );
		return self::fb_signed_key_from_data( $current_key_data );
	}

	public static function get_next_public_key(): ?FBPublicKey {
		$next_key_data = get_option( self::SETTING_NEXT_PUBLIC_KEY );
		return self::fb_signed_key_from_data( $next_key_data );
	}

	public static function get_previous_public_keys(): array {
		$previous_key_data = get_option( self::SETTING_PREVIOUS_PUBLIC_KEYS ) ?? [];
		return array_map(
			function ( $previous_key_datum ) {
				return self::fb_signed_key_from_data( $previous_key_datum );
			},
			$previous_key_data
		);
	}

	private static function fb_signed_key_from_data( $key_data ): ?FBPublicKey {
		if ( ! self::is_valid_key_data( $key_data ) ) {
			return null;
		}
		return new FBPublicKey( $key_data['key'], $key_data['algorithm'], $key_data['encoding_format'] );
	}
}
