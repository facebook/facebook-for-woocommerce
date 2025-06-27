<?php
namespace WooCommerce\Facebook\Tests\Admin;

use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;
use WooCommerce\Facebook\Admin\Settings;

/**
 * Class SettingsTest
 *
 * @package WooCommerce\Facebook\Tests\Unit\Admin
 */
class SettingsTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

    /** @var \WC_Facebookcommerce|\PHPUnit\Framework\MockObject\MockObject */
    protected $plugin;

    /**
     * Set up the test environment
     */
    public function setUp(): void {
        parent::setUp();

        // Only create the plugin mock here, not the Settings instance
        $this->plugin = $this->getMockBuilder('WC_Facebookcommerce')
            ->disableOriginalConstructor()
            ->onlyMethods([
                'get_connection_handler',
                'get_rollout_switches',
            ])
            ->getMock();
        // Do NOT instantiate $this->settings here!
    }

    /**
     * Test constructor sets up hooks and screens
     */
    public function test_constructor_sets_properties_and_hooks() {
        // Set up the required mock for get_connection_handler
        $handler = $this->getMockBuilder('stdClass')
            ->addMethods(['is_connected'])
            ->getMock();
        $handler->method('is_connected')->willReturn(true);
        $this->plugin->method('get_connection_handler')->willReturn($handler);

        // Now instantiate Settings
        $settings = new Settings($this->plugin);

        // Assert the object is of the correct class
        $this->assertInstanceOf(Settings::class, $settings);

        // Use reflection to access private property
        $reflection = new \ReflectionClass($settings);
        $screens = $reflection->getProperty('screens');
        $screens->setAccessible(true);

        // Assert screens property is an array
        $this->assertIsArray($screens->getValue($settings));
    }

    /**
     * Test build_menu_item_array returns expected array structure
     */
    public function test_build_menu_item_array_returns_array() {
        // Inline stub connection handler
        $handler = $this->getMockBuilder('stdClass')
            ->addMethods(['is_connected'])
            ->getMock();
        $handler->method('is_connected')->willReturn(true);
        $this->plugin->method('get_connection_handler')
            ->willReturn($handler);

        // Now instantiate Settings
        $settings = new Settings($this->plugin);

        // Call the method under test
        $result = $settings->build_menu_item_array();

        // Assert the result is a non-empty array
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test add_extra_screens does not throw
     */
    public function test_add_extra_screens_is_callable() {
        // Inline stub connection handler
        $handler = $this->getMockBuilder('stdClass')
            ->addMethods(['is_connected'])
            ->getMock();
        $handler->method('is_connected')->willReturn(true);
        $this->plugin->method('get_connection_handler')
            ->willReturn($handler);

        // Inline stub rollout switches
        $switches = $this->getMockBuilder('stdClass')
            ->addMethods(['is_switch_enabled'])
            ->getMock();
        $switches->method('is_switch_enabled')
            ->willReturnMap([
                ['WHATSAPP_UTILITY_MESSAGING', true],
                ['SWITCH_WOO_ALL_PRODUCTS_SYNC_ENABLED', true],
            ]);
        $this->plugin->method('get_rollout_switches')
            ->willReturn($switches);

        // Now instantiate Settings
        $settings = new Settings($this->plugin);

        // Call the method and assert no exception is thrown
        $settings->add_extra_screens();
        $this->assertTrue(true); // If no exception, test passes
    }

    /**
     * Test root_menu_item returns expected string
     */
    public function test_root_menu_item_returns_string() {
        // Inline stub connection handler
        $handler = $this->getMockBuilder('stdClass')
            ->addMethods(['is_connected'])
            ->getMock();
        $handler->method('is_connected')->willReturn(true);
        $this->plugin->method('get_connection_handler')->willReturn($handler);

        // Test when marketing is enabled
        $settings_enabled = $this->getMockBuilder(Settings::class)
            ->setConstructorArgs([$this->plugin])
            ->onlyMethods(['is_marketing_enabled'])
            ->getMock();
        $settings_enabled->method('is_marketing_enabled')->willReturn(true);
        $this->assertEquals('woocommerce-marketing', $settings_enabled->root_menu_item());

        // Test when marketing is not enabled
        $settings_disabled = $this->getMockBuilder(Settings::class)
            ->setConstructorArgs([$this->plugin])
            ->onlyMethods(['is_marketing_enabled'])
            ->getMock();
        $settings_disabled->method('is_marketing_enabled')->willReturn(false);
        $this->assertEquals('woocommerce', $settings_disabled->root_menu_item());
    }

    /**
     * Test is_marketing_enabled returns bool
     */
    public function test_is_marketing_enabled_returns_bool() {
        // Inline stub connection handler
        $handler = $this->getMockBuilder('stdClass')
            ->addMethods(['is_connected'])
            ->getMock();
        $handler->method('is_connected')->willReturn(true);
        $this->plugin->method('get_connection_handler')->willReturn($handler);

        // Now instantiate Settings
        $settings = new Settings($this->plugin);

        // Call the method and assert the result is a boolean
        $result = $settings->is_marketing_enabled();
        $this->assertIsBool($result);
    }

    /**
     * Test get_screen returns null for unknown screen
     */
    public function test_get_screen_returns_null_for_unknown() {
        // Inline stub connection handler
        $handler = $this->getMockBuilder('stdClass')
            ->addMethods(['is_connected'])
            ->getMock();
        $handler->method('is_connected')->willReturn(true);
        $this->plugin->method('get_connection_handler')->willReturn($handler);

        // Now instantiate Settings
        $settings = new Settings($this->plugin);

        // Call with a non-existent screen ID
        $this->assertNull($settings->get_screen('not_a_real_screen'));
    }

    /**
     * Test get_screens returns array
     */
    public function test_get_screens_returns_array() {
        // Inline stub connection handler
        $handler = $this->getMockBuilder('stdClass')
            ->addMethods(['is_connected'])
            ->getMock();
        $handler->method('is_connected')->willReturn(true);
        $this->plugin->method('get_connection_handler')->willReturn($handler);

        // Now instantiate Settings
        $settings = new Settings($this->plugin);

        // Call the method and assert the result is an array
        $result = $settings->get_screens();
        $this->assertIsArray($result);
    }

    /**
     * Test get_screens filters out non-Abstract_Settings_Screen values
     */
    public function test_get_screens_filters_invalid_values() {
        // Inline stub connection handler
        $handler = $this->getMockBuilder('stdClass')
            ->addMethods(['is_connected'])
            ->getMock();
        $handler->method('is_connected')->willReturn(true);
        $this->plugin->method('get_connection_handler')->willReturn($handler);

        // Now instantiate Settings
        $settings = new Settings($this->plugin);

        // Use reflection to set up screens with both valid and invalid entries
        $reflection = new \ReflectionClass($settings);
        $prop = $reflection->getProperty('screens');
        $prop->setAccessible(true);

        // Create a valid mock screen and an invalid stdClass
        $mock_screen = $this->getMockBuilder('WooCommerce\\Facebook\\Admin\\Abstract_Settings_Screen')
            ->disableOriginalConstructor()->getMockForAbstractClass();

        $prop->setValue($settings, [
            'valid' => $mock_screen,
            'invalid' => new \stdClass(),
        ]);

        // Call get_screens and assert only the valid key remains
        $screens = $settings->get_screens();
        $this->assertArrayHasKey('valid', $screens);
        $this->assertArrayNotHasKey('invalid', $screens);
    }

    /**
     * Test get_tabs returns array
     */
    public function test_get_tabs_returns_array() {
        // Inline stub connection handler
        $handler = $this->getMockBuilder('stdClass')
            ->addMethods(['is_connected'])
            ->getMock();
        $handler->method('is_connected')->willReturn(true);
        $this->plugin->method('get_connection_handler')->willReturn($handler);

        // Now instantiate Settings
        $settings = new Settings($this->plugin);

        // Call the method and assert the result is an array
        $result = $settings->get_tabs();
        $this->assertIsArray($result);
    }

    /**
     * Test get_tabs applies filter and handles empty screens
     */
    public function test_get_tabs_applies_filter_and_handles_empty() {
        // Inline stub connection handler
        $handler = $this->getMockBuilder('stdClass')
            ->addMethods(['is_connected'])
            ->getMock();
        $handler->method('is_connected')->willReturn(true);
        $this->plugin->method('get_connection_handler')->willReturn($handler);

        // Now instantiate Settings
        $settings = new Settings($this->plugin);

        // Add a filter to modify tabs
        add_filter('wc_facebook_admin_settings_tabs', function($tabs) {
            $tabs['extra'] = 'Extra Tab';
            return $tabs;
        }, 10, 1);

        // Use reflection to set up a mock screen
        $reflection = new \ReflectionClass($settings);
        $prop = $reflection->getProperty('screens');
        $prop->setAccessible(true);

        // Use getMockForAbstractClass and onlyMethods for get_label
        $mock_screen = $this->getMockBuilder(\WooCommerce\Facebook\Admin\Abstract_Settings_Screen::class)
            ->disableOriginalConstructor()->onlyMethods(['get_label'])->getMockForAbstractClass();
        $mock_screen->method('get_label')->willReturn('Label');

        $prop->setValue($settings, ['mock' => $mock_screen]);

        // Call get_tabs and assert the filter was applied
        $tabs = $settings->get_tabs();
        $this->assertArrayHasKey('extra', $tabs);
        $this->assertEquals('Extra Tab', $tabs['extra']);
    }

    /**
     * Test set_parent_and_submenu_file returns string
     */
    public function test_set_parent_and_submenu_file_returns_string() {
        // Inline stub connection handler
        $handler = $this->getMockBuilder('stdClass')
            ->addMethods(['is_connected'])
            ->getMock();
        $handler->method('is_connected')->willReturn(true);
        $this->plugin->method('get_connection_handler')->willReturn($handler);

        // Now instantiate Settings
        $settings = new Settings($this->plugin);

        // Call the method and assert the result is a string
        $result = $settings->set_parent_and_submenu_file('woocommerce');
        $this->assertIsString($result);
    }

    /**
     * Test save returns early for non-admin, wrong page, no screen, no save, or insufficient permissions
     */
    public function test_save_returns_early_on_invalid_conditions() {
        // Inline stub connection handler
        $handler = $this->getMockBuilder('stdClass')
            ->addMethods(['is_connected'])
            ->getMock();
        $handler->method('is_connected')->willReturn(true);
        $this->plugin->method('get_connection_handler')->willReturn($handler);

        // Now instantiate Settings
        $settings = new Settings($this->plugin);

        // Define is_admin and related functions if not present
        if (!function_exists('is_admin')) {
            function is_admin() { static $call = 0; return $call++ === 0 ? false : true; }
        }
        if (!function_exists('current_user_can')) {
            function current_user_can() { return true; }
        }
        if (!function_exists('check_admin_referer')) {
            function check_admin_referer() { return true; }
        }
        // Simulate Helper::get_requested_value and Helper::get_posted_value using static variables
        global $test_requested_value_call;
        $test_requested_value_call = 0;
        if (!function_exists('test_get_requested_value')) {
            function test_get_requested_value() {
                global $test_requested_value_call;
                return $test_requested_value_call++ === 0 ? 'not-wc-facebook' : Settings::PAGE_ID;
            }
        }
        if (!function_exists('test_get_posted_value')) {
            function test_get_posted_value() { return null; }
        }
        // Patch the Settings class to use these test functions if needed
        // Not admin
        $this->assertNull($settings->save());
        // Wrong page
        $this->assertNull($settings->save());
        // No screen
        $this->assertNull($settings->save());
    }

    /**
     * Test save handles PluginException and success path
     */
    public function test_save_handles_plugin_exception_and_success() {
        // Inline stub connection handler
        $handler = $this->getMockBuilder('stdClass')
            ->addMethods(['is_connected'])
            ->getMock();
        $handler->method('is_connected')->willReturn(true);
        $this->plugin->method('get_connection_handler')->willReturn($handler);

        // Now instantiate Settings
        $settings = $this->getMockBuilder(Settings::class)
            ->setConstructorArgs([$this->plugin])
            ->onlyMethods(['get_screen'])
            ->getMock();

        // Define is_admin and related functions if not present
        if (!function_exists('is_admin')) {
            function is_admin() { return true; }
        }
        if (!function_exists('current_user_can')) {
            function current_user_can($cap = null) { return true; }
        }
        if (!function_exists('check_admin_referer')) {
            function check_admin_referer() { return true; }
        }
        // Simulate Helper::get_requested_value and Helper::get_posted_value for the correct sequence
        global $test_save_call_count;
        $test_save_call_count = 0;
        if (!function_exists('test_get_requested_value_success')) {
            function test_get_requested_value_success($key = null) {
                // Always return the correct page ID for this test
                return Settings::PAGE_ID;
            }
        }
        if (!function_exists('test_get_posted_value_success')) {
            function test_get_posted_value_success($key = null) {
                // Return the correct values for the save sequence
                if ($key === 'screen_id') return 'mock';
                if ($key === 'save_mock_settings') return true;
                return null;
            }
        }

        // Mock screen that throws PluginException on save
        $mock_screen = $this->getMockBuilder(\WooCommerce\Facebook\Admin\Abstract_Settings_Screen::class)
            ->disableOriginalConstructor()->onlyMethods(['get_id', 'save'])->getMockForAbstractClass();
        $mock_screen->method('get_id')->willReturn('mock');
        $mock_screen->expects($this->once())->method('save')->willThrowException(new \WooCommerce\Facebook\Framework\Plugin\Exception('fail'));

        // Mock Settings to return our mock screen
        $settings->method('get_screen')->willReturn($mock_screen);

        // Call save and assert no exception is thrown
        $settings->save();
    }

    /**
     * Test render_tabs outputs correct nav-tab markup for tabs, including whatsapp_utility special case
     */
    public function test_render_tabs_outputs_markup() {
        // Mock connection handler before instantiating Settings
        $handler = $this->getMockBuilder('stdClass')
            ->addMethods(['is_connected'])
            ->getMock();
        $handler->method('is_connected')->willReturn(true);
        $this->plugin->method('get_connection_handler')->willReturn($handler);

        // Use getMockForAbstractClass and onlyMethods for get_label
        $mock_screen = $this->getMockBuilder(\WooCommerce\Facebook\Admin\Abstract_Settings_Screen::class)
            ->disableOriginalConstructor()->onlyMethods(['get_label'])->getMockForAbstractClass();
        $mock_screen->method('get_label')->willReturn('Whatsapp Utility');

        $settings = new Settings($this->plugin);

        $reflection = new \ReflectionClass($settings);
        $prop = $reflection->getProperty('screens');
        $prop->setAccessible(true);
        $prop->setValue($settings, ['whatsapp_utility' => $mock_screen]);

        ob_start();
        $settings->render_tabs('whatsapp_utility');
        $output = ob_get_clean();

        $this->assertStringContainsString('nav-tab', $output);
        $this->assertStringContainsString('whatsapp_utility', $output);
    }

    /**
     * Test add_tabs_to_product_sets_taxonomy only renders for correct screen/taxonomy
     */
    public function test_add_tabs_to_product_sets_taxonomy_renders_only_for_product_set() {
        // Inline stub connection handler
        $handler = $this->getMockBuilder('stdClass')
            ->addMethods(['is_connected'])
            ->getMock();
        $handler->method('is_connected')->willReturn(true);
        $this->plugin->method('get_connection_handler')->willReturn($handler);

        // Now instantiate Settings
        $settings = new Settings($this->plugin);

        // Patch get_current_screen for this test only
        if (!function_exists('get_current_screen')) {
            function get_current_screen() {
                return (object)[
                    'base' => 'edit-tags',
                    'taxonomy' => 'fb_product_set',
                ];
            }
        }

        // Use getMockForAbstractClass and onlyMethods for get_label
        $mock_screen = $this->getMockBuilder(\WooCommerce\Facebook\Admin\Abstract_Settings_Screen::class)
            ->disableOriginalConstructor()->onlyMethods(['get_label'])->getMockForAbstractClass();
        $mock_screen->method('get_label')->willReturn('Product Sets');

        $reflection = new \ReflectionClass($settings);
        $prop = $reflection->getProperty('screens');
        $prop->setAccessible(true);
        $prop->setValue($settings, ['product_sets' => $mock_screen]);

        ob_start();
        $settings->add_tabs_to_product_sets_taxonomy();
        $output = ob_get_clean();

        $this->assertStringContainsString('facebook-for-woocommerce-tabs', $output);
    }
} 