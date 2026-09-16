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

	public function test_event_data_is_empty() {
		$order = new \WC_Order();

		$this->assertSame( array(), ( new WCPOS_Integration() )->get_event_data( $order ) );
	}
}
