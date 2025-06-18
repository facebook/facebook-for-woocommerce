<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Api\Exceptions;

use WooCommerce\Facebook\API\Exceptions\Request_Limit_Reached;
use WooCommerce\Facebook\Framework\Api\Exception as ApiException;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Request_Limit_Reached exception class.
 *
 * @since 3.5.2
 */
class Request_Limit_ReachedTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the class exists and can be instantiated.
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( 'WooCommerce\Facebook\API\Exceptions\Request_Limit_Reached' ) );
		$exception = new Request_Limit_Reached();
		$this->assertInstanceOf( Request_Limit_Reached::class, $exception );
	}

	/**
	 * Test that Request_Limit_Reached extends ApiException.
	 */
	public function test_extends_api_exception() {
		$exception = new Request_Limit_Reached();
		$this->assertInstanceOf( ApiException::class, $exception );
	}

	/**
	 * Test constructor with message.
	 */
	public function test_constructor_with_message() {
		$message = 'Rate limit exceeded';
		$exception = new Request_Limit_Reached( $message );
		$this->assertEquals( $message, $exception->getMessage() );
	}

	/**
	 * Test constructor with message and code.
	 */
	public function test_constructor_with_message_and_code() {
		$message = 'Too many requests';
		$code = 429;
		$exception = new Request_Limit_Reached( $message, $code );
		$this->assertEquals( $message, $exception->getMessage() );
		$this->assertEquals( $code, $exception->getCode() );
	}

	/**
	 * Test get_throttle_end returns null by default.
	 */
	public function test_get_throttle_end_default() {
		$exception = new Request_Limit_Reached();
		$this->assertNull( $exception->get_throttle_end() );
	}

	/**
	 * Test set_throttle_end and get_throttle_end.
	 */
	public function test_set_and_get_throttle_end() {
		$exception = new Request_Limit_Reached();
		$date_time = new \DateTime( '2023-12-25 10:30:00' );
		
		$exception->set_throttle_end( $date_time );
		$result = $exception->get_throttle_end();
		
		$this->assertInstanceOf( \DateTime::class, $result );
		$this->assertEquals( $date_time, $result );
		$this->assertEquals( '2023-12-25 10:30:00', $result->format( 'Y-m-d H:i:s' ) );
	}

	/**
	 * Test set_throttle_end with different DateTime objects.
	 */
	public function test_set_throttle_end_various_dates() {
		$exception = new Request_Limit_Reached();
		
		// Test with current time
		$now = new \DateTime();
		$exception->set_throttle_end( $now );
		$this->assertEquals( $now, $exception->get_throttle_end() );
		
		// Test with future time
		$future = new \DateTime( '+1 hour' );
		$exception->set_throttle_end( $future );
		$this->assertEquals( $future, $exception->get_throttle_end() );
		
		// Test with past time
		$past = new \DateTime( '-1 day' );
		$exception->set_throttle_end( $past );
		$this->assertEquals( $past, $exception->get_throttle_end() );
	}

	/**
	 * Test set_throttle_end with timezone.
	 */
	public function test_set_throttle_end_with_timezone() {
		$exception = new Request_Limit_Reached();
		
		// Create DateTime with specific timezone
		$timezone = new \DateTimeZone( 'America/New_York' );
		$date_time = new \DateTime( '2023-12-25 15:00:00', $timezone );
		
		$exception->set_throttle_end( $date_time );
		$result = $exception->get_throttle_end();
		
		$this->assertEquals( $date_time, $result );
		$this->assertEquals( 'America/New_York', $result->getTimezone()->getName() );
	}

	/**
	 * Test that throttle_end is properly isolated between instances.
	 */
	public function test_throttle_end_isolation() {
		$exception1 = new Request_Limit_Reached();
		$exception2 = new Request_Limit_Reached();
		
		$date_time1 = new \DateTime( '2023-01-01 00:00:00' );
		$date_time2 = new \DateTime( '2023-12-31 23:59:59' );
		
		$exception1->set_throttle_end( $date_time1 );
		$exception2->set_throttle_end( $date_time2 );
		
		$this->assertEquals( $date_time1, $exception1->get_throttle_end() );
		$this->assertEquals( $date_time2, $exception2->get_throttle_end() );
		$this->assertNotEquals( $exception1->get_throttle_end(), $exception2->get_throttle_end() );
	}

	/**
	 * Test exception can be thrown and caught properly.
	 */
	public function test_exception_can_be_thrown() {
		$this->expectException( Request_Limit_Reached::class );
		$this->expectExceptionMessage( 'API rate limit reached' );
		$this->expectExceptionCode( 429 );
		
		throw new Request_Limit_Reached( 'API rate limit reached', 429 );
	}

	/**
	 * Test exception with throttle_end can be thrown and accessed in catch.
	 */
	public function test_exception_with_throttle_end_in_catch() {
		$throttle_end = new \DateTime( '+5 minutes' );
		
		try {
			$exception = new Request_Limit_Reached( 'Rate limited' );
			$exception->set_throttle_end( $throttle_end );
			throw $exception;
		} catch ( Request_Limit_Reached $e ) {
			$this->assertEquals( 'Rate limited', $e->getMessage() );
			$this->assertEquals( $throttle_end, $e->get_throttle_end() );
			$this->assertInstanceOf( \DateTime::class, $e->get_throttle_end() );
		}
	}

	/**
	 * Test multiple set_throttle_end calls override previous value.
	 */
	public function test_multiple_set_throttle_end_calls() {
		$exception = new Request_Limit_Reached();
		
		$date1 = new \DateTime( '2023-01-01' );
		$date2 = new \DateTime( '2023-06-15' );
		$date3 = new \DateTime( '2023-12-31' );
		
		$exception->set_throttle_end( $date1 );
		$this->assertEquals( $date1, $exception->get_throttle_end() );
		
		$exception->set_throttle_end( $date2 );
		$this->assertEquals( $date2, $exception->get_throttle_end() );
		
		$exception->set_throttle_end( $date3 );
		$this->assertEquals( $date3, $exception->get_throttle_end() );
	}

	/**
	 * Test DateTime immutability when setting throttle_end.
	 */
	public function test_datetime_immutability() {
		$exception = new Request_Limit_Reached();
		$original_date = new \DateTime( '2023-12-25 12:00:00' );
		$original_timestamp = $original_date->getTimestamp();
		
		$exception->set_throttle_end( $original_date );
		
		// Modify the original DateTime object
		$original_date->modify( '+1 day' );
		
		// The stored DateTime should not be affected
		$stored_date = $exception->get_throttle_end();
		$this->assertEquals( $original_timestamp, $stored_date->getTimestamp() );
		$this->assertNotEquals( $original_date->getTimestamp(), $stored_date->getTimestamp() );
	}
} 