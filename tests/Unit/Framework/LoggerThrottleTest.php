<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Framework;

use WooCommerce\Facebook\Framework\Logger;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for the 'throttle' log option on WooCommerce\Facebook\Framework\Logger.
 *
 * Asserts against the Meta sink, which is directly observable: it appends each log to the
 * LOGGING_MESSAGE_QUEUE transient.
 *
 * @covers \WooCommerce\Facebook\Framework\Logger::log
 */
class LoggerThrottleTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/** @var string[] transient keys to clean up after each test */
	private $transients = array();

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->reset_log_state();
		$this->enable_diagnosis();
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		$this->reset_log_state();

		parent::tearDown();
	}

	/**
	 * Turns the Meta sink on, which is the only gate the throttle sits behind in these tests.
	 */
	private function enable_diagnosis(): void {
		$this->mock_set_option( Logger::SETTING_ENABLE_META_DIAGNOSIS, 'yes' );
	}

	/**
	 * Turns the Meta sink off.
	 */
	private function disable_diagnosis(): void {
		$this->mock_set_option( Logger::SETTING_ENABLE_META_DIAGNOSIS, 'no' );
	}

	/**
	 * Clears the queued logs and every throttle transient touched by these tests.
	 */
	private function reset_log_state(): void {
		delete_transient( Logger::LOGGING_MESSAGE_QUEUE );

		foreach ( $this->transients as $transient ) {
			delete_transient( $transient );
		}

		$this->transients = array();
	}

	/**
	 * Records a throttle transient so tearDown can remove it.
	 *
	 * @param string $key   the throttle key
	 * @param bool   $group whether the key identifies a group rather than a single occurrence
	 */
	private function track_throttle_transient( string $key, bool $group = false ): void {
		$this->transients[] = Logger::THROTTLE_TRANSIENT_PREFIX . ( $group ? 'group_' : '' ) . md5( $key );
	}

	/**
	 * Counts the logs currently queued for Meta.
	 *
	 * @return int
	 */
	private function queued_log_count(): int {
		$logs = get_transient( Logger::LOGGING_MESSAGE_QUEUE );

		return is_array( $logs ) ? count( $logs ) : 0;
	}

	/**
	 * Builds the log options used by these tests.
	 *
	 * @param array $throttle the throttle options, or an empty array for no throttling
	 * @return array
	 */
	private function log_options( array $throttle = array() ): array {
		$options = array(
			'should_send_log_to_meta'        => true,
			'should_save_log_in_woocommerce' => false,
			'woocommerce_log_level'          => \WC_Log_Levels::WARNING,
		);

		if ( $throttle ) {
			$options['throttle'] = $throttle;
		}

		return $options;
	}

	/**
	 * A log without a 'throttle' option is never suppressed.
	 */
	public function test_logs_without_throttle_are_not_suppressed(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			Logger::log( 'no throttle', array( 'event' => 'test' ), $this->log_options() );
		}

		$this->assertSame( 5, $this->queued_log_count(), 'Unthrottled logs should all be written' );
	}

	/**
	 * Repeats of the same throttle key within the interval are suppressed.
	 */
	public function test_repeated_key_is_logged_once_within_the_interval(): void {
		$this->track_throttle_transient( 'clamp:1' );

		for ( $i = 0; $i < 5; $i++ ) {
			Logger::log(
				'repeated',
				array( 'event' => 'test' ),
				$this->log_options( array( 'key' => 'clamp:1', 'interval' => HOUR_IN_SECONDS ) )
			);
		}

		$this->assertSame( 1, $this->queued_log_count(), 'Only the first log for a key should be written' );
	}

	/**
	 * Distinct keys are throttled independently when no group cap applies.
	 */
	public function test_distinct_keys_are_throttled_independently(): void {
		foreach ( array( 'clamp:1', 'clamp:2', 'clamp:3' ) as $key ) {
			$this->track_throttle_transient( $key );

			// Twice each: the second call for a key should be suppressed.
			Logger::log( 'distinct', array( 'event' => 'test' ), $this->log_options( array( 'key' => $key ) ) );
			Logger::log( 'distinct', array( 'event' => 'test' ), $this->log_options( array( 'key' => $key ) ) );
		}

		$this->assertSame( 3, $this->queued_log_count(), 'Each distinct key should be logged once' );
	}

	/**
	 * The group cap limits the total across distinct keys, so a caller walking many keys
	 * (a bot adding oversized quantities across a catalogue) cannot flood the log.
	 */
	public function test_group_cap_limits_total_across_distinct_keys(): void {
		$this->track_throttle_transient( 'clamp_group', true );

		for ( $i = 0; $i < 10; $i++ ) {
			$key = 'clamp:' . $i;
			$this->track_throttle_transient( $key );

			Logger::log(
				'grouped',
				array( 'event' => 'test' ),
				$this->log_options(
					array(
						'key'         => $key,
						'group'       => 'clamp_group',
						'max_per_day' => 3,
					)
				)
			);
		}

		$this->assertSame( 3, $this->queued_log_count(), 'The group cap should stop logging past max_per_day' );
	}

	/**
	 * A zero or missing max_per_day leaves the group uncapped.
	 */
	public function test_group_without_max_per_day_is_uncapped(): void {
		for ( $i = 0; $i < 6; $i++ ) {
			$key = 'uncapped:' . $i;
			$this->track_throttle_transient( $key );

			Logger::log(
				'uncapped',
				array( 'event' => 'test' ),
				$this->log_options( array( 'key' => $key, 'group' => 'uncapped_group' ) )
			);
		}

		$this->assertSame( 6, $this->queued_log_count(), 'Without max_per_day the group should not cap' );
	}

	/**
	 * An empty throttle key opts out of throttling rather than collapsing every caller
	 * onto one shared bucket.
	 */
	public function test_empty_key_is_not_throttled(): void {
		for ( $i = 0; $i < 4; $i++ ) {
			Logger::log( 'empty key', array( 'event' => 'test' ), $this->log_options( array( 'key' => '' ) ) );
		}

		$this->assertSame( 4, $this->queued_log_count(), 'An empty key should disable throttling' );
	}

	/**
	 * A suppressed log must not spend the throttle budget. With every sink disabled nothing is
	 * written, so turning a sink on afterwards should still produce a log rather than finding
	 * the interval already consumed.
	 */
	public function test_throttle_budget_is_not_spent_while_sinks_are_disabled(): void {
		$this->track_throttle_transient( 'clamp:disabled' );
		$this->track_throttle_transient( 'clamp_group', true );

		$options = $this->log_options(
			array(
				'key'         => 'clamp:disabled',
				'group'       => 'clamp_group',
				'max_per_day' => 10,
			)
		);

		$this->disable_diagnosis();

		for ( $i = 0; $i < 3; $i++ ) {
			Logger::log( 'while disabled', array( 'event' => 'test' ), $options );
		}

		$this->assertSame( 0, $this->queued_log_count(), 'Nothing should be written while sinks are disabled' );

		$this->enable_diagnosis();

		Logger::log( 'after enabling', array( 'event' => 'test' ), $options );

		$this->assertSame(
			1,
			$this->queued_log_count(),
			'Enabling a sink should produce a log; the disabled calls must not have consumed the interval'
		);
	}

	/**
	 * The group counter likewise only advances for logs that were actually written.
	 */
	public function test_group_counter_does_not_advance_while_sinks_are_disabled(): void {
		$this->track_throttle_transient( 'clamp_group', true );

		$this->disable_diagnosis();

		for ( $i = 0; $i < 5; $i++ ) {
			$key = 'suppressed:' . $i;
			$this->track_throttle_transient( $key );

			Logger::log(
				'while disabled',
				array( 'event' => 'test' ),
				$this->log_options( array( 'key' => $key, 'group' => 'clamp_group', 'max_per_day' => 3 ) )
			);
		}

		$this->enable_diagnosis();

		for ( $i = 0; $i < 3; $i++ ) {
			$key = 'written:' . $i;
			$this->track_throttle_transient( $key );

			Logger::log(
				'after enabling',
				array( 'event' => 'test' ),
				$this->log_options( array( 'key' => $key, 'group' => 'clamp_group', 'max_per_day' => 3 ) )
			);
		}

		$this->assertSame(
			3,
			$this->queued_log_count(),
			'The full group budget should remain available after suppressed calls'
		);
	}
}
