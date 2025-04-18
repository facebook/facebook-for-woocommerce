<?php
namespace WooCommerce\Facebook\Tests\Admin\Settings;

use WooCommerce\Facebook\Admin\Settings_Screens\Shops;

/**
 * Class ShopsTest
 *
 * @package WooCommerce\Facebook\Tests\Unit\Admin\Settings
 */
class ShopsTest extends \WP_UnitTestCase {

    /** @var Shops */
    private $shops;

    /**
     * Set up the test environment
     */
    protected function setUp(): void {
        parent::setUp();
        $this->shops = new Shops();
    }

    /**
     * Test that enqueue_assets enqueues the expected styles when on the page
     */
    public function testEnqueueAssetsWhenNotOnPage(): void {
        // Mock is_current_screen_page to return false
        $shops = $this->getMockBuilder(Shops::class)
            ->onlyMethods(['is_current_screen_page'])
            ->getMock();

        $shops->method('is_current_screen_page')
            ->willReturn(false);

        // No styles should be enqueued
        $shops->enqueue_assets();

        $this->assertFalse(wp_style_is('wc-facebook-admin-shops-settings'));
    }

    /**
     * Test that get_settings returns the expected settings
     */
     public function testGetSettings(): void {
        $settings = $this->shops->get_settings();

        $this->assertIsArray($settings);
        $this->assertNotEmpty($settings);

        // Check that the settings array has the expected structure
        $this->assertArrayHasKey('type', $settings[0]);
        $this->assertEquals('title', $settings[0]['type']);

        // Check meta diagnosis setting
        $debug_setting = $settings[1];
        $this->assertEquals('checkbox', $debug_setting['type']);
        $this->assertEquals('yes', $debug_setting['default']);

        // Check debug mode setting
        $debug_setting = $settings[2];
        $this->assertEquals('checkbox', $debug_setting['type']);
        $this->assertEquals('no', $debug_setting['default']);
    }
}
