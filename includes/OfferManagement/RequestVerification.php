<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\OfferManagement;

defined( 'ABSPATH' ) || exit;

use Exception;
use WooCommerce\Facebook\FBSignedData\JWTAlgorithmMismatchException;
use WooCommerce\Facebook\FBSignedData\JWTSignatureInvalidException;
use WooCommerce\Facebook\FBSignedData\FBPublicKey;
use WooCommerce\Facebook\FBSignedData\PublicKeyStorageHelper;
use WooCommerce\Facebook\FBSignedData\JWTCodec;



/**
 * Utils class for decoding JWTs sent from Meta. Includes retry logic where we re-retrieve keys in case of mismatch
 * between signature and key due to key rotation.
 */
class RequestVerification {
	const KEY_NAME_FIELD                    = 'key_name';
	const PUBLIC_KEY_FETCH_COOLDOWN_TRANSIENT = '_wc_facebook_for_woocommerce_public_key_fetch_cooldown';
	const PUBLIC_KEY_FETCH_COOLDOWN_SECONDS   = 30;

	/**
	 * Attempts to decode the JWT using stored public keys. We have multiple retries in this flow.
	 * We will attempt to use both current and next public keys, and if those don't work we will retrieve public keys
	 * and retry refreshed current and next keys
	 *
	 * @param string $jwt
	 * @return array
	 * @throws Exception Throws if all decode attempts fail.
	 */
	public static function decode_jwt_with_retries( string $jwt ): array {
		$payload  = JWTCodec::extract_unverified_payload( $jwt );
		$key_name = $payload[ self::KEY_NAME_FIELD ] ?? null;
		if ( null === $key_name ) {
			throw new Exception( 'JWT payload missing key_name field' );
		}

		$public_key = PublicKeyStorageHelper::get_current_public_key();

		$params = self::attempt_decode_jwt( $jwt, $key_name, $public_key );
		if ( null !== $params ) {
			return $params;
		}

		$public_key = PublicKeyStorageHelper::get_next_public_key();
		$params     = self::attempt_decode_jwt( $jwt, $key_name, $public_key );
		if ( null !== $params ) {
			return $params;
		}

		if ( 'yes' !== get_transient( self::PUBLIC_KEY_FETCH_COOLDOWN_TRANSIENT ) ) {
			set_transient( self::PUBLIC_KEY_FETCH_COOLDOWN_TRANSIENT, 'yes', self::PUBLIC_KEY_FETCH_COOLDOWN_SECONDS );
			PublicKeyStorageHelper::request_and_store_public_key( facebook_for_woocommerce(), $key_name );

			$public_key = PublicKeyStorageHelper::get_current_public_key();
			$params     = self::attempt_decode_jwt( $jwt, $key_name, $public_key );
			if ( null !== $params ) {
				return $params;
			}

			$public_key = PublicKeyStorageHelper::get_next_public_key();
			$params     = self::attempt_decode_jwt( $jwt, $key_name, $public_key );
			if ( null !== $params ) {
				return $params;
			}
		}

		throw new Exception( 'JWT could not be decoded with any available key' );
	}

	/**
	 * Attempts to decode the JWT, returning null on failure so the caller can retry with an alternate key.
	 *
	 * @param string           $jwt
	 * @param string           $jwt_key_name The key name that was provided in the JWT data. Checked against the stored key name
	 * @param null|FBPublicKey $fb_public_key
	 * @return array|null
	 */
	private static function attempt_decode_jwt( string $jwt, string $jwt_key_name, ?FBPublicKey $fb_public_key ): ?array {
		if ( null === $fb_public_key ) {
			return null;
		}

		if ( $jwt_key_name !== $fb_public_key->get_project() ) {
			return null;
		}

		try {
			return self::decode_jwt_with_public_key( $jwt, $fb_public_key );
		} catch ( JWTSignatureInvalidException | JWTAlgorithmMismatchException $ex ) {
			return null;
		}
	}

	private static function decode_jwt_with_public_key( string $jwt, FBPublicKey $fb_public_key ): array {
		return JWTCodec::decode( $jwt, $fb_public_key->get_key(), $fb_public_key->get_algorithm() );
	}
}
