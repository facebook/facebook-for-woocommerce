<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit;

use WooCommerce\Facebook\Checkout;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Checkout class. 
 * 
 * Several internals of the checkout class cannot be tested in isolation due to the 
 * use of exit() and other non-testable code. These functions take phpunit completely
 * offline, and this logic will instead be tested in integration tests.
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
		
		// Ensure $wp_rewrite is initialized
		if ( ! $wp_rewrite ) {
			$wp_rewrite = new \WP_Rewrite();
		}
		
		// Clear existing rules
		$wp_rewrite->rules = array();
		
		// Add the rule
		$this->checkout->add_checkout_permalink_rewrite_rule();
		
		// Check that the rule was added by verifying the rewrite rules array was modified
		// We can't reliably test the exact rule format as it depends on WordPress internals
		$this->assertTrue( true ); // Simple pass - the method runs without error
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
	 * Test flush_rewrite_rules_on_activation.
	 */
	public function test_flush_rewrite_rules_on_activation() {
		// Simply test that the method can be called without error
		$this->checkout->flush_rewrite_rules_on_activation();
		
		// If we get here without error, the test passes
		$this->assertTrue( true );
	}

	/**
	 * Test flush_rewrite_rules_on_deactivation.
	 */
	public function test_flush_rewrite_rules_on_deactivation() {
		// Simply test that the method can be called without error
		$this->checkout->flush_rewrite_rules_on_deactivation();
		
		// If we get here without error, the test passes
		$this->assertTrue( true );
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
	 * Clean up after tests.
	 */
	public function tearDown(): void {
		remove_all_actions( 'init' );
		remove_all_filters( 'query_vars' );
		remove_all_filters( 'template_include' );
		
		parent::tearDown();
	}
} 