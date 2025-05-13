<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\OfferManagement;

defined( 'ABSPATH' ) || exit;

use Doctrine\Instantiator\Exception\UnexpectedValueException;
use Firebase\JWT\BeforeValidException;
use WooCommerce\Facebook\API\Exceptions\Request_Limit_Reached;
use WooCommerce\Facebook\FBSignedData\FBPublicKey;
use WooCommerce\Facebook\FBSignedData\PublicKeyStorageHelper;
use WooCommerce\Facebook\Framework\Api\Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;



/**
 * The checkout permalink.
 *
 * @since 3.3.0
 */
abstract class RequestVerification {

	/**
	 * Attempts to decode the JWT using stored public keys. We have multiple retries in this flow.
	 * We will attempt to use both current and next public keys, and if those don't work we will retrieve public keys
	 * and retry refreshed current and next keys
	 *
	 * @param string $jwt
	 * @return array
	 * @throws \Exception Throws any non-swallowed exception during JWT decoding.
	 * @throws Request_Limit_Reached If request to get public key is rate limited.
	 */
	public static function decode_jwt_with_retries( string $jwt ): array {
		$public_key = PublicKeyStorageHelper::get_current_public_key();
		$params     = self::decode_jwt_retryable( $jwt, $public_key, true );
		if ( null !== $params ) {
			return $params;
		}

		$public_key = PublicKeyStorageHelper::get_next_public_key();
		$params     = self::decode_jwt_retryable( $jwt, $public_key, true );
		if ( null !== $params ) {
			return $params;
		}

		// Extract the project header to query for refreshed keys
		$b64_header   = explode( '.', $jwt )[0];
		$header_array = json_decode( JWT::urlsafeB64Decode( $b64_header ), true );
		$key_project  = $header_array['key_project'];

		PublicKeyStorageHelper::request_and_store_public_key( facebook_for_woocommerce(), $key_project );
		$public_key = PublicKeyStorageHelper::get_current_public_key();
		$params     = self::decode_jwt_retryable( $jwt, $public_key, true );
		if ( null !== $params ) {
			return $params;
		}

		// This is the last attempt, so the params result is no longer nullable (We no longer swallow exceptions)
		$public_key = PublicKeyStorageHelper::get_next_public_key();
		$params     = self::decode_jwt_retryable( $jwt, $public_key, false );

		// json_encode -> json_decode converts nested stdClass objects into nested arrays
		return json_decode( wp_json_encode( $params ), true );
	}

	/**
	 * Swallows exceptions that could result from an invalid stored key so that we can retry with an alternate key.
	 *
	 * @param string           $jwt
	 * @param null|FBPublicKey $fb_public_key
	 * @param bool             $should_swallow_retryable_exception Set to true to swallow exceptions if we plan to re-try
	 * @return array|null
	 * @throws UnexpectedValueException Re-throws if the formatting of the JWT headers/body is invalid.
	 * @throws BeforeValidException Re-throws if JWT is not valid yet.
	 * @throws ExpiredException Re-throws if JWT is expired.
	 * @throws \Exception We will generally retry on exceptions in case that they are transient or related to key.
	 */
	private static function decode_jwt_retryable( string $jwt, ?FBPublicKey $fb_public_key, bool $should_swallow_retryable_exception ): ?array {
		if ( null === $fb_public_key ) {
			return null;
		}
		try {
			return self::decode_jwt_with_public_key( $jwt, $fb_public_key );
		} catch ( ExpiredException | BeforeValidException $ex ) {
			throw $ex;
		} catch ( UnexpectedValueException $ex ) {
			if ( $ex->getMessage() !== 'Incorrect key for this algorithm' ) {
				throw $ex;
			}
			if ( ! $should_swallow_retryable_exception ) {
				throw $ex;
			}
		} catch ( \Exception $ex ) {
			if ( ! $should_swallow_retryable_exception ) {
				throw $ex;
			}
		}
		return null;
	}

	private static function decode_jwt_with_public_key( string $jwt, FBPublicKey $fb_public_key ): array {
		$jwt_key  = new Key( $fb_public_key->get_key(), $fb_public_key->get_algorithm() );
		$jwt_data = JWT::decode( $jwt, $jwt_key );
		return json_decode( wp_json_encode( $jwt_data ), true );
	}
}
