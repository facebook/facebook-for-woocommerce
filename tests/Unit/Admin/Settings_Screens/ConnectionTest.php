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
        // Simulate no transient set
        \WP_Mock::userFunction('get_transient', ['return' => false]);
        $connection = new Connection();

        // Should not throw or add notice
        $this->assertNull($connection->add_notices());
    }

    /**
     * Test add_notices adds notice and deletes transient if set
     */
    public function test_add_notices_with_transient() {
        // Simulate transient is set
        \WP_Mock::userFunction('get_transient', ['return' => true]);
        \WP_Mock::userFunction('delete_transient', ['return' => true]);

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

        // Patch global function
        global $facebook_for_woocommerce;
        $facebook_for_woocommerce = function() use ($mock_plugin) { return $mock_plugin; };

        $connection = new Connection();
        $connection->add_notices();
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

        // Expect wp_enqueue_style to be called
        \WP_Mock::userFunction('wp_enqueue_style', ['times' => 1]);

        // Patch global facebook_for_woocommerce() and its methods
        $mock_plugin = $this->getMockBuilder('stdClass')
            ->addMethods(['get_plugin_url'])
            ->getMock();
        $mock_plugin->method('get_plugin_url')->willReturn('https://plugin.url');
        global $facebook_for_woocommerce;
        $facebook_for_woocommerce = function() use ($mock_plugin) { return $mock_plugin; };
        define('WC_Facebookcommerce::VERSION', '1.0.0');

        $connection->enqueue_assets();
    }

    /**
     * Test render when not connected (should only render CTA box)
     */
    public function test_render_not_connected() {
        // Patch global facebook_for_woocommerce() and its methods
        $mock_plugin = $this->getMockBuilder('stdClass')
            ->addMethods(['get_connection_handler'])
            ->getMock();
        $mock_handler = $this->getMockBuilder('stdClass')
            ->addMethods(['is_connected'])
            ->getMock();
        $mock_handler->method('is_connected')->willReturn(false);
        $mock_plugin->method('get_connection_handler')->willReturn($mock_handler);
        global $facebook_for_woocommerce;
        $facebook_for_woocommerce = function() use ($mock_plugin) { return $mock_plugin; };

        $connection = new Connection();
        ob_start();
        $connection->render();
        $output = ob_get_clean();

        // Assert the CTA box is present
        $this->assertStringContainsString('wc-facebook-connection-box', $output);
    }

    /**
     * Test render when connected (should render CTA and static items)
     */
    public function test_render_connected() {
        // Patch global facebook_for_woocommerce() and its methods BEFORE instantiating Connection
        $mock_plugin = $this->getMockBuilder('stdClass')
            ->addMethods([
                'get_connection_handler', 'get_integration', 'get_api'
            ])
            ->getMock();
        $mock_handler = $this->getMockBuilder('stdClass')
            ->addMethods(['is_connected', 'get_business_manager_id', 'get_ad_account_id', 'get_instagram_business_id', 'get_commerce_merchant_settings_id'])
            ->getMock();
        $mock_handler->method('is_connected')->willReturn(true);
        $mock_handler->method('get_business_manager_id')->willReturn('bm_id');
        $mock_handler->method('get_ad_account_id')->willReturn('ad_id');
        $mock_handler->method('get_instagram_business_id')->willReturn('ig_id');
        $mock_handler->method('get_commerce_merchant_settings_id')->willReturn('cms_id');
        $mock_integration = $this->getMockBuilder('stdClass')
            ->addMethods(['get_facebook_page_id', 'get_facebook_pixel_id', 'get_product_catalog_id'])
            ->getMock();
        $mock_integration->method('get_facebook_page_id')->willReturn('page_id');
        $mock_integration->method('get_facebook_pixel_id')->willReturn('pixel_id');
        $mock_integration->method('get_product_catalog_id')->willReturn('catalog_id');
        $mock_api = $this->getMockBuilder('stdClass')
            ->addMethods(['get_catalog'])
            ->getMock();
        $mock_api->method('get_catalog')->willReturn((object)['name' => 'Catalog Name']);
        $mock_plugin->method('get_connection_handler')->willReturn($mock_handler);
        $mock_plugin->method('get_integration')->willReturn($mock_integration);
        $mock_plugin->method('get_api')->willReturn($mock_api);
        global $facebook_for_woocommerce;
        $facebook_for_woocommerce = function() use ($mock_plugin) { return $mock_plugin; };

        $connection = new Connection(); // Instantiate after patching global
        ob_start();
        $connection->render();
        $output = ob_get_clean();

        // Assert all expected static items and CTA are present
        $this->assertStringContainsString('Catalog Name', $output);
        $this->assertStringContainsString('wc-facebook-connection-box', $output);
        $this->assertStringContainsString('bm_id', $output);
        $this->assertStringContainsString('ad_id', $output);
        $this->assertStringContainsString('ig_id', $output);
        $this->assertStringContainsString('cms_id', $output);
    }

    /**
     * Test render handles API exception gracefully
     */
    public function test_render_catalog_api_exception() {
        // Patch global facebook_for_woocommerce() and its methods BEFORE instantiating Connection
        $mock_plugin = $this->getMockBuilder('stdClass')
            ->addMethods([
                'get_connection_handler', 'get_integration', 'get_api', 'log'
            ])
            ->getMock();
        $mock_handler = $this->getMockBuilder('stdClass')
            ->addMethods(['is_connected', 'get_business_manager_id', 'get_ad_account_id', 'get_instagram_business_id', 'get_commerce_merchant_settings_id'])
            ->getMock();
        $mock_handler->method('is_connected')->willReturn(true);
        $mock_handler->method('get_business_manager_id')->willReturn('bm_id');
        $mock_handler->method('get_ad_account_id')->willReturn('ad_id');
        $mock_handler->method('get_instagram_business_id')->willReturn('ig_id');
        $mock_handler->method('get_commerce_merchant_settings_id')->willReturn('cms_id');
        $mock_integration = $this->getMockBuilder('stdClass')
            ->addMethods(['get_facebook_page_id', 'get_facebook_pixel_id', 'get_product_catalog_id'])
            ->getMock();
        $mock_integration->method('get_facebook_page_id')->willReturn('page_id');
        $mock_integration->method('get_facebook_pixel_id')->willReturn('pixel_id');
        $mock_integration->method('get_product_catalog_id')->willReturn('catalog_id');
        $mock_api = $this->getMockBuilder('stdClass')
            ->addMethods(['get_catalog'])
            ->getMock();
        $mock_api->method('get_catalog')->willThrowException(new ApiException('API error'));
        $mock_plugin->method('get_connection_handler')->willReturn($mock_handler);
        $mock_plugin->method('get_integration')->willReturn($mock_integration);
        $mock_plugin->method('get_api')->willReturn($mock_api);
        $mock_plugin->expects($this->once())->method('log');
        global $facebook_for_woocommerce;
        $facebook_for_woocommerce = function() use ($mock_plugin) { return $mock_plugin; };

        $connection = new Connection(); // Instantiate after patching global
        ob_start();
        $connection->render();
        $output = ob_get_clean();

        // Assert the CTA box is present even if API fails
        $this->assertStringContainsString('wc-facebook-connection-box', $output);
        // Assert the static items are present (since is_connected is true)
        $this->assertStringContainsString('bm_id', $output);
        $this->assertStringContainsString('ad_id', $output);
        $this->assertStringContainsString('ig_id', $output);
        $this->assertStringContainsString('cms_id', $output);
    }

    /**
     * Test render_facebook_box outputs correct CTA for both states
     */
    public function test_render_facebook_box_connected_and_not_connected() {
        // Patch global facebook_for_woocommerce() and its methods BEFORE instantiating Connection
        $mock_plugin = $this->getMockBuilder('stdClass')
            ->addMethods(['get_connection_handler'])
            ->getMock();
        $mock_handler = $this->getMockBuilder('stdClass')
            ->addMethods(['get_disconnect_url', 'get_connect_url'])
            ->getMock();
        $mock_handler->method('get_disconnect_url')->willReturn('https://disconnect.url');
        $mock_handler->method('get_connect_url')->willReturn('https://connect.url');
        $mock_plugin->method('get_connection_handler')->willReturn($mock_handler);
        global $facebook_for_woocommerce;
        $facebook_for_woocommerce = function() use ($mock_plugin) { return $mock_plugin; };

        $connection = new Connection(); // Instantiate after patching global

        // Connected
        ob_start();
        $this->invoke_method($connection, 'render_facebook_box', [true]);
        $output_connected = ob_get_clean();
        $this->assertStringContainsString('Disconnect', $output_connected);
        $this->assertStringContainsString('https://disconnect.url', $output_connected);

        // Not connected
        ob_start();
        $this->invoke_method($connection, 'render_facebook_box', [false]);
        $output_not_connected = ob_get_clean();
        $this->assertStringContainsString('Get Started', $output_not_connected);
        $this->assertStringContainsString('https://connect.url', $output_not_connected);
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
        $this->assertEquals('wc_facebook_enable_facebook_managed_coupons', $settings[3]['id']);
        $this->assertEquals('checkbox', $settings[3]['type']);
        $this->assertEquals('yes', $settings[3]['default']);
        $this->assertEquals('sectionend', $settings[count($settings)-1]['type']);
    }
}
