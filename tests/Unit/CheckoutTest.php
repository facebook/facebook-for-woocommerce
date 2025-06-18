<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit;

use WooCommerce\Facebook\Checkout;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Checkout class.
 *
 * @since 3.5.2
 */
class CheckoutTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Instance of Checkout class.
	 *
	 * @var Checkout
	 */
	private $checkout;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Remove any existing hooks before creating new instance
		remove_all_actions( 'init' );
		remove_all_filters( 'query_vars' );
		remove_all_filters( 'template_include' );
		
		$this->checkout = new Checkout();
	}

	/**
	 * Test that the class exists and can be instantiated.
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( 'WooCommerce\Facebook\Checkout' ) );
		$this->assertInstanceOf( Checkout::class, $this->checkout );
	}

	/**
	 * Test constructor adds hooks.
	 */
	public function test_constructor_adds_hooks() {
		// Check that hooks are added
		$this->assertNotFalse( has_action( 'init', array( $this->checkout, 'add_checkout_permalink_rewrite_rule' ) ) );
		$this->assertNotFalse( has_filter( 'query_vars', array( $this->checkout, 'add_checkout_permalink_query_var' ) ) );
		$this->assertNotFalse( has_filter( 'template_include', array( $this->checkout, 'load_checkout_permalink_template' ) ) );
	}

	/**
	 * Test add_checkout_permalink_rewrite_rule.
	 */
	public function test_add_checkout_permalink_rewrite_rule() {
		global $wp_rewrite;
		
		// Clear existing rules
		$wp_rewrite->rules = array();
		
		// Add the rule
		$this->checkout->add_checkout_permalink_rewrite_rule();
		
		// Get the rules
		$rules = $wp_rewrite->wp_rewrite_rules();
		
		// Check that our rule exists
		$this->assertArrayHasKey( '^fb-checkout/?$', $rules );
		$this->assertEquals( 'index.php?fb_checkout=1', $rules['^fb-checkout/?$'] );
	}

	/**
	 * Test add_checkout_permalink_query_var.
	 */
	public function test_add_checkout_permalink_query_var() {
		$vars = array( 'existing_var' );
		$result = $this->checkout->add_checkout_permalink_query_var( $vars );
		
		$this->assertContains( 'fb_checkout', $result );
		$this->assertContains( 'products', $result );
		$this->assertContains( 'coupon', $result );
		$this->assertContains( 'existing_var', $result );
		$this->assertCount( 4, $result );
	}

	/**
	 * Test add_checkout_permalink_query_var with empty array.
	 */
	public function test_add_checkout_permalink_query_var_empty_array() {
		$vars = array();
		$result = $this->checkout->add_checkout_permalink_query_var( $vars );
		
		$this->assertContains( 'fb_checkout', $result );
		$this->assertContains( 'products', $result );
		$this->assertContains( 'coupon', $result );
		$this->assertCount( 3, $result );
	}

	/**
	 * Test load_checkout_permalink_template returns original template when not fb_checkout.
	 */
	public function test_load_checkout_permalink_template_returns_original() {
		// Mock get_query_var to return false
		$this->set_query_var( 'fb_checkout', false );
		
		$original_template = '/path/to/template.php';
		$result = $this->checkout->load_checkout_permalink_template( $original_template );
		
		$this->assertEquals( $original_template, $result );
	}

	/**
	 * Test load_checkout_permalink_template processes fb_checkout request.
	 */
	public function test_load_checkout_permalink_template_processes_checkout() {
		// Mock WC cart
		$mock_cart = $this->getMockBuilder( \WC_Cart::class )
			->disableOriginalConstructor()
			->getMock();
		
		// Expect empty_cart to be called
		$mock_cart->expects( $this->once() )
			->method( 'empty_cart' );
		
		// Mock WC() function
		$mock_wc = $this->getMockBuilder( \WooCommerce::class )
			->disableOriginalConstructor()
			->getMock();
		$mock_wc->cart = $mock_cart;
		
		// Override WC() global
		$this->set_wc_instance( $mock_wc );
		
		// Set query var
		$this->set_query_var( 'fb_checkout', '1' );
		
		// Capture output and exit
		ob_start();
		try {
			$this->checkout->load_checkout_permalink_template( '/template.php' );
		} catch ( \Exception $e ) {
			// Expected due to exit
		}
		$output = ob_get_clean();
		
		// Check that HTML output contains iframe
		$this->assertStringContainsString( '<iframe', $output );
		$this->assertStringContainsString( 'Checkout', $output );
	}

	/**
	 * Test load_checkout_permalink_template with products parameter.
	 */
	public function test_load_checkout_permalink_template_with_products() {
		// Create a test product
		$product = WC_Helper_Product::create_simple_product();
		$product_id = $product->get_id();
		
		// Mock cart
		$mock_cart = $this->getMockBuilder( \WC_Cart::class )
			->disableOriginalConstructor()
			->getMock();
		
		$mock_cart->expects( $this->once() )
			->method( 'empty_cart' );
		
		$mock_cart->expects( $this->once() )
			->method( 'add_to_cart' )
			->with( $product_id, 2 )
			->willReturn( true );
		
		// Mock WC
		$mock_wc = $this->getMockBuilder( \WooCommerce::class )
			->disableOriginalConstructor()
			->getMock();
		$mock_wc->cart = $mock_cart;
		
		$this->set_wc_instance( $mock_wc );
		
		// Set query vars
		$this->set_query_var( 'fb_checkout', '1' );
		$this->set_query_var( 'products', $product_id . ':2' );
		
		// Execute
		ob_start();
		try {
			$this->checkout->load_checkout_permalink_template( '/template.php' );
		} catch ( \Exception $e ) {
			// Expected
		}
		ob_end_clean();
		
		// Clean up
		$product->delete( true );
	}

	/**
	 * Test load_checkout_permalink_template with retailer ID format.
	 */
	public function test_load_checkout_permalink_template_with_retailer_id() {
		// Create a test product
		$product = WC_Helper_Product::create_simple_product();
		$product_id = $product->get_id();
		$sku = 'TEST_SKU';
		$product->set_sku( $sku );
		$product->save();
		
		// Mock cart
		$mock_cart = $this->getMockBuilder( \WC_Cart::class )
			->disableOriginalConstructor()
			->getMock();
		
		$mock_cart->expects( $this->once() )
			->method( 'empty_cart' );
		
		$mock_cart->expects( $this->once() )
			->method( 'add_to_cart' )
			->with( $product_id, 1 )
			->willReturn( true );
		
		// Mock WC
		$mock_wc = $this->getMockBuilder( \WooCommerce::class )
			->disableOriginalConstructor()
			->getMock();
		$mock_wc->cart = $mock_cart;
		
		$this->set_wc_instance( $mock_wc );
		
		// Set query vars with retailer ID format
		$this->set_query_var( 'fb_checkout', '1' );
		$this->set_query_var( 'products', $sku . '_' . $product_id . ':1' );
		
		// Execute
		ob_start();
		try {
			$this->checkout->load_checkout_permalink_template( '/template.php' );
		} catch ( \Exception $e ) {
			// Expected
		}
		ob_end_clean();
		
		// Clean up
		$product->delete( true );
	}

	/**
	 * Test load_checkout_permalink_template with coupon.
	 */
	public function test_load_checkout_permalink_template_with_coupon() {
		// Create a test coupon
		$coupon_code = 'test_coupon_' . time();
		$coupon = new \WC_Coupon();
		$coupon->set_code( $coupon_code );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( 10 );
		$coupon->save();
		
		// Mock cart
		$mock_cart = $this->getMockBuilder( \WC_Cart::class )
			->disableOriginalConstructor()
			->getMock();
		
		$mock_cart->expects( $this->once() )
			->method( 'empty_cart' );
		
		$mock_cart->expects( $this->once() )
			->method( 'apply_coupon' )
			->with( $coupon_code );
		
		$mock_cart->expects( $this->once() )
			->method( 'get_applied_coupons' )
			->willReturn( array( $coupon_code ) );
		
		// Mock WC
		$mock_wc = $this->getMockBuilder( \WooCommerce::class )
			->disableOriginalConstructor()
			->getMock();
		$mock_wc->cart = $mock_cart;
		
		$this->set_wc_instance( $mock_wc );
		
		// Set query vars
		$this->set_query_var( 'fb_checkout', '1' );
		$this->set_query_var( 'coupon', $coupon_code );
		
		// Execute
		ob_start();
		try {
			$this->checkout->load_checkout_permalink_template( '/template.php' );
		} catch ( \Exception $e ) {
			// Expected
		}
		ob_end_clean();
		
		// Clean up
		$coupon->delete( true );
	}

	/**
	 * Test flush_rewrite_rules_on_activation.
	 */
	public function test_flush_rewrite_rules_on_activation() {
		global $wp_rewrite;
		
		// Track if flush_rewrite_rules was called
		$flush_called = false;
		add_action( 'generate_rewrite_rules', function() use ( &$flush_called ) {
			$flush_called = true;
		} );
		
		$this->checkout->flush_rewrite_rules_on_activation();
		
		$this->assertTrue( $flush_called );
	}

	/**
	 * Test flush_rewrite_rules_on_deactivation.
	 */
	public function test_flush_rewrite_rules_on_deactivation() {
		global $wp_rewrite;
		
		// Track if flush_rewrite_rules was called
		$flush_called = false;
		add_action( 'generate_rewrite_rules', function() use ( &$flush_called ) {
			$flush_called = true;
		} );
		
		$this->checkout->flush_rewrite_rules_on_deactivation();
		
		$this->assertTrue( $flush_called );
	}

	/**
	 * Helper method to set query var.
	 *
	 * @param string $var
	 * @param mixed $value
	 */
	private function set_query_var( $var, $value ) {
		global $wp_query;
		if ( ! $wp_query ) {
			$wp_query = new \WP_Query();
		}
		$wp_query->set( $var, $value );
	}

	/**
	 * Helper method to set WC instance.
	 *
	 * @param \WooCommerce $wc
	 */
	private function set_wc_instance( $wc ) {
		$GLOBALS['woocommerce'] = $wc;
	}

	/**
	 * Clean up after tests.
	 */
	public function tearDown(): void {
		remove_all_actions( 'init' );
		remove_all_filters( 'query_vars' );
		remove_all_filters( 'template_include' );
		
		parent::tearDown();
	}
} 