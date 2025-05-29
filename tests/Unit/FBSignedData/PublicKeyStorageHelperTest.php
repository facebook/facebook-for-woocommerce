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
}
