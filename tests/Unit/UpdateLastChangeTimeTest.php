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
 * Simple tests for the update_last_change_time method
 */
class UpdateLastChangeTimeTest extends TestCase {

    private $integration;
    private $update_post_meta_called;
    private $mock_product;

    public function setUp(): void {
        parent::setUp();

        // Mock the integration class
        $this->integration = $this->getMockBuilder('WC_Facebookcommerce_Integration')
            ->disableOriginalConstructor()
            ->onlyMethods(['update_last_change_time'])
            ->getMock();

        // Reset tracking
        $this->update_post_meta_called = false;

        // Mock WordPress functions
        $this->mockWordPressFunctions();
    }

    private function mockWordPressFunctions() {
        if (!function_exists('wc_get_product')) {
            function wc_get_product($product_id) {
                global $test_mock_product;
                return $test_mock_product;
            }
        }

        if (!function_exists('update_post_meta')) {
            function update_post_meta($post_id, $meta_key, $meta_value) {
                global $test_update_post_meta_called;
                $test_update_post_meta_called = true;
                return true;
            }
        }

        if (!function_exists('time')) {
            function time() {
                return 1234567890;
            }
        }
    }

    /**
     * Test that update_post_meta is called when conditions are met
     */
    public function test_update_post_meta_called_when_conditions_met() {
        global $test_mock_product, $test_update_post_meta_called;

        // Set up a valid product
        $test_mock_product = $this->createMock('WC_Product');
        $test_update_post_meta_called = false;

        // Create real integration instance for testing
        $facebook_mock = $this->createMock('WC_Facebookcommerce');
        $integration = new WC_Facebookcommerce_Integration($facebook_mock);

        // Call the method with valid parameters
        $integration->update_last_change_time(1, 123, 'some_meta_key', 'some_value');

        // Assert that update_post_meta was called
        $this->assertTrue($test_update_post_meta_called, 'update_post_meta should be called when conditions are met');
    }

    /**
     * Test that update_post_meta is not called when product doesn't exist
     */
    public function test_update_post_meta_not_called_when_product_does_not_exist() {
        global $test_mock_product, $test_update_post_meta_called;

        // Set up null product (product doesn't exist)
        $test_mock_product = null;
        $test_update_post_meta_called = false;

        // Create real integration instance for testing
        $facebook_mock = $this->createMock('WC_Facebookcommerce');
        $integration = new WC_Facebookcommerce_Integration($facebook_mock);

        // Call the method with non-existent product
        $integration->update_last_change_time(1, 999, 'some_meta_key', 'some_value');

        // Assert that update_post_meta was not called
        $this->assertFalse($test_update_post_meta_called, 'update_post_meta should not be called when product does not exist');
    }

    /**
     * Test that update_post_meta is not called when meta_key is _fb_sync_last_time
     */
    public function test_update_post_meta_not_called_when_meta_key_is_fb_sync_last_time() {
        global $test_mock_product, $test_update_post_meta_called;

        // Set up a valid product
        $test_mock_product = $this->createMock('WC_Product');
        $test_update_post_meta_called = false;

        // Create real integration instance for testing
        $facebook_mock = $this->createMock('WC_Facebookcommerce');
        $integration = new WC_Facebookcommerce_Integration($facebook_mock);

        // Call the method with _fb_sync_last_time as meta_key
        $integration->update_last_change_time(1, 123, '_fb_sync_last_time', 'some_value');

        // Assert that update_post_meta was not called
        $this->assertFalse($test_update_post_meta_called, 'update_post_meta should not be called when meta_key is _fb_sync_last_time');
    }

    /**
     * Test that update_post_meta is not called when meta_key is _last_change_time
     */
    public function test_update_post_meta_not_called_when_meta_key_is_last_change_time() {
        global $test_mock_product, $test_update_post_meta_called;

        // Set up a valid product
        $test_mock_product = $this->createMock('WC_Product');
        $test_update_post_meta_called = false;

        // Create real integration instance for testing
        $facebook_mock = $this->createMock('WC_Facebookcommerce');
        $integration = new WC_Facebookcommerce_Integration($facebook_mock);

        // Call the method with _last_change_time as meta_key
        $integration->update_last_change_time(1, 123, '_last_change_time', 'some_value');

        // Assert that update_post_meta was not called
        $this->assertFalse($test_update_post_meta_called, 'update_post_meta should not be called when meta_key is _last_change_time');
    }

    public function tearDown(): void {
        global $test_mock_product, $test_update_post_meta_called;
        $test_mock_product = null;
        $test_update_post_meta_called = false;
        parent::tearDown();
    }
}
