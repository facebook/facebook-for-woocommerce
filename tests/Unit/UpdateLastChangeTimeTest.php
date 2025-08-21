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
	 * Track calls to update_post_meta for verification
	 *
	 * @var array
	 */
	private $update_post_meta_calls = [];

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Mock the main plugin class
		$facebook_for_woocommerce = $this->createMock( WC_Facebookcommerce::class );

		// Create the integration instance
		$this->integration = new WC_Facebookcommerce_Integration( $facebook_for_woocommerce );

		// Reset call tracking
		$this->update_post_meta_calls = [];

		// Set up WordPress function mocks if they don't exist
		$this->setupWordPressFunctions();
	}

	/**
	 * Set up WordPress function mocks if they don't exist
	 */
	private function setupWordPressFunctions() {
		if ( ! function_exists( 'wc_get_product' ) ) {
			function wc_get_product( $product_id ) {
				return UpdateLastChangeTimeTest::getMockProduct( $product_id );
			}
		}

		if ( ! function_exists( 'update_post_meta' ) ) {
			function update_post_meta( $post_id, $meta_key, $meta_value ) {
				return UpdateLastChangeTimeTest::trackUpdatePostMeta( $post_id, $meta_key, $meta_value );
			}
		}

		if ( ! function_exists( 'time' ) ) {
			function time() {
				return 1234567890; // Fixed timestamp for testing
			}
		}
	}

	/**
	 * Static method to get mock product (called from global function)
	 *
	 * @param int $product_id
	 * @return WC_Product|null
	 */
	public static function getMockProduct( $product_id ) {
		$instance = $GLOBALS['current_test_instance'] ?? null;
		if ( ! $instance ) {
			return null;
		}
		return $instance->mock_product ?? null;
	}

	/**
	 * Static method to track update_post_meta calls (called from global function)
	 *
	 * @param int    $post_id
	 * @param string $meta_key
	 * @param mixed  $meta_value
	 * @return bool
	 */
	public static function trackUpdatePostMeta( $post_id, $meta_key, $meta_value ) {
		$instance = $GLOBALS['current_test_instance'] ?? null;
		if ( $instance ) {
			$instance->update_post_meta_calls[] = [
				'post_id'    => $post_id,
				'meta_key'   => $meta_key,
				'meta_value' => $meta_value,
			];
		}
		return true;
	}

	/**
	 * Helper to set up test state
	 *
	 * @param WC_Product|null $product
	 */
	private function setMockProduct( $product ) {
		$this->mock_product = $product;
		$GLOBALS['current_test_instance'] = $this;
	}

	/**
	 * Test that update_post_meta is not called when meta_key is _last_change_time
	 */
	public function test_no_update_when_meta_key_is_last_change_time() {
		// Set up a valid product
		$product = $this->createMock( WC_Product::class );
		$this->setMockProduct( $product );

		// Call the method with _last_change_time as meta_key
		$this->integration->update_last_change_time( 1, 123, '_last_change_time', 'some_value' );

		// Assert that update_post_meta was not called
		$this->assertEmpty( $this->update_post_meta_calls, 'update_post_meta should not be called when meta_key is _last_change_time' );
	}

	/**
	 * Test that update_post_meta is not called when meta_key is _fb_sync_last_time
	 */
	public function test_no_update_when_meta_key_is_fb_sync_last_time() {
		// Set up a valid product
		$product = $this->createMock( WC_Product::class );
		$this->setMockProduct( $product );

		// Call the method with _fb_sync_last_time as meta_key
		$this->integration->update_last_change_time( 1, 123, '_fb_sync_last_time', 'some_value' );

		// Assert that update_post_meta was not called
		$this->assertEmpty( $this->update_post_meta_calls, 'update_post_meta should not be called when meta_key is _fb_sync_last_time' );
	}

	/**
	 * Test that update_post_meta is not called when product doesn't exist
	 */
	public function test_no_update_when_product_does_not_exist() {
		// Set up null product (product doesn't exist)
		$this->setMockProduct( null );

		// Call the method with a non-existent product
		$this->integration->update_last_change_time( 1, 999, 'some_meta_key', 'some_value' );

		// Assert that update_post_meta was not called
		$this->assertEmpty( $this->update_post_meta_calls, 'update_post_meta should not be called when product does not exist' );
	}

	/**
	 * Test that update_post_meta is called when conditions are met
	 */
	public function test_update_called_when_conditions_met() {
		// Set up a valid product
		$product = $this->createMock( WC_Product::class );
		$this->setMockProduct( $product );

		// Call the method with valid meta_key
		$this->integration->update_last_change_time( 1, 123, 'some_other_meta_key', 'some_value' );

		// Assert that update_post_meta was called once
		$this->assertCount( 1, $this->update_post_meta_calls, 'update_post_meta should be called once when conditions are met' );

		// Verify the call parameters
		$call = $this->update_post_meta_calls[0];
		$this->assertEquals( 123, $call['post_id'] );
		$this->assertEquals( '_last_change_time', $call['meta_key'] );
		$this->assertEquals( 1234567890, $call['meta_value'] );
	}

	/**
	 * Clean up after each test
	 */
	public function tearDown(): void {
		unset( $GLOBALS['current_test_instance'] );
		$this->update_post_meta_calls = [];
		parent::tearDown();
	}
}
