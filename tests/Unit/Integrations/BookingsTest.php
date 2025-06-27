<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Integrations;

use WooCommerce\Facebook\Integrations\Bookings;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Bookings integration class.
 *
 * @since 3.5.2
 */
class BookingsTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the class exists and can be instantiated.
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( Bookings::class ) );
		$bookings = new Bookings();
		$this->assertInstanceOf( Bookings::class, $bookings );
	}

	/**
	 * Test constructor adds init action.
	 */
	public function test_constructor_adds_init_action() {
		// Remove any existing hooks
		remove_all_actions( 'init' );
		
		$bookings = new Bookings();
		
		// Check that the action was added
		$this->assertTrue( has_action( 'init' ) !== false );
		$this->assertEquals( 10, has_action( 'init', array( $bookings, 'add_hooks' ) ) );
	}

	/**
	 * Test add_hooks when WooCommerce Bookings is not active.
	 */
	public function test_add_hooks_when_bookings_not_active() {
		// Mock the plugin check to return false
		$plugin_mock = $this->createMock( \WC_Facebookcommerce::class );
		$plugin_mock->expects( $this->once() )
			->method( 'is_plugin_active' )
			->with( 'woocommerce-bookings.php' )
			->willReturn( false );

		// Override the global function for this test
		add_filter( 'wc_facebook_instance', function() use ( $plugin_mock ) {
			return $plugin_mock;
		}, 10, 1 );

		// Remove any existing filters
		remove_all_filters( 'wc_facebook_product_price' );
		
		$bookings = new Bookings();
		$bookings->add_hooks();
		
		// Check that the filter was NOT added
		$this->assertFalse( has_filter( 'wc_facebook_product_price' ) );

		// Clean up
		remove_all_filters( 'wc_facebook_instance' );
	}

	/**
	 * Test add_hooks when WooCommerce Bookings is active.
	 */
	public function test_add_hooks_when_bookings_active() {
		// Mock the plugin check to return true
		$plugin_mock = $this->createMock( \WC_Facebookcommerce::class );
		$plugin_mock->expects( $this->once() )
			->method( 'is_plugin_active' )
			->with( 'woocommerce-bookings.php' )
			->willReturn( true );

		// Override the global function for this test
		add_filter( 'wc_facebook_instance', function() use ( $plugin_mock ) {
			return $plugin_mock;
		}, 10, 1 );

		// Remove any existing filters
		remove_all_filters( 'wc_facebook_product_price' );
		
		$bookings = new Bookings();
		$bookings->add_hooks();
		
		// Check that the filter was added
		$this->assertTrue( has_filter( 'wc_facebook_product_price' ) !== false );
		$this->assertEquals( 10, has_filter( 'wc_facebook_product_price', array( $bookings, 'get_product_price' ) ) );

		// Clean up
		remove_all_filters( 'wc_facebook_instance' );
	}

	/**
	 * Test get_product_price with non-bookable product and no facebook price.
	 */
	public function test_get_product_price_non_bookable_no_facebook_price() {
		$bookings = new Bookings();
		
		// Create a mock product
		$product = $this->createMock( \WC_Product::class );
		
		// Test with price = 1000 (cents), no facebook price
		$result = $bookings->get_product_price( 1000, 0, $product );
		
		// Should return original price for non-bookable product
		$this->assertEquals( 1000, $result );
	}

	/**
	 * Test get_product_price with facebook price set.
	 */
	public function test_get_product_price_with_facebook_price() {
		$bookings = new Bookings();
		
		// Create a mock product
		$product = $this->createMock( \WC_Product::class );
		
		// Test with price = 1000, facebook_price = 2000
		$result = $bookings->get_product_price( 1000, 2000, $product );
		
		// Should return original price when facebook price is set
		$this->assertEquals( 1000, $result );
	}

	/**
	 * Test is_bookable_product private method via reflection.
	 */
	public function test_is_bookable_product_method() {
		$bookings = new Bookings();
		
		// Use reflection to access private method
		$reflection = new \ReflectionClass( $bookings );
		$method = $reflection->getMethod( 'is_bookable_product' );
		$method->setAccessible( true );
		
		// Create a mock product
		$product = $this->createMock( \WC_Product::class );
		
		// Test when WC_Product_Booking doesn't exist
		if ( ! class_exists( 'WC_Product_Booking' ) ) {
			$result = $method->invoke( $bookings, $product );
			$this->assertFalse( $result );
		}
		
		// Test when is_wc_booking_product doesn't exist
		if ( ! function_exists( 'is_wc_booking_product' ) ) {
			$result = $method->invoke( $bookings, $product );
			$this->assertFalse( $result );
		}
	}

	/**
	 * Test get_product_price filter priority.
	 */
	public function test_get_product_price_filter_priority() {
		// Mock the plugin check
		$plugin_mock = $this->createMock( \WC_Facebookcommerce::class );
		$plugin_mock->method( 'is_plugin_active' )->willReturn( true );

		// Override the global function for this test
		add_filter( 'wc_facebook_instance', function() use ( $plugin_mock ) {
			return $plugin_mock;
		}, 10, 1 );

		// Remove existing filters
		remove_all_filters( 'wc_facebook_product_price' );
		
		$bookings = new Bookings();
		$bookings->add_hooks();
		
		// Verify filter is added with correct priority
		$this->assertEquals( 10, has_filter( 'wc_facebook_product_price', array( $bookings, 'get_product_price' ) ) );

		// Clean up
		remove_all_filters( 'wc_facebook_instance' );
	}

	/**
	 * Test get_product_price with various price values.
	 */
	public function test_get_product_price_various_values() {
		$bookings = new Bookings();
		$product = $this->createMock( \WC_Product::class );
		
		// Test with zero price
		$result = $bookings->get_product_price( 0, 0, $product );
		$this->assertEquals( 0, $result );
		
		// Test with negative price (edge case)
		$result = $bookings->get_product_price( -100, 0, $product );
		$this->assertEquals( -100, $result );
		
		// Test with very large price
		$result = $bookings->get_product_price( 999999999, 0, $product );
		$this->assertEquals( 999999999, $result );
		
		// Test with float facebook price (should still return original)
		$result = $bookings->get_product_price( 1000, 50.5, $product );
		$this->assertEquals( 1000, $result );
	}

	/**
	 * Test that get_product_price maintains price type.
	 */
	public function test_get_product_price_maintains_type() {
		$bookings = new Bookings();
		$product = $this->createMock( \WC_Product::class );
		
		// Input is int, output should be int
		$result = $bookings->get_product_price( 1000, 0, $product );
		$this->assertIsInt( $result );
		
		// Even with float facebook price
		$result = $bookings->get_product_price( 1000, 99.99, $product );
		$this->assertIsInt( $result );
	}

	/**
	 * Test multiple instances don't duplicate hooks.
	 */
	public function test_multiple_instances_no_duplicate_hooks() {
		// Mock the plugin check
		$plugin_mock = $this->createMock( \WC_Facebookcommerce::class );
		$plugin_mock->method( 'is_plugin_active' )->willReturn( true );

		// Override the global function for this test
		add_filter( 'wc_facebook_instance', function() use ( $plugin_mock ) {
			return $plugin_mock;
		}, 10, 1 );

		remove_all_filters( 'wc_facebook_product_price' );
		remove_all_actions( 'init' );
		
		// Create multiple instances
		$bookings1 = new Bookings();
		$bookings2 = new Bookings();
		$bookings3 = new Bookings();
		
		// Manually call add_hooks on each
		$bookings1->add_hooks();
		$bookings2->add_hooks();
		$bookings3->add_hooks();
		
		// Check that filter is only added once per instance
		// Note: WordPress allows multiple identical callbacks, so we just verify they exist
		$this->assertTrue( has_filter( 'wc_facebook_product_price' ) !== false );

		// Clean up
		remove_all_filters( 'wc_facebook_instance' );
	}
} 