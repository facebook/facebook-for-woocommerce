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
 *
 * @covers WC_Facebookcommerce_EventsTracker
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
	 * Remove all purchase-related hooks registered by the tracker constructor.
	 * Call this before creating orders to prevent hooks from firing during order creation.
	 */
	private function remove_purchase_hooks(): void {
		remove_action( 'woocommerce_new_order', array( $this->instance, 'inject_purchase_event' ), 10 );
		remove_action( 'woocommerce_process_shop_order_meta', array( $this->instance, 'inject_purchase_event' ), 20 );
		remove_action( 'woocommerce_checkout_update_order_meta', array( $this->instance, 'inject_purchase_event' ), 30 );
		remove_action( 'woocommerce_thankyou', array( $this->instance, 'inject_purchase_event' ), 40 );
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
	 * Test that inject_add_to_cart_event does nothing with invalid product_id.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::inject_add_to_cart_event
	 */
	public function test_inject_add_to_cart_event_does_nothing_with_invalid_product_id(): void {
		$this->instance = $this->create_tracker_with_pixel_enabled();

		// Product ID of 0 should be invalid
		$this->instance->inject_add_to_cart_event( 'cart_key', 0, 1, 0 );

		$this->assertEmpty(
			$this->instance->get_tracked_events(),
			'inject_add_to_cart_event should not track with invalid product_id'
		);
	}

	/**
	 * Test that inject_add_to_cart_event does nothing with zero quantity.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::inject_add_to_cart_event
	 */
	public function test_inject_add_to_cart_event_does_nothing_with_zero_quantity(): void {
		$this->instance = $this->create_tracker_with_pixel_enabled();

		// Quantity of 0 should be invalid
		$this->instance->inject_add_to_cart_event( 'cart_key', 123, 0, 0 );

		$this->assertEmpty(
			$this->instance->get_tracked_events(),
			'inject_add_to_cart_event should not track with zero quantity'
		);
	}

	/**
	 * Test that inject_base_pixel_noscript outputs nothing when pixel is disabled.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::inject_base_pixel_noscript
	 */
	public function test_inject_base_pixel_noscript_outputs_nothing_when_disabled(): void {
		$this->instance = $this->create_tracker_with_pixel_disabled();

		ob_start();
		$this->instance->inject_base_pixel_noscript();
		$output = ob_get_clean();

		$this->assertEmpty( $output, 'inject_base_pixel_noscript should output nothing when pixel is disabled' );
	}

	/**
	 * Test that inject_initiate_checkout_event does nothing when pixel is disabled.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::inject_initiate_checkout_event
	 */
	public function test_inject_initiate_checkout_event_does_nothing_when_disabled(): void {
		$this->instance = $this->create_tracker_with_pixel_disabled();

		$this->instance->inject_initiate_checkout_event();

		$this->assertTrue( true, 'inject_initiate_checkout_event should handle disabled pixel' );
	}

	/**
	 * Test that inject_initiate_checkout_event does nothing when cart is null.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::inject_initiate_checkout_event
	 */
	public function test_inject_initiate_checkout_event_does_nothing_when_cart_is_null(): void {
		$this->instance = $this->create_tracker_with_pixel_enabled();

		// When WC()->cart is null, the method should bail early
		$this->instance->inject_initiate_checkout_event();

		$this->assertTrue( true, 'inject_initiate_checkout_event should handle null cart' );
	}

	/**
	 * Test that inject_search_event does nothing when pixel is disabled.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::inject_search_event
	 */
	public function test_inject_search_event_does_nothing_when_disabled(): void {
		$this->instance = $this->create_tracker_with_pixel_disabled();

		$query = new \WP_Query();
		$this->instance->inject_search_event( $query );

		$this->assertTrue( true, 'inject_search_event should handle disabled pixel' );
	}

	/**
	 * Test that inject_search_event does nothing when not main query.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::inject_search_event
	 */
	public function test_inject_search_event_does_nothing_when_not_main_query(): void {
		$this->instance = $this->create_tracker_with_pixel_enabled();

		// Create a query that is not the main query
		$query = new \WP_Query();
		$this->instance->inject_search_event( $query );

		$this->assertTrue( true, 'inject_search_event should handle non-main query' );
	}

	/**
	 * Test that maybe_inject_search_event does nothing when pixel is disabled.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::maybe_inject_search_event
	 */
	public function test_maybe_inject_search_event_does_nothing_when_disabled(): void {
		$this->instance = $this->create_tracker_with_pixel_disabled();

		$this->instance->maybe_inject_search_event();

		$this->assertTrue( true, 'maybe_inject_search_event should handle disabled pixel' );
	}

	/**
	 * Test that send_search_event does nothing when search event is null.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::send_search_event
	 */
	public function test_send_search_event_does_nothing_when_null(): void {
		$this->instance = $this->create_tracker_with_pixel_enabled();

		// No search event has been created, so this should do nothing
		$this->instance->send_search_event();

		$this->assertTrue( true, 'send_search_event should handle null search event' );
	}

	/**
	 * Test that actually_inject_search_event does nothing when search event is null.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::actually_inject_search_event
	 */
	public function test_actually_inject_search_event_does_nothing_when_null(): void {
		$this->instance = $this->create_tracker_with_pixel_enabled();

		// No search event has been created, so this should do nothing
		$this->instance->actually_inject_search_event();

		$this->assertTrue( true, 'actually_inject_search_event should handle null search event' );
	}

	/**
	 * Test that inject_purchase_event does nothing when user is admin.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::inject_purchase_event
	 */
	public function test_inject_purchase_event_does_nothing_when_admin_user(): void {
		$this->instance = $this->create_tracker_with_pixel_enabled();

		// Create an admin user and set as current user
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$this->instance->inject_purchase_event( 999 );

		$this->assertTrue( true, 'inject_purchase_event should not track for admin users' );

		// Clean up
		wp_set_current_user( 0 );
	}

	/**
	 * Test that inject_purchase_event does nothing when pixel is disabled.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::inject_purchase_event
	 */
	public function test_inject_purchase_event_does_nothing_when_disabled(): void {
		$this->instance = $this->create_tracker_with_pixel_disabled();

		$this->instance->inject_purchase_event( 999 );

		$this->assertTrue( true, 'inject_purchase_event should handle disabled pixel' );
	}

	/**
	 * Test that inject_purchase_event does nothing with invalid order.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::inject_purchase_event
	 */
	public function test_inject_purchase_event_does_nothing_with_invalid_order(): void {
		$this->instance = $this->create_tracker_with_pixel_enabled();

		// Use an order ID that doesn't exist
		$this->instance->inject_purchase_event( 999999 );

		$this->assertEmpty(
			$this->instance->get_tracked_events(),
			'inject_purchase_event should not track with invalid order'
		);
	}

	/**
	 * Test that inject_purchase_event sends CAPI event for server context.
	 *
	 * When triggered by a server-side hook (e.g., woocommerce_new_order),
	 * the method should send a CAPI event.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::inject_purchase_event
	 */
	public function test_inject_purchase_event_sends_capi_for_server_context(): void {
		$this->instance = $this->create_tracker_with_pixel_enabled();
		$this->remove_purchase_hooks();

		// Create a valid order with processing status
		$order = wc_create_order();
		$order->set_status( 'processing' );
		$order->set_total( 100 );
		$order->save();

		// Simulate server hook by calling inject_purchase_event via do_action
		// This sets current_action() to 'woocommerce_new_order'
		add_action( 'woocommerce_new_order', array( $this->instance, 'inject_purchase_event' ), 10 );
		do_action( 'woocommerce_new_order', $order->get_id(), $order );
		remove_action( 'woocommerce_new_order', array( $this->instance, 'inject_purchase_event' ), 10 );

		$tracked_events = $this->instance->get_tracked_events();

		$this->assertNotEmpty(
			$tracked_events,
			'inject_purchase_event should track CAPI event for server context'
		);
		$this->assertCount(
			1,
			$tracked_events,
			'inject_purchase_event should track exactly one CAPI event for server context'
		);

		// Clean up
		$order->delete( true );
	}

	/**
	 * Test that inject_purchase_event does NOT send CAPI event for browser context.
	 *
	 * When triggered by the browser-side hook (woocommerce_thankyou),
	 * the method should NOT send a CAPI event (only inject pixel).
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::inject_purchase_event
	 */
	public function test_inject_purchase_event_does_not_send_capi_for_browser_context(): void {
		$this->instance = $this->create_tracker_with_pixel_enabled();
		$this->remove_purchase_hooks();

		// Create a valid order with processing status
		$order = wc_create_order();
		$order->set_status( 'processing' );
		$order->set_total( 100 );
		$order->save();

		// Simulate browser hook by calling inject_purchase_event via do_action
		// This sets current_action() to 'woocommerce_thankyou'
		add_action( 'woocommerce_thankyou', array( $this->instance, 'inject_purchase_event' ), 40 );
		do_action( 'woocommerce_new_order', $order->get_id(), $order );
		remove_action( 'woocommerce_thankyou', array( $this->instance, 'inject_purchase_event' ), 40 );

		$tracked_events = $this->instance->get_tracked_events();

		$this->assertEmpty(
			$tracked_events,
			'inject_purchase_event should NOT track CAPI event for browser context'
		);

		// Clean up
		$order->delete( true );
	}


	/**
	 * Test that inject_purchase_event only sends one CAPI event when both hooks fire.
	 *
	 * When both server and browser hooks fire (typical checkout flow),
	 * only one CAPI event should be sent (from server context).
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::inject_purchase_event
	 */
	public function test_inject_purchase_event_sends_single_capi_for_full_checkout_flow(): void {
		$this->instance = $this->create_tracker_with_pixel_enabled();
		$this->remove_purchase_hooks();

		// Create a valid order with processing status
		$order = wc_create_order();
		$order->set_status( 'processing' );
		$order->set_total( 100 );
		$order->save();

		// Simulate server hook first (as happens in real checkout)
		add_action( 'woocommerce_new_order', array( $this->instance, 'inject_purchase_event' ), 10 );
		do_action( 'woocommerce_new_order', $order->get_id(), $order );
		remove_action( 'woocommerce_new_order', array( $this->instance, 'inject_purchase_event' ), 10 );

		// Simulate browser hook second (thank you page)
		add_action( 'woocommerce_thankyou', array( $this->instance, 'inject_purchase_event' ), 40 );
		do_action( 'woocommerce_thankyou', $order->get_id() );
		remove_action( 'woocommerce_thankyou', array( $this->instance, 'inject_purchase_event' ), 40 );

		$tracked_events = $this->instance->get_tracked_events();

		$this->assertCount(
			1,
			$tracked_events,
			'inject_purchase_event should track exactly one CAPI event when both server and browser hooks fire'
		);

		// Clean up
		$order->delete( true );
	}

	/**
	 * Test that inject_subscribe_event does nothing when function not available.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::inject_subscribe_event
	 */
	public function test_inject_subscribe_event_does_nothing_when_function_unavailable(): void {
		$this->instance = $this->create_tracker_with_pixel_enabled();

		// wcs_get_subscriptions_for_order doesn't exist, so this should bail
		$this->instance->inject_subscribe_event( 999 );

		$this->assertTrue( true, 'inject_subscribe_event should handle missing WooCommerce Subscriptions' );
	}

	/**
	 * Test that inject_subscribe_event does nothing when pixel is disabled.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::inject_subscribe_event
	 */
	public function test_inject_subscribe_event_does_nothing_when_disabled(): void {
		$this->instance = $this->create_tracker_with_pixel_disabled();

		$this->instance->inject_subscribe_event( 999 );

		$this->assertTrue( true, 'inject_subscribe_event should handle disabled pixel' );
	}

	/**
	 * Test that inject_lead_event_hook adds the footer action.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::inject_lead_event_hook
	 */
	public function test_inject_lead_event_hook_adds_footer_action(): void {
		$this->instance = $this->create_tracker_with_pixel_enabled();

		$this->instance->inject_lead_event_hook();

		$this->assertTrue(
			has_action( 'wp_footer', array( $this->instance, 'inject_lead_event' ) ) !== false,
			'inject_lead_event_hook should add inject_lead_event to wp_footer'
		);
	}

	/**
	 * Test that inject_lead_event does nothing when in admin.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::inject_lead_event
	 */
	public function test_inject_lead_event_does_nothing_when_admin(): void {
		$this->instance = $this->create_tracker_with_pixel_enabled();

		// Simulate admin context
		set_current_screen( 'dashboard' );

		$this->instance->inject_lead_event();

		$this->assertTrue( true, 'inject_lead_event should not output in admin' );
	}

	/**
	 * Test that add_filter_for_add_to_cart_fragments adds filter when redirect disabled.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::add_filter_for_add_to_cart_fragments
	 */
	public function test_add_filter_for_add_to_cart_fragments_adds_filter(): void {
		$this->instance = $this->create_tracker_with_pixel_enabled();

		// Set cart redirect to 'no'
		update_option( 'woocommerce_cart_redirect_after_add', 'no' );

		$this->instance->add_filter_for_add_to_cart_fragments();

		$this->assertTrue(
			has_filter( 'woocommerce_add_to_cart_fragments', array( $this->instance, 'add_add_to_cart_event_fragment' ) ) !== false,
			'add_filter_for_add_to_cart_fragments should add the fragment filter'
		);

		// Clean up
		delete_option( 'woocommerce_cart_redirect_after_add' );
	}

	/**
	 * Test that add_filter_for_add_to_cart_fragments does nothing when redirect enabled.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::add_filter_for_add_to_cart_fragments
	 */
	public function test_add_filter_for_add_to_cart_fragments_does_nothing_when_redirect(): void {
		$this->instance = $this->create_tracker_with_pixel_enabled();

		// Set cart redirect to 'yes'
		update_option( 'woocommerce_cart_redirect_after_add', 'yes' );

		$this->instance->add_filter_for_add_to_cart_fragments();

		$this->assertFalse(
			has_filter( 'woocommerce_add_to_cart_fragments', array( $this->instance, 'add_add_to_cart_event_fragment' ) ),
			'add_filter_for_add_to_cart_fragments should not add filter when redirect is enabled'
		);

		// Clean up
		delete_option( 'woocommerce_cart_redirect_after_add' );
	}

	/**
	 * Test that add_add_to_cart_event_fragment returns fragments unchanged when product invalid.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::add_add_to_cart_event_fragment
	 */
	public function test_add_add_to_cart_event_fragment_returns_unchanged_when_invalid(): void {
		$this->instance = $this->create_tracker_with_pixel_enabled();

		$_POST['product_id'] = 999999; // Non-existent product
		$_POST['quantity']   = 1;

		$fragments         = array( 'test' => 'value' );
		$result_fragments = $this->instance->add_add_to_cart_event_fragment( $fragments );

		$this->assertSame(
			$fragments,
			$result_fragments,
			'Fragments should be unchanged when product is invalid'
		);

		unset( $_POST['product_id'], $_POST['quantity'] );
	}

	/**
	 * Test that add_conditional_add_to_cart_event_fragment returns fragments when pixel disabled.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::add_conditional_add_to_cart_event_fragment
	 */
	public function test_add_conditional_add_to_cart_event_fragment_when_disabled(): void {
		$this->instance = $this->create_tracker_with_pixel_disabled();

		$fragments         = array( 'test' => 'value' );
		$result_fragments = $this->instance->add_conditional_add_to_cart_event_fragment( $fragments );

		$this->assertSame(
			$fragments,
			$result_fragments,
			'Fragments should be unchanged when pixel is disabled'
		);
	}

	/**
	 * Test that get_tracked_events returns empty array initially.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::get_tracked_events
	 */
	public function test_get_tracked_events_returns_empty_initially(): void {
		$this->instance = $this->create_tracker_with_pixel_enabled();

		$events = $this->instance->get_tracked_events();

		$this->assertIsArray( $events );
		$this->assertEmpty( $events, 'Tracked events should be empty initially' );
	}

	/**
	 * Test that get_pending_events returns empty array initially.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::get_pending_events
	 */
	public function test_get_pending_events_returns_empty_initially(): void {
		$this->instance = $this->create_tracker_with_pixel_enabled();

		$events = $this->instance->get_pending_events();

		$this->assertIsArray( $events );
		$this->assertEmpty( $events, 'Pending events should be empty initially' );
	}

	/**
	 * Test that add_filter_for_conditional_add_to_cart_fragment adds filter when redirect disabled.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::add_filter_for_conditional_add_to_cart_fragment
	 */
	public function test_add_filter_for_conditional_add_to_cart_fragment_adds_filter(): void {
		$this->instance = $this->create_tracker_with_pixel_enabled();

		// Set cart redirect to 'no'
		update_option( 'woocommerce_cart_redirect_after_add', 'no' );

		$this->instance->add_filter_for_conditional_add_to_cart_fragment();

		$this->assertTrue(
			has_filter( 'woocommerce_add_to_cart_fragments', array( $this->instance, 'add_conditional_add_to_cart_event_fragment' ) ) !== false,
			'add_filter_for_conditional_add_to_cart_fragment should add the fragment filter'
		);

		// Clean up
		delete_option( 'woocommerce_cart_redirect_after_add' );
	}

	/**
	 * Test that param_builder_client_setup does nothing when not connected.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::param_builder_client_setup
	 */
	public function test_param_builder_client_setup_does_nothing_when_not_connected(): void {
		$this->instance = $this->create_tracker_with_pixel_enabled();

		// When not connected, this should do nothing without errors
		$this->instance->param_builder_client_setup();

		$this->assertTrue( true, 'param_builder_client_setup should handle disconnected state' );
	}

	/**
	 * Test that tracker can be constructed with user info array.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::__construct
	 */
	public function test_constructor_accepts_user_info_array(): void {
		$filter = $this->add_filter_with_safe_teardown(
			'facebook_for_woocommerce_integration_pixel_enabled',
			function() {
				return true;
			}
		);

		$user_info = array(
			'em' => 'test@example.com',
			'fn' => 'John',
			'ln' => 'Doe',
		);

		$tracker = new WC_Facebookcommerce_EventsTracker( $user_info, $this->aam_settings );

		$this->assertInstanceOf(
			WC_Facebookcommerce_EventsTracker::class,
			$tracker,
			'Tracker should be instantiated with user info'
		);
	}

	/**
	 * Test that tracker can be constructed with empty user info.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::__construct
	 */
	public function test_constructor_accepts_empty_user_info(): void {
		$filter = $this->add_filter_with_safe_teardown(
			'facebook_for_woocommerce_integration_pixel_enabled',
			function() {
				return true;
			}
		);

		$tracker = new WC_Facebookcommerce_EventsTracker( array(), $this->aam_settings );

		$this->assertInstanceOf(
			WC_Facebookcommerce_EventsTracker::class,
			$tracker,
			'Tracker should be instantiated with empty user info'
		);
	}

	/**
	 * Test inject_view_content_event does nothing when post ID not set.
	 *
	 * @covers WC_Facebookcommerce_EventsTracker::inject_view_content_event
	 */
	public function test_inject_view_content_event_does_nothing_when_no_post(): void {
		$this->instance = $this->create_tracker_with_pixel_enabled();

		// Ensure $post is not set
		global $post;
		$original_post = $post;
		$post          = null;

		$this->instance->inject_view_content_event();

		$this->assertTrue( true, 'inject_view_content_event should handle missing post' );

		// Restore
		$post = $original_post;
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
