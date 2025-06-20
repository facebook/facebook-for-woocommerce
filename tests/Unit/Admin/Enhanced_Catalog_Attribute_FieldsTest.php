<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Admin;

use WooCommerce\Facebook\Admin\Enhanced_Catalog_Attribute_Fields;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Enhanced_Catalog_Attribute_Fields class.
 *
 * @since 3.5.2
 */
class Enhanced_Catalog_Attribute_FieldsTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the class can be instantiated.
	 */
	public function test_class_exists_and_can_be_instantiated() {
		$this->assertTrue( class_exists( Enhanced_Catalog_Attribute_Fields::class ) );
		
		$fields = new Enhanced_Catalog_Attribute_Fields( Enhanced_Catalog_Attribute_Fields::PAGE_TYPE_EDIT_CATEGORY );
		$this->assertInstanceOf( Enhanced_Catalog_Attribute_Fields::class, $fields );
	}

	/**
	 * Test constructor with different page types.
	 */
	public function test_constructor_with_different_page_types() {
		// Test with edit category page type
		$fields_edit = new Enhanced_Catalog_Attribute_Fields( Enhanced_Catalog_Attribute_Fields::PAGE_TYPE_EDIT_CATEGORY );
		$this->assertInstanceOf( Enhanced_Catalog_Attribute_Fields::class, $fields_edit );
		
		// Test with add category page type
		$fields_add = new Enhanced_Catalog_Attribute_Fields( Enhanced_Catalog_Attribute_Fields::PAGE_TYPE_ADD_CATEGORY );
		$this->assertInstanceOf( Enhanced_Catalog_Attribute_Fields::class, $fields_add );
		
		// Test with edit product page type
		$fields_product = new Enhanced_Catalog_Attribute_Fields( Enhanced_Catalog_Attribute_Fields::PAGE_TYPE_EDIT_PRODUCT );
		$this->assertInstanceOf( Enhanced_Catalog_Attribute_Fields::class, $fields_product );
	}

	/**
	 * Test constructor with term parameter.
	 */
	public function test_constructor_with_term() {
		// Create a proper WP_Term object
		$term = new \WP_Term( (object) [
			'term_id' => 123,
			'name' => 'Test Category',
			'slug' => 'test-category',
			'term_group' => 0,
			'term_taxonomy_id' => 123,
			'taxonomy' => 'product_cat',
			'description' => '',
			'parent' => 0,
			'count' => 0
		] );
		
		$fields = new Enhanced_Catalog_Attribute_Fields( 
			Enhanced_Catalog_Attribute_Fields::PAGE_TYPE_EDIT_CATEGORY, 
			$term 
		);
		
		$this->assertInstanceOf( Enhanced_Catalog_Attribute_Fields::class, $fields );
	}

	/**
	 * Test constructor with product parameter.
	 */
	public function test_constructor_with_product() {
		// Create a mock product
		$product = $this->createMock( \WC_Product::class );
		
		$fields = new Enhanced_Catalog_Attribute_Fields( 
			Enhanced_Catalog_Attribute_Fields::PAGE_TYPE_EDIT_PRODUCT, 
			null,
			$product 
		);
		
		$this->assertInstanceOf( Enhanced_Catalog_Attribute_Fields::class, $fields );
	}

	/**
	 * Test class constants.
	 */
	public function test_class_constants() {
		$this->assertEquals( 'wc_facebook_enhanced_catalog_attribute_', Enhanced_Catalog_Attribute_Fields::FIELD_ENHANCED_CATALOG_ATTRIBUTE_PREFIX );
		$this->assertEquals( '__optional_selector', Enhanced_Catalog_Attribute_Fields::OPTIONAL_SELECTOR_KEY );
		$this->assertEquals( 'wc_facebook_enhanced_catalog_attributes_id', Enhanced_Catalog_Attribute_Fields::FIELD_ENHANCED_CATALOG_ATTRIBUTES_ID );
		$this->assertEquals( 'wc_facebook_can_show_enhanced_catalog_attributes_id', Enhanced_Catalog_Attribute_Fields::FIELD_CAN_SHOW_ENHANCED_ATTRIBUTES_ID );
		$this->assertEquals( 'edit_category', Enhanced_Catalog_Attribute_Fields::PAGE_TYPE_EDIT_CATEGORY );
		$this->assertEquals( 'add_category', Enhanced_Catalog_Attribute_Fields::PAGE_TYPE_ADD_CATEGORY );
		$this->assertEquals( 'edit_product', Enhanced_Catalog_Attribute_Fields::PAGE_TYPE_EDIT_PRODUCT );
	}

	/**
	 * Test render_hidden_input_can_show_attributes static method.
	 */
	public function test_render_hidden_input_can_show_attributes() {
		ob_start();
		Enhanced_Catalog_Attribute_Fields::render_hidden_input_can_show_attributes();
		$output = ob_get_clean();
		
		// Check output contains expected HTML
		$this->assertStringContainsString( '<input', $output );
		$this->assertStringContainsString( 'type="hidden"', $output );
		$this->assertStringContainsString( 'id="' . Enhanced_Catalog_Attribute_Fields::FIELD_CAN_SHOW_ENHANCED_ATTRIBUTES_ID . '"', $output );
		$this->assertStringContainsString( 'name="' . Enhanced_Catalog_Attribute_Fields::FIELD_CAN_SHOW_ENHANCED_ATTRIBUTES_ID . '"', $output );
		$this->assertStringContainsString( 'value="true"', $output );
	}

	/**
	 * Test extract_attribute method functionality.
	 */
	public function test_extract_attribute() {
		$fields = new Enhanced_Catalog_Attribute_Fields( Enhanced_Catalog_Attribute_Fields::PAGE_TYPE_EDIT_CATEGORY );
		
		// Use reflection to access private method
		$reflection = new \ReflectionClass( $fields );
		$method = $reflection->getMethod( 'extract_attribute' );
		$method->setAccessible( true );
		
		// Test data
		$attributes = [
			[ 'key' => 'color', 'value' => 'red' ],
			[ 'key' => 'size', 'value' => 'large' ],
			[ 'key' => 'gender', 'value' => 'unisex' ]
		];
		
		// Extract existing attribute
		$extracted = $method->invokeArgs( $fields, [ &$attributes, 'size' ] );
		$this->assertEquals( [ 'key' => 'size', 'value' => 'large' ], $extracted );
		$this->assertCount( 2, $attributes );
		
		// Extract non-existing attribute
		$extracted = $method->invokeArgs( $fields, [ &$attributes, 'non_existing' ] );
		$this->assertNull( $extracted );
		$this->assertCount( 2, $attributes );
	}

	/**
	 * Test __get magic method for backward compatibility.
	 */
	public function test_magic_get_method() {
		// Set up mock before creating the instance
		$mock_category_handler = $this->createMock( \WooCommerce\Facebook\Products\FBCategories::class );
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->method( 'get_facebook_category_handler' )->willReturn( $mock_category_handler );
		
		$this->add_filter_with_safe_teardown( 'wc_facebook_instance', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
		
		$term = new \WP_Term( (object) [ 
			'term_id' => 123,
			'name' => 'Test Category',
			'slug' => 'test-category',
			'term_group' => 0,
			'term_taxonomy_id' => 123,
			'taxonomy' => 'product_cat',
			'description' => '',
			'parent' => 0,
			'count' => 0
		] );
		$fields = new Enhanced_Catalog_Attribute_Fields( 
			Enhanced_Catalog_Attribute_Fields::PAGE_TYPE_EDIT_CATEGORY,
			$term
		);
		
		// Set expected incorrect usage for WordPress doing_it_wrong
		$this->setExpectedIncorrectUsage( '__get' );
		
		// Test accessing private properties (should trigger doing_it_wrong)
		$page_type = $fields->page_type;
		$this->assertEquals( Enhanced_Catalog_Attribute_Fields::PAGE_TYPE_EDIT_CATEGORY, $page_type );
		
		// Test that it returns null for non-existent properties
		$non_existent = $fields->non_existent_property;
		$this->assertNull( $non_existent );
	}

	/**
	 * Test render method with empty category ID.
	 */
	public function test_render_with_empty_category_id() {
		// Mock the category handler
		$mock_category_handler = $this->createMock( \WooCommerce\Facebook\Products\FBCategories::class );
		$mock_category_handler->method( 'get_attributes_with_fallback_to_parent_category' )
			->willReturn( [] );
		
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->method( 'get_facebook_category_handler' )->willReturn( $mock_category_handler );
		
		$this->add_filter_with_safe_teardown( 'wc_facebook_instance', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
		
		$fields = new Enhanced_Catalog_Attribute_Fields( Enhanced_Catalog_Attribute_Fields::PAGE_TYPE_EDIT_CATEGORY );
		
		ob_start();
		$fields->render( '' );
		$output = ob_get_clean();
		
		// Should output minimal or no content for empty category
		$this->assertIsString( $output );
	}

	/**
	 * Test render method with valid category and attributes.
	 */
	public function test_render_with_valid_category_and_attributes() {
		// Mock attributes
		$mock_attributes = [
			[ 'key' => 'color', 'recommended' => true, 'type' => 'enum', 'enum_values' => ['red', 'blue', 'green'], 'description' => 'Product color', 'example' => 'red' ],
			[ 'key' => 'size', 'recommended' => true, 'type' => 'string', 'description' => 'Product size', 'example' => 'Large' ],
			[ 'key' => 'brand', 'recommended' => false, 'type' => 'string', 'description' => 'Product brand', 'example' => 'Nike' ]
		];
		
		// Mock the category handler
		$mock_category_handler = $this->createMock( \WooCommerce\Facebook\Products\FBCategories::class );
		$mock_category_handler->method( 'get_attributes_with_fallback_to_parent_category' )
			->willReturn( $mock_attributes );
		$mock_category_handler->method( 'is_valid_value_for_attribute' )
			->willReturn( true );
		
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->method( 'get_facebook_category_handler' )->willReturn( $mock_category_handler );
		
		$this->add_filter_with_safe_teardown( 'wc_facebook_instance', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
		
		// Mock term meta to return null for values
		$this->add_filter_with_safe_teardown( 'get_term_metadata', function( $value, $term_id, $meta_key, $single ) {
			if ( $single ) {
				return '';
			}
			return $value;
		}, 10, 4 );
		
		// Create a term for the test
		$term = new \WP_Term( (object) [ 
			'term_id' => 456,
			'name' => 'Test Category',
			'slug' => 'test-category',
			'term_group' => 0,
			'term_taxonomy_id' => 456,
			'taxonomy' => 'product_cat',
			'description' => '',
			'parent' => 0,
			'count' => 0
		] );
		
		$fields = new Enhanced_Catalog_Attribute_Fields( 
			Enhanced_Catalog_Attribute_Fields::PAGE_TYPE_EDIT_CATEGORY,
			$term
		);
		
		ob_start();
		$fields->render( 'test_category_123' );
		$output = ob_get_clean();
		
		// Check output contains expected elements
		$this->assertStringContainsString( 'color', $output );
		$this->assertStringContainsString( 'size', $output );
		$this->assertStringContainsString( Enhanced_Catalog_Attribute_Fields::FIELD_ENHANCED_CATALOG_ATTRIBUTE_PREFIX, $output );
	}

	/**
	 * Test get_value method with product.
	 */
	public function test_get_value_with_product() {
		// Mock the category handler first
		$mock_category_handler = $this->createMock( \WooCommerce\Facebook\Products\FBCategories::class );
		$mock_category_handler->method( 'is_valid_value_for_attribute' )
			->willReturn( true );
		
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->method( 'get_facebook_category_handler' )->willReturn( $mock_category_handler );
		
		$this->add_filter_with_safe_teardown( 'wc_facebook_instance', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
		
		// Create a mock product
		$product = $this->createMock( \WC_Product::class );
		$product->method( 'get_id' )->willReturn( 123 );
		$product->method( 'get_meta' )
			->with( '_wc_facebook_enhanced_catalog_attributes_color' )
			->willReturn( 'red' );
		$product->method( 'get_attributes' )->willReturn( [] );
		$product->method( 'is_type' )->willReturn( false );
		
		$fields = new Enhanced_Catalog_Attribute_Fields( 
			Enhanced_Catalog_Attribute_Fields::PAGE_TYPE_EDIT_PRODUCT,
			null,
			$product
		);
		
		// Use reflection to access private method
		$reflection = new \ReflectionClass( $fields );
		$method = $reflection->getMethod( 'get_value' );
		$method->setAccessible( true );
		
		$value = $method->invokeArgs( $fields, [ 'color', 'test_category' ] );
		
		// Should return the mocked value
		$this->assertEquals( 'red', $value );
	}

	/**
	 * Test get_value method with term.
	 */
	public function test_get_value_with_term() {
		// Mock the category handler first
		$mock_category_handler = $this->createMock( \WooCommerce\Facebook\Products\FBCategories::class );
		$mock_category_handler->method( 'is_valid_value_for_attribute' )
			->willReturn( true );
		
		$mock_plugin = $this->createMock( \WC_Facebookcommerce::class );
		$mock_plugin->method( 'get_facebook_category_handler' )->willReturn( $mock_category_handler );
		
		$this->add_filter_with_safe_teardown( 'wc_facebook_instance', function() use ( $mock_plugin ) {
			return $mock_plugin;
		} );
		
		// Mock term meta
		$this->add_filter_with_safe_teardown( 'get_term_metadata', function( $value, $term_id, $meta_key, $single ) {
			if ( $term_id === 123 && $meta_key === '_wc_facebook_enhanced_catalog_attributes_color' && $single ) {
				return 'blue';
			}
			return $value;
		}, 10, 4 );
		
		// Create a proper WP_Term object
		$term = new \WP_Term( (object) [ 
			'term_id' => 123,
			'name' => 'Test Category',
			'slug' => 'test-category',
			'term_group' => 0,
			'term_taxonomy_id' => 123,
			'taxonomy' => 'product_cat',
			'description' => '',
			'parent' => 0,
			'count' => 0
		] );
		
		$fields = new Enhanced_Catalog_Attribute_Fields( 
			Enhanced_Catalog_Attribute_Fields::PAGE_TYPE_EDIT_CATEGORY,
			$term
		);
		
		// Use reflection to access private method
		$reflection = new \ReflectionClass( $fields );
		$method = $reflection->getMethod( 'get_value' );
		$method->setAccessible( true );
		
		$value = $method->invokeArgs( $fields, [ 'color', 'test_category' ] );
		
		// Should return the mocked value
		$this->assertEquals( 'blue', $value );
	}

	/**
	 * Test render_selector_checkbox for edit product page type.
	 */
	public function test_render_selector_checkbox_for_edit_product() {
		$fields = new Enhanced_Catalog_Attribute_Fields( Enhanced_Catalog_Attribute_Fields::PAGE_TYPE_EDIT_PRODUCT );
		
		// Use reflection to access private method
		$reflection = new \ReflectionClass( $fields );
		$method = $reflection->getMethod( 'render_selector_checkbox' );
		$method->setAccessible( true );
		
		ob_start();
		$method->invokeArgs( $fields, [ true ] );
		$output = ob_get_clean();
		
		// Check output for product page type
		$this->assertStringContainsString( '<p class="form-field', $output );
		$this->assertStringContainsString( 'type="checkbox"', $output );
		// Check for the escaped version since esc_attr is used
		$this->assertStringContainsString( 'checked=&quot;checked&quot;', $output );
		$this->assertStringContainsString( 'Show more attributes', $output );
	}

	/**
	 * Test render_selector_checkbox for category page types.
	 */
	public function test_render_selector_checkbox_for_category_pages() {
		$fields = new Enhanced_Catalog_Attribute_Fields( Enhanced_Catalog_Attribute_Fields::PAGE_TYPE_EDIT_CATEGORY );
		
		// Use reflection to access private method
		$reflection = new \ReflectionClass( $fields );
		$method = $reflection->getMethod( 'render_selector_checkbox' );
		$method->setAccessible( true );
		
		ob_start();
		$method->invokeArgs( $fields, [ false ] );
		$output = ob_get_clean();
		
		// Check output for category page type
		$this->assertStringContainsString( '<tr class="form-field', $output );
		$this->assertStringContainsString( 'type="checkbox"', $output );
		$this->assertStringNotContainsString( 'checked="checked"', $output );
		$this->assertStringContainsString( 'Show more attributes', $output );
	}

	/**
	 * Test render_attribute method for different attribute types.
	 */
	public function test_render_attribute_different_types() {
		$fields = new Enhanced_Catalog_Attribute_Fields( Enhanced_Catalog_Attribute_Fields::PAGE_TYPE_EDIT_CATEGORY );
		
		// Use reflection to access private method
		$reflection = new \ReflectionClass( $fields );
		$method = $reflection->getMethod( 'render_attribute' );
		$method->setAccessible( true );
		
		// Test boolean/enum attribute
		$enum_attribute = [
			'key' => 'gender',
			'type' => 'enum',
			'enum_values' => ['male', 'female', 'unisex'],
			'value' => 'unisex',
			'description' => 'Product gender',
			'example' => 'unisex'
		];
		
		ob_start();
		$method->invokeArgs( $fields, [ $enum_attribute, false, true ] );
		$output = ob_get_clean();
		
		// Should render select field
		$this->assertStringContainsString( '<select', $output );
		$this->assertStringContainsString( '<option', $output );
		$this->assertStringContainsString( 'male', $output );
		$this->assertStringContainsString( 'female', $output );
		$this->assertStringContainsString( 'unisex', $output );
		
		// Test string attribute
		$string_attribute = [
			'key' => 'brand',
			'type' => 'string',
			'value' => 'Nike',
			'description' => 'Product brand',
			'example' => 'Nike, Adidas'
		];
		
		ob_start();
		$method->invokeArgs( $fields, [ $string_attribute, false, true ] );
		$output = ob_get_clean();
		
		// Should render text input
		$this->assertStringContainsString( '<input type="text"', $output );
		$this->assertStringContainsString( 'value="Nike"', $output );
		$this->assertStringContainsString( 'placeholder="Nike, Adidas"', $output );
	}

	/**
	 * Test render_field method with multiple values support.
	 */
	public function test_render_field_with_multiple_values() {
		$fields = new Enhanced_Catalog_Attribute_Fields( Enhanced_Catalog_Attribute_Fields::PAGE_TYPE_EDIT_CATEGORY );
		
		// Use reflection to access private method
		$reflection = new \ReflectionClass( $fields );
		$method = $reflection->getMethod( 'render_field' );
		$method->setAccessible( true );
		
		// Test enum with multiple values allowed
		$multi_value_attribute = [
			'key' => 'colors',
			'type' => 'enum',
			'enum_values' => ['red', 'blue', 'green'],
			'value' => 'red,blue',
			'can_have_multiple_values' => true,
			'description' => 'Product colors',
			'example' => 'red, blue'
		];
		
		ob_start();
		$method->invokeArgs( $fields, [ Enhanced_Catalog_Attribute_Fields::FIELD_ENHANCED_CATALOG_ATTRIBUTE_PREFIX . 'colors', $multi_value_attribute ] );
		$output = ob_get_clean();
		
		// Should render text input instead of select for multi-value enum
		$this->assertStringContainsString( '<input type="text"', $output );
		$this->assertStringNotContainsString( '<select', $output );
	}
} 