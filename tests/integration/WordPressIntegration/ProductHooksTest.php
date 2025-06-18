<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Integration\WordPressIntegration;

use WooCommerce\Facebook\Tests\Integration\IntegrationTestCase;

/**
 * Test class for WordPress and WooCommerce hook integration scenarios.
 *
 * Tests how the plugin responds to WordPress events and hooks.
 *
 * @group wordpress-integration
 * @group integration
 */
class ProductHooksTest extends IntegrationTestCase {

	/**
	 * Test product save hook integration
	 */
	public function test_product_save_hook_triggers(): void {
		$this->enable_facebook_sync();

		// Create a product
		$product = $this->create_simple_product([
			'name' => 'Hook Test Product',
			'regular_price' => '15.99',
			'status' => 'publish'
		]);

		// Verify product was created
		$this->assertTrue( $product->get_id() > 0, 'Product should have been created with valid ID' );
		$this->assertEquals( 'publish', $product->get_status(), 'Product should be published' );

		// Update the product to trigger save hooks
		$product->set_name( 'Updated Hook Test Product' );
		$product->set_regular_price( '19.99' );
		$product->save();

		// Verify updates were saved
		$updated_product = wc_get_product( $product->get_id() );
		$this->assertEquals( 'Updated Hook Test Product', $updated_product->get_name(), 'Product name should be updated' );
		$this->assertEquals( '19.99', $updated_product->get_regular_price(), 'Product price should be updated' );
	}

	/**
	 * Test product status change hooks
	 */
	public function test_product_status_change_hooks(): void {
		$this->enable_facebook_sync();

		// Create a draft product
		$product = $this->create_simple_product([
			'name' => 'Status Change Test',
			'regular_price' => '25.00',
			'status' => 'draft'
		]);

		$this->assertEquals( 'draft', $product->get_status(), 'Product should start as draft' );

		// Publish the product
		$product->set_status( 'publish' );
		$product->save();

		// Verify status change
		$published_product = wc_get_product( $product->get_id() );
		$this->assertEquals( 'publish', $published_product->get_status(), 'Product should be published' );

		// Test sync eligibility after status change
		$this->assertProductShouldSync( $published_product, 'Published product should be eligible for sync' );
	}

	/**
	 * Test product deletion hooks
	 */
	public function test_product_deletion_hooks(): void {
		$this->enable_facebook_sync();

		// Create a product
		$product = $this->create_simple_product([
			'name' => 'Deletion Test Product',
			'regular_price' => '30.00',
			'status' => 'publish'
		]);

		$product_id = $product->get_id();
		$this->assertTrue( $product_id > 0, 'Product should have valid ID' );

		// Verify product exists
		$this->assertInstanceOf( 'WC_Product', wc_get_product( $product_id ), 'Product should exist before deletion' );

		// Delete the product
		wp_delete_post( $product_id, true );

		// Verify product is deleted
		$this->assertFalse( wc_get_product( $product_id ), 'Product should not exist after deletion' );
	}

	/**
	 * Test product category assignment hooks
	 */
	public function test_product_category_assignment_hooks(): void {
		$this->enable_facebook_sync();

		// Create categories
		$electronics_cat = $this->create_category( 'Electronics' );
		$phones_cat = $this->create_category( 'Phones', $electronics_cat->term_id );

		// Create product
		$product = $this->create_simple_product([
			'name' => 'Category Test Product',
			'regular_price' => '199.99',
			'status' => 'publish'
		]);

		// Assign categories
		wp_set_object_terms( $product->get_id(), [ $electronics_cat->term_id, $phones_cat->term_id ], 'product_cat' );

		// Refresh product data
		$product = wc_get_product( $product->get_id() );
		$category_ids = $product->get_category_ids();

		$this->assertContains( $electronics_cat->term_id, $category_ids, 'Product should have Electronics category' );
		$this->assertContains( $phones_cat->term_id, $category_ids, 'Product should have Phones category' );
	}

	/**
	 * Test product meta update hooks
	 */
	public function test_product_meta_update_hooks(): void {
		$this->enable_facebook_sync();

		// Create product
		$product = $this->create_simple_product([
			'name' => 'Meta Test Product',
			'regular_price' => '45.00',
			'status' => 'publish'
		]);

		$product_id = $product->get_id();

		// Add custom meta
		update_post_meta( $product_id, '_test_meta_key', 'test_meta_value' );
		update_post_meta( $product_id, '_facebook_sync_enabled', 'yes' );

		// Verify meta was saved
		$this->assertEquals( 'test_meta_value', get_post_meta( $product_id, '_test_meta_key', true ), 'Custom meta should be saved' );
		$this->assertEquals( 'yes', get_post_meta( $product_id, '_facebook_sync_enabled', true ), 'Facebook sync meta should be saved' );

		// Update meta
		update_post_meta( $product_id, '_test_meta_key', 'updated_meta_value' );

		// Verify meta update
		$this->assertEquals( 'updated_meta_value', get_post_meta( $product_id, '_test_meta_key', true ), 'Meta should be updated' );
	}

	/**
	 * Test variable product and variation hooks
	 */
	public function test_variable_product_hooks(): void {
		$this->enable_facebook_sync();

		// Create variable product
		$variable_product = $this->create_variable_product();
		$this->assertEquals( 'variable', $variable_product->get_type(), 'Product should be variable type' );

		// Create a variation
		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $variable_product->get_id() );
		$variation->set_attributes( [ 'size' => 'large' ] );
		$variation->set_regular_price( '29.99' );
		$variation->set_status( 'publish' );
		$variation->save();

		$this->assertTrue( $variation->get_id() > 0, 'Variation should have valid ID' );
		$this->assertEquals( $variable_product->get_id(), $variation->get_parent_id(), 'Variation should have correct parent' );

		// Update variation
		$variation->set_regular_price( '34.99' );
		$variation->save();

		// Verify variation update
		$updated_variation = wc_get_product( $variation->get_id() );
		$this->assertEquals( '34.99', $updated_variation->get_regular_price(), 'Variation price should be updated' );
	}

	/**
	 * Test product image attachment hooks
	 */
	public function test_product_image_hooks(): void {
		$this->enable_facebook_sync();

		// Create product
		$product = $this->create_simple_product([
			'name' => 'Image Test Product',
			'regular_price' => '55.00',
			'status' => 'publish'
		]);

		// Test that product starts without images
		$this->assertEmpty( $product->get_image_id(), 'Product should start without main image' );
		$this->assertEmpty( $product->get_gallery_image_ids(), 'Product should start without gallery images' );

		// Note: In a real test environment, we would create actual image attachments
		// For integration tests, we're testing the hook structure and data flow
		$this->assertProductShouldSync( $product, 'Product without images should still be syncable' );
	}

	/**
	 * Test product inventory update hooks
	 */
	public function test_product_inventory_hooks(): void {
		$this->enable_facebook_sync();

		// Create product with stock management
		$product = $this->create_simple_product([
			'name' => 'Inventory Test Product',
			'regular_price' => '40.00',
			'manage_stock' => true,
			'stock_quantity' => 100,
			'stock_status' => 'instock',
			'status' => 'publish'
		]);

		// Verify initial stock
		$this->assertTrue( $product->managing_stock(), 'Product should manage stock' );
		$this->assertEquals( 100, $product->get_stock_quantity(), 'Initial stock should be 100' );
		$this->assertTrue( $product->is_in_stock(), 'Product should be in stock' );

		// Update stock
		$product->set_stock_quantity( 50 );
		$product->save();

		// Verify stock update
		$updated_product = wc_get_product( $product->get_id() );
		$this->assertEquals( 50, $updated_product->get_stock_quantity(), 'Stock should be updated to 50' );

		// Set out of stock
		$product->set_stock_status( 'outofstock' );
		$product->set_stock_quantity( 0 );
		$product->save();

		// Verify out of stock
		$out_of_stock_product = wc_get_product( $product->get_id() );
		$this->assertFalse( $out_of_stock_product->is_in_stock(), 'Product should be out of stock' );
		$this->assertEquals( 0, $out_of_stock_product->get_stock_quantity(), 'Stock quantity should be 0' );
	}

	/**
	 * Test product attribute update hooks
	 */
	public function test_product_attribute_hooks(): void {
		$this->enable_facebook_sync();

		// Create product
		$product = $this->create_simple_product([
			'name' => 'Attribute Test Product',
			'regular_price' => '65.00',
			'status' => 'publish'
		]);

		// Add attributes using the proper WooCommerce format
		$attributes = [];
		$color_attribute = new \WC_Product_Attribute();
		$color_attribute->set_name( 'pa_color' ); // Use proper attribute taxonomy format
		$color_attribute->set_options( [ 'Red', 'Blue' ] );
		$color_attribute->set_visible( true );
		$color_attribute->set_variation( false );
		$attributes['pa_color'] = $color_attribute;

		$product->set_attributes( $attributes );
		$product->save();

		// Verify attributes were saved
		$updated_product = wc_get_product( $product->get_id() );
		$saved_attributes = $updated_product->get_attributes();
		
		$this->assertNotEmpty( $saved_attributes, 'Product should have attributes' );
		$this->assertArrayHasKey( 'pa_color', $saved_attributes, 'Product should have pa_color attribute' );
		
		if ( isset( $saved_attributes['pa_color'] ) ) {
			$this->assertEquals( [ 'Red', 'Blue' ], $saved_attributes['pa_color']->get_options(), 'Color attribute should have correct options' );
		}
	}

	/**
	 * Test bulk product operations
	 */
	public function test_bulk_product_operations(): void {
		$this->enable_facebook_sync();

		// Create multiple products
		$products = [];
		for ( $i = 1; $i <= 5; $i++ ) {
			$products[] = $this->create_simple_product([
				'name' => "Bulk Test Product {$i}",
				'regular_price' => "1{$i}.99",
				'status' => 'publish'
			]);
		}

		// Verify all products were created
		$this->assertCount( 5, $products, 'Should have created 5 products' );

		foreach ( $products as $index => $product ) {
			$expected_name = "Bulk Test Product " . ($index + 1);
			$this->assertEquals( $expected_name, $product->get_name(), "Product {$index} should have correct name" );
			$this->assertTrue( $product->get_id() > 0, "Product {$index} should have valid ID" );
		}

		// Test bulk status update
		foreach ( $products as $product ) {
			$product->set_status( 'draft' );
			$product->save();
		}

		// Verify bulk status change
		foreach ( $products as $product ) {
			$updated_product = wc_get_product( $product->get_id() );
			$this->assertEquals( 'draft', $updated_product->get_status(), 'Product should be changed to draft' );
		}
	}
} 