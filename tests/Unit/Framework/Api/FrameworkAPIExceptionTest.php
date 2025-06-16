<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Framework\Api;

use WooCommerce\Facebook\Framework\Api\Exception;
use WooCommerce\Facebook\Framework\Plugin\Exception as PluginException;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Framework Api Exception class.
 *
 * @since 3.5.2
 */
class FrameworkAPIExceptionTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the Exception class exists and can be instantiated.
	 */
	public function test_exception_class_exists() {
		$this->assertTrue( class_exists( Exception::class ) );
	}

	/**
	 * Test that Exception extends the Framework Plugin Exception class.
	 */
	public function test_exception_extends_plugin_exception() {
		$exception = new Exception();
		$this->assertInstanceOf( PluginException::class, $exception );
	}

	/**
	 * Test that Exception extends the base PHP Exception class through inheritance.
	 */
	public function test_exception_extends_base_exception() {
		$exception = new Exception();
		$this->assertInstanceOf( \Exception::class, $exception );
	}

	/**
	 * Test exception instantiation with message.
	 */
	public function test_exception_with_message() {
		$message = 'API error occurred';
		$exception = new Exception( $message );
		
		$this->assertEquals( $message, $exception->getMessage() );
	}

	/**
	 * Test exception instantiation with message and code.
	 */
	public function test_exception_with_message_and_code() {
		$message = 'API request failed';
		$code = 500;
		$exception = new Exception( $message, $code );
		
		$this->assertEquals( $message, $exception->getMessage() );
		$this->assertEquals( $code, $exception->getCode() );
	}

	/**
	 * Test exception instantiation with message, code, and previous exception.
	 */
	public function test_exception_with_previous_exception() {
		$previousMessage = 'Network error';
		$previousException = new \Exception( $previousMessage );
		
		$message = 'API call failed';
		$code = 503;
		$exception = new Exception( $message, $code, $previousException );
		
		$this->assertEquals( $message, $exception->getMessage() );
		$this->assertEquals( $code, $exception->getCode() );
		$this->assertSame( $previousException, $exception->getPrevious() );
		$this->assertEquals( $previousMessage, $exception->getPrevious()->getMessage() );
	}

	/**
	 * Test throwing and catching the API exception.
	 */
	public function test_exception_can_be_thrown_and_caught() {
		$message = 'API endpoint not found';
		$code = 404;
		
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
	 * Test catching as parent Plugin Exception.
	 */
	public function test_exception_caught_as_plugin_exception() {
		$message = 'API error';
		
		try {
			throw new Exception( $message );
		} catch ( PluginException $e ) {
			$this->assertInstanceOf( Exception::class, $e );
			$this->assertEquals( $message, $e->getMessage() );
			return;
		}
		
		$this->fail( 'Exception was not caught as PluginException' );
	}

	/**
	 * Test exception with HTTP status codes.
	 */
	public function test_exception_with_http_status_codes() {
		$testCases = [
			[ 'message' => 'Bad Request', 'code' => 400 ],
			[ 'message' => 'Unauthorized', 'code' => 401 ],
			[ 'message' => 'Forbidden', 'code' => 403 ],
			[ 'message' => 'Not Found', 'code' => 404 ],
			[ 'message' => 'Internal Server Error', 'code' => 500 ],
			[ 'message' => 'Service Unavailable', 'code' => 503 ],
		];
		
		foreach ( $testCases as $testCase ) {
			$exception = new Exception( $testCase['message'], $testCase['code'] );
			$this->assertEquals( $testCase['message'], $exception->getMessage() );
			$this->assertEquals( $testCase['code'], $exception->getCode() );
		}
	}

	/**
	 * Test exception with API-specific error message.
	 */
	public function test_exception_with_api_error_details() {
		$message = 'Facebook API Error: (#100) Invalid parameter';
		$code = 100;
		$exception = new Exception( $message, $code );
		
		$this->assertEquals( $message, $exception->getMessage() );
		$this->assertEquals( $code, $exception->getCode() );
		$this->assertStringContainsString( 'Facebook API Error', $exception->getMessage() );
		$this->assertStringContainsString( 'Invalid parameter', $exception->getMessage() );
	}

	/**
	 * Test exception with JSON error message.
	 */
	public function test_exception_with_json_error_message() {
		$message = '{"error":{"message":"Invalid OAuth access token","type":"OAuthException","code":190}}';
		$exception = new Exception( $message, 190 );
		
		$this->assertEquals( $message, $exception->getMessage() );
		$this->assertEquals( 190, $exception->getCode() );
	}

	/**
	 * Test exception inheritance chain.
	 */
	public function test_exception_inheritance_chain() {
		$exception = new Exception( 'Test inheritance' );
		
		// Should be instance of all parent classes
		$this->assertInstanceOf( Exception::class, $exception );
		$this->assertInstanceOf( PluginException::class, $exception );
		$this->assertInstanceOf( \Exception::class, $exception );
		$this->assertInstanceOf( \Throwable::class, $exception );
	}

	/**
	 * Test exception string representation includes class name.
	 */
	public function test_exception_string_representation() {
		$message = 'API Exception test';
		$exception = new Exception( $message );
		$string = (string) $exception;
		
		$this->assertStringContainsString( Exception::class, $string );
		$this->assertStringContainsString( $message, $string );
	}
} 