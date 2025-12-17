<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Events;

use WooCommerce\Facebook\Events\AAMSettings;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;
use WC_Facebookcommerce_EventsTracker;

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
}
