<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Framework;

use ReflectionClass;
use WooCommerce\Facebook\Framework\PluginCrashHandler;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for dedup lock release behavior on pre-enqueue drop paths.
 *
 * @since 3.6.4
 */
class PluginCrashHandlerDedupReleaseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/** @var PluginCrashHandler */
	private $handler;

	/** @var ReflectionClass */
	private $reflection;

	public function setUp(): void {
		parent::setUp();
		$this->handler    = new PluginCrashHandler();
		$this->reflection = new ReflectionClass( PluginCrashHandler::class );
	}

	public function test_lock_is_released_on_drop_path_and_same_report_can_retry(): void {
		$report = [
			'event'             => 'plugin_crash',
			'event_type'        => 'fatal_error',
			'exception_message' => 'Unit test dedup lock release',
			'extra_data'        => [
				'file'           => 'plugin:/includes/unit-test.php',
				'line'           => 123,
				'plugin_version' => 'test',
			],
		];

		$should_queue = $this->reflection->getMethod( 'should_queue_crash_report' );
		$should_queue->setAccessible( true );

		$release_lock = $this->reflection->getMethod( 'release_crash_dedup_lock' );
		$release_lock->setAccessible( true );

		$initial_storage = null;
		$this->assertTrue( $should_queue->invokeArgs( $this->handler, [ $report, &$initial_storage ] ) );

		$blocked_storage = null;
		$this->assertFalse( $should_queue->invokeArgs( $this->handler, [ $report, &$blocked_storage ] ) );

		// Simulate a pre-enqueue drop path (e.g. queue_cap) that must release the dedup lock.
		$release_lock->invokeArgs( $this->handler, [ $report, 'queue_cap', $initial_storage ] );

		$retry_storage = null;
		$this->assertTrue( $should_queue->invokeArgs( $this->handler, [ $report, &$retry_storage ] ) );

		// Cleanup lock created during retry acquire.
		$release_lock->invokeArgs( $this->handler, [ $report, 'test_cleanup', $retry_storage ] );
	}
}
