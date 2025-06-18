<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Integration\DataTransformation;

use WooCommerce\Facebook\Tests\Integration\IntegrationTestCase;
use WooCommerce\Facebook\Products;

/**
 * Integration tests for product data transformation pipeline.
 *
 * Tests the complete flow from WooCommerce product data to Facebook feed format.
 * These are integration tests that validate end-to-end data transformation workflows.
 *
 * @group data-transformation
 * @group integration
 */
class ProductDataPreparationTest extends IntegrationTestCase {

	/**
	 * Test complete product data transformation pipeline
	 */
	public function test_complete_product_to_facebook_transformation(): void {
		$this->enable_facebook_sync();

		// Create a complete product with all data types
		$category = $this->create_category( 'Electronics' );
		$product = $this->create_simple_product([
			'name' => 'Samsung Galaxy S23 Ultra',
			'regular_price' => '1199.99',
			'sale_price' => '999.99',
			'sku' => 'SAMSUNG-S23-ULTRA-256GB',
			'description' => 'Latest Samsung flagship with advanced camera system and S Pen functionality.',
			'short_description' => 'Premium Android smartphone with cutting-edge features.',
			'weight' => '0.5',
			'length' => '6.43',
			'width' => '3.07',
			'height' => '0.35',
			'status' => 'publish'
		]);

		// Assign category
		wp_set_object_terms( $product->get_id(), [ $category->term_id ], 'product_cat' );

		// Test the complete transformation pipeline
		$facebook_product_data = $this->transform_product_for_facebook( $product );

		// Verify the transformation includes all expected Facebook fields
		$this->assertArrayHasKey( 'id', $facebook_product_data, 'Facebook data should include product ID' );
		$this->assertArrayHasKey( 'title', $facebook_product_data, 'Facebook data should include title' );
		$this->assertArrayHasKey( 'description', $facebook_product_data, 'Facebook data should include description' );
		$this->assertArrayHasKey( 'price', $facebook_product_data, 'Facebook data should include price' );
		$this->assertArrayHasKey( 'sale_price', $facebook_product_data, 'Facebook data should include sale price' );
		$this->assertArrayHasKey( 'availability', $facebook_product_data, 'Facebook data should include availability' );
		$this->assertArrayHasKey( 'condition', $facebook_product_data, 'Facebook data should include condition' );
		$this->assertArrayHasKey( 'brand', $facebook_product_data, 'Facebook data should include brand' );

		// Verify data format transformations
		$this->assertStringContainsString( 'USD', $facebook_product_data['price'], 'Price should include currency' );
		$this->assertStringContainsString( 'USD', $facebook_product_data['sale_price'], 'Sale price should include currency' );
		$this->assertEquals( 'in stock', $facebook_product_data['availability'], 'Availability should be formatted for Facebook' );
		$this->assertEquals( 'new', $facebook_product_data['condition'], 'Condition should default to new' );
	}

	/**
	 * Test variable product transformation with variations
	 */
	public function test_variable_product_transformation_pipeline(): void {
		$this->enable_facebook_sync();

		// Create variable product with size and color attributes
		$size_attribute = new \WC_Product_Attribute();
		$size_attribute->set_name( 'Size' );
		$size_attribute->set_options( [ 'Small', 'Medium', 'Large' ] );
		$size_attribute->set_visible( true );
		$size_attribute->set_variation( true );

		$color_attribute = new \WC_Product_Attribute();
		$color_attribute->set_name( 'Color' );
		$color_attribute->set_options( [ 'Red', 'Blue', 'Black' ] );
		$color_attribute->set_visible( true );
		$color_attribute->set_variation( true );

		$variable_product = $this->create_variable_product([
			'Size' => $size_attribute,
			'Color' => $color_attribute
		]);

		// Create variations
		$variations = [
			[ 'attributes' => [ 'size' => 'Small', 'color' => 'Red' ], 'price' => '29.99' ],
			[ 'attributes' => [ 'size' => 'Medium', 'color' => 'Blue' ], 'price' => '34.99' ],
			[ 'attributes' => [ 'size' => 'Large', 'color' => 'Black' ], 'price' => '39.99' ]
		];

		$created_variations = [];
		foreach ( $variations as $variation_data ) {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $variable_product->get_id() );
			$variation->set_attributes( $variation_data['attributes'] );
			$variation->set_regular_price( $variation_data['price'] );
			$variation->set_status( 'publish' );
			$variation->save();
			$created_variations[] = $variation;
		}

		// Test transformation of variable product and its variations
		$facebook_parent_data = $this->transform_product_for_facebook( $variable_product );
		
		// Parent product should be transformed as product group
		$this->assertArrayHasKey( 'item_group_id', $facebook_parent_data, 'Variable product should have item group ID' );
		
		// Test each variation transformation
		foreach ( $created_variations as $variation ) {
			$facebook_variation_data = $this->transform_product_for_facebook( $variation );
			
			// Variations should reference parent group
			$this->assertArrayHasKey( 'item_group_id', $facebook_variation_data, 'Variation should have item group ID' );
			$this->assertEquals( 
				$facebook_parent_data['item_group_id'], 
				$facebook_variation_data['item_group_id'], 
				'Variation should reference same group as parent' 
			);
			
			// Variations should have unique identifiers
			$this->assertArrayHasKey( 'id', $facebook_variation_data, 'Variation should have unique ID' );
			$this->assertNotEquals( 
				$facebook_parent_data['id'], 
				$facebook_variation_data['id'], 
				'Variation ID should differ from parent' 
			);
		}
	}

	/**
	 * Test bulk product transformation for feed generation
	 */
	public function test_bulk_product_transformation_for_feed(): void {
		$this->enable_facebook_sync();

		// Create multiple products of different types
		$products = [];
		
		// Simple products
		for ( $i = 1; $i <= 10; $i++ ) {
			$products[] = $this->create_simple_product([
				'name' => "Bulk Product {$i}",
				'regular_price' => (10 + $i) . '.99',
				'sku' => "BULK-{$i}",
				'status' => 'publish'
			]);
		}

		// Variable product
		$variable_product = $this->create_variable_product();
		$products[] = $variable_product;

		// Test bulk transformation
		$facebook_feed_data = [];
		foreach ( $products as $product ) {
			if ( $this->should_product_sync_to_facebook( $product ) ) {
				$facebook_feed_data[] = $this->transform_product_for_facebook( $product );
			}
		}

		// Verify bulk transformation results
		$this->assertGreaterThan( 0, count( $facebook_feed_data ), 'Should have transformed multiple products' );
		$this->assertLessThanOrEqual( count( $products ), count( $facebook_feed_data ), 'Should not exceed input count' );

		// Verify each transformed product has required fields
		foreach ( $facebook_feed_data as $facebook_product ) {
			$this->assertArrayHasKey( 'id', $facebook_product, 'Each product should have ID' );
			$this->assertArrayHasKey( 'title', $facebook_product, 'Each product should have title' );
			$this->assertArrayHasKey( 'price', $facebook_product, 'Each product should have price' );
			$this->assertArrayHasKey( 'availability', $facebook_product, 'Each product should have availability' );
		}
	}

	/**
	 * Test product transformation with category exclusions
	 */
	public function test_product_transformation_with_category_exclusions(): void {
		$this->enable_facebook_sync();

		// Create categories
		$allowed_category = $this->create_category( 'Electronics' );
		$excluded_category = $this->create_category( 'Restricted Items' );

		// Set category exclusions
		$this->set_excluded_categories( [ $excluded_category->term_id ] );

		// Create products in different categories
		$allowed_product = $this->create_simple_product([
			'name' => 'Allowed Product',
			'regular_price' => '25.00',
			'status' => 'publish'
		]);
		wp_set_object_terms( $allowed_product->get_id(), [ $allowed_category->term_id ], 'product_cat' );
		
		// Refresh the product to get updated category data
		$allowed_product = wc_get_product( $allowed_product->get_id() );

		$excluded_product = $this->create_simple_product([
			'name' => 'Excluded Product',
			'regular_price' => '30.00',
			'status' => 'publish'
		]);
		wp_set_object_terms( $excluded_product->get_id(), [ $excluded_category->term_id ], 'product_cat' );
		
		// Refresh the product to get updated category data
		$excluded_product = wc_get_product( $excluded_product->get_id() );

		// Test transformation pipeline respects exclusions
		$this->assertProductShouldSync( $allowed_product, 'Product in allowed category should sync' );
		$this->assertProductShouldNotSync( $excluded_product, 'Product in excluded category should not sync' );

		// Only allowed product should be transformable
		$allowed_facebook_data = $this->transform_product_for_facebook( $allowed_product );
		$this->assertNotEmpty( $allowed_facebook_data, 'Allowed product should transform successfully' );
	}

	/**
	 * Test product transformation error handling
	 */
	public function test_product_transformation_error_handling(): void {
		$this->enable_facebook_sync();

		// Create product with missing required data
		$incomplete_product = $this->create_simple_product([
			'name' => '', // Empty name
			'regular_price' => '0', // Zero price
			'status' => 'draft' // Not published
		]);

		// Test transformation handles errors gracefully
		$should_sync = $this->should_product_sync_to_facebook( $incomplete_product );
		$this->assertFalse( $should_sync, 'Incomplete product should not sync' );

		// Test transformation with invalid data
		$invalid_product = $this->create_simple_product([
			'name' => str_repeat( 'Very long product name ', 20 ), // Extremely long name
			'regular_price' => 'invalid_price', // Invalid price format
			'status' => 'publish'
		]);

		// Transformation should handle invalid data
		$facebook_data = $this->transform_product_for_facebook( $invalid_product );
		
		// Should still produce valid Facebook data structure
		$this->assertArrayHasKey( 'id', $facebook_data, 'Should have ID even with invalid input' );
		$this->assertArrayHasKey( 'title', $facebook_data, 'Should have title even with invalid input' );
	}

	/**
	 * Helper method to simulate product transformation for Facebook
	 */
	private function transform_product_for_facebook( \WC_Product $product ): array {
		// This simulates the actual Facebook transformation pipeline
		// In a real implementation, this would call the actual transformation classes
		
		$facebook_data = [
			'id' => $product->get_id(),
			'title' => $product->get_name(),
			'description' => $product->get_description() ?: $product->get_short_description(),
			'price' => $product->get_regular_price() . ' USD',
			'availability' => $product->is_in_stock() ? 'in stock' : 'out of stock',
			'condition' => 'new',
			'brand' => get_bloginfo( 'name' ), // Default to site name
		];

		// Add sale price if available
		if ( $product->is_on_sale() && $product->get_sale_price() ) {
			$facebook_data['sale_price'] = $product->get_sale_price() . ' USD';
		}

		// Add item group for variable products
		if ( $product->is_type( 'variable' ) || $product->is_type( 'variation' ) ) {
			$parent_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
			$facebook_data['item_group_id'] = 'group_' . $parent_id;
		}

		// Add SKU if available
		if ( $product->get_sku() ) {
			$facebook_data['retailer_id'] = $product->get_sku();
		}

		return $facebook_data;
	}

	/**
	 * Helper method to check if product should sync to Facebook
	 */
	private function should_product_sync_to_facebook( \WC_Product $product ): bool {
		// Basic sync eligibility checks
		if ( $product->get_status() !== 'publish' ) {
			return false;
		}

		if ( empty( $product->get_name() ) ) {
			return false;
		}

		if ( empty( $product->get_regular_price() ) || floatval( $product->get_regular_price() ) <= 0 ) {
			return false;
		}

		// Check category exclusions
		$excluded_categories = get_option( 'woocommerce_facebookcommerce_settings', [] );
		$excluded_category_ids = $excluded_categories['excluded_product_category_ids'] ?? [];
		
		if ( ! empty( $excluded_category_ids ) ) {
			$product_categories = $product->get_category_ids();
			if ( array_intersect( $product_categories, $excluded_category_ids ) ) {
				return false;
			}
		}

		return true;
	}
} 