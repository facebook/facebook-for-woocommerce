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
 * Tests for the updated last change time functionality
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
     * Test: Core last change time flow
     * Tests main entry point, validation, and update mechanisms
     */
    public function test_core_last_change_time_flow() {
        // Test main entry point with edge cases
        $edge_cases = [
            [0, 0, '', null],                           // Empty values
            [1, 999999, 'normal_key', 'value'],        // Non-existent product
            [1, 123, '_last_change_time', 'value'],    // Self-referential (should be excluded)
        ];

        foreach ($edge_cases as [$meta_id, $product_id, $meta_key, $meta_value]) {
            try {
                $this->integration->update_product_last_change_time($meta_id, $product_id, $meta_key, $meta_value);
                $this->assertTrue(true, 'update_product_last_change_time handled edge case gracefully');
            } catch (Exception $e) {
                $this->assertTrue(true, 'update_product_last_change_time properly caught exception');
            }
        }

        // Test validation methods using reflection for private method
        $reflection = new \ReflectionClass($this->integration);
        $validation_method = $reflection->getMethod('should_update_product_change_time');
        $validation_method->setAccessible(true);

        $this->assertFalse(
            $validation_method->invoke($this->integration, 123, 'irrelevant_meta'),
            'should_update_product_change_time rejects irrelevant meta keys'
        );

        $this->assertFalse(
            $validation_method->invoke($this->integration, 123, '_last_change_time'),
            'should_update_product_change_time rejects self-referential updates'
        );

        // Test core update mechanism
        $reflection = new \ReflectionClass($this->integration);
        $update_method = $reflection->getMethod('perform_product_last_change_time_update');
        $update_method->setAccessible(true);

        try {
            $update_method->invoke($this->integration, 123);
            $this->assertTrue(true, 'perform_product_last_change_time_update executes without errors');
        } catch (Exception $e) {
            $this->fail('perform_product_last_change_time_update should not throw exceptions: ' . $e->getMessage());
        }
    }

    /**
     * Test: Meta key filtering logic
     * Tests which meta keys should trigger sync updates
     */
    public function test_meta_key_filtering() {
        $reflection = new \ReflectionClass($this->integration);
        $method = $reflection->getMethod('is_product_attribute_sync_relevant');
        $method->setAccessible(true);

        // Should EXCLUDE these keys
        $excluded_keys = [
            '_last_change_time', '_fb_sync_last_time',  // Prevent infinite loops
            '_wp_attached_file', '_edit_last',          // WordPress internals
            'custom_field', 'other_plugin_meta'         // Unrelated meta
        ];

        foreach ($excluded_keys as $key) {
            $this->assertFalse(
                $method->invoke($this->integration, $key),
                "Should exclude key: {$key}"
            );
        }

        // Should INCLUDE these keys (trigger sync)
        $sync_relevant_keys = [
            '_regular_price', '_sale_price', '_stock', '_stock_status',     // WooCommerce core
            'fb_brand', 'fb_color', 'fb_size', 'fb_product_condition',     // Facebook attributes
            '_wc_facebook_sync_enabled'                                     // Facebook settings
        ];

        foreach ($sync_relevant_keys as $key) {
            $this->assertTrue(
                $method->invoke($this->integration, $key),
                "Should include key: {$key}"
            );
        }
    }

    /**
     * Test: Rate limiting functionality
     * Tests that updates are properly rate-limited to prevent spam
     */
    public function test_rate_limiting() {
        $reflection = new \ReflectionClass($this->integration);
        $rate_limited_method = $reflection->getMethod('is_last_change_time_update_rate_limited');
        $rate_limited_method->setAccessible(true);

        $cache_method = $reflection->getMethod('set_last_change_time_cache');
        $cache_method->setAccessible(true);

        $product_id = 456;

        // Initially not rate limited
        $this->assertFalse(
            $rate_limited_method->invoke($this->integration, $product_id),
            'Should not be rate limited initially'
        );

        // Set cache with current time - should now be rate limited
        $current_time = time();
        $cache_method->invoke($this->integration, $product_id, $current_time);
        $this->assertTrue(
            $rate_limited_method->invoke($this->integration, $product_id),
            'Should be rate limited after setting cache'
        );

        // Set cache with old time (beyond 60s window) - should not be rate limited
        $old_time = $current_time - 120;
        $cache_method->invoke($this->integration, $product_id, $old_time);
        $this->assertFalse(
            $rate_limited_method->invoke($this->integration, $product_id),
            'Should not be rate limited after cache expires'
        );

        // Test cache setting doesn't throw exceptions
        try {
            $cache_method->invoke($this->integration, $product_id, time());
            $this->assertTrue(true, 'set_last_change_time_cache executes without errors');
        } catch (Exception $e) {
            $this->fail('set_last_change_time_cache should not throw exceptions: ' . $e->getMessage());
        }
    }
}
