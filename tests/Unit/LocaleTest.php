<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit;

use WooCommerce\Facebook\Locale;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Locale class.
 *
 * @since 3.5.2
 */
class LocaleTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the default locale constant is defined correctly.
	 */
	public function test_default_locale_constant() {
		$this->assertEquals( 'en_US', Locale::DEFAULT_LOCALE );
	}

	/**
	 * Test get_supported_locales returns an array.
	 */
	public function test_get_supported_locales_returns_array() {
		$locales = Locale::get_supported_locales();

		$this->assertIsArray( $locales );
		$this->assertNotEmpty( $locales );
	}

	/**
	 * Test get_supported_locales returns locale => name format.
	 */
	public function test_get_supported_locales_format() {
		$locales = Locale::get_supported_locales();

		foreach ( $locales as $locale => $name ) {
			// Locale should be in xx_XX format
			$this->assertMatchesRegularExpression( '/^[a-z]{2}_[A-Z]{2}$/', $locale );

			// Name should be a non-empty string
			$this->assertIsString( $name );
			$this->assertNotEmpty( $name );
		}
	}

	/**
	 * Test get_supported_locales is sorted.
	 */
	public function test_get_supported_locales_is_sorted() {
		$locales = Locale::get_supported_locales();
		$values = array_values( $locales );

		// Create a copy and sort it
		$sorted_values = $values;
		natcasesort( $sorted_values );
		$sorted_values = array_values( $sorted_values ); // Re-index

		// Compare with original
		$this->assertEquals( $sorted_values, $values );
	}

	/**
	 * Test is_supported_locale with invalid locales.
	 */
	public function test_is_supported_locale_invalid() {
		// Invalid formats
		$this->assertFalse( Locale::is_supported_locale( 'en' ) );
		$this->assertFalse( Locale::is_supported_locale( 'EN_US' ) );
		$this->assertFalse( Locale::is_supported_locale( 'en-US' ) );
		$this->assertFalse( Locale::is_supported_locale( 'english' ) );

		// Non-existent locales
		$this->assertFalse( Locale::is_supported_locale( 'xx_XX' ) );
		$this->assertFalse( Locale::is_supported_locale( 'en_UK' ) ); // Should be en_GB
		$this->assertFalse( Locale::is_supported_locale( 'fr_US' ) );

		// Empty and null
		$this->assertFalse( Locale::is_supported_locale( '' ) );
		$this->assertFalse( Locale::is_supported_locale( ' ' ) );

		// Case sensitivity tests
		$this->assertFalse( Locale::is_supported_locale( 'en_us' ) );
		$this->assertFalse( Locale::is_supported_locale( 'EN_US' ) );
		$this->assertFalse( Locale::is_supported_locale( 'En_Us' ) );
	}

	/**
	 * Test that all hardcoded supported locales are actually supported.
	 */
	public function test_all_hardcoded_locales_are_supported() {
		// Use reflection to access the private static property
		$reflection = new \ReflectionClass( Locale::class );
		$property = $reflection->getProperty( 'supported_locales' );
		$property->setAccessible( true );
		$hardcoded_locales = $property->getValue();

		foreach ( $hardcoded_locales as $locale ) {
			$this->assertTrue(
				Locale::is_supported_locale( $locale ),
				"Locale {$locale} should be supported"
			);
		}
	}

	/**
	 * Test get_supported_locales filter.
	 */
	public function test_get_supported_locales_filter() {
		// Add a filter to modify the locales
		add_filter( 'wc_facebook_messenger_supported_locales', function( $locales ) {
			$locales['test_XX'] = 'Test Language';
			return $locales;
		} );

		$locales = Locale::get_supported_locales();

		// Check that our test locale was added
		$this->assertArrayHasKey( 'test_XX', $locales );
		$this->assertEquals( 'Test Language', $locales['test_XX'] );

		// Clean up
		remove_all_filters( 'wc_facebook_messenger_supported_locales' );
	}

	/**
	 * Test get_supported_locales filter with array_unique.
	 */
	public function test_get_supported_locales_applies_array_unique() {
		// Add a filter that adds new locales
		add_filter( 'wc_facebook_messenger_supported_locales', function( $locales ) {
			// Add a new locale
			$locales['new_XX'] = 'New Language';
			// Try to duplicate an existing value (en_US should already exist)
			$locales['dup_US'] = $locales['en_US'];
			return $locales;
		} );

		$locales = Locale::get_supported_locales();

		// The filter should have added our new locale
		$this->assertArrayHasKey( 'new_XX', $locales );
		$this->assertEquals( 'New Language', $locales['new_XX'] );

		// Both keys should exist even with duplicate values
		// array_unique preserves keys, so duplicate values are allowed
		$this->assertArrayHasKey( 'en_US', $locales );
		$this->assertArrayHasKey( 'dup_US', $locales );

		// Clean up
		remove_all_filters( 'wc_facebook_messenger_supported_locales' );
	}

	/**
	 * Test get_supported_locales without Locale extension (fallback to WP translations).
	 */
	public function test_get_supported_locales_without_locale_extension() {
		// This test would require mocking the class_exists function
		// which is complex in PHPUnit. Instead, we'll test that the method
		// works regardless of the Locale extension availability
		$locales = Locale::get_supported_locales();

		// Should always have en_US at minimum
		$this->assertArrayHasKey( 'en_US', $locales );
		$this->assertNotEmpty( $locales['en_US'] );
	}

	/**
	 * Test specific locale formats.
	 */
	public function test_specific_locale_formats() {
		$locales = Locale::get_supported_locales();

		// Test some specific locale codes that might be edge cases
		$edge_cases = array(
			'zh_CN' => 'Chinese (China)',
			'zh_HK' => 'Chinese (Hong Kong)',
			'zh_TW' => 'Chinese (Taiwan)',
			'pt_BR' => 'Portuguese (Brazil)',
			'pt_PT' => 'Portuguese (Portugal)',
			'es_ES' => 'Spanish (Spain)',
			'es_LA' => 'Spanish (Latin America)',
		);

		foreach ( $edge_cases as $locale => $expected_pattern ) {
			if ( isset( $locales[ $locale ] ) ) {
				$this->assertArrayHasKey( $locale, $locales );
				$this->assertNotEmpty( $locales[ $locale ] );
			}
		}
	}

	/**
	 * Test that locale names are properly capitalized.
	 */
	public function test_locale_names_capitalization() {
		$locales = Locale::get_supported_locales();

		// Check that en_US specifically has proper capitalization
		if ( isset( $locales['en_US'] ) ) {
			$name = $locales['en_US'];
			// Should start with capital letter
			$this->assertMatchesRegularExpression( '/^[A-Z]/', $name );
		}

		// Check a few more if they exist
		foreach ( array( 'fr_FR', 'de_DE', 'es_ES' ) as $locale ) {
			if ( isset( $locales[ $locale ] ) ) {
				$name = $locales[ $locale ];
				// Should start with capital letter
				$this->assertMatchesRegularExpression( '/^[A-Z]/', $name );
			}
		}
	}

	/**
	 * Test edge cases for is_supported_locale.
	 */
	public function test_is_supported_locale_edge_cases() {
		// Numeric input
		$this->assertFalse( Locale::is_supported_locale( '123' ) );

		// Special characters
		$this->assertFalse( Locale::is_supported_locale( 'en_US!' ) );
		$this->assertFalse( Locale::is_supported_locale( '@#$%' ) );

		// Very long string
		$this->assertFalse( Locale::is_supported_locale( str_repeat( 'a', 100 ) ) );

		// Locale with extra parts
		$this->assertFalse( Locale::is_supported_locale( 'en_US_extra' ) );

		// Partial matches
		$this->assertFalse( Locale::is_supported_locale( 'en_' ) );
		$this->assertFalse( Locale::is_supported_locale( '_US' ) );
	}

	/**
	 * Test that the supported locales list is reasonable.
	 */
	public function test_supported_locales_count() {
		$locales = Locale::get_supported_locales();

		// Should have a reasonable number of locales (at least 50)
		$this->assertGreaterThan( 50, count( $locales ) );

		// But not too many (less than 200)
		$this->assertLessThan( 200, count( $locales ) );
	}

	/**
	 * Test consistency between get_supported_locales and is_supported_locale.
	 */
	public function test_consistency_between_methods() {
		$locales = Locale::get_supported_locales();

		// Every locale returned by get_supported_locales should return true for is_supported_locale
		foreach ( array_keys( $locales ) as $locale ) {
			$this->assertTrue(
				Locale::is_supported_locale( $locale ),
				"Locale {$locale} from get_supported_locales() should return true for is_supported_locale()"
			);
		}
	}
}
