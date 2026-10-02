<?php
/**
 * Copyright (c) Meta, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Integration\WordPressIntegration;

use WooCommerce\Facebook\Events\POS\WCPOS_Integration;
use WooCommerce\Facebook\Tests\Integration\IntegrationTestCase;

/**
 * Contract tests against the real WCPOS plugin.
 *
 * WCPOS_Integration relies on WCPOS behaviour this plugin does not control: its
 * wcpos_request() and wcpos_is_pos_order() helpers, and the query var its routes
 * set. The unit tests stub all of that, so an upstream change would pass them while
 * quietly sending storefront events from the till again, or missing POS orders.
 * These tests exercise the real plugin so such a change fails here instead.
 *
 * They run only when WCPOS is loaded — FB_TEST_PLUGIN=wcpos, as the integration
 * workflow sets after installing it with WCPOS_VERSION — and are skipped otherwise.
 *
 * URLs come from WCPOS's own helpers, not hard-coded paths, so a store with a
 * custom POS slug is exercised the same way.
 */
class WCPOSContractTest extends IntegrationTestCase {

	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'wcpos_request' ) ) {
			$this->markTestSkipped( 'WCPOS is not loaded. Install it with WCPOS_VERSION and run with FB_TEST_PLUGIN=wcpos.' );
		}

		// Visiting a WCPOS checkout screen starts a WooCommerce session, which sets a
		// cookie. Output has already started in a test run, so the cookie cannot be
		// sent and WooCommerce raises a notice that fails the test. Cookies play no
		// part in what these tests check.
		add_filter( 'woocommerce_set_cookie_enabled', '__return_false' );
	}

	/**
	 * Registers WCPOS's routes so its URLs resolve.
	 *
	 * WCPOS only builds its router on requests it classifies as point of sale
	 * (Services\Request_Lane), and a test process is not one, so it is built here.
	 */
	private function route_pos_requests(): void {
		$this->set_permalink_structure( '/%postname%/' );

		new \WCPOS\WooCommercePOS\Template_Router();

		flush_rewrite_rules( false );
	}

	public function test_wcpos_is_detected_as_supported() {
		$this->assertTrue( ( new WCPOS_Integration() )->is_supported() );
	}

	public function test_the_till_is_a_pos_page() {
		$this->route_pos_requests();

		$this->go_to( wcpos_url() );

		$this->assertTrue( ( new WCPOS_Integration() )->is_pos_page_request() );
	}

	public function test_a_checkout_screen_is_a_pos_page() {
		$this->route_pos_requests();

		$this->go_to( wcpos_checkout_url( 'order-pay/123' ) );

		$this->assertTrue( ( new WCPOS_Integration() )->is_pos_page_request() );
	}

	public function test_a_storefront_page_is_not_a_pos_page() {
		$this->route_pos_requests();

		$this->go_to( home_url( '/' ) );

		$this->assertFalse( ( new WCPOS_Integration() )->is_pos_page_request() );
	}

	public function test_an_order_stamped_by_wcpos_is_claimed() {
		// PLUGIN_NAME is the created_via value WCPOS stamps on the orders it creates.
		$order = wc_create_order();
		$order->set_created_via( \WCPOS\WooCommercePOS\PLUGIN_NAME );
		$order->save();

		$this->assertTrue( ( new WCPOS_Integration() )->is_pos_order( $order ) );

		$order->delete( true );
	}

	public function test_a_storefront_order_is_not_claimed() {
		$order = wc_create_order();
		$order->set_created_via( 'checkout' );
		$order->save();

		$this->assertFalse( ( new WCPOS_Integration() )->is_pos_order( $order ) );

		$order->delete( true );
	}
}
