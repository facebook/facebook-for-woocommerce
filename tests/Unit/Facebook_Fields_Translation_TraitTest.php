<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit;

use WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Test class that uses the Facebook_Fields_Translation_Trait.
 *
 * This class implements the required abstract methods from the trait
 * to enable testing of the trait's functionality.
 */
class TestFacebookFieldsTranslationClass {
	use Facebook_Fields_Translation_Trait;

	/**
	 * Track language switching calls for testing.
	 *
	 * @var array
	 */
	public $language_switches = [];

	/**
	 * Track language restoration calls for testing.
	 *
	 * @var array
	 */
	public $language_restorations = [];

	/**
	 * Simulate switch_to_language behavior.
	 *
	 * @param string $locale Full locale code (e.g., 'es_ES', 'zh_CN')
	 * @return string|null The previous language code if successful, null otherwise
	 */
	public function switch_to_language( string $locale ): ?string {
		$this->language_switches[] = $locale;
		return 'en_US'; // Return a mock previous language
	}

	/**
	 * Simulate restore_language behavior.
	 *
	 * @param string $language_code The language code to restore
	 * @return void
	 */
	public function restore_language( string $language_code ): void {
		$this->language_restorations[] = $language_code;
	}

	/**
	 * Make get_translated_fields public for testing.
	 *
	 * @param int    $original_id Original product ID
	 * @param int    $translated_id Translated product ID
	 * @param string $target_language Target language code for permalink translation (optional)
	 * @return array Array of field names that have different values
	 */
	public function public_get_translated_fields( int $original_id, int $translated_id, ?string $target_language = null ): array {
		return $this->get_translated_fields( $original_id, $translated_id, $target_language );
	}

	/**
	 * Make get_facebook_field_mapping public for testing.
	 *
	 * @return array Array mapping field names to WC_Facebook_Product method names
	 */
	public function public_get_facebook_field_mapping(): array {
		return $this->get_facebook_field_mapping();
	}
}

/**
 * Unit tests for Facebook_Fields_Translation_Trait.
 *
 * @since 3.6.0
 */
class Facebook_Fields_Translation_TraitTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test instance using the trait.
	 *
	 * @var TestFacebookFieldsTranslationClass
	 */
	private $instance;

	/**
	 * Test products.
	 *
	 * @var array
	 */
	private $products = [];

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->instance = new TestFacebookFieldsTranslationClass();
	}

	/**
	 * Clean up test data.
	 */
	public function tearDown(): void {
		// Clean up any created products
		foreach ( $this->products as $product ) {
			if ( $product && $product->get_id() ) {
				wp_delete_post( $product->get_id(), true );
			}
		}
		$this->products = [];

		parent::tearDown();
	}

	/**
	 * Test that the trait can be used in a class.
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait
	 */
	public function test_trait_can_be_used() {
		$this->assertInstanceOf( TestFacebookFieldsTranslationClass::class, $this->instance );
		$this->assertTrue( method_exists( $this->instance, 'get_translated_fields' ) );
		$this->assertTrue( method_exists( $this->instance, 'get_facebook_field_mapping' ) );
	}

	/**
	 * Test that get_facebook_field_mapping returns an array.
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_facebook_field_mapping
	 */
	public function test_get_facebook_field_mapping_returns_array() {
		$mapping = $this->instance->public_get_facebook_field_mapping();

		$this->assertIsArray( $mapping );
		$this->assertNotEmpty( $mapping );
	}

	/**
	 * Test that get_facebook_field_mapping contains expected fields.
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_facebook_field_mapping
	 */
	public function test_get_facebook_field_mapping_contains_expected_fields() {
		$mapping = $this->instance->public_get_facebook_field_mapping();

		// Verify expected field names exist
		$this->assertArrayHasKey( 'name', $mapping );
		$this->assertArrayHasKey( 'description', $mapping );
		$this->assertArrayHasKey( 'short_description', $mapping );
		$this->assertArrayHasKey( 'rich_text_description', $mapping );
		$this->assertArrayHasKey( 'image_id', $mapping );
		$this->assertArrayHasKey( 'gallery_image_ids', $mapping );
		$this->assertArrayHasKey( 'video', $mapping );
		$this->assertArrayHasKey( 'link', $mapping );
	}

	/**
	 * Test that get_facebook_field_mapping has correct structure.
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_facebook_field_mapping
	 */
	public function test_get_facebook_field_mapping_has_correct_structure() {
		$mapping = $this->instance->public_get_facebook_field_mapping();

		// Verify each mapping entry has string key and string value
		foreach ( $mapping as $field_name => $method_name ) {
			$this->assertIsString( $field_name );
			$this->assertIsString( $method_name );
			$this->assertNotEmpty( $field_name );
			$this->assertNotEmpty( $method_name );
		}
	}

	/**
	 * Test that get_facebook_field_mapping values are method names.
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_facebook_field_mapping
	 */
	public function test_get_facebook_field_mapping_values_are_method_names() {
		$mapping = $this->instance->public_get_facebook_field_mapping();

		// Verify specific method mappings from the source code
		$this->assertEquals( 'get_name', $mapping['name'] );
		$this->assertEquals( 'get_fb_description', $mapping['description'] );
		$this->assertEquals( 'get_fb_short_description', $mapping['short_description'] );
		$this->assertEquals( 'get_rich_text_description', $mapping['rich_text_description'] );
		$this->assertEquals( 'get_all_image_urls', $mapping['image_id'] );
		$this->assertEquals( 'get_all_image_urls', $mapping['gallery_image_ids'] );
		$this->assertEquals( 'get_all_video_urls', $mapping['video'] );
		$this->assertEquals( 'get_permalink', $mapping['link'] );
	}

	/**
	 * Test get_translated_fields with invalid product IDs returns empty array.
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_with_invalid_product_ids() {
		// Note: This test verifies the trait handles invalid IDs gracefully
		// The actual implementation requires WC_Facebook_Product which may not be available in tests
		$result = $this->instance->public_get_translated_fields( 999999, 999998 );

		$this->assertIsArray( $result );
		// Should return empty array for invalid product IDs
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_translated_fields with zero product IDs returns empty array.
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_with_zero_product_ids() {
		// Use zero IDs which should trigger null return from wc_get_product
		$result = $this->instance->public_get_translated_fields( 0, 0 );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test method visibility.
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_facebook_field_mapping
	 */
	public function test_method_visibility() {
		$reflection = new \ReflectionClass( TestFacebookFieldsTranslationClass::class );

		// get_translated_fields should be protected in the trait
		$this->assertTrue( $reflection->hasMethod( 'get_translated_fields' ) );
		$method = $reflection->getMethod( 'get_translated_fields' );
		$this->assertTrue( $method->isProtected() );

		// get_facebook_field_mapping should be protected in the trait
		$this->assertTrue( $reflection->hasMethod( 'get_facebook_field_mapping' ) );
		$method = $reflection->getMethod( 'get_facebook_field_mapping' );
		$this->assertTrue( $method->isProtected() );
	}

	/**
	 * Test that trait methods exist and have correct signatures.
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_method_signature() {
		$reflection = new \ReflectionClass( TestFacebookFieldsTranslationClass::class );
		
		$this->assertTrue( $reflection->hasMethod( 'get_translated_fields' ) );
		
		$method = $reflection->getMethod( 'get_translated_fields' );
		$params = $method->getParameters();
		
		// Verify method has correct number of parameters
		$this->assertGreaterThanOrEqual( 2, count( $params ) );
		
		// Verify first two parameters are required integers
		$this->assertEquals( 'original_id', $params[0]->getName() );
		$this->assertEquals( 'translated_id', $params[1]->getName() );
	}

	/**
	 * Test that the trait integrates with classes that implement required methods.
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait
	 */
	public function test_trait_integration_with_implementing_class() {
		// Verify the test class properly implements required methods
		$this->assertTrue( method_exists( $this->instance, 'switch_to_language' ) );
		$this->assertTrue( method_exists( $this->instance, 'restore_language' ) );
		
		// Verify language switching works
		$result = $this->instance->switch_to_language( 'es_ES' );
		$this->assertIsString( $result );
		$this->assertEquals( 'en_US', $result );
		
		// Verify language restoration works
		$this->instance->restore_language( 'en_US' );
		$this->assertContains( 'en_US', $this->instance->language_restorations );
	}
}
