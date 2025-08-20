<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests for the update_last_change_time method
 */
class UpdateLastChangeTimeTest extends TestCase {

	/**
	 * The integration instance.
	 *
	 * @var WC_Facebookcommerce_Integration
	 */
	private $integration;

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Mock the main plugin class
		$facebook_for_woocommerce = $this->createMock( WC_Facebookcommerce::class );

		// Create the integration instance
		$this->integration = new WC_Facebookcommerce_Integration( $facebook_for_woocommerce );
	}

	/**
	 * Test that update_post_meta is not called when meta_key is _last_change_time
	 */
	public function test_no_update_when_meta_key_is_last_change_time() {
		// Mock wc_get_product to return a valid product
		$product = $this->createMock( WC_Product::class );

		// Create a mock function for wc_get_product
		if ( ! function_exists( 'wc_get_product' ) ) {
			function wc_get_product( $product_id ) {
				global $mock_product;
				return $mock_product;
			}
		}
		global $mock_product;
		$mock_product = $product;

		// Mock update_post_meta function to track if it's called
		$update_post_meta_called = false;
		if ( ! function_exists( 'update_post_meta' ) ) {
			function update_post_meta( $post_id, $meta_key, $meta_value ) {
				global $update_post_meta_called;
				$update_post_meta_called = true;
			}
		}
		global $update_post_meta_called;
		$update_post_meta_called = false;

		// No need to mock time() since the method returns early and never calls time()

		// Call the method with _last_change_time as meta_key
		$this->integration->update_last_change_time( 1, 123, '_last_change_time', 'some_value' );

		// Assert that update_post_meta was not called
		$this->assertFalse( $update_post_meta_called, 'update_post_meta should not be called when meta_key is _last_change_time' );
	}

	/**
	 * Test that update_post_meta is not called when meta_key is _fb_sync_last_time
	 */
	public function test_no_update_when_meta_key_is_fb_sync_last_time() {
		// Mock wc_get_product to return a valid product
		$product = $this->createMock( WC_Product::class );

		global $mock_product;
		$mock_product = $product;

		// Reset the mock function state
		global $update_post_meta_called;
		$update_post_meta_called = false;

		// Call the method with _fb_sync_last_time as meta_key
		$this->integration->update_last_change_time( 1, 123, '_fb_sync_last_time', 'some_value' );

		// Assert that update_post_meta was not called
		$this->assertFalse( $update_post_meta_called, 'update_post_meta should not be called when meta_key is _fb_sync_last_time' );
	}

	/**
	 * Test that update_post_meta is not called when product doesn't exist
	 */
	public function test_no_update_when_product_does_not_exist() {
		// Mock wc_get_product to return null (product doesn't exist)
		global $mock_product;
		$mock_product = null;

		// Reset the mock function state
		global $update_post_meta_called;
		$update_post_meta_called = false;

		// Call the method with a non-existent product
		$this->integration->update_last_change_time( 1, 999, 'some_meta_key', 'some_value' );

		// Assert that update_post_meta was not called
		$this->assertFalse( $update_post_meta_called, 'update_post_meta should not be called when product does not exist' );
	}
}
