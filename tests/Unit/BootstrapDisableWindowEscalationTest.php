<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests;

use ReflectionClass;

/**
 * Unit test for disable-window escalation by crash count.
 *
 * @since 3.6.4
 */
class BootstrapDisableWindowEscalationTest extends AbstractWPUnitTestWithSafeFiltering {

	/** @var \WC_Facebook_Loader */
	private $loader;

	/** @var ReflectionClass */
	private $reflection;

	public function setUp(): void {
		parent::setUp();
		$this->loader     = \WC_Facebook_Loader::instance();
		$this->reflection = new ReflectionClass( \WC_Facebook_Loader::class );
	}

	public function test_disable_window_escalates_with_crash_count(): void {
		$get_disable_window_seconds = $this->reflection->getMethod( 'get_disable_window_seconds' );
		$get_disable_window_seconds->setAccessible( true );

		// 1st crash => temporary 10-minute disable window.
		$this->assertSame( 10 * MINUTE_IN_SECONDS, $get_disable_window_seconds->invoke( $this->loader, 1 ) );

		// 2nd crash => temporary 1-hour disable window.
		$this->assertSame( HOUR_IN_SECONDS, $get_disable_window_seconds->invoke( $this->loader, 2 ) );

		// 3rd+ crash => permanent disable (no automatic re-enable window).
		$this->assertNull( $get_disable_window_seconds->invoke( $this->loader, 3 ) );
		$this->assertNull( $get_disable_window_seconds->invoke( $this->loader, 4 ) );
	}
}
