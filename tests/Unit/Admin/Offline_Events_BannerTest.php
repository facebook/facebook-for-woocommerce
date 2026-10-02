<?php
/**
 * Copyright (c) Meta, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Admin;

use WC_Facebookcommerce_Integration;
use WooCommerce\Facebook\Admin\Offline_Events_Banner;
use WooCommerce\Facebook\Admin\Settings_Screens\Configuration;
use WooCommerce\Facebook\Events\POS\POS_Integration_Interface;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;

/**
 * Tests the banner introducing offline events.
 *
 * It must appear only on the plugin's page, only to admins of stores with a supported
 * POS active that have never opted in, and only until that admin dismisses it.
 *
 * @covers \WooCommerce\Facebook\Admin\Offline_Events_Banner
 */
class Offline_Events_BannerTest extends AbstractWPUnitTestWithSafeFiltering {

	/** @var int */
	private $admin_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );

		// A connected store: is_connected() is whether an access token exists.
		$this->add_filter_with_safe_teardown(
			'wc_facebook_connection_access_token',
			static function () {
				return 'test-access-token';
			}
		);

		// is_admin() is true once a screen is set.
		set_current_screen( 'dashboard' );
		$this->open_page( 'wc-facebook' );

		delete_option( WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS );
		delete_option( WC_Facebookcommerce_Integration::OPTION_OFFLINE_PURCHASE_EVENTS_EVER_OPTED_IN );
		delete_option( WC_Facebookcommerce_Integration::OPTION_OFFLINE_PURCHASE_EVENTS_OPTED_IN_AT );

		$this->clear_registered_notices();
	}

	public function tearDown(): void {
		$this->clear_registered_notices();
		unset( $_GET['page'], $_REQUEST['page'] );
		set_current_screen( 'front' );

		delete_option( WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS );
		delete_option( WC_Facebookcommerce_Integration::OPTION_OFFLINE_PURCHASE_EVENTS_EVER_OPTED_IN );
		delete_option( WC_Facebookcommerce_Integration::OPTION_OFFLINE_PURCHASE_EVENTS_OPTED_IN_AT );

		parent::tearDown();
	}

	/**
	 * Simulates a request to the given admin page.
	 *
	 * @param string $page the `page` query arg.
	 */
	private function open_page( string $page ): void {
		$_GET['page']     = $page;
		$_REQUEST['page'] = $page;
	}

	/**
	 * Registers a stub point-of-sale integration.
	 *
	 * @param bool $is_supported whether its plugin is active.
	 */
	private function register_pos( bool $is_supported ): void {
		$integration = $this->createMock( POS_Integration_Interface::class );
		$integration->method( 'get_slug' )->willReturn( 'wcpos' );
		$integration->method( 'get_name' )->willReturn( 'WCPOS' );
		$integration->method( 'is_supported' )->willReturn( $is_supported );

		$this->add_filter_with_safe_teardown(
			'wc_facebook_pos_integrations',
			static function () use ( $integration ) {
				return array( $integration );
			}
		);
	}

	/**
	 * Gets the notice handler's private list of registered notices.
	 *
	 * @return \ReflectionProperty
	 */
	private function notices_property(): \ReflectionProperty {
		$property = ( new \ReflectionObject( facebook_for_woocommerce()->get_admin_notice_handler() ) )->getProperty( 'admin_notices' );
		$property->setAccessible( true );

		return $property;
	}

	private function clear_registered_notices(): void {
		$this->notices_property()->setValue( facebook_for_woocommerce()->get_admin_notice_handler(), array() );
	}

	/**
	 * Determines whether the banner was registered on this request.
	 *
	 * @return bool
	 */
	private function banner_was_added(): bool {
		$notices = $this->notices_property()->getValue( facebook_for_woocommerce()->get_admin_notice_handler() );

		return isset( $notices[ Offline_Events_Banner::NOTICE_ID ] );
	}

	public function test_banner_shows_for_a_store_with_a_pos_that_never_opted_in() {
		$this->register_pos( true );

		( new Offline_Events_Banner() )->maybe_add_notice();

		$this->assertTrue( $this->banner_was_added() );
	}

	public function test_banner_does_not_show_until_the_store_is_connected() {
		// Disconnected stores have no Configuration tab to send the merchant to.
		$this->register_pos( true );
		$this->teardown_callback_category_safely( 'wc_facebook_connection_access_token' );
		delete_option( 'wc_facebook_access_token' );

		( new Offline_Events_Banner() )->maybe_add_notice();

		$this->assertFalse( $this->banner_was_added() );
	}

	public function test_banner_does_not_show_without_an_active_pos() {
		// WCPOS installed but inactive, or no POS at all: the setting cannot be enabled.
		$this->register_pos( false );

		( new Offline_Events_Banner() )->maybe_add_notice();

		$this->assertFalse( $this->banner_was_added() );
	}

	public function test_banner_does_not_show_once_opted_in() {
		$this->register_pos( true );
		update_option( WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS, 'yes' );

		( new Offline_Events_Banner() )->maybe_add_notice();

		$this->assertFalse( $this->banner_was_added() );
	}

	public function test_banner_does_not_return_after_opting_out() {
		// A merchant who tried the feature and turned it off is not pitched it as new.
		$this->register_pos( true );
		update_option( WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS, 'yes' );
		update_option( WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS, 'no' );

		( new Offline_Events_Banner() )->maybe_add_notice();

		$this->assertFalse( $this->banner_was_added() );
	}

	public function test_banner_does_not_show_once_dismissed_by_this_admin() {
		$this->register_pos( true );
		facebook_for_woocommerce()->get_admin_notice_handler()->dismiss_notice( Offline_Events_Banner::NOTICE_ID, $this->admin_id );

		( new Offline_Events_Banner() )->maybe_add_notice();

		$this->assertFalse( $this->banner_was_added(), 'Dismissal must hold on the plugin page itself.' );
	}

	public function test_dismissal_is_per_admin() {
		$this->register_pos( true );
		facebook_for_woocommerce()->get_admin_notice_handler()->dismiss_notice( Offline_Events_Banner::NOTICE_ID, $this->admin_id );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		( new Offline_Events_Banner() )->maybe_add_notice();

		$this->assertTrue( $this->banner_was_added() );
	}

	public function test_banner_does_not_show_to_users_who_cannot_manage_the_store() {
		$this->register_pos( true );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		( new Offline_Events_Banner() )->maybe_add_notice();

		$this->assertFalse( $this->banner_was_added() );
	}

	public function test_banner_does_not_show_outside_the_plugin_page() {
		$this->register_pos( true );
		$this->open_page( 'wc-settings' );

		( new Offline_Events_Banner() )->maybe_add_notice();

		$this->assertFalse( $this->banner_was_added() );
	}

	public function test_message_links_to_the_setting_and_the_documentation() {
		$this->register_pos( true );

		$message = ( new Offline_Events_Banner() )->get_message();

		$this->assertStringContainsString( 'tab=' . Configuration::ID, $message );
		$this->assertStringContainsString( 'page=wc-facebook', $message );
		$this->assertStringContainsString( Configuration::OFFLINE_EVENTS_DOC_URL, $message );
		$this->assertStringContainsString( 'WCPOS', $message );
	}
}
