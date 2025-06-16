<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\Traits;

use WooCommerce\Facebook\API\Traits\Rate_Limited_Response;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Rate_Limited_Response trait.
 *
 * @since 3.5.2
 */
class RateLimitedResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test class that uses the Rate_Limited_Response trait.
	 */
	private $test_class;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Create an anonymous class that uses the trait
		$this->test_class = new class {
			use Rate_Limited_Response;
		};
	}

	/**
	 * Test that the trait can be used in a class.
	 */
	public function test_trait_can_be_used() {
		$this->assertContains( 
			Rate_Limited_Response::class, 
			class_uses( $this->test_class ) 
		);
	}

	/**
	 * Test get_usage_data with Business Use Case headers (both cases).
	 */
	public function test_get_usage_data_with_business_use_case_headers() {
		$reflection = new \ReflectionClass( $this->test_class );
		$method = $reflection->getMethod( 'get_usage_data' );
		$method->setAccessible( true );
		
		// Test uppercase header
		$headers_uppercase = [
			'X-Business-Use-Case-Usage' => [
				'call_count' => 50,
				'total_time' => 30,
				'total_cputime' => 25,
			],
		];
		$result = $method->invoke( $this->test_class, $headers_uppercase );
		$this->assertEquals( $headers_uppercase['X-Business-Use-Case-Usage'], $result );
		
		// Test lowercase header
		$headers_lowercase = [
			'x-business-use-case-usage' => [
				'call_count' => 75,
				'total_time' => 45,
			],
		];
		$result = $method->invoke( $this->test_class, $headers_lowercase );
		$this->assertEquals( $headers_lowercase['x-business-use-case-usage'], $result );
	}

	/**
	 * Test get_usage_data with App Usage headers (both cases).
	 */
	public function test_get_usage_data_with_app_usage_headers() {
		$reflection = new \ReflectionClass( $this->test_class );
		$method = $reflection->getMethod( 'get_usage_data' );
		$method->setAccessible( true );
		
		// Test uppercase header
		$headers_uppercase = [
			'X-App-Usage' => [
				'call_count' => 25,
				'total_time' => 15,
			],
		];
		$result = $method->invoke( $this->test_class, $headers_uppercase );
		$this->assertEquals( $headers_uppercase['X-App-Usage'], $result );
		
		// Test lowercase header
		$headers_lowercase = [
			'x-app-usage' => [
				'call_count' => 90,
				'total_cputime' => 80,
			],
		];
		$result = $method->invoke( $this->test_class, $headers_lowercase );
		$this->assertEquals( $headers_lowercase['x-app-usage'], $result );
	}

	/**
	 * Test get_usage_data with no rate limit headers.
	 */
	public function test_get_usage_data_with_no_rate_limit_headers() {
		$headers = [
			'Content-Type' => 'application/json',
			'X-Other-Header' => 'value',
		];
		
		$reflection = new \ReflectionClass( $this->test_class );
		$method = $reflection->getMethod( 'get_usage_data' );
		$method->setAccessible( true );
		
		$result = $method->invoke( $this->test_class, $headers );
		
		$this->assertEquals( [], $result );
	}

	/**
	 * Test get_rate_limit_usage with valid data.
	 */
	public function test_get_rate_limit_usage_with_valid_data() {
		$headers = [
			'X-Business-Use-Case-Usage' => [
				'call_count' => 85,
				'total_time' => 60,
			],
		];
		
		$result = $this->test_class->get_rate_limit_usage( $headers );
		
		$this->assertEquals( 85, $result );
		$this->assertIsInt( $result );
	}

	/**
	 * Test get_rate_limit_usage with missing call_count.
	 */
	public function test_get_rate_limit_usage_with_missing_call_count() {
		$headers = [
			'X-Business-Use-Case-Usage' => [
				'total_time' => 60,
			],
		];
		
		$result = $this->test_class->get_rate_limit_usage( $headers );
		
		$this->assertEquals( 0, $result );
	}

	/**
	 * Test get_rate_limit_usage with no headers.
	 */
	public function test_get_rate_limit_usage_with_no_headers() {
		$headers = [];
		
		$result = $this->test_class->get_rate_limit_usage( $headers );
		
		$this->assertEquals( 0, $result );
	}

	/**
	 * Test get_rate_limit_total_time with valid data.
	 */
	public function test_get_rate_limit_total_time_with_valid_data() {
		$headers = [
			'X-App-Usage' => [
				'call_count' => 50,
				'total_time' => 95,
			],
		];
		
		$result = $this->test_class->get_rate_limit_total_time( $headers );
		
		$this->assertEquals( 95, $result );
		$this->assertIsInt( $result );
	}

	/**
	 * Test get_rate_limit_total_time with missing total_time.
	 */
	public function test_get_rate_limit_total_time_with_missing_total_time() {
		$headers = [
			'X-App-Usage' => [
				'call_count' => 50,
			],
		];
		
		$result = $this->test_class->get_rate_limit_total_time( $headers );
		
		$this->assertEquals( 0, $result );
	}

	/**
	 * Test get_rate_limit_total_cpu_time with valid data.
	 */
	public function test_get_rate_limit_total_cpu_time_with_valid_data() {
		$headers = [
			'x-business-use-case-usage' => [
				'call_count' => 40,
				'total_cputime' => 88,
			],
		];
		
		$result = $this->test_class->get_rate_limit_total_cpu_time( $headers );
		
		$this->assertEquals( 88, $result );
		$this->assertIsInt( $result );
	}

	/**
	 * Test get_rate_limit_total_cpu_time with missing total_cputime.
	 */
	public function test_get_rate_limit_total_cpu_time_with_missing_total_cputime() {
		$headers = [
			'x-app-usage' => [
				'call_count' => 40,
				'total_time' => 30,
			],
		];
		
		$result = $this->test_class->get_rate_limit_total_cpu_time( $headers );
		
		$this->assertEquals( 0, $result );
	}

	/**
	 * Test get_rate_limit_estimated_time_to_regain_access with valid data.
	 */
	public function test_get_rate_limit_estimated_time_to_regain_access_with_valid_data() {
		$headers = [
			'X-Business-Use-Case-Usage' => [
				'call_count' => 100,
				'estimated_time_to_regain_access' => 300,
			],
		];
		
		$result = $this->test_class->get_rate_limit_estimated_time_to_regain_access( $headers );
		
		$this->assertEquals( 300, $result );
		$this->assertIsInt( $result );
	}

	/**
	 * Test get_rate_limit_estimated_time_to_regain_access with zero value.
	 */
	public function test_get_rate_limit_estimated_time_to_regain_access_with_zero() {
		$headers = [
			'X-Business-Use-Case-Usage' => [
				'estimated_time_to_regain_access' => 0,
			],
		];
		
		$result = $this->test_class->get_rate_limit_estimated_time_to_regain_access( $headers );
		
		$this->assertNull( $result );
	}

	/**
	 * Test get_rate_limit_estimated_time_to_regain_access with missing field.
	 */
	public function test_get_rate_limit_estimated_time_to_regain_access_with_missing_field() {
		$headers = [
			'X-Business-Use-Case-Usage' => [
				'call_count' => 100,
			],
		];
		
		$result = $this->test_class->get_rate_limit_estimated_time_to_regain_access( $headers );
		
		$this->assertNull( $result );
	}

	/**
	 * Test all methods with string values that should be cast to int.
	 */
	public function test_methods_with_string_values() {
		$headers = [
			'X-Business-Use-Case-Usage' => [
				'call_count' => '75',
				'total_time' => '45',
				'total_cputime' => '35',
				'estimated_time_to_regain_access' => '120',
			],
		];
		
		$this->assertEquals( 75, $this->test_class->get_rate_limit_usage( $headers ) );
		$this->assertEquals( 45, $this->test_class->get_rate_limit_total_time( $headers ) );
		$this->assertEquals( 35, $this->test_class->get_rate_limit_total_cpu_time( $headers ) );
		$this->assertEquals( 120, $this->test_class->get_rate_limit_estimated_time_to_regain_access( $headers ) );
	}

	/**
	 * Test priority of headers (Business Use Case should take precedence).
	 */
	public function test_header_priority() {
		$headers = [
			'X-Business-Use-Case-Usage' => [
				'call_count' => 100,
			],
			'X-App-Usage' => [
				'call_count' => 50,
			],
		];
		
		$result = $this->test_class->get_rate_limit_usage( $headers );
		
		// Business Use Case header should take precedence
		$this->assertEquals( 100, $result );
	}

	/**
	 * Test with malformed usage data.
	 */
	public function test_with_malformed_usage_data() {
		$headers = [
			'X-Business-Use-Case-Usage' => 'not-an-array',
		];
		
		$reflection = new \ReflectionClass( $this->test_class );
		$method = $reflection->getMethod( 'get_usage_data' );
		$method->setAccessible( true );
		
		$result = $method->invoke( $this->test_class, $headers );
		
		$this->assertEquals( 'not-an-array', $result );
		
		// The public methods should handle this gracefully
		$this->assertEquals( 0, $this->test_class->get_rate_limit_usage( $headers ) );
		$this->assertEquals( 0, $this->test_class->get_rate_limit_total_time( $headers ) );
		$this->assertEquals( 0, $this->test_class->get_rate_limit_total_cpu_time( $headers ) );
		$this->assertNull( $this->test_class->get_rate_limit_estimated_time_to_regain_access( $headers ) );
	}
} 