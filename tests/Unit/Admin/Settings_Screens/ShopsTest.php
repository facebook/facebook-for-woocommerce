<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace WooCommerce\Facebook\Tests\Admin\Settings_Screens;

use PHPUnit\Framework\TestCase;
use WooCommerce\Facebook\Admin\Settings_Screens\Shops;
use WooCommerce\Facebook\Handlers\Connection;
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

        // Clear the singleton admin notice handler's notices between tests.
        $handler = facebook_for_woocommerce()->get_admin_notice_handler();
        $ref     = new \ReflectionObject( $handler );
        $prop    = $ref->getProperty( 'admin_notices' );
        $prop->setAccessible( true );
        $prop->setValue( $handler, [] );
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

        $this->assertStringContainsString("'https://www.commercepartnerhub.com'", $output);
        $this->assertStringContainsString("'https://www.facebook.com'", $output);
        $this->assertStringContainsString("'https://business.facebook.com'", $output);
        $this->assertStringContainsString('ALLOWED_ORIGINS.indexOf(event.origin) === -1', $output);

        $origin_guard_pos = strpos($output, 'ALLOWED_ORIGINS.indexOf(event.origin)');
        $data_access_pos  = strpos($output, 'const message = event.data');
        $this->assertNotFalse($origin_guard_pos);
        $this->assertNotFalse($data_access_pos);
        $this->assertLessThan($data_access_pos, $origin_guard_pos, 'Origin allowlist must be checked before event.data is read.');

        $this->assertStringContainsString("typeof message !== 'object'", $output);
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
	 * Test that the management URL uses the connection's Commerce Partner Integration ID.
	 */
	public function test_renders_management_url_using_cpi_id() {
		$connection_cpi_id = 'connection-cpi';
		$fallback_cpi_id   = 'fallback-cpi';
		$expected_endpoint = 'https://api.facebook.com/commerce-partner-integrations/' . $connection_cpi_id . '/commerce-extension-token';
		$request_count     = 0;
		$plugin            = facebook_for_woocommerce();
		$connection        = $this->createMock( Connection::class );
		$connection->method( 'is_connected' )->willReturn( true );
		$connection->method( 'get_external_business_id' )->willReturn( 'test-external-business' );
		$connection->method( 'get_commerce_partner_integration_id' )->willReturn( $connection_cpi_id );

		$plugin_proxy = new class( $plugin, $connection ) {

			/** @var \WC_Facebookcommerce */
			private $plugin;

			/** @var Connection */
			private $connection;

			public function __construct( $plugin, $connection ) {
				$this->plugin     = $plugin;
				$this->connection = $connection;
			}

			public function get_connection_handler() {
				return $this->connection;
			}

			public function __call( $method, $arguments ) {
				return $this->plugin->{$method}( ...$arguments );
			}
		};

		$this->mock_set_option( 'wc_facebook_access_token', 'long-lived-bisu-token' );
		$this->mock_set_option( 'wc_facebook_merchant_access_token', 'merchant-token' );
		$this->mock_set_option( 'wc_facebook_commerce_partner_integration_id', $fallback_cpi_id );
		$this->add_filter_with_safe_teardown(
			'wc_facebook_instance',
			function () use ( $plugin_proxy ) {
				return $plugin_proxy;
			},
			10,
			1
		);
		$this->add_filter_with_safe_teardown(
			'pre_http_request',
			function ( $preempt, $request_args, $url ) use ( $expected_endpoint, &$request_count ) {
				++$request_count;
				$this->assertSame( $expected_endpoint, $url );
				return array(
					'headers'  => array(),
					'body'     => wp_json_encode( array( 'access_token' => 'short-lived-delegate-token' ) ),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
				);
			},
			10,
			3
		);

		ob_start();
		$this->shops->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<iframe', $output );
		$this->assertStringContainsString( 'id="facebook-commerce-iframe-enhanced"', $output );
		$this->assertStringContainsString( 'commerce_extension/overview/', $output );
		$this->assertStringContainsString( 'access_token=short-lived-delegate-token', $output );
		$this->assertStringContainsString( 'external_business_id=test-external-business', $output );
		$this->assertStringNotContainsString( 'commerce_extension/splash/', $output );
		$this->assertStringNotContainsString( 'long-lived-bisu-token', $output );
		$this->assertSame( 1, substr_count( $output, '<iframe' ) );
		$this->assertSame( 1, $request_count );
	}

	/**
	 * Test that a token endpoint failure falls back to the legacy management URL.
	 */
	public function test_token_endpoint_failure_falls_back_to_legacy_management_url() {
		$legacy_url    = 'https://www.facebook.com/commerce/app/management/test-external-business/';
		$request_count = 0;

		$this->mock_set_option( 'wc_facebook_access_token', 'long-lived-bisu-token' );
		$this->mock_set_option( 'wc_facebook_merchant_access_token', 'merchant-token' );
		$this->mock_set_option( 'wc_facebook_commerce_partner_integration_id', 'test-cpi' );
		$this->add_filter_with_safe_teardown(
			'wc_facebook_external_business_id',
			function () {
				return 'test-external-business';
			},
			10,
			2
		);
		$this->add_filter_with_safe_teardown(
			'pre_http_request',
			function ( $preempt, $request_args, $url ) use ( $legacy_url, &$request_count ) {
				++$request_count;
				if ( false !== strpos( $url, 'api.facebook.com' ) ) {
					return new \WP_Error( 'http_request_failed', 'Test transport failure' );
				}

				return array(
					'headers'  => array(),
					'body'     => wp_json_encode(
						array(
							'commerce_extension' => array( 'uri' => $legacy_url ),
						)
					),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
				);
			},
			10,
			3
		);

		ob_start();
		$this->shops->render();
		$output = ob_get_clean();

		$this->assertSame( 2, $request_count );
		$this->assertSame( 1, substr_count( $output, '<iframe' ) );
		$this->assertStringContainsString( $legacy_url, $output );
		$this->assertStringNotContainsString( 'commerce_extension/splash/', $output );
	}

	/**
	 * Test that both management URL failures fall back to the connected-store splash URL.
	 */
	public function test_management_url_failures_fall_back_to_installed_splash() {
		$request_count = 0;

		$this->mock_set_option( 'wc_facebook_access_token', 'long-lived-bisu-token' );
		$this->mock_set_option( 'wc_facebook_merchant_access_token', 'merchant-token' );
		$this->mock_set_option( 'wc_facebook_commerce_partner_integration_id', 'test-cpi' );
		$this->add_filter_with_safe_teardown(
			'wc_facebook_external_business_id',
			function () {
				return 'test-external-business';
			},
			10,
			2
		);
		$this->add_filter_with_safe_teardown(
			'pre_http_request',
			function () use ( &$request_count ) {
				++$request_count;
				return new \WP_Error( 'http_request_failed', 'Test transport failure' );
			},
			10,
			3
		);

		ob_start();
		$this->shops->render();
		$output = ob_get_clean();

		$this->assertSame( 2, $request_count );
		$this->assertSame( 1, substr_count( $output, '<iframe' ) );
		$this->assertStringContainsString( 'commerce_extension/splash/', $output );
		$this->assertStringContainsString( 'installed=1', $output );
		$this->assertStringNotContainsString( 'commerce_extension/overview/', $output );
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

    /**
     * Test that add_notices registers a connection invalid notice when the transient is set.
     */
    public function test_add_notices_shows_connection_invalid_notice() {
        // Set up an admin user so the notice handler allows display.
        $user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $user_id );

        set_transient( 'wc_facebook_connection_invalid', time(), DAY_IN_SECONDS );

        // The connection invalid notice is now handled globally in
        // WC_Facebookcommerce::add_connection_invalid_notice(), not in Shops::add_notices().
        // Simulate being on an allowed screen (plugins page).
        set_current_screen( 'plugins' );
        facebook_for_woocommerce()->add_connection_invalid_notice();

        // Access the admin notice handler's internal notices array.
        $handler = facebook_for_woocommerce()->get_admin_notice_handler();
        $ref     = new \ReflectionObject( $handler );
        $prop    = $ref->getProperty( 'admin_notices' );
        $prop->setAccessible( true );
        $notices = $prop->getValue( $handler );

        $this->assertArrayHasKey( 'wc_facebook_connection_invalid', $notices );
        $this->assertStringContainsString( 'access token is no longer valid', $notices['wc_facebook_connection_invalid']['message'] );

        delete_transient( 'wc_facebook_connection_invalid' );
    }

    /**
     * Test that add_notices does NOT register a connection invalid notice when the transient is not set.
     */
    public function test_add_notices_skips_connection_invalid_when_transient_not_set() {
        $user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $user_id );

        // Ensure transient is not set.
        delete_transient( 'wc_facebook_connection_invalid' );

        $shops = new Shops();
        $shops->add_notices();

        $handler = facebook_for_woocommerce()->get_admin_notice_handler();
        $ref     = new \ReflectionObject( $handler );
        $prop    = $ref->getProperty( 'admin_notices' );
        $prop->setAccessible( true );
        $notices = $prop->getValue( $handler );

        $this->assertArrayNotHasKey( 'wc_facebook_connection_invalid', $notices );
    }

    /**
     * Test that the connection invalid notice links to the settings page, not the legacy OAuth URL.
     */
    public function test_connection_invalid_notice_links_to_settings_page() {
        $user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $user_id );

        set_transient( 'wc_facebook_connection_invalid', time(), DAY_IN_SECONDS );

        // The connection invalid notice is now handled globally.
        set_current_screen( 'plugins' );
        facebook_for_woocommerce()->add_connection_invalid_notice();

        $handler = facebook_for_woocommerce()->get_admin_notice_handler();
        $ref     = new \ReflectionObject( $handler );
        $prop    = $ref->getProperty( 'admin_notices' );
        $prop->setAccessible( true );
        $notices = $prop->getValue( $handler );

        $message = $notices['wc_facebook_connection_invalid']['message'];

        // Should link to the settings page.
        $this->assertStringContainsString( 'page=wc-facebook', $message );
        // Should NOT link to the legacy OAuth flow.
        $this->assertStringNotContainsString( 'facebook.com/dialog/oauth', $message );

        delete_transient( 'wc_facebook_connection_invalid' );
    }

    /**
     * Test that render_facebook_iframe falls back to splash URL when connection is invalid.
     */
    public function test_render_facebook_iframe_shows_splash_when_connection_invalid() {
        // Set merchant token so the management path would normally be taken.
        update_option( 'wc_facebook_merchant_access_token', 'test_token' );
        // Set the connection invalid transient.
        set_transient( 'wc_facebook_connection_invalid', time(), DAY_IN_SECONDS );

        $shops      = $this->getMockBuilder( Shops::class )->getMock();
        $reflection = new \ReflectionClass( get_class( $shops ) );
        $method     = $reflection->getMethod( 'render_facebook_iframe' );
        $method->setAccessible( true );

        ob_start();
        $method->invoke( $shops );
        $output = ob_get_clean();

        // Should show the splash iframe (onboarding), not the management iframe.
        $this->assertStringContainsString( 'commercepartnerhub.com/commerce_extension/splash', $output );

        delete_transient( 'wc_facebook_connection_invalid' );
    }

    /**
     * Test that the splash URL has installed=false when connection is invalid.
     */
    public function test_render_facebook_iframe_shows_splash_with_installed_false() {
        update_option( 'wc_facebook_merchant_access_token', 'test_token' );
        set_transient( 'wc_facebook_connection_invalid', time(), DAY_IN_SECONDS );

        $shops      = $this->getMockBuilder( Shops::class )->getMock();
        $reflection = new \ReflectionClass( get_class( $shops ) );
        $method     = $reflection->getMethod( 'render_facebook_iframe' );
        $method->setAccessible( true );

        ob_start();
        $method->invoke( $shops );
        $output = ob_get_clean();

        // The splash URL should have installed= with an empty or falsy value.
        // When connection is invalid, we pass false for $is_connected so the iframe shows onboarding.
        $this->assertStringNotContainsString( 'installed=1', $output );

        delete_transient( 'wc_facebook_connection_invalid' );
    }
}
