<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Events;

use ReflectionClass;
use WC_Facebookcommerce_EventsTracker;
use WooCommerce\Facebook\Events\AAMSettings;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;

/**
 * Tests that out-of-range cart quantities are clamped before being reported to Meta.
 *
 * WooCommerce places no upper bound on cart quantities on the classic add-to-cart path:
 * \WC_Product::has_enough_stock() short-circuits to true for products that do not manage
 * stock, and PHP saturates overflowing numeric strings at PHP_INT_MAX on cast.
 *
 * @covers WC_Facebookcommerce_EventsTracker
 */
class EventsTrackerQuantityClampTest extends AbstractWPUnitTestWithSafeFiltering {

	/** @var int the ceiling applied when no filter overrides it */
	private const DEFAULT_MAX = 9999;

	/** @var WC_Facebookcommerce_EventsTracker|null */
	private $instance;

	/** @var \WC_Product[] products created by a test, deleted on teardown */
	private $products = array();

	/** @var string|null */
	private $original_user_agent;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->original_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

		// Avoid being classified as a crawler, which would suppress events.
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

		$this->instance = $this->create_tracker();
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}

		foreach ( $this->products as $product ) {
			$product->delete( true );
		}

		$this->products = array();
		$this->instance = null;

		if ( null === $this->original_user_agent ) {
			unset( $_SERVER['HTTP_USER_AGENT'] );
		} else {
			$_SERVER['HTTP_USER_AGENT'] = $this->original_user_agent;
		}

		parent::tearDown();
	}

	/**
	 * Creates a tracker with the pixel enabled.
	 *
	 * @return WC_Facebookcommerce_EventsTracker
	 */
	private function create_tracker(): WC_Facebookcommerce_EventsTracker {
		$this->add_filter_with_safe_teardown(
			'facebook_for_woocommerce_integration_pixel_enabled',
			function () {
				return true;
			}
		);

		$aam_settings = new AAMSettings(
			array(
				'enableAutomaticMatching'        => true,
				'enabledAutomaticMatchingFields' => array( 'em' ),
				'pixelId'                        => 'test_pixel_123',
			)
		);

		return new WC_Facebookcommerce_EventsTracker( array(), $aam_settings );
	}

	/**
	 * Calls a private method on the tracker.
	 *
	 * @param string $name the method name
	 * @param mixed  ...$args the arguments to pass
	 * @return mixed
	 */
	private function call_private( string $name, ...$args ) {
		$method = ( new ReflectionClass( $this->instance ) )->getMethod( $name );
		$method->setAccessible( true );

		return $method->invoke( $this->instance, ...$args );
	}

	/**
	 * Creates a simple product that does not manage stock, so cart quantities are unbounded.
	 *
	 * @param string $price the product price
	 * @return \WC_Product
	 */
	private function create_product( string $price = '10.00' ): \WC_Product {
		$product = \WC_Helper_Product::create_simple_product();
		$product->set_regular_price( $price );
		$product->set_price( $price );
		$product->save();

		$product = wc_get_product( $product->get_id() );

		$this->products[] = $product;

		return $product;
	}

	/**
	 * Quantities within range are returned untouched.
	 */
	public function test_in_range_quantity_is_not_clamped(): void {
		$product = $this->create_product();

		$this->assertSame( 1, $this->call_private( 'clamp_quantity', 1, $product ) );
		$this->assertSame( 500, $this->call_private( 'clamp_quantity', 500, $product ) );
		$this->assertSame( self::DEFAULT_MAX, $this->call_private( 'clamp_quantity', self::DEFAULT_MAX, $product ) );
	}

	/**
	 * Out-of-range quantities, including the PHP_INT_MAX saturation that an overflowing
	 * numeric string produces, are clamped to the ceiling.
	 */
	public function test_out_of_range_quantity_is_clamped(): void {
		$product = $this->create_product();

		$this->assertSame(
			self::DEFAULT_MAX,
			$this->call_private( 'clamp_quantity', self::DEFAULT_MAX + 1, $product )
		);
		$this->assertSame(
			self::DEFAULT_MAX,
			$this->call_private( 'clamp_quantity', PHP_INT_MAX, $product ),
			'A saturated PHP_INT_MAX quantity should be clamped'
		);
		$this->assertSame(
			self::DEFAULT_MAX,
			$this->call_private( 'clamp_quantity', (int) '99999999999999999999', $product ),
			'An overflowing numeric string casts to PHP_INT_MAX and should be clamped'
		);
	}

	/**
	 * Non-numeric quantities degrade to zero rather than to the ceiling.
	 */
	public function test_non_numeric_quantity_becomes_zero(): void {
		$product = $this->create_product();

		$this->assertSame( 0, $this->call_private( 'clamp_quantity', 'not-a-number', $product ) );
	}

	/**
	 * The ceiling is filterable.
	 */
	public function test_max_reportable_quantity_filter_is_respected(): void {
		$product = $this->create_product();

		$this->add_filter_with_safe_teardown(
			'wc_facebook_max_reportable_quantity',
			function () {
				return 50;
			}
		);

		$this->assertSame( 50, $this->call_private( 'get_max_reportable_quantity', $product ) );
		$this->assertSame( 50, $this->call_private( 'clamp_quantity', 1000, $product ) );
		$this->assertSame( 10, $this->call_private( 'clamp_quantity', 10, $product ) );
	}

	/**
	 * A filter returning a non-positive ceiling falls back to the default rather than
	 * clamping every quantity to zero.
	 */
	public function test_non_positive_filtered_max_falls_back_to_default(): void {
		$product = $this->create_product();

		$this->add_filter_with_safe_teardown(
			'wc_facebook_max_reportable_quantity',
			function () {
				return 0;
			}
		);

		$this->assertSame( self::DEFAULT_MAX, $this->call_private( 'get_max_reportable_quantity', $product ) );
	}

	/**
	 * The AddToCart payload reports the clamped quantity and a value derived from it, rather
	 * than the ~1e21 figure an unclamped PHP_INT_MAX quantity produces.
	 */
	public function test_add_to_cart_custom_data_is_clamped(): void {
		$product = $this->create_product( '49.99' );

		$data = $this->call_private( 'build_add_to_cart_custom_data', $product, PHP_INT_MAX );

		$contents = json_decode( $data['contents'], true );

		$this->assertSame( self::DEFAULT_MAX, $contents[0]['quantity'], 'Reported quantity should be clamped' );
		$this->assertEqualsWithDelta(
			49.99 * self::DEFAULT_MAX,
			$data['value'],
			0.01,
			'Reported value should be derived from the clamped quantity'
		);
	}

	/**
	 * An ordinary AddToCart payload is unchanged by the clamp.
	 */
	public function test_add_to_cart_custom_data_is_unchanged_in_range(): void {
		$product = $this->create_product( '49.99' );

		$data     = $this->call_private( 'build_add_to_cart_custom_data', $product, 3 );
		$contents = json_decode( $data['contents'], true );

		$this->assertSame( 3, $contents[0]['quantity'] );
		$this->assertEqualsWithDelta( 149.97, $data['value'], 0.01 );
		$this->assertSame( 'product', $data['content_type'] );
		$this->assertSame( get_woocommerce_currency(), $data['currency'] );
	}

	/**
	 * Cart-derived event data all reports the clamped quantity: contents, item count and the
	 * recomputed total agree with one another.
	 */
	public function test_cart_event_data_uses_clamped_quantities(): void {
		$product = $this->create_product( '10.00' );

		WC()->cart->add_to_cart( $product->get_id(), 50000 );
		WC()->cart->calculate_totals();

		$contents = json_decode( $this->call_private( 'get_cart_contents' ), true );

		$this->assertCount( 1, $contents );
		$this->assertSame( self::DEFAULT_MAX, $contents[0]['quantity'], 'Cart contents should report the clamped quantity' );

		$this->assertSame(
			self::DEFAULT_MAX,
			$this->call_private( 'get_cart_num_items' ),
			'Item count should agree with the clamped contents'
		);

		$this->assertEqualsWithDelta(
			10.0 * self::DEFAULT_MAX,
			$this->call_private( 'get_cart_total' ),
			0.01,
			'Total should be recomputed from the clamped line items'
		);
	}

	/**
	 * When nothing is clamped the total is WooCommerce's own, so tax, shipping, fees and
	 * discounts are preserved.
	 */
	public function test_cart_total_defers_to_woocommerce_when_nothing_is_clamped(): void {
		$product = $this->create_product( '10.00' );

		WC()->cart->add_to_cart( $product->get_id(), 2 );
		WC()->cart->calculate_totals();

		$this->assertEqualsWithDelta(
			(float) WC()->cart->total,
			(float) $this->call_private( 'get_cart_total' ),
			0.01,
			'An unclamped cart should report WooCommerce\'s own total'
		);

		$this->assertSame( 2, $this->call_private( 'get_cart_num_items' ) );
	}

	/**
	 * Content IDs and names still reflect every reportable line item after clamping.
	 */
	public function test_cart_content_ids_and_names_cover_clamped_items(): void {
		$product = $this->create_product();

		WC()->cart->add_to_cart( $product->get_id(), 50000 );
		WC()->cart->calculate_totals();

		$ids   = json_decode( $this->call_private( 'get_cart_content_ids' ), true );
		$names = json_decode( $this->call_private( 'get_cart_content_names' ), true );

		$this->assertIsArray( $ids );
		$this->assertNotEmpty( $ids, 'Clamping should not drop the item from content_ids' );
		$this->assertIsArray( $names );
		$this->assertNotEmpty( $names, 'Clamping should not drop the item from content_names' );
	}

	/**
	 * The reportable cart items are memoized for a given cart state, so a single event that
	 * reads the cart several times evaluates (and logs) the clamp once.
	 */
	public function test_reportable_cart_items_are_memoized_per_cart_state(): void {
		$product = $this->create_product();

		WC()->cart->add_to_cart( $product->get_id(), 50000 );
		WC()->cart->calculate_totals();

		$first  = $this->call_private( 'get_reportable_cart_items' );
		$second = $this->call_private( 'get_reportable_cart_items' );

		$this->assertSame( $first, $second, 'Repeated reads of an unchanged cart should be memoized' );
		$this->assertTrue( $first[0]['clamped'], 'The clamped flag should be set for an out-of-range item' );
	}

	/**
	 * Changing the cart invalidates the memo, so later events do not report a stale cart.
	 */
	public function test_memo_is_invalidated_when_the_cart_changes(): void {
		$first_product  = $this->create_product();
		$second_product = $this->create_product();

		WC()->cart->add_to_cart( $first_product->get_id(), 1 );
		WC()->cart->calculate_totals();

		$this->assertCount( 1, $this->call_private( 'get_reportable_cart_items' ) );

		WC()->cart->add_to_cart( $second_product->get_id(), 1 );
		WC()->cart->calculate_totals();

		$this->assertCount(
			2,
			$this->call_private( 'get_reportable_cart_items' ),
			'Adding an item should invalidate the memoized cart items'
		);
	}

	/**
	 * An unclamped cart item is not flagged as clamped.
	 */
	public function test_in_range_cart_item_is_not_flagged_as_clamped(): void {
		$product = $this->create_product();

		WC()->cart->add_to_cart( $product->get_id(), 4 );
		WC()->cart->calculate_totals();

		$items = $this->call_private( 'get_reportable_cart_items' );

		$this->assertCount( 1, $items );
		$this->assertFalse( $items[0]['clamped'] );
		$this->assertSame( 4, $items[0]['quantity'] );
	}
}
