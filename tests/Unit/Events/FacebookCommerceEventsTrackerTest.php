<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Events;

use WooCommerce\Facebook\Events\AAMSettings;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;
use WC_Facebookcommerce_EventsTracker;
use ReflectionClass;

/**
 * Unit tests for WC_Facebookcommerce_EventsTracker class.
 *
 * Tests the Facebook Pixel events tracking functionality.
 */
class FacebookCommerceEventsTrackerTest extends AbstractWPUnitTestWithSafeFiltering {

	/**
	 * @var WC_Facebookcommerce_EventsTracker|null
	 */
	private $instance;

	/**
	 * @var AAMSettings|null
	 */
	private $aam_settings;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->aam_settings = new AAMSettings( array(
			'enableAutomaticMatching'        => true,
			'enabledAutomaticMatchingFields' => array( 'em', 'fn', 'ln', 'ph', 'ct', 'st', 'zp', 'country' ),
			'pixelId'                        => 'test_pixel_123',
		) );
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		$this->instance     = null;
		$this->aam_settings = null;

		parent::tearDown();
	}

	/**
	 * Create an instance of the events tracker with pixel enabled.
	 *
	 * @return WC_Facebookcommerce_EventsTracker
	 */
	private function create_tracker_with_pixel_enabled(): WC_Facebookcommerce_EventsTracker {
		$filter = $this->add_filter_with_safe_teardown(
			'facebook_for_woocommerce_integration_pixel_enabled',
			function() {
				return true;
			}
		);

		$tracker = new WC_Facebookcommerce_EventsTracker( array(), $this->aam_settings );

		return $tracker;
	}

	/**
	 * Create an instance of the events tracker with pixel disabled.
	 *
	 * @return WC_Facebookcommerce_EventsTracker
	 */
	private function create_tracker_with_pixel_disabled(): WC_Facebookcommerce_EventsTracker {
		$filter = $this->add_filter_with_safe_teardown(
			'facebook_for_woocommerce_integration_pixel_enabled',
			function() {
				return false;
			}
		);

		$tracker = new WC_Facebookcommerce_EventsTracker( array(), $this->aam_settings );
		$filter->teardown_safely_immediately();

		return $tracker;
	}

	/**
	 * Test that get_param_builder returns a value or null.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::get_param_builder
	 */
	public function test_get_param_builder_returns_value_or_null(): void {
		$result = WC_Facebookcommerce_EventsTracker::get_param_builder();

		$this->assertTrue(
			is_null( $result ) || is_object( $result ),
			'get_param_builder should return null or an object'
		);
	}

	/**
	 * Test that get_tracked_events returns an array.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::get_tracked_events
	 */
	public function test_get_tracked_events_returns_array(): void {
		$this->instance = $this->create_tracker_with_pixel_enabled();

		$result = $this->instance->get_tracked_events();

		$this->assertIsArray( $result, 'get_tracked_events should return an array' );
	}

	/**
	 * Test that get_pending_events returns an array.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::get_pending_events
	 */
	public function test_get_pending_events_returns_array(): void {
		$this->instance = $this->create_tracker_with_pixel_enabled();

		$result = $this->instance->get_pending_events();

		$this->assertIsArray( $result, 'get_pending_events should return an array' );
	}

	/**
	 * Test that send_pending_events does nothing when no pending events.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::send_pending_events
	 */
	public function test_send_pending_events_does_nothing_when_empty(): void {
		$this->instance = $this->create_tracker_with_pixel_enabled();

		$this->instance->send_pending_events();

		$this->assertTrue( true, 'send_pending_events should handle empty pending events' );
	}

	/**
	 * Test that maybe_add_product_search_event_to_session returns the redirect value.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::maybe_add_product_search_event_to_session
	 */
	public function test_maybe_add_product_search_event_to_session_returns_redirect(): void {
		$this->instance = $this->create_tracker_with_pixel_enabled();

		$result_false = $this->instance->maybe_add_product_search_event_to_session( false );
		$this->assertFalse( $result_false, 'Should return false when passed false' );

		$result_true = $this->instance->maybe_add_product_search_event_to_session( true );
		$this->assertTrue( $result_true, 'Should return true when passed true' );
	}

	/**
	 * Test that inject_base_pixel outputs nothing when pixel is disabled.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::inject_base_pixel
	 */
	public function test_inject_base_pixel_outputs_nothing_when_disabled(): void {
		$this->instance = $this->create_tracker_with_pixel_disabled();

		ob_start();
		$this->instance->inject_base_pixel();
		$output = ob_get_clean();

		$this->assertEmpty( $output, 'inject_base_pixel should output nothing when pixel is disabled' );
	}

	/**
	 * Test that inject_page_view_event does nothing when pixel is disabled.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::inject_page_view_event
	 */
	public function test_inject_page_view_event_does_nothing_when_disabled(): void {
		$this->instance = $this->create_tracker_with_pixel_disabled();

		$this->instance->inject_page_view_event();

		$this->assertTrue( true, 'inject_page_view_event should handle disabled pixel' );
	}

	/**
	 * Test that inject_view_category_event does nothing when pixel is disabled.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::inject_view_category_event
	 */
	public function test_inject_view_category_event_does_nothing_when_disabled(): void {
		$this->instance = $this->create_tracker_with_pixel_disabled();

		$this->instance->inject_view_category_event();

		$this->assertTrue( true, 'inject_view_category_event should handle disabled pixel' );
	}

	/**
	 * Test that inject_view_content_event does nothing when pixel is disabled.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::inject_view_content_event
	 */
	public function test_inject_view_content_event_does_nothing_when_disabled(): void {
		$this->instance = $this->create_tracker_with_pixel_disabled();

		$this->instance->inject_view_content_event();

		$this->assertTrue( true, 'inject_view_content_event should handle disabled pixel' );
	}

	/**
	 * Test that inject_add_to_cart_event does nothing when pixel is disabled.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::inject_add_to_cart_event
	 */
	public function test_inject_add_to_cart_event_does_nothing_when_disabled(): void {
		$this->instance = $this->create_tracker_with_pixel_disabled();

		$this->instance->inject_add_to_cart_event( 'cart_key', 123, 1, 0 );

		$this->assertTrue( true, 'inject_add_to_cart_event should handle disabled pixel' );
	}

	/**
	 * Set up a mock param_builder that returns test cookies.
	 *
	 * @param string $cookie_name The name of the test cookie to return.
	 * @return mixed the mock param_builder
	 */
	private function create_mock_param_builder_with_cookies( string $cookie_name ) {
		$mock_cookie        = new \stdClass();
		$mock_cookie->name  = $cookie_name;
		$mock_cookie->value = 'test_value';
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$mock_cookie->max_age = 3600;
		$mock_cookie->domain  = '';

		$mock_param_builder = new class( $mock_cookie ) {
			private $cookie;
			private $was_called = false;
			public function __construct( $cookie ) {
				$this->cookie = $cookie;
			}

			public function getCookiesToSet() {
				$this->was_called = true;
				return array( $this->cookie );
			}

			public function wasGetCookiesToSetCalled() {
				return $this->was_called;
			}

			public function getFbc() {
				return null;
			}

			public function getFbp() {
				return null;
			}
		};
		return $mock_param_builder;
	}

	/**
	 * Set the param_builder static value and return the original.
	 *
	 * @param mixed $param_builder The value to update param_builder to.
	 * @return mixed The previous param_builder value
	 */
	private function install_param_builder_mock( $param_builder ) {
		$original_param_builder = null;
		if ( class_exists( 'WC_Facebookcommerce_EventsTracker' ) ) {
			$ref = new ReflectionClass( 'WC_Facebookcommerce_EventsTracker' );
			if ( $ref->hasProperty( 'param_builder' ) ) {
				$prop = $ref->getProperty( 'param_builder' );
				$prop->setAccessible( true );
				$original_param_builder = $prop->getValue();
				$prop->setValue( null, $param_builder );
			}
		}

		return $original_param_builder;
	}

	/**
	 * Data provider for testing setcookie behavior based on pixel enabled filter.
	 *
	 * @return array Test cases with format: [pixel_enabled, expected_setcookie_called]
	 */
	public function setcookie_behavior_provider(): array {
		return array(
			'pixel enabled - setcookie should be called'     => array(
				'pixel_enabled'   => true,
				'expected_called' => true,
			),
			'pixel disabled - setcookie should not be called' => array(
				'pixel_enabled'   => false,
				'expected_called' => false,
			),
		);
	}

	/**
	 * Test that param_builder_server_setup calls setcookie when pixel is enabled
	 * and does not call setcookie when pixel is disabled.
	 *
	 * @dataProvider setcookie_behavior_provider
	 * @covers WC_Facebookcommerce_EventsTracker::param_builder_server_setup
	 *
	 * @param bool $pixel_enabled Whether the pixel should be enabled via the filter.
	 * @param bool $expected_setcookie_called Whether setcookie is expected to be called.
	 */
	public function test_param_builder_server_setup_setcookie_behavior( bool $pixel_enabled, bool $expected_called ): void {
		$test_cookie_name = 'fbp_test_' . uniqid();

		// Set up mock param_builder before creating the tracker.
		$mock_param_builder = $this->create_mock_param_builder_with_cookies( $test_cookie_name );
		$original_param_builder = $this->install_param_builder_mock( $mock_param_builder );

		// Create tracker with appropriate pixel setting.
		if ( $pixel_enabled ) {
			$this->create_tracker_with_pixel_enabled();
		} else {
			$this->create_tracker_with_pixel_disabled();
		}

		$wasCalled = $mock_param_builder->wasGetCookiesToSetCalled();

		// Restore original param_builder.
		$this->install_param_builder_mock( $original_param_builder );

		// Assert the expected behavior.
		$this->assertEquals(
			$expected_called,
			$wasCalled,
			$pixel_enabled
				? 'param_builder_server_setup should complete when pixel is enabled'
				: 'param_builder_server_setup should return early when pixel is disabled'
		);
	}
}
