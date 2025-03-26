<?php
namespace WooCommerce\Facebook\Tests\Unit\Admin\Settings;

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

        $this->assertFalse(wp_style_is('wc-facebook-admin-connection-settings'));
    }
}
