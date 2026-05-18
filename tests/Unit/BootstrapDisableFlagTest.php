<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests;

use ReflectionClass;
use WooCommerce\Facebook\Framework\ErrorLogHandler;

/**
 * Unit test for bootstrap disable-flag behavior.
 *
 * @since 3.6.4
 */
class BootstrapDisableFlagTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

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
		remove_action( ErrorLogHandler::META_LOG_API, [ $this->loader, 'handle_disabled_mode_crash_report' ], 10 );
		parent::tearDown();
	}

	public function test_active_disable_flag_detected_and_full_init_is_skipped(): void {
		set_transient(
			\WC_Facebook_Loader::DISABLE_FLAG_TRANSIENT,
			[
				'timestamp'   => time(),
				'crash_count' => 1,
			],
			HOUR_IN_SECONDS
		);

		$has_valid_disable_flag = $this->reflection->getMethod( 'has_valid_disable_flag' );
		$has_valid_disable_flag->setAccessible( true );
		$this->assertTrue( $has_valid_disable_flag->invoke( $this->loader ) );

		$this->assert_init_skipped_when_disabled();
	}

	public function test_active_file_disable_flag_detected_and_full_init_is_skipped(): void {
		$flag_dir = dirname( $this->flag_file_path );
		if ( ! is_dir( $flag_dir ) ) {
			wp_mkdir_p( $flag_dir );
		}

		file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$this->flag_file_path,
			wp_json_encode(
				[
					'timestamp'   => time(),
					'crash_count' => 1,
				]
			)
		);

		$has_active_disable_flag_file_only = $this->reflection->getMethod( 'has_active_disable_flag_file_only' );
		$has_active_disable_flag_file_only->setAccessible( true );
		$this->assertTrue( $has_active_disable_flag_file_only->invoke( $this->loader ) );

		$has_valid_disable_flag = $this->reflection->getMethod( 'has_valid_disable_flag' );
		$has_valid_disable_flag->setAccessible( true );
		$this->assertTrue( $has_valid_disable_flag->invoke( $this->loader ) );

		$this->assert_init_skipped_when_disabled();
	}

	/**
	 * Asserts that init_plugin follows the disabled-mode path.
	 */
	private function assert_init_skipped_when_disabled(): void {
		$before_integration_hooks = $this->count_hook_callbacks_with_method( 'init', 'get_integration' );

		$this->loader->init_plugin();

		$this->assertNotFalse(
			has_action( ErrorLogHandler::META_LOG_API, [ $this->loader, 'handle_disabled_mode_crash_report' ] ),
			'Disabled-mode services should be registered when disable flag is active.'
		);

		$after_integration_hooks = $this->count_hook_callbacks_with_method( 'init', 'get_integration' );
		$this->assertSame(
			$before_integration_hooks,
			$after_integration_hooks,
			'Main WC_Facebookcommerce init hook should not be added during disabled bootstrap path.'
		);
	}

	/**
	 * Counts callbacks for a hook that point to a specific method name.
	 *
	 * @param string $hook hook name.
	 * @param string $method method name.
	 * @return int
	 */
	private function count_hook_callbacks_with_method( string $hook, string $method ): int {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $hook ] ) || ! is_object( $wp_filter[ $hook ] ) || ! isset( $wp_filter[ $hook ]->callbacks ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $wp_filter[ $hook ]->callbacks as $priority_callbacks ) {
			foreach ( $priority_callbacks as $callback ) {
				$fn = $callback['function'] ?? null;
				if ( is_array( $fn ) && isset( $fn[1] ) && $method === $fn[1] ) {
					$count++;
				}
			}
		}

		return $count;
	}
}
