<?php
/**
 * Copyright (c) Meta, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare(strict_types=1);

namespace WooCommerce\Facebook\Tests\Unit\Events\POS;

use WooCommerce\Facebook\Events\POS\POS_Integration_Interface;
use WooCommerce\Facebook\Events\POS\WCPOS_Integration;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for WCPOS_Integration.
 *
 * WCPOS is not installed in CI, so these cover the not-installed path. The
 * detection itself defers to WCPOS's own wcpos_is_pos_order() helper.
 */
class WCPOS_IntegrationTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	public function test_it_implements_the_integration_contract() {
		$this->assertInstanceOf( POS_Integration_Interface::class, new WCPOS_Integration() );
	}

	public function test_slug_is_wcpos() {
		$this->assertSame( 'wcpos', ( new WCPOS_Integration() )->get_slug() );
	}

	public function test_given_wcpos_not_installed_then_it_is_not_supported() {
		if ( function_exists( 'wcpos_is_pos_order' ) ) {
			$this->markTestSkipped( 'WCPOS is active in this environment.' );
		}

		$this->assertFalse( ( new WCPOS_Integration() )->is_supported() );
	}

	public function test_given_wcpos_not_installed_then_no_order_is_claimed() {
		if ( function_exists( 'wcpos_is_pos_order' ) ) {
			$this->markTestSkipped( 'WCPOS is active in this environment.' );
		}

		$order = new \WC_Order();

		$this->assertFalse( ( new WCPOS_Integration() )->is_pos_order( $order ) );
	}

	public function test_given_wcpos_not_installed_then_no_request_is_a_pos_page() {
		if ( function_exists( 'wcpos_request' ) ) {
			$this->markTestSkipped( 'WCPOS is active in this environment.' );
		}

		$this->assertFalse( ( new WCPOS_Integration() )->is_pos_page_request() );
	}

	public function test_cashier_id_is_read_from_pos_user_meta() {
		$order = new \WC_Order();
		$order->update_meta_data( '_pos_user', '42' );

		$this->assertSame( 42, ( new WCPOS_Integration() )->get_cashier_id( $order ) );
	}

	public function test_cashier_id_is_zero_when_not_recorded() {
		$this->assertSame( 0, ( new WCPOS_Integration() )->get_cashier_id( new \WC_Order() ) );
	}

	public function test_store_code_is_read_from_pos_store_meta() {
		$order = new \WC_Order();
		$order->update_meta_data( '_pos_store', '7' );

		$this->assertSame( array( 'store_code' => '7' ), ( new WCPOS_Integration() )->get_store_data( $order ) );
	}

	/**
	 * @dataProvider no_store_provider
	 *
	 * @param string|null $store the `_pos_store` value, or null for none.
	 */
	public function test_no_store_data_without_a_real_store( ?string $store ) {
		$order = new \WC_Order();
		if ( null !== $store ) {
			$order->update_meta_data( '_pos_store', $store );
		}

		$this->assertSame( array(), ( new WCPOS_Integration() )->get_store_data( $order ) );
	}

	/**
	 * Store values that do not identify a real location.
	 *
	 * @return array
	 */
	public static function no_store_provider(): array {
		return array(
			'not recorded'          => array( null ),
			'empty'                 => array( '' ),
			'default store placeholder' => array( '0' ),
		);
	}

	public function test_event_data_is_empty() {
		$order = new \WC_Order();

		$this->assertSame( array(), ( new WCPOS_Integration() )->get_event_data( $order ) );
	}
}
