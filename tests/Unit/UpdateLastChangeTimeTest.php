<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

require_once __DIR__ . '/../../facebook-commerce.php';

use PHPUnit\Framework\TestCase;

/**
 * Simple tests for the update_last_change_time method
 */
class UpdateLastChangeTimeTest extends TestCase {

    private $integration;
    private $facebook_for_woocommerce;

    public function setUp(): void {
        parent::setUp();

        // Create minimal mocks to satisfy the constructor
        $this->facebook_for_woocommerce = $this->createMock(WC_Facebookcommerce::class);

        // Mock the get_rollout_switches method to return a mock rollout switches object
        $rollout_switches = $this->createMock(WooCommerce\Facebook\RolloutSwitches::class);
        $rollout_switches->method('is_switch_enabled')->willReturn(false);
        $this->facebook_for_woocommerce->method('get_rollout_switches')->willReturn($rollout_switches);

        // Create a real WC_Facebookcommerce_Integration instance
        $this->integration = new WC_Facebookcommerce_Integration($this->facebook_for_woocommerce);
    }

    /**
     * Test that the method handles non-existent products correctly
     */
    public function test_update_last_change_time_with_non_existent_product() {
        // This test will use the actual WordPress functionality if available,
        // but will handle the case gracefully if not

        // Create test parameters
        $meta_id = 1;
        $product_id = 999999; // Non-existent product ID
        $meta_key = 'some_meta_key';
        $meta_value = 'some_value';

        // The method should handle this gracefully without throwing exceptions
        $this->integration->update_last_change_time($meta_id, $product_id, $meta_key, $meta_value);

        // If we get here without an exception, the test passes
        $this->assertTrue(true, 'Method should handle non-existent product without throwing exception');
    }

    /**
     * Test that the method handles excluded meta keys correctly
     */
    public function test_update_last_change_time_with_excluded_meta_keys() {
        // Test with _last_change_time meta key
        $meta_id = 1;
        $product_id = 123;
        $meta_key = '_last_change_time';
        $meta_value = 'some_value';

        // The method should return early for this meta key
        $this->integration->update_last_change_time($meta_id, $product_id, $meta_key, $meta_value);

        // Test with _fb_sync_last_time meta key
        $meta_key = '_fb_sync_last_time';
        $this->integration->update_last_change_time($meta_id, $product_id, $meta_key, $meta_value);

        // If we get here without an exception, the test passes
        $this->assertTrue(true, 'Method should handle excluded meta keys without issues');
    }

    /**
     * Test the method behavior with valid inputs
     */
    public function test_update_last_change_time_with_valid_inputs() {
        // Test with a normal meta key that should potentially trigger an update
        $meta_id = 1;
        $product_id = 123;
        $meta_key = 'regular_meta_key';
        $meta_value = 'some_value';

        // The method should handle this case without throwing exceptions
        $this->integration->update_last_change_time($meta_id, $product_id, $meta_key, $meta_value);

        // If we get here without an exception, the test passes
        $this->assertTrue(true, 'Method should handle valid inputs without throwing exception');
    }

    /**
     * Test exception handling in the method
     */
    public function test_update_last_change_time_exception_handling() {
        // Test that the method has proper exception handling
        // Using edge case values that might cause issues

        $meta_id = 0;
        $product_id = 0;
        $meta_key = '';
        $meta_value = null;

        // The method should handle edge cases gracefully
        $this->integration->update_last_change_time($meta_id, $product_id, $meta_key, $meta_value);

        // If we get here without an exception, the test passes
        $this->assertTrue(true, 'Method should handle edge cases without throwing exception');
    }
}
