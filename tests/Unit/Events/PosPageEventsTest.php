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
use WooCommerce\Facebook\Events\AAMSettings;
use WooCommerce\Facebook\Events\POS\POS_Integration_Interface;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;

/**
 * Tests that point-of-sale pages send no storefront events.
 *
 * The till and the checkout screens it opens are used by staff, not shoppers, so
 * no pixel, PageView or website Purchase may come from them.
 *
 * @covers WC_Facebookcommerce_EventsTracker
 */
class PosPageEventsTest extends AbstractWPUnitTestWithSafeFiltering {

	/** @var \WC_Order[] orders created by a test, deleted on teardown */
	private $orders = array();

	/** @var string|null */
	private $original_user_agent;

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

		// Offline events off, so a POS order would otherwise take the web path.
		$this->add_filter_with_safe_teardown(
			'wc_facebook_is_offline_purchase_events_enabled',
			static function () {
				return false;
			}
		);
	}

	public function tearDown(): void {
		delete_transient( 'wc_facebook_connection_invalid' );

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
	 * Creates a tracker whose only point of sale reports the given page state.
	 *
	 * @param bool $on_pos_page whether the request renders a point-of-sale page.
	 * @return WC_Facebookcommerce_EventsTracker
	 */
	private function create_tracker( bool $on_pos_page ): WC_Facebookcommerce_EventsTracker {
		$integration = $this->createMock( POS_Integration_Interface::class );
		$integration->method( 'is_supported' )->willReturn( true );
		$integration->method( 'is_pos_page_request' )->willReturn( $on_pos_page );

		$this->add_filter_with_safe_teardown(
			'wc_facebook_pos_integrations',
			static function () use ( $integration ) {
				return array( $integration );
			}
		);

		return new WC_Facebookcommerce_EventsTracker(
			array(),
			new AAMSettings(
				array(
					'enableAutomaticMatching'        => false,
					'enabledAutomaticMatchingFields' => array(),
					'pixelId'                        => 'test_pixel_123',
				)
			)
		);
	}

	/**
	 * Creates and saves a paid order.
	 *
	 * @return \WC_Order
	 */
	private function create_paid_order(): \WC_Order {
		$order = wc_create_order();
		$order->set_status( 'processing' );
		$order->save();

		$this->orders[] = $order;

		return $order;
	}

	public function test_page_view_is_not_sent_on_a_pos_page() {
		$tracker = $this->create_tracker( true );

		$tracker->inject_page_view_event();

		$this->assertSame( array(), $tracker->get_pending_events() );
	}

	public function test_page_view_is_still_sent_on_a_storefront_page() {
		$tracker = $this->create_tracker( false );

		$tracker->inject_page_view_event();

		$this->assertCount( 1, $tracker->get_pending_events() );
		$this->assertSame( 'PageView', $tracker->get_pending_events()[0]->get_name() );
	}

	public function test_pixel_code_is_not_printed_on_a_pos_page() {
		$tracker = $this->create_tracker( true );

		ob_start();
		$tracker->inject_base_pixel();
		$tracker->inject_base_pixel_noscript();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
		$this->assertSame( array(), $tracker->get_pending_events(), 'The PageView must not be queued either.' );
	}

	/**
	 * Makes the connection handler report a connected store.
	 */
	private function connect(): void {
		$this->add_filter_with_safe_teardown(
			'wc_facebook_connection_access_token',
			static function () {
				return 'test-access-token';
			}
		);
	}

	public function test_param_builder_script_is_not_enqueued_on_a_pos_page() {
		$this->connect();
		$tracker = $this->create_tracker( true );

		$tracker->param_builder_client_setup();

		$this->assertFalse( wp_script_is( 'facebook-capi-param-builder', 'enqueued' ) );
	}

	public function test_param_builder_script_is_still_enqueued_on_a_storefront_page() {
		// Proves the POS-page case is stopped by the gate, not by a missing connection.
		$this->connect();
		$tracker = $this->create_tracker( false );

		$tracker->param_builder_client_setup();

		$this->assertTrue( wp_script_is( 'facebook-capi-param-builder', 'enqueued' ) );
		wp_dequeue_script( 'facebook-capi-param-builder' );
	}

	public function test_website_purchase_is_not_sent_on_a_pos_page() {
		// Stop send_api_event() before it makes a request.
		set_transient( 'wc_facebook_connection_invalid', time(), HOUR_IN_SECONDS );
		$tracker = $this->create_tracker( true );
		$order   = $this->create_paid_order();

		$tracker->inject_purchase_event( $order->get_id() );

		$this->assertFalse(
			wc_get_order( $order->get_id() )->meta_exists( WC_Facebookcommerce_EventsTracker::META_PURCHASE_TRACKED_SERVER )
		);
	}

	public function test_website_purchase_is_still_sent_on_a_storefront_page() {
		set_transient( 'wc_facebook_connection_invalid', time(), HOUR_IN_SECONDS );
		$tracker = $this->create_tracker( false );
		$order   = $this->create_paid_order();

		$tracker->inject_purchase_event( $order->get_id() );

		$this->assertTrue(
			wc_get_order( $order->get_id() )->meta_exists( WC_Facebookcommerce_EventsTracker::META_PURCHASE_TRACKED_SERVER )
		);
	}
}
