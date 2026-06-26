<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit;

use WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait;
use WooCommerce\Facebook\Integrations\Abstract_Localization_Integration;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;

/**
 * Unit tests for Facebook_Fields_Translation_Trait.
 *
 * @since 3.6.0
 */
class Facebook_Fields_Translation_TraitTest extends AbstractWPUnitTestWithSafeFiltering {

	/**
	 * @var object Instance using the trait
	 */
	private $trait_instance;

	/**
	 * @var \WC_Product Original product for testing
	 */
	private $original_product;

	/**
	 * @var \WC_Product Translated product for testing
	 */
	private $translated_product;

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Define the plugin directory constant if not already defined
		if ( ! defined( 'WC_FACEBOOKCOMMERCE_PLUGIN_DIR' ) ) {
			define( 'WC_FACEBOOKCOMMERCE_PLUGIN_DIR', dirname( dirname( dirname( __DIR__ ) ) ) );
		}

		// Create a concrete implementation for testing
		$this->trait_instance = new class extends Abstract_Localization_Integration {
			use Facebook_Fields_Translation_Trait;

			public $switched_language = null;
			public $restored_language = null;

			public function get_plugin_file_name(): string {
				return 'test-plugin/test-plugin.php';
			}

			public function get_plugin_name(): string {
				return 'Test Plugin';
			}

			public function is_plugin_active(): bool {
				return true;
			}

			public function get_available_languages(): array {
				return [ 'en_US', 'es_ES', 'fr_FR' ];
			}

			public function get_default_language(): ?string {
				return 'en_US';
			}

			protected function get_plugin_language_identifier( string $locale ): ?string {
				return substr( $locale, 0, 2 );
			}

			protected function is_product_in_language( int $product_id, string $language_identifier ): bool {
				return true;
			}

			public function switch_to_language( string $locale ): ?string {
				$this->switched_language = $locale;
				return 'en_US'; // Return original language
			}

			public function restore_language( string $language_code ): void {
				$this->restored_language = $language_code;
			}

			// Expose protected methods for testing
			public function test_get_translated_fields( int $original_id, int $translated_id, ?string $target_language = null ): array {
				return $this->get_translated_fields( $original_id, $translated_id, $target_language );
			}

			public function test_get_facebook_field_mapping(): array {
				return $this->get_facebook_field_mapping();
			}
		};

		// Create test products
		$this->original_product = \WC_Helper_Product::create_simple_product();
		$this->translated_product = \WC_Helper_Product::create_simple_product();
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
	 * Test that get_facebook_field_mapping returns an array.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_facebook_field_mapping
	 */
	public function test_get_facebook_field_mapping_returns_array() {
		$result = $this->trait_instance->test_get_facebook_field_mapping();

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Test that get_facebook_field_mapping contains expected field mappings.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_facebook_field_mapping
	 */
	public function test_get_facebook_field_mapping_contains_expected_fields() {
		$result = $this->trait_instance->test_get_facebook_field_mapping();

		$expected_fields = [
			'name',
			'description',
			'short_description',
			'rich_text_description',
			'image_id',
			'gallery_image_ids',
			'video',
			'link',
		];

		foreach ( $expected_fields as $field ) {
			$this->assertArrayHasKey( $field, $result, "Field '{$field}' should be present in mapping" );
		}
	}

	/**
	 * Test that get_facebook_field_mapping values are method names.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_facebook_field_mapping
	 */
	public function test_get_facebook_field_mapping_values_are_method_names() {
		$result = $this->trait_instance->test_get_facebook_field_mapping();

		foreach ( $result as $field_name => $method_name ) {
			$this->assertIsString( $method_name, "Method name for field '{$field_name}' should be a string" );
			$this->assertNotEmpty( $method_name, "Method name for field '{$field_name}' should not be empty" );
		}
	}

	/**
	 * Test that get_facebook_field_mapping has correct method mappings.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_facebook_field_mapping
	 */
	public function test_get_facebook_field_mapping_has_correct_mappings() {
		$result = $this->trait_instance->test_get_facebook_field_mapping();

		$expected_mappings = [
			'name' => 'get_name',
			'description' => 'get_fb_description',
			'short_description' => 'get_fb_short_description',
			'rich_text_description' => 'get_rich_text_description',
			'image_id' => 'get_all_image_urls',
			'gallery_image_ids' => 'get_all_image_urls',
			'video' => 'get_all_video_urls',
			'link' => 'get_permalink',
		];

		foreach ( $expected_mappings as $field => $method ) {
			$this->assertEquals( $method, $result[ $field ], "Field '{$field}' should map to method '{$method}'" );
		}
	}

	/**
	 * Test get_translated_fields when original product doesn't exist.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_returns_empty_when_original_product_not_exists() {
		$result = $this->trait_instance->test_get_translated_fields( 999999, $this->translated_product->get_id() );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_translated_fields when translated product doesn't exist.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_returns_empty_when_translated_product_not_exists() {
		$result = $this->trait_instance->test_get_translated_fields( $this->original_product->get_id(), 999999 );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_translated_fields when both products don't exist.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_returns_empty_when_both_products_not_exist() {
		$result = $this->trait_instance->test_get_translated_fields( 999999, 888888 );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_translated_fields when products have identical values.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_returns_empty_when_products_identical() {
		// Use the same product for both original and translated
		$result = $this->trait_instance->test_get_translated_fields(
			$this->original_product->get_id(),
			$this->original_product->get_id()
		);

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_translated_fields when products have different name values.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_detects_different_name() {
		$this->original_product->set_name( 'Original Product Name' );
		$this->original_product->save();

		$this->translated_product->set_name( 'Translated Product Name' );
		$this->translated_product->save();

		$result = $this->trait_instance->test_get_translated_fields(
			$this->original_product->get_id(),
			$this->translated_product->get_id()
		);

		$this->assertIsArray( $result );
		$this->assertContains( 'name', $result );
	}

	/**
	 * Test get_translated_fields when products have different description values.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_detects_different_description() {
		$this->original_product->set_description( 'Original description' );
		$this->original_product->save();

		$this->translated_product->set_description( 'Translated description' );
		$this->translated_product->save();

		$result = $this->trait_instance->test_get_translated_fields(
			$this->original_product->get_id(),
			$this->translated_product->get_id()
		);

		$this->assertIsArray( $result );
		$this->assertContains( 'description', $result );
	}

	/**
	 * Test get_translated_fields when products have different short_description values.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_detects_different_short_description() {
		$this->original_product->set_short_description( 'Original short description' );
		$this->original_product->save();

		$this->translated_product->set_short_description( 'Translated short description' );
		$this->translated_product->save();

		$result = $this->trait_instance->test_get_translated_fields(
			$this->original_product->get_id(),
			$this->translated_product->get_id()
		);

		$this->assertIsArray( $result );
		$this->assertContains( 'short_description', $result );
	}

	/**
	 * Test get_translated_fields when translated value is empty.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_excludes_empty_translated_value() {
		$this->original_product->set_name( 'Original Product Name' );
		$this->original_product->save();

		$this->translated_product->set_name( '' );
		$this->translated_product->save();

		$result = $this->trait_instance->test_get_translated_fields(
			$this->original_product->get_id(),
			$this->translated_product->get_id()
		);

		$this->assertIsArray( $result );
		$this->assertNotContains( 'name', $result );
	}

	/**
	 * Test get_translated_fields when original value is empty but translated is not.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_includes_when_original_empty_translated_not() {
		$this->original_product->set_description( '' );
		$this->original_product->save();

		$this->translated_product->set_description( 'Translated description' );
		$this->translated_product->save();

		$result = $this->trait_instance->test_get_translated_fields(
			$this->original_product->get_id(),
			$this->translated_product->get_id()
		);

		$this->assertIsArray( $result );
		$this->assertContains( 'description', $result );
	}

	/**
	 * Test get_translated_fields with whitespace trimming.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_trims_whitespace() {
		$this->original_product->set_name( '  Original Name  ' );
		$this->original_product->save();

		$this->translated_product->set_name( 'Original Name' );
		$this->translated_product->save();

		$result = $this->trait_instance->test_get_translated_fields(
			$this->original_product->get_id(),
			$this->translated_product->get_id()
		);

		$this->assertIsArray( $result );
		// Should not contain 'name' because after trimming they are the same
		$this->assertNotContains( 'name', $result );
	}

	/**
	 * Test get_translated_fields with whitespace differences.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_detects_whitespace_differences() {
		$this->original_product->set_name( '  Original Name  ' );
		$this->original_product->save();

		$this->translated_product->set_name( '  Translated Name  ' );
		$this->translated_product->save();

		$result = $this->trait_instance->test_get_translated_fields(
			$this->original_product->get_id(),
			$this->translated_product->get_id()
		);

		$this->assertIsArray( $result );
		$this->assertContains( 'name', $result );
	}

	/**
	 * Test get_translated_fields with target_language parameter.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_with_target_language() {
		$this->original_product->set_name( 'Original Name' );
		$this->original_product->save();

		$this->translated_product->set_name( 'Translated Name' );
		$this->translated_product->save();

		$result = $this->trait_instance->test_get_translated_fields(
			$this->original_product->get_id(),
			$this->translated_product->get_id(),
			'es_ES'
		);

		$this->assertIsArray( $result );
		// Verify language was switched for permalink
		$this->assertEquals( 'es_ES', $this->trait_instance->switched_language );
	}

	/**
	 * Test get_translated_fields language switching for permalink field.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_switches_language_for_permalink() {
		$result = $this->trait_instance->test_get_translated_fields(
			$this->original_product->get_id(),
			$this->translated_product->get_id(),
			'fr_FR'
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'fr_FR', $this->trait_instance->switched_language );
	}

	/**
	 * Test get_translated_fields language restoration after permalink field.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_restores_language_after_permalink() {
		$result = $this->trait_instance->test_get_translated_fields(
			$this->original_product->get_id(),
			$this->translated_product->get_id(),
			'es_ES'
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'en_US', $this->trait_instance->restored_language );
	}

	/**
	 * Test get_translated_fields without target_language parameter.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_without_target_language() {
		$this->original_product->set_name( 'Original Name' );
		$this->original_product->save();

		$this->translated_product->set_name( 'Translated Name' );
		$this->translated_product->save();

		$result = $this->trait_instance->test_get_translated_fields(
			$this->original_product->get_id(),
			$this->translated_product->get_id()
		);

		$this->assertIsArray( $result );
		// Language should not be switched when target_language is null
		$this->assertNull( $this->trait_instance->switched_language );
	}

	/**
	 * Test get_translated_fields returns array type.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_returns_array() {
		$result = $this->trait_instance->test_get_translated_fields(
			$this->original_product->get_id(),
			$this->translated_product->get_id()
		);

		$this->assertIsArray( $result );
	}

	/**
	 * Test get_translated_fields with zero product IDs.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_with_zero_product_ids() {
		$result = $this->trait_instance->test_get_translated_fields( 0, 0 );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_translated_fields with negative product IDs.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_with_negative_product_ids() {
		$result = $this->trait_instance->test_get_translated_fields( -1, -1 );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_translated_fields with multiple different fields.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_detects_multiple_differences() {
		$this->original_product->set_name( 'Original Name' );
		$this->original_product->set_description( 'Original description' );
		$this->original_product->set_short_description( 'Original short' );
		$this->original_product->save();

		$this->translated_product->set_name( 'Translated Name' );
		$this->translated_product->set_description( 'Translated description' );
		$this->translated_product->set_short_description( 'Translated short' );
		$this->translated_product->save();

		$result = $this->trait_instance->test_get_translated_fields(
			$this->original_product->get_id(),
			$this->translated_product->get_id()
		);

		$this->assertIsArray( $result );
		$this->assertContains( 'name', $result );
		$this->assertContains( 'description', $result );
		$this->assertContains( 'short_description', $result );
	}

	/**
	 * Test get_translated_fields with only whitespace in translated value.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_excludes_whitespace_only_translated_value() {
		$this->original_product->set_name( 'Original Name' );
		$this->original_product->save();

		$this->translated_product->set_name( '   ' );
		$this->translated_product->save();

		$result = $this->trait_instance->test_get_translated_fields(
			$this->original_product->get_id(),
			$this->translated_product->get_id()
		);

		$this->assertIsArray( $result );
		// Should not contain 'name' because trimmed translated value is empty
		$this->assertNotContains( 'name', $result );
	}

	/**
	 * Test get_translated_fields with null target_language.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_with_null_target_language() {
		$result = $this->trait_instance->test_get_translated_fields(
			$this->original_product->get_id(),
			$this->translated_product->get_id(),
			null
		);

		$this->assertIsArray( $result );
		$this->assertNull( $this->trait_instance->switched_language );
		$this->assertNull( $this->trait_instance->restored_language );
	}

	/**
	 * Test get_translated_fields with empty string target_language.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait::get_translated_fields
	 */
	public function test_get_translated_fields_with_empty_target_language() {
		$result = $this->trait_instance->test_get_translated_fields(
			$this->original_product->get_id(),
			$this->translated_product->get_id(),
			''
		);

		$this->assertIsArray( $result );
		// Empty string is falsy, so language should not be switched
		$this->assertNull( $this->trait_instance->switched_language );
	}

	/**
	 * Test that trait can be used in a class.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Facebook_Fields_Translation_Trait
	 */
	public function test_trait_can_be_used() {
		$this->assertNotNull( $this->trait_instance );
		$this->assertTrue( method_exists( $this->trait_instance, 'test_get_facebook_field_mapping' ) );
		$this->assertTrue( method_exists( $this->trait_instance, 'test_get_translated_fields' ) );
	}
}
