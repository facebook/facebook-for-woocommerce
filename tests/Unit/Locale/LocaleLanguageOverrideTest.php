<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Locale;

use WooCommerce\Facebook\Locale;
use WooCommerce\Facebook\Framework\Plugin\Exception as PluginException;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;

/**
 * Unit tests for Locale language override functionality.
 *
 * Tests the conversion of locale codes to Facebook's language override format
 * for language override feeds. These tests focus on the new methods added in 3.6.0
 * for supporting language-specific product feeds.
 *
 * @since 3.6.0
 */
class LocaleLanguageOverrideTest extends AbstractWPUnitTestWithSafeFiltering {

	// ========================================
	// Tests for convert_to_facebook_language_code()
	// ========================================

	/**
	 * Test _XX language conversion for English variants.
	 *
	 * Facebook uses en_XX for all English language variants in override feeds.
	 */
	public function test_convert_english_variants_to_xx_format() {
		$this->assertEquals( 'en_XX', Locale::convert_to_facebook_language_code( 'en_US' ) );
		$this->assertEquals( 'en_XX', Locale::convert_to_facebook_language_code( 'en_GB' ) );
		$this->assertEquals( 'en_XX', Locale::convert_to_facebook_language_code( 'en_CA' ) );
		$this->assertEquals( 'en_XX', Locale::convert_to_facebook_language_code( 'en_AU' ) );
		$this->assertEquals( 'en_XX', Locale::convert_to_facebook_language_code( 'en_NZ' ) );
		$this->assertEquals( 'en_XX', Locale::convert_to_facebook_language_code( 'en_IE' ) );
	}

	/**
	 * Test _XX language conversion for Spanish variants.
	 *
	 * Facebook uses es_XX for all Spanish language variants.
	 */
	public function test_convert_spanish_variants_to_xx_format() {
		$this->assertEquals( 'es_XX', Locale::convert_to_facebook_language_code( 'es_ES' ) );
		$this->assertEquals( 'es_XX', Locale::convert_to_facebook_language_code( 'es_MX' ) );
		$this->assertEquals( 'es_XX', Locale::convert_to_facebook_language_code( 'es_AR' ) );
		$this->assertEquals( 'es_XX', Locale::convert_to_facebook_language_code( 'es_CO' ) );
		$this->assertEquals( 'es_XX', Locale::convert_to_facebook_language_code( 'es_CL' ) );
	}

	/**
	 * Test _XX language conversion for French variants.
	 *
	 * Facebook uses fr_XX for all French language variants.
	 */
	public function test_convert_french_variants_to_xx_format() {
		$this->assertEquals( 'fr_XX', Locale::convert_to_facebook_language_code( 'fr_FR' ) );
		$this->assertEquals( 'fr_XX', Locale::convert_to_facebook_language_code( 'fr_CA' ) );
		$this->assertEquals( 'fr_XX', Locale::convert_to_facebook_language_code( 'fr_BE' ) );
		$this->assertEquals( 'fr_XX', Locale::convert_to_facebook_language_code( 'fr_CH' ) );
	}

	/**
	 * Test _XX language conversion for Portuguese variants.
	 *
	 * Facebook uses pt_XX for all Portuguese language variants.
	 */
	public function test_convert_portuguese_variants_to_xx_format() {
		$this->assertEquals( 'pt_XX', Locale::convert_to_facebook_language_code( 'pt_BR' ) );
		$this->assertEquals( 'pt_XX', Locale::convert_to_facebook_language_code( 'pt_PT' ) );
	}

	/**
	 * Test _XX language conversion for Dutch variants.
	 *
	 * Facebook uses nl_XX for all Dutch language variants.
	 */
	public function test_convert_dutch_variants_to_xx_format() {
		$this->assertEquals( 'nl_XX', Locale::convert_to_facebook_language_code( 'nl_NL' ) );
		$this->assertEquals( 'nl_XX', Locale::convert_to_facebook_language_code( 'nl_BE' ) );
	}

	/**
	 * Test _XX language conversion for Norwegian variants.
	 *
	 * Note: Only 'no' (without region) converts to no_XX. Regional variants like
	 * nb_NO and nn_NO are mapped to their standard override values.
	 */
	public function test_convert_norwegian_variants_to_xx_format() {
		// Only 'no' without region converts to no_XX
		$this->assertEquals( 'no_XX', Locale::convert_to_facebook_language_code( 'no_NO' ) );

		// Regional variants have their own mappings
		$this->assertEquals( 'nb_NO', Locale::convert_to_facebook_language_code( 'nb_NO' ) );
		$this->assertEquals( 'nn_NO', Locale::convert_to_facebook_language_code( 'nn_NO' ) );
	}

	/**
	 * Test _XX language conversion for Japanese variants.
	 *
	 * Facebook uses ja_XX for all Japanese language variants.
	 */
	public function test_convert_japanese_variants_to_xx_format() {
		$this->assertEquals( 'ja_XX', Locale::convert_to_facebook_language_code( 'ja_JP' ) );
	}

	/**
	 * Test _XX language conversion for Tagalog variants.
	 *
	 * Facebook uses tl_XX for all Tagalog language variants.
	 */
	public function test_convert_tagalog_variants_to_xx_format() {
		$this->assertEquals( 'tl_XX', Locale::convert_to_facebook_language_code( 'tl_PH' ) );
	}

	/**
	 * Test Chinese special case handling.
	 *
	 * Facebook supports both zh_CN (Simplified) and zh_TW (Traditional) Chinese.
	 * The code distinguishes between them based on the region code.
	 */
	public function test_convert_chinese_variants() {
		// Simplified Chinese variants (default)
		$this->assertEquals( 'zh_CN', Locale::convert_to_facebook_language_code( 'zh_CN' ) );
		$this->assertEquals( 'zh_CN', Locale::convert_to_facebook_language_code( 'zh_SG' ) );

		// Traditional Chinese variants
		$this->assertEquals( 'zh_TW', Locale::convert_to_facebook_language_code( 'zh_TW' ) );
		$this->assertEquals( 'zh_TW', Locale::convert_to_facebook_language_code( 'zh_HK' ) );
		$this->assertEquals( 'zh_TW', Locale::convert_to_facebook_language_code( 'zh_MO' ) );
	}

	/**
	 * Test standard locale mappings that don't use _XX format.
	 *
	 * Most languages map to their standard locale code (e.g., de_DE, it_IT).
	 */
	public function test_convert_standard_locales() {
		$this->assertEquals( 'de_DE', Locale::convert_to_facebook_language_code( 'de_DE' ) );
		$this->assertEquals( 'it_IT', Locale::convert_to_facebook_language_code( 'it_IT' ) );
		$this->assertEquals( 'pl_PL', Locale::convert_to_facebook_language_code( 'pl_PL' ) );
		$this->assertEquals( 'ru_RU', Locale::convert_to_facebook_language_code( 'ru_RU' ) );
		$this->assertEquals( 'tr_TR', Locale::convert_to_facebook_language_code( 'tr_TR' ) );
		$this->assertEquals( 'ko_KR', Locale::convert_to_facebook_language_code( 'ko_KR' ) );
		$this->assertEquals( 'ar_AR', Locale::convert_to_facebook_language_code( 'ar_SA' ) );
		$this->assertEquals( 'hi_IN', Locale::convert_to_facebook_language_code( 'hi_IN' ) );
	}

	/**
	 * Test conversion with lowercase language codes.
	 *
	 * The method should handle both uppercase and lowercase properly.
	 */
	public function test_convert_handles_case_variations() {
		// Should work with standard case
		$this->assertEquals( 'en_XX', Locale::convert_to_facebook_language_code( 'en_US' ) );

		// Should work with lowercase language part
		$this->assertEquals( 'en_XX', Locale::convert_to_facebook_language_code( 'en_US' ) );

		// German with various cases
		$this->assertEquals( 'de_DE', Locale::convert_to_facebook_language_code( 'de_DE' ) );
		$this->assertEquals( 'de_DE', Locale::convert_to_facebook_language_code( 'de_de' ) );
	}

	/**
	 * Test fallback behavior for unmapped locales.
	 *
	 * When a locale doesn't have a specific mapping, it should return the original code.
	 */
	public function test_convert_fallback_for_unmapped_locales() {
		// These should return the original code as fallback
		$this->assertEquals( 'xx_XX', Locale::convert_to_facebook_language_code( 'xx_XX' ) );
	}

	// ========================================
	// Tests for convert_to_facebook_override_value()
	// ========================================

	/**
	 * Test convert_to_facebook_override_value for supported languages.
	 *
	 * This method is stricter than convert_to_facebook_language_code and
	 * throws exceptions for unsupported languages.
	 *
	 * Note: The special case Chinese handling runs BEFORE the mapping array check,
	 * so it properly distinguishes between Simplified (zh_CN) and Traditional (zh_TW).
	 */
	public function test_convert_to_facebook_override_value_for_supported_languages() {
		// _XX languages
		$this->assertEquals( 'en_XX', Locale::convert_to_facebook_override_value( 'en_US' ) );
		$this->assertEquals( 'es_XX', Locale::convert_to_facebook_override_value( 'es_ES' ) );
		$this->assertEquals( 'fr_XX', Locale::convert_to_facebook_override_value( 'fr_FR' ) );

		// Standard mappings
		$this->assertEquals( 'de_DE', Locale::convert_to_facebook_override_value( 'de_DE' ) );
		$this->assertEquals( 'it_IT', Locale::convert_to_facebook_override_value( 'it_IT' ) );

		// Chinese - special case handling distinguishes variants
		// Simplified Chinese (default)
		$this->assertEquals( 'zh_CN', Locale::convert_to_facebook_override_value( 'zh_CN' ) );
		$this->assertEquals( 'zh_CN', Locale::convert_to_facebook_override_value( 'zh_SG' ) );

		// Traditional Chinese
		$this->assertEquals( 'zh_TW', Locale::convert_to_facebook_override_value( 'zh_TW' ) );
		$this->assertEquals( 'zh_TW', Locale::convert_to_facebook_override_value( 'zh_HK' ) );
		$this->assertEquals( 'zh_TW', Locale::convert_to_facebook_override_value( 'zh_MO' ) );
	}

	/**
	 * Test convert_to_facebook_override_value throws exception for unsupported languages.
	 */
	public function test_convert_to_facebook_override_value_throws_exception_for_unsupported() {
		$this->expectException( PluginException::class );
		$this->expectExceptionCode( 400 );

		// Use a clearly unsupported language code
		Locale::convert_to_facebook_override_value( 'unsupported_XX' );
	}

	/**
	 * Test convert_to_facebook_override_value exception message.
	 */
	public function test_convert_to_facebook_override_value_exception_message() {
		try {
			Locale::convert_to_facebook_override_value( 'fake_LANG' );
			$this->fail( 'Expected PluginException was not thrown' );
		} catch ( PluginException $e ) {
			$this->assertStringContainsString( 'Language Feed not supported', $e->getMessage() );
			$this->assertStringContainsString( 'fake_LANG', $e->getMessage() );
		}
	}

	// ========================================
	// Tests for is_language_override_supported()
	// ========================================

	/**
	 * Test is_language_override_supported returns true for supported languages.
	 */
	public function test_is_language_override_supported_returns_true_for_supported() {
		// _XX languages
		$this->assertTrue( Locale::is_language_override_supported( 'en_US' ) );
		$this->assertTrue( Locale::is_language_override_supported( 'en_GB' ) );
		$this->assertTrue( Locale::is_language_override_supported( 'es_ES' ) );
		$this->assertTrue( Locale::is_language_override_supported( 'es_MX' ) );
		$this->assertTrue( Locale::is_language_override_supported( 'fr_FR' ) );
		$this->assertTrue( Locale::is_language_override_supported( 'pt_BR' ) );
		$this->assertTrue( Locale::is_language_override_supported( 'nl_NL' ) );
		$this->assertTrue( Locale::is_language_override_supported( 'ja_JP' ) );

		// Standard mappings
		$this->assertTrue( Locale::is_language_override_supported( 'de_DE' ) );
		$this->assertTrue( Locale::is_language_override_supported( 'it_IT' ) );
		$this->assertTrue( Locale::is_language_override_supported( 'pl_PL' ) );
		$this->assertTrue( Locale::is_language_override_supported( 'ru_RU' ) );
		$this->assertTrue( Locale::is_language_override_supported( 'ko_KR' ) );

		// Chinese variants
		$this->assertTrue( Locale::is_language_override_supported( 'zh_CN' ) );
		$this->assertTrue( Locale::is_language_override_supported( 'zh_TW' ) );
		$this->assertTrue( Locale::is_language_override_supported( 'zh_HK' ) );
	}

	/**
	 * Test is_language_override_supported returns false for unsupported languages.
	 */
	public function test_is_language_override_supported_returns_false_for_unsupported() {
		// Fictional language codes
		$this->assertFalse( Locale::is_language_override_supported( 'xx_XX' ) );
		$this->assertFalse( Locale::is_language_override_supported( 'fake_LANG' ) );
		$this->assertFalse( Locale::is_language_override_supported( 'unsupported_ZZ' ) );
	}

	/**
	 * Test is_language_override_supported handles edge cases.
	 */
	public function test_is_language_override_supported_edge_cases() {
		// Empty string
		$this->assertFalse( Locale::is_language_override_supported( '' ) );

		// Language codes without region ARE supported (they convert to _XX format)
		$this->assertTrue( Locale::is_language_override_supported( 'en' ) );
		$this->assertTrue( Locale::is_language_override_supported( 'es' ) );
		$this->assertTrue( Locale::is_language_override_supported( 'fr' ) );

		// Invalid formats
		$this->assertFalse( Locale::is_language_override_supported( 'US' ) );
		$this->assertFalse( Locale::is_language_override_supported( 'en-US' ) );
	}

	// ========================================
	// Tests for get_supported_language_override_codes()
	// ========================================

	/**
	 * Test get_supported_language_override_codes returns an array.
	 */
	public function test_get_supported_language_override_codes_returns_array() {
		$codes = Locale::get_supported_language_override_codes();

		$this->assertIsArray( $codes );
		$this->assertNotEmpty( $codes );
	}

	/**
	 * Test get_supported_language_override_codes includes _XX languages.
	 */
	public function test_get_supported_language_override_codes_includes_xx_languages() {
		$codes = Locale::get_supported_language_override_codes();

		// Should include _XX formats
		$this->assertContains( 'en_XX', $codes );
		$this->assertContains( 'es_XX', $codes );
		$this->assertContains( 'fr_XX', $codes );
		$this->assertContains( 'pt_XX', $codes );
		$this->assertContains( 'nl_XX', $codes );
		$this->assertContains( 'no_XX', $codes );
		$this->assertContains( 'ja_XX', $codes );
		$this->assertContains( 'tl_XX', $codes );
	}

	/**
	 * Test get_supported_language_override_codes includes standard mappings.
	 */
	public function test_get_supported_language_override_codes_includes_standard_mappings() {
		$codes = Locale::get_supported_language_override_codes();

		// Should include standard mappings
		$this->assertContains( 'de_DE', $codes );
		$this->assertContains( 'it_IT', $codes );
		$this->assertContains( 'pl_PL', $codes );
		$this->assertContains( 'ru_RU', $codes );
		$this->assertContains( 'zh_CN', $codes );
		// Note: zh_TW is returned by convert_to_facebook_override_value but not in the base mapping
	}

	/**
	 * Test get_supported_language_override_codes returns unique values.
	 */
	public function test_get_supported_language_override_codes_returns_unique_values() {
		$codes = Locale::get_supported_language_override_codes();

		// Should not have duplicates
		$unique_codes = array_unique( $codes );
		$this->assertEquals( count( $codes ), count( $unique_codes ) );
	}

	/**
	 * Test get_supported_language_override_codes format.
	 */
	public function test_get_supported_language_override_codes_format() {
		$codes = Locale::get_supported_language_override_codes();

		foreach ( $codes as $code ) {
			// Each code should be a string
			$this->assertIsString( $code );

			// Should be in xx_XX format (allows digits for special Facebook codes like q2_KH, q3_CV)
			$this->assertMatchesRegularExpression(
				'/^[a-z0-9]{2}_[A-Z]{2}$/',
				$code,
				"Code '{$code}' does not match expected format"
			);
		}
	}

	// ========================================
	// Tests for get_language_override_mapping()
	// ========================================

	/**
	 * Test get_language_override_mapping returns an array.
	 */
	public function test_get_language_override_mapping_returns_array() {
		$mapping = Locale::get_language_override_mapping();

		$this->assertIsArray( $mapping );
		$this->assertNotEmpty( $mapping );
	}

	/**
	 * Test get_language_override_mapping format.
	 */
	public function test_get_language_override_mapping_format() {
		$mapping = Locale::get_language_override_mapping();

		foreach ( $mapping as $key => $value ) {
			// Key should be lowercase 2-character language code (allows digits for special codes like q2, q3)
			$this->assertIsString( $key );
			$this->assertMatchesRegularExpression( '/^[a-z0-9]{2}$/', $key );

			// Value should be in xx_XX format (allows digits for special codes)
			$this->assertIsString( $value );
			$this->assertMatchesRegularExpression( '/^[a-z0-9]{2}_[A-Z]{2}$/', $value );
		}
	}

	/**
	 * Test get_language_override_mapping includes common languages.
	 */
	public function test_get_language_override_mapping_includes_common_languages() {
		$mapping = Locale::get_language_override_mapping();

		// Should include common languages
		$this->assertArrayHasKey( 'en', $mapping );
		$this->assertArrayHasKey( 'es', $mapping );
		$this->assertArrayHasKey( 'fr', $mapping );
		$this->assertArrayHasKey( 'de', $mapping );
		$this->assertArrayHasKey( 'it', $mapping );
		$this->assertArrayHasKey( 'pt', $mapping );
		$this->assertArrayHasKey( 'nl', $mapping );
		$this->assertArrayHasKey( 'ja', $mapping );
		$this->assertArrayHasKey( 'zh', $mapping );
		$this->assertArrayHasKey( 'ru', $mapping );
		$this->assertArrayHasKey( 'ko', $mapping );
		$this->assertArrayHasKey( 'ar', $mapping );
	}

	/**
	 * Test get_language_override_mapping values for _XX languages.
	 */
	public function test_get_language_override_mapping_xx_languages() {
		$mapping = Locale::get_language_override_mapping();

		// _XX languages should map to _XX
		$this->assertEquals( 'en_XX', $mapping['en'] );
		$this->assertEquals( 'es_XX', $mapping['es'] );
		$this->assertEquals( 'fr_XX', $mapping['fr'] );
		$this->assertEquals( 'pt_XX', $mapping['pt'] );
		$this->assertEquals( 'nl_XX', $mapping['nl'] );
		$this->assertEquals( 'ja_XX', $mapping['ja'] );
		$this->assertEquals( 'tl_XX', $mapping['tl'] );
	}

	// ========================================
	// Edge Cases and Boundary Tests
	// ========================================

	/**
	 * Test handling of edge case inputs.
	 */
	public function test_edge_case_inputs() {
		// Empty string - should return as-is (fallback)
		$this->assertEquals( '', Locale::convert_to_facebook_language_code( '' ) );

		// Single character - should return as-is (fallback)
		$this->assertEquals( 'x', Locale::convert_to_facebook_language_code( 'x' ) );

		// No underscore - language codes without region ARE converted to _XX format
		$this->assertEquals( 'en_XX', Locale::convert_to_facebook_language_code( 'en' ) );
		$this->assertEquals( 'es_XX', Locale::convert_to_facebook_language_code( 'es' ) );
		$this->assertEquals( 'fr_XX', Locale::convert_to_facebook_language_code( 'fr' ) );
	}

	/**
	 * Test consistency between methods.
	 *
	 * For most supported languages, convert_to_facebook_language_code and
	 * convert_to_facebook_override_value should return the same result.
	 * Exception: Chinese TW/HK/MO have special handling in convert_to_facebook_override_value.
	 */
	public function test_consistency_between_conversion_methods() {
		$test_locales = [
			'en_US',
			'en_GB',
			'es_ES',
			'fr_FR',
			'de_DE',
			'it_IT',
			'pt_BR',
			'zh_CN',
			'ja_JP',
		];

		foreach ( $test_locales as $locale ) {
			$result1 = Locale::convert_to_facebook_language_code( $locale );
			$result2 = Locale::convert_to_facebook_override_value( $locale );

			$this->assertEquals(
				$result1,
				$result2,
				"Conversion methods returned different results for {$locale}"
			);
		}
	}

	/**
	 * Test that is_language_override_supported is consistent with conversion.
	 *
	 * If is_language_override_supported returns true, conversion should not throw exception.
	 */
	public function test_is_supported_consistent_with_conversion() {
		$test_locales = [
			'en_US',
			'es_ES',
			'fr_FR',
			'de_DE',
			'zh_CN',
		];

		foreach ( $test_locales as $locale ) {
			if ( Locale::is_language_override_supported( $locale ) ) {
				// Should not throw exception
				try {
					$result = Locale::convert_to_facebook_override_value( $locale );
					$this->assertNotEmpty( $result );
				} catch ( PluginException $e ) {
					$this->fail( "Supported locale {$locale} threw exception: " . $e->getMessage() );
				}
			}
		}
	}

	/**
	 * Test real-world Polylang language codes.
	 *
	 * These are actual locale codes that Polylang uses.
	 */
	public function test_polylang_language_codes() {
		$this->assertEquals( 'en_XX', Locale::convert_to_facebook_language_code( 'en_US' ) );
		$this->assertEquals( 'es_XX', Locale::convert_to_facebook_language_code( 'es_ES' ) );
		$this->assertEquals( 'fr_XX', Locale::convert_to_facebook_language_code( 'fr_FR' ) );
		$this->assertEquals( 'de_DE', Locale::convert_to_facebook_language_code( 'de_DE' ) );
	}

	/**
	 * Test real-world WPML language codes.
	 *
	 * These are actual locale codes that WPML uses.
	 */
	public function test_wpml_language_codes() {
		$this->assertEquals( 'en_XX', Locale::convert_to_facebook_language_code( 'en' ) );
		$this->assertEquals( 'es_XX', Locale::convert_to_facebook_language_code( 'es' ) );
		$this->assertEquals( 'fr_XX', Locale::convert_to_facebook_language_code( 'fr' ) );
		$this->assertEquals( 'de_DE', Locale::convert_to_facebook_language_code( 'de' ) );
	}
}
