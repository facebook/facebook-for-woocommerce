<?php
/**
 * Copyright (c) Meta, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Events;

use WC_Facebookcommerce_EventsTracker;
use WC_Facebookcommerce_Integration;
use WooCommerce\Facebook\Events\AAMSettings;
use WooCommerce\Facebook\Events\POS\POS_Integration_Interface;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;

/**
 * Tests when offline (physical store) Purchase events are reported.
 *
 * Point-of-sale orders are usually created before payment is taken, so the event
 * must wait until the order is paid, and must then be picked up from the status
 * change rather than from order creation.
 *
 * The `_meta_offline_purchase_tracked` flag is written just before the event is
 * sent, so its presence shows the event went out. Sending itself is short-circuited
 * by the connection-invalid transient, so no request leaves the test.
 *
 * @covers WC_Facebookcommerce_EventsTracker
 */
class OfflinePurchaseTriggerTest extends AbstractWPUnitTestWithSafeFiltering {

	/** @var WC_Facebookcommerce_EventsTracker */
	private $tracker;

	/** @var \WC_Order[] orders created by a test, deleted on teardown */
	private $orders = array();

	/** @var string|null */
	private $original_user_agent;

	/** @var bool whether the stubbed send reports the event as delivered */
	private $send_succeeds = true;

	/** @var \WooCommerce\Facebook\Events\Event[] events handed to the stubbed send */
	private $sent_events = array();

	public function setUp(): void {
		parent::setUp();

		$this->original_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

		// Avoid being classified as a crawler, which would suppress events.
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

		$this->add_filter_with_safe_teardown(
			'facebook_for_woocommerce_integration_pixel_enabled',
			static function () {
				return true;
			}
		);

		// A point-of-sale integration that claims every order.
		$integration = $this->createMock( POS_Integration_Interface::class );
		$integration->method( 'get_slug' )->willReturn( 'stub' );
		$integration->method( 'is_supported' )->willReturn( true );
		$integration->method( 'is_pos_order' )->willReturn( true );
		$integration->method( 'get_event_data' )->willReturn( array() );

		$this->add_filter_with_safe_teardown(
			'wc_facebook_pos_integrations',
			static function () use ( $integration ) {
				return array( $integration );
			}
		);

		$this->set_opted_in( true );

		// Opted in an hour ago, so orders paid during the test count as after the
		// opt-in. Left unset, the getter would record "now" mid-test and an order paid
		// a second earlier would be skipped.
		$this->set_opted_in_at( time() - HOUR_IN_SECONDS );

		// The real tracker, with only the network call replaced: the test decides
		// whether delivery succeeds, and can see what was sent.
		$this->tracker = $this->getMockBuilder( WC_Facebookcommerce_EventsTracker::class )
			->setConstructorArgs(
				array(
					array(),
					new AAMSettings(
						array(
							'enableAutomaticMatching'        => false,
							'enabledAutomaticMatchingFields' => array(),
							'pixelId'                        => 'test_pixel_123',
						)
					),
				)
			)
			->onlyMethods( array( 'send_api_event' ) )
			->getMock();

		$this->tracker->method( 'send_api_event' )->willReturnCallback(
			function ( $event ) {
				$this->sent_events[] = $event;
				return $this->send_succeeds;
			}
		);
	}

	public function tearDown(): void {
		delete_option( WC_Facebookcommerce_Integration::OPTION_OFFLINE_PURCHASE_EVENTS_OPTED_IN_AT );

		foreach ( $this->orders as $order ) {
			$order->delete( true );
		}
		$this->orders = array();

		if ( null === $this->original_user_agent ) {
			unset( $_SERVER['HTTP_USER_AGENT'] );
		} else {
			$_SERVER['HTTP_USER_AGENT'] = $this->original_user_agent;
		}

		parent::tearDown();
	}

	/**
	 * Sets whether the merchant has opted in to offline events.
	 *
	 * @param bool $opted_in whether offline events are enabled.
	 */
	private function set_opted_in( bool $opted_in ): void {
		$this->teardown_callback_category_safely( 'wc_facebook_is_offline_purchase_events_enabled' );
		$this->add_filter_with_safe_teardown(
			'wc_facebook_is_offline_purchase_events_enabled',
			static function () use ( $opted_in ) {
				return $opted_in;
			}
		);
	}

	/**
	 * Sets when the merchant opted in.
	 *
	 * @param int $timestamp Unix timestamp.
	 */
	private function set_opted_in_at( int $timestamp ): void {
		update_option( WC_Facebookcommerce_Integration::OPTION_OFFLINE_PURCHASE_EVENTS_OPTED_IN_AT, $timestamp, false );
	}

	/**
	 * Creates and saves an order with the given status.
	 *
	 * @param string $status the order status.
	 * @param string $email  the billing email, or '' for none.
	 * @return \WC_Order
	 */
	private function create_order( string $status, string $email = 'buyer@example.com' ): \WC_Order {
		$order = wc_create_order();
		// A customer to match, without which the event is not sent. Pass '' for none.
		$order->set_billing_email( $email );
		$order->set_status( $status );
		$order->save();

		$this->orders[] = $order;

		return $order;
	}

	/**
	 * Determines whether the offline event was reported for an order.
	 *
	 * @param \WC_Order $order the order.
	 * @return bool
	 */
	private function was_reported( \WC_Order $order ): bool {
		return wc_get_order( $order->get_id() )->meta_exists( WC_Facebookcommerce_EventsTracker::META_OFFLINE_PURCHASE_TRACKED );
	}

	public function test_status_change_listener_is_registered() {
		$this->assertNotFalse(
			has_action( 'woocommerce_order_status_changed', array( $this->tracker, 'inject_offline_purchase_event_on_status_change' ) )
		);
	}

	/**
	 * @dataProvider unpaid_status_provider
	 *
	 * @param string $status an unpaid order status.
	 */
	public function test_given_unpaid_order_then_event_is_not_reported( string $status ) {
		$order = $this->create_order( $status );

		$this->tracker->inject_offline_purchase_event_on_status_change( $order->get_id() );

		$this->assertFalse( $this->was_reported( $order ) );
	}

	/**
	 * Unpaid statuses that must wait for payment.
	 *
	 * @return array
	 */
	public static function unpaid_status_provider(): array {
		return array(
			'pending' => array( 'pending' ),
			'on-hold' => array( 'on-hold' ),
		);
	}

	/**
	 * @dataProvider paid_status_provider
	 *
	 * @param string $status a paid order status.
	 */
	public function test_given_paid_order_then_event_is_reported( string $status ) {
		$order = $this->create_order( $status );

		$this->tracker->inject_offline_purchase_event_on_status_change( $order->get_id() );

		$this->assertTrue( $this->was_reported( $order ) );
	}

	/**
	 * Paid statuses.
	 *
	 * @return array
	 */
	public static function paid_status_provider(): array {
		return array(
			'processing' => array( 'processing' ),
			'completed'  => array( 'completed' ),
		);
	}

	public function test_order_created_unpaid_is_reported_once_it_becomes_paid() {
		// The WCPOS flow: the order is created before payment, then paid.
		$order = $this->create_order( 'pending' );

		$this->tracker->inject_purchase_event( $order->get_id() );
		$this->assertFalse( $this->was_reported( $order ), 'Must not report before payment.' );

		// A real status change, so the registered listener is what reports it.
		$order->update_status( 'processing' );

		$this->assertTrue( $this->was_reported( $order ), 'Must report once the order becomes paid.' );
	}

	public function test_given_merchant_has_not_opted_in_then_status_change_reports_nothing() {
		$this->set_opted_in( false );
		$order = $this->create_order( 'processing' );

		$this->tracker->inject_offline_purchase_event_on_status_change( $order->get_id() );

		$this->assertFalse( $this->was_reported( $order ) );
	}

	public function test_order_paid_before_opt_in_is_not_reported() {
		// Opting in must not backfill history, even when an older order's status
		// changes afterwards.
		$this->set_opted_in_at( time() + HOUR_IN_SECONDS );
		$order = $this->create_order( 'processing' );

		$order->update_status( 'completed' );

		$this->assertFalse( $this->was_reported( $order ) );
	}

	public function test_event_time_is_when_the_order_was_paid() {
		$paid_at = time() - ( 2 * HOUR_IN_SECONDS );
		$order   = wc_create_order();
		$order->set_date_paid( $paid_at );
		$order->save();
		$this->orders[] = $order;

		$integration = $this->createMock( POS_Integration_Interface::class );
		$integration->method( 'get_event_data' )->willReturn( array() );

		$method = new \ReflectionMethod( WC_Facebookcommerce_EventsTracker::class, 'get_offline_event' );
		$method->setAccessible( true );
		$event = $method->invoke( $this->tracker, $order, $integration );

		$this->assertSame( $paid_at, $event->get_data()['event_time'] );
	}

	/**
	 * Determines whether a website Purchase was reported for an order.
	 *
	 * @param \WC_Order $order the order.
	 * @return bool
	 */
	private function was_reported_as_web_purchase( \WC_Order $order ): bool {
		return wc_get_order( $order->get_id() )->meta_exists( WC_Facebookcommerce_EventsTracker::META_PURCHASE_TRACKED_SERVER );
	}

	public function test_given_opted_out_then_pos_order_is_not_reported_as_a_website_purchase() {
		// A till sale is not a website purchase, opt-in or not.
		$this->set_opted_in( false );
		$order = $this->create_order( 'processing' );

		$this->tracker->inject_purchase_event( $order->get_id() );

		$this->assertFalse( $this->was_reported_as_web_purchase( $order ), 'Must not take the web path.' );
		$this->assertFalse( $this->was_reported( $order ), 'Must not report an offline event without the opt-in.' );
	}

	public function test_given_opted_in_then_pos_order_is_reported_offline_only() {
		$order = $this->create_order( 'processing' );

		$this->tracker->inject_purchase_event( $order->get_id() );

		$this->assertTrue( $this->was_reported( $order ) );
		$this->assertFalse( $this->was_reported_as_web_purchase( $order ) );
	}

	public function test_given_no_customer_to_match_then_event_is_not_sent_or_marked() {
		// Meta rejects such an event, so it must not be sent, and the order must stay
		// unmarked so it can be reported later.
		$order = $this->create_order( 'processing', '' );

		$this->tracker->inject_purchase_event( $order->get_id() );

		$this->assertFalse( $this->was_reported( $order ) );
	}

	public function test_order_is_reported_once_a_customer_is_attached() {
		$order = $this->create_order( 'processing', '' );
		$this->tracker->inject_purchase_event( $order->get_id() );
		$this->assertFalse( $this->was_reported( $order ), 'Nothing to match yet.' );

		// The cashier attaches the customer, and the order later moves on.
		$order = wc_get_order( $order->get_id() );
		$order->set_billing_email( 'buyer@example.com' );
		$order->save();
		$order->update_status( 'completed' );

		$this->assertTrue( $this->was_reported( $order ) );
	}

	/**
	 * @dataProvider customer_identifier_provider
	 *
	 * @param string $identifier which single identifier the order carries.
	 */
	public function test_any_one_customer_identifier_is_enough( string $identifier ) {
		$order = $this->create_order( 'pending', '' );

		if ( 'phone' === $identifier ) {
			$order->set_billing_phone( '5551234567' );
		} elseif ( 'account' === $identifier ) {
			$order->set_customer_id( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		} else {
			$order->set_billing_email( 'buyer@example.com' );
		}
		$order->save();

		$order->update_status( 'processing' );

		$this->assertTrue( $this->was_reported( $order ) );
	}

	/**
	 * Single identifiers that each make an event matchable.
	 *
	 * @return array
	 */
	public static function customer_identifier_provider(): array {
		return array(
			'email'            => array( 'email' ),
			'phone'            => array( 'phone' ),
			'customer account' => array( 'account' ),
		);
	}

	public function test_location_alone_is_not_enough() {
		// The rejected event in production carried only a country.
		$order = $this->create_order( 'pending', '' );
		$order->set_billing_country( 'US' );
		$order->set_billing_city( 'Springfield' );
		$order->set_billing_postcode( '62701' );
		$order->save();

		$order->update_status( 'processing' );

		$this->assertFalse( $this->was_reported( $order ) );
	}

	/**
	 * Builds the offline event for an order through the tracker's private builder.
	 *
	 * @param \WC_Order $order      the order.
	 * @param array     $store_data the store data the integration reports.
	 * @return array the event data.
	 */
	private function build_offline_event( \WC_Order $order, array $store_data = array() ): array {
		$integration = $this->createMock( POS_Integration_Interface::class );
		$integration->method( 'get_event_data' )->willReturn( array() );
		$integration->method( 'get_store_data' )->willReturn( $store_data );

		$method = new \ReflectionMethod( WC_Facebookcommerce_EventsTracker::class, 'get_offline_event' );
		$method->setAccessible( true );

		return $method->invoke( $this->tracker, $order, $integration )->get_data();
	}

	/**
	 * Creates an order with one line item.
	 *
	 * @param int $quantity the quantity bought.
	 * @return array{0: \WC_Order, 1: \WC_Product}
	 */
	private function create_order_with_product( int $quantity ): array {
		$product = \WC_Helper_Product::create_simple_product();
		$order   = wc_create_order();
		$order->add_product( $product, $quantity );
		$order->calculate_totals();
		$order->save();
		$this->orders[] = $order;

		return array( $order, $product );
	}

	public function test_order_id_is_at_the_top_level_of_the_event() {
		list( $order ) = $this->create_order_with_product( 1 );

		$event = $this->build_offline_event( $order );

		$this->assertSame( (string) $order->get_id(), $event['order_id'] );
		$this->assertArrayNotHasKey( 'order_id', $event['custom_data'] );
	}

	public function test_contents_and_value_are_sent_as_arrays_and_numbers() {
		list( $order, $product ) = $this->create_order_with_product( 2 );

		$custom_data = $this->build_offline_event( $order )['custom_data'];

		$this->assertSame(
			array(
				array(
					'id'       => \WC_Facebookcommerce_Utils::get_fb_retailer_id( $product ),
					'quantity' => 2,
				),
			),
			$custom_data['contents']
		);
		$this->assertIsArray( $custom_data['content_ids'] );
		$this->assertContains( \WC_Facebookcommerce_Utils::get_fb_retailer_id( $product ), $custom_data['content_ids'] );
		$this->assertIsFloat( $custom_data['value'] );
		$this->assertSame( (float) $order->get_total(), $custom_data['value'] );
	}

	public function test_store_data_is_sent_at_the_top_level_when_the_pos_reports_a_store() {
		list( $order ) = $this->create_order_with_product( 1 );

		$event = $this->build_offline_event( $order, array( 'store_code' => 'STORE-7' ) );

		$this->assertSame( array( 'store_code' => 'STORE-7' ), $event['store_data'] );
	}

	public function test_store_data_is_omitted_when_the_pos_reports_none() {
		list( $order ) = $this->create_order_with_product( 1 );

		$this->assertArrayNotHasKey( 'store_data', $this->build_offline_event( $order ) );
	}

	public function test_store_data_keeps_all_three_fields_in_meta_types() {
		list( $order ) = $this->create_order_with_product( 1 );

		$event = $this->build_offline_event(
			$order,
			array(
				'store_page_id' => '8576093908',
				'brand_page_id' => 10236898932,
				'store_code'    => ' STORE-NYC-001 ',
			)
		);

		$this->assertSame(
			array(
				'store_page_id' => 8576093908,
				'brand_page_id' => 10236898932,
				'store_code'    => 'STORE-NYC-001',
			),
			$event['store_data']
		);
	}

	public function test_store_data_drops_unknown_and_invalid_fields() {
		list( $order ) = $this->create_order_with_product( 1 );

		$event = $this->build_offline_event(
			$order,
			array(
				'store_page_id' => 'not-an-id',
				'brand_page_id' => -5,
				'store_code'    => 'KEEP-ME',
				'store_name'    => 'Downtown',
			)
		);

		$this->assertSame( array( 'store_code' => 'KEEP-ME' ), $event['store_data'] );
	}

	public function test_store_code_longer_than_64_characters_is_dropped_not_truncated() {
		list( $order ) = $this->create_order_with_product( 1 );

		$event = $this->build_offline_event( $order, array( 'store_code' => str_repeat( 'A', 65 ) ) );

		$this->assertArrayNotHasKey( 'store_data', $event );
	}

	public function test_store_code_of_exactly_64_characters_is_kept() {
		list( $order ) = $this->create_order_with_product( 1 );
		$code          = str_repeat( 'A', 64 );

		$event = $this->build_offline_event( $order, array( 'store_code' => $code ) );

		$this->assertSame( array( 'store_code' => $code ), $event['store_data'] );
	}

	/**
	 * Gets the in-flight flag that stops two requests sending the same order.
	 *
	 * @param \WC_Order $order the order.
	 * @return string
	 */
	private function in_flight_flag( \WC_Order $order ): string {
		return '_wc_' . facebook_for_woocommerce()->get_id() . '_purchase_tracked_' . $order->get_id() . '_offline';
	}

	public function test_given_send_fails_then_order_is_not_marked_and_claim_is_released() {
		// A failed request or a Meta rejection must not count as reported.
		$this->send_succeeds = false;

		$order = $this->create_order( 'processing' );

		$this->assertCount( 1, $this->sent_events, 'The event was attempted.' );
		$this->assertFalse( $this->was_reported( $order ), 'A failed send must not mark the order.' );
		$this->assertFalse( get_transient( $this->in_flight_flag( $order ) ), 'The claim must be released so a retry can run.' );
	}

	public function test_failed_event_is_retried_on_the_next_status_change() {
		$this->send_succeeds = false;
		$order               = $this->create_order( 'processing' );
		$this->assertFalse( $this->was_reported( $order ) );

		// Meta is reachable again, and the order moves on.
		$this->send_succeeds = true;
		wc_get_order( $order->get_id() )->update_status( 'completed' );

		$this->assertCount( 2, $this->sent_events, 'Sent once, failed, then sent again.' );
		$this->assertTrue( $this->was_reported( $order ) );
	}

	public function test_delivered_event_is_sent_only_once() {
		$order = $this->create_order( 'processing' );
		$this->assertTrue( $this->was_reported( $order ) );

		wc_get_order( $order->get_id() )->update_status( 'completed' );

		$this->assertCount( 1, $this->sent_events, 'A delivered event must not be sent again.' );
	}
}
