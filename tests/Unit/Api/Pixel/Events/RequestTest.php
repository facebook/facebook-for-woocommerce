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
		
		// Verify the values are correctly converted
		$this->assertEquals( 'fb.1.1234567890.AbCdEfGh', $event_data['user_data']['fbc'] );
		$this->assertEquals( 'fb.1.1234567890.987654321', $event_data['user_data']['fbp'] );
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
		// Ensure they were converted to fbc/fbp with correct values
		$this->assertArrayHasKey( 'fbc', $event_data['user_data'] );
		$this->assertArrayHasKey( 'fbp', $event_data['user_data'] );
		$this->assertEquals( 'fb.1.1234567890.AbCdEfGh', $event_data['user_data']['fbc'] );
		$this->assertEquals( 'fb.1.1234567890.987654321', $event_data['user_data']['fbp'] );
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
	 * Test that empty string click_id and browser_id values are handled correctly (filtered out).
	 */
	public function test_request_filters_empty_string_values() {
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
		
		// Empty string click_id and browser_id should not result in fbc/fbp being added
		// The ! empty() check in Request should prevent conversion
		$this->assertArrayHasKey( 'user_data', $event_data );
		$this->assertArrayHasKey( 'em', $event_data['user_data'] );
		
		// Empty strings are filtered by array_filter, so fbc/fbp keys should not exist
		// or if they do exist, they should be empty and filtered out
		if ( isset( $event_data['user_data']['fbc'] ) ) {
			$this->fail( 'fbc should not be present when click_id is empty string' );
		}
		if ( isset( $event_data['user_data']['fbp'] ) ) {
			$this->fail( 'fbp should not be present when browser_id is empty string' );
		}
	}

	/**
	 * Test that null click_id and browser_id values are handled correctly (filtered out).
	 */
	public function test_request_filters_null_values() {
		$pixel_id = 'test_pixel_id_123';
		
		$event = new Event( array(
			'event_name'  => 'Purchase',
			'custom_data' => array( 'value' => '99.99' ),
			'user_data'   => array(
				'click_id' => null,
				'browser_id' => null,
				'em'  => 'test@example.com',
			),
		) );
		
		$request = new Request( $pixel_id, array( $event ) );
		$data = $request->get_data();
		
		$event_data = $data['data'][0];
		
		// Null click_id and browser_id should not result in fbc/fbp being added
		// The ! empty() check in Request should prevent conversion
		$this->assertArrayHasKey( 'user_data', $event_data );
		$this->assertArrayHasKey( 'em', $event_data['user_data'] );
		
		// Null values are filtered by array_filter, so fbc/fbp keys should not exist
		if ( isset( $event_data['user_data']['fbc'] ) ) {
			$this->fail( 'fbc should not be present when click_id is null' );
		}
		if ( isset( $event_data['user_data']['fbp'] ) ) {
			$this->fail( 'fbp should not be present when browser_id is null' );
		}
	}

	/**
	 * Test that only click_id is converted when browser_id is empty.
	 */
	public function test_request_converts_only_click_id_when_browser_id_empty() {
		$pixel_id = 'test_pixel_id_123';
		
		$event = new Event( array(
			'event_name'  => 'Purchase',
			'custom_data' => array( 'value' => '99.99' ),
			'user_data'   => array(
				'click_id' => 'fb.1.1234567890.ValidClickId',
				'browser_id' => '',
				'em'  => 'test@example.com',
			),
		) );
		
		$request = new Request( $pixel_id, array( $event ) );
		$data = $request->get_data();
		
		$event_data = $data['data'][0];
		
		// Only fbc should be present, not fbp
		$this->assertArrayHasKey( 'fbc', $event_data['user_data'] );
		$this->assertEquals( 'fb.1.1234567890.ValidClickId', $event_data['user_data']['fbc'] );
		
		// fbp should not be present
		if ( isset( $event_data['user_data']['fbp'] ) ) {
			$this->fail( 'fbp should not be present when browser_id is empty' );
		}
		
		// Legacy parameters should be removed
		$this->assertArrayNotHasKey( 'click_id', $event_data['user_data'] );
		$this->assertArrayNotHasKey( 'browser_id', $event_data['user_data'] );
	}

	/**
	 * Test that only browser_id is converted when click_id is null.
	 */
	public function test_request_converts_only_browser_id_when_click_id_null() {
		$pixel_id = 'test_pixel_id_123';
		
		$event = new Event( array(
			'event_name'  => 'Purchase',
			'custom_data' => array( 'value' => '99.99' ),
			'user_data'   => array(
				'click_id' => null,
				'browser_id' => 'fb.1.1234567890.ValidBrowserId',
				'em'  => 'test@example.com',
			),
		) );
		
		$request = new Request( $pixel_id, array( $event ) );
		$data = $request->get_data();
		
		$event_data = $data['data'][0];
		
		// Only fbp should be present, not fbc
		$this->assertArrayHasKey( 'fbp', $event_data['user_data'] );
		$this->assertEquals( 'fb.1.1234567890.ValidBrowserId', $event_data['user_data']['fbp'] );
		
		// fbc should not be present
		if ( isset( $event_data['user_data']['fbc'] ) ) {
			$this->fail( 'fbc should not be present when click_id is null' );
		}
		
		// Legacy parameters should be removed
		$this->assertArrayNotHasKey( 'click_id', $event_data['user_data'] );
		$this->assertArrayNotHasKey( 'browser_id', $event_data['user_data'] );
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

	/**
	 * Test for Request with different click_id values
	 */
	public function test_get_data_structure_with_fbc_format() {
		$test_fbc = 'fb.1.1234567890.Test';
		$pixel_id = 'test_pixel_id_123';

		// Case 1: click_id existed in cookie
		// Arrange
		$_COOKIE['_fbc'] = $test_fbc;
		$event = new Event( array(
			'event_name'  => 'TestEvent',
		) );
		$request = new Request( $pixel_id, array( $event ) );
		// Act
		$data = $request->get_data();
		// Assert
		$this->assertArrayHasKey( 'user_data', $data['data'][0] );
		$this->assertArrayHasKey( 'fbc', $data['data'][0]['user_data'] );
		$this->assertEquals( $test_fbc, $data['data'][0]['user_data']['fbc'] );
		$this->assertArrayNotHasKey( 'click_id', $data['data'][0]['user_data'] );
		$this->assertArrayNotHasKey( 'browser_id', $data['data'][0]['user_data'] );

		// Cleanup
		unset( $_COOKIE['_fbc'] );


		// Case 2: click_id generated from session
		$_SESSION['_fbc'] = $test_fbc;
		$event = new Event( array(
			'event_name'  => 'TestEvent',
		) );
		$request = new Request( $pixel_id, array( $event ) );
		// Act
		$data = $request->get_data();
		// Assert
		$this->assertArrayHasKey( 'fbc', $data['data'][0]['user_data'] );
		$this->assertEquals( $test_fbc, $data['data'][0]['user_data']['fbc'] );
		$this->assertArrayNotHasKey( 'click_id', $data['data'][0]['user_data'] );
		$this->assertArrayNotHasKey( 'browser_id', $data['data'][0]['user_data'] );
		// Cleanup
		unset( $_SESSION['_fbc'] );

		// Case 3: click_id generated from parambuilder
		// Arrange
		$test_pb_fbc = 'fb.1.1234567890.Test.AQ'; // parambuilder fbc signature
		$event = new Event( array(
			'event_name'  => 'TestEvent',
		) );
		$mock_param_builder = new class($test_pb_fbc) {
			private $fbc;
			public function __construct($fbc) {
				$this->fbc = $fbc;
			}
			public function getFbc() {
				return $this->fbc;
			}
		};
		// If the real WC_Facebookcommerce_EventsTracker class exists, inject our mock ParamBuilder
		$original_param_builder = null;
		if ( class_exists( 'WC_Facebookcommerce_EventsTracker' ) ) {
			$ref = new \ReflectionClass( 'WC_Facebookcommerce_EventsTracker' );
			if ( $ref->hasProperty( 'param_builder' ) ) {
				$prop = $ref->getProperty( 'param_builder' );
				$prop->setAccessible( true );
				// save original value to restore later
				$original_param_builder = $prop->getValue();
				$prop->setValue( null, $mock_param_builder );
			}
		}
		$request = new Request( $pixel_id, array( $event ) );
		// Act
		$data = $request->get_data();
		// Assert
		$this->assertArrayHasKey( 'user_data', $data['data'][0] );
		$this->assertArrayHasKey( 'fbc', $data['data'][0]['user_data'] );
		$this->assertEquals( $test_pb_fbc, $data['data'][0]['user_data']['fbc'] );
		$this->assertArrayNotHasKey( 'click_id', $data['data'][0]['user_data'] );
		$this->assertArrayNotHasKey( 'browser_id', $data['data'][0]['user_data'] );

		// Cleanup - restore original param builder (may be null)
		if ( isset( $prop ) ) {
			$prop->setValue( null, $original_param_builder );
		}

		// Case 4: click_id existed in request parameter
		// Arrange
		$_REQUEST['fbclid'] = $test_fbc;

		$original_param_builder = null;
		if ( class_exists( 'WC_Facebookcommerce_EventsTracker' ) ) {
			$ref = new \ReflectionClass( 'WC_Facebookcommerce_EventsTracker' );
			if ( $ref->hasProperty( 'param_builder' ) ) {
				$prop = $ref->getProperty( 'param_builder' );
				$prop->setAccessible( true );
				$original_param_builder = $prop->getValue();
				$prop->setValue( null, null );
			}
		}
		if ( isset( $_COOKIE['_fbc'] ) ) {
			unset( $_COOKIE['_fbc'] );
		}
		if ( isset( $_SESSION['_fbc'] ) ) {
			unset( $_SESSION['_fbc'] );
		}

		$event = new Event( array(
			'event_name'  => 'TestEvent',
		) );
		$request = new Request( $pixel_id, array( $event ) );

		// Act
		$data = $request->get_data();
		// Assert
		$this->assertArrayHasKey( 'user_data', $data['data'][0] );
		$this->assertArrayHasKey( 'fbc', $data['data'][0]['user_data'] );
		// Expected format: fb.1.{timestamp}.{fbclid}
		$fbc_value = $data['data'][0]['user_data']['fbc'];
		$this->assertMatchesRegularExpression('/^fb\.1\.\d+\.' . preg_quote( $test_fbc, '/' ) . '$/', $fbc_value );
		$this->assertArrayNotHasKey( 'click_id', $data['data'][0]['user_data'] );
		$this->assertArrayNotHasKey( 'browser_id', $data['data'][0]['user_data'] );

		// Cleanup
		unset( $_REQUEST['fbclid'] );
	}

	/**
	 * Test for Request with different browser_id (fbp) values
	 */
	public function test_get_data_structure_fbp_format() {
		$test_fbp = 'fb.1.1234567890.TestFBP';
		$pixel_id = 'test_pixel_id_123';

		// Case 1: browser_id existed in cookie
		// Arrange
		$_COOKIE['_fbp'] = $test_fbp;
		$event = new Event( array(
			'event_name'  => 'TestEvent',
		) );
		$request = new Request( $pixel_id, array( $event ) );
		// Act
		$data = $request->get_data();
		// Assert
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertCount( 1, $data['data'] );
		$this->assertArrayHasKey( 'user_data', $data['data'][0] );
		$this->assertArrayHasKey( 'fbp', $data['data'][0]['user_data'] );
		$this->assertEquals( $test_fbp, $data['data'][0]['user_data']['fbp'] );
		$this->assertArrayNotHasKey( 'browser_id', $data['data'][0]['user_data'] );
		$this->assertArrayNotHasKey( 'click_id', $data['data'][0]['user_data'] );

		// Cleanup - restore original cookie state
		unset($_COOKIE['_fbp']);


		// Case 2: browser_id generated from session
		// Arrange
		$_SESSION['_fbp'] = $test_fbp;
		$event = new Event( array(
			'event_name'  => 'TestEvent',
		) );
		$request = new Request( $pixel_id, array( $event ) );
		// Act
		$data = $request->get_data();
		// Assert
		$this->assertCount( 1, $data['data'] );
		$this->assertArrayHasKey( 'user_data', $data['data'][0] );
		$this->assertArrayHasKey( 'fbp', $data['data'][0]['user_data'] );
		$this->assertEquals( $test_fbp, $data['data'][0]['user_data']['fbp'] );
		$this->assertArrayNotHasKey( 'browser_id', $data['data'][0]['user_data'] );
		$this->assertArrayNotHasKey( 'click_id', $data['data'][0]['user_data'] );
		// Cleanup
		unset( $_SESSION['_fbp'] );

		// Case 3: browser_id from param builder
		// Arrange
		$test_pb_fbp = 'fb.1.1234567890.ParamFBP';
		$mock_param_builder = new class($test_pb_fbp) {
			private $fbp;
			public function __construct($fbp) {
				$this->fbp = $fbp;
			}
			public function getFbp() {
				return $this->fbp;
			}
			public function getFbc() {
				return null;
			}
		};
		// Inject mock param builder
		$original_param_builder = null;
		if ( class_exists( 'WC_Facebookcommerce_EventsTracker' ) ) {
			$ref = new \ReflectionClass( 'WC_Facebookcommerce_EventsTracker' );
			if ( $ref->hasProperty( 'param_builder' ) ) {
				$prop = $ref->getProperty( 'param_builder' );
				$prop->setAccessible( true );
				$original_param_builder = $prop->getValue();
				$prop->setValue( null, $mock_param_builder );
			}
		}
		// Ensure no cookie/session value takes precedence
		if ( isset( $_COOKIE['_fbp'] ) ) {
			unset( $_COOKIE['_fbp'] );
		}
		if ( isset( $_SESSION['_fbp'] ) ) {
			unset( $_SESSION['_fbp'] );
		}
		$event = new Event( array(
			'event_name'  => 'TestEvent',
		) );
		$request = new Request( $pixel_id, array( $event ) );
		// Act
		$data = $request->get_data();
		// Assert
		$this->assertCount( 1, $data['data'] );
		$this->assertArrayHasKey( 'user_data', $data['data'][0] );
		$this->assertArrayHasKey( 'fbp', $data['data'][0]['user_data'] );
		$this->assertEquals( $test_pb_fbp, $data['data'][0]['user_data']['fbp'] );
		$this->assertArrayNotHasKey( 'browser_id', $data['data'][0]['user_data'] );
		$this->assertArrayNotHasKey( 'click_id', $data['data'][0]['user_data'] );
		// Cleanup - restore original param builder
		if ( isset( $prop ) ) {
			$prop->setValue( null, $original_param_builder );
		}
	}

}


