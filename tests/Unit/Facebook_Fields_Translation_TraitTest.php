<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Integrations;

use WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;

/**
 * Test class that uses the Facebook_Fields_Translation_Trait.
 */
class TestFacebookFieldsTranslationClass {
	use Facebook_Fields_Translation_Trait;

	/**
	 * Track language switches for testing.
	 *
	 * @var array
	 */
	public $language_switches = [];

	/**
	 * Current language for testing.
	 *
	 * @var string|null
	 */
	private $current_language = 'en';

	/**
	 * Switch to a target language.
	 *
	 * @param string $target_language Target language code
	 * @return string|null Original language or null if switch failed
	 */
	protected function switch_to_language( string $target_language ): ?string {
		$original = $this->current_language;
		$this->current_language = $target_language;
		$this->language_switches[] = [
			'from' => $original,
			'to'   => $target_language,
		];
		return $original;
	}

	/**
	 * Restore the original language.
	 *
	 * @param string $original_language Original language code
	 * @return void
	 */
	protected function restore_language( string $original_language ): void {
		$this->current_language = $original_language;
		$this->language_switches[] = [
			'restored' => $original_language,
		];
	}

	/**
	 * Get current language for testing.
	 *
	 * @return string|null
	 */
	public function get_current_language(): ?string {
		return $this->current_language;
	}

	/**
	 * Reset language switches tracking.
	 *
	 * @return void
	 */
	public function reset_language_switches(): void {
		$this->language_switches = [];
	}
}

/**
 * Unit tests for Facebook_Fields_Translation_Trait.
 *
 * IMPORTANT: These tests are based on verified source code analysis:
 * 
 * Source File: fbcode/signals/opensource/facebook-for-woocommerce/includes/Integrations/Facebook_Fields_Translation_Trait.php
 * 
 * Verified Methods in Trait:
 * - get_translated_fields() - Line 29 (protected method)
 * - get_facebook_field_mapping() - Line 100 (protected method)
 * 
 * Verified Field Mapping (lines 101-118):
 * - 'name' => 'get_name'
 * - 'description' => 'get_fb_description'
 * - 'short_description' => 'get_fb_short_description'
 * - 'rich_text_description' => 'get_rich_text_description'
 * - 'image_id' => 'get_all_image_urls'
 * - 'gallery_image_ids' => 'get_all_image_urls'
 * - 'video' => 'get_all_video_urls'
 * - 'link' => 'get_permalink'
 * 
 * How the Trait Works:
 * 1. The trait creates WC_Facebook_Product instances (lines 44-45 of source)
 * 2. WC_Facebook_Product wraps WooCommerce products and adds Facebook-specific methods
 * 3. WC_Facebook_Product has a __call() magic method (fbproduct.php) that delegates
 *    method calls to the underlying WooCommerce product object
 * 4. Methods like get_name(), get_permalink() work via this delegation
 * 5. Methods like get_fb_description(), get_rich_text_description() are defined
 *    in WC_Facebook_Product class (fbproduct.php)
 * 
 * Test Environment:
 * - The bootstrap.php loads the Facebook for WooCommerce plugin
 * - This makes WC_Facebook_Product class available in tests
 * - The trait's class_exists() check (line 40) prevents re-loading
 *
 * @since 3.6.0
 */
class Facebook_Fields_Translation_TraitTest extends AbstractWPUnitTestWithSafeFiltering {

	/**
	 * Test instance.
	 *
	 * @var TestFacebookFieldsTranslationClass
	 */
	private $instance;

	/**
	 * Original product for testing.
	 *
	 * @var \WC_Product
	 */
	private $original_product;

	/**
	 * Translated product for testing.
	 *
	 * @var \WC_Product
	 */
	private $translated_product;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->instance = new TestFacebookFieldsTranslationClass();
		$this->original_product = null;
		$this->translated_product = null;
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		if ( $this->original_product && $this->original_product->get_id() ) {
			wp_delete_post( $this->original_product->get_id(), true );
		}
		if ( $this->translated_product && $this->translated_product->get_id() ) {
			wp_delete_post( $this->translated_product->get_id(), true );
		}
		parent::tearDown();
	}

	/**
	 * Test that the trait can be used in a class.
	 *
	 * Verifies that the trait methods exist as documented in the source code:
	 * - get_translated_fields() is defined at line 29 of Facebook_Fields_Translation_Trait.php
	 * - get_facebook_field_mapping() is defined at line 100 of Facebook_Fields_Translation_Trait.php
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait
	 */
	public function test_trait_can_be_used() {
		$this->assertInstanceOf( TestFacebookFieldsTranslationClass::class, $this->instance );
		// Verify methods exist (defined in source at lines 29 and 100)
		$this->assertTrue( method_exists( $this->instance, 'get_translated_fields' ) );
		$this->assertTrue( method_exists( $this->instance, 'get_facebook_field_mapping' ) );
	}

	/**
	 * Test get_facebook_field_mapping returns correct mapping.
	 *
	 * This test verifies the EXACT mapping defined in the source code at lines 101-118
	 * of Facebook_Fields_Translation_Trait.php:
	 *
	 * return [
	 *     'name' => 'get_name',                              // Line 103
	 *     'description' => 'get_fb_description',             // Line 104
	 *     'short_description' => 'get_fb_short_description', // Line 105
	 *     'rich_text_description' => 'get_rich_text_description', // Line 106
	 *     'image_id' => 'get_all_image_urls',                // Line 109
	 *     'gallery_image_ids' => 'get_all_image_urls',       // Line 110
	 *     'video' => 'get_all_video_urls',                   // Line 113
	 *     'link' => 'get_permalink',                         // Line 116
	 * ];
	 *
	 * Note: These method names are called on WC_Facebook_Product instances (not WC_Product).
	 * WC_Facebook_Product has these methods defined or delegates via __call() magic method.
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_facebook_field_mapping
	 */
	public function test_get_facebook_field_mapping_returns_correct_mapping() {
		$reflection = new \ReflectionClass( $this->instance );
		$method = $reflection->getMethod( 'get_facebook_field_mapping' );
		$method->setAccessible( true );

		$mapping = $method->invoke( $this->instance );

		$this->assertIsArray( $mapping );
		$this->assertArrayHasKey( 'name', $mapping );
		$this->assertArrayHasKey( 'description', $mapping );
		$this->assertArrayHasKey( 'short_description', $mapping );
		$this->assertArrayHasKey( 'rich_text_description', $mapping );
		$this->assertArrayHasKey( 'image_id', $mapping );
		$this->assertArrayHasKey( 'gallery_image_ids', $mapping );
		$this->assertArrayHasKey( 'video', $mapping );
		$this->assertArrayHasKey( 'link', $mapping );

		// Verify exact mapping values from source code (lines 103-116)
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
	 * Test get_translated_fields with identical products returns empty array.
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_with_identical_products_returns_empty_array() {
		$this->original_product = \WC_Helper_Product::create_simple_product();
		$this->original_product->set_name( 'Test Product' );
		$this->original_product->set_description( 'Test description' );
		$this->original_product->set_short_description( 'Short desc' );
		$this->original_product->save();

		$this->translated_product = \WC_Helper_Product::create_simple_product();
		$this->translated_product->set_name( 'Test Product' );
		$this->translated_product->set_description( 'Test description' );
		$this->translated_product->set_short_description( 'Short desc' );
		$this->translated_product->save();

		$reflection = new \ReflectionClass( $this->instance );
		$method = $reflection->getMethod( 'get_translated_fields' );
		$method->setAccessible( true );

		$result = $method->invoke(
			$this->instance,
			$this->original_product->get_id(),
			$this->translated_product->get_id()
		);

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_translated_fields with invalid product IDs returns empty array.
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_with_invalid_product_ids_returns_empty_array() {
		$reflection = new \ReflectionClass( $this->instance );
		$method = $reflection->getMethod( 'get_translated_fields' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->instance, 999999, 999998 );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_translated_fields with different product names.
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_with_different_product_names() {
		$this->original_product = \WC_Helper_Product::create_simple_product();
		$this->original_product->set_name( 'English Product' );
		$this->original_product->save();

		$this->translated_product = \WC_Helper_Product::create_simple_product();
		$this->translated_product->set_name( 'Producto en Español' );
		$this->translated_product->save();

		$reflection = new \ReflectionClass( $this->instance );
		$method = $reflection->getMethod( 'get_translated_fields' );
		$method->setAccessible( true );

		$result = $method->invoke(
			$this->instance,
			$this->original_product->get_id(),
			$this->translated_product->get_id()
		);

		$this->assertIsArray( $result );
		$this->assertContains( 'name', $result );
	}

	/**
	 * Test get_translated_fields with different descriptions.
	 *
	 * The trait calls get_fb_description() on WC_Facebook_Product instances (source line 54).
	 * WC_Facebook_Product.get_fb_description() is defined in fbproduct.php and reads from
	 * the 'fb_product_description' post meta field.
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_with_different_descriptions() {
		$this->original_product = \WC_Helper_Product::create_simple_product();
		$this->original_product->set_name( 'Product' );
		$this->original_product->set_description( 'English description' );
		$this->original_product->save();

		// Set Facebook-specific description (used by WC_Facebook_Product.get_fb_description())
		update_post_meta(
			$this->original_product->get_id(),
			'fb_product_description',
			'English FB description'
		);

		$this->translated_product = \WC_Helper_Product::create_simple_product();
		$this->translated_product->set_name( 'Product' );
		$this->translated_product->set_description( 'Spanish description' );
		$this->translated_product->save();

		update_post_meta(
			$this->translated_product->get_id(),
			'fb_product_description',
			'Spanish FB description'
		);

		$reflection = new \ReflectionClass( $this->instance );
		$method = $reflection->getMethod( 'get_translated_fields' );
		$method->setAccessible( true );

		$result = $method->invoke(
			$this->instance,
			$this->original_product->get_id(),
			$this->translated_product->get_id()
		);

		$this->assertIsArray( $result );
		$this->assertContains( 'description', $result );
	}

	/**
	 * Test get_translated_fields with different short descriptions.
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_with_different_short_descriptions() {
		$this->original_product = \WC_Helper_Product::create_simple_product();
		$this->original_product->set_name( 'Product' );
		$this->original_product->set_short_description( 'English short' );
		$this->original_product->save();

		$this->translated_product = \WC_Helper_Product::create_simple_product();
		$this->translated_product->set_name( 'Product' );
		$this->translated_product->set_short_description( 'Spanish short' );
		$this->translated_product->save();

		$reflection = new \ReflectionClass( $this->instance );
		$method = $reflection->getMethod( 'get_translated_fields' );
		$method->setAccessible( true );

		$result = $method->invoke(
			$this->instance,
			$this->original_product->get_id(),
			$this->translated_product->get_id()
		);

		$this->assertIsArray( $result );
		$this->assertContains( 'short_description', $result );
	}

	/**
	 * Test get_translated_fields with different rich text descriptions.
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_with_different_rich_text_descriptions() {
		$this->original_product = \WC_Helper_Product::create_simple_product();
		$this->original_product->set_name( 'Product' );
		$this->original_product->save();

		update_post_meta(
			$this->original_product->get_id(),
			'fb_rich_text_description',
			'English rich text'
		);

		$this->translated_product = \WC_Helper_Product::create_simple_product();
		$this->translated_product->set_name( 'Product' );
		$this->translated_product->save();

		update_post_meta(
			$this->translated_product->get_id(),
			'fb_rich_text_description',
			'Spanish rich text'
		);

		$reflection = new \ReflectionClass( $this->instance );
		$method = $reflection->getMethod( 'get_translated_fields' );
		$method->setAccessible( true );

		$result = $method->invoke(
			$this->instance,
			$this->original_product->get_id(),
			$this->translated_product->get_id()
		);

		$this->assertIsArray( $result );
		$this->assertContains( 'rich_text_description', $result );
	}

	/**
	 * Test get_translated_fields with multiple different fields.
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_with_multiple_different_fields() {
		$this->original_product = \WC_Helper_Product::create_simple_product();
		$this->original_product->set_name( 'English Product' );
		$this->original_product->set_description( 'English description' );
		$this->original_product->set_short_description( 'English short' );
		$this->original_product->save();

		$this->translated_product = \WC_Helper_Product::create_simple_product();
		$this->translated_product->set_name( 'Spanish Product' );
		$this->translated_product->set_description( 'Spanish description' );
		$this->translated_product->set_short_description( 'Spanish short' );
		$this->translated_product->save();

		$reflection = new \ReflectionClass( $this->instance );
		$method = $reflection->getMethod( 'get_translated_fields' );
		$method->setAccessible( true );

		$result = $method->invoke(
			$this->instance,
			$this->original_product->get_id(),
			$this->translated_product->get_id()
		);

		$this->assertIsArray( $result );
		$this->assertGreaterThan( 1, count( $result ) );
		$this->assertContains( 'name', $result );
	}

	/**
	 * Test get_translated_fields with target language parameter.
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_with_target_language_parameter() {
		$this->original_product = \WC_Helper_Product::create_simple_product();
		$this->original_product->set_name( 'English Product' );
		$this->original_product->save();

		$this->translated_product = \WC_Helper_Product::create_simple_product();
		$this->translated_product->set_name( 'Spanish Product' );
		$this->translated_product->save();

		$this->instance->reset_language_switches();

		$reflection = new \ReflectionClass( $this->instance );
		$method = $reflection->getMethod( 'get_translated_fields' );
		$method->setAccessible( true );

		$result = $method->invoke(
			$this->instance,
			$this->original_product->get_id(),
			$this->translated_product->get_id(),
			'es'
		);

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $this->instance->language_switches );
	}

	/**
	 * Test language switching behavior.
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_language_switching_behavior() {
		$this->original_product = \WC_Helper_Product::create_simple_product();
		$this->original_product->set_name( 'Product' );
		$this->original_product->save();

		$this->translated_product = \WC_Helper_Product::create_simple_product();
		$this->translated_product->set_name( 'Product' );
		$this->translated_product->save();

		$this->instance->reset_language_switches();
		$original_language = $this->instance->get_current_language();

		$reflection = new \ReflectionClass( $this->instance );
		$method = $reflection->getMethod( 'get_translated_fields' );
		$method->setAccessible( true );

		$method->invoke(
			$this->instance,
			$this->original_product->get_id(),
			$this->translated_product->get_id(),
			'fr'
		);

		// Language should be switched and restored
		$this->assertNotEmpty( $this->instance->language_switches );
		// Language should be restored to original
		$this->assertEquals( $original_language, $this->instance->get_current_language() );
	}

	/**
	 * Test that empty translated values are ignored.
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_empty_translated_values_are_ignored() {
		$this->original_product = \WC_Helper_Product::create_simple_product();
		$this->original_product->set_name( 'English Product' );
		$this->original_product->set_description( 'English description' );
		$this->original_product->save();

		$this->translated_product = \WC_Helper_Product::create_simple_product();
		$this->translated_product->set_name( 'English Product' );
		$this->translated_product->set_description( '' ); // Empty description
		$this->translated_product->save();

		$reflection = new \ReflectionClass( $this->instance );
		$method = $reflection->getMethod( 'get_translated_fields' );
		$method->setAccessible( true );

		$result = $method->invoke(
			$this->instance,
			$this->original_product->get_id(),
			$this->translated_product->get_id()
		);

		$this->assertIsArray( $result );
		// Empty translated description should not be counted as translated
		$this->assertNotContains( 'description', $result );
	}

	/**
	 * Test that whitespace-only differences are ignored.
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_whitespace_only_differences_are_ignored() {
		$this->original_product = \WC_Helper_Product::create_simple_product();
		$this->original_product->set_name( 'Product Name' );
		$this->original_product->save();

		$this->translated_product = \WC_Helper_Product::create_simple_product();
		$this->translated_product->set_name( '  Product Name  ' ); // Same with whitespace
		$this->translated_product->save();

		$reflection = new \ReflectionClass( $this->instance );
		$method = $reflection->getMethod( 'get_translated_fields' );
		$method->setAccessible( true );

		$result = $method->invoke(
			$this->instance,
			$this->original_product->get_id(),
			$this->translated_product->get_id()
		);

		$this->assertIsArray( $result );
		// Whitespace differences should be ignored
		$this->assertNotContains( 'name', $result );
	}

	/**
	 * Test that translation from empty to non-empty is detected.
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_translation_from_empty_to_non_empty_is_detected() {
		$this->original_product = \WC_Helper_Product::create_simple_product();
		$this->original_product->set_name( 'Product' );
		$this->original_product->set_description( '' ); // Empty original
		$this->original_product->save();

		$this->translated_product = \WC_Helper_Product::create_simple_product();
		$this->translated_product->set_name( 'Product' );
		$this->translated_product->set_description( 'Translated description' ); // Non-empty translated
		$this->translated_product->save();

		$reflection = new \ReflectionClass( $this->instance );
		$method = $reflection->getMethod( 'get_translated_fields' );
		$method->setAccessible( true );

		$result = $method->invoke(
			$this->instance,
			$this->original_product->get_id(),
			$this->translated_product->get_id()
		);

		$this->assertIsArray( $result );
		// Translation from empty to non-empty should be detected
		$this->assertContains( 'description', $result );
	}

	/**
	 * Test method visibility.
	 *
	 * Verifies that both trait methods are protected as defined in source:
	 * - Line 29: protected function get_translated_fields(...)
	 * - Line 100: protected function get_facebook_field_mapping()
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait
	 */
	public function test_method_visibility() {
		$reflection = new \ReflectionClass( TestFacebookFieldsTranslationClass::class );

		$this->assertTrue( $reflection->hasMethod( 'get_translated_fields' ) );
		$this->assertTrue( $reflection->hasMethod( 'get_facebook_field_mapping' ) );

		$get_translated_fields = $reflection->getMethod( 'get_translated_fields' );
		$get_facebook_field_mapping = $reflection->getMethod( 'get_facebook_field_mapping' );

		// Both methods are protected in the source trait
		$this->assertTrue( $get_translated_fields->isProtected() );
		$this->assertTrue( $get_facebook_field_mapping->isProtected() );
	}

	/**
	 * Test that null target language is handled correctly.
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_null_target_language_is_handled_correctly() {
		$this->original_product = \WC_Helper_Product::create_simple_product();
		$this->original_product->set_name( 'English Product' );
		$this->original_product->save();

		$this->translated_product = \WC_Helper_Product::create_simple_product();
		$this->translated_product->set_name( 'Spanish Product' );
		$this->translated_product->save();

		$this->instance->reset_language_switches();

		$reflection = new \ReflectionClass( $this->instance );
		$method = $reflection->getMethod( 'get_translated_fields' );
		$method->setAccessible( true );

		$result = $method->invoke(
			$this->instance,
			$this->original_product->get_id(),
			$this->translated_product->get_id(),
			null
		);

		$this->assertIsArray( $result );
		// No language switching should occur with null target language
		$this->assertEmpty( $this->instance->language_switches );
	}

	/**
	 * Test that different permalinks are detected.
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_different_permalinks_are_detected() {
		$this->original_product = \WC_Helper_Product::create_simple_product();
		$this->original_product->set_name( 'Product One' );
		$this->original_product->save();

		$this->translated_product = \WC_Helper_Product::create_simple_product();
		$this->translated_product->set_name( 'Product Two' );
		$this->translated_product->save();

		$reflection = new \ReflectionClass( $this->instance );
		$method = $reflection->getMethod( 'get_translated_fields' );
		$method->setAccessible( true );

		$result = $method->invoke(
			$this->instance,
			$this->original_product->get_id(),
			$this->translated_product->get_id()
		);

		$this->assertIsArray( $result );
		// Different product names will result in different permalinks
		$this->assertContains( 'link', $result );
	}

	/**
	 * Test return type is always array.
	 *
	 * @covers WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_return_type_is_always_array() {
		$this->original_product = \WC_Helper_Product::create_simple_product();
		$this->original_product->save();

		$this->translated_product = \WC_Helper_Product::create_simple_product();
		$this->translated_product->save();

		$reflection = new \ReflectionClass( $this->instance );
		$method = $reflection->getMethod( 'get_translated_fields' );
		$method->setAccessible( true );

		// Test with valid products
		$result1 = $method->invoke(
			$this->instance,
			$this->original_product->get_id(),
			$this->translated_product->get_id()
		);
		$this->assertIsArray( $result1 );

		// Test with invalid products
		$result2 = $method->invoke( $this->instance, 999999, 999998 );
		$this->assertIsArray( $result2 );

		// Test with null language
		$result3 = $method->invoke(
			$this->instance,
			$this->original_product->get_id(),
			$this->translated_product->get_id(),
			null
		);
		$this->assertIsArray( $result3 );
	}
}
