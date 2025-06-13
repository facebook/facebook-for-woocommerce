<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\ProductSync;

use WooCommerce\Facebook\ProductSync\ProductExcludedException;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;
use Exception;

/**
 * Unit tests for ProductExcludedException class.
 *
 * @since 3.5.2
 */
class ProductExcludedExceptionTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that ProductExcludedException exists and can be instantiated.
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( ProductExcludedException::class ) );
	}

	/**
	 * Test that ProductExcludedException extends Exception.
	 */
	public function test_extends_exception() {
		$exception = new ProductExcludedException();
		$this->assertInstanceOf( Exception::class, $exception );
		$this->assertInstanceOf( ProductExcludedException::class, $exception );
	}

	/**
	 * Test throwing and catching exception with message.
	 */
	public function test_throw_with_message() {
		$message = 'Product is excluded from Facebook sync';
		
		try {
			throw new ProductExcludedException( $message );
		} catch ( ProductExcludedException $e ) {
			$this->assertEquals( $message, $e->getMessage() );
			$this->assertInstanceOf( ProductExcludedException::class, $e );
		}
	}

	/**
	 * Test throwing exception with custom code.
	 */
	public function test_throw_with_code() {
		$message = 'Product excluded';
		$code = 100;
		
		try {
			throw new ProductExcludedException( $message, $code );
		} catch ( ProductExcludedException $e ) {
			$this->assertEquals( $message, $e->getMessage() );
			$this->assertEquals( $code, $e->getCode() );
		}
	}

	/**
	 * Test exception chaining.
	 */
	public function test_exception_chaining() {
		$previousException = new Exception( 'Previous error' );
		$message = 'Product excluded due to previous error';
		
		try {
			throw new ProductExcludedException( $message, 0, $previousException );
		} catch ( ProductExcludedException $e ) {
			$this->assertEquals( $message, $e->getMessage() );
			$this->assertSame( $previousException, $e->getPrevious() );
			$this->assertEquals( 'Previous error', $e->getPrevious()->getMessage() );
		}
	}

	/**
	 * Test default exception values.
	 */
	public function test_default_values() {
		$exception = new ProductExcludedException();
		
		$this->assertEquals( '', $exception->getMessage() );
		$this->assertEquals( 0, $exception->getCode() );
		$this->assertNull( $exception->getPrevious() );
	}

	/**
	 * Test exception with empty message.
	 */
	public function test_empty_message() {
		$exception = new ProductExcludedException( '' );
		$this->assertEquals( '', $exception->getMessage() );
	}

	/**
	 * Test exception with special characters in message.
	 */
	public function test_special_characters_in_message() {
		$message = "Product 'Test & Demo' <excluded> due to \"special\" characters!";
		$exception = new ProductExcludedException( $message );
		$this->assertEquals( $message, $exception->getMessage() );
	}

	/**
	 * Test that exception can be caught as base Exception.
	 */
	public function test_catch_as_base_exception() {
		$caught = false;
		
		try {
			throw new ProductExcludedException( 'Test' );
		} catch ( Exception $e ) {
			$caught = true;
			$this->assertInstanceOf( ProductExcludedException::class, $e );
		}
		
		$this->assertTrue( $caught );
	}

	/**
	 * Test exception trace functionality.
	 */
	public function test_exception_trace() {
		$exception = new ProductExcludedException( 'Test trace' );
		
		$this->assertIsArray( $exception->getTrace() );
		$this->assertNotEmpty( $exception->getTraceAsString() );
		$this->assertStringContainsString( __FILE__, $exception->getFile() );
		$this->assertIsInt( $exception->getLine() );
	}
} 