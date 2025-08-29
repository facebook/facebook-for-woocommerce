<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Integration\Feed\Localization;

use WooCommerce\Facebook\Feed\Localization\TranslationDataExtractor;
use WooCommerce\Facebook\Feed\Localization\LanguageFeedData;
use WooCommerce\Facebook\Feed\FeedManager;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Integration tests for localization feed functionality
 *
 * @since 3.6.0
 */
class LocalizationIntegrationTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that localization feed is included in active feed types
	 */
	public function test_localization_feed_in_active_types() {
		$active_types = FeedManager::get_active_feed_types();

		$this->assertContains( FeedManager::LANGUAGE_OVERRIDE, $active_types );
	}

	/**
	 * Test that FeedManager can create a localization feed instance
	 */
	public function test_feed_manager_creates_localization_feed() {
		$feed_manager = new FeedManager();

		$localization_feed = $feed_manager->get_feed_instance( FeedManager::LANGUAGE_OVERRIDE );

		$this->assertInstanceOf(
			\WooCommerce\Facebook\Feed\Localization\LanguageOverrideFeed::class,
			$localization_feed
		);
	}

	/**
	 * Test TranslationDataExtractor basic functionality
	 */
	public function test_translation_data_extractor_basic_functionality() {
		$extractor = new TranslationDataExtractor();

		// Test getting translatable fields
		$fields = $extractor->get_translatable_fields();
		$this->assertIsArray( $fields );
		$this->assertNotEmpty( $fields );
		$this->assertContains( 'title', $fields );
		$this->assertContains( 'description', $fields );

		// Test getting available languages (should be empty without plugins)
		$languages = $extractor->get_available_languages();
		$this->assertIsArray( $languages );

		// Test checking for active plugins (should be false without plugins)
		$has_plugins = $extractor->has_active_localization_plugin();
		$this->assertIsBool( $has_plugins );

		// Test getting statistics
		$stats = $extractor->get_translation_statistics();
		$this->assertIsArray( $stats );
		$this->assertArrayHasKey( 'has_active_plugin', $stats );
		$this->assertArrayHasKey( 'available_languages', $stats );
	}

	/**
	 * Test LanguageFeedData basic functionality
	 */
	public function test_language_feed_data_basic_functionality() {
		$feed_data = new LanguageFeedData();

		// Test getting CSV headers
		$headers = $feed_data->get_csv_headers();
		$this->assertIsArray( $headers );
		$this->assertContains( 'id', $headers );
		$this->assertContains( 'language', $headers );
		$this->assertContains( 'title', $headers );

		// Test sample data
		$sample_data = $feed_data->get_sample_csv_data();
		$this->assertIsArray( $sample_data );
		$this->assertNotEmpty( $sample_data );

		// Test validation with sample data
		$validation = $feed_data->validate_translation_data( [ 'es_ES' => $sample_data ] );
		$this->assertIsArray( $validation );
		$this->assertArrayHasKey( 'valid', $validation );
		$this->assertArrayHasKey( 'errors', $validation );
	}

	/**
	 * Test CSV formatting with sample data
	 */
	public function test_csv_formatting_with_sample_data() {
		$feed_data = new LanguageFeedData();
		$sample_data = $feed_data->get_sample_csv_data();

		// Test formatting for CSV
		$csv_rows = $feed_data->format_language_for_csv( 'es_ES', $sample_data );
		$this->assertIsArray( $csv_rows );
		$this->assertNotEmpty( $csv_rows );

		// Test converting to CSV string
		$csv_string = $feed_data->convert_to_csv_string( $csv_rows );
		$this->assertIsString( $csv_string );
		$this->assertStringContainsString( 'id,language,title', $csv_string );
		$this->assertStringContainsString( 'es_ES', $csv_string );
	}

	/**
	 * Test product translation extraction with real product
	 */
	public function test_product_translation_extraction() {
		$extractor = new TranslationDataExtractor();

		// Create a test product
		$product = new \WC_Product_Simple();
		$product->set_name( 'Test Product' );
		$product->set_description( 'Test product description' );
		$product->set_short_description( 'Short description' );
		$product->set_price( 10.00 );
		$product->set_status( 'publish' );
		$product_id = $product->save();

		// Test extracting translations (should be empty without localization plugins)
		$translations = $extractor->extract_product_translations( $product_id );
		$this->assertIsArray( $translations );

		// Test checking if product has translations
		$has_translations = $extractor->product_has_translations( $product_id );
		$this->assertIsBool( $has_translations );

		// Clean up
		wp_delete_post( $product_id, true );
	}

	/**
	 * Test localization feed should skip when no plugins active
	 */
	public function test_localization_feed_skips_without_plugins() {
		$feed_manager = new FeedManager();
		$localization_feed = $feed_manager->get_feed_instance( FeedManager::LANGUAGE_OVERRIDE );

		// Should skip feed when no localization plugins are active
		$should_skip = $localization_feed->should_skip_feed();
		$this->assertTrue( $should_skip );
	}

	/**
	 * Test language feed data validation
	 */
	public function test_language_feed_data_validation() {
		$feed_data = new LanguageFeedData();

		// Test valid data
		$valid_data = [
			'es_ES' => [
				[
					'id' => '123',
					'language' => 'es_ES',
					'title' => 'Producto de Prueba',
					'description' => 'Descripción del producto',
					'short_description' => 'Descripción corta',
				]
			]
		];

		$validation = $feed_data->validate_translation_data( $valid_data );
		$this->assertTrue( $validation['valid'] );
		$this->assertEmpty( $validation['errors'] );
		$this->assertEquals( 1, $validation['valid_count'] );
		$this->assertEquals( 1, $validation['total_count'] );

		// Test invalid data
		$invalid_data = [
			'invalid_lang' => [
				[
					'id' => '', // Missing ID
					'language' => 'invalid_lang',
					'title' => '', // Missing content
				]
			]
		];

		$validation = $feed_data->validate_translation_data( $invalid_data );
		$this->assertFalse( $validation['valid'] );
		$this->assertNotEmpty( $validation['errors'] );
	}

	/**
	 * Test translation statistics
	 */
	public function test_translation_statistics() {
		$feed_data = new LanguageFeedData();
		$sample_data = [
			'es_ES' => [
				[ 'id' => '1', 'language' => 'es_ES', 'title' => 'Producto 1' ],
				[ 'id' => '2', 'language' => 'es_ES', 'title' => 'Producto 2' ],
			],
			'fr_FR' => [
				[ 'id' => '1', 'language' => 'fr_FR', 'title' => 'Produit 1' ],
			],
		];

		$stats = $feed_data->get_translation_statistics( $sample_data );

		$this->assertEquals( 2, $stats['total_languages'] );
		$this->assertEquals( 3, $stats['total_products'] );
		$this->assertContains( 'es_ES', $stats['languages'] );
		$this->assertContains( 'fr_FR', $stats['languages'] );
		$this->assertEquals( 2, $stats['products_per_language']['es_ES'] );
		$this->assertEquals( 1, $stats['products_per_language']['fr_FR'] );
	}

	/**
	 * Test CSV sanitization
	 */
	public function test_csv_sanitization() {
		$feed_data = new LanguageFeedData();

		// Test data with HTML and special characters
		$test_data = [
			[
				'id' => '123',
				'language' => 'es_ES',
				'title' => '<strong>Producto con HTML</strong>',
				'description' => 'Descripción con "comillas" y caracteres especiales & símbolos',
				'short_description' => "Texto con\nmúltiples\nlíneas",
			]
		];

		$csv_rows = $feed_data->format_language_for_csv( 'es_ES', $test_data );
		$this->assertNotEmpty( $csv_rows );

		$first_row = $csv_rows[0];

		// HTML should be stripped
		$this->assertEquals( 'Producto con HTML', $first_row['title'] );

		// Quotes should be escaped for CSV
		$this->assertStringContainsString( '""comillas""', $first_row['description'] );

		// Multiple lines should be normalized to single spaces
		$this->assertEquals( 'Texto con múltiples líneas', $first_row['short_description'] );
	}
}
