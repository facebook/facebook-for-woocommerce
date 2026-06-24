<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Framework {
	/** @var array */
	$fbw_test_enqueued_actions = [];

	if ( ! function_exists( __NAMESPACE__ . '\\as_has_scheduled_action' ) ) {
		function as_has_scheduled_action( $hook, $args = [], $group = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
			return false;
		}
	}

	if ( ! function_exists( __NAMESPACE__ . '\\as_enqueue_async_action' ) ) {
		function as_enqueue_async_action( $hook, $args = [], $group = '', $unique = true ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
			global $fbw_test_enqueued_actions;
			$fbw_test_enqueued_actions[] = [
				'hook'  => $hook,
				'args'  => $args,
				'group' => $group,
			];
			return count( $fbw_test_enqueued_actions );
		}
	}
}

namespace WooCommerce\Facebook\Tests\Unit\Framework {

use ReflectionClass;
use WooCommerce\Facebook\Framework\ErrorLogHandler;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for paused crash aggregation replay behavior.
 *
 * @since 3.6.4
 */
class ErrorLogHandlerPauseReplayTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/** @var ReflectionClass */
	private $reflection;

	public function setUp(): void {
		parent::setUp();
		$this->reflection = new ReflectionClass( ErrorLogHandler::class );

		global $fbw_test_enqueued_actions;
		$fbw_test_enqueued_actions = [];

		delete_transient( ErrorLogHandler::CRASH_REPORTING_PAUSE_KEY );
		delete_transient( ErrorLogHandler::PAUSED_CRASH_AGGREGATE_INDEX_KEY );
	}

	public function tearDown(): void {
		$index = get_transient( ErrorLogHandler::PAUSED_CRASH_AGGREGATE_INDEX_KEY );
		if ( is_array( $index ) ) {
			foreach ( array_keys( $index ) as $fingerprint ) {
				delete_transient( ErrorLogHandler::PAUSED_CRASH_AGGREGATE_KEY_PREFIX . (string) $fingerprint );
			}
		}

		delete_transient( ErrorLogHandler::CRASH_REPORTING_PAUSE_KEY );
		delete_transient( ErrorLogHandler::PAUSED_CRASH_AGGREGATE_INDEX_KEY );
		parent::tearDown();
	}

	public function test_paused_aggregate_is_replayed_after_pause_expires(): void {
		$store_aggregate = $this->reflection->getMethod( 'store_crash_aggregate_only' );
		$store_aggregate->setAccessible( true );

		set_transient( ErrorLogHandler::CRASH_REPORTING_PAUSE_KEY, time() + 60, 60 );

		$store_aggregate->invokeArgs(
			null,
			[
				[
					'event'             => 'plugin_crash',
					'event_type'        => 'fatal_error',
					'exception_message' => 'paused crash sample',
					'extra_data'        => [
						'fingerprint' => 'pause-replay-fp-1',
						'file'        => 'plugin:/includes/test.php',
						'line'        => 99,
					],
				],
			]
		);

		set_transient( ErrorLogHandler::CRASH_REPORTING_PAUSE_KEY, time() - 1, 1 );
		ErrorLogHandler::maybe_replay_paused_crash_aggregates();

		global $fbw_test_enqueued_actions;
		$this->assertNotEmpty( $fbw_test_enqueued_actions, 'Paused crash aggregate should be enqueued for replay.' );
		$this->assertSame( ErrorLogHandler::META_LOG_API, $fbw_test_enqueued_actions[0]['hook'] );

		$replay_payload = $fbw_test_enqueued_actions[0]['args'][0] ?? [];
		$this->assertSame( 'plugin_crash', $replay_payload['event'] ?? '' );
		$this->assertTrue( ! empty( $replay_payload['extra_data']['replayed_after_pause'] ), 'Replay payload should mark replayed_after_pause.' );

		$this->assertFalse( get_transient( ErrorLogHandler::PAUSED_CRASH_AGGREGATE_KEY_PREFIX . 'pause-replay-fp-1' ) );
		$this->assertSame( [], get_transient( ErrorLogHandler::PAUSED_CRASH_AGGREGATE_INDEX_KEY ) );
	}
}
}
