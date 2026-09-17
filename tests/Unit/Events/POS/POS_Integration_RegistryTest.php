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
use WooCommerce\Facebook\Events\POS\POS_Integration_Registry;
use WooCommerce\Facebook\Events\POS\WCPOS_Integration;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for POS_Integration_Registry.
 */
class POS_Integration_RegistryTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Builds a stub integration that claims (or ignores) every order.
	 *
	 * @param bool   $is_supported whether the POS plugin is "active".
	 * @param bool   $claims_order whether the integration claims any order passed to it.
	 * @param string $slug         the integration slug.
	 * @return POS_Integration_Interface
	 */
	private function make_integration( bool $is_supported, bool $claims_order, string $slug = 'stub' ): POS_Integration_Interface {
		$integration = $this->createMock( POS_Integration_Interface::class );
		$integration->method( 'get_slug' )->willReturn( $slug );
		$integration->method( 'get_name' )->willReturn( strtoupper( $slug ) );
		$integration->method( 'is_supported' )->willReturn( $is_supported );
		$integration->method( 'is_pos_order' )->willReturn( $claims_order );
		$integration->method( 'get_event_data' )->willReturn( array() );

		return $integration;
	}

	/**
	 * Replaces the registered integrations for the duration of a test.
	 *
	 * @param POS_Integration_Interface[]|object[] $integrations the integrations to register.
	 */
	private function register_integrations( array $integrations ): void {
		$this->add_filter_with_safe_teardown(
			'wc_facebook_pos_integrations',
			static function () use ( $integrations ) {
				return $integrations;
			}
		);
	}

	public function test_given_default_registry_then_only_wcpos_is_registered() {
		$this->assertSame(
			array( WCPOS_Integration::class ),
			POS_Integration_Registry::INTEGRATIONS
		);
	}

	public function test_given_default_registry_when_get_integrations_called_then_wcpos_is_instantiated() {
		$integrations = ( new POS_Integration_Registry() )->get_integrations();

		$this->assertCount( 1, $integrations );
		$this->assertInstanceOf( WCPOS_Integration::class, $integrations[0] );
	}

	public function test_given_filter_adds_integration_then_it_is_returned() {
		$stub = $this->make_integration( true, false, 'extra' );
		$this->register_integrations( array( $stub ) );

		$integrations = ( new POS_Integration_Registry() )->get_integrations();

		$this->assertSame( array( $stub ), $integrations );
	}

	public function test_given_filter_adds_non_integration_then_it_is_discarded() {
		$stub = $this->make_integration( true, false );
		$this->register_integrations( array( $stub, new \stdClass(), 'not an object' ) );

		$integrations = ( new POS_Integration_Registry() )->get_integrations();

		$this->assertSame( array( $stub ), $integrations );
	}

	public function test_given_unsupported_integration_then_it_is_not_in_supported_integrations() {
		$supported   = $this->make_integration( true, false, 'supported' );
		$unsupported = $this->make_integration( false, false, 'unsupported' );
		$this->register_integrations( array( $unsupported, $supported ) );

		$registry = new POS_Integration_Registry();

		$this->assertCount( 2, $registry->get_integrations() );
		$this->assertSame( array( $supported ), $registry->get_supported_integrations() );
	}

	public function test_given_no_integration_claims_order_when_match_called_then_null_is_returned() {
		$this->register_integrations( array( $this->make_integration( true, false ) ) );

		$order = new \WC_Order();

		$this->assertNull( ( new POS_Integration_Registry() )->match( $order ) );
	}

	public function test_given_integration_claims_order_when_match_called_then_it_is_returned() {
		$stub = $this->make_integration( true, true );
		$this->register_integrations( array( $stub ) );

		$order = new \WC_Order();

		$this->assertSame( $stub, ( new POS_Integration_Registry() )->match( $order ) );
	}

	public function test_given_unsupported_integration_claims_order_when_match_called_then_null_is_returned() {
		// An integration whose plugin is inactive must never claim an order, even
		// if its is_pos_order() would say yes.
		$this->register_integrations( array( $this->make_integration( false, true ) ) );

		$order = new \WC_Order();

		$this->assertNull( ( new POS_Integration_Registry() )->match( $order ) );
	}

	public function test_given_multiple_claiming_integrations_when_match_called_then_first_wins() {
		$first  = $this->make_integration( true, true, 'first' );
		$second = $this->make_integration( true, true, 'second' );
		$this->register_integrations( array( $first, $second ) );

		$order = new \WC_Order();

		$this->assertSame( $first, ( new POS_Integration_Registry() )->match( $order ) );
	}
}
