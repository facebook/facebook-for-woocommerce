<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit;

use WooCommerce\Facebook\Commerce;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Commerce class.
 *
 * @since 3.5.2
 */
class CommerceTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Instance of Commerce class.
	 *
	 * @var Commerce
	 */
	private $commerce;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->commerce = new Commerce();
	}

	/**
	 * Test that the class exists and can be instantiated.
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( 'WooCommerce\Facebook\Commerce' ) );
		$this->assertInstanceOf( Commerce::class, $this->commerce );
	}

	/**
	 * Test the OPTION_GOOGLE_PRODUCT_CATEGORY_ID constant.
	 */
	public function test_option_constant() {
		$this->assertEquals( 'wc_facebook_google_product_category_id', Commerce::OPTION_GOOGLE_PRODUCT_CATEGORY_ID );
	}

	/**
	 * Test get_default_google_product_category_id with no option set.
	 */
	public function test_get_default_google_product_category_id_no_option() {
		delete_option( Commerce::OPTION_GOOGLE_PRODUCT_CATEGORY_ID );
		$result = $this->commerce->get_default_google_product_category_id();
		$this->assertEquals( '', $result );
		$this->assertIsString( $result );
	}

	/**
	 * Test get_default_google_product_category_id with option set.
	 */
	public function test_get_default_google_product_category_id_with_option() {
		update_option( Commerce::OPTION_GOOGLE_PRODUCT_CATEGORY_ID, '123' );
		$result = $this->commerce->get_default_google_product_category_id();
		$this->assertEquals( '123', $result );
	}

	/**
	 * Test get_default_google_product_category_id with various values.
	 */
	public function test_get_default_google_product_category_id_various_values() {
		// Test with numeric string
		update_option( Commerce::OPTION_GOOGLE_PRODUCT_CATEGORY_ID, '456789' );
		$this->assertEquals( '456789', $this->commerce->get_default_google_product_category_id() );

		// Test with empty string
		update_option( Commerce::OPTION_GOOGLE_PRODUCT_CATEGORY_ID, '' );
		$this->assertEquals( '', $this->commerce->get_default_google_product_category_id() );

		// Test with special characters
		update_option( Commerce::OPTION_GOOGLE_PRODUCT_CATEGORY_ID, 'cat_123_special' );
		$this->assertEquals( 'cat_123_special', $this->commerce->get_default_google_product_category_id() );

		// Test with Unicode
		update_option( Commerce::OPTION_GOOGLE_PRODUCT_CATEGORY_ID, 'カテゴリー123' );
		$this->assertEquals( 'カテゴリー123', $this->commerce->get_default_google_product_category_id() );
	}

	/**
	 * Test get_default_google_product_category_id filter.
	 */
	public function test_get_default_google_product_category_id_filter() {
		update_option( Commerce::OPTION_GOOGLE_PRODUCT_CATEGORY_ID, '123' );

		// Add filter to modify the return value
		add_filter( 'wc_facebook_commerce_default_google_product_category_id', function( $category_id, $commerce ) {
			$this->assertEquals( '123', $category_id );
			$this->assertInstanceOf( Commerce::class, $commerce );
			return '999';
		}, 10, 2 );

		$result = $this->commerce->get_default_google_product_category_id();
		$this->assertEquals( '999', $result );

		// Clean up
		remove_all_filters( 'wc_facebook_commerce_default_google_product_category_id' );
	}

	/**
	 * Test get_default_google_product_category_id always returns string.
	 */
	public function test_get_default_google_product_category_id_returns_string() {
		// Test when filter returns non-string value
		add_filter( 'wc_facebook_commerce_default_google_product_category_id', function() {
			return 12345; // Return integer
		} );

		$result = $this->commerce->get_default_google_product_category_id();
		$this->assertIsString( $result );
		$this->assertEquals( '12345', $result );

		// Clean up
		remove_all_filters( 'wc_facebook_commerce_default_google_product_category_id' );

		// Test when filter returns null
		add_filter( 'wc_facebook_commerce_default_google_product_category_id', '__return_null' );
		$result = $this->commerce->get_default_google_product_category_id();
		$this->assertIsString( $result );
		$this->assertEquals( '', $result );

		// Clean up
		remove_all_filters( 'wc_facebook_commerce_default_google_product_category_id' );
	}

	/**
	 * Test update_default_google_product_category_id with string value.
	 */
	public function test_update_default_google_product_category_id_string() {
		$this->commerce->update_default_google_product_category_id( '789' );
		$stored_value = get_option( Commerce::OPTION_GOOGLE_PRODUCT_CATEGORY_ID );
		$this->assertEquals( '789', $stored_value );
	}

	/**
	 * Test update_default_google_product_category_id with empty string.
	 */
	public function test_update_default_google_product_category_id_empty_string() {
		$this->commerce->update_default_google_product_category_id( '' );
		$stored_value = get_option( Commerce::OPTION_GOOGLE_PRODUCT_CATEGORY_ID );
		$this->assertEquals( '', $stored_value );
	}

	/**
	 * Test update_default_google_product_category_id with non-string values.
	 */
	public function test_update_default_google_product_category_id_non_string() {
		// Test with integer
		$this->commerce->update_default_google_product_category_id( 123 );
		$stored_value = get_option( Commerce::OPTION_GOOGLE_PRODUCT_CATEGORY_ID );
		$this->assertEquals( '', $stored_value );

		// Test with null
		$this->commerce->update_default_google_product_category_id( null );
		$stored_value = get_option( Commerce::OPTION_GOOGLE_PRODUCT_CATEGORY_ID );
		$this->assertEquals( '', $stored_value );

		// Test with array
		$this->commerce->update_default_google_product_category_id( array( 'test' ) );
		$stored_value = get_option( Commerce::OPTION_GOOGLE_PRODUCT_CATEGORY_ID );
		$this->assertEquals( '', $stored_value );

		// Test with boolean
		$this->commerce->update_default_google_product_category_id( true );
		$stored_value = get_option( Commerce::OPTION_GOOGLE_PRODUCT_CATEGORY_ID );
		$this->assertEquals( '', $stored_value );
	}

	/**
	 * Test update_default_google_product_category_id with special characters.
	 */
	public function test_update_default_google_product_category_id_special_characters() {
		// Test with special characters
		$special_id = 'cat_!@#$%^&*()_123';
		$this->commerce->update_default_google_product_category_id( $special_id );
		$stored_value = get_option( Commerce::OPTION_GOOGLE_PRODUCT_CATEGORY_ID );
		$this->assertEquals( $special_id, $stored_value );

		// Test with Unicode
		$unicode_id = 'カテゴリー_123_测试';
		$this->commerce->update_default_google_product_category_id( $unicode_id );
		$stored_value = get_option( Commerce::OPTION_GOOGLE_PRODUCT_CATEGORY_ID );
		$this->assertEquals( $unicode_id, $stored_value );
	}

	/**
	 * Test that update and get methods work together correctly.
	 */
	public function test_update_and_get_consistency() {
		// Set a value
		$test_id = 'test_category_999';
		$this->commerce->update_default_google_product_category_id( $test_id );
		
		// Get the value back
		$retrieved_id = $this->commerce->get_default_google_product_category_id();
		$this->assertEquals( $test_id, $retrieved_id );

		// Update to empty
		$this->commerce->update_default_google_product_category_id( '' );
		$retrieved_id = $this->commerce->get_default_google_product_category_id();
		$this->assertEquals( '', $retrieved_id );
	}

	/**
	 * Test multiple sequential updates.
	 */
	public function test_multiple_updates() {
		$values = array( '111', '222', '333', '', '444' );
		
		foreach ( $values as $value ) {
			$this->commerce->update_default_google_product_category_id( $value );
			$this->assertEquals( $value, $this->commerce->get_default_google_product_category_id() );
		}
	}
} 