<?php
/**
 * Copyright (c) Meta, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit;

use WC_Facebookcommerce_Integration;
use WooCommerce\Facebook\Events\POS\POS_Integration_Interface;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;

/**
 * Tests that the offline events opt-in time is recorded and reset.
 *
 * Uses real options rather than the option-isolating base class: the opt-in time
 * is recorded from the update_option_{$option} hook, which an intercepted
 * update_option() never fires.
 *
 * @covers WC_Facebookcommerce_Integration
 */
class OfflineEventsOptInTimeTest extends AbstractWPUnitTestWithSafeFiltering {

	public function setUp(): void {
		parent::setUp();

		delete_option( WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS );
		delete_option( WC_Facebookcommerce_Integration::OPTION_OFFLINE_PURCHASE_EVENTS_OPTED_IN_AT );
		delete_option( WC_Facebookcommerce_Integration::OPTION_OFFLINE_PURCHASE_EVENTS_EVER_OPTED_IN );
	}

	/**
	 * Reads the stored opt-in time without the getter's initialize-if-missing behaviour.
	 *
	 * @return int
	 */
	private function stored_opt_in_time(): int {
		return (int) get_option( WC_Facebookcommerce_Integration::OPTION_OFFLINE_PURCHASE_EVENTS_OPTED_IN_AT, 0 );
	}

	public function test_enabling_for_the_first_time_records_the_opt_in_time() {
		$before = time();

		add_option( WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS, 'yes' );

		$this->assertGreaterThanOrEqual( $before, $this->stored_opt_in_time() );
	}

	public function test_switching_on_records_the_opt_in_time() {
		add_option( WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS, 'no' );
		$this->assertSame( 0, $this->stored_opt_in_time() );

		$before = time();
		update_option( WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS, 'yes' );

		$this->assertGreaterThanOrEqual( $before, $this->stored_opt_in_time() );
	}

	public function test_switching_off_clears_the_opt_in_time() {
		add_option( WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS, 'yes' );

		update_option( WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS, 'no' );

		$this->assertSame( 0, $this->stored_opt_in_time() );
	}

	public function test_re_enabling_starts_a_fresh_window() {
		// Sales paid while the feature was off must not be reported after it is
		// turned back on.
		add_option( WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS, 'yes' );
		update_option( WC_Facebookcommerce_Integration::OPTION_OFFLINE_PURCHASE_EVENTS_OPTED_IN_AT, 1000, false );

		update_option( WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS, 'no' );
		$before = time();
		update_option( WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS, 'yes' );

		$this->assertGreaterThanOrEqual( $before, $this->stored_opt_in_time() );
	}

	public function test_saving_while_already_enabled_keeps_the_original_opt_in_time() {
		add_option( WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS, 'yes' );
		update_option( WC_Facebookcommerce_Integration::OPTION_OFFLINE_PURCHASE_EVENTS_OPTED_IN_AT, 1000, false );

		// Same value, so WordPress does not treat it as a change.
		update_option( WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS, 'yes' );

		$this->assertSame( 1000, $this->stored_opt_in_time() );
	}

	public function test_getter_records_now_when_no_opt_in_time_is_stored() {
		// E.g. the feature was enabled through the filter rather than the setting.
		$before = time();

		$opted_in_at = facebook_for_woocommerce()->get_integration()->get_offline_purchase_events_opted_in_at();

		$this->assertGreaterThanOrEqual( $before, $opted_in_at );
		$this->assertSame( $opted_in_at, $this->stored_opt_in_time(), 'The recorded time must persist.' );
	}

	/**
	 * Gets the integration under test.
	 *
	 * @return WC_Facebookcommerce_Integration
	 */
	private function integration(): WC_Facebookcommerce_Integration {
		return facebook_for_woocommerce()->get_integration();
	}

	/**
	 * Registers a stub point-of-sale integration.
	 *
	 * @param bool $is_supported whether its plugin is active.
	 */
	private function register_pos( bool $is_supported ): void {
		$integration = $this->createMock( POS_Integration_Interface::class );
		$integration->method( 'is_supported' )->willReturn( $is_supported );

		$this->add_filter_with_safe_teardown(
			'wc_facebook_pos_integrations',
			static function () use ( $integration ) {
				return array( $integration );
			}
		);
	}

	public function test_store_that_never_opted_in_has_not_ever_opted_in() {
		$this->assertFalse( $this->integration()->has_ever_opted_in_to_offline_purchase_events() );
	}

	public function test_switching_on_records_that_the_store_has_ever_opted_in() {
		add_option( WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS, 'yes' );

		$this->assertSame( 'yes', get_option( WC_Facebookcommerce_Integration::OPTION_OFFLINE_PURCHASE_EVENTS_EVER_OPTED_IN ) );
		$this->assertTrue( $this->integration()->has_ever_opted_in_to_offline_purchase_events() );
	}

	public function test_ever_opted_in_survives_opting_out() {
		// Unlike the opt-in time, which is cleared.
		add_option( WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS, 'yes' );
		update_option( WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS, 'no' );

		$this->assertSame( 0, $this->stored_opt_in_time() );
		$this->assertTrue( $this->integration()->has_ever_opted_in_to_offline_purchase_events() );
	}

	public function test_store_enabled_before_the_record_existed_counts_as_opted_in() {
		// Enabled directly, without the hook that writes the record.
		$this->add_filter_with_safe_teardown(
			'pre_option_' . WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS,
			static function () {
				return 'yes';
			}
		);

		$this->assertFalse( (bool) get_option( WC_Facebookcommerce_Integration::OPTION_OFFLINE_PURCHASE_EVENTS_EVER_OPTED_IN ) );
		$this->assertTrue( $this->integration()->has_ever_opted_in_to_offline_purchase_events() );
	}

	public function test_offline_events_can_be_enabled_while_a_supported_pos_is_active() {
		$this->register_pos( true );

		$this->assertTrue( $this->integration()->can_enable_offline_purchase_events() );
	}

	public function test_offline_events_cannot_be_enabled_without_an_active_pos() {
		$this->register_pos( false );

		$this->assertFalse( $this->integration()->can_enable_offline_purchase_events() );
	}
}
