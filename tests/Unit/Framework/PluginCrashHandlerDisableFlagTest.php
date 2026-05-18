<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Framework;

use ReflectionClass;
use WooCommerce\Facebook\Framework\PluginCrashHandler;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for crash disable-flag writes.
 *
 * @since 3.6.4
 */
class PluginCrashHandlerDisableFlagTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/** @var PluginCrashHandler */
	private $handler;

	/** @var ReflectionClass */
	private $reflection;

	/** @var string */
	private $flag_file_path;

	public function setUp(): void {
		parent::setUp();

		$this->handler        = new PluginCrashHandler();
		$this->reflection     = new ReflectionClass( PluginCrashHandler::class );
		$this->flag_file_path = trailingslashit( WP_CONTENT_DIR ) . 'uploads/facebook-for-woocommerce/.disabled';

		delete_transient( PluginCrashHandler::DISABLE_FLAG_TRANSIENT );
		if ( file_exists( $this->flag_file_path ) ) {
			@unlink( $this->flag_file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
	}

	public function tearDown(): void {
		delete_transient( PluginCrashHandler::DISABLE_FLAG_TRANSIENT );
		if ( file_exists( $this->flag_file_path ) ) {
			@unlink( $this->flag_file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
		parent::tearDown();
	}

	public function test_write_disable_flag_increments_count_and_sets_timestamp(): void {
		// Seed prior crash count to verify increment behavior.
		set_transient(
			PluginCrashHandler::DISABLE_FLAG_TRANSIENT,
			[
				'timestamp'   => time() - 300,
				'crash_count' => 2,
			],
			0
		);

		$before = time();

		$write_disable_flag = $this->reflection->getMethod( 'write_disable_flag' );
		$write_disable_flag->setAccessible( true );
		$write_disable_flag->invoke( $this->handler );

		$payload = $this->read_disable_flag_payload();

		$this->assertIsArray( $payload, 'Disable flag payload should be written to file or transient.' );
		$this->assertArrayHasKey( 'crash_count', $payload );
		$this->assertArrayHasKey( 'timestamp', $payload );
		$this->assertSame( 3, (int) $payload['crash_count'], 'Crash count should increment from prior value.' );
		$this->assertGreaterThanOrEqual( $before, (int) $payload['timestamp'], 'Timestamp should be set for the new write.' );
	}

	/**
	 * Reads disable-flag payload from primary file or transient fallback.
	 *
	 * @return array|null
	 */
	private function read_disable_flag_payload() {
		if ( file_exists( $this->flag_file_path ) && is_readable( $this->flag_file_path ) ) {
			$raw = @file_get_contents( $this->flag_file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( is_string( $raw ) && '' !== $raw ) {
				$decoded = json_decode( $raw, true );
				if ( is_array( $decoded ) ) {
					return $decoded;
				}
			}
		}

		$fallback = get_transient( PluginCrashHandler::DISABLE_FLAG_TRANSIENT );
		return is_array( $fallback ) ? $fallback : null;
	}
}
