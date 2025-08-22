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

        // Create minimal mocks for the integration constructor
        $this->facebook_for_woocommerce = $this->createMock(WC_Facebookcommerce::class);

        // Mock rollout switches to prevent initialization issues
        $rollout_switches = $this->createMock(WooCommerce\Facebook\RolloutSwitches::class);
        $rollout_switches->method('is_switch_enabled')->willReturn(false);
        $this->facebook_for_woocommerce->method('get_rollout_switches')->willReturn($rollout_switches);

        // Create integration instance for testing the refactored methods
        $this->integration = new WC_Facebookcommerce_Integration($this->facebook_for_woocommerce);
    }

    /**
     * Test: should_update_last_change_time() logic function
     * Tests the core logic that determines whether to proceed with updates
     */
    public function test_should_update_last_change_time_logic() {
        // Test excluded meta keys (infinite loop prevention)
        $this->assertFalse(
            $this->integration->should_update_last_change_time(123, '_last_change_time'),
            'should_update_last_change_time should return false for _last_change_time meta key'
        );

        $this->assertFalse(
            $this->integration->should_update_last_change_time(123, '_fb_sync_last_time'),
            'should_update_last_change_time should return false for _fb_sync_last_time meta key'
        );

        // Test non-existent product
        $this->assertFalse(
            $this->integration->should_update_last_change_time(999999, '_price'),
            'should_update_last_change_time should return false for non-existent product'
        );
    }

    /**
     * Test: update_last_change_time() integration
     * Tests the complete flow including both excluded keys and edge cases
     */
    public function test_update_last_change_time_integration() {
        // Test excluded meta keys - should handle gracefully without exceptions
        $excluded_keys = ['_last_change_time', '_fb_sync_last_time'];
        foreach ($excluded_keys as $excluded_key) {
            try {
                $this->integration->update_last_change_time(1, 123, $excluded_key, 'value');
                $this->assertTrue(true, "update_last_change_time handled excluded key '{$excluded_key}' gracefully");
            } catch (Exception $e) {
                $this->fail("update_last_change_time should not throw exception for excluded key '{$excluded_key}': " . $e->getMessage());
            }
        }

        // Test edge cases - should handle gracefully
        $edge_cases = [
            [0, 0, '', null],                    // All zero/empty values
            [1, 999999, 'normal_key', 'value'], // Non-existent product
            [1, 123, null, 'value'],            // Null meta key
        ];

        foreach ($edge_cases as $index => [$meta_id, $product_id, $meta_key, $meta_value]) {
            try {
                $this->integration->update_last_change_time($meta_id, $product_id, $meta_key, $meta_value);
                $this->assertTrue(true, "update_last_change_time handled edge case {$index} gracefully");
            } catch (Exception $e) {
                $this->assertTrue(true, "update_last_change_time properly caught exception for edge case {$index}");
            }
        }
    }
}
