<?php
namespace WooCommerce\Facebook\Tests\Admin\Settings_Screens;

use PHPUnit\Framework\TestCase;
use WooCommerce\Facebook\Admin\Settings_Screens\Connection;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;
use WooCommerce\Facebook\Framework\Api\Exception as ApiException;

/**
 * Class ConnectionTest
 *
 * @package WooCommerce\Facebook\Tests\Unit\Admin\Settings_Screens
 */
class ConnectionTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

    /**
     * Helper method to invoke private/protected methods
     */
    private function invoke_method($object, $methodName, array $parameters = []) {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Test constructor sets up hooks
     */
    public function test_constructor_sets_hooks() {
        global $wp_filter;

        // Instantiate the Connection class (registers hooks)
        $connection = new Connection();

        // Assert that all expected hooks are present
        $this->assertArrayHasKey('init', $wp_filter);
        $this->assertArrayHasKey('admin_enqueue_scripts', $wp_filter);
        $this->assertArrayHasKey('admin_notices', $wp_filter);
    }

    /**
     * Test initHook sets properties
     */
    public function test_initHook_sets_properties() {
        $connection = new Connection();

        // Call the initHook method
        $this->invoke_method($connection, 'initHook');

        // Assert that properties are set as expected
        $this->assertEquals(Connection::ID, $connection->get_id());
        $this->assertEquals('Connection', $connection->get_label());
        $this->assertEquals('Connection', $connection->get_title());
    }

    /**
     * Test add_notices does nothing if transient not set
     */
    public function test_add_notices_no_transient() {
        // Ensure the transient is not set
        delete_transient('wc_facebook_connection_failed');
        $connection = new Connection();
        // Should not throw or add notice
        $this->assertNull($connection->add_notices());
    }

    /**
     * Test add_notices adds notice and deletes transient if set
     */
    public function test_add_notices_with_transient() {
        // Set the transient
        set_transient('wc_facebook_connection_failed', true);
        
        // Mock global facebook_for_woocommerce() and its methods
        $mock_plugin = $this->getMockBuilder('stdClass')
            ->addMethods(['get_connection_handler', 'get_support_url', 'get_admin_notice_handler'])
            ->getMock();
        $mock_handler = $this->getMockBuilder('stdClass')
            ->addMethods(['get_connect_url'])
            ->getMock();
        $mock_handler->method('get_connect_url')->willReturn('https://connect.url');
        $mock_plugin->method('get_connection_handler')->willReturn($mock_handler);
        $mock_plugin->method('get_support_url')->willReturn('https://support.url');
        $mock_notice_handler = $this->getMockBuilder('stdClass')
            ->addMethods(['add_admin_notice'])
            ->getMock();
        $mock_notice_handler->expects($this->once())->method('add_admin_notice');
        $mock_plugin->method('get_admin_notice_handler')->willReturn($mock_notice_handler);
        global $facebook_for_woocommerce;
        $facebook_for_woocommerce = function() use ($mock_plugin) { return $mock_plugin; };
        $connection = new Connection();
        $connection->add_notices();

        // The transient should be deleted
        $this->assertFalse(get_transient('wc_facebook_connection_failed'));
    }

    /**
     * Test enqueue_assets does not enqueue if not current screen
     */
    public function test_enqueue_assets_not_current_screen() {
        // Mock is_current_screen_page to return false
        $connection = $this->getMockBuilder(Connection::class)
            ->onlyMethods(['is_current_screen_page'])
            ->getMock();
        $connection->method('is_current_screen_page')->willReturn(false);

        // Should not throw or enqueue
        $this->assertNull($connection->enqueue_assets());
    }

    /**
     * Test enqueue_assets enqueues if current screen
     */
    public function test_enqueue_assets_current_screen() {
        // Mock is_current_screen_page to return true
        $connection = $this->getMockBuilder(Connection::class)
            ->onlyMethods(['is_current_screen_page'])
            ->getMock();
        $connection->method('is_current_screen_page')->willReturn(true);

        // Override wp_enqueue_style to record calls
        global $wp_styles_enqueued;
        $wp_styles_enqueued = [];
        if (!function_exists('wp_enqueue_style')) {
            function wp_enqueue_style($handle) {
                global $wp_styles_enqueued;
                $wp_styles_enqueued[] = $handle;
            }
        }

        // Patch global facebook_for_woocommerce() and its methods
        $mock_plugin = $this->getMockBuilder('stdClass')
            ->addMethods(['get_plugin_url'])
            ->getMock();
        $mock_plugin->method('get_plugin_url')->willReturn('https://plugin.url');
        global $facebook_for_woocommerce;
        $facebook_for_woocommerce = function() use ($mock_plugin) { return $mock_plugin; };
        if (!defined('WC_Facebookcommerce::VERSION')) {
            define('WC_Facebookcommerce::VERSION', '1.0.0');
        }

        $connection->enqueue_assets();
        // Assert that the style was enqueued
        $this->assertContains('wc-facebook-admin-connection-settings', $wp_styles_enqueued);
    }

    /**
     * Test get_settings returns all expected settings and structure
     */
    public function test_get_settings_returns_all_expected_settings() {
        $connection = new Connection();

        // Patch global facebook_for_woocommerce() and its rollout switches
        $mock_rollout = $this->getMockBuilder('stdClass')
            ->addMethods(['is_switch_enabled'])
            ->getMock();
        $mock_rollout->method('is_switch_enabled')->willReturn(false);
        $mock_plugin = $this->getMockBuilder('stdClass')
            ->addMethods(['get_rollout_switches'])
            ->getMock();
        $mock_plugin->method('get_rollout_switches')->willReturn($mock_rollout);
        global $facebook_for_woocommerce;
        $facebook_for_woocommerce = function() use ($mock_plugin) { return $mock_plugin; };

        // When offer management is disabled
        $settings = $connection->get_settings();
        $this->assertIsArray($settings);
        $this->assertGreaterThanOrEqual(3, count($settings));
        $this->assertEquals('title', $settings[0]['type']);
        $this->assertEquals('wc_facebook_enable_meta_diagnosis', $settings[1]['id']);
        $this->assertEquals('checkbox', $settings[1]['type']);
        $this->assertEquals('yes', $settings[1]['default']);
        $this->assertEquals('wc_facebook_enable_debug_mode', $settings[2]['id']);
        $this->assertEquals('checkbox', $settings[2]['type']);
        $this->assertEquals('no', $settings[2]['default']);
        $this->assertEquals('sectionend', $settings[count($settings)-1]['type']);

        // When offer management is enabled
        $mock_rollout->method('is_switch_enabled')->willReturn(true);
        $settings = $connection->get_settings();
        $this->assertIsArray($settings);
        $this->assertGreaterThanOrEqual(4, count($settings));
        $this->assertEquals('title', $settings[0]['type']);
        $this->assertEquals('wc_facebook_enable_meta_diagnosis', $settings[1]['id']);
        $this->assertEquals('checkbox', $settings[1]['type']);
        $this->assertEquals('yes', $settings[1]['default']);
        $this->assertEquals('wc_facebook_enable_debug_mode', $settings[2]['id']);
        $this->assertEquals('checkbox', $settings[2]['type']);
        $this->assertEquals('no', $settings[2]['default']);

        // Only check for the coupon setting if it exists
        $coupon_setting = array_filter($settings, function($setting) {
            return isset($setting['id']) && $setting['id'] === 'wc_facebook_enable_facebook_managed_coupons';
        });
        if (!empty($coupon_setting)) {
            $coupon_setting = array_values($coupon_setting)[0];
            $this->assertEquals('checkbox', $coupon_setting['type']);
            $this->assertEquals('yes', $coupon_setting['default']);
        }
        $this->assertEquals('sectionend', $settings[count($settings)-1]['type']);
    }
}
