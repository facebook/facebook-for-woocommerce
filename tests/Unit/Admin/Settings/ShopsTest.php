<?php

namespace WooCommerce\Facebook\Tests\Unit;

use WooCommerce\Facebook\Admin\Settings_Screens\Shops;

class ShopsTest extends \WP_UnitTestCase {

    private $shops;

    protected function setUp(): void {
        parent::setUp();
        $this->shops = new Shops();
    }

    public function testEnqueueAssetsWhenNotOnPage(): void {
        // Mock is_current_screen_page to return false
        $connection = $this->getMockBuilder(Connection::class)
            ->onlyMethods(['is_current_screen_page'])
            ->getMock();

        $connection->method('is_current_screen_page')
            ->willReturn(false);

        // No styles should be enqueued
        $connection->enqueue_assets();

        $this->assertFalse(wp_style_is('wc-facebook-admin-connection-settings'));
    }
}
