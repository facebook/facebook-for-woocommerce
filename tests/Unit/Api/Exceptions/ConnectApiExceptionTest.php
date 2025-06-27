<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\Exceptions;

use WooCommerce\Facebook\API\Exceptions\ConnectApiException;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for ConnectApiException class.
 *
 * @since 3.5.2
 */
class ConnectApiExceptionTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the class exists and can be instantiated.
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( ConnectApiException::class ) );
	}

	/**
	 * Test instantiation with default values.
	 */
	public function test_instantiation_with_defaults() {
		$exception = new ConnectApiException();
		$this->assertInstanceOf( ConnectApiException::class, $exception );
		$this->assertEquals( '', $exception->getMessage() );
		$this->assertEquals( 0, $exception->getCode() );
		$this->assertNull( $exception->getPrevious() );
	}

	/**
	 * Test instantiation with message only.
	 */
	public function test_instantiation_with_message() {
		$message = 'Connection to Facebook failed';
		$exception = new ConnectApiException( $message );
		
		$this->assertEquals( $message, $exception->getMessage() );
		$this->assertEquals( 0, $exception->getCode() );
		$this->assertNull( $exception->getPrevious() );
	}

	/**
	 * Test instantiation with message and code.
	 */
	public function test_instantiation_with_message_and_code() {
		$message = 'Connection to Facebook failed';
		$code = 500;
		$exception = new ConnectApiException( $message, $code );
		
		$this->assertEquals( $message, $exception->getMessage() );
		$this->assertEquals( $code, $exception->getCode() );
		$this->assertNull( $exception->getPrevious() );
	}

	/**
	 * Test instantiation with all parameters.
	 */
	public function test_instantiation_with_all_parameters() {
		$message = 'Connection to Facebook failed';
		$code = 500;
		$previous = new \Exception( 'Previous exception' );
		$exception = new ConnectApiException( $message, $code, $previous );
		
		$this->assertEquals( $message, $exception->getMessage() );
		$this->assertEquals( $code, $exception->getCode() );
		$this->assertSame( $previous, $exception->getPrevious() );
	}

	/**
	 * Test throwing and catching the exception.
	 */
	public function test_throw_and_catch() {
		$message = 'API connection error';
		$code = 503;
		
		try {
			throw new ConnectApiException( $message, $code );
		} catch ( ConnectApiException $e ) {
			$this->assertInstanceOf( ConnectApiException::class, $e );
			$this->assertEquals( $message, $e->getMessage() );
			$this->assertEquals( $code, $e->getCode() );
			return;
		}
		
		$this->fail( 'Exception was not thrown' );
	}

	/**
	 * Test catching as generic Exception.
	 */
	public function test_catch_as_generic_exception() {
		try {
			throw new ConnectApiException( 'Test error' );
		} catch ( \Exception $e ) {
			$this->assertInstanceOf( ConnectApiException::class, $e );
			$this->assertInstanceOf( \Exception::class, $e );
			return;
		}
		
		$this->fail( 'Exception was not caught' );
	}

	/**
	 * Test with special characters in message.
	 */
	public function test_with_special_characters_in_message() {
		$message = "Connection failed: <script>alert('test')</script> & \"quotes\" 'apostrophes' \n\r\t";
		$exception = new ConnectApiException( $message );
		
		$this->assertEquals( $message, $exception->getMessage() );
	}

	/**
	 * Test with Unicode characters in message.
	 */
	public function test_with_unicode_characters_in_message() {
		$message = 'Connection failed: 你好世界 🌍 émojis';
		$exception = new ConnectApiException( $message );
		
		$this->assertEquals( $message, $exception->getMessage() );
	}

	/**
	 * Test with empty message.
	 */
	public function test_with_empty_message() {
		$exception = new ConnectApiException( '' );
		$this->assertEquals( '', $exception->getMessage() );
	}

	/**
	 * Test with negative error code.
	 */
	public function test_with_negative_error_code() {
		$exception = new ConnectApiException( 'Error', -1 );
		$this->assertEquals( -1, $exception->getCode() );
	}

	/**
	 * Test with large error code.
	 */
	public function test_with_large_error_code() {
		$code = PHP_INT_MAX;
		$exception = new ConnectApiException( 'Error', $code );
		$this->assertEquals( $code, $exception->getCode() );
	}

	/**
	 * Test exception chaining.
	 */
	public function test_exception_chaining() {
		$rootCause = new \RuntimeException( 'Root cause' );
		$middleException = new \Exception( 'Middle layer', 0, $rootCause );
		$connectException = new ConnectApiException( 'Connection failed', 500, $middleException );
		
		$this->assertSame( $middleException, $connectException->getPrevious() );
		$this->assertSame( $rootCause, $connectException->getPrevious()->getPrevious() );
	}

	/**
	 * Test getFile and getLine methods.
	 */
	public function test_file_and_line_tracking() {
		$exception = new ConnectApiException( 'Test' );
		
		$this->assertStringContainsString( 'ConnectApiExceptionTest.php', $exception->getFile() );
		$this->assertIsInt( $exception->getLine() );
		$this->assertGreaterThan( 0, $exception->getLine() );
	}

	/**
	 * Test getTrace and getTraceAsString methods.
	 */
	public function test_stack_trace() {
		$exception = new ConnectApiException( 'Test' );
		
		$trace = $exception->getTrace();
		$this->assertIsArray( $trace );
		$this->assertNotEmpty( $trace );
		
		$traceString = $exception->getTraceAsString();
		$this->assertIsString( $traceString );
		$this->assertStringContainsString( 'ConnectApiExceptionTest', $traceString );
	}

	/**
	 * Test __toString method.
	 */
	public function test_to_string() {
		$message = 'Connection error occurred';
		$exception = new ConnectApiException( $message );
		
		$string = (string) $exception;
		$this->assertStringContainsString( 'ConnectApiException', $string );
		$this->assertStringContainsString( $message, $string );
		$this->assertStringContainsString( 'ConnectApiExceptionTest.php', $string );
	}
}


