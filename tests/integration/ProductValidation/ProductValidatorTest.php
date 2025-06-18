<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Integration\ProductValidation;

use WooCommerce\Facebook\Tests\Integration\IntegrationTestCase;
use WooCommerce\Facebook\Products;
use WC_Product;

/**
 * Test class for ProductValidator integration scenarios.
 *
 * Tests core business logic for determining whether products should be synced to Facebook.
 *
 * @group product-validation
 * @group integration
 */
class ProductValidatorTest extends IntegrationTestCase {

	/**
	 * Test product sync when globally enabled
	 */
	public function test_product_sync_when_globally_enabled(): void {
		// Enable Facebook sync globally
		$this->enable_facebook_sync();

		// Create a simple product
		$product = $this->create_simple_product([
			'name' => 'Test Product',
			'regular_price' => '19.99',
			'status' => 'publish',
			'catalog_visibility' => 'visible'
		]);

		// Product should be synced when all conditions are met
		$this->assertProductShouldSync( $product, 'Product should sync when globally enabled and meets all criteria' );
	}

	/**
	 * Test product sync when globally disabled
	 */
	public function test_product_sync_when_globally_disabled(): void {
		// Disable Facebook sync globally
		$this->disable_facebook_sync();

		// Create a simple product
		$product = $this->create_simple_product([
			'name' => 'Test Product',
			'regular_price' => '19.99',
			'status' => 'publish',
			'catalog_visibility' => 'visible'
		]);

		// Product should not be synced when globally disabled
		$this->assertProductShouldNotSync( $product, 'Product should not sync when globally disabled' );
	}

	/**
	 * Test product sync with draft status
	 */
	public function test_product_sync_with_draft_status(): void {
		$this->enable_facebook_sync();

		// Create a draft product
		$product = $this->create_simple_product([
			'name' => 'Draft Product',
			'regular_price' => '19.99',
			'status' => 'draft',
			'catalog_visibility' => 'visible'
		]);

		// Draft products should not be synced
		$this->assertProductShouldNotSync( $product, 'Draft products should not be synced' );
	}

	/**
	 * Test product sync with private status
	 */
	public function test_product_sync_with_private_status(): void {
		$this->enable_facebook_sync();

		// Create a private product
		$product = $this->create_simple_product([
			'name' => 'Private Product',
			'regular_price' => '19.99',
			'status' => 'private',
			'catalog_visibility' => 'visible'
		]);

		// Private products should not be synced
		$this->assertProductShouldNotSync( $product, 'Private products should not be synced' );
	}

	/**
	 * Test product sync with hidden catalog visibility
	 */
	public function test_product_sync_with_hidden_visibility(): void {
		$this->enable_facebook_sync();

		// Create a hidden product
		$product = $this->create_simple_product([
			'name' => 'Hidden Product',
			'regular_price' => '19.99',
			'status' => 'publish',
			'catalog_visibility' => 'hidden'
		]);

		// Hidden products should not be synced
		$this->assertProductShouldNotSync( $product, 'Hidden products should not be synced' );
	}

	/**
	 * Test product sync with search-only visibility
	 */
	public function test_product_sync_with_search_only_visibility(): void {
		$this->enable_facebook_sync();

		// Create a search-only product
		$product = $this->create_simple_product([
			'name' => 'Search Only Product',
			'regular_price' => '19.99',
			'status' => 'publish',
			'catalog_visibility' => 'search'
		]);

		// Search-only products should be synced
		$this->assertProductShouldSync( $product, 'Search-only products should be synced' );
	}

	/**
	 * Test product sync with catalog-only visibility
	 */
	public function test_product_sync_with_catalog_only_visibility(): void {
		$this->enable_facebook_sync();

		// Create a catalog-only product
		$product = $this->create_simple_product([
			'name' => 'Catalog Only Product',
			'regular_price' => '19.99',
			'status' => 'publish',
			'catalog_visibility' => 'catalog'
		]);

		// Catalog-only products should be synced
		$this->assertProductShouldSync( $product, 'Catalog-only products should be synced' );
	}

	/**
	 * Test product sync with excluded category
	 */
	public function test_product_sync_with_excluded_category(): void {
		$this->enable_facebook_sync();

		// Create a test category
		$category = $this->create_category( 'Excluded Category' );

		// Set the category as excluded
		$this->set_excluded_categories( [ $category->term_id ] );

		// Create a product in the excluded category
		$product = $this->create_simple_product([
			'name' => 'Product in Excluded Category',
			'regular_price' => '19.99',
			'status' => 'publish',
			'catalog_visibility' => 'visible'
		]);

		// Assign product to excluded category
		wp_set_object_terms( $product->get_id(), [ $category->term_id ], 'product_cat' );

		// Product should not be synced due to excluded category
		$this->assertProductShouldNotSync( $product, 'Products in excluded categories should not be synced' );
	}

	/**
	 * Test product sync with excluded tag
	 */
	public function test_product_sync_with_excluded_tag(): void {
		$this->enable_facebook_sync();

		// Create a test tag
		$tag_result = wp_insert_term( 'Excluded Tag', 'product_tag' );
		$tag_id = $tag_result['term_id'];

		// Set the tag as excluded
		$this->set_excluded_tags( [ $tag_id ] );

		// Create a product with the excluded tag
		$product = $this->create_simple_product([
			'name' => 'Product with Excluded Tag',
			'regular_price' => '19.99',
			'status' => 'publish',
			'catalog_visibility' => 'visible'
		]);

		// Assign product to excluded tag
		wp_set_object_terms( $product->get_id(), [ $tag_id ], 'product_tag' );

		// Product should not be synced due to excluded tag
		$this->assertProductShouldNotSync( $product, 'Products with excluded tags should not be synced' );
	}

	/**
	 * Test product sync explicitly disabled at product level
	 */
	public function test_product_sync_explicitly_disabled(): void {
		$this->enable_facebook_sync();

		// Create a product
		$product = $this->create_simple_product([
			'name' => 'Disabled Sync Product',
			'regular_price' => '19.99',
			'status' => 'publish',
			'catalog_visibility' => 'visible'
		]);

		// Explicitly disable sync for this product
		$this->disable_product_sync( $product );

		// Product should not be synced when explicitly disabled
		$this->assertProductShouldNotSync( $product, 'Products with sync explicitly disabled should not be synced' );
	}

	/**
	 * Test product sync explicitly enabled at product level
	 */
	public function test_product_sync_explicitly_enabled(): void {
		$this->enable_facebook_sync();

		// Create a product
		$product = $this->create_simple_product([
			'name' => 'Enabled Sync Product',
			'regular_price' => '19.99',
			'status' => 'publish',
			'catalog_visibility' => 'visible'
		]);

		// Explicitly enable sync for this product
		$this->enable_product_sync( $product );

		// Product should be synced when explicitly enabled
		$this->assertProductShouldSync( $product, 'Products with sync explicitly enabled should be synced' );
	}

	/**
	 * Test variable product sync validation
	 */
	public function test_variable_product_sync(): void {
		$this->enable_facebook_sync();

		// Create a variable product with variations
		$variable_product = $this->create_variable_product();

		// Variable products themselves should not be synced (only variations)
		// But the validation should pass for the parent product logic
		$validator = facebook_for_woocommerce()->get_product_sync_validator( $variable_product );
		
		// Test that validation passes for variable products
		$this->assertTrue( 
			$validator->passes_all_checks_except_sync_field(), 
			'Variable product should pass validation checks' 
		);
	}

	/**
	 * Test product deletion scenarios
	 */
	public function test_product_should_be_deleted(): void {
		$this->enable_facebook_sync();

		// Create a category
		$category = $this->create_category( 'Test Category' );

		// Create a product in the category
		$product = $this->create_simple_product([
			'name' => 'Test Product',
			'regular_price' => '19.99',
			'status' => 'publish',
			'catalog_visibility' => 'visible'
		]);

		// Assign product to category
		wp_set_object_terms( $product->get_id(), [ $category->term_id ], 'product_cat' );

		// Product should not be deleted initially
		$this->assertFalse( 
			Products::product_should_be_deleted( $product ),
			'Product should not be deleted when in allowed category' 
		);

		// Now exclude the category
		$this->set_excluded_categories( [ $category->term_id ] );

		// Product should now be deleted
		$this->assertProductShouldBeDeleted( $product, 'Product should be deleted when category becomes excluded' );
	}

	/**
	 * Test product sync with multiple categories (mixed excluded/included)
	 */
	public function test_product_sync_with_mixed_categories(): void {
		$this->enable_facebook_sync();

		// Create categories
		$allowed_category = $this->create_category( 'Allowed Category' );
		$excluded_category = $this->create_category( 'Excluded Category' );

		// Set only one category as excluded
		$this->set_excluded_categories( [ $excluded_category->term_id ] );

		// Create a product in both categories
		$product = $this->create_simple_product([
			'name' => 'Product in Mixed Categories',
			'regular_price' => '19.99',
			'status' => 'publish',
			'catalog_visibility' => 'visible'
		]);

		// Assign product to both categories
		wp_set_object_terms( $product->get_id(), [ $allowed_category->term_id, $excluded_category->term_id ], 'product_cat' );

		// Product should not be synced because it's in an excluded category
		$this->assertProductShouldNotSync( $product, 'Products in any excluded category should not be synced' );
	}

	/**
	 * Test product sync with no price
	 */
	public function test_product_sync_with_no_price(): void {
		$this->enable_facebook_sync();

		// Create a product without price
		$product = $this->create_simple_product([
			'name' => 'Product Without Price',
			'regular_price' => '',
			'status' => 'publish',
			'catalog_visibility' => 'visible'
		]);

		// Products without price should still be validated (price validation is separate)
		$this->assertProductShouldSync( $product, 'Products without price should pass basic validation' );
	}

	/**
	 * Test product sync with out of stock status
	 */
	public function test_product_sync_with_out_of_stock(): void {
		$this->enable_facebook_sync();

		// Create an out of stock product
		$product = $this->create_simple_product([
			'name' => 'Out of Stock Product',
			'regular_price' => '19.99',
			'status' => 'publish',
			'catalog_visibility' => 'visible',
			'manage_stock' => true,
			'stock_quantity' => 0,
			'stock_status' => 'outofstock'
		]);

		// Out of stock products should still be synced (stock status sync is separate)
		$this->assertProductShouldSync( $product, 'Out of stock products should be synced' );
	}
} 