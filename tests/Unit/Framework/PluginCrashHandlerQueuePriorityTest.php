<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Framework;

use WooCommerce\Facebook\Framework\PluginCrashHandler;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for queue-priority trimming in PluginCrashHandler.
 *
 * @since 3.6.4
 */
class PluginCrashHandlerQueuePriorityTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * @var TestablePluginCrashHandler
	 */
	private $handler;

	public function setUp(): void {
		parent::setUp();
		$this->handler = new TestablePluginCrashHandler();
	}

	public function test_higher_value_incoming_replaces_lower_value_queued_report(): void {
		$pending = [
			new FakeQueueAction( 101, [ $this->make_report( 'low_fp', 1 ) ] ),
			new FakeQueueAction( 102, [ $this->make_report( 'mid_fp', 3 ) ] ),
		];

		$this->handler->set_pending_actions( $pending );
		$incoming = $this->make_report( 'high_fp', 6 );

		$this->assertTrue( $this->handler->exposed_trim_queue_for_prioritized_report( $incoming, 50 ) );
		$this->assertEquals( $this->make_report( 'low_fp', 1 ), $this->handler->last_unscheduled_report );
		$this->assertSame( 101, $this->handler->last_unscheduled_action_id );

		$this->assertNotEmpty( $this->handler->logged_messages );
		$this->assertStringContainsString( 'reason=queue_priority_replace', implode( "\n", $this->handler->logged_messages ) );
	}

	public function test_equal_or_lower_value_incoming_is_dropped(): void {
		$pending = [
			new FakeQueueAction( 201, [ $this->make_report( 'low_fp', 2 ) ] ),
			new FakeQueueAction( 202, [ $this->make_report( 'high_fp', 7 ) ] ),
		];

		$this->handler->set_pending_actions( $pending );
		$incoming = $this->make_report( 'equal_fp', 2 );

		$this->assertFalse( $this->handler->exposed_trim_queue_for_prioritized_report( $incoming, 50 ) );
		$this->assertNull( $this->handler->last_unscheduled_report );

		$this->assertNotEmpty( $this->handler->logged_messages );
		$this->assertStringContainsString( 'reason=queue_priority', implode( "\n", $this->handler->logged_messages ) );
	}

	public function test_queue_size_remains_bounded_after_priority_replacement_path(): void {
		$pending = [
			new FakeQueueAction( 301, [ $this->make_report( 'low_1', 1 ) ] ),
			new FakeQueueAction( 302, [ $this->make_report( 'low_2', 1 ) ] ),
			new FakeQueueAction( 303, [ $this->make_report( 'mid_1', 2 ) ] ),
		];

		$this->handler->set_pending_actions( $pending );
		$before_count = count( $this->handler->get_pending_crash_queue_actions( 100 ) );

		$this->assertTrue( $this->handler->exposed_trim_queue_for_prioritized_report( $this->make_report( 'incoming', 5 ), 50 ) );

		$after_count = count( $this->handler->get_pending_crash_queue_actions( 100 ) );
		$this->assertEquals( $before_count - 1, $after_count, 'One pending action should be removed before incoming enqueue.' );
	}

	private function make_report( string $fingerprint, int $aggregate_count ): array {
		return [
			'event'      => 'plugin_crash',
			'event_type' => 'fatal_error',
			'extra_data' => [
				'fingerprint'     => $fingerprint,
				'aggregate_count' => $aggregate_count,
			],
		];
	}
}

/**
 * Test double exposing queue-priority behavior without touching real Action Scheduler.
 */
class TestablePluginCrashHandler extends PluginCrashHandler {

	/** @var int */
	public $last_unscheduled_action_id = 0;

	/** @var array */
	private $pending_actions = [];

	/** @var array */
	public $logged_messages = [];

	/** @var array|null */
	public $last_unscheduled_report = null;

	public function set_pending_actions( array $actions ): void {
		$this->pending_actions = $actions;
	}

	public function exposed_trim_queue_for_prioritized_report( array $incoming, int $queue_size ): bool {
		return $this->maybe_trim_queue_for_prioritized_report( $incoming, $queue_size );
	}

	protected function get_pending_crash_queue_actions( $per_page ) {
		return array_slice( $this->pending_actions, 0, (int) $per_page );
	}

	protected function unschedule_pending_crash_action( array $report, $action_id = 0 ) {
		$this->last_unscheduled_report    = $report;
		$this->last_unscheduled_action_id = (int) $action_id;

		foreach ( $this->pending_actions as $index => $action ) {
			if ( ! is_object( $action ) || ! method_exists( $action, 'get_args' ) ) {
				continue;
			}

			if ( $action_id > 0 && method_exists( $action, 'get_id' ) && (int) $action->get_id() !== (int) $action_id ) {
				continue;
			}

			$args = $action->get_args();
			if ( isset( $args[0] ) && is_array( $args[0] ) && $args[0] === $report ) {
				unset( $this->pending_actions[ $index ] );
				$this->pending_actions = array_values( $this->pending_actions );
				return 1;
			}
		}

		return 0;
	}

	protected function log_crash_observability( $message ) {
		$this->logged_messages[] = (string) $message;
	}
}

/**
 * Minimal fake action object compatible with get_args() usage.
 */
class FakeQueueAction {
	/** @var int */
	private $id;

	/** @var array */
	private $args;

	public function __construct( int $id, array $args ) {
		$this->id   = $id;
		$this->args = $args;
	}

	public function get_id(): int {
		return $this->id;
	}

	public function get_args(): array {
		return $this->args;
	}
}
