<?php

namespace WooCommerce\Facebook\Tests\Unit;

use WooCommerce\Facebook\Admin\Settings_Screens\Connection;

class ConnectionTest extends \WP_UnitTestCase {

    private $connection;

    protected function setUp(): void {
        parent::setUp();
        $this->connection = new Connection();
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

    public function testGetSettings(): void {
        $settings = $this->connection->get_settings();

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

        // Check feed generator setting
        $feed_setting = $settings[3];
        $this->assertEquals('checkbox', $feed_setting['type']);
        $this->assertEquals('no', $feed_setting['default']);
    }
}
