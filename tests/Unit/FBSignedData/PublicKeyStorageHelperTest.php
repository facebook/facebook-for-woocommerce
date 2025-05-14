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
	const MOCK_PREVIOUS_PUBLIC_KEYS =
		[
			[
				'key' => 'previous_key_1',
				'algorithm' => FBPublicKey::ALGORITHM_ES256,
				'encoding_format' => FBPublicKey::ENCODING_FORMAT_PEM
			],
			[
				'key' => 'previous_key_2',
				'algorithm' => FBPublicKey::ALGORITHM_ES256,
				'encoding_format' => FBPublicKey::ENCODING_FORMAT_PEM
			],
		];


	public function test_store_api_response(): void {
		$mock_fb = $this->get_plugin_with_mocked_response(self::MOCK_CURRENT_PUBLIC_KEY, self::MOCK_NEXT_PUBLIC_KEY, self::MOCK_PREVIOUS_PUBLIC_KEYS);

		$this->assertFalse(get_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY));
		$this->assertFalse(get_option(PublicKeyStorageHelper::SETTING_NEXT_PUBLIC_KEY));
		$this->assertFalse(get_option(PublicKeyStorageHelper::SETTING_PREVIOUS_PUBLIC_KEYS));


		PublicKeyStorageHelper::request_and_store_public_key( $mock_fb );

        $this->assert_on_key_data(
            [
                'current' => self::MOCK_CURRENT_PUBLIC_KEY,
                'next' => self::MOCK_NEXT_PUBLIC_KEY,
                'previous' => self::MOCK_PREVIOUS_PUBLIC_KEYS
            ]);

		// Test setting invalid data
		$mock_fb = $this->get_plugin_with_mocked_response(null, null, []);
		PublicKeyStorageHelper::request_and_store_public_key( $mock_fb );
        $this->assert_on_key_data(
            [
                'current' => self::MOCK_CURRENT_PUBLIC_KEY,
                'next' => self::MOCK_NEXT_PUBLIC_KEY,
                'previous' => self::MOCK_PREVIOUS_PUBLIC_KEYS
            ]);
	}

    private function assert_on_key_data($expected_key_data) {
        $current_expected_key_data  = $expected_key_data['current'];
        $next_expected_key_data     = $expected_key_data['next'];
        $previous_expected_key_data = $expected_key_data['previous'];

        $this->assertEquals($current_expected_key_data, get_option(PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY));
        $this->assertEquals($next_expected_key_data, get_option(PublicKeyStorageHelper::SETTING_NEXT_PUBLIC_KEY));
        $this->assertEquals($previous_expected_key_data, get_option(PublicKeyStorageHelper::SETTING_PREVIOUS_PUBLIC_KEYS));


        $current_key = PublicKeyStorageHelper::get_current_public_key();
        $this->assertEquals($current_expected_key_data['key'], $current_key->get_key());
        $this->assertEquals($current_expected_key_data['algorithm'], $current_key->get_algorithm());
        $this->assertEquals($current_expected_key_data['encoding_format'], $current_key->get_encoding_format());

        $next_key = PublicKeyStorageHelper::get_next_public_key();
        $this->assertEquals($next_expected_key_data['key'], $next_key->get_key());
        $this->assertEquals($next_expected_key_data['algorithm'], $next_key->get_algorithm());
        $this->assertEquals($next_expected_key_data['encoding_format'], $next_key->get_encoding_format());

        $previous_keys = PublicKeyStorageHelper::get_previous_public_keys();
        $previous_key_data_from_objects = array_map(function ($previous_key) {
            return [
                'key' => $previous_key->get_key(),
                'algorithm' => $previous_key->get_algorithm(),
                'encoding_format' => $previous_key->get_encoding_format(),
            ];
        }, $previous_keys );
        $this->assertEqualsCanonicalizing($previous_expected_key_data, $previous_key_data_from_objects);
    }


	private function get_plugin_with_mocked_response(?array $current_key, ?array $next_key, array $previous_keys): \WC_Facebookcommerce{
		$response_data =
			[
				'current' => $current_key,
				'next' => $next_key,
				'previous' => $previous_keys,
			];
		$response_string = wp_json_encode($response_data);
		$response = new WooCommerce\Facebook\API\Response($response_string );

		$mock_api = $this->getMockBuilder(WooCommerce\Facebook\API::class)
			->setConstructorArgs(['access_token'])
			->setMethods(['get_public_key'])
			->getMock();
		$mock_api->method('get_public_key')
			->willReturnCallback(function() use (&$response) {
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
}
