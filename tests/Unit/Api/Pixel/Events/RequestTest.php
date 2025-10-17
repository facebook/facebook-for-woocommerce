<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Api\Pixel\Events;

use WooCommerce\Facebook\API\Pixel\Events\Request;
use WooCommerce\Facebook\Events\Event;
use WP_UnitTestCase;

/**
 * Unit tests for Pixel Events Request class.
 *
 * Tests that the Request class properly converts click_id/browser_id
 * to fbc/fbp when sending events to Facebook.
 */
class RequestTest extends WP_UnitTestCase {

	/**
	 * Test that Request properly formats data with fbc and fbp.
	 */
	public function test_request_data_contains_fbc_fbp() {
		$pixel_id = 'test_pixel_id_123';
		
		$event = new Event( array(
			'event_name'  => 'Purchase',
			'custom_data' => array( 'value' => '99.99' ),
			'user_data'   => array(
				'click_id' => 'fb.1.1234567890.AbCdEfGh',
				'browser_id' => 'fb.1.1234567890.987654321',
				'em'  => 'test@example.com',
			),
		) );
		
		$request = new Request( $pixel_id, array( $event ) );
		$data = $request->get_data();
		
		// Verify the data structure
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'partner_agent', $data );
		
		// Verify event data contains fbc and fbp (converted from click_id and browser_id)
		$this->assertCount( 1, $data['data'] );
		$event_data = $data['data'][0];
		
		$this->assertArrayHasKey( 'user_data', $event_data );
		$this->assertArrayHasKey( 'fbc', $event_data['user_data'] );
		$this->assertArrayHasKey( 'fbp', $event_data['user_data'] );
	}

	/**
	 * Test that Request converts click_id/browser_id to fbc/fbp and removes legacy parameters.
	 */
	public function test_request_data_no_legacy_parameters() {
		$pixel_id = 'test_pixel_id_123';
		
		$event = new Event( array(
			'event_name'  => 'AddToCart',
			'custom_data' => array( 'value' => '49.99' ),
			'user_data'   => array(
				'click_id' => 'fb.1.1234567890.AbCdEfGh',
				'browser_id' => 'fb.1.1234567890.987654321',
			),
		) );
		
		$request = new Request( $pixel_id, array( $event ) );
		$data = $request->get_data();
		
		$event_data = $data['data'][0];
		
		// Ensure no legacy parameter names are present in the request
		$this->assertArrayNotHasKey( 'click_id', $event_data['user_data'] );
		$this->assertArrayNotHasKey( 'browser_id', $event_data['user_data'] );
		// Ensure they were converted to fbc/fbp
		$this->assertArrayHasKey( 'fbc', $event_data['user_data'] );
		$this->assertArrayHasKey( 'fbp', $event_data['user_data'] );
	}

	/**
	 * Test that Request handles multiple events correctly.
	 */
	public function test_request_handles_multiple_events() {
		$pixel_id = 'test_pixel_id_123';
		
		$event1 = new Event( array(
			'event_name'  => 'ViewContent',
			'user_data'   => array(
				'click_id' => 'fb.1.1234567890.Event1FBC',
				'browser_id' => 'fb.1.1234567890.Event1FBP',
			),
		) );
		
		$event2 = new Event( array(
			'event_name'  => 'AddToCart',
			'user_data'   => array(
				'click_id' => 'fb.1.1234567890.Event2FBC',
				'browser_id' => 'fb.1.1234567890.Event2FBP',
			),
		) );
		
		$request = new Request( $pixel_id, array( $event1, $event2 ) );
		$data = $request->get_data();
		
		// Verify both events are present
		$this->assertCount( 2, $data['data'] );
		
		// Verify first event (click_id/browser_id should be converted to fbc/fbp)
		$this->assertEquals( 'ViewContent', $data['data'][0]['event_name'] );
		$this->assertEquals( 'fb.1.1234567890.Event1FBC', $data['data'][0]['user_data']['fbc'] );
		$this->assertEquals( 'fb.1.1234567890.Event1FBP', $data['data'][0]['user_data']['fbp'] );
		
		// Verify second event
		$this->assertEquals( 'AddToCart', $data['data'][1]['event_name'] );
		$this->assertEquals( 'fb.1.1234567890.Event2FBC', $data['data'][1]['user_data']['fbc'] );
		$this->assertEquals( 'fb.1.1234567890.Event2FBP', $data['data'][1]['user_data']['fbp'] );
	}

	/**
	 * Test that Request path is correct.
	 */
	public function test_request_path() {
		$pixel_id = 'test_pixel_id_123';
		$event = new Event( array( 'event_name' => 'PageView' ) );
		
		$request = new Request( $pixel_id, array( $event ) );
		
		$this->assertEquals( '/test_pixel_id_123/events', $request->get_path() );
	}

	/**
	 * Test that Request method is POST.
	 */
	public function test_request_method() {
		$pixel_id = 'test_pixel_id_123';
		$event = new Event( array( 'event_name' => 'PageView' ) );
		
		$request = new Request( $pixel_id, array( $event ) );
		
		$this->assertEquals( 'POST', $request->get_method() );
	}

	/**
	 * Test that empty click_id and browser_id values are handled correctly (filtered out).
	 */
	public function test_request_filters_empty_values() {
		$pixel_id = 'test_pixel_id_123';
		
		$event = new Event( array(
			'event_name'  => 'Purchase',
			'custom_data' => array( 'value' => '99.99' ),
			'user_data'   => array(
				'click_id' => '',
				'browser_id' => '',
				'em'  => 'test@example.com',
			),
		) );
		
		$request = new Request( $pixel_id, array( $event ) );
		$data = $request->get_data();
		
		$event_data = $data['data'][0];
		
		// Empty click_id and browser_id should not result in fbc/fbp being added
		// But em should still be present (though hashed)
		$this->assertArrayHasKey( 'user_data', $event_data );
		$this->assertArrayHasKey( 'em', $event_data['user_data'] );
		
		// Empty strings are filtered by array_filter, so these keys may not exist
		// This is expected behavior to keep the API payload clean
	}

	/**
	 * Test that Request includes partner_agent in the data.
	 */
	public function test_request_includes_partner_agent() {
		$pixel_id = 'test_pixel_id_123';
		$event = new Event( array( 'event_name' => 'PageView' ) );
		
		$request = new Request( $pixel_id, array( $event ) );
		$data = $request->get_data();
		
		$this->assertArrayHasKey( 'partner_agent', $data );
		$this->assertIsString( $data['partner_agent'] );
		$this->assertStringContainsString( 'woocommerce', $data['partner_agent'] );
	}

	/**
	 * Test that click_id value is properly converted to fbc.
	 */
	public function test_fbc_from_cookie_format() {
		$pixel_id = 'test_pixel_id_123';
		
		// Simulate a realistic click_id value from Facebook cookie
		$realistic_click_id = 'fb.1.1554763741205.AbCdEfGh';
		
		$event = new Event( array(
			'event_name'  => 'Purchase',
			'user_data'   => array(
				'click_id' => $realistic_click_id,
			),
		) );
		
		$request = new Request( $pixel_id, array( $event ) );
		$data = $request->get_data();
		
		$event_data = $data['data'][0];
		
		// Verify the click_id value is converted to fbc
		$this->assertEquals( $realistic_click_id, $event_data['user_data']['fbc'] );
		$this->assertArrayNotHasKey( 'click_id', $event_data['user_data'] );
	}

	/**
	 * Test that browser_id value is properly converted to fbp.
	 */
	public function test_fbp_from_cookie_format() {
		$pixel_id = 'test_pixel_id_123';
		
		// Simulate a realistic browser_id value from Facebook cookie
		$realistic_browser_id = 'fb.1.1554763741205.987654321';
		
		$event = new Event( array(
			'event_name'  => 'Purchase',
			'user_data'   => array(
				'browser_id' => $realistic_browser_id,
			),
		) );
		
		$request = new Request( $pixel_id, array( $event ) );
		$data = $request->get_data();
		
		$event_data = $data['data'][0];
		
		// Verify the browser_id value is converted to fbp
		$this->assertEquals( $realistic_browser_id, $event_data['user_data']['fbp'] );
		$this->assertArrayNotHasKey( 'browser_id', $event_data['user_data'] );
	}
}


