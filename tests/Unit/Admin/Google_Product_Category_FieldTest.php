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
		$field = new Google_Product_Category_Field();
		$input_id = 'test_input_id';
		
		// Mock categories data
		$mock_categories = [
			'1' => 'Category 1',
			'2' => 'Category 2'
		];
		
		// Track what gets passed to wc_enqueue_js
		$enqueued_js = null;
		
		// Mock wc_enqueue_js function
		$this->add_filter_with_safe_teardown( 'woocommerce_queued_js', function( $js ) use ( &$enqueued_js ) {
			$enqueued_js = $js;
			return $js;
		} );
		
		// Mock facebook_for_woocommerce and category handler
		$mock_category_handler = $this->createMock( \WooCommerce\Facebook\Products\FBCategories::class );
		$mock_category_handler->method( 'get_categories' )->willReturn( $mock_categories );
		
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->method( 'get_facebook_category_handler' )->willReturn( $mock_category_handler );
		
		// Replace the global function
		$GLOBALS['facebook_for_woocommerce_instance'] = $mock_plugin;
		
		// Call render
		$field->render( $input_id );
		
		// Verify JavaScript was enqueued
		$this->assertNotNull( $enqueued_js );
		$this->assertStringContainsString( 'window.wc_facebook_google_product_category_fields', $enqueued_js );
		$this->assertStringContainsString( 'new WC_Facebook_Google_Product_Category_Fields', $enqueued_js );
		$this->assertStringContainsString( $input_id, $enqueued_js );
		$this->assertStringContainsString( wp_json_encode( $mock_categories ), $enqueued_js );
	}

	/**
	 * Test render method escapes input ID properly.
	 */
	public function test_render_escapes_input_id() {
		$field = new Google_Product_Category_Field();
		$input_id = '<script>alert("xss")</script>';
		
		// Track what gets passed to wc_enqueue_js
		$enqueued_js = null;
		
		// Mock wc_enqueue_js function
		$this->add_filter_with_safe_teardown( 'woocommerce_queued_js', function( $js ) use ( &$enqueued_js ) {
			$enqueued_js = $js;
			return $js;
		} );
		
		// Mock facebook_for_woocommerce and category handler
		$mock_category_handler = $this->createMock( \WooCommerce\Facebook\Products\FBCategories::class );
		$mock_category_handler->method( 'get_categories' )->willReturn( [] );
		
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->method( 'get_facebook_category_handler' )->willReturn( $mock_category_handler );
		
		// Replace the global function
		$GLOBALS['facebook_for_woocommerce_instance'] = $mock_plugin;
		
		// Call render
		$field->render( $input_id );
		
		// Verify input ID was escaped
		$this->assertNotNull( $enqueued_js );
		$this->assertStringNotContainsString( '<script>', $enqueued_js );
		$this->assertStringContainsString( esc_js( $input_id ), $enqueued_js );
	}

	/**
	 * Test render method with empty categories.
	 */
	public function test_render_with_empty_categories() {
		$field = new Google_Product_Category_Field();
		$input_id = 'empty_categories_test';
		
		// Track what gets passed to wc_enqueue_js
		$enqueued_js = null;
		
		// Mock wc_enqueue_js function
		$this->add_filter_with_safe_teardown( 'woocommerce_queued_js', function( $js ) use ( &$enqueued_js ) {
			$enqueued_js = $js;
			return $js;
		} );
		
		// Mock facebook_for_woocommerce and category handler with empty categories
		$mock_category_handler = $this->createMock( \WooCommerce\Facebook\Products\FBCategories::class );
		$mock_category_handler->method( 'get_categories' )->willReturn( [] );
		
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->method( 'get_facebook_category_handler' )->willReturn( $mock_category_handler );
		
		// Replace the global function
		$GLOBALS['facebook_for_woocommerce_instance'] = $mock_plugin;
		
		// Call render
		$field->render( $input_id );
		
		// Verify JavaScript was enqueued with empty array
		$this->assertNotNull( $enqueued_js );
		$this->assertStringContainsString( '[]', $enqueued_js );
	}

	/**
	 * Test render method with special characters in categories.
	 */
	public function test_render_with_special_characters_in_categories() {
		$field = new Google_Product_Category_Field();
		$input_id = 'special_chars_test';
		
		// Categories with special characters that need JSON encoding
		$mock_categories = [
			'cat1' => 'Category with "quotes"',
			'cat2' => 'Category with \'apostrophes\'',
			'cat3' => 'Category with \\ backslash'
		];
		
		// Track what gets passed to wc_enqueue_js
		$enqueued_js = null;
		
		// Mock wc_enqueue_js function
		$this->add_filter_with_safe_teardown( 'woocommerce_queued_js', function( $js ) use ( &$enqueued_js ) {
			$enqueued_js = $js;
			return $js;
		} );
		
		// Mock facebook_for_woocommerce and category handler
		$mock_category_handler = $this->createMock( \WooCommerce\Facebook\Products\FBCategories::class );
		$mock_category_handler->method( 'get_categories' )->willReturn( $mock_categories );
		
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->method( 'get_facebook_category_handler' )->willReturn( $mock_category_handler );
		
		// Replace the global function
		$GLOBALS['facebook_for_woocommerce_instance'] = $mock_plugin;
		
		// Call render
		$field->render( $input_id );
		
		// Verify categories were properly JSON encoded
		$this->assertNotNull( $enqueued_js );
		$expected_json = wp_json_encode( $mock_categories );
		$this->assertStringContainsString( $expected_json, $enqueued_js );
	}

	/**
	 * Clean up after tests.
	 */
	public function tearDown(): void {
		// Clean up global variable
		unset( $GLOBALS['facebook_for_woocommerce_instance'] );
		parent::tearDown();
	}
} 