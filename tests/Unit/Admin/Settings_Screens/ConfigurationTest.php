<?php
/**
 * Copyright (c) Meta, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Admin\Settings_Screens;

use WooCommerce\Facebook\Admin\Abstract_Settings_Screen;
use WooCommerce\Facebook\Admin\Settings_Screens\Configuration;
use WooCommerce\Facebook\Events\POS\POS_Integration_Interface;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;

/**
 * Tests the Configuration settings screen.
 *
 * These settings and the manual sync controls moved here from the collapsed
 * Troubleshooting drawer on the Shops tab.
 *
 * @covers \WooCommerce\Facebook\Admin\Settings_Screens\Configuration
 */
class ConfigurationTest extends AbstractWPUnitTestWithSafeFiltering {

	/** @var string */
	private const SWITCHES_OPTION = 'wc_facebook_for_woocommerce_rollout_switches';

	public function tearDown(): void {
		delete_option( self::SWITCHES_OPTION );

		parent::tearDown();
	}

	/**
	 * Finds a setting by its id.
	 *
	 * @param array  $settings the settings array.
	 * @param string $id       the setting id to find.
	 * @return array|null
	 */
	private function find_setting( array $settings, string $id ): ?array {
		foreach ( $settings as $setting ) {
			if ( isset( $setting['id'] ) && $id === $setting['id'] ) {
				return $setting;
			}
		}

		return null;
	}

	public function test_it_is_a_settings_screen() {
		$this->assertInstanceOf( Abstract_Settings_Screen::class, new Configuration() );
	}

	public function test_screen_id_is_configuration() {
		$this->assertSame( 'configuration', Configuration::ID );
	}

	public function test_sync_action_constants_keep_their_wire_values() {
		// The JS posts these literals, and AJAX.php verifies nonces against them,
		// so renaming the constants must not change the values.
		$this->assertSame( 'wc_facebook_sync_products', Configuration::ACTION_SYNC_PRODUCTS );
		$this->assertSame( 'wc_facebook_sync_coupons', Configuration::ACTION_SYNC_COUPONS );
		$this->assertSame( 'wc_facebook_sync_shipping_profiles', Configuration::ACTION_SYNC_SHIPPING_PROFILES );
		$this->assertSame( 'wc_facebook_sync_navigation_menu', Configuration::ACTION_SYNC_NAVIGATION_MENU );
	}

	public function test_given_offer_management_disabled_then_core_settings_are_present() {
		update_option( self::SWITCHES_OPTION, array( 'offer_management_enabled' => 'no' ) );

		$settings = Configuration::get_plugin_settings();

		$meta = $this->find_setting( $settings, 'wc_facebook_enable_meta_diagnosis' );
		$this->assertNotNull( $meta );
		$this->assertSame( 'checkbox', $meta['type'] );
		$this->assertSame( 'yes', $meta['default'] );

		$debug = $this->find_setting( $settings, 'wc_facebook_enable_debug_mode' );
		$this->assertNotNull( $debug );
		$this->assertSame( 'checkbox', $debug['type'] );
		$this->assertSame( 'no', $debug['default'] );

		$this->assertNull(
			$this->find_setting( $settings, 'wc_facebook_enable_facebook_managed_coupons' ),
			'Managed coupons must stay hidden while the rollout switch is off.'
		);

		$last = end( $settings );
		$this->assertSame( 'sectionend', $last['type'] );
	}

	public function test_given_offer_management_enabled_then_managed_coupons_is_present() {
		update_option( self::SWITCHES_OPTION, array( 'offer_management_enabled' => 'yes' ) );

		$settings = Configuration::get_plugin_settings();
		$coupons  = $this->find_setting( $settings, 'wc_facebook_enable_facebook_managed_coupons' );

		$this->assertNotNull( $coupons );
		$this->assertSame( 'checkbox', $coupons['type'] );
		$this->assertSame( 'yes', $coupons['default'] );

		$last = end( $settings );
		$this->assertSame( 'sectionend', $last['type'] );
	}

	public function test_settings_open_with_a_title_and_close_with_a_matching_sectionend() {
		$settings = Configuration::get_plugin_settings();

		$first = reset( $settings );
		$last  = end( $settings );

		$this->assertSame( 'title', $first['type'] );
		$this->assertSame( 'sectionend', $last['type'] );
		$this->assertSame( $first['id'], $last['id'], 'The section must open and close on the same id.' );
	}

	public function test_render_outputs_the_manual_sync_controls() {
		$configuration = new Configuration();

		ob_start();
		$configuration->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'wc-facebook-enhanced-settings-sync-products', $output );
		$this->assertStringContainsString( 'wc-facebook-enhanced-settings-sync-coupons', $output );
		$this->assertStringContainsString( 'wc-facebook-enhanced-settings-sync-shipping-profiles', $output );
		$this->assertStringContainsString( 'wc-facebook-enhanced-settings-sync-navigation-menu', $output );
	}

	public function test_render_does_not_wrap_controls_in_a_drawer() {
		$configuration = new Configuration();

		ob_start();
		$configuration->render();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'troubleshooting-drawer', $output );
		$this->assertStringNotContainsString( 'drawer-toggle-button', $output );
	}

	/**
	 * Registers a stub point-of-sale integration for the duration of a test.
	 *
	 * @param bool   $is_supported whether the POS plugin should report as active.
	 * @param string $name         the display name.
	 */
	private function register_pos( bool $is_supported, string $name = 'Stub POS' ): void {
		$integration = $this->createMock( POS_Integration_Interface::class );
		$integration->method( 'get_slug' )->willReturn( 'stub' );
		$integration->method( 'get_name' )->willReturn( $name );
		$integration->method( 'is_supported' )->willReturn( $is_supported );
		$integration->method( 'is_pos_order' )->willReturn( false );
		$integration->method( 'get_event_data' )->willReturn( array() );

		$this->add_filter_with_safe_teardown(
			'wc_facebook_pos_integrations',
			static function () use ( $integration ) {
				return array( $integration );
			}
		);
	}

	/**
	 * Gets the offline events setting definition.
	 *
	 * @return array|null
	 */
	private function offline_events_setting(): ?array {
		return $this->find_setting(
			Configuration::get_plugin_settings(),
			'wc_facebook_enable_offline_purchase_events'
		);
	}

	public function test_offline_events_setting_is_present_and_defaults_to_disabled() {
		$this->register_pos( true );

		$setting = $this->offline_events_setting();

		$this->assertNotNull( $setting );
		$this->assertSame( 'checkbox', $setting['type'] );
		$this->assertSame( 'no', $setting['default'] );
		$this->assertSame( 'Enable Offline Events', $setting['title'] );
		$this->assertStringContainsString( 'Omni-channel Ads', $setting['desc'] );
		$this->assertStringContainsString( Configuration::OFFLINE_EVENTS_DOC_URL, $setting['desc'] );
	}

	public function test_description_introduces_pos_before_the_status_line_refers_to_it() {
		// The status line talks about "supported POS plugins", so the description
		// above it has to establish that POS plugins are what produce these events.
		$this->register_pos( true, 'WCPOS' );

		$desc = $this->offline_events_setting()['desc'];

		$pos_mention    = strpos( $desc, 'point of sale (POS)' );
		$status_mention = strpos( $desc, 'POS plugins:' );

		$this->assertNotFalse( $pos_mention, 'The description must explain that POS plugins produce these events.' );
		$this->assertNotFalse( $status_mention );
		$this->assertLessThan( $status_mention, $pos_mention, 'POS must be introduced before the status line refers to it.' );
	}

	public function test_learn_more_points_at_the_in_repo_documentation() {
		$this->assertSame(
			'https://github.com/facebook/facebook-for-woocommerce/blob/main/docs/offline-events.md',
			Configuration::OFFLINE_EVENTS_DOC_URL
		);
	}

	public function test_given_a_pos_is_active_then_the_offline_events_checkbox_is_operable() {
		$this->register_pos( true );

		$this->assertFalse( $this->offline_events_setting()['disabled'] );
	}

	public function test_given_no_pos_is_active_then_the_offline_events_checkbox_is_disabled() {
		$this->register_pos( false );

		$this->assertTrue( $this->offline_events_setting()['disabled'] );
	}

	public function test_detected_pos_is_named_inside_the_offline_events_description() {
		// The status belongs to the checkbox's own description, not a separate row,
		// so it reads as context for the control it explains.
		$this->register_pos( true, 'WCPOS' );

		$desc = $this->offline_events_setting()['desc'];

		$this->assertStringContainsString( 'Detected supported POS plugins: WCPOS', $desc );
		$this->assertStringContainsString( 'wc-facebook-pos-status', $desc );
		$this->assertStringNotContainsString( 'No supported POS plugin detected', $desc );
	}

	public function test_description_explains_when_no_pos_is_detected() {
		$this->register_pos( false, 'WCPOS' );

		$desc = $this->offline_events_setting()['desc'];

		$this->assertStringContainsString( 'No supported POS plugin detected', $desc );
		// The inactive integration is still named, so the merchant knows what to install.
		$this->assertStringContainsString( 'WCPOS', $desc );
	}

	public function test_offline_events_description_survives_woocommerce_sanitization() {
		// WooCommerce runs checkbox descriptions through wp_kses_post(), so the link
		// and the status span must both be in its allowlist.
		$this->register_pos( true, 'WCPOS' );

		$desc = $this->offline_events_setting()['desc'];

		$this->assertSame( $desc, wp_kses_post( $desc ) );
	}

	public function test_no_separate_pos_status_row_is_registered() {
		// The status used to be its own custom field type; it must not render twice.
		$this->assertNull( $this->find_setting( Configuration::get_plugin_settings(), 'pos_integration_status' ) );

		foreach ( Configuration::get_plugin_settings() as $setting ) {
			$this->assertNotSame( 'pos_integration_status', $setting['type'] ?? '' );
		}
	}
}
