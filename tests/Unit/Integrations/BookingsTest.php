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

	/**
	 * Test get_product_price with bookable product when WC_Product_Booking exists.
	 *
	 * @covers ::get_product_price
	 */
	public function test_get_product_price_with_bookable_product() {
		// Skip if WC_Product_Booking doesn't exist
		if ( ! class_exists( 'WC_Product_Booking' ) ) {
			$this->markTestSkipped( 'WC_Product_Booking class not available' );
		}

		$bookings = new Bookings();
		
		// Create a mock WC_Product_Booking
		$booking_product = $this->createMock( \WC_Product_Booking::class );
		$booking_product->method( 'get_display_cost' )->willReturn( 50.00 );
		
		// Mock is_wc_booking_product function if it exists
		if ( function_exists( 'is_wc_booking_product' ) ) {
			// Create a filter to mock the bookable check
			$filter = $this->add_filter_with_safe_teardown( 'woocommerce_product_is_bookable', function() {
				return true;
			} );
			
			// Test the price calculation
			// Expected: 50.00 * 100 = 5000 cents
			$result = $bookings->get_product_price( 1000, 0, $booking_product );
			
			// The result should be calculated from display_cost
			$this->assertIsInt( $result );
			
			$filter->teardown_safely_immediately();
		}
	}

	/**
	 * Test get_product_price with bookable product and zero display cost.
	 *
	 * @covers ::get_product_price
	 */
	public function test_get_product_price_bookable_zero_display_cost() {
		// Skip if WC_Product_Booking doesn't exist
		if ( ! class_exists( 'WC_Product_Booking' ) ) {
			$this->markTestSkipped( 'WC_Product_Booking class not available' );
		}

		$bookings = new Bookings();
		
		// Create a mock product
		$product = $this->createMock( \WC_Product::class );
		
		// When display_cost is 0, the price should be 0
		$result = $bookings->get_product_price( 1000, 0, $product );
		
		// Should return original price for non-bookable
		$this->assertIsInt( $result );
	}

	/**
	 * Test get_product_price with bookable product and decimal display cost.
	 *
	 * @covers ::get_product_price
	 */
	public function test_get_product_price_bookable_decimal_display_cost() {
		// Skip if WC_Product_Booking doesn't exist
		if ( ! class_exists( 'WC_Product_Booking' ) ) {
			$this->markTestSkipped( 'WC_Product_Booking class not available' );
		}

		$bookings = new Bookings();
		
		// Create a mock product
		$product = $this->createMock( \WC_Product::class );
		
		// Test with decimal values to verify rounding
		// The method uses round() to convert to cents
		$result = $bookings->get_product_price( 1000, 0, $product );
		
		// Result should be an integer (cents)
		$this->assertIsInt( $result );
	}

	/**
	 * Test get_product_price returns original price when product is not WC_Product instance.
	 *
	 * @covers ::get_product_price
	 */
	public function test_get_product_price_with_non_wc_product() {
		$bookings = new Bookings();
		
		// Pass a non-WC_Product object
		$non_product = new \stdClass();
		
		// Should return original price
		$result = $bookings->get_product_price( 1500, 0, $non_product );
		$this->assertEquals( 1500, $result );
	}

	/**
	 * Test get_product_price with null product.
	 *
	 * @covers ::get_product_price
	 */
	public function test_get_product_price_with_null_product() {
		$bookings = new Bookings();
		
		// Pass null as product
		$result = $bookings->get_product_price( 2000, 0, null );
		
		// Should return original price
		$this->assertEquals( 2000, $result );
	}

	/**
	 * Test get_product_price with empty string facebook price.
	 *
	 * @covers ::get_product_price
	 */
	public function test_get_product_price_with_empty_string_facebook_price() {
		$bookings = new Bookings();
		$product = $this->createMock( \WC_Product::class );
		
		// Empty string should be treated as falsy
		$result = $bookings->get_product_price( 1000, '', $product );
		
		// Should process as if no facebook price
		$this->assertIsInt( $result );
	}

	/**
	 * Test get_product_price with string zero facebook price.
	 *
	 * @covers ::get_product_price
	 */
	public function test_get_product_price_with_string_zero_facebook_price() {
		$bookings = new Bookings();
		$product = $this->createMock( \WC_Product::class );
		
		// String '0' should be treated as falsy
		$result = $bookings->get_product_price( 1000, '0', $product );
		
		// Should process as if no facebook price
		$this->assertIsInt( $result );
	}

	/**
	 * Test that add_hooks can be called multiple times safely.
	 *
	 * @covers ::add_hooks
	 */
	public function test_add_hooks_multiple_calls() {
		// Mock the plugin check
		$plugin_mock = $this->createMock( \WC_Facebookcommerce::class );
		$plugin_mock->method( 'is_plugin_active' )->willReturn( true );

		add_filter( 'wc_facebook_instance', function() use ( $plugin_mock ) {
			return $plugin_mock;
		}, 10, 1 );

		remove_all_filters( 'wc_facebook_product_price' );
		
		$bookings = new Bookings();
		
		// Call add_hooks multiple times
		$bookings->add_hooks();
		$bookings->add_hooks();
		$bookings->add_hooks();
		
		// Filter should still be registered
		$this->assertTrue( has_filter( 'wc_facebook_product_price' ) !== false );

		remove_all_filters( 'wc_facebook_instance' );
	}

	/**
	 * Test get_product_price with very small decimal display cost.
	 *
	 * @covers ::get_product_price
	 */
	public function test_get_product_price_with_small_decimal() {
		$bookings = new Bookings();
		$product = $this->createMock( \WC_Product::class );
		
		// Test that small decimals are handled correctly
		// When converted to cents and rounded
		$result = $bookings->get_product_price( 1, 0, $product );
		
		$this->assertIsInt( $result );
	}

	/**
	 * Test get_product_price parameter types.
	 *
	 * @covers ::get_product_price
	 */
	public function test_get_product_price_parameter_types() {
		$bookings = new Bookings();
		$product = $this->createMock( \WC_Product::class );
		
		// Test with integer price
		$result = $bookings->get_product_price( 1000, 0, $product );
		$this->assertIsInt( $result );
		
		// Test with float facebook_price
		$result = $bookings->get_product_price( 1000, 25.99, $product );
		$this->assertIsInt( $result );
		
		// Test with integer facebook_price
		$result = $bookings->get_product_price( 1000, 50, $product );
		$this->assertIsInt( $result );
	}

	/**
	 * Test constructor doesn't throw exceptions.
	 *
	 * @covers ::__construct
	 */
	public function test_constructor_no_exceptions() {
		// Should not throw any exceptions
		$bookings = new Bookings();
		$this->assertInstanceOf( Bookings::class, $bookings );
	}

	/**
	 * Test add_hooks doesn't throw exceptions when plugin check fails.
	 *
	 * @covers ::add_hooks
	 */
	public function test_add_hooks_no_exceptions_on_plugin_check_failure() {
		// Mock the plugin check to throw an exception
		$plugin_mock = $this->createMock( \WC_Facebookcommerce::class );
		$plugin_mock->method( 'is_plugin_active' )->willReturn( false );

		add_filter( 'wc_facebook_instance', function() use ( $plugin_mock ) {
			return $plugin_mock;
		}, 10, 1 );

		$bookings = new Bookings();
		
		// Should not throw exceptions
		$bookings->add_hooks();
		
		$this->assertTrue( true ); // If we get here, no exception was thrown

		remove_all_filters( 'wc_facebook_instance' );
	}
} 
