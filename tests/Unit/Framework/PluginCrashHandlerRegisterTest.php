<?php

declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Framework;

use ReflectionClass;
use WooCommerce\Facebook\Framework\PluginCrashHandler;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for crash handler registration behavior.
 *
 * @since 3.6.4
 */
class PluginCrashHandlerRegisterTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/** @var PluginCrashHandler */
	private $handler;

	/** @var ReflectionClass */
	private $reflection;

	/** @var callable|null */
	private $previous_exception_handler;

	public function setUp(): void {
		parent::setUp();

		$this->handler    = new PluginCrashHandler();
		$this->reflection = new ReflectionClass( PluginCrashHandler::class );

		$is_registered = $this->reflection->getProperty( 'is_registered' );
		$is_registered->setAccessible( true );
		$is_registered->setValue( null, false );

		$this->previous_exception_handler = set_exception_handler( [ $this, 'test_exception_handler' ] );
	}

	public function tearDown(): void {
		if ( is_callable( $this->previous_exception_handler ) ) {
			set_exception_handler( $this->previous_exception_handler );
		} else {
			restore_exception_handler();
		}

		$is_registered = $this->reflection->getProperty( 'is_registered' );
		$is_registered->setAccessible( true );
		$is_registered->setValue( null, false );

		parent::tearDown();
	}

	public function test_register_is_idempotent_in_single_request(): void {
		$this->handler->register();
		$after_first_register = $this->get_previous_exception_handler();

		$this->assertIsArray( $after_first_register );
		$this->assertSame( 'test_exception_handler', $after_first_register[1] ?? '' );

		$this->handler->register();
		$after_second_register = $this->get_previous_exception_handler();

		$this->assertSame(
			$after_first_register,
			$after_second_register,
			'Second register() call should be a no-op in the same request.'
		);
	}

	/**
	 * Test exception handler used as sentinel in this test.
	 *
	 * @param \Throwable $throwable thrown exception.
	 */
	public function test_exception_handler( \Throwable $throwable ): void {
		unset( $throwable );
	}

	/**
	 * Reads private previous_exception_handler from the crash handler.
	 *
	 * @return mixed
	 */
	private function get_previous_exception_handler() {
		$property = $this->reflection->getProperty( 'previous_exception_handler' );
		$property->setAccessible( true );
		return $property->getValue( $this->handler );
	}
}
