<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace WooCommerce\Facebook\Tests\Admin\Settings_Screens;

use WooCommerce\Facebook\API\Plugin\Settings\FinalizeInstall\Request as FinalizeInstallRequest;
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
	 * Test that disconnected merchants see the current CPH onboarding iframe.
	 */
	public function test_render_shows_cph_splash_when_disconnected(): void {
		delete_option( Connection::OPTION_ACCESS_TOKEN );
		delete_option( Connection::OPTION_MERCHANT_ACCESS_TOKEN );
		delete_transient( 'wc_facebook_connection_invalid' );

		ob_start();
		$this->shops->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'id="facebook-commerce-iframe-enhanced"', $output );
		$this->assertStringContainsString( 'commerce_extension/splash', $output );
		$this->assertStringNotContainsString( 'wc-facebook-connection-box', $output );
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
        $this->assertStringContainsString('fbAPI.finalizeInstall', $output);
        $this->assertStringContainsString('Object.keys(requestBody).forEach', $output);
        $this->assertStringContainsString('requestBody[key] === undefined || requestBody[key] === null', $output);

        $cleanup_pos  = strpos($output, 'Object.keys(requestBody).forEach');
        $finalize_pos = strpos($output, 'fbAPI.finalizeInstall');
        $this->assertNotFalse($cleanup_pos);
        $this->assertNotFalse($finalize_pos);
        $this->assertLessThan($finalize_pos, $cleanup_pos, 'Missing optional values must be removed before finalization.');

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
	 * Test that install payload fields match the generated request schema.
	 */
	public function test_finalize_install_payload_matches_request_schema(): void {
		$output = $this->shops->generate_inline_enhanced_onboarding_script();

		$this->assertSame(
			1,
			preg_match( '/const requestBody = \{(?P<body>.*?)\n\s*\};/s', $output, $payload_match ),
			'Unable to find the finalize-install request body.'
		);
		$this->assertGreaterThan(
			0,
			preg_match_all( '/^\s*([a-z_]+):/m', $payload_match['body'], $key_matches ),
			'Unable to find any finalize-install request parameters.'
		);

		$request = new FinalizeInstallRequest( new \WP_REST_Request() );
		$schema_keys = array_keys( $request->get_param_schema() );
		$payload_keys = $key_matches[1];

		$this->assertSame( array(), array_values( array_diff( $schema_keys, $payload_keys ) ) );
		$this->assertSame(
			array( 'merchant_access_token' ),
			array_values( array_diff( $payload_keys, $schema_keys ) ),
			'The duplicate merchant token is the only permitted legacy payload field until #4035 lands.'
		);
	}

	/**
	 * Test that missing or malformed installed features cannot abort finalization.
	 */
	public function test_finalize_install_normalizes_installed_features_before_use(): void {
		$output = $this->shops->generate_inline_enhanced_onboarding_script();

		$normalization_pos = strpos( $output, 'const installed_features = Array.isArray(message.installed_features) ? message.installed_features : [];' );
		$lookup_pos        = strpos( $output, 'installed_features.find' );
		$payload_pos       = strpos( $output, 'installed_features: installed_features' );

		$this->assertNotFalse( $normalization_pos );
		$this->assertNotFalse( $lookup_pos );
		$this->assertNotFalse( $payload_pos );
		$this->assertLessThan( $lookup_pos, $normalization_pos, 'Installed features must be normalized before lookups.' );
		$this->assertLessThan( $payload_pos, $normalization_pos, 'The normalized installed features must be sent in the request.' );
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

    /**
     * Test that the global handler registers a connection invalid notice when the transient is set.
     */
    public function test_global_handler_shows_connection_invalid_notice() {
        // Set up an admin user so the notice handler allows display.
        $user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $user_id );

        set_transient( 'wc_facebook_connection_invalid', time(), DAY_IN_SECONDS );

        // The connection invalid notice is handled globally in
        // WC_Facebookcommerce::add_connection_invalid_notice().
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
