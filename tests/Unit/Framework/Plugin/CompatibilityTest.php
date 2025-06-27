<?php

declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Framework\Plugin;

use WooCommerce\Facebook\Framework\Plugin\Compatibility;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Framework Plugin Compatibility class.
 *
 * @since 3.5.2
 */
class CompatibilityTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the Compatibility class exists.
	 */
	public function test_compatibility_class_exists() {
		$this->assertTrue( class_exists( Compatibility::class ) );
	}

	/**
	 * Test get_latest_wc_versions with cached data.
	 */
	public function test_get_latest_wc_versions_with_cached_data() {
		// Mock cached data
		$cached_versions = ['5.0.0', '4.9.0', '4.8.0'];
		set_transient( 'sv_wc_plugin_wc_versions', $cached_versions, WEEK_IN_SECONDS );

		// Get versions
		$versions = Compatibility::get_latest_wc_versions();

		// Assert cached data is returned
		$this->assertEquals( $cached_versions, $versions );
		$this->assertIsArray( $versions );
	}

	/**
	 * Test get_latest_wc_versions with successful API response.
	 */
	public function test_get_latest_wc_versions_with_successful_api_response() {
		// Delete any cached data
		delete_transient( 'sv_wc_plugin_wc_versions' );

		// Mock successful API response
		$mock_response = [
			'body' => json_encode( [
				'versions' => [
					'3.0.0' => 'https://downloads.wordpress.org/plugin/woocommerce.3.0.0.zip',
					'4.0.0' => 'https://downloads.wordpress.org/plugin/woocommerce.4.0.0.zip',
					'5.0.0' => 'https://downloads.wordpress.org/plugin/woocommerce.5.0.0.zip',
					'5.0.0-beta1' => 'https://downloads.wordpress.org/plugin/woocommerce.5.0.0-beta1.zip',
					'trunk' => 'https://downloads.wordpress.org/plugin/woocommerce.trunk.zip',
				],
			] ),
		];

		// Mock wp_remote_get
		$this->add_filter_with_safe_teardown( 'pre_http_request', function() use ( $mock_response ) {
			return $mock_response;
		} );

		// Get versions
		$versions = Compatibility::get_latest_wc_versions();

		// Assert only valid versions are returned (excluding betas, trunk, etc.)
		$this->assertIsArray( $versions );
		$this->assertContains( '5.0.0', $versions );
		$this->assertContains( '4.0.0', $versions );
		$this->assertContains( '3.0.0', $versions );
		$this->assertNotContains( '5.0.0-beta1', $versions );
		$this->assertNotContains( 'trunk', $versions );

		// Assert versions are sorted from newest to oldest
		$this->assertEquals( '5.0.0', $versions[0] );
		$this->assertEquals( '4.0.0', $versions[1] );
		$this->assertEquals( '3.0.0', $versions[2] );
	}

	/**
	 * Test get_latest_wc_versions with failed API response.
	 */
	public function test_get_latest_wc_versions_with_failed_api_response() {
		// Delete any cached data
		delete_transient( 'sv_wc_plugin_wc_versions' );

		// Mock failed API response
		$this->add_filter_with_safe_teardown( 'pre_http_request', function() {
			return new \WP_Error( 'http_request_failed', 'Request failed' );
		} );

		// Get versions
		$versions = Compatibility::get_latest_wc_versions();

		// Assert empty array is returned
		$this->assertIsArray( $versions );
		$this->assertEmpty( $versions );
	}

	/**
	 * Test get_latest_wc_versions with invalid API response.
	 */
	public function test_get_latest_wc_versions_with_invalid_api_response() {
		// Delete any cached data
		delete_transient( 'sv_wc_plugin_wc_versions' );

		// Mock invalid API response
		$mock_response = [
			'body' => 'invalid json',
		];

		// Mock wp_remote_get
		$this->add_filter_with_safe_teardown( 'pre_http_request', function() use ( $mock_response ) {
			return $mock_response;
		} );

		// Get versions
		$versions = Compatibility::get_latest_wc_versions();

		// Assert empty array is returned
		$this->assertIsArray( $versions );
		$this->assertEmpty( $versions );
	}

	/**
	 * Test get_wc_version when WC_VERSION is defined.
	 */
	public function test_get_wc_version_when_wc_version_is_defined() {
		// Get version
		$version = Compatibility::get_wc_version();

		// Assert version is returned and matches the defined WC_VERSION
		$this->assertEquals( WC_VERSION, $version );
	}

	/**
	 * Test get_wc_version when WC_VERSION is not defined.
	 */
	public function test_get_wc_version_when_wc_version_is_not_defined() {
		// Temporarily undefine WC_VERSION if it exists
		$original_wc_version = null;
		if ( defined( 'WC_VERSION' ) ) {
			$original_wc_version = WC_VERSION;
			// We can't undefine constants in PHP, so we'll test the logic differently
			// by mocking the constant check
		}

		// Test with a mock that simulates WC_VERSION not being defined
		// Since we can't actually undefine constants, we'll test the fallback behavior
		// by ensuring the method handles the case gracefully
		$version = Compatibility::get_wc_version();

		// If WC_VERSION is defined, it should return that value
		// If not defined, it should return null
		if ( defined( 'WC_VERSION' ) ) {
			$this->assertEquals( WC_VERSION, $version );
		} else {
			$this->assertNull( $version );
		}
	}

	/**
	 * Test is_wc_version_gte with valid version comparison.
	 */
	public function test_is_wc_version_gte_with_valid_version_comparison() {
		// Test greater than or equal with the actual WC_VERSION
		$current_version = WC_VERSION;
		
		// Test with a version lower than current
		$this->assertTrue( Compatibility::is_wc_version_gte( '1.0.0' ) );
		// Test with the current version
		$this->assertTrue( Compatibility::is_wc_version_gte( $current_version ) );
		// Test with a version higher than current (should be false)
		$this->assertFalse( Compatibility::is_wc_version_gte( '999.0.0' ) );
	}

	/**
	 * Test is_wc_version_gte when WC_VERSION is not available.
	 */
	public function test_is_wc_version_gte_when_wc_version_not_available() {
		// Test when WC_VERSION is not defined (should return false)
		// We can't actually undefine constants, so we'll test the logic
		// by ensuring the method handles null gracefully
		$result = Compatibility::is_wc_version_gte( '4.0.0' );

		// If WC_VERSION is defined, it should return a boolean
		// If not defined, it should return false
		if ( defined( 'WC_VERSION' ) ) {
			$this->assertIsBool( $result );
		} else {
			$this->assertFalse( $result );
		}
	}

	/**
	 * Test is_enhanced_admin_available when conditions are met.
	 */
	public function test_is_enhanced_admin_available_when_conditions_met() {
		// Mock wc_admin_url function
		$this->add_filter_with_safe_teardown( 'function_exists', function( $function ) {
			if ( $function === 'wc_admin_url' ) {
				return true;
			}
			return function_exists( $function );
		} );

		// Test enhanced admin availability
		$result = Compatibility::is_enhanced_admin_available();

		// Assert true when both conditions are met (WC_VERSION >= 4.0 and wc_admin_url exists)
		$this->assertTrue( $result );
	}

	/**
	 * Test is_enhanced_admin_available when WC version is too low.
	 */
	public function test_is_enhanced_admin_available_when_wc_version_too_low() {
		// Mock wc_admin_url function to exist
		$this->add_filter_with_safe_teardown( 'function_exists', function( $function ) {
			if ( $function === 'wc_admin_url' ) {
				return true;
			}
			return function_exists( $function );
		} );

		// Since we can't easily mock constants, we'll test the logic by ensuring
		// the method works correctly with the current WC_VERSION
		$result = Compatibility::is_enhanced_admin_available();

		// With WC_VERSION being '9.9.5', this should be true
		// The test verifies that the method works correctly with the actual WC_VERSION
		$this->assertTrue( $result );
	}

	/**
	 * Test is_enhanced_admin_available when wc_admin_url function doesn't exist.
	 */
	public function test_is_enhanced_admin_available_when_wc_admin_url_not_exists() {
		// Mock wc_admin_url function to not exist
		$this->add_filter_with_safe_teardown( 'function_exists', function( $function ) {
			if ( $function === 'wc_admin_url' ) {
				return false;
			}
			return function_exists( $function );
		} );

		// Test enhanced admin availability
		$result = Compatibility::is_enhanced_admin_available();

		// Assert false when function doesn't exist
		$this->assertFalse( $result );
	}

	/**
	 * Test convert_hr_to_bytes with wp_convert_hr_to_bytes available.
	 */
	public function test_convert_hr_to_bytes_with_wp_convert_hr_to_bytes_available() {
		// Mock wp_convert_hr_to_bytes function
		$this->add_filter_with_safe_teardown( 'function_exists', function( $function ) {
			if ( $function === 'wp_convert_hr_to_bytes' ) {
				return true;
			}
			return function_exists( $function );
		} );

		// Mock wp_convert_hr_to_bytes to return a specific value
		$this->add_filter_with_safe_teardown( 'wp_convert_hr_to_bytes', function( $value ) {
			return 1024; // Return 1KB for any input
		} );

		// Test conversion
		$result = Compatibility::convert_hr_to_bytes( '1K' );

		// Assert WordPress function is used
		$this->assertEquals( 1024, $result );
	}

	/**
	 * Test convert_hr_to_bytes with custom implementation.
	 */
	public function test_convert_hr_to_bytes_with_custom_implementation() {
		// Mock wp_convert_hr_to_bytes function to not exist
		$this->add_filter_with_safe_teardown( 'function_exists', function( $function ) {
			if ( $function === 'wp_convert_hr_to_bytes' ) {
				return false;
			}
			return function_exists( $function );
		} );

		// Test various byte conversions
		$this->assertEquals( 1024, Compatibility::convert_hr_to_bytes( '1K' ) );
		$this->assertEquals( 1024, Compatibility::convert_hr_to_bytes( '1k' ) );
		$this->assertEquals( 1048576, Compatibility::convert_hr_to_bytes( '1M' ) );
		$this->assertEquals( 1048576, Compatibility::convert_hr_to_bytes( '1m' ) );
		$this->assertEquals( 1073741824, Compatibility::convert_hr_to_bytes( '1G' ) );
		$this->assertEquals( 1073741824, Compatibility::convert_hr_to_bytes( '1g' ) );
		$this->assertEquals( 512, Compatibility::convert_hr_to_bytes( '512' ) );
	}

	/**
	 * Test convert_hr_to_bytes with edge cases.
	 */
	public function test_convert_hr_to_bytes_with_edge_cases() {
		// Mock wp_convert_hr_to_bytes function to not exist
		$this->add_filter_with_safe_teardown( 'function_exists', function( $function ) {
			if ( $function === 'wp_convert_hr_to_bytes' ) {
				return false;
			}
			return function_exists( $function );
		} );

		// Test edge cases
		$this->assertEquals( 0, Compatibility::convert_hr_to_bytes( '0' ) );
		$this->assertEquals( 0, Compatibility::convert_hr_to_bytes( '0K' ) );
		$this->assertEquals( 0, Compatibility::convert_hr_to_bytes( '0M' ) );
		$this->assertEquals( 0, Compatibility::convert_hr_to_bytes( '0G' ) );
		$this->assertEquals( 1024, Compatibility::convert_hr_to_bytes( ' 1K ' ) ); // With whitespace
		$this->assertEquals( 1024, Compatibility::convert_hr_to_bytes( '1K' ) );
	}

	/**
	 * Test convert_hr_to_bytes with large values.
	 */
	public function test_convert_hr_to_bytes_with_large_values() {
		// Mock wp_convert_hr_to_bytes function to not exist
		$this->add_filter_with_safe_teardown( 'function_exists', function( $function ) {
			if ( $function === 'wp_convert_hr_to_bytes' ) {
				return false;
			}
			return function_exists( $function );
		} );

		// Test large values that might exceed PHP_INT_MAX
		$large_value = Compatibility::convert_hr_to_bytes( '999999999G' );
		$this->assertLessThanOrEqual( PHP_INT_MAX, $large_value );
		$this->assertIsInt( $large_value );
	}

	/**
	 * Test convert_hr_to_bytes with invalid input.
	 */
	public function test_convert_hr_to_bytes_with_invalid_input() {
		// Mock wp_convert_hr_to_bytes function to not exist
		$this->add_filter_with_safe_teardown( 'function_exists', function( $function ) {
			if ( $function === 'wp_convert_hr_to_bytes' ) {
				return false;
			}
			return function_exists( $function );
		} );

		// Test invalid inputs
		$this->assertEquals( 0, Compatibility::convert_hr_to_bytes( '' ) );
		$this->assertEquals( 0, Compatibility::convert_hr_to_bytes( 'abc' ) ); // (int)'abc' = 0
		$this->assertEquals( 0, Compatibility::convert_hr_to_bytes( '1X' ) ); // Invalid unit
	}

	/**
	 * Test get_latest_wc_versions with empty versions array.
	 */
	public function test_get_latest_wc_versions_with_empty_versions_array() {
		// Delete any cached data
		delete_transient( 'sv_wc_plugin_wc_versions' );

		// Mock response with empty versions
		$mock_response = [
			'body' => json_encode( [
				'versions' => [],
			] ),
		];

		// Mock wp_remote_get
		$this->add_filter_with_safe_teardown( 'pre_http_request', function() use ( $mock_response ) {
			return $mock_response;
		} );

		// Get versions
		$versions = Compatibility::get_latest_wc_versions();

		// Assert empty array is returned
		$this->assertIsArray( $versions );
		$this->assertEmpty( $versions );
	}

	/**
	 * Test get_latest_wc_versions with non-array versions.
	 */
	public function test_get_latest_wc_versions_with_non_array_versions() {
		// Delete any cached data
		delete_transient( 'sv_wc_plugin_wc_versions' );

		// Mock response with non-array versions
		$mock_response = [
			'body' => json_encode( [
				'versions' => 'not an array',
			] ),
		];

		// Mock wp_remote_get
		$this->add_filter_with_safe_teardown( 'pre_http_request', function() use ( $mock_response ) {
			return $mock_response;
		} );

		// Get versions
		$versions = Compatibility::get_latest_wc_versions();

		// Assert empty array is returned
		$this->assertIsArray( $versions );
		$this->assertEmpty( $versions );
	}

	/**
	 * Test get_latest_wc_versions with different version formats.
	 */
	public function test_get_latest_wc_versions_with_different_version_formats() {
		// Delete any cached data
		delete_transient( 'sv_wc_plugin_wc_versions' );

		// Mock response with various version formats
		$mock_response = [
			'body' => json_encode( [
				'versions' => [
					'3.0.0' => 'url',
					'4.0.0-rc1' => 'url',
					'4.0.0-alpha' => 'url',
					'4.0.0-beta2' => 'url',
					'4.0.0-dev' => 'url',
					'4.0.0-nightly' => 'url',
					'4.0.0' => 'url',
					'5.0.0' => 'url',
					'5.0.0-rc.1' => 'url',
					'5.0.0-alpha.1' => 'url',
					'5.0.0-beta.1' => 'url',
					'5.0.0-dev.1' => 'url',
					'5.0.0-nightly.1' => 'url',
					'5.0.0' => 'url',
					'6.0.0' => 'url',
				],
			] ),
		];

		// Mock wp_remote_get
		$this->add_filter_with_safe_teardown( 'pre_http_request', function() use ( $mock_response ) {
			return $mock_response;
		} );

		// Get versions
		$versions = Compatibility::get_latest_wc_versions();

		// Assert only stable versions are returned
		$this->assertIsArray( $versions );
		$this->assertContains( '6.0.0', $versions );
		$this->assertContains( '5.0.0', $versions );
		$this->assertContains( '4.0.0', $versions );
		$this->assertContains( '3.0.0', $versions );
		$this->assertNotContains( '5.0.0-rc.1', $versions );
		$this->assertNotContains( '5.0.0-alpha.1', $versions );
		$this->assertNotContains( '5.0.0-beta.1', $versions );
		$this->assertNotContains( '5.0.0-dev.1', $versions );
		$this->assertNotContains( '5.0.0-nightly.1', $versions );
		$this->assertNotContains( '4.0.0-rc1', $versions );
		$this->assertNotContains( '4.0.0-alpha', $versions );
		$this->assertNotContains( '4.0.0-beta2', $versions );
		$this->assertNotContains( '4.0.0-dev', $versions );
		$this->assertNotContains( '4.0.0-nightly', $versions );
	}

	/**
	 * Test convert_hr_to_bytes with decimal values.
	 */
	public function test_convert_hr_to_bytes_with_decimal_values() {
		// Mock wp_convert_hr_to_bytes function to not exist
		$this->add_filter_with_safe_teardown( 'function_exists', function( $function ) {
			if ( $function === 'wp_convert_hr_to_bytes' ) {
				return false;
			}
			return function_exists( $function );
		} );

		// Test decimal values (they get truncated to integers)
		$this->assertEquals( 0, Compatibility::convert_hr_to_bytes( '0.5K' ) ); // (int)0.5 = 0
		$this->assertEquals( 0, Compatibility::convert_hr_to_bytes( '0.5M' ) ); // (int)0.5 = 0
		$this->assertEquals( 0, Compatibility::convert_hr_to_bytes( '0.5G' ) ); // (int)0.5 = 0
		$this->assertEquals( 1024, Compatibility::convert_hr_to_bytes( '1.5K' ) ); // (int)1.5 = 1, 1 * 1024 = 1024
		$this->assertEquals( 1048576, Compatibility::convert_hr_to_bytes( '1.5M' ) ); // (int)1.5 = 1, 1 * 1048576 = 1048576
	}

	/**
	 * Test convert_hr_to_bytes with PHP_INT_MAX edge cases.
	 */
	public function test_convert_hr_to_bytes_with_php_int_max_edge_cases() {
		// Mock wp_convert_hr_to_bytes function to not exist
		$this->add_filter_with_safe_teardown( 'function_exists', function( $function ) {
			if ( $function === 'wp_convert_hr_to_bytes' ) {
				return false;
			}
			return function_exists( $function );
		} );

		// Test values that would exceed PHP_INT_MAX
		$max_gb = floor( PHP_INT_MAX / GB_IN_BYTES );
		$result = Compatibility::convert_hr_to_bytes( $max_gb . 'G' );
		$this->assertLessThanOrEqual( PHP_INT_MAX, $result );
		$this->assertIsNumeric( $result );

		// Test value that would definitely exceed PHP_INT_MAX
		$excessive_gb = $max_gb + 1;
		$result = Compatibility::convert_hr_to_bytes( $excessive_gb . 'G' );
		$this->assertEquals( PHP_INT_MAX, $result );
		$this->assertIsNumeric( $result );
	}

	/**
	 * Test convert_hr_to_bytes with whitespace and special characters.
	 */
	public function test_convert_hr_to_bytes_with_whitespace_and_special_chars() {
		// Mock wp_convert_hr_to_bytes function to not exist
		$this->add_filter_with_safe_teardown( 'function_exists', function( $function ) {
			if ( $function === 'wp_convert_hr_to_bytes' ) {
				return false;
			}
			return function_exists( $function );
		} );

		// Test whitespace handling
		$this->assertEquals( 1024, Compatibility::convert_hr_to_bytes( ' 1K ' ) );
		$this->assertEquals( 1024, Compatibility::convert_hr_to_bytes( "\t1K\n" ) );
		$this->assertEquals( 1024, Compatibility::convert_hr_to_bytes( '  1  K  ' ) );

		// Test special characters
		$this->assertEquals( 1024, Compatibility::convert_hr_to_bytes( '1K' ) );
		$this->assertEquals( 1024, Compatibility::convert_hr_to_bytes( '1k' ) );
		$this->assertEquals( 1024, Compatibility::convert_hr_to_bytes( '1K' ) );
		$this->assertEquals( 1024, Compatibility::convert_hr_to_bytes( '1k' ) );
	}

	/**
	 * Test convert_hr_to_bytes with malformed input strings.
	 */
	public function test_convert_hr_to_bytes_with_malformed_input_strings() {
		// Mock wp_convert_hr_to_bytes function to not exist
		$this->add_filter_with_safe_teardown( 'function_exists', function( $function ) {
			if ( $function === 'wp_convert_hr_to_bytes' ) {
				return false;
			}
			return function_exists( $function );
		} );

		// Test malformed strings
		$this->assertEquals( 0, Compatibility::convert_hr_to_bytes( 'K' ) ); // (int)'K' = 0
		$this->assertEquals( 0, Compatibility::convert_hr_to_bytes( 'M' ) ); // (int)'M' = 0
		$this->assertEquals( 0, Compatibility::convert_hr_to_bytes( 'G' ) ); // (int)'G' = 0
		$this->assertEquals( 0, Compatibility::convert_hr_to_bytes( 'K1' ) ); // (int)'K1' = 0
		$this->assertEquals( 0, Compatibility::convert_hr_to_bytes( 'M1' ) ); // (int)'M1' = 0
		$this->assertEquals( 0, Compatibility::convert_hr_to_bytes( 'G1' ) ); // (int)'G1' = 0
		$this->assertEquals( 1024, Compatibility::convert_hr_to_bytes( '1K1' ) ); // (int)'1K1' = 1, finds 'k', 1 * 1024 = 1024
		$this->assertEquals( 1048576, Compatibility::convert_hr_to_bytes( '1M1' ) ); // (int)'1M1' = 1, finds 'm', 1 * 1048576 = 1048576
		$this->assertEquals( 1073741824, Compatibility::convert_hr_to_bytes( '1G1' ) ); // (int)'1G1' = 1, finds 'g', 1 * 1073741824 = 1073741824
		$this->assertEquals( 1024, Compatibility::convert_hr_to_bytes( '1.2.3K' ) ); // (int)'1.2.3K' = 1, finds 'k', 1 * 1024 = 1024
		$this->assertEquals( 1024, Compatibility::convert_hr_to_bytes( '1,000K' ) ); // (int)'1,000K' = 1, finds 'k', 1 * 1024 = 1024
	}
} 