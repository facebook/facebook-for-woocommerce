<?php
/**
 * Copyright (c) Meta, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Events;

use ReflectionClass;
use WC_Facebookcommerce_EventsTracker;
use WooCommerce\Facebook\Events\AAMSettings;
use WooCommerce\Facebook\Events\POS\POS_Integration_Interface;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;

/**
 * Tests the user data reported with offline (physical store) events.
 *
 * On a point-of-sale request the logged-in user is the cashier, so user data must
 * come from the order alone, and must be withheld when the person on the order is
 * a member of staff.
 *
 * @covers WC_Facebookcommerce_EventsTracker
 */
class OfflineEventUserDataTest extends AbstractWPUnitTestWithSafeFiltering {

	/** @var string[] every advanced matching field the offline path can populate */
	private const ALL_FIELDS = array( 'em', 'fn', 'ln', 'ph', 'ct', 'st', 'zp', 'country', 'external_id' );

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
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );

		if ( null === $this->original_user_agent ) {
			unset( $_SERVER['HTTP_USER_AGENT'] );
		} else {
			$_SERVER['HTTP_USER_AGENT'] = $this->original_user_agent;
		}

		parent::tearDown();
	}

	/**
	 * Creates a tracker.
	 *
	 * The advanced matching settings are only there to satisfy the constructor, and
	 * to show they are ignored by the offline path.
	 *
	 * @param bool     $matching_enabled whether automatic advanced matching is on.
	 * @param string[] $fields           the enabled matching fields.
	 * @return WC_Facebookcommerce_EventsTracker
	 */
	private function create_tracker( bool $matching_enabled = true, array $fields = self::ALL_FIELDS ): WC_Facebookcommerce_EventsTracker {
		$aam_settings = new AAMSettings(
			array(
				'enableAutomaticMatching'        => $matching_enabled,
				'enabledAutomaticMatchingFields' => $fields,
				'pixelId'                        => 'test_pixel_123',
			)
		);

		return new WC_Facebookcommerce_EventsTracker( array(), $aam_settings );
	}

	/**
	 * Builds a point-of-sale integration reporting the given cashier.
	 *
	 * @param int $cashier_id the cashier's user ID.
	 * @return POS_Integration_Interface
	 */
	private function make_integration( int $cashier_id = 0 ): POS_Integration_Interface {
		$integration = $this->createMock( POS_Integration_Interface::class );
		$integration->method( 'get_cashier_id' )->willReturn( $cashier_id );

		return $integration;
	}

	/**
	 * Builds an order with a full set of billing details.
	 *
	 * @param int $customer_id the customer account, or 0 for a guest order.
	 * @param string $email    the billing email.
	 * @return \WC_Order
	 */
	private function make_order( int $customer_id = 0, string $email = 'customer@example.com' ): \WC_Order {
		$order = new \WC_Order();
		$order->set_customer_id( $customer_id );
		$order->set_billing_first_name( 'Casey' );
		$order->set_billing_last_name( 'Customer' );
		$order->set_billing_email( $email );
		$order->set_billing_phone( '5551234567' );
		$order->set_billing_city( 'Springfield' );
		$order->set_billing_state( 'IL' );
		$order->set_billing_postcode( '62701' );
		$order->set_billing_country( 'US' );

		return $order;
	}

	/**
	 * Calls the tracker's private get_user_data().
	 *
	 * @param WC_Facebookcommerce_EventsTracker $tracker     the tracker.
	 * @param \WC_Order                         $order       the order.
	 * @param POS_Integration_Interface         $integration the claiming integration.
	 * @return array
	 */
	private function get_user_data( WC_Facebookcommerce_EventsTracker $tracker, \WC_Order $order, POS_Integration_Interface $integration ): array {
		$method = ( new ReflectionClass( $tracker ) )->getMethod( 'get_user_data_for_offline_event' );
		$method->setAccessible( true );

		return $method->invoke( $tracker, $order, $integration );
	}

	public function test_given_customer_is_the_cashier_then_no_user_data_is_sent() {
		$cashier = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$user_data = $this->get_user_data( $this->create_tracker(), $this->make_order( $cashier ), $this->make_integration( $cashier ) );

		$this->assertSame( array(), $user_data );
	}

	public function test_given_customer_is_an_administrator_then_no_user_data_is_sent() {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$user_data = $this->get_user_data( $this->create_tracker(), $this->make_order( $admin ), $this->make_integration() );

		$this->assertSame( array(), $user_data );
	}

	public function test_given_customer_is_a_shop_manager_then_no_user_data_is_sent() {
		$manager = self::factory()->user->create( array( 'role' => 'shop_manager' ) );

		$user_data = $this->get_user_data( $this->create_tracker(), $this->make_order( $manager ), $this->make_integration() );

		$this->assertSame( array(), $user_data );
	}

	public function test_given_guest_order_with_staff_billing_email_then_no_user_data_is_sent() {
		// Staff details typed into a guest order must be caught as well.
		$admin = self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_email' => 'owner@example.com',
			)
		);

		$user_data = $this->get_user_data( $this->create_tracker(), $this->make_order( 0, 'owner@example.com' ), $this->make_integration() );

		$this->assertSame( array(), $user_data );
	}

	public function test_given_real_customer_then_billing_details_are_sent() {
		$customer = self::factory()->user->create( array( 'role' => 'customer' ) );

		$user_data = $this->get_user_data( $this->create_tracker(), $this->make_order( $customer ), $this->make_integration() );

		$this->assertSame( 'customer@example.com', $user_data['em'] );
		$this->assertSame( 'Casey', $user_data['fn'] );
		$this->assertSame( 'Customer', $user_data['ln'] );
		$this->assertSame( '5551234567', $user_data['ph'] );
		$this->assertSame( 'Springfield', $user_data['ct'] );
		$this->assertSame( 'IL', $user_data['st'] );
		$this->assertSame( '62701', $user_data['zp'] );
		$this->assertSame( 'US', $user_data['country'] );
		$this->assertSame( (string) $customer, $user_data['external_id'] );
	}

	public function test_given_guest_order_then_billing_details_are_sent_without_external_id() {
		$user_data = $this->get_user_data( $this->create_tracker(), $this->make_order( 0 ), $this->make_integration() );

		$this->assertSame( 'customer@example.com', $user_data['em'] );
		$this->assertArrayNotHasKey( 'external_id', $user_data );
	}

	public function test_logged_in_cashier_details_never_fill_in_missing_billing_fields() {
		// The web path seeds user data from the current user. On a POS request that is
		// the cashier, so the offline path must not.
		$cashier = self::factory()->user->create(
			array(
				'role'       => 'subscriber',
				'user_email' => 'cashier@example.com',
				'first_name' => 'Terry',
			)
		);
		wp_set_current_user( $cashier );

		$order = new \WC_Order();
		$order->set_billing_phone( '5559876543' );

		$user_data = $this->get_user_data( $this->create_tracker(), $order, $this->make_integration( $cashier ) );

		$this->assertSame( array( 'ph' => '5559876543' ), $user_data );
	}

	public function test_advanced_matching_settings_do_not_apply() {
		// The pixel's advanced matching settings govern storefront matching; an
		// in-store sale must be reported the same way whatever they are set to.
		$tracker = $this->create_tracker( false, array() );

		$user_data = $this->get_user_data( $tracker, $this->make_order( 0 ), $this->make_integration() );

		$this->assertSame( 'customer@example.com', $user_data['em'] );
		$this->assertSame( '5551234567', $user_data['ph'] );
	}
	/**
	 * @dataProvider guest_placeholder_provider
	 *
	 * @param string $first_name a "Guest" placeholder first name.
	 */
	public function test_given_guest_order_with_guest_placeholder_then_first_name_is_omitted( string $first_name ) {
		$order = $this->make_order( 0 );
		$order->set_billing_first_name( $first_name );

		$user_data = $this->get_user_data( $this->create_tracker(), $order, $this->make_integration() );

		$this->assertArrayNotHasKey( 'fn', $user_data );
		// The rest of the billing details still go out.
		$this->assertSame( 'customer@example.com', $user_data['em'] );
		$this->assertSame( 'Customer', $user_data['ln'] );
	}

	/**
	 * Placeholder first names, in the forms they may arrive in.
	 *
	 * @return array
	 */
	public static function guest_placeholder_provider(): array {
		return array(
			'as written'       => array( 'Guest' ),
			'lowercase'        => array( 'guest' ),
			'with whitespace'  => array( '  Guest ' ),
		);
	}

	public function test_given_registered_customer_named_guest_then_first_name_is_kept() {
		// Only guest orders carry the placeholder; a real account's name is real.
		$customer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$order    = $this->make_order( $customer );
		$order->set_billing_first_name( 'Guest' );

		$user_data = $this->get_user_data( $this->create_tracker(), $order, $this->make_integration() );

		$this->assertSame( 'Guest', $user_data['fn'] );
	}

	public function test_given_guest_order_with_a_real_name_then_first_name_is_kept() {
		$user_data = $this->get_user_data( $this->create_tracker(), $this->make_order( 0 ), $this->make_integration() );

		$this->assertSame( 'Casey', $user_data['fn'] );
	}
}
