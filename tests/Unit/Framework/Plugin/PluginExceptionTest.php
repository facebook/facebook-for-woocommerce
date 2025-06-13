<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Framework\Plugin;

use WooCommerce\Facebook\Framework\Plugin\Exception;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Framework Plugin Exception class.
 *
 * @since x.x.x
 */
class PluginExceptionTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the Exception class exists and can be instantiated.
	 */
	public function test_exception_class_exists() {
		$this->assertTrue( class_exists( Exception::class ) );
	}

	/**
	 * Test that Exception extends the base PHP Exception class.
	 */
	public function test_exception_extends_base_exception() {
		$exception = new Exception();
		$this->assertInstanceOf( \Exception::class, $exception );
	}

	/**
	 * Test exception instantiation with message.
	 */
	public function test_exception_with_message() {
		$message = 'Test exception message';
		$exception = new Exception( $message );
		
		$this->assertEquals( $message, $exception->getMessage() );
	}

	/**
	 * Test exception instantiation with message and code.
	 */
	public function test_exception_with_message_and_code() {
		$message = 'Test exception message';
		$code = 123;
		$exception = new Exception( $message, $code );
		
		$this->assertEquals( $message, $exception->getMessage() );
		$this->assertEquals( $code, $exception->getCode() );
	}

	/**
	 * Test exception instantiation with message, code, and previous exception.
	 */
	public function test_exception_with_previous_exception() {
		$previousMessage = 'Previous exception';
		$previousException = new \Exception( $previousMessage );
		
		$message = 'Current exception';
		$code = 456;
		$exception = new Exception( $message, $code, $previousException );
		
		$this->assertEquals( $message, $exception->getMessage() );
		$this->assertEquals( $code, $exception->getCode() );
		$this->assertSame( $previousException, $exception->getPrevious() );
		$this->assertEquals( $previousMessage, $exception->getPrevious()->getMessage() );
	}

	/**
	 * Test throwing and catching the exception.
	 */
	public function test_exception_can_be_thrown_and_caught() {
		$message = 'Thrown exception';
		$code = 789;
		
		try {
			throw new Exception( $message, $code );
		} catch ( Exception $e ) {
			$this->assertEquals( $message, $e->getMessage() );
			$this->assertEquals( $code, $e->getCode() );
			return;
		}
		
		$this->fail( 'Exception was not thrown' );
	}

	/**
	 * Test exception with empty message.
	 */
	public function test_exception_with_empty_message() {
		$exception = new Exception( '' );
		$this->assertEquals( '', $exception->getMessage() );
	}

	/**
	 * Test exception with zero code.
	 */
	public function test_exception_with_zero_code() {
		$exception = new Exception( 'Message', 0 );
		$this->assertEquals( 0, $exception->getCode() );
	}

	/**
	 * Test exception with negative code.
	 */
	public function test_exception_with_negative_code() {
		$exception = new Exception( 'Message', -1 );
		$this->assertEquals( -1, $exception->getCode() );
	}

	/**
	 * Test exception with special characters in message.
	 */
	public function test_exception_with_special_characters() {
		$message = "Special chars: !@#$%^&*()_+{}[]|\\:\";<>?,./~`\n\t";
		$exception = new Exception( $message );
		$this->assertEquals( $message, $exception->getMessage() );
	}

	/**
	 * Test exception with Unicode characters in message.
	 */
	public function test_exception_with_unicode_characters() {
		$message = 'Unicode: 你好世界 🌍 émojis';
		$exception = new Exception( $message );
		$this->assertEquals( $message, $exception->getMessage() );
	}

	/**
	 * Test exception trace contains expected information.
	 */
	public function test_exception_trace() {
		$exception = new Exception( 'Test trace' );
		$trace = $exception->getTrace();
		
		$this->assertIsArray( $trace );
		$this->assertNotEmpty( $trace );
		$this->assertArrayHasKey( 'file', $trace[0] );
		$this->assertArrayHasKey( 'line', $trace[0] );
		$this->assertArrayHasKey( 'function', $trace[0] );
	}

	/**
	 * Test exception string representation.
	 */
	public function test_exception_string_representation() {
		$message = 'String representation test';
		$exception = new Exception( $message );
		$string = (string) $exception;
		
		$this->assertStringContainsString( Exception::class, $string );
		$this->assertStringContainsString( $message, $string );
	}
} 