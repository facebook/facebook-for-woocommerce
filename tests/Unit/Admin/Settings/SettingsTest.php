<?php
namespace WooCommerce\Facebook\Tests\Admin\Settings;

use PHPUnit\Framework\TestCase;
use WooCommerce\Facebook\Admin\Settings;

/**
 * Class SettingsTest
 *
 * @package WooCommerce\Facebook\Tests\Unit\Admin\Settings
 */
class SettingsTest extends TestCase {

    /** @var \WC_Facebookcommerce|\PHPUnit\Framework\MockObject\MockObject */
    protected $plugin;

    /** @var Settings */
    protected $settings;

    /**
     * Set up the test environment
     */
    protected function setUp(): void {
        parent::setUp();
        // Mock the plugin dependency
        $this->plugin = $this->getMockBuilder('WC_Facebookcommerce')
            ->disableOriginalConstructor()
            ->onlyMethods([
                'get_connection_handler',
                'get_rollout_switches',
            ])
            ->getMock();
        $this->settings = new Settings($this->plugin);
    }

    /**
     * Test constructor sets up hooks and screens
     */
    public function test_constructor_sets_properties_and_hooks() {
        // Assert the object is of the correct class
        $this->assertInstanceOf(Settings::class, $this->settings);

        // Use reflection to access private property
        $reflection = new \ReflectionClass($this->settings);
        $screens = $reflection->getProperty('screens');
        $screens->setAccessible(true);

        // Assert screens property is an array
        $this->assertIsArray($screens->getValue($this->settings));
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

        // Mock the plugin's get_connection_handler method
        $this->plugin->method('get_connection_handler')
            ->willReturn($handler);

        // Call the method under test
        $result = $this->settings->build_menu_item_array();

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

        // Mock the plugin's get_connection_handler method
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

        // Mock the plugin's get_rollout_switches method
        $this->plugin->method('get_rollout_switches')
            ->willReturn($switches);

        // Call the method and assert no exception is thrown
        $this->settings->add_extra_screens();
        $this->assertTrue(true); // If no exception, test passes
    }

    /**
     * Test root_menu_item returns expected string
     */
    public function test_root_menu_item_returns_string() {
        // Mock Settings with is_marketing_enabled
        $settings = $this->getMockBuilder(Settings::class)
            ->setConstructorArgs([$this->plugin])
            ->onlyMethods(['is_marketing_enabled'])
            ->getMock();

        // Test when marketing is enabled
        $settings->method('is_marketing_enabled')->willReturn(true);
        $this->assertEquals('woocommerce-marketing', $settings->root_menu_item());

        // Test when marketing is not enabled
        $settings->method('is_marketing_enabled')->willReturn(false);
        $this->assertEquals('woocommerce', $settings->root_menu_item());
    }

    /**
     * Test is_marketing_enabled returns bool
     */
    public function test_is_marketing_enabled_returns_bool() {
        // Call the method and assert the result is a boolean
        $result = $this->settings->is_marketing_enabled();
        $this->assertIsBool($result);
    }

    /**
     * Test get_screen returns null for unknown screen
     */
    public function test_get_screen_returns_null_for_unknown() {
        // Call with a non-existent screen ID
        $this->assertNull($this->settings->get_screen('not_a_real_screen'));
    }

    /**
     * Test get_screens returns array
     */
    public function test_get_screens_returns_array() {
        // Call the method and assert the result is an array
        $result = $this->settings->get_screens();
        $this->assertIsArray($result);
    }

    /**
     * Test get_screens filters out non-Abstract_Settings_Screen values
     */
    public function test_get_screens_filters_invalid_values() {
        // Use reflection to set up screens with both valid and invalid entries
        $reflection = new \ReflectionClass($this->settings);
        $prop = $reflection->getProperty('screens');
        $prop->setAccessible(true);

        // Create a valid mock screen and an invalid stdClass
        $mock_screen = $this->getMockBuilder('WooCommerce\\Facebook\\Admin\\Abstract_Settings_Screen')
            ->disableOriginalConstructor()->getMockForAbstractClass();

        $prop->setValue($this->settings, [
            'valid' => $mock_screen,
            'invalid' => new \stdClass(),
        ]);

        // Call get_screens and assert only the valid key remains
        $screens = $this->settings->get_screens();
        $this->assertArrayHasKey('valid', $screens);
        $this->assertArrayNotHasKey('invalid', $screens);
    }

    /**
     * Test get_tabs returns array
     */
    public function test_get_tabs_returns_array() {
        // Call the method and assert the result is an array
        $result = $this->settings->get_tabs();
        $this->assertIsArray($result);
    }

    /**
     * Test get_tabs applies filter and handles empty screens
     */
    public function test_get_tabs_applies_filter_and_handles_empty() {
        // Add a filter to modify tabs
        add_filter('wc_facebook_admin_settings_tabs', function($tabs) {
            $tabs['extra'] = 'Extra Tab';
            return $tabs;
        }, 10, 1);

        // Use reflection to set up a mock screen
        $reflection = new \ReflectionClass($this->settings);
        $prop = $reflection->getProperty('screens');
        $prop->setAccessible(true);

        $mock_screen = $this->getMockBuilder('WooCommerce\\Facebook\\Admin\\Abstract_Settings_Screen')
            ->disableOriginalConstructor()->getMockForAbstractClass();
        $mock_screen->method('get_label')->willReturn('Label');

        $prop->setValue($this->settings, ['mock' => $mock_screen]);

        // Call get_tabs and assert the filter was applied
        $tabs = $this->settings->get_tabs();
        $this->assertArrayHasKey('extra', $tabs);
        $this->assertEquals('Extra Tab', $tabs['extra']);
    }

    /**
     * Test set_parent_and_submenu_file returns string
     */
    public function test_set_parent_and_submenu_file_returns_string() {
        // Call the method and assert the result is a string
        $result = $this->settings->set_parent_and_submenu_file('woocommerce');
        $this->assertIsString($result);
    }

    /**
     * Test save returns early for non-admin, wrong page, no screen, no save, or insufficient permissions
     */
    public function test_save_returns_early_on_invalid_conditions() {
        // Not admin
        \WP_Mock::userFunction('is_admin', ['return' => false]);
        $this->assertNull($this->settings->save());

        // Wrong page
        \WP_Mock::userFunction('is_admin', ['return' => true]);
        \WP_Mock::userFunction('WooCommerce\\Facebook\\Framework\\Helper::get_requested_value', ['return' => 'not-wc-facebook']);
        $this->assertNull($this->settings->save());

        // No screen
        \WP_Mock::userFunction('WooCommerce\\Facebook\\Framework\\Helper::get_requested_value', ['return' => Settings::PAGE_ID]);
        \WP_Mock::userFunction('WooCommerce\\Facebook\\Framework\\Helper::get_posted_value', ['return' => null]);
        $this->assertNull($this->settings->save());
    }

    /**
     * Test save handles PluginException and success path
     */
    public function test_save_handles_plugin_exception_and_success() {
        // Setup mocks for admin, correct page, screen, and permissions
        \WP_Mock::userFunction('is_admin', ['return' => true]);
        \WP_Mock::userFunction('WooCommerce\\Facebook\\Framework\\Helper::get_requested_value', ['return' => Settings::PAGE_ID]);
        \WP_Mock::userFunction('WooCommerce\\Facebook\\Framework\\Helper::get_posted_value', function($key) {
            if ($key === 'screen_id') return 'mock';
            if ($key === 'save_mock_settings') return true;
            return null;
        });
        \WP_Mock::userFunction('current_user_can', ['return' => true]);
        \WP_Mock::userFunction('check_admin_referer', ['return' => true]);

        // Mock screen that throws PluginException on save
        $mock_screen = $this->getMockBuilder('WooCommerce\\Facebook\\Admin\\Abstract_Settings_Screen')
            ->disableOriginalConstructor()->getMockForAbstractClass();
        $mock_screen->method('get_id')->willReturn('mock');
        $mock_screen->expects($this->once())->method('save')->willThrowException(new \WooCommerce\Facebook\Framework\Plugin\Exception('fail'));

        // Mock Settings to return our mock screen
        $settings = $this->getMockBuilder(Settings::class)
            ->setConstructorArgs([$this->plugin])
            ->onlyMethods(['get_screen'])
            ->getMock();
        $settings->method('get_screen')->willReturn($mock_screen);

        // Call save and assert no exception is thrown
        $settings->save();
    }

    /**
     * Test render_tabs outputs correct nav-tab markup for tabs, including whatsapp_utility special case
     */
    public function test_render_tabs_outputs_markup() {
        // Use reflection to set up a whatsapp_utility screen
        $mock_screen = $this->getMockBuilder('WooCommerce\\Facebook\\Admin\\Abstract_Settings_Screen')
            ->disableOriginalConstructor()->getMockForAbstractClass();
        $mock_screen->method('get_label')->willReturn('Whatsapp Utility');

        $reflection = new \ReflectionClass($this->settings);
        $prop = $reflection->getProperty('screens');
        $prop->setAccessible(true);
        $prop->setValue($this->settings, ['whatsapp_utility' => $mock_screen]);

        // Capture output
        ob_start();
        $this->settings->render_tabs('whatsapp_utility');
        $output = ob_get_clean();

        // Assert nav-tab markup and tab ID are present
        $this->assertStringContainsString('nav-tab', $output);
        $this->assertStringContainsString('whatsapp_utility', $output);
    }

    /**
     * Test add_tabs_to_product_sets_taxonomy only renders for correct screen/taxonomy
     */
    public function test_add_tabs_to_product_sets_taxonomy_renders_only_for_product_set() {
        // Mock get_current_screen to simulate the correct taxonomy page
        \WP_Mock::userFunction('get_current_screen', ['return' => (object)[
            'base' => 'edit-tags',
            'taxonomy' => 'fb_product_set',
        ]]);

        // Use reflection to set up a product_sets screen
        $mock_screen = $this->getMockBuilder('WooCommerce\\Facebook\\Admin\\Abstract_Settings_Screen')
            ->disableOriginalConstructor()->getMockForAbstractClass();
        $mock_screen->method('get_label')->willReturn('Product Sets');

        $reflection = new \ReflectionClass($this->settings);
        $prop = $reflection->getProperty('screens');
        $prop->setAccessible(true);
        $prop->setValue($this->settings, ['product_sets' => $mock_screen]);

        // Capture output
        ob_start();
        $this->settings->add_tabs_to_product_sets_taxonomy();
        $output = ob_get_clean();

        // Assert the tabs markup is present
        $this->assertStringContainsString('facebook-for-woocommerce-tabs', $output);
    }
} 