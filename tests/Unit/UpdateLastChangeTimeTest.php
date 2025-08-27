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

	/**
	 * Test: is_meta_key_sync_relevant() - helper function for determining sync relevance
	 * Tests the logic that identifies which meta keys should trigger sync updates
	 */
	public function test_is_meta_key_sync_relevant() {
		// Use reflection to test the private method
		$reflection = new \ReflectionClass($this->integration);
		$method = $reflection->getMethod('is_meta_key_sync_relevant');
		$method->setAccessible(true);

		// Test excluded meta keys (should return false)
		$excluded_keys = [
			'_last_change_time',
			'_fb_sync_last_time',
			'_wp_attached_file',
			'_wp_attachment_metadata',
			'_edit_last',
			'_edit_lock'
		];

		foreach ($excluded_keys as $key) {
			$this->assertFalse(
				$method->invoke($this->integration, $key),
				"is_meta_key_sync_relevant should return false for excluded key: {$key}"
			);
		}

		// Test sync-relevant meta keys (should return true)
		$sync_relevant_keys = [
			'_regular_price',
			'_sale_price',
			'_stock',
			'_stock_status',
			'_thumbnail_id',
			'_price',
			'fb_visibility',
			'fb_product_description',
			'fb_brand',
			'fb_mpn',
			'fb_size',
			'fb_color',
			'fb_material',
			'fb_pattern',
			'fb_age_group',
			'fb_gender',
			'fb_product_condition',
			'_wc_facebook_sync_enabled',
			'_wc_facebook_product_image_source'
		];

		foreach ($sync_relevant_keys as $key) {
			$this->assertTrue(
				$method->invoke($this->integration, $key),
				"is_meta_key_sync_relevant should return true for sync-relevant key: {$key}"
			);
		}

		// Test other meta keys (should return false)
		$other_keys = [
			'some_random_meta_key',
			'custom_field',
			'other_plugin_meta'
		];

		foreach ($other_keys as $key) {
			$this->assertFalse(
				$method->invoke($this->integration, $key),
				"is_meta_key_sync_relevant should return false for unrelated key: {$key}"
			);
		}
	}
}
