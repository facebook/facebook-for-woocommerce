<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Events;

use WooCommerce\Facebook\Events\Normalizer;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;
use InvalidArgumentException;

/**
 * Unit tests for Events\Normalizer class.
 *
 * @since 3.5.2
 */
class NormalizerTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test normalize method with null data.
	 */
	public function test_normalize_with_null_data() {
		$this->assertNull( Normalizer::normalize( 'em', null ) );
		$this->assertNull( Normalizer::normalize( 'ph', null ) );
		$this->assertNull( Normalizer::normalize( 'ct', null ) );
	}

	/**
	 * Test normalize method with empty string.
	 */
	public function test_normalize_with_empty_string() {
		$this->assertNull( Normalizer::normalize( 'em', '' ) );
		$this->assertNull( Normalizer::normalize( 'ph', '' ) );
		$this->assertNull( Normalizer::normalize( 'zp', '' ) );
	}

	/**
	 * Test email normalization with valid emails.
	 */
	public function test_normalize_email_valid() {
		// Basic email
		$this->assertEquals( 'test@example.com', Normalizer::normalize( 'em', 'test@example.com' ) );
		
		// Email with uppercase
		$this->assertEquals( 'test@example.com', Normalizer::normalize( 'em', 'TEST@EXAMPLE.COM' ) );
		
		// Email with spaces
		$this->assertEquals( 'test@example.com', Normalizer::normalize( 'em', '  test@example.com  ' ) );
		
		// Email with subdomain
		$this->assertEquals( 'user@mail.example.com', Normalizer::normalize( 'em', 'user@mail.example.com' ) );
		
		// Email with numbers
		$this->assertEquals( 'user123@example.com', Normalizer::normalize( 'em', 'USER123@EXAMPLE.COM' ) );
		
		// Email with dots
		$this->assertEquals( 'first.last@example.com', Normalizer::normalize( 'em', 'first.last@example.com' ) );
		
		// Email with plus
		$this->assertEquals( 'user+tag@example.com', Normalizer::normalize( 'em', 'user+tag@example.com' ) );
	}

	/**
	 * Test email normalization with invalid emails.
	 */
	public function test_normalize_email_invalid() {
		// Missing @
		$this->expectException( InvalidArgumentException::class );
		Normalizer::normalize( 'em', 'notanemail' );
	}

	/**
	 * Test email normalization with invalid format.
	 */
	public function test_normalize_email_invalid_format() {
		// Missing domain
		$this->expectException( InvalidArgumentException::class );
		Normalizer::normalize( 'em', 'test@' );
	}

	/**
	 * Test email normalization with invalid characters.
	 */
	public function test_normalize_email_invalid_characters() {
		// Double @
		$this->expectException( InvalidArgumentException::class );
		Normalizer::normalize( 'em', 'test@@example.com' );
	}

	/**
	 * Test phone normalization with US numbers.
	 */
	public function test_normalize_phone_us_numbers() {
		// Basic US number
		$this->assertEquals( '5551234567', Normalizer::normalize( 'ph', '555-123-4567' ) );
		
		// With parentheses
		$this->assertEquals( '5551234567', Normalizer::normalize( 'ph', '(555) 123-4567' ) );
		
		// With spaces
		$this->assertEquals( '5551234567', Normalizer::normalize( 'ph', '555 123 4567' ) );
		
		// With dots
		$this->assertEquals( '555.123.4567', Normalizer::normalize( 'ph', '555.123.4567' ) );
		
		// With country code
		$this->assertEquals( '15551234567', Normalizer::normalize( 'ph', '+1-555-123-4567' ) );
	}

	/**
	 * Test phone normalization with international numbers.
	 */
	public function test_normalize_phone_international_numbers() {
		// UK number
		$this->assertEquals( '442079460958', Normalizer::normalize( 'ph', '+44 20 7946 0958' ) );
		
		// German number
		$this->assertEquals( '49301234567', Normalizer::normalize( 'ph', '+49 30 1234567' ) );
		
		// French number
		$this->assertEquals( '33123456789', Normalizer::normalize( 'ph', '+33 1 23 45 67 89' ) );
		
		// With leading zeros
		$this->assertEquals( '00442079460958', Normalizer::normalize( 'ph', '0044 20 7946 0958' ) );
	}

	/**
	 * Test phone normalization with letters.
	 */
	public function test_normalize_phone_with_letters() {
		// Phone with letters (should be removed)
		$this->assertEquals( '555', Normalizer::normalize( 'ph', '555-CALL' ) );
		
		// Mixed letters and numbers
		$this->assertEquals( '18005551234', Normalizer::normalize( 'ph', '1-800-555-1234-CALL' ) );
	}

	/**
	 * Test country normalization with valid codes.
	 */
	public function test_normalize_country_valid() {
		// Uppercase
		$this->assertEquals( 'us', Normalizer::normalize( 'country', 'US' ) );
		
		// Lowercase
		$this->assertEquals( 'gb', Normalizer::normalize( 'country', 'gb' ) );
		
		// Mixed case
		$this->assertEquals( 'ca', Normalizer::normalize( 'country', 'Ca' ) );
		
		// With spaces
		$this->assertEquals( 'fr', Normalizer::normalize( 'country', ' FR ' ) );
		
		// Using 'cn' field
		$this->assertEquals( 'de', Normalizer::normalize( 'cn', 'DE' ) );
	}

	/**
	 * Test country normalization with invalid codes.
	 */
	public function test_normalize_country_invalid() {
		// Too long
		$this->expectException( InvalidArgumentException::class );
		Normalizer::normalize( 'country', 'USA' );
	}

	/**
	 * Test country normalization with single character.
	 */
	public function test_normalize_country_single_char() {
		// Too short
		$this->expectException( InvalidArgumentException::class );
		Normalizer::normalize( 'country', 'U' );
	}

	/**
	 * Test country normalization with numbers.
	 */
	public function test_normalize_country_with_numbers() {
		// Numbers should be removed, resulting in invalid length
		$this->expectException( InvalidArgumentException::class );
		Normalizer::normalize( 'country', 'U1' );
	}

	/**
	 * Test zip code normalization.
	 */
	public function test_normalize_zip_code() {
		// US 5-digit
		$this->assertEquals( '12345', Normalizer::normalize( 'zp', '12345' ) );
		
		// US ZIP+4
		$this->assertEquals( '12345', Normalizer::normalize( 'zp', '12345-6789' ) );
		
		// With spaces
		$this->assertEquals( '12345', Normalizer::normalize( 'zp', '12 345' ) );
		
		// Canadian postal code
		$this->assertEquals( 'k1a0b1', Normalizer::normalize( 'zp', 'K1A 0B1' ) );
		
		// UK postcode
		$this->assertEquals( 'sw1a1aa', Normalizer::normalize( 'zp', 'SW1A 1AA' ) );
		
		// With multiple hyphens
		$this->assertEquals( '12345', Normalizer::normalize( 'zp', '12345-6789-1234' ) );
	}

	/**
	 * Test city normalization.
	 */
	public function test_normalize_city() {
		// Basic city
		$this->assertEquals( 'newyork', Normalizer::normalize( 'ct', 'New York' ) );
		
		// With numbers
		$this->assertEquals( 'losangeles', Normalizer::normalize( 'ct', 'Los Angeles 90210' ) );
		
		// With hyphens
		$this->assertEquals( 'saintlouis', Normalizer::normalize( 'ct', 'Saint-Louis' ) );
		
		// With dots
		$this->assertEquals( 'stlouis', Normalizer::normalize( 'ct', 'St. Louis' ) );
		
		// With parentheses (parentheses are removed but content remains)
		$this->assertEquals( 'parisfrance', Normalizer::normalize( 'ct', 'Paris (France)' ) );
		
		// Multiple spaces
		$this->assertEquals( 'sanfrancisco', Normalizer::normalize( 'ct', '  San   Francisco  ' ) );
	}

	/**
	 * Test state normalization.
	 */
	public function test_normalize_state() {
		// State code
		$this->assertEquals( 'ca', Normalizer::normalize( 'st', 'CA' ) );
		
		// Full state name
		$this->assertEquals( 'california', Normalizer::normalize( 'st', 'California' ) );
		
		// With spaces
		$this->assertEquals( 'newyork', Normalizer::normalize( 'st', 'New York' ) );
		
		// With numbers (should be removed)
		$this->assertEquals( 'state', Normalizer::normalize( 'st', 'State123' ) );
		
		// With special characters
		$this->assertEquals( 'dc', Normalizer::normalize( 'st', 'D.C.' ) );
		
		// With hyphens
		$this->assertEquals( 'northcarolina', Normalizer::normalize( 'st', 'North-Carolina' ) );
	}

	/**
	 * Test normalize with unknown field type.
	 */
	public function test_normalize_unknown_field() {
		// Unknown field should return the trimmed lowercase value
		$this->assertEquals( 'test value', Normalizer::normalize( 'unknown', '  TEST VALUE  ' ) );
		
		// Another unknown field
		$this->assertEquals( 'data123', Normalizer::normalize( 'custom', 'DATA123' ) );
	}

	/**
	 * Test normalize_array with pixel data.
	 */
	public function test_normalize_array_pixel_data() {
		$data = array(
			'fn' => 'JOHN',
			'ln' => 'DOE',
			'em' => 'john@example.com',
			'ph' => '555-123-4567',
			'zp' => '12345-6789',
			'ct' => 'New York',
			'st' => 'NY',
			'cn' => 'US',
		);
		
		$normalized = Normalizer::normalize_array( $data, true );
		
		$this->assertEquals( 'john', $normalized['fn'] );
		$this->assertEquals( 'doe', $normalized['ln'] );
		$this->assertEquals( 'john@example.com', $normalized['em'] );
		$this->assertEquals( '5551234567', $normalized['ph'] );
		$this->assertEquals( '12345', $normalized['zp'] );
		$this->assertEquals( 'newyork', $normalized['ct'] );
		$this->assertEquals( 'ny', $normalized['st'] );
		$this->assertEquals( 'us', $normalized['cn'] );
	}

	/**
	 * Test normalize_array with CAPI data.
	 */
	public function test_normalize_array_capi_data() {
		$data = array(
			'fn' => 'Jane',
			'ln' => 'Smith',
			'em' => 'JANE@EXAMPLE.COM',
			'country' => 'GB',
		);
		
		$normalized = Normalizer::normalize_array( $data, false );
		
		$this->assertEquals( 'jane', $normalized['fn'] );
		$this->assertEquals( 'smith', $normalized['ln'] );
		$this->assertEquals( 'jane@example.com', $normalized['em'] );
		$this->assertEquals( 'gb', $normalized['country'] );
	}

	/**
	 * Test normalize_array with invalid data that should be removed.
	 */
	public function test_normalize_array_with_invalid_data() {
		$data = array(
			'em' => 'invalid-email',
			'country' => 'USA', // Too long
			'ph' => '555-123-4567',
			'fn' => 'John',
		);
		
		$normalized = Normalizer::normalize_array( $data, false );
		
		// Invalid fields should be removed
		$this->assertArrayNotHasKey( 'em', $normalized );
		$this->assertArrayNotHasKey( 'country', $normalized );
		
		// Valid fields should remain
		$this->assertArrayHasKey( 'ph', $normalized );
		$this->assertArrayHasKey( 'fn', $normalized );
		$this->assertEquals( '5551234567', $normalized['ph'] );
		$this->assertEquals( 'john', $normalized['fn'] );
	}

	/**
	 * Test normalize_array with missing fields.
	 */
	public function test_normalize_array_with_missing_fields() {
		$data = array(
			'fn' => 'John',
			'custom_field' => 'value',
		);
		
		$normalized = Normalizer::normalize_array( $data, true );
		
		// Only normalized field should be present
		$this->assertEquals( 'john', $normalized['fn'] );
		
		// Custom field should remain unchanged
		$this->assertEquals( 'value', $normalized['custom_field'] );
		
		// Missing fields should not be added
		$this->assertArrayNotHasKey( 'ln', $normalized );
		$this->assertArrayNotHasKey( 'em', $normalized );
	}

	/**
	 * Test normalize_array preserves non-normalizable fields.
	 */
	public function test_normalize_array_preserves_other_fields() {
		$data = array(
			'fn' => 'John',
			'custom1' => 'Value1',
			'custom2' => array( 'nested' => 'data' ),
			'em' => 'john@example.com',
		);
		
		$normalized = Normalizer::normalize_array( $data, false );
		
		$this->assertEquals( 'john', $normalized['fn'] );
		$this->assertEquals( 'john@example.com', $normalized['em'] );
		$this->assertEquals( 'Value1', $normalized['custom1'] );
		$this->assertEquals( array( 'nested' => 'data' ), $normalized['custom2'] );
	}

	/**
	 * Test edge cases for email normalization.
	 */
	public function test_normalize_email_edge_cases() {
		// Very long email
		$long_email = str_repeat( 'a', 50 ) . '@' . str_repeat( 'b', 50 ) . '.com';
		$this->assertEquals( strtolower( $long_email ), Normalizer::normalize( 'em', $long_email ) );
		
		// Email with underscores
		$this->assertEquals( 'user_name@example.com', Normalizer::normalize( 'em', 'user_name@example.com' ) );
		
		// Email with hyphens
		$this->assertEquals( 'user-name@ex-ample.com', Normalizer::normalize( 'em', 'user-name@ex-ample.com' ) );
	}

	/**
	 * Test edge cases for phone normalization.
	 */
	public function test_normalize_phone_edge_cases() {
		// Empty after removing letters
		$this->assertEquals( '', Normalizer::normalize( 'ph', 'CALL-NOW' ) );
		
		// Only numbers
		$this->assertEquals( '1234567890', Normalizer::normalize( 'ph', '1234567890' ) );
		
		// With extension notation (x)
		$this->assertEquals( '5551234567123', Normalizer::normalize( 'ph', '555-123-4567x123' ) );
	}

	/**
	 * Test special characters handling.
	 */
	public function test_special_characters_handling() {
		// City with accents (accents remain in lowercase)
		$this->assertEquals( 'montréal', Normalizer::normalize( 'ct', 'Montréal' ) );
		
		// State with accents (non-letter characters are removed)
		$this->assertEquals( 'qubec', Normalizer::normalize( 'st', 'Québec' ) );
		
		// Zip with special chars (hyphen splits the code, only first part is kept)
		$this->assertEquals( 'ab', Normalizer::normalize( 'zp', 'A B-C' ) );
	}

	/**
	 * Test normalize_array with empty array.
	 */
	public function test_normalize_array_with_empty_array() {
		$data = array();
		
		$normalized = Normalizer::normalize_array( $data, true );
		
		$this->assertIsArray( $normalized );
		$this->assertEmpty( $normalized );
	}

	/**
	 * Test normalize_array with all null values.
	 */
	public function test_normalize_array_with_null_values() {
		$data = array(
			'fn' => null,
			'ln' => null,
			'em' => null,
			'ph' => null,
		);
		
		$normalized = Normalizer::normalize_array( $data, false );
		
		// Null values should be normalized to null
		$this->assertNull( $normalized['fn'] );
		$this->assertNull( $normalized['ln'] );
		$this->assertNull( $normalized['em'] );
		$this->assertNull( $normalized['ph'] );
	}

	/**
	 * Test phone normalization with leading zero (non-international).
	 */
	public function test_normalize_phone_with_leading_zero() {
		// Phone starting with 0 (non-international)
		$this->assertEquals( '0123456789', Normalizer::normalize( 'ph', '0123456789' ) );
		
		// With formatting
		$this->assertEquals( '0123456789', Normalizer::normalize( 'ph', '(012) 345-6789' ) );
	}

	/**
	 * Test zip code with only hyphens.
	 */
	public function test_normalize_zip_code_only_hyphens() {
		// Only hyphens should result in empty string
		$this->assertEquals( '', Normalizer::normalize( 'zp', '---' ) );
	}

	/**
	 * Test city with only numbers.
	 */
	public function test_normalize_city_only_numbers() {
		// Only numbers should be removed, resulting in empty string
		$this->assertEquals( '', Normalizer::normalize( 'ct', '12345' ) );
	}

	/**
	 * Test state with only special characters.
	 */
	public function test_normalize_state_only_special_chars() {
		// Only special characters should be removed
		$this->assertEquals( '', Normalizer::normalize( 'st', '---' ) );
		$this->assertEquals( '', Normalizer::normalize( 'st', '...' ) );
		$this->assertEquals( '', Normalizer::normalize( 'st', '123' ) );
	}

	/**
	 * Test country normalization with special characters.
	 */
	public function test_normalize_country_with_special_chars() {
		// Special characters should be removed, but length must still be 2
		$this->expectException( InvalidArgumentException::class );
		Normalizer::normalize( 'country', 'U-S' );
	}

	/**
	 * Test normalize with whitespace-only string.
	 */
	public function test_normalize_with_whitespace_only() {
		// Whitespace-only should be treated as empty
		$this->assertNull( Normalizer::normalize( 'em', '   ' ) );
		$this->assertNull( Normalizer::normalize( 'ph', '   ' ) );
		$this->assertNull( Normalizer::normalize( 'ct', '   ' ) );
	}

	/**
	 * Test email with multiple dots in domain.
	 */
	public function test_normalize_email_multiple_dots_in_domain() {
		// Multiple dots in domain should be valid
		$this->assertEquals( 'user@mail.server.example.com', Normalizer::normalize( 'em', 'user@mail.server.example.com' ) );
		
		// Consecutive dots should be invalid
		$this->expectException( InvalidArgumentException::class );
		Normalizer::normalize( 'em', 'user@example..com' );
	}

	/**
	 * Test phone with only special characters.
	 */
	public function test_normalize_phone_only_special_chars() {
		// Only special characters should result in empty string
		$this->assertEquals( '', Normalizer::normalize( 'ph', '---()' ) );
	}

	/**
	 * Test normalize_array switching between pixel and CAPI.
	 */
	public function test_normalize_array_pixel_vs_capi_country_field() {
		$data = array(
			'fn' => 'John',
			'cn' => 'US',
			'country' => 'GB',
		);
		
		// With pixel data, 'cn' should be normalized
		$normalized_pixel = Normalizer::normalize_array( $data, true );
		$this->assertEquals( 'us', $normalized_pixel['cn'] );
		$this->assertEquals( 'GB', $normalized_pixel['country'] ); // Not normalized for pixel
		
		// With CAPI data, 'country' should be normalized
		$normalized_capi = Normalizer::normalize_array( $data, false );
		$this->assertEquals( 'gb', $normalized_capi['country'] );
		$this->assertEquals( 'US', $normalized_capi['cn'] ); // Not normalized for CAPI
	}

	/**
	 * Test UTF-8 multibyte characters in various fields.
	 */
	public function test_normalize_utf8_multibyte_characters() {
		// City with UTF-8 characters
		$this->assertEquals( 'münchen', Normalizer::normalize( 'ct', 'München' ) );
		
		// State with UTF-8 characters (non-ASCII letters are removed)
		$this->assertEquals( 'bayern', Normalizer::normalize( 'st', 'Bayern' ) );
		
		// Zip with UTF-8 characters
		$this->assertEquals( 'abc123', Normalizer::normalize( 'zp', 'ABC 123' ) );
	}

	/**
	 * Test normalize with very long strings.
	 */
	public function test_normalize_with_very_long_strings() {
		// Very long city name
		$long_city = str_repeat( 'City', 100 );
		$result = Normalizer::normalize( 'ct', $long_city );
		$this->assertEquals( strtolower( $long_city ), $result );
		
		// Very long state name
		$long_state = str_repeat( 'State', 100 );
		$result = Normalizer::normalize( 'st', $long_state );
		$this->assertEquals( strtolower( $long_state ), $result );
	}

	/**
	 * Test phone normalization with various international formats.
	 */
	public function test_normalize_phone_various_international_formats() {
		// With double zero prefix
		$this->assertEquals( '00123456789', Normalizer::normalize( 'ph', '00 12 345 6789' ) );
		
		// With plus and parentheses
		$this->assertEquals( '123456789', Normalizer::normalize( 'ph', '+1 (234) 567-89' ) );
		
		// Short international number
		$this->assertEquals( '12345678', Normalizer::normalize( 'ph', '+1 234 5678' ) );
	}

	/**
	 * Test zip code with complex formats.
	 */
	public function test_normalize_zip_code_complex_formats() {
		// Multiple spaces
		$this->assertEquals( 'a1b2c3', Normalizer::normalize( 'zp', 'A 1 B 2 C 3' ) );
		
		// Mixed case with hyphens
		$this->assertEquals( 'abc', Normalizer::normalize( 'zp', 'ABC-DEF-GHI' ) );
		
		// Only first part before hyphen
		$this->assertEquals( '12345', Normalizer::normalize( 'zp', '12345-6789-0000' ) );
	}

	/**
	 * Test normalize_array with mixed valid and invalid emails.
	 */
	public function test_normalize_array_mixed_valid_invalid() {
		$data = array(
			'fn' => 'John',
			'ln' => 'Doe',
			'em' => 'invalid@',
			'ph' => '555-123-4567',
			'country' => 'U', // Invalid - too short
		);
		
		$normalized = Normalizer::normalize_array( $data, false );
		
		// Valid fields should be normalized
		$this->assertEquals( 'john', $normalized['fn'] );
		$this->assertEquals( 'doe', $normalized['ln'] );
		$this->assertEquals( '5551234567', $normalized['ph'] );
		
		// Invalid fields should be removed
		$this->assertArrayNotHasKey( 'em', $normalized );
		$this->assertArrayNotHasKey( 'country', $normalized );
	}

	/**
	 * Test city normalization removes all numeric and special characters.
	 */
	public function test_normalize_city_removes_all_numeric_special() {
		// Mix of letters, numbers, and special chars
		$this->assertEquals( 'cityname', Normalizer::normalize( 'ct', 'City123-Name.456 (789)' ) );
		
		// Only special characters and numbers
		$this->assertEquals( '', Normalizer::normalize( 'ct', '123-456.789 ()' ) );
	}

	/**
	 * Test email normalization preserves valid special characters.
	 */
	public function test_normalize_email_preserves_valid_special_chars() {
		// Underscore in local part
		$this->assertEquals( 'user_name@example.com', Normalizer::normalize( 'em', 'USER_NAME@EXAMPLE.COM' ) );
		
		// Hyphen in domain
		$this->assertEquals( 'user@my-domain.com', Normalizer::normalize( 'em', 'user@my-domain.com' ) );
		
		// Plus sign in local part
		$this->assertEquals( 'user+tag@example.com', Normalizer::normalize( 'em', 'USER+TAG@EXAMPLE.COM' ) );
	}
} 
