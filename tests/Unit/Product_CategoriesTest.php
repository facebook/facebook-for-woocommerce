<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit;

use WooCommerce\Facebook\Product_Categories;
use WooCommerce\Facebook\Products;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Product_Categories class.
 *
 * @since 3.5.2
 */
class Product_CategoriesTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test category ID for testing.
	 *
	 * @var int
	 */
	private $test_category_id;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Create a test category
		$this->test_category_id = wp_create_category( 'Test Category' );
	}

	/**
	 * Test that the class exists.
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( 'WooCommerce\Facebook\Product_Categories' ) );
	}

	/**
	 * Test get_google_product_category_id with no meta set.
	 */
	public function test_get_google_product_category_id_no_meta() {
		$result = Product_Categories::get_google_product_category_id( $this->test_category_id );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test get_google_product_category_id with meta set.
	 */
	public function test_get_google_product_category_id_with_meta() {
		// Set the meta
		update_term_meta( $this->test_category_id, Products::GOOGLE_PRODUCT_CATEGORY_META_KEY, '123' );
		
		// Get the meta
		$result = Product_Categories::get_google_product_category_id( $this->test_category_id );
		$this->assertEquals( '123', $result );
	}

	/**
	 * Test get_google_product_category_id with various values.
	 */
	public function test_get_google_product_category_id_various_values() {
		// Test with numeric string
		update_term_meta( $this->test_category_id, Products::GOOGLE_PRODUCT_CATEGORY_META_KEY, '456789' );
		$this->assertEquals( '456789', Product_Categories::get_google_product_category_id( $this->test_category_id ) );

		// Test with empty string
		update_term_meta( $this->test_category_id, Products::GOOGLE_PRODUCT_CATEGORY_META_KEY, '' );
		$this->assertEquals( '', Product_Categories::get_google_product_category_id( $this->test_category_id ) );

		// Test with special characters
		update_term_meta( $this->test_category_id, Products::GOOGLE_PRODUCT_CATEGORY_META_KEY, 'cat_123_special' );
		$this->assertEquals( 'cat_123_special', Product_Categories::get_google_product_category_id( $this->test_category_id ) );

		// Test with Unicode
		update_term_meta( $this->test_category_id, Products::GOOGLE_PRODUCT_CATEGORY_META_KEY, 'カテゴリー123' );
		$this->assertEquals( 'カテゴリー123', Product_Categories::get_google_product_category_id( $this->test_category_id ) );
	}

	/**
	 * Test get_google_product_category_id with invalid category ID.
	 */
	public function test_get_google_product_category_id_invalid_id() {
		// Test with non-existent category ID
		$result = Product_Categories::get_google_product_category_id( 999999 );
		$this->assertEquals( '', $result );

		// Test with zero
		$result = Product_Categories::get_google_product_category_id( 0 );
		$this->assertEquals( '', $result );

		// Test with negative number
		$result = Product_Categories::get_google_product_category_id( -1 );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test update_google_product_category_id with valid string.
	 */
	public function test_update_google_product_category_id_valid_string() {
		Product_Categories::update_google_product_category_id( $this->test_category_id, '789' );
		$stored_value = get_term_meta( $this->test_category_id, Products::GOOGLE_PRODUCT_CATEGORY_META_KEY, true );
		$this->assertEquals( '789', $stored_value );
	}

	/**
	 * Test update_google_product_category_id with empty string.
	 */
	public function test_update_google_product_category_id_empty_string() {
		Product_Categories::update_google_product_category_id( $this->test_category_id, '' );
		$stored_value = get_term_meta( $this->test_category_id, Products::GOOGLE_PRODUCT_CATEGORY_META_KEY, true );
		$this->assertEquals( '', $stored_value );
	}

	/**
	 * Test update_google_product_category_id with special characters.
	 */
	public function test_update_google_product_category_id_special_characters() {
		// Test with special characters
		$special_id = 'cat_!@#$%^&*()_123';
		Product_Categories::update_google_product_category_id( $this->test_category_id, $special_id );
		$stored_value = get_term_meta( $this->test_category_id, Products::GOOGLE_PRODUCT_CATEGORY_META_KEY, true );
		$this->assertEquals( $special_id, $stored_value );

		// Test with Unicode
		$unicode_id = 'カテゴリー_123_测试';
		Product_Categories::update_google_product_category_id( $this->test_category_id, $unicode_id );
		$stored_value = get_term_meta( $this->test_category_id, Products::GOOGLE_PRODUCT_CATEGORY_META_KEY, true );
		$this->assertEquals( $unicode_id, $stored_value );
	}

	/**
	 * Test update_google_product_category_id with invalid category ID.
	 */
	public function test_update_google_product_category_id_invalid_id() {
		// Update with invalid ID should not throw error
		Product_Categories::update_google_product_category_id( 999999, 'test_value' );
		
		// Verify it doesn't affect our test category
		$stored_value = get_term_meta( $this->test_category_id, Products::GOOGLE_PRODUCT_CATEGORY_META_KEY, true );
		$this->assertEquals( '', $stored_value );
	}

	/**
	 * Test that update and get methods work together correctly.
	 */
	public function test_update_and_get_consistency() {
		// Set a value
		$test_id = 'test_category_999';
		Product_Categories::update_google_product_category_id( $this->test_category_id, $test_id );
		
		// Get the value back
		$retrieved_id = Product_Categories::get_google_product_category_id( $this->test_category_id );
		$this->assertEquals( $test_id, $retrieved_id );

		// Update to empty
		Product_Categories::update_google_product_category_id( $this->test_category_id, '' );
		$retrieved_id = Product_Categories::get_google_product_category_id( $this->test_category_id );
		$this->assertEquals( '', $retrieved_id );
	}

	/**
	 * Test multiple sequential updates.
	 */
	public function test_multiple_updates() {
		$values = array( '111', '222', '333', '', '444' );
		
		foreach ( $values as $value ) {
			Product_Categories::update_google_product_category_id( $this->test_category_id, $value );
			$this->assertEquals( $value, Product_Categories::get_google_product_category_id( $this->test_category_id ) );
		}
	}

	/**
	 * Test with very long category ID string.
	 */
	public function test_long_category_id_string() {
		$long_id = str_repeat( 'a', 1000 );
		Product_Categories::update_google_product_category_id( $this->test_category_id, $long_id );
		$retrieved_id = Product_Categories::get_google_product_category_id( $this->test_category_id );
		$this->assertEquals( $long_id, $retrieved_id );
	}

	/**
	 * Test with numeric values.
	 */
	public function test_numeric_values() {
		// Integer value (will be converted to string by WordPress)
		Product_Categories::update_google_product_category_id( $this->test_category_id, 12345 );
		$retrieved_id = Product_Categories::get_google_product_category_id( $this->test_category_id );
		$this->assertEquals( '12345', $retrieved_id );

		// Float value (will be converted to string by WordPress)
		Product_Categories::update_google_product_category_id( $this->test_category_id, 123.45 );
		$retrieved_id = Product_Categories::get_google_product_category_id( $this->test_category_id );
		$this->assertEquals( '123.45', $retrieved_id );
	}

	/**
	 * Test meta key constant is correct.
	 */
	public function test_meta_key_constant() {
		// Verify we're using the correct meta key from Products class
		$this->assertEquals( '_wc_facebook_google_product_category_id', Products::GOOGLE_PRODUCT_CATEGORY_META_KEY );
	}

	/**
	 * Clean up after tests.
	 */
	public function tearDown(): void {
		// Delete the test category
		if ( $this->test_category_id ) {
			wp_delete_category( $this->test_category_id );
		}
		
		parent::tearDown();
	}
} 