<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Tests\Unit;

use WC_Product;
use WooCommerce\Facebook\ProductAttributeMapper;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;

/**
 * Unit tests for the ProductAttributeMapper class.
 */
class ProductAttributeMapperTest extends AbstractWPUnitTestWithSafeFiltering {

	/** @var WC_Product */
	private $product;

	/**
	 * Setup test case.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->product = $this->createMock( WC_Product::class );
	}

	/**
	 * Test sanitize_attribute_name function
	 */
	public function test_sanitize_attribute_name() {
		// Test removing pa_ prefix
		$this->assertEquals(
			'material',
			ProductAttributeMapper::sanitize_attribute_name( 'pa_material' )
		);

		// Test converting spaces to underscores
		$this->assertEquals(
			'product_material',
			ProductAttributeMapper::sanitize_attribute_name( 'Product Material' )
		);

		// Test converting special characters to underscores
		$this->assertEquals(
			'material_type',
			ProductAttributeMapper::sanitize_attribute_name( 'Material-Type' )
		);

		// Test lowercase conversion
		$this->assertEquals(
			'color',
			ProductAttributeMapper::sanitize_attribute_name( 'COLOR' )
		);
	}

	/**
	 * Test check_attribute_mapping function
	 */
	public function test_check_attribute_mapping() {
		// Test direct match with standard field
		$this->assertEquals(
			'color',
			ProductAttributeMapper::check_attribute_mapping( 'color' )
		);

		// Test match with alternative naming for standard field
		$this->assertEquals(
			'color',
			ProductAttributeMapper::check_attribute_mapping( 'colour' )
		);

		// Test match with taxonomy attribute
		$this->assertEquals(
			'material',
			ProductAttributeMapper::check_attribute_mapping( 'pa_material' )
		);

		// Test common naming variation
		$this->assertEquals(
			'color',
			ProductAttributeMapper::check_attribute_mapping( 'product_color' )
		);

		// Test non-matching attribute
		$this->assertFalse(
			ProductAttributeMapper::check_attribute_mapping( 'nonexistent_attribute' )
		);
	}

	/**
	 * Test normalize_gender_value function
	 */
	public function test_normalize_gender_value() {
		$reflection = new \ReflectionClass( ProductAttributeMapper::class );
		$method = $reflection->getMethod( 'normalize_gender_value' );
		$method->setAccessible( true );

		// Test various gender names mapping to male
		$this->assertEquals( 'male', $method->invoke( null, 'Men' ) );
		$this->assertEquals( 'male', $method->invoke( null, 'man' ) );
		$this->assertEquals( 'male', $method->invoke( null, 'boy' ) );

		// Test various gender names mapping to female
		$this->assertEquals( 'female', $method->invoke( null, 'Women' ) );
		$this->assertEquals( 'female', $method->invoke( null, 'woman' ) );
		$this->assertEquals( 'female', $method->invoke( null, 'girl' ) );

		// Test various gender names mapping to unisex
		$this->assertEquals( 'unisex', $method->invoke( null, 'Unisex' ) );
		$this->assertEquals( 'unisex', $method->invoke( null, 'neutral' ) );
		$this->assertEquals( 'unisex', $method->invoke( null, 'all' ) );

		// Test unknown gender values pass through unchanged
		$this->assertEquals( 'other', $method->invoke( null, 'other' ) );
	}

	/**
	 * Test normalize_age_group_value function
	 */
	public function test_normalize_age_group_value() {
		$reflection = new \ReflectionClass( ProductAttributeMapper::class );
		$method = $reflection->getMethod( 'normalize_age_group_value' );
		$method->setAccessible( true );

		// Test various age group names
		$this->assertEquals( 'adult', $method->invoke( null, 'Adult' ) );
		$this->assertEquals( 'adult', $method->invoke( null, 'adults' ) );
		
		$this->assertEquals( 'all ages', $method->invoke( null, 'All Ages' ) );
		$this->assertEquals( 'all ages', $method->invoke( null, 'everyone' ) );
		
		$this->assertEquals( 'teen', $method->invoke( null, 'Teen' ) );
		$this->assertEquals( 'teen', $method->invoke( null, 'teenagers' ) );
		
		$this->assertEquals( 'kids', $method->invoke( null, 'Kids' ) );
		$this->assertEquals( 'kids', $method->invoke( null, 'children' ) );
		
		$this->assertEquals( 'toddler', $method->invoke( null, 'Toddler' ) );
		
		$this->assertEquals( 'infant', $method->invoke( null, 'Baby' ) );
		$this->assertEquals( 'infant', $method->invoke( null, 'infants' ) );
		
		$this->assertEquals( 'newborn', $method->invoke( null, 'Newborn' ) );

		// Test unknown age group values pass through unchanged
		$this->assertEquals( 'other', $method->invoke( null, 'other' ) );
	}

	/**
	 * Test normalize_condition_value function
	 */
	public function test_normalize_condition_value() {
		$reflection = new \ReflectionClass( ProductAttributeMapper::class );
		$method = $reflection->getMethod( 'normalize_condition_value' );
		$method->setAccessible( true );

		// Test various condition names
		$this->assertEquals( 'new', $method->invoke( null, 'New' ) );
		$this->assertEquals( 'new', $method->invoke( null, 'brand new' ) );
		$this->assertEquals( 'new', $method->invoke( null, 'sealed' ) );
		
		$this->assertEquals( 'used', $method->invoke( null, 'Used' ) );
		$this->assertEquals( 'used', $method->invoke( null, 'pre-owned' ) );
		$this->assertEquals( 'used', $method->invoke( null, 'second hand' ) );
		
		$this->assertEquals( 'refurbished', $method->invoke( null, 'Refurbished' ) );
		$this->assertEquals( 'refurbished', $method->invoke( null, 'renewed' ) );
		$this->assertEquals( 'refurbished', $method->invoke( null, 'reconditioned' ) );

		// Test unknown condition values pass through unchanged
		$this->assertEquals( 'other', $method->invoke( null, 'other' ) );
	}

	/**
	 * Test get_mapped_attributes function with mocked product attributes
	 */
	public function test_get_mapped_attributes() {
		// Setup attribute data
		$attributes = array(
			'pa_color' => (object) array(), // Placeholder for WC_Product_Attribute
			'pa_size'  => (object) array(),
		);

		// Setup the product mock to return our test data
		$this->product->method( 'get_attributes' )->willReturn( $attributes );
		$this->product->method( 'get_attribute' )
			->willReturnMap(
				array(
					array( 'pa_color', 'Red' ),
					array( 'pa_size', 'Large' ),
				)
			);
		$this->product->method( 'get_meta' )->willReturn( '' );
		$this->product->method( 'get_id' )->willReturn( 123 );

		// Run the method and check results
		$mapped_attributes = ProductAttributeMapper::get_mapped_attributes( $this->product );

		// Verify that color and size are correctly mapped
		$this->assertArrayHasKey( 'color', $mapped_attributes );
		$this->assertEquals( 'Red', $mapped_attributes['color'] );
		
		$this->assertArrayHasKey( 'size', $mapped_attributes );
		$this->assertEquals( 'Large', $mapped_attributes['size'] );
	}

	/**
	 * Test custom attribute mappings
	 */
	public function test_custom_attribute_mappings() {
		// Add a custom mapping
		ProductAttributeMapper::add_custom_attribute_mapping( 'fabric_type', 'material' );
		
		// Setup attribute data
		$attributes = array(
			'pa_fabric_type' => (object) array(),
		);

		// Setup the product mock
		$this->product->method( 'get_attributes' )->willReturn( $attributes );
		$this->product->method( 'get_attribute' )
			->willReturnMap(
				array(
					array( 'pa_fabric_type', 'Cotton' ),
				)
			);
		$this->product->method( 'get_meta' )->willReturn( '' );
		$this->product->method( 'get_id' )->willReturn( 123 );

		// Get mapped attributes
		$mapped_attributes = ProductAttributeMapper::get_mapped_attributes( $this->product );
		
		// Verify fabric_type is mapped to material
		$this->assertArrayHasKey( 'material', $mapped_attributes );
		$this->assertEquals( 'Cotton', $mapped_attributes['material'] );
		
		// Test removing custom mapping
		ProductAttributeMapper::remove_custom_attribute_mapping( 'fabric_type' );
		
		// Get custom mappings and verify removal
		$custom_mappings = ProductAttributeMapper::get_custom_attribute_mappings();
		$this->assertArrayNotHasKey( 'fabric_type', $custom_mappings );
	}

	/**
	 * Test arbitrary attribute mappings to verify the general mapping system
	 * works for any WooCommerce attribute to any Facebook attribute.
	 */
	public function test_arbitrary_attribute_mappings() {
		// Get all Facebook fields to test different combinations
		$all_fb_fields = ProductAttributeMapper::get_all_facebook_fields();
		$valid_fb_field_names = array_keys($all_fb_fields);
		
		// Test a set of arbitrary mappings
		$test_mappings = array(
			// Custom WooCommerce attribute name => Facebook field
			'custom_mat_type' => 'material',
			'product_shade' => 'color',
			'garment_fit' => 'size',
			'surface_design' => 'pattern',
			'item_target' => 'gender',
			'clothing_demographic' => 'age_group',
			'item_manufacturer' => 'brand',
			'product_state' => 'condition',
			'part_number' => 'mpn',
			'barcode' => 'gtin',
		);
		
		// Verify each mapping can be set and used
		foreach ($test_mappings as $wc_attr => $fb_field) {
			// Verify the Facebook field is valid
			$this->assertContains($fb_field, $valid_fb_field_names, "Facebook field {$fb_field} is not a valid field");
			
			// Add the mapping
			$result = ProductAttributeMapper::add_custom_attribute_mapping($wc_attr, $fb_field);
			$this->assertTrue($result, "Failed to add mapping from {$wc_attr} to {$fb_field}");
			
			// Verify the mapping was set correctly
			$found_field = ProductAttributeMapper::check_attribute_mapping($wc_attr);
			$this->assertEquals($fb_field, $found_field, "Mapping from {$wc_attr} to {$fb_field} was not set correctly");
			
			// Setup a product with this attribute
			$attr_name = 'pa_' . $wc_attr;
			
			// Use different test values based on the field to account for normalization
			$attr_value = '';
			$expected_value = '';
			
			switch ($fb_field) {
				case 'gender':
					$attr_value = 'male';
					$expected_value = 'male'; // This is a standard value that shouldn't be normalized
					break;
				case 'age_group':
					$attr_value = 'adult';
					$expected_value = 'adult'; // This is a standard value that shouldn't be normalized
					break;
				case 'condition':
					$attr_value = 'new';
					$expected_value = 'new'; // This is a standard value that shouldn't be normalized
					break;
				default:
					$attr_value = 'Test Value for ' . $wc_attr;
					$expected_value = $attr_value; // For most fields, normalization shouldn't happen
					break;
			}
			
			$attributes = array(
				$attr_name => (object) array(),
			);
			
			// Mock the product
			$product = $this->createMock(WC_Product::class);
			$product->method('get_attributes')->willReturn($attributes);
			$product->method('get_attribute')
				->willReturnMap(array(
					array($attr_name, $attr_value),
				));
			$product->method('get_meta')->willReturn('');
			$product->method('get_id')->willReturn(123);
			
			// Get mapped attributes
			$mapped_attributes = ProductAttributeMapper::get_mapped_attributes($product);
			
			// Verify the mapping works correctly
			$this->assertArrayHasKey($fb_field, $mapped_attributes, "Attribute not correctly mapped to {$fb_field}");
			$this->assertEquals($expected_value, $mapped_attributes[$fb_field], "Mapped value not correct for field {$fb_field}");
			
			// Remove the mapping for next iteration
			ProductAttributeMapper::remove_custom_attribute_mapping($wc_attr);
		}
		
		// Verify all mappings have been removed
		$custom_mappings = ProductAttributeMapper::get_custom_attribute_mappings();
		foreach ($test_mappings as $wc_attr => $fb_field) {
			$this->assertArrayNotHasKey($wc_attr, $custom_mappings, "Mapping for {$wc_attr} not properly removed");
		}
	}

	/**
	 * Test saving mapped attributes to product meta
	 * 
	 * Note: We're skipping the actual update_post_meta calls since they require
	 * a complete WP environment with a real post. Instead, we'll just verify that
	 * the function returns the expected mapped attributes.
	 */
	public function test_save_mapped_attributes() {
		// Skip this test if update_post_meta function doesn't exist
		if (!function_exists('update_post_meta')) {
			$this->markTestSkipped('update_post_meta function not available.');
			return;
		}

		// Setup the product mock
		$this->product->method('get_id')->willReturn(123);
		
		// Define mapped attributes to save
		$mapped_attributes = array(
			'color' => 'Blue',
			'size' => 'Medium',
			'material' => 'Polyester',
		);
		
		// Execute the method with the expectation that update_post_meta might not actually work
		// in the test environment, but we can at least verify it returns the mapped attributes
		$result = ProductAttributeMapper::save_mapped_attributes( $this->product, $mapped_attributes );
		
		// Verify the method returned the expected attributes
		$this->assertEquals( $mapped_attributes, $result );
	}

	/**
	 * Test getting unmapped attributes
	 */
	public function test_get_unmapped_attributes() {
		// Setup attribute data with a mix of mapped and unmapped attributes
		$attributes = array(
			'pa_color' => (object) array(),  // Should be mapped
			'pa_custom_feature' => (object) array(), // Should be unmapped
		);

		// Setup the product mock
		$this->product->method( 'get_attributes' )->willReturn( $attributes );
		$this->product->method( 'get_attribute' )
			->willReturnMap(
				array(
					array( 'pa_color', 'Green' ),
					array( 'pa_custom_feature', 'Special Feature' ),
				)
			);
		
		// Get unmapped attributes
		$unmapped = ProductAttributeMapper::get_unmapped_attributes( $this->product );
		
		// Verify only the custom_feature is in the unmapped list
		$this->assertCount( 1, $unmapped );
		$this->assertEquals( 'pa_custom_feature', $unmapped[0]['name'] );
		$this->assertEquals( 'Special Feature', $unmapped[0]['value'] );
	}
} 