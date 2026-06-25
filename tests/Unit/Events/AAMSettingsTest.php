<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Events;

use WooCommerce\Facebook\Events\AAMSettings;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Events AAMSettings class.
 *
 * @since 3.5.2
 */
class AAMSettingsTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the AAMSettings class exists and can be instantiated.
	 */
	public function test_aam_settings_class_exists() {
		$this->assertTrue( class_exists( AAMSettings::class ) );
	}

	/**
	 * Test AAMSettings instantiation with empty data.
	 */
	public function test_aam_settings_with_empty_data() {
		$settings = new AAMSettings();
		
		$this->assertNull( $settings->get_enable_automatic_matching() );
		$this->assertNull( $settings->get_enabled_automatic_matching_fields() );
		$this->assertNull( $settings->get_pixel_id() );
	}

	/**
	 * Test AAMSettings instantiation with complete data.
	 */
	public function test_aam_settings_with_complete_data() {
		$data = [
			'enableAutomaticMatching' => true,
			'enabledAutomaticMatchingFields' => [ 'em', 'fn', 'ln', 'ph' ],
			'pixelId' => '1234567890',
		];
		
		$settings = new AAMSettings( $data );
		
		$this->assertTrue( $settings->get_enable_automatic_matching() );
		$this->assertEquals( [ 'em', 'fn', 'ln', 'ph' ], $settings->get_enabled_automatic_matching_fields() );
		$this->assertEquals( '1234567890', $settings->get_pixel_id() );
	}

	/**
	 * Test AAMSettings with partial data.
	 */
	public function test_aam_settings_with_partial_data() {
		$data = [
			'enableAutomaticMatching' => false,
			'pixelId' => '9876543210',
		];
		
		$settings = new AAMSettings( $data );
		
		$this->assertFalse( $settings->get_enable_automatic_matching() );
		$this->assertNull( $settings->get_enabled_automatic_matching_fields() );
		$this->assertEquals( '9876543210', $settings->get_pixel_id() );
	}

	/**
	 * Test setting enable automatic matching.
	 */
	public function test_set_enable_automatic_matching() {
		$settings = new AAMSettings();
		
		$result = $settings->set_enable_automatic_matching( true );
		$this->assertInstanceOf( AAMSettings::class, $result );
		$this->assertTrue( $settings->get_enable_automatic_matching() );
		
		$settings->set_enable_automatic_matching( false );
		$this->assertFalse( $settings->get_enable_automatic_matching() );
	}

	/**
	 * Test setting enabled automatic matching fields.
	 */
	public function test_set_enabled_automatic_matching_fields() {
		$settings = new AAMSettings();
		$fields = [ 'em', 'fn', 'ln', 'ph', 'ct', 'st', 'zp' ];
		
		$result = $settings->set_enabled_automatic_matching_fields( $fields );
		$this->assertInstanceOf( AAMSettings::class, $result );
		$this->assertEquals( $fields, $settings->get_enabled_automatic_matching_fields() );
		
		// Test with empty array
		$settings->set_enabled_automatic_matching_fields( [] );
		$this->assertEquals( [], $settings->get_enabled_automatic_matching_fields() );
	}

	/**
	 * Test setting pixel ID.
	 */
	public function test_set_pixel_id() {
		$settings = new AAMSettings();
		$pixelId = '1234567890';
		
		$result = $settings->set_pixel_id( $pixelId );
		$this->assertInstanceOf( AAMSettings::class, $result );
		$this->assertEquals( $pixelId, $settings->get_pixel_id() );
		
		// Test with different pixel ID
		$settings->set_pixel_id( '0987654321' );
		$this->assertEquals( '0987654321', $settings->get_pixel_id() );
	}

	/**
	 * Test method chaining.
	 */
	public function test_method_chaining() {
		$settings = new AAMSettings();
		
		$result = $settings
			->set_enable_automatic_matching( true )
			->set_enabled_automatic_matching_fields( [ 'em', 'fn' ] )
			->set_pixel_id( '123456' );
		
		$this->assertInstanceOf( AAMSettings::class, $result );
		$this->assertTrue( $settings->get_enable_automatic_matching() );
		$this->assertEquals( [ 'em', 'fn' ], $settings->get_enabled_automatic_matching_fields() );
		$this->assertEquals( '123456', $settings->get_pixel_id() );
	}

	/**
	 * Test get_url static method.
	 */
	public function test_get_url() {
		$pixelId = '1234567890';
		$expectedUrl = 'https://connect.facebook.net/signals/config/json/' . $pixelId;
		
		$url = AAMSettings::get_url( $pixelId );
		
		$this->assertEquals( $expectedUrl, $url );
	}

	/**
	 * Test get_url with different pixel IDs.
	 */
	public function test_get_url_with_various_pixel_ids() {
		$testCases = [
			'123' => 'https://connect.facebook.net/signals/config/json/123',
			'abc123xyz' => 'https://connect.facebook.net/signals/config/json/abc123xyz',
			'9876543210' => 'https://connect.facebook.net/signals/config/json/9876543210',
		];
		
		foreach ( $testCases as $pixelId => $expectedUrl ) {
			$this->assertEquals( $expectedUrl, AAMSettings::get_url( $pixelId ) );
		}
	}

	/**
	 * Test toString method with all data.
	 */
	public function test_to_string_with_all_data() {
		$data = [
			'enableAutomaticMatching' => true,
			'enabledAutomaticMatchingFields' => [ 'em', 'fn', 'ln' ],
			'pixelId' => '1234567890',
		];
		
		$settings = new AAMSettings( $data );
		$json = (string) $settings;
		
		$decoded = json_decode( $json, true );
		
		$this->assertIsArray( $decoded );
		$this->assertTrue( $decoded['enableAutomaticMatching'] );
		$this->assertEquals( [ 'em', 'fn', 'ln' ], $decoded['enabledAutomaticMatchingFields'] );
		$this->assertEquals( '1234567890', $decoded['pixelId'] );
	}

	/**
	 * Test toString method with null values.
	 */
	public function test_to_string_with_null_values() {
		$settings = new AAMSettings();
		$json = (string) $settings;
		
		$decoded = json_decode( $json, true );
		
		$this->assertIsArray( $decoded );
		$this->assertNull( $decoded['enableAutomaticMatching'] );
		$this->assertNull( $decoded['enabledAutomaticMatchingFields'] );
		$this->assertNull( $decoded['pixelId'] );
	}

	/**
	 * Test toString method with mixed values.
	 */
	public function test_to_string_with_mixed_values() {
		$settings = new AAMSettings();
		$settings->set_enable_automatic_matching( false )
		         ->set_enabled_automatic_matching_fields( [] )
		         ->set_pixel_id( 'test123' );
		
		$json = (string) $settings;
		$decoded = json_decode( $json, true );
		
		$this->assertFalse( $decoded['enableAutomaticMatching'] );
		$this->assertEquals( [], $decoded['enabledAutomaticMatchingFields'] );
		$this->assertEquals( 'test123', $decoded['pixelId'] );
	}

	/**
	 * Test AAMSettings with boolean string values.
	 */
	public function test_aam_settings_with_boolean_string_values() {
		$data = [
			'enableAutomaticMatching' => 'true', // string instead of boolean
			'enabledAutomaticMatchingFields' => [ 'em' ],
			'pixelId' => '123',
		];
		
		$settings = new AAMSettings( $data );
		
		// PHP will treat the string 'true' as truthy
		$this->assertEquals( 'true', $settings->get_enable_automatic_matching() );
	}

	/**
	 * Test AAMSettings with unexpected data keys.
	 */
	public function test_aam_settings_with_unexpected_keys() {
		$data = [
			'enableAutomaticMatching' => true,
			'enabledAutomaticMatchingFields' => [ 'em' ],
			'pixelId' => '123',
			'unexpectedKey' => 'unexpectedValue',
			'anotherKey' => 123,
		];
		
		$settings = new AAMSettings( $data );
		
		// Should only set the expected properties
		$this->assertTrue( $settings->get_enable_automatic_matching() );
		$this->assertEquals( [ 'em' ], $settings->get_enabled_automatic_matching_fields() );
		$this->assertEquals( '123', $settings->get_pixel_id() );
	}

	/**
	 * Test build_from_pixel_id with successful response.
	 *
	 * @covers AAMSettings::build_from_pixel_id
	 */
	public function test_build_from_pixel_id_with_successful_response() {
		$pixel_id = '1234567890';
		$expected_url = 'https://connect.facebook.net/signals/config/json/' . $pixel_id;
		
		$response_body = [
			'matchingConfig' => [
				'enableAutomaticMatching' => true,
				'enabledAutomaticMatchingFields' => [ 'em', 'fn' ],
			],
		];
		
		$filter = $this->add_filter_with_safe_teardown(
			'pre_http_request',
			function( $preempt, $args, $url ) use ( $expected_url, $response_body ) {
				if ( $url === $expected_url ) {
					return [
						'body' => wp_json_encode( $response_body ),
						'response' => [ 'code' => 200 ],
					];
				}
				return $preempt;
			},
			10,
			3
		);
		
		$settings = AAMSettings::build_from_pixel_id( $pixel_id );
		
		$this->assertInstanceOf( AAMSettings::class, $settings );
		$this->assertTrue( $settings->get_enable_automatic_matching() );
		$this->assertEquals( [ 'em', 'fn' ], $settings->get_enabled_automatic_matching_fields() );
		$this->assertEquals( $pixel_id, $settings->get_pixel_id() );
		
		$filter->teardown_safely_immediately();
	}

	/**
	 * Test build_from_pixel_id with WP_Error.
	 *
	 * @covers AAMSettings::build_from_pixel_id
	 */
	public function test_build_from_pixel_id_with_wp_error() {
		$pixel_id = '1234567890';
		$expected_url = 'https://connect.facebook.net/signals/config/json/' . $pixel_id;
		
		$filter = $this->add_filter_with_safe_teardown(
			'pre_http_request',
			function( $preempt, $args, $url ) use ( $expected_url ) {
				if ( $url === $expected_url ) {
					return new \WP_Error( 'http_request_failed', 'Connection timeout' );
				}
				return $preempt;
			},
			10,
			3
		);
		
		$settings = AAMSettings::build_from_pixel_id( $pixel_id );
		
		$this->assertNull( $settings );
		
		$filter->teardown_safely_immediately();
	}

	/**
	 * Test build_from_pixel_id with error message in response.
	 *
	 * @covers AAMSettings::build_from_pixel_id
	 */
	public function test_build_from_pixel_id_with_error_message_in_response() {
		$pixel_id = '1234567890';
		$expected_url = 'https://connect.facebook.net/signals/config/json/' . $pixel_id;
		
		$response_body = [
			'errorMessage' => 'Invalid pixel ID',
		];
		
		$filter = $this->add_filter_with_safe_teardown(
			'pre_http_request',
			function( $preempt, $args, $url ) use ( $expected_url, $response_body ) {
				if ( $url === $expected_url ) {
					return [
						'body' => wp_json_encode( $response_body ),
						'response' => [ 'code' => 400 ],
					];
				}
				return $preempt;
			},
			10,
			3
		);
		
		$settings = AAMSettings::build_from_pixel_id( $pixel_id );
		
		$this->assertNull( $settings );
		
		$filter->teardown_safely_immediately();
	}

	/**
	 * Test build_from_pixel_id with missing matching config.
	 *
	 * @covers AAMSettings::build_from_pixel_id
	 */
	public function test_build_from_pixel_id_with_missing_matching_config() {
		$pixel_id = '1234567890';
		$expected_url = 'https://connect.facebook.net/signals/config/json/' . $pixel_id;
		
		$response_body = [
			'someOtherData' => 'value',
		];
		
		$filter = $this->add_filter_with_safe_teardown(
			'pre_http_request',
			function( $preempt, $args, $url ) use ( $expected_url, $response_body ) {
				if ( $url === $expected_url ) {
					return [
						'body' => wp_json_encode( $response_body ),
						'response' => [ 'code' => 200 ],
					];
				}
				return $preempt;
			},
			10,
			3
		);
		
		$settings = AAMSettings::build_from_pixel_id( $pixel_id );
		
		$this->assertNull( $settings );
		
		$filter->teardown_safely_immediately();
	}

	/**
	 * Test build_from_pixel_id with invalid JSON response.
	 *
	 * @covers AAMSettings::build_from_pixel_id
	 */
	public function test_build_from_pixel_id_with_invalid_json_response() {
		$pixel_id = '1234567890';
		$expected_url = 'https://connect.facebook.net/signals/config/json/' . $pixel_id;
		
		$filter = $this->add_filter_with_safe_teardown(
			'pre_http_request',
			function( $preempt, $args, $url ) use ( $expected_url ) {
				if ( $url === $expected_url ) {
					return [
						'body' => 'invalid json {{{',
						'response' => [ 'code' => 200 ],
					];
				}
				return $preempt;
			},
			10,
			3
		);
		
		$settings = AAMSettings::build_from_pixel_id( $pixel_id );
		
		$this->assertNull( $settings );
		
		$filter->teardown_safely_immediately();
	}

	/**
	 * Test build_from_pixel_id with empty response.
	 *
	 * @covers AAMSettings::build_from_pixel_id
	 */
	public function test_build_from_pixel_id_with_empty_response() {
		$pixel_id = '1234567890';
		$expected_url = 'https://connect.facebook.net/signals/config/json/' . $pixel_id;
		
		$filter = $this->add_filter_with_safe_teardown(
			'pre_http_request',
			function( $preempt, $args, $url ) use ( $expected_url ) {
				if ( $url === $expected_url ) {
					return [
						'body' => '',
						'response' => [ 'code' => 200 ],
					];
				}
				return $preempt;
			},
			10,
			3
		);
		
		$settings = AAMSettings::build_from_pixel_id( $pixel_id );
		
		$this->assertNull( $settings );
		
		$filter->teardown_safely_immediately();
	}

	/**
	 * Test build_from_pixel_id with complete matching config.
	 *
	 * @covers AAMSettings::build_from_pixel_id
	 */
	public function test_build_from_pixel_id_with_complete_matching_config() {
		$pixel_id = '9876543210';
		$expected_url = 'https://connect.facebook.net/signals/config/json/' . $pixel_id;
		
		$response_body = [
			'matchingConfig' => [
				'enableAutomaticMatching' => true,
				'enabledAutomaticMatchingFields' => [ 'em', 'fn', 'ln', 'ph', 'ct', 'st', 'zp', 'country' ],
			],
			'otherData' => 'should be ignored',
		];
		
		$filter = $this->add_filter_with_safe_teardown(
			'pre_http_request',
			function( $preempt, $args, $url ) use ( $expected_url, $response_body ) {
				if ( $url === $expected_url ) {
					return [
						'body' => wp_json_encode( $response_body ),
						'response' => [ 'code' => 200 ],
					];
				}
				return $preempt;
			},
			10,
			3
		);
		
		$settings = AAMSettings::build_from_pixel_id( $pixel_id );
		
		$this->assertInstanceOf( AAMSettings::class, $settings );
		$this->assertTrue( $settings->get_enable_automatic_matching() );
		$this->assertEquals( 
			[ 'em', 'fn', 'ln', 'ph', 'ct', 'st', 'zp', 'country' ], 
			$settings->get_enabled_automatic_matching_fields() 
		);
		$this->assertEquals( $pixel_id, $settings->get_pixel_id() );
		
		$filter->teardown_safely_immediately();
	}
} 
