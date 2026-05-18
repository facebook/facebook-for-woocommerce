<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests;

use ReflectionClass;

/**
 * Unit test for clearing bootstrap disable-flag state.
 *
 * @since 3.6.4
 */
class BootstrapDisableFlagClearStateTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/** @var \WC_Facebook_Loader */
	private $loader;

	/** @var ReflectionClass */
	private $reflection;

	/** @var string */
	private $flag_file_path;

	public function setUp(): void {
		parent::setUp();
		$this->loader         = \WC_Facebook_Loader::instance();
		$this->reflection     = new ReflectionClass( \WC_Facebook_Loader::class );
		$this->flag_file_path = trailingslashit( WP_CONTENT_DIR ) . \WC_Facebook_Loader::DISABLE_FLAG_FILE_RELATIVE_PATH;

		delete_transient( \WC_Facebook_Loader::DISABLE_FLAG_TRANSIENT );
		if ( file_exists( $this->flag_file_path ) ) {
			@unlink( $this->flag_file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
	}

	public function tearDown(): void {
		delete_transient( \WC_Facebook_Loader::DISABLE_FLAG_TRANSIENT );
		if ( file_exists( $this->flag_file_path ) ) {
			@unlink( $this->flag_file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
		parent::tearDown();
	}

	public function test_clear_disable_flag_state_removes_file_and_transient(): void {
		$flag_dir = dirname( $this->flag_file_path );
		if ( ! is_dir( $flag_dir ) ) {
			wp_mkdir_p( $flag_dir );
		}

		$payload = [
			'timestamp'   => time(),
			'crash_count' => 3,
		];

		file_put_contents( $this->flag_file_path, wp_json_encode( $payload ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		set_transient( \WC_Facebook_Loader::DISABLE_FLAG_TRANSIENT, $payload, HOUR_IN_SECONDS );

		$clear_method = $this->reflection->getMethod( 'clear_disable_flag_state' );
		$clear_method->setAccessible( true );
		$cleared = (bool) $clear_method->invoke( $this->loader );

		$this->assertTrue( $cleared, 'Disable flag clear operation should report success.' );
		$this->assertFileDoesNotExist( $this->flag_file_path, 'Disable flag file should be removed.' );
		$this->assertFalse( get_transient( \WC_Facebook_Loader::DISABLE_FLAG_TRANSIENT ), 'Disable transient should be removed.' );

		$has_valid_method = $this->reflection->getMethod( 'has_valid_disable_flag' );
		$has_valid_method->setAccessible( true );
		$this->assertFalse( (bool) $has_valid_method->invoke( $this->loader ), 'Loader should not remain disabled after clear.' );
	}
}
