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
	 * Test render method calls wc_enqueue_js with correct JavaScript.
	 */
	public function test_render_calls_wc_enqueue_js_with_correct_javascript() {
		global $wc_queued_js;
		
		$field = new Google_Product_Category_Field();
		$input_id = 'test_input_id';
		
		// Mock categories data
		$mock_categories = [
			'1' => 'Category 1',
			'2' => 'Category 2'
		];
		
		// Mock facebook_for_woocommerce and category handler
		$mock_category_handler = $this->createMock( \WooCommerce\Facebook\Products\FBCategories::class );
		$mock_category_handler->method( 'get_categories' )->willReturn( $mock_categories );
		
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->method( 'get_facebook_category_handler' )->willReturn( $mock_category_handler );
		
		// Mock the global function
		$this->add_filter_with_safe_teardown( 'facebook_for_woocommerce_plugin_instance', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
		
		// Call render
		$field->render( $input_id );
		
		// Verify JavaScript was enqueued - check global variable
		$this->assertNotEmpty( $wc_queued_js );
		$this->assertStringContainsString( 'window.wc_facebook_google_product_category_fields', $wc_queued_js );
		$this->assertStringContainsString( 'new WC_Facebook_Google_Product_Category_Fields', $wc_queued_js );
		$this->assertStringContainsString( $input_id, $wc_queued_js );
		$this->assertStringContainsString( wp_json_encode( $mock_categories ), $wc_queued_js );
	}

	/**
	 * Test render method escapes input ID properly.
	 */
	public function test_render_escapes_input_id() {
		global $wc_queued_js;
		
		$field = new Google_Product_Category_Field();
		$input_id = '<script>alert("xss")</script>';
		
		// Mock facebook_for_woocommerce and category handler
		$mock_category_handler = $this->createMock( \WooCommerce\Facebook\Products\FBCategories::class );
		$mock_category_handler->method( 'get_categories' )->willReturn( [] );
		
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->method( 'get_facebook_category_handler' )->willReturn( $mock_category_handler );
		
		// Mock the global function
		$this->add_filter_with_safe_teardown( 'facebook_for_woocommerce_plugin_instance', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
		
		// Call render
		$field->render( $input_id );
		
		// Verify input ID was escaped
		$this->assertNotEmpty( $wc_queued_js );
		$this->assertStringNotContainsString( '<script>', $wc_queued_js );
		$this->assertStringContainsString( esc_js( $input_id ), $wc_queued_js );
	}

	/**
	 * Test render method with empty categories.
	 */
	public function test_render_with_empty_categories() {
		global $wc_queued_js;
		
		$field = new Google_Product_Category_Field();
		$input_id = 'empty_categories_test';
		
		// Mock facebook_for_woocommerce and category handler with empty categories
		$mock_category_handler = $this->createMock( \WooCommerce\Facebook\Products\FBCategories::class );
		$mock_category_handler->method( 'get_categories' )->willReturn( [] );
		
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->method( 'get_facebook_category_handler' )->willReturn( $mock_category_handler );
		
		// Mock the global function
		$this->add_filter_with_safe_teardown( 'facebook_for_woocommerce_plugin_instance', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
		
		// Call render
		$field->render( $input_id );
		
		// Verify JavaScript was enqueued with empty array
		$this->assertNotEmpty( $wc_queued_js );
		$this->assertStringContainsString( '[]', $wc_queued_js );
	}

	/**
	 * Test render method with special characters in categories.
	 */
	public function test_render_with_special_characters_in_categories() {
		global $wc_queued_js;
		
		$field = new Google_Product_Category_Field();
		$input_id = 'special_chars_test';
		
		// Categories with special characters that need JSON encoding
		$mock_categories = [
			'cat1' => 'Category with "quotes"',
			'cat2' => "Category's <Name>",
			'cat3' => 'Category \\ with backslash'
		];
		
		// Mock facebook_for_woocommerce and category handler
		$mock_category_handler = $this->createMock( \WooCommerce\Facebook\Products\FBCategories::class );
		$mock_category_handler->method( 'get_categories' )->willReturn( $mock_categories );
		
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->method( 'get_facebook_category_handler' )->willReturn( $mock_category_handler );
		
		// Mock the global function
		$this->add_filter_with_safe_teardown( 'facebook_for_woocommerce_plugin_instance', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
		
		// Call render
		$field->render( $input_id );
		
		// Verify categories were properly JSON encoded
		$this->assertNotEmpty( $wc_queued_js );
		$expected_json = wp_json_encode( $mock_categories );
		$this->assertStringContainsString( $expected_json, $wc_queued_js );
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