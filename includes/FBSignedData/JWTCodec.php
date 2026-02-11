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

defined( 'ABSPATH' ) || exit;

/**
 * Minimal JWT codec for ES256 (ECDSA P-256 + SHA-256).
 *
 * Replaces firebase/php-jwt to avoid PHP 8.4 deprecation issues
 * with implicitly nullable parameters while maintaining PHP 7.4 support.
 */
class JWTCodec {

	private const ASN1_INTEGER    = 0x02;
	private const ASN1_SEQUENCE   = 0x10;
	private const ASN1_BIT_STRING = 0x03;

	/**
	 * Map of supported algorithms to their OpenSSL digest names.
	 */
	private const SUPPORTED_ALGS = [
		'ES256' => 'SHA256',
	];

	/**
	 * Map of supported algorithms to their key sizes in bits.
	 */
	private const ALG_KEY_SIZES = [
		'ES256' => 256,
	];

	/**
	 * Decode and verify a JWT string.
	 *
	 * @param string $jwt       The JWT string.
	 * @param string $public_key The PEM-encoded public key.
	 * @param string $algorithm  The expected algorithm (e.g. 'ES256').
	 * @return array The decoded payload as an associative array.
	 *
	 * @throws \UnexpectedValueException    If the JWT is malformed or the algorithm doesn't match.
	 * @throws JWTSignatureInvalidException If signature verification fails.
	 * @throws JWTExpiredException          If the token has expired.
	 */
	public static function decode( string $jwt, string $public_key, string $algorithm ): array {
		if ( ! isset( self::SUPPORTED_ALGS[ $algorithm ] ) ) {
			throw new \UnexpectedValueException( 'Algorithm not supported: ' . $algorithm );
		}

		$parts = explode( '.', $jwt );
		if ( count( $parts ) !== 3 ) {
			throw new \UnexpectedValueException( 'Wrong number of segments' );
		}

		list( $head_b64, $body_b64, $sig_b64 ) = $parts;

		$header = json_decode( self::urlsafe_b64_decode( $head_b64 ), true );
		if ( null === $header ) {
			throw new \UnexpectedValueException( 'Invalid header encoding' );
		}

		$payload = json_decode( self::urlsafe_b64_decode( $body_b64 ), true );
		if ( null === $payload ) {
			throw new \UnexpectedValueException( 'Invalid claims encoding' );
		}

		if ( empty( $header['alg'] ) ) {
			throw new \UnexpectedValueException( 'Empty algorithm' );
		}

		if ( $header['alg'] !== $algorithm ) {
			throw new \UnexpectedValueException( 'Incorrect key for this algorithm' );
		}

		// Verify signature.
		$signature     = self::urlsafe_b64_decode( $sig_b64 );
		$der_signature = self::signature_to_der( $signature );
		$digest        = self::SUPPORTED_ALGS[ $algorithm ];
		$msg           = $head_b64 . '.' . $body_b64;

		$result = openssl_verify( $msg, $der_signature, $public_key, $digest );
		if ( 1 !== $result ) {
			if ( -1 === $result ) {
				throw new \UnexpectedValueException( 'OpenSSL error: ' . openssl_error_string() );
			}
			throw new JWTSignatureInvalidException( 'Signature verification failed' );
		}

		// Check expiration.
		if ( isset( $payload['exp'] ) && time() >= $payload['exp'] ) {
			throw new JWTExpiredException( 'Expired token' );
		}

		return $payload;
	}

	/**
	 * Encode a payload as a JWT string.
	 *
	 * @param array  $payload     The payload data.
	 * @param string $private_key The PEM-encoded private key.
	 * @param string $algorithm   The signing algorithm (e.g. 'ES256').
	 * @return string The JWT string.
	 * @throws \DomainException In case no product found.
	 */
	public static function encode( array $payload, string $private_key, string $algorithm ): string {
		if ( ! isset( self::SUPPORTED_ALGS[ $algorithm ] ) ) {
			throw new \DomainException( 'Algorithm not supported: ' . $algorithm );
		}

		$header   = [
			'typ' => 'JWT',
			'alg' => $algorithm,
		];
		$segments = [];

		$segments[] = self::urlsafe_b64_encode( (string) wp_json_encode( $header ) );
		$segments[] = self::urlsafe_b64_encode( (string) wp_json_encode( $payload ) );

		$msg       = implode( '.', $segments );
		$signature = '';
		$success   = openssl_sign( $msg, $signature, $private_key, self::SUPPORTED_ALGS[ $algorithm ] );

		if ( ! $success ) {
			throw new \DomainException( 'OpenSSL unable to sign data' );
		}

		$key_size   = self::ALG_KEY_SIZES[ $algorithm ];
		$signature  = self::signature_from_der( $signature, $key_size );
		$segments[] = self::urlsafe_b64_encode( $signature );

		return implode( '.', $segments );
	}

	/**
	 * Decode a URL-safe Base64 string.
	 *
	 * @param string $input The base64url encoded string.
	 * @return string The decoded bytes.
	 */
	public static function urlsafe_b64_decode( string $input ): string {
		$remainder = strlen( $input ) % 4;
		if ( $remainder ) {
			$input .= str_repeat( '=', 4 - $remainder );
		}
		return base64_decode( strtr( $input, '-_', '+/' ) );
	}

	/**
	 * Encode a string as URL-safe Base64.
	 *
	 * @param string $input The raw bytes.
	 * @return string The base64url encoded string.
	 */
	private static function urlsafe_b64_encode( string $input ): string {
		return str_replace( '=', '', strtr( base64_encode( $input ), '+/', '-_' ) );
	}

	/**
	 * Convert a fixed-length ECDSA signature (R||S) to ASN.1 DER format
	 * as expected by openssl_verify().
	 *
	 * @param string $sig The raw ECDSA signature.
	 * @return string The DER-encoded signature.
	 */
	private static function signature_to_der( string $sig ): string {
		$length = max( 1, (int) ( strlen( $sig ) / 2 ) );
		list( $r, $s ) = str_split( $sig, $length );

		$r = ltrim( $r, "\x00" );
		$s = ltrim( $s, "\x00" );

		if ( ord( $r[0] ) > 0x7f ) {
			$r = "\x00" . $r;
		}
		if ( ord( $s[0] ) > 0x7f ) {
			$s = "\x00" . $s;
		}

		return self::encode_der(
			self::ASN1_SEQUENCE,
			self::encode_der( self::ASN1_INTEGER, $r ) .
			self::encode_der( self::ASN1_INTEGER, $s )
		);
	}

	/**
	 * Convert a DER-encoded ECDSA signature to fixed-length (R||S) format
	 * as used in JWT.
	 *
	 * @param string $der     The DER-encoded signature.
	 * @param int    $key_size The key size in bits.
	 * @return string The fixed-length signature.
	 */
	private static function signature_from_der( string $der, int $key_size ): string {
		list( $offset, $_ ) = self::read_der( $der );
		list( $offset, $r ) = self::read_der( $der, $offset );
		list( $offset, $s ) = self::read_der( $der, $offset );

		$r = ltrim( $r, "\x00" );
		$s = ltrim( $s, "\x00" );

		$r = str_pad( $r, $key_size / 8, "\x00", STR_PAD_LEFT );
		$s = str_pad( $s, $key_size / 8, "\x00", STR_PAD_LEFT );

		return $r . $s;
	}

	/**
	 * Encode a value into a DER object.
	 *
	 * @param int    $type  The ASN.1 tag type.
	 * @param string $value The value to encode.
	 * @return string The DER-encoded object.
	 */
	private static function encode_der( int $type, string $value ): string {
		$tag_header = 0;
		if ( self::ASN1_SEQUENCE === $type ) {
			$tag_header |= 0x20;
		}

		$der  = chr( $tag_header | $type );
		$der .= chr( strlen( $value ) );

		return $der . $value;
	}

	/**
	 * Read a single DER-encoded object.
	 *
	 * @param string $der    The DER data.
	 * @param int    $offset The starting offset.
	 * @return array{int, string|null} The new offset and the decoded value.
	 */
	private static function read_der( string $der, int $offset = 0 ): array {
		$pos         = $offset;
		$size        = strlen( $der );
		$constructed = ( ord( $der[ $pos ] ) >> 5 ) & 0x01;
		$type        = ord( $der[ $pos++ ] ) & 0x1f;

		$len = ord( $der[ $pos++ ] );
		if ( $len & 0x80 ) {
			$n   = $len & 0x1f;
			$len = 0;
			while ( $n-- && $pos < $size ) {
				$len = ( $len << 8 ) | ord( $der[ $pos++ ] );
			}
		}

		if ( self::ASN1_BIT_STRING === $type ) {
			$pos++;
			$data = substr( $der, $pos, $len - 1 );
			$pos += $len - 1;
		} elseif ( ! $constructed ) {
			$data = substr( $der, $pos, $len );
			$pos += $len;
		} else {
			$data = null;
		}

		return [ $pos, $data ];
	}
}
