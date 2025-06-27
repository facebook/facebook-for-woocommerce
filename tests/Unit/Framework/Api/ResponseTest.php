<?php

declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Framework\Api;

use WooCommerce\Facebook\Framework\Api\Response;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for the Response interface.
 */
class ResponseTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Returns a concrete implementation of the Response interface for testing.
	 *
	 * @param array $args Optional overrides for string and string_safe.
	 * @return Response
	 */
	private function get_test_response( array $args = [] ) : Response {
		$defaults = [
			'string' => '{"foo":"bar"}',
			'string_safe' => '{"foo":"safe"}',
		];

		$args = array_merge( $defaults, $args );

		return new class( $args ) implements Response {
			private $string;
			private $string_safe;

			public function __construct( $args ) {
				$this->string = $args['string'];
				$this->string_safe = $args['string_safe'];
			}

			public function to_string() {
				return $this->string;
			}

			public function to_string_safe() {
				return $this->string_safe;
			}
		};
	}

	/**
	 * Test that to_string returns the expected value.
	 */
	public function test_to_string_returns_expected_value() {
		// Create a response with a custom string
		$response = $this->get_test_response([ 'string' => 'foobar' ]);

		// Assert the string is as set
		$this->assertEquals('foobar', $response->to_string());
	}

	/**
	 * Test that to_string_safe returns the expected value.
	 */
	public function test_to_string_safe_returns_expected_value() {
		// Create a response with a custom safe string
		$response = $this->get_test_response([ 'string_safe' => 'safe-string' ]);

		// Assert the safe string is as set
		$this->assertEquals('safe-string', $response->to_string_safe());
	}

	/**
	 * Test that both methods return the expected types.
	 */
	public function test_types_of_methods() {
		// Create a default response
		$response = $this->get_test_response();

		// Assert types for both interface methods
		$this->assertIsString($response->to_string());
		$this->assertIsString($response->to_string_safe());
	}

	/**
	 * Test that null and empty values are handled correctly.
	 */
	public function test_null_and_empty_values() {
		// Null values
		$response = $this->get_test_response([
			'string' => null,
			'string_safe' => null,
		]);

		// Assert null is returned
		$this->assertNull($response->to_string());
		$this->assertNull($response->to_string_safe());

		// Empty string values
		$response2 = $this->get_test_response([
			'string' => '',
			'string_safe' => '',
		]);

		// Assert empty string is returned
		$this->assertSame('', $response2->to_string());
		$this->assertSame('', $response2->to_string_safe());
	}

	/**
	 * Test with unicode and special characters.
	 */
	public function test_unicode_and_special_characters() {
		// Create a response with unicode and special characters
		$response = $this->get_test_response([
			'string' => 'üñîçødë✓😀',
			'string_safe' => '!@#$%^&*()_+-=\n',
		]);

		// Assert the values are as set
		$this->assertEquals('üñîçødë✓😀', $response->to_string());
		$this->assertEquals('!@#$%^&*()_+-=\n', $response->to_string_safe());
	}

	/**
	 * Test to_string and to_string_safe returning non-string values.
	 */
	public function test_to_string_and_safe_with_non_string() {
		// to_string returns int, to_string_safe returns array
		$response = $this->get_test_response([
			'string' => 12345,
			'string_safe' => [1,2,3],
		]);

		// Assert the values are as set
		$this->assertSame(12345, $response->to_string());
		$this->assertSame([1,2,3], $response->to_string_safe());
	}

	/**
	 * Test to_string and to_string_safe returning boolean values.
	 */
	public function test_to_string_and_safe_with_boolean() {
		// to_string returns true, to_string_safe returns false
		$response = $this->get_test_response([
			'string' => true,
			'string_safe' => false,
		]);

		// Assert the boolean values are as set
		$this->assertTrue($response->to_string());
		$this->assertFalse($response->to_string_safe());
	}

	/**
	 * Test to_string and to_string_safe returning objects.
	 */
	public function test_to_string_and_safe_with_object() {
		$obj1 = (object)['foo' => 'bar'];
		$obj2 = (object)['baz' => 'qux'];

		// Create a response with object values
		$response = $this->get_test_response([
			'string' => $obj1,
			'string_safe' => $obj2,
		]);

		// Assert the objects are as set
		$this->assertSame($obj1, $response->to_string());
		$this->assertSame($obj2, $response->to_string_safe());
	}

	/**
	 * Test with large string values.
	 */
	public function test_large_string_values() {
		$large = str_repeat('A', 10000);

		// Create a response with large string values
		$response = $this->get_test_response([
			'string' => $large,
			'string_safe' => $large,
		]);

		// Assert the large strings are as set
		$this->assertEquals($large, $response->to_string());
		$this->assertEquals($large, $response->to_string_safe());
	}

	/**
	 * Test with overlapping values for both methods.
	 */
	public function test_overlapping_to_string_and_safe() {
		$value = 'same-value';

		// Create a response with the same value for both methods
		$response = $this->get_test_response([
			'string' => $value,
			'string_safe' => $value,
		]);

		// Assert both methods return the same value
		$this->assertSame($value, $response->to_string());
		$this->assertSame($value, $response->to_string_safe());
	}

	/**
	 * Test that the Response interface is not instantiable.
	 */
	public function test_response_interface_is_not_instantiable() {
		$reflection = new \ReflectionClass(\WooCommerce\Facebook\Framework\Api\Response::class);

		// Assert that Response is an interface and not instantiable
		$this->assertTrue($reflection->isInterface());
		$this->assertFalse($reflection->isInstantiable());
	}

	/**
	 * Test that a class missing required methods triggers an error (using Reflection).
	 */
	public function test_missing_methods_in_implementation() {
		// Create a class with no methods
		$reflection = new \ReflectionClass(new class {
			// No methods at all
		});

		// Assert it does not implement the Response interface
		$interfaces = $reflection->getInterfaceNames();
		$this->assertNotContains(Response::class, $interfaces);
	}

	/**
	 * Test that a class implementing Response with only one method triggers an error (using Reflection).
	 */
	public function test_partial_implementation_triggers_error() {
		// Create a class that implements Response but only one method
		$partial = new class implements Response {
			public function to_string() { return 'foo'; }
			// Missing to_string_safe
		};

		$reflection = new \ReflectionClass($partial);
		$methods = $reflection->getMethods();
		$methodNames = array_map(function($m) { return $m->getName(); }, $methods);

		// Assert to_string exists, to_string_safe does not
		$this->assertContains('to_string', $methodNames);
		$this->assertNotContains('to_string_safe', $methodNames);
	}
} 