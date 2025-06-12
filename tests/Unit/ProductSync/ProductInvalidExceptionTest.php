<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\ProductSync;

use WooCommerce\Facebook\ProductSync\ProductInvalidException;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;
use Exception;

/**
 * Unit tests for ProductInvalidException class.
 *
 * @since x.x.x
 */
class ProductInvalidExceptionTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that ProductInvalidException exists and can be instantiated.
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( ProductInvalidException::class ) );
	}

	/**
	 * Test that ProductInvalidException extends Exception.
	 */
	public function test_extends_exception() {
		$exception = new ProductInvalidException();
		$this->assertInstanceOf( Exception::class, $exception );
		$this->assertInstanceOf( ProductInvalidException::class, $exception );
	}

	/**
	 * Test throwing and catching exception with message.
	 */
	public function test_throw_with_message() {
		$message = 'Product configuration is invalid for Facebook sync';
		
		try {
			throw new ProductInvalidException( $message );
		} catch ( ProductInvalidException $e ) {
			$this->assertEquals( $message, $e->getMessage() );
			$this->assertInstanceOf( ProductInvalidException::class, $e );
		}
	}

	/**
	 * Test throwing exception with custom code.
	 */
	public function test_throw_with_code() {
		$message = 'Invalid product configuration';
		$code = 200;
		
		try {
			throw new ProductInvalidException( $message, $code );
		} catch ( ProductInvalidException $e ) {
			$this->assertEquals( $message, $e->getMessage() );
			$this->assertEquals( $code, $e->getCode() );
		}
	}

	/**
	 * Test exception chaining.
	 */
	public function test_exception_chaining() {
		$previousException = new Exception( 'Validation failed' );
		$message = 'Product invalid due to validation failure';
		
		try {
			throw new ProductInvalidException( $message, 0, $previousException );
		} catch ( ProductInvalidException $e ) {
			$this->assertEquals( $message, $e->getMessage() );
			$this->assertSame( $previousException, $e->getPrevious() );
			$this->assertEquals( 'Validation failed', $e->getPrevious()->getMessage() );
		}
	}

	/**
	 * Test default exception values.
	 */
	public function test_default_values() {
		$exception = new ProductInvalidException();
		
		$this->assertEquals( '', $exception->getMessage() );
		$this->assertEquals( 0, $exception->getCode() );
		$this->assertNull( $exception->getPrevious() );
	}

	/**
	 * Test exception with empty message.
	 */
	public function test_empty_message() {
		$exception = new ProductInvalidException( '' );
		$this->assertEquals( '', $exception->getMessage() );
	}

	/**
	 * Test exception with special characters in message.
	 */
	public function test_special_characters_in_message() {
		$message = "Product 'Test & Demo' <invalid> due to \"configuration\" errors!";
		$exception = new ProductInvalidException( $message );
		$this->assertEquals( $message, $exception->getMessage() );
	}

	/**
	 * Test that exception can be caught as base Exception.
	 */
	public function test_catch_as_base_exception() {
		$caught = false;
		
		try {
			throw new ProductInvalidException( 'Test' );
		} catch ( Exception $e ) {
			$caught = true;
			$this->assertInstanceOf( ProductInvalidException::class, $e );
		}
		
		$this->assertTrue( $caught );
	}

	/**
	 * Test exception trace functionality.
	 */
	public function test_exception_trace() {
		$exception = new ProductInvalidException( 'Test trace' );
		
		$this->assertIsArray( $exception->getTrace() );
		$this->assertNotEmpty( $exception->getTraceAsString() );
		$this->assertStringContainsString( __FILE__, $exception->getFile() );
		$this->assertIsInt( $exception->getLine() );
	}

	/**
	 * Test different error scenarios for invalid products.
	 */
	public function test_product_validation_scenarios() {
		// Missing required fields
		try {
			throw new ProductInvalidException( 'Product missing required fields' );
		} catch ( ProductInvalidException $e ) {
			$this->assertEquals( 'Product missing required fields', $e->getMessage() );
		}

		// Invalid price
		try {
			throw new ProductInvalidException( 'Product price is invalid', 301 );
		} catch ( ProductInvalidException $e ) {
			$this->assertEquals( 'Product price is invalid', $e->getMessage() );
			$this->assertEquals( 301, $e->getCode() );
		}

		// Invalid category
		try {
			throw new ProductInvalidException( 'Product category not supported by Facebook', 302 );
		} catch ( ProductInvalidException $e ) {
			$this->assertEquals( 'Product category not supported by Facebook', $e->getMessage() );
			$this->assertEquals( 302, $e->getCode() );
		}
	}
} 