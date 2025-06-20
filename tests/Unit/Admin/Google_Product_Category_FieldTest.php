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
		global $wc_queued_js;
		
		$field = new Google_Product_Category_Field();
		$input_id = 'test_input_id';
		
		// Call render
		$field->render( $input_id );
		
		// Verify JavaScript was enqueued
		$this->assertNotEmpty( $wc_queued_js, 'JavaScript should be enqueued' );
		$this->assertStringContainsString( 'window.wc_facebook_google_product_category_fields', $wc_queued_js );
		$this->assertStringContainsString( 'new WC_Facebook_Google_Product_Category_Fields', $wc_queued_js );
		$this->assertStringContainsString( $input_id, $wc_queued_js );
	}

	/**
	 * Test render method escapes input ID properly.
	 */
	public function test_render_escapes_input_id() {
		global $wc_queued_js;
		
		$field = new Google_Product_Category_Field();
		$input_id = '<script>alert("xss")</script>';
		
		// Call render
		$field->render( $input_id );
		
		// Verify input ID was escaped
		$this->assertNotEmpty( $wc_queued_js );
		$this->assertStringNotContainsString( '<script>', $wc_queued_js );
		$this->assertStringContainsString( esc_js( $input_id ), $wc_queued_js );
	}

	/**
	 * Test render method with different input IDs.
	 */
	public function test_render_with_different_input_ids() {
		global $wc_queued_js;
		
		$field = new Google_Product_Category_Field();
		$test_ids = [
			'simple_id',
			'id-with-dashes',
			'id_with_underscores',
			'id123',
			'CamelCaseId'
		];
		
		foreach ( $test_ids as $input_id ) {
			$wc_queued_js = ''; // Reset
			
			$field->render( $input_id );
			
			$this->assertStringContainsString( $input_id, $wc_queued_js, "Input ID '$input_id' should be in the JavaScript" );
		}
	}

	/**
	 * Test render method adds proper JavaScript structure.
	 */
	public function test_render_javascript_structure() {
		global $wc_queued_js;
		
		$field = new Google_Product_Category_Field();
		$input_id = 'structure_test';
		
		// Call render
		$field->render( $input_id );
		
		// Verify the JavaScript has the expected structure
		$this->assertNotEmpty( $wc_queued_js );
		
		// Should set window variable
		$this->assertStringContainsString( 'window.wc_facebook_google_product_category_fields =', $wc_queued_js );
		
		// Should create new instance
		$this->assertStringContainsString( 'new WC_Facebook_Google_Product_Category_Fields(', $wc_queued_js );
		
		// Should have two parameters (categories JSON and input ID)
		$this->assertMatchesRegularExpression( '/new WC_Facebook_Google_Product_Category_Fields\s*\(\s*\{.*\}\s*,\s*[\'"]' . $input_id . '[\'"]\s*\)/', $wc_queued_js );
	}

	/**
	 * Clean up after tests.
	 */
	public function tearDown(): void {
		// Restore original global value
		global $wc_queued_js;
		$wc_queued_js = $this->original_wc_queued_js;
		
		parent::tearDown();
	}
} 