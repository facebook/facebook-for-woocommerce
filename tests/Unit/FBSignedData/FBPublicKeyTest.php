<?php
/** Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

use WooCommerce\Facebook\FBSignedData\FBPublicKey;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Class FBPublicKeyTest
 */
class FBPublicKeyTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering
{
	/**
	 * Test FBPublicKey getters with ES256 algorithm and PEM encoding.
	 *
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::__construct
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_key
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_algorithm
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_encoding_format
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_project
	 */
	public function test_fb_public_key_getters(): void {
		$algorithm = FBPublicKey::ALGORITHM_ES256;
		$key = 'test_key';
		$encoding_format = FBPublicKey::ENCODING_FORMAT_PEM;
		$project = 'test_project';

		$fb_public_key = new FBPublicKey($key, $algorithm, $encoding_format, $project );

		$this->assertEquals($fb_public_key->get_key(), $key);
		$this->assertEquals($fb_public_key->get_algorithm(), $algorithm);
		$this->assertEquals($fb_public_key->get_encoding_format(), $encoding_format);
		$this->assertEquals($fb_public_key->get_project(), $project);
	}

	/**
	 * Test FBPublicKey with EdDSA algorithm and HEX encoding.
	 *
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::__construct
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_key
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_algorithm
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_encoding_format
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_project
	 */
	public function test_fb_public_key_with_eddsa_algorithm(): void {
		$algorithm = FBPublicKey::ALGORITHM_EDDSA;
		$key = 'eddsa_test_key_hex_encoded';
		$encoding_format = FBPublicKey::ENCODING_FORMAT_HEX;
		$project = 'eddsa_project';

		$fb_public_key = new FBPublicKey($key, $algorithm, $encoding_format, $project);

		$this->assertEquals($key, $fb_public_key->get_key());
		$this->assertEquals($algorithm, $fb_public_key->get_algorithm());
		$this->assertEquals($encoding_format, $fb_public_key->get_encoding_format());
		$this->assertEquals($project, $fb_public_key->get_project());
	}

	/**
	 * Test FBPublicKey with HEX encoding format.
	 *
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::__construct
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_key
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_algorithm
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_encoding_format
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_project
	 */
	public function test_fb_public_key_with_hex_encoding(): void {
		$algorithm = FBPublicKey::ALGORITHM_ES256;
		$key = '04a1b2c3d4e5f6';
		$encoding_format = FBPublicKey::ENCODING_FORMAT_HEX;
		$project = 'hex_project';

		$fb_public_key = new FBPublicKey($key, $algorithm, $encoding_format, $project);

		$this->assertEquals($key, $fb_public_key->get_key());
		$this->assertEquals($algorithm, $fb_public_key->get_algorithm());
		$this->assertEquals($encoding_format, $fb_public_key->get_encoding_format());
		$this->assertEquals($project, $fb_public_key->get_project());
	}

	/**
	 * Test FBPublicKey with empty strings for all parameters.
	 *
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::__construct
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_key
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_algorithm
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_encoding_format
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_project
	 */
	public function test_fb_public_key_with_empty_strings(): void {
		$key = '';
		$algorithm = '';
		$encoding_format = '';
		$project = '';

		$fb_public_key = new FBPublicKey($key, $algorithm, $encoding_format, $project);

		$this->assertEquals('', $fb_public_key->get_key());
		$this->assertEquals('', $fb_public_key->get_algorithm());
		$this->assertEquals('', $fb_public_key->get_encoding_format());
		$this->assertEquals('', $fb_public_key->get_project());
	}

	/**
	 * Test FBPublicKey with special characters in key and project.
	 *
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::__construct
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_key
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_algorithm
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_encoding_format
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_project
	 */
	public function test_fb_public_key_with_special_characters(): void {
		$key = "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA\n-----END PUBLIC KEY-----";
		$algorithm = FBPublicKey::ALGORITHM_ES256;
		$encoding_format = FBPublicKey::ENCODING_FORMAT_PEM;
		$project = 'test-project_123';

		$fb_public_key = new FBPublicKey($key, $algorithm, $encoding_format, $project);

		$this->assertEquals($key, $fb_public_key->get_key());
		$this->assertEquals($algorithm, $fb_public_key->get_algorithm());
		$this->assertEquals($encoding_format, $fb_public_key->get_encoding_format());
		$this->assertEquals($project, $fb_public_key->get_project());
	}

	/**
	 * Test FBPublicKey class constants have expected values.
	 */
	public function test_fb_public_key_constants(): void {
		$this->assertEquals('PEM', FBPublicKey::ENCODING_FORMAT_PEM);
		$this->assertEquals('HEX', FBPublicKey::ENCODING_FORMAT_HEX);
		$this->assertEquals('ES256', FBPublicKey::ALGORITHM_ES256);
		$this->assertEquals('EdDSA', FBPublicKey::ALGORITHM_EDDSA);
	}

	/**
	 * Test FBPublicKey immutability - values remain consistent across multiple getter calls.
	 *
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::__construct
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_key
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_algorithm
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_encoding_format
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_project
	 */
	public function test_fb_public_key_immutability(): void {
		$key = 'immutable_key';
		$algorithm = FBPublicKey::ALGORITHM_ES256;
		$encoding_format = FBPublicKey::ENCODING_FORMAT_PEM;
		$project = 'immutable_project';

		$fb_public_key = new FBPublicKey($key, $algorithm, $encoding_format, $project);

		// Call getters multiple times and verify consistency
		$this->assertEquals($key, $fb_public_key->get_key());
		$this->assertEquals($key, $fb_public_key->get_key());
		$this->assertEquals($key, $fb_public_key->get_key());

		$this->assertEquals($algorithm, $fb_public_key->get_algorithm());
		$this->assertEquals($algorithm, $fb_public_key->get_algorithm());

		$this->assertEquals($encoding_format, $fb_public_key->get_encoding_format());
		$this->assertEquals($encoding_format, $fb_public_key->get_encoding_format());

		$this->assertEquals($project, $fb_public_key->get_project());
		$this->assertEquals($project, $fb_public_key->get_project());
	}

	/**
	 * Test FBPublicKey with a very long key string.
	 *
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::__construct
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_key
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_algorithm
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_encoding_format
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_project
	 */
	public function test_fb_public_key_with_long_key_string(): void {
		// Generate a long key string (500+ characters)
		$key = str_repeat('A1B2C3D4E5F6G7H8I9J0', 30); // 600 characters
		$algorithm = FBPublicKey::ALGORITHM_EDDSA;
		$encoding_format = FBPublicKey::ENCODING_FORMAT_HEX;
		$project = 'long_key_project';

		$fb_public_key = new FBPublicKey($key, $algorithm, $encoding_format, $project);

		$this->assertEquals($key, $fb_public_key->get_key());
		$this->assertEquals(600, strlen($fb_public_key->get_key()));
		$this->assertEquals($algorithm, $fb_public_key->get_algorithm());
		$this->assertEquals($encoding_format, $fb_public_key->get_encoding_format());
		$this->assertEquals($project, $fb_public_key->get_project());
	}

	/**
	 * Test that different FBPublicKey instances maintain independent state.
	 *
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::__construct
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_key
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_algorithm
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_encoding_format
	 * @covers \WooCommerce\Facebook\FBSignedData\FBPublicKey::get_project
	 */
	public function test_fb_public_key_different_instances(): void {
		$key1 = 'first_key';
		$algorithm1 = FBPublicKey::ALGORITHM_ES256;
		$encoding_format1 = FBPublicKey::ENCODING_FORMAT_PEM;
		$project1 = 'first_project';

		$key2 = 'second_key';
		$algorithm2 = FBPublicKey::ALGORITHM_EDDSA;
		$encoding_format2 = FBPublicKey::ENCODING_FORMAT_HEX;
		$project2 = 'second_project';

		$fb_public_key1 = new FBPublicKey($key1, $algorithm1, $encoding_format1, $project1);
		$fb_public_key2 = new FBPublicKey($key2, $algorithm2, $encoding_format2, $project2);

		// Verify first instance
		$this->assertEquals($key1, $fb_public_key1->get_key());
		$this->assertEquals($algorithm1, $fb_public_key1->get_algorithm());
		$this->assertEquals($encoding_format1, $fb_public_key1->get_encoding_format());
		$this->assertEquals($project1, $fb_public_key1->get_project());

		// Verify second instance
		$this->assertEquals($key2, $fb_public_key2->get_key());
		$this->assertEquals($algorithm2, $fb_public_key2->get_algorithm());
		$this->assertEquals($encoding_format2, $fb_public_key2->get_encoding_format());
		$this->assertEquals($project2, $fb_public_key2->get_project());

		// Verify instances are independent
		$this->assertNotEquals($fb_public_key1->get_key(), $fb_public_key2->get_key());
		$this->assertNotEquals($fb_public_key1->get_algorithm(), $fb_public_key2->get_algorithm());
		$this->assertNotEquals($fb_public_key1->get_encoding_format(), $fb_public_key2->get_encoding_format());
		$this->assertNotEquals($fb_public_key1->get_project(), $fb_public_key2->get_project());
	}
}
