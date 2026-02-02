<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Admin;

use WooCommerce\Facebook\Admin\Google_Product_Category_Field;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Google_Product_Category_Field class.
 *
 * @since 3.5.2
 */
class Google_Product_Category_FieldTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Original global $wc_queued_js value.
	 * 
	 * @var string
	 */
	private $original_wc_queued_js;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Save original global value
		global $wc_queued_js;
		$this->original_wc_queued_js = $wc_queued_js;
		$wc_queued_js = '';

		$this->reset_inline_js_handle();
	}

	/**
	 * Test that the class can be instantiated.
	 */
	public function test_class_exists_and_can_be_instantiated() {
		$this->assertTrue( class_exists( Google_Product_Category_Field::class ) );
		
		$field = new Google_Product_Category_Field();
		$this->assertInstanceOf( Google_Product_Category_Field::class, $field );
	}

	/**
	 * Test render method enqueues JavaScript.
	 */
	public function test_render_enqueues_javascript() {
		$field = new Google_Product_Category_Field();
		$input_id = 'test_input_id';
		
		// Call render
		$field->render( $input_id );
		
		$inline_js = $this->get_inline_js();

		// Verify JavaScript was enqueued
		$this->assertNotEmpty( $inline_js, 'JavaScript should be enqueued' );
		$this->assertStringContainsString( 'window.wc_facebook_google_product_category_fields', $inline_js );
		$this->assertStringContainsString( 'new WC_Facebook_Google_Product_Category_Fields', $inline_js );
		$this->assertStringContainsString( $input_id, $inline_js );
	}

	/**
	 * Test render method escapes input ID properly.
	 */
	public function test_render_escapes_input_id() {
		$field = new Google_Product_Category_Field();
		$input_id = '<script>alert("xss")</script>';
		
		// Call render
		$field->render( $input_id );
		
		$inline_js = $this->get_inline_js();

		// Verify input ID was escaped
		$this->assertNotEmpty( $inline_js );
		$this->assertStringNotContainsString( '<script>', $inline_js );
		$this->assertStringContainsString( esc_js( $input_id ), $inline_js );
	}

	/**
	 * Test render method with different input IDs.
	 */
	public function test_render_with_different_input_ids() {
		$field = new Google_Product_Category_Field();
		$test_ids = [
			'simple_id',
			'id-with-dashes',
			'id_with_underscores',
			'id123',
			'CamelCaseId'
		];
		
		foreach ( $test_ids as $input_id ) {
			$this->reset_inline_js_handle();
			
			$field->render( $input_id );
			
			$inline_js = $this->get_inline_js();

			$this->assertStringContainsString( $input_id, $inline_js, "Input ID '$input_id' should be in the JavaScript" );
		}
	}

	/**
	 * Test render method adds proper JavaScript structure.
	 */
	public function test_render_javascript_structure() {
		$field = new Google_Product_Category_Field();
		$input_id = 'structure_test';
		
		// Call render
		$field->render( $input_id );
		
		$inline_js = $this->get_inline_js();

		// Verify the JavaScript has the expected structure
		$this->assertNotEmpty( $inline_js );
		
		// Should set window variable
		$this->assertStringContainsString( 'window.wc_facebook_google_product_category_fields =', $inline_js );
		
		// Should create new instance
		$this->assertStringContainsString( 'new WC_Facebook_Google_Product_Category_Fields(', $inline_js );
		
		// Should have two parameters (categories JSON and input ID)
		$this->assertMatchesRegularExpression( '/new WC_Facebook_Google_Product_Category_Fields\s*\(\s*\{.*\}\s*,\s*[\'"]' . $input_id . '[\'"]\s*\)/', $inline_js );
	}

	/**
	 * Clean up after tests.
	 */
	public function tearDown(): void {
		// Restore original global value
		global $wc_queued_js;
		$wc_queued_js = $this->original_wc_queued_js;

		$this->reset_inline_js_handle();
		
		parent::tearDown();
	}

	/**
	 * Retrieve any inline JavaScript added to the Facebook inline script handle.
	 *
	 * @return string
	 */
	private function get_inline_js(): string {
		$inline_js = '';

		if ( function_exists( 'wp_scripts' ) ) {
			$scripts = wp_scripts();
			$data    = $scripts->get_data( 'facebook-for-woocommerce-inline', 'after' );

			if ( is_array( $data ) && ! empty( $data ) ) {
				$inline_js = implode( "\n", $data );
			}
		}

		// Fall back to WooCommerce's legacy queue if inline data isn't available.
		if ( empty( $inline_js ) ) {
			global $wc_queued_js;
			$inline_js = (string) $wc_queued_js;
		}

		return $inline_js;
	}

	/**
	 * Clear any previously enqueued inline JavaScript to isolate test runs.
	 */
	private function reset_inline_js_handle(): void {
		if ( function_exists( 'wp_scripts' ) ) {
			$scripts = wp_scripts();

			// Ensure the handle is present so subsequent inline additions succeed even if Utils already marked it registered.
			if ( ! isset( $scripts->registered['facebook-for-woocommerce-inline'] ) ) {
				wp_register_script( 'facebook-for-woocommerce-inline', '', [], false, true );
			}

			if ( isset( $scripts->registered['facebook-for-woocommerce-inline'] ) ) {
				$scripts->registered['facebook-for-woocommerce-inline']->extra['after'] = [];
			}
		}

		global $wc_queued_js;
		$wc_queued_js = '';
	}
}
