<?php
/** Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

use WooCommerce\Facebook\FBSignedData\FBPublicKey;
use WooCommerce\Facebook\FBSignedData\PublicKeyStorageHelper;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Class FBPublicKeyTest
 */
class PublicKeyStorageHelperTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	const PROJECT_NAME = 'TEST_PROJECT';

	const MOCK_CURRENT_PUBLIC_KEY =
		[
			'key' => 'current_key_1',
			'algorithm' => FBPublicKey::ALGORITHM_ES256,
			'encoding_format' => FBPublicKey::ENCODING_FORMAT_PEM
		];

	const MOCK_NEXT_PUBLIC_KEY =
		[
			'key' => 'next_key_1',
			'algorithm' => FBPublicKey::ALGORITHM_EDDSA,
			'encoding_format' => FBPublicKey::ENCODING_FORMAT_HEX
		];

	public function test_store_api_response(): void {
		$this->assertFalse(get_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY));
		$this->assertFalse(get_option(PublicKeyStorageHelper::SETTING_NEXT_PUBLIC_KEY));

		$mock_fb = $this->get_plugin_with_mocked_response(self::MOCK_CURRENT_PUBLIC_KEY, self::MOCK_NEXT_PUBLIC_KEY, self::PROJECT_NAME);
		PublicKeyStorageHelper::request_and_store_public_key( $mock_fb, self::PROJECT_NAME );

		$expected_stored_current_key_data = array_merge(self::MOCK_CURRENT_PUBLIC_KEY, ['project' => self::PROJECT_NAME]);
		$expected_stored_next_key_data = array_merge(self::MOCK_NEXT_PUBLIC_KEY, ['project' => self::PROJECT_NAME]);

		$this->assert_on_key_data(
            [
                'current' => $expected_stored_current_key_data,
                'next' => $expected_stored_next_key_data,
            ]);

		// Test setting invalid data
		$mock_fb = $this->get_plugin_with_mocked_response(null, null,  self::PROJECT_NAME);
		PublicKeyStorageHelper::request_and_store_public_key( $mock_fb, self::PROJECT_NAME );
		$this->assert_on_key_data(
			[
				'current' => $expected_stored_current_key_data,
				'next' => $expected_stored_next_key_data,
			]);
	}

    public function test_store_invalid_data(): void {
        $mock_invalid_current_key_data =
        [
            'key' => 'current_key_1',
            'encoding_format' => FBPublicKey::ENCODING_FORMAT_PEM
        ];

        $mock_fb = $this->get_plugin_with_mocked_response($mock_invalid_current_key_data, null, self::PROJECT_NAME);

        $this->assertFalse(get_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY));
        $this->assertFalse(get_option(PublicKeyStorageHelper::SETTING_NEXT_PUBLIC_KEY));

        PublicKeyStorageHelper::request_and_store_public_key( $mock_fb, self::PROJECT_NAME );

        $this->assertFalse(get_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY));
        $this->assertFalse(get_option(PublicKeyStorageHelper::SETTING_NEXT_PUBLIC_KEY));

        $this->assertNull(PublicKeyStorageHelper::get_current_public_key());
        $this->assertNull(PublicKeyStorageHelper::get_next_public_key());
    }

    private function assert_on_key_data($expected_key_data) {
        $current_expected_key_data  = $expected_key_data['current'];
        $next_expected_key_data     = $expected_key_data['next'];

        $this->assertEquals($current_expected_key_data, get_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY));
        $this->assertEquals($next_expected_key_data, get_option(PublicKeyStorageHelper::SETTING_NEXT_PUBLIC_KEY));


        $current_key = PublicKeyStorageHelper::get_current_public_key();
        $this->assertEquals($current_expected_key_data['key'], $current_key->get_key());
        $this->assertEquals($current_expected_key_data['algorithm'], $current_key->get_algorithm());
        $this->assertEquals($current_expected_key_data['encoding_format'], $current_key->get_encoding_format());

        $next_key = PublicKeyStorageHelper::get_next_public_key();
        $this->assertEquals($next_expected_key_data['key'], $next_key->get_key());
        $this->assertEquals($next_expected_key_data['algorithm'], $next_key->get_algorithm());
        $this->assertEquals($next_expected_key_data['encoding_format'], $next_key->get_encoding_format());
    }


	private function get_plugin_with_mocked_response(?array $current_key, ?array $next_key, string $project): \WC_Facebookcommerce{
		$response_data =
			[
				'current' => $current_key,
				'next' => $next_key,
				'project' => $project,
			];
		$response_string = wp_json_encode($response_data);
		$response = new WooCommerce\Facebook\API\Response($response_string );

		$mock_api = $this->getMockBuilder(WooCommerce\Facebook\API::class)
			->setConstructorArgs(['access_token'])
			->setMethods(['get_public_key'])
			->getMock();
		$mock_api->method('get_public_key')
			->willReturnCallback(function( string $key_project ) use (&$response) {
				return $response;
			});

		$mock_fb = $this->getMockBuilder( \WC_Facebookcommerce::class )
			->setMethods(['get_api'])
			->getMock();
		$mock_fb->method('get_api')
			->willReturnCallback(function($access_token) use (&$mock_api) {
				return $mock_api;
			});

		return $mock_fb;
	}

	/**
	 * Test that get_current_public_key returns null when option is not set.
	 *
	 * @covers \WooCommerce\Facebook\FBSignedData\PublicKeyStorageHelper::get_current_public_key
	 */
	public function test_get_current_public_key_when_not_set(): void {
		$this->assertFalse(get_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY));
		$this->assertNull(PublicKeyStorageHelper::get_current_public_key());
	}

	/**
	 * Test that get_next_public_key returns null when option is not set.
	 *
	 * @covers \WooCommerce\Facebook\FBSignedData\PublicKeyStorageHelper::get_next_public_key
	 */
	public function test_get_next_public_key_when_not_set(): void {
		$this->assertFalse(get_option(PublicKeyStorageHelper::SETTING_NEXT_PUBLIC_KEY));
		$this->assertNull(PublicKeyStorageHelper::get_next_public_key());
	}

	/**
	 * Test that get_current_public_key returns null with invalid data.
	 *
	 * @covers \WooCommerce\Facebook\FBSignedData\PublicKeyStorageHelper::get_current_public_key
	 */
	public function test_get_current_public_key_with_invalid_data(): void {
		// Test with non-array data
		update_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY, 'invalid_string');
		$this->assertNull(PublicKeyStorageHelper::get_current_public_key());

		// Test with missing key field
		update_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY, [
			'algorithm' => FBPublicKey::ALGORITHM_ES256,
			'encoding_format' => FBPublicKey::ENCODING_FORMAT_PEM,
			'project' => self::PROJECT_NAME,
		]);
		$this->assertNull(PublicKeyStorageHelper::get_current_public_key());

		// Test with missing algorithm field
		update_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY, [
			'key' => 'test_key',
			'encoding_format' => FBPublicKey::ENCODING_FORMAT_PEM,
			'project' => self::PROJECT_NAME,
		]);
		$this->assertNull(PublicKeyStorageHelper::get_current_public_key());

		// Test with missing encoding_format field
		update_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY, [
			'key' => 'test_key',
			'algorithm' => FBPublicKey::ALGORITHM_ES256,
			'project' => self::PROJECT_NAME,
		]);
		$this->assertNull(PublicKeyStorageHelper::get_current_public_key());

		// Test with missing project field
		update_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY, [
			'key' => 'test_key',
			'algorithm' => FBPublicKey::ALGORITHM_ES256,
			'encoding_format' => FBPublicKey::ENCODING_FORMAT_PEM,
		]);
		$this->assertNull(PublicKeyStorageHelper::get_current_public_key());

		// Test with empty string values
		update_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY, [
			'key' => '',
			'algorithm' => FBPublicKey::ALGORITHM_ES256,
			'encoding_format' => FBPublicKey::ENCODING_FORMAT_PEM,
			'project' => self::PROJECT_NAME,
		]);
		$this->assertNull(PublicKeyStorageHelper::get_current_public_key());
	}

	/**
	 * Test that get_next_public_key returns null with invalid data.
	 *
	 * @covers \WooCommerce\Facebook\FBSignedData\PublicKeyStorageHelper::get_next_public_key
	 */
	public function test_get_next_public_key_with_invalid_data(): void {
		// Test with non-array data
		update_option(PublicKeyStorageHelper::SETTING_NEXT_PUBLIC_KEY, 'invalid_string');
		$this->assertNull(PublicKeyStorageHelper::get_next_public_key());

		// Test with missing key field
		update_option(PublicKeyStorageHelper::SETTING_NEXT_PUBLIC_KEY, [
			'algorithm' => FBPublicKey::ALGORITHM_EDDSA,
			'encoding_format' => FBPublicKey::ENCODING_FORMAT_HEX,
			'project' => self::PROJECT_NAME,
		]);
		$this->assertNull(PublicKeyStorageHelper::get_next_public_key());

		// Test with missing algorithm field
		update_option(PublicKeyStorageHelper::SETTING_NEXT_PUBLIC_KEY, [
			'key' => 'test_key',
			'encoding_format' => FBPublicKey::ENCODING_FORMAT_HEX,
			'project' => self::PROJECT_NAME,
		]);
		$this->assertNull(PublicKeyStorageHelper::get_next_public_key());

		// Test with missing encoding_format field
		update_option(PublicKeyStorageHelper::SETTING_NEXT_PUBLIC_KEY, [
			'key' => 'test_key',
			'algorithm' => FBPublicKey::ALGORITHM_EDDSA,
			'project' => self::PROJECT_NAME,
		]);
		$this->assertNull(PublicKeyStorageHelper::get_next_public_key());

		// Test with missing project field
		update_option(PublicKeyStorageHelper::SETTING_NEXT_PUBLIC_KEY, [
			'key' => 'test_key',
			'algorithm' => FBPublicKey::ALGORITHM_EDDSA,
			'encoding_format' => FBPublicKey::ENCODING_FORMAT_HEX,
		]);
		$this->assertNull(PublicKeyStorageHelper::get_next_public_key());

		// Test with empty string values
		update_option(PublicKeyStorageHelper::SETTING_NEXT_PUBLIC_KEY, [
			'key' => 'test_key',
			'algorithm' => '',
			'encoding_format' => FBPublicKey::ENCODING_FORMAT_HEX,
			'project' => self::PROJECT_NAME,
		]);
		$this->assertNull(PublicKeyStorageHelper::get_next_public_key());
	}

	/**
	 * Test that keys are not stored when project name is empty.
	 *
	 * @covers \WooCommerce\Facebook\FBSignedData\PublicKeyStorageHelper::request_and_store_public_key
	 */
	public function test_request_and_store_with_empty_project_name(): void {
		$this->assertFalse(get_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY));
		$this->assertFalse(get_option(PublicKeyStorageHelper::SETTING_NEXT_PUBLIC_KEY));

		$mock_fb = $this->get_plugin_with_mocked_response(self::MOCK_CURRENT_PUBLIC_KEY, self::MOCK_NEXT_PUBLIC_KEY, '');
		PublicKeyStorageHelper::request_and_store_public_key($mock_fb, '');

		// Keys should not be stored because project is empty
		$this->assertFalse(get_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY));
		$this->assertFalse(get_option(PublicKeyStorageHelper::SETTING_NEXT_PUBLIC_KEY));
		$this->assertNull(PublicKeyStorageHelper::get_current_public_key());
		$this->assertNull(PublicKeyStorageHelper::get_next_public_key());
	}

	/**
	 * Test that keys with missing required fields are not stored.
	 *
	 * @covers \WooCommerce\Facebook\FBSignedData\PublicKeyStorageHelper::request_and_store_public_key
	 */
	public function test_request_and_store_with_missing_key_fields(): void {
		// Test with missing 'key' field
		$invalid_key_data = [
			'algorithm' => FBPublicKey::ALGORITHM_ES256,
			'encoding_format' => FBPublicKey::ENCODING_FORMAT_PEM,
		];
		$mock_fb = $this->get_plugin_with_mocked_response($invalid_key_data, null, self::PROJECT_NAME);
		PublicKeyStorageHelper::request_and_store_public_key($mock_fb, self::PROJECT_NAME);
		$this->assertFalse(get_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY));

		// Test with missing 'algorithm' field
		$invalid_key_data = [
			'key' => 'test_key',
			'encoding_format' => FBPublicKey::ENCODING_FORMAT_PEM,
		];
		$mock_fb = $this->get_plugin_with_mocked_response($invalid_key_data, null, self::PROJECT_NAME);
		PublicKeyStorageHelper::request_and_store_public_key($mock_fb, self::PROJECT_NAME);
		$this->assertFalse(get_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY));

		// Test with missing 'encoding_format' field
		$invalid_key_data = [
			'key' => 'test_key',
			'algorithm' => FBPublicKey::ALGORITHM_ES256,
		];
		$mock_fb = $this->get_plugin_with_mocked_response($invalid_key_data, null, self::PROJECT_NAME);
		PublicKeyStorageHelper::request_and_store_public_key($mock_fb, self::PROJECT_NAME);
		$this->assertFalse(get_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY));
	}

	/**
	 * Test that keys with empty string values are not stored.
	 *
	 * @covers \WooCommerce\Facebook\FBSignedData\PublicKeyStorageHelper::request_and_store_public_key
	 */
	public function test_request_and_store_with_empty_string_values(): void {
		// Test with empty key string
		$invalid_key_data = [
			'key' => '',
			'algorithm' => FBPublicKey::ALGORITHM_ES256,
			'encoding_format' => FBPublicKey::ENCODING_FORMAT_PEM,
		];
		$mock_fb = $this->get_plugin_with_mocked_response($invalid_key_data, null, self::PROJECT_NAME);
		PublicKeyStorageHelper::request_and_store_public_key($mock_fb, self::PROJECT_NAME);
		$this->assertFalse(get_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY));

		// Test with empty algorithm string
		$invalid_key_data = [
			'key' => 'test_key',
			'algorithm' => '',
			'encoding_format' => FBPublicKey::ENCODING_FORMAT_PEM,
		];
		$mock_fb = $this->get_plugin_with_mocked_response($invalid_key_data, null, self::PROJECT_NAME);
		PublicKeyStorageHelper::request_and_store_public_key($mock_fb, self::PROJECT_NAME);
		$this->assertFalse(get_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY));

		// Test with empty encoding_format string
		$invalid_key_data = [
			'key' => 'test_key',
			'algorithm' => FBPublicKey::ALGORITHM_ES256,
			'encoding_format' => '',
		];
		$mock_fb = $this->get_plugin_with_mocked_response($invalid_key_data, null, self::PROJECT_NAME);
		PublicKeyStorageHelper::request_and_store_public_key($mock_fb, self::PROJECT_NAME);
		$this->assertFalse(get_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY));
	}

	/**
	 * Test that get_current_public_key returns correct FBPublicKey object.
	 *
	 * @covers \WooCommerce\Facebook\FBSignedData\PublicKeyStorageHelper::get_current_public_key
	 */
	public function test_get_current_public_key_returns_correct_fbpublickey_object(): void {
		$key_data = array_merge(self::MOCK_CURRENT_PUBLIC_KEY, ['project' => self::PROJECT_NAME]);
		update_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY, $key_data);

		$public_key = PublicKeyStorageHelper::get_current_public_key();

		$this->assertInstanceOf(FBPublicKey::class, $public_key);
		$this->assertEquals($key_data['key'], $public_key->get_key());
		$this->assertEquals($key_data['algorithm'], $public_key->get_algorithm());
		$this->assertEquals($key_data['encoding_format'], $public_key->get_encoding_format());
		$this->assertEquals($key_data['project'], $public_key->get_project());
	}

	/**
	 * Test that get_next_public_key returns correct FBPublicKey object.
	 *
	 * @covers \WooCommerce\Facebook\FBSignedData\PublicKeyStorageHelper::get_next_public_key
	 */
	public function test_get_next_public_key_returns_correct_fbpublickey_object(): void {
		$key_data = array_merge(self::MOCK_NEXT_PUBLIC_KEY, ['project' => self::PROJECT_NAME]);
		update_option(PublicKeyStorageHelper::SETTING_NEXT_PUBLIC_KEY, $key_data);

		$public_key = PublicKeyStorageHelper::get_next_public_key();

		$this->assertInstanceOf(FBPublicKey::class, $public_key);
		$this->assertEquals($key_data['key'], $public_key->get_key());
		$this->assertEquals($key_data['algorithm'], $public_key->get_algorithm());
		$this->assertEquals($key_data['encoding_format'], $public_key->get_encoding_format());
		$this->assertEquals($key_data['project'], $public_key->get_project());
	}

	/**
	 * Test storing only current key when next key is null.
	 *
	 * @covers \WooCommerce\Facebook\FBSignedData\PublicKeyStorageHelper::request_and_store_public_key
	 */
	public function test_request_and_store_only_current_key(): void {
		$this->assertFalse(get_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY));
		$this->assertFalse(get_option(PublicKeyStorageHelper::SETTING_NEXT_PUBLIC_KEY));

		$mock_fb = $this->get_plugin_with_mocked_response(self::MOCK_CURRENT_PUBLIC_KEY, null, self::PROJECT_NAME);
		PublicKeyStorageHelper::request_and_store_public_key($mock_fb, self::PROJECT_NAME);

		$expected_stored_current_key_data = array_merge(self::MOCK_CURRENT_PUBLIC_KEY, ['project' => self::PROJECT_NAME]);

		$this->assertEquals($expected_stored_current_key_data, get_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY));
		$this->assertFalse(get_option(PublicKeyStorageHelper::SETTING_NEXT_PUBLIC_KEY));

		$current_key = PublicKeyStorageHelper::get_current_public_key();
		$this->assertInstanceOf(FBPublicKey::class, $current_key);
		$this->assertEquals($expected_stored_current_key_data['key'], $current_key->get_key());

		$this->assertNull(PublicKeyStorageHelper::get_next_public_key());
	}

	/**
	 * Test storing only next key when current key is null.
	 *
	 * @covers \WooCommerce\Facebook\FBSignedData\PublicKeyStorageHelper::request_and_store_public_key
	 */
	public function test_request_and_store_only_next_key(): void {
		$this->assertFalse(get_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY));
		$this->assertFalse(get_option(PublicKeyStorageHelper::SETTING_NEXT_PUBLIC_KEY));

		$mock_fb = $this->get_plugin_with_mocked_response(null, self::MOCK_NEXT_PUBLIC_KEY, self::PROJECT_NAME);
		PublicKeyStorageHelper::request_and_store_public_key($mock_fb, self::PROJECT_NAME);

		$expected_stored_next_key_data = array_merge(self::MOCK_NEXT_PUBLIC_KEY, ['project' => self::PROJECT_NAME]);

		$this->assertFalse(get_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY));
		$this->assertEquals($expected_stored_next_key_data, get_option(PublicKeyStorageHelper::SETTING_NEXT_PUBLIC_KEY));

		$this->assertNull(PublicKeyStorageHelper::get_current_public_key());

		$next_key = PublicKeyStorageHelper::get_next_public_key();
		$this->assertInstanceOf(FBPublicKey::class, $next_key);
		$this->assertEquals($expected_stored_next_key_data['key'], $next_key->get_key());
	}

	/**
	 * Test that existing valid keys are not overwritten by invalid data.
	 *
	 * @covers \WooCommerce\Facebook\FBSignedData\PublicKeyStorageHelper::request_and_store_public_key
	 */
	public function test_existing_valid_keys_not_overwritten_by_invalid_data(): void {
		// First, store valid keys
		$mock_fb = $this->get_plugin_with_mocked_response(self::MOCK_CURRENT_PUBLIC_KEY, self::MOCK_NEXT_PUBLIC_KEY, self::PROJECT_NAME);
		PublicKeyStorageHelper::request_and_store_public_key($mock_fb, self::PROJECT_NAME);

		$expected_stored_current_key_data = array_merge(self::MOCK_CURRENT_PUBLIC_KEY, ['project' => self::PROJECT_NAME]);
		$expected_stored_next_key_data = array_merge(self::MOCK_NEXT_PUBLIC_KEY, ['project' => self::PROJECT_NAME]);

		$this->assertEquals($expected_stored_current_key_data, get_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY));
		$this->assertEquals($expected_stored_next_key_data, get_option(PublicKeyStorageHelper::SETTING_NEXT_PUBLIC_KEY));

		// Now attempt to store invalid keys
		$invalid_current_key = [
			'key' => 'invalid_key',
			'encoding_format' => FBPublicKey::ENCODING_FORMAT_PEM,
			// Missing algorithm
		];
		$invalid_next_key = [
			'algorithm' => FBPublicKey::ALGORITHM_EDDSA,
			'encoding_format' => FBPublicKey::ENCODING_FORMAT_HEX,
			// Missing key
		];

		$mock_fb = $this->get_plugin_with_mocked_response($invalid_current_key, $invalid_next_key, self::PROJECT_NAME);
		PublicKeyStorageHelper::request_and_store_public_key($mock_fb, self::PROJECT_NAME);

		// Verify that the original valid keys are still present
		$this->assertEquals($expected_stored_current_key_data, get_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY));
		$this->assertEquals($expected_stored_next_key_data, get_option(PublicKeyStorageHelper::SETTING_NEXT_PUBLIC_KEY));

		// Verify that the keys can still be retrieved as FBPublicKey objects
		$current_key = PublicKeyStorageHelper::get_current_public_key();
		$this->assertInstanceOf(FBPublicKey::class, $current_key);
		$this->assertEquals($expected_stored_current_key_data['key'], $current_key->get_key());

		$next_key = PublicKeyStorageHelper::get_next_public_key();
		$this->assertInstanceOf(FBPublicKey::class, $next_key);
		$this->assertEquals($expected_stored_next_key_data['key'], $next_key->get_key());
	}
}
