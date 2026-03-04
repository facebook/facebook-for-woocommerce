<?php
/** Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package MetaCommerce
 */

use WooCommerce\Facebook\FBSignedData\JWTCodec;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Class JWTCodecTest
 */
class JWTCodecTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * @covers \WooCommerce\Facebook\FBSignedData\JWTCodec::extract_unverified_payload
	 */
	public function test_extract_unverified_payload_with_valid_jwt(): void {
		$payload = [ 'key_name' => 'test_project', 'aud' => 'catalog_123' ];
		$key_pair = self::generate_ec_key_pair();
		$jwt = JWTCodec::encode( $payload, $key_pair['private_key'], 'ES256' );

		$result = JWTCodec::extract_unverified_payload( $jwt );

		$this->assertEquals( 'test_project', $result['key_name'] );
		$this->assertEquals( 'catalog_123', $result['aud'] );
	}

	/**
	 * @covers \WooCommerce\Facebook\FBSignedData\JWTCodec::extract_unverified_payload
	 */
	public function test_extract_unverified_payload_with_one_segment(): void {
		$this->expectException( \UnexpectedValueException::class );
		$this->expectExceptionMessage( 'Wrong number of segments' );

		JWTCodec::extract_unverified_payload( 'single_segment' );
	}

	/**
	 * @covers \WooCommerce\Facebook\FBSignedData\JWTCodec::extract_unverified_payload
	 */
	public function test_extract_unverified_payload_with_two_segments(): void {
		$this->expectException( \UnexpectedValueException::class );
		$this->expectExceptionMessage( 'Wrong number of segments' );

		JWTCodec::extract_unverified_payload( 'header.body' );
	}

	/**
	 * @covers \WooCommerce\Facebook\FBSignedData\JWTCodec::extract_unverified_payload
	 */
	public function test_extract_unverified_payload_with_four_segments(): void {
		$this->expectException( \UnexpectedValueException::class );
		$this->expectExceptionMessage( 'Wrong number of segments' );

		JWTCodec::extract_unverified_payload( 'a.b.c.d' );
	}

	/**
	 * @covers \WooCommerce\Facebook\FBSignedData\JWTCodec::extract_unverified_payload
	 */
	public function test_extract_unverified_payload_with_invalid_base64_payload(): void {
		$this->expectException( \UnexpectedValueException::class );
		$this->expectExceptionMessage( 'Invalid claims encoding' );

		// Valid header, invalid payload (not valid JSON after base64 decode), valid sig placeholder
		$header = base64_encode( wp_json_encode( [ 'alg' => 'ES256', 'typ' => 'JWT' ] ) );
		JWTCodec::extract_unverified_payload( $header . '.not-valid-json.signature' );
	}

	/**
	 * @covers \WooCommerce\Facebook\FBSignedData\JWTCodec::extract_unverified_payload
	 */
	public function test_extract_unverified_payload_with_empty_string(): void {
		$this->expectException( \UnexpectedValueException::class );
		$this->expectExceptionMessage( 'Wrong number of segments' );

		JWTCodec::extract_unverified_payload( '' );
	}

	private static function generate_ec_key_pair(): array {
		$key_config = [
			'private_key_type' => OPENSSL_KEYTYPE_EC,
			'curve_name'       => 'prime256v1',
		];
		$key_pair    = openssl_pkey_new( $key_config );
		$public_key  = openssl_pkey_get_details( $key_pair )['key'];
		$private_key = '';
		openssl_pkey_export( $key_pair, $private_key );
		return [ 'public_key' => $public_key, 'private_key' => $private_key ];
	}
}
