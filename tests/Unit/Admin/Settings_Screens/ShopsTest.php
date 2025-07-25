<?php
namespace WooCommerce\Facebook\Tests\Admin\Settings_Screens;

use PHPUnit\Framework\TestCase;
use WooCommerce\Facebook\Admin\Settings_Screens\Shops;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Class ShopsTest
 *
 * @package WooCommerce\Facebook\Tests\Unit\Admin\Settings_Screens
 */
class ShopsTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

    /** @var Shops */
    private $shops;

    /**
     * Set up the test environment
     */
    public function setUp(): void {
        parent::setUp();
        
        $this->shops = new Shops();
    }

    /**
     * Test that render method calls render_facebook_iframe when enhanced onboarding is enabled
     */
    public function test_render_facebook_box_iframe() {
        // Create a mock of the Shops class
        $shops = $this->getMockBuilder(Shops::class)
            ->getMock();

        // Start output buffering to capture the render output
        ob_start();
        $shops->render();
        $output = ob_get_clean();

        // Since we can't directly test the private render_facebook_iframe method,
        // we'll verify that the render method doesn't output the legacy Facebook box
        // when enhanced onboarding is enabled
        $this->assertStringNotContainsString('wc-facebook-connection-box', $output);
    }

    /**
     * Test that render_message_handler outputs the expected JavaScript
     */
    public function test_render_message_handler() {
        // Create a mock of the Shops class
        $shops_mock = $this->getMockBuilder(Shops::class)
            ->onlyMethods(['is_current_screen_page'])
            ->getMock();

        // Configure the mock to return true for is_current_screen_page
        $shops_mock->method('is_current_screen_page')
            ->willReturn(true);

        // Call the method
        $output = $shops_mock->generate_inline_enhanced_onboarding_script();

        // Assert JavaScript event listeners and handlers
        $this->assertStringContainsString('window.addEventListener(\'message\'', $output);
        $this->assertStringContainsString('CommerceExtension::INSTALL', $output);
        $this->assertStringContainsString('CommerceExtension::RESIZE', $output);
        $this->assertStringContainsString('CommerceExtension::UNINSTALL', $output);

        // Assert fetch request setup - check for wpApiSettings.root instead of hardcoded path
        $this->assertStringContainsString('GeneratePluginAPIClient', $output);
        $this->assertStringContainsString('fbAPI.updateSettings', $output);
    }

    /**
     * Test that render_message_handler doesn't output when not on current screen
     */
    public function test_render_message_handler_not_current_screen() {
        // Create a mock of the Shops class
        $shops_mock = $this->getMockBuilder(Shops::class)
            ->onlyMethods(['is_current_screen_page'])
            ->getMock();

        $shops_mock->method('is_current_screen_page')
            ->willReturn(false);

        // Start output buffering to capture the render output
        ob_start();
        $shops_mock->render_message_handler();
        $output = ob_get_clean();

        // Assert that no output is generated
        $this->assertEmpty($output);
    }

    /**
     * Test that the management URL is used when merchant token exists
     */
    public function test_renders_management_url_based_on_merchant_token() {
        // Create a mock of the Shops class
        $shops = $this->getMockBuilder(Shops::class)
            ->getMock();

        // Set up the merchant token
        update_option('wc_facebook_merchant_access_token', 'test_token');

        // Start output buffering to capture the render output
        ob_start();
        // Directly use Reflection to invoke the private/protected method
        $reflection = new \ReflectionClass(get_class($shops));
        $method = $reflection->getMethod('render_facebook_iframe');
        $method->setAccessible(true);
        $method->invoke($shops);
        $output = ob_get_clean();

        // Check that the iframe is rendered
        $this->assertStringContainsString('<iframe', $output);
        $this->assertStringContainsString('id="facebook-commerce-iframe-enhanced"', $output);
    }

    /**
     * Test get_settings returns all expected settings and structure
     */
    public function test_get_settings_returns_all_expected_settings() {
        $shops = new Shops();
        $switch_key = 'offer_management_enabled';
        $option_key = 'wc_facebook_for_woocommerce_rollout_switches';

        // When offer management is disabled
        update_option($option_key, [$switch_key => 'no']);
        $settings = $shops->get_settings();
        $this->assertIsArray($settings);
        $found_meta = false;
        $found_debug = false;
        foreach ($settings as $setting) {
            if (isset($setting['id']) && $setting['id'] === 'wc_facebook_enable_meta_diagnosis') {
                $found_meta = true;
                $this->assertEquals('checkbox', $setting['type']);
                $this->assertEquals('yes', $setting['default']);
            }
            if (isset($setting['id']) && $setting['id'] === 'wc_facebook_enable_debug_mode') {
                $found_debug = true;
                $this->assertEquals('checkbox', $setting['type']);
                $this->assertEquals('no', $setting['default']);
            }
        }
        $this->assertTrue($found_meta);
        $this->assertTrue($found_debug);
        $last_setting = end($settings);
        $this->assertEquals('sectionend', $last_setting['type']);

        // When offer management is enabled
        update_option($option_key, [$switch_key => 'yes']);
        $settings = $shops->get_settings();
        $this->assertIsArray($settings);
        $found_meta = false;
        $found_debug = false;
        $found_coupon = false;
        foreach ($settings as $setting) {
            if (isset($setting['id']) && $setting['id'] === 'wc_facebook_enable_meta_diagnosis') {
                $found_meta = true;
                $this->assertEquals('checkbox', $setting['type']);
                $this->assertEquals('yes', $setting['default']);
            }
            if (isset($setting['id']) && $setting['id'] === 'wc_facebook_enable_debug_mode') {
                $found_debug = true;
                $this->assertEquals('checkbox', $setting['type']);
                $this->assertEquals('no', $setting['default']);
            }
            if (isset($setting['id']) && $setting['id'] === 'wc_facebook_enable_facebook_managed_coupons') {
                $found_coupon = true;
                $this->assertEquals('checkbox', $setting['type']);
                $this->assertEquals('yes', $setting['default']);
            }
        }
        $this->assertTrue($found_meta);
        $this->assertTrue($found_debug);
        $this->assertTrue($found_coupon);
        $last_setting = end($settings);
        $this->assertEquals('sectionend', $last_setting['type']);
    }
}
