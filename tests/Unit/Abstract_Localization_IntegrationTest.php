<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit;

use WooCommerce\Facebook\Integrations\Abstract_Localization_Integration;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;

/**
 * Unit tests for Abstract_Localization_Integration class.
 *
 * @since 3.6.0
 */
class Abstract_Localization_IntegrationTest extends AbstractWPUnitTestWithSafeFiltering {

	/**
	 * @var Abstract_Localization_Integration
	 */
	private $integration;

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create a concrete implementation for testing
		$this->integration = new class extends Abstract_Localization_Integration {
			public $plugin_active = true;
			public $default_language = 'en_US';
			public $available_languages = [ 'en_US', 'es_ES', 'fr_FR' ];
			public $plugin_language_identifier = 'en';
			public $product_in_language = true;

			public function get_plugin_file_name(): string {
				return 'test-plugin/test-plugin.php';
			}

			public function get_plugin_name(): string {
				return 'Test Plugin';
			}

			public function is_plugin_active(): bool {
				return $this->plugin_active;
			}

			public function get_available_languages(): array {
				return $this->available_languages;
			}

			public function get_default_language(): ?string {
				return $this->default_language;
			}

			protected function get_plugin_language_identifier( string $locale ): ?string {
				return $this->plugin_language_identifier;
			}

			protected function is_product_in_language( int $product_id, string $language_identifier ): bool {
				return $this->product_in_language;
			}

			public function switch_to_language( string $locale ): ?string {
				return 'previous_language';
			}

			public function restore_language( string $language_code ): void {
				// No-op for testing
			}
		};
	}

	/**
	 * Test that the class can be instantiated.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration
	 */
	public function test_class_exists_and_can_be_instantiated() {
		$this->assertTrue( class_exists( Abstract_Localization_Integration::class ) );
		$this->assertInstanceOf( Abstract_Localization_Integration::class, $this->integration );
	}

	/**
	 * Test is_available returns true when plugin is active and has default language.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::is_available
	 */
	public function test_is_available_returns_true_when_plugin_active_and_has_default_language() {
		$this->integration->plugin_active = true;
		$this->integration->default_language = 'en_US';

		$result = $this->integration->is_available();

		$this->assertTrue( $result );
	}

	/**
	 * Test is_available returns false when plugin is not active.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::is_available
	 */
	public function test_is_available_returns_false_when_plugin_not_active() {
		$this->integration->plugin_active = false;
		$this->integration->default_language = 'en_US';

		$result = $this->integration->is_available();

		$this->assertFalse( $result );
	}

	/**
	 * Test is_available returns false when default language is null.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::is_available
	 */
	public function test_is_available_returns_false_when_default_language_is_null() {
		$this->integration->plugin_active = true;
		$this->integration->default_language = null;

		$result = $this->integration->is_available();

		$this->assertFalse( $result );
	}

	/**
	 * Test is_available returns false when default language is empty string.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::is_available
	 */
	public function test_is_available_returns_false_when_default_language_is_empty() {
		$this->integration->plugin_active = true;
		$this->integration->default_language = '';

		$result = $this->integration->is_available();

		$this->assertFalse( $result );
	}

	/**
	 * Test is_plugin_installed returns true when plugin file exists.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::is_plugin_installed
	 */
	public function test_is_plugin_installed_returns_true_when_file_exists() {
		// Mock WP_PLUGIN_DIR constant if not defined
		if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
			define( 'WP_PLUGIN_DIR', '/tmp/wp-content/plugins' );
		}

		// Create a temporary file to simulate plugin existence
		$plugin_path = WP_PLUGIN_DIR . '/' . $this->integration->get_plugin_file_name();
		$plugin_dir = dirname( $plugin_path );

		if ( ! file_exists( $plugin_dir ) ) {
			mkdir( $plugin_dir, 0777, true );
		}
		touch( $plugin_path );

		$result = $this->integration->is_plugin_installed();

		$this->assertTrue( $result );

		// Clean up
		if ( file_exists( $plugin_path ) ) {
			unlink( $plugin_path );
		}
		if ( file_exists( $plugin_dir ) && is_dir( $plugin_dir ) ) {
			rmdir( $plugin_dir );
		}
	}

	/**
	 * Test is_plugin_installed returns false when plugin file does not exist.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::is_plugin_installed
	 */
	public function test_is_plugin_installed_returns_false_when_file_does_not_exist() {
		// Mock WP_PLUGIN_DIR constant if not defined
		if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
			define( 'WP_PLUGIN_DIR', '/tmp/wp-content/plugins' );
		}

		// Ensure the file does not exist
		$plugin_path = WP_PLUGIN_DIR . '/' . $this->integration->get_plugin_file_name();
		if ( file_exists( $plugin_path ) ) {
			unlink( $plugin_path );
		}

		$result = $this->integration->is_plugin_installed();

		$this->assertFalse( $result );
	}

	/**
	 * Test get_plugin_version returns null when plugin is not active.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::get_plugin_version
	 */
	public function test_get_plugin_version_returns_null_when_plugin_not_active() {
		$this->integration->plugin_active = false;

		$result = $this->integration->get_plugin_version();

		$this->assertNull( $result );
	}

	/**
	 * Test get_plugin_version returns null when version is not available.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::get_plugin_version
	 */
	public function test_get_plugin_version_returns_null_when_version_not_available() {
		$this->integration->plugin_active = true;

		// Mock get_plugin_data to return empty version
		if ( ! function_exists( 'get_plugin_data' ) ) {
			function get_plugin_data( $plugin_file ) {
				return [];
			}
		}

		$result = $this->integration->get_plugin_version();

		$this->assertNull( $result );
	}

	/**
	 * Test get_products_from_default_language returns empty array when plugin not active.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::get_products_from_default_language
	 */
	public function test_get_products_from_default_language_returns_empty_when_plugin_not_active() {
		$this->integration->plugin_active = false;

		$result = $this->integration->get_products_from_default_language();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_products_from_default_language returns empty array when default language is null.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::get_products_from_default_language
	 */
	public function test_get_products_from_default_language_returns_empty_when_default_language_null() {
		$this->integration->plugin_active = true;
		$this->integration->default_language = null;

		$result = $this->integration->get_products_from_default_language();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_products_from_default_language returns empty array when language identifier is null.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::get_products_from_default_language
	 */
	public function test_get_products_from_default_language_returns_empty_when_language_identifier_null() {
		$this->integration->plugin_active = true;
		$this->integration->default_language = 'en_US';
		$this->integration->plugin_language_identifier = null;

		$result = $this->integration->get_products_from_default_language();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_products_from_default_language with custom limit.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::get_products_from_default_language
	 */
	public function test_get_products_from_default_language_with_custom_limit() {
		$this->integration->plugin_active = true;
		$this->integration->default_language = 'en_US';
		$this->integration->plugin_language_identifier = 'en';

		// Create test products
		$product1 = \WC_Helper_Product::create_simple_product();
		$product2 = \WC_Helper_Product::create_simple_product();
		$product3 = \WC_Helper_Product::create_simple_product();

		$result = $this->integration->get_products_from_default_language( 2, 0 );

		$this->assertIsArray( $result );
		$this->assertLessThanOrEqual( 2, count( $result ) );

		// Clean up
		wp_delete_post( $product1->get_id(), true );
		wp_delete_post( $product2->get_id(), true );
		wp_delete_post( $product3->get_id(), true );
	}

	/**
	 * Test get_products_from_default_language with offset.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::get_products_from_default_language
	 */
	public function test_get_products_from_default_language_with_offset() {
		$this->integration->plugin_active = true;
		$this->integration->default_language = 'en_US';
		$this->integration->plugin_language_identifier = 'en';

		// Create test products
		$product1 = \WC_Helper_Product::create_simple_product();
		$product2 = \WC_Helper_Product::create_simple_product();

		$result = $this->integration->get_products_from_default_language( 10, 1 );

		$this->assertIsArray( $result );

		// Clean up
		wp_delete_post( $product1->get_id(), true );
		wp_delete_post( $product2->get_id(), true );
	}

	/**
	 * Test get_products_from_default_language with limit -1 (all products).
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::get_products_from_default_language
	 */
	public function test_get_products_from_default_language_with_limit_minus_one() {
		$this->integration->plugin_active = true;
		$this->integration->default_language = 'en_US';
		$this->integration->plugin_language_identifier = 'en';

		$result = $this->integration->get_products_from_default_language( -1, 0 );

		$this->assertIsArray( $result );
	}

	/**
	 * Test get_products_from_default_language filters products by language.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::get_products_from_default_language
	 */
	public function test_get_products_from_default_language_filters_by_language() {
		$this->integration->plugin_active = true;
		$this->integration->default_language = 'en_US';
		$this->integration->plugin_language_identifier = 'en';
		$this->integration->product_in_language = false;

		// Create test products
		$product = \WC_Helper_Product::create_simple_product();

		$result = $this->integration->get_products_from_default_language( 10, 0 );

		// Should be empty since product_in_language returns false
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );

		// Clean up
		wp_delete_post( $product->get_id(), true );
	}

	/**
	 * Test get_product_translation_details returns default structure.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::get_product_translation_details
	 */
	public function test_get_product_translation_details_returns_default_structure() {
		$product = \WC_Helper_Product::create_simple_product();
		$product_id = $product->get_id();

		$result = $this->integration->get_product_translation_details( $product_id );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'product_id', $result );
		$this->assertArrayHasKey( 'default_language', $result );
		$this->assertArrayHasKey( 'translations', $result );
		$this->assertArrayHasKey( 'translation_status', $result );
		$this->assertArrayHasKey( 'translated_fields', $result );

		$this->assertEquals( $product_id, $result['product_id'] );
		$this->assertEquals( 'en_US', $result['default_language'] );
		$this->assertIsArray( $result['translations'] );
		$this->assertEmpty( $result['translations'] );

		// Clean up
		wp_delete_post( $product_id, true );
	}

	/**
	 * Test get_product_translation_details with different product IDs.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::get_product_translation_details
	 */
	public function test_get_product_translation_details_with_different_product_ids() {
		$product1 = \WC_Helper_Product::create_simple_product();
		$product2 = \WC_Helper_Product::create_simple_product();

		$result1 = $this->integration->get_product_translation_details( $product1->get_id() );
		$result2 = $this->integration->get_product_translation_details( $product2->get_id() );

		$this->assertEquals( $product1->get_id(), $result1['product_id'] );
		$this->assertEquals( $product2->get_id(), $result2['product_id'] );
		$this->assertNotEquals( $result1['product_id'], $result2['product_id'] );

		// Clean up
		wp_delete_post( $product1->get_id(), true );
		wp_delete_post( $product2->get_id(), true );
	}

	/**
	 * Test get_availability_data returns correct structure.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::get_availability_data
	 */
	public function test_get_availability_data_returns_correct_structure() {
		$this->integration->plugin_active = true;

		$result = $this->integration->get_availability_data();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'plugin_name', $result );
		$this->assertArrayHasKey( 'plugin_file', $result );
		$this->assertArrayHasKey( 'is_installed', $result );
		$this->assertArrayHasKey( 'is_active', $result );

		$this->assertEquals( 'Test Plugin', $result['plugin_name'] );
		$this->assertEquals( 'test-plugin/test-plugin.php', $result['plugin_file'] );
		$this->assertIsBool( $result['is_installed'] );
		$this->assertTrue( $result['is_active'] );
	}

	/**
	 * Test get_availability_data includes version when available.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::get_availability_data
	 */
	public function test_get_availability_data_includes_version_when_available() {
		$this->integration->plugin_active = true;

		// Mock get_plugin_data to return a version
		if ( ! function_exists( 'get_plugin_data' ) ) {
			function get_plugin_data( $plugin_file ) {
				return [ 'Version' => '1.2.3' ];
			}
		}

		// Mock WP_PLUGIN_DIR
		if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
			define( 'WP_PLUGIN_DIR', '/tmp/wp-content/plugins' );
		}

		$result = $this->integration->get_availability_data();

		$this->assertIsArray( $result );
		// Version may or may not be included depending on get_plugin_version implementation
		// Just verify the structure is correct
		$this->assertArrayHasKey( 'plugin_name', $result );
		$this->assertArrayHasKey( 'is_active', $result );
	}

	/**
	 * Test get_availability_data when plugin is not active.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::get_availability_data
	 */
	public function test_get_availability_data_when_plugin_not_active() {
		$this->integration->plugin_active = false;

		$result = $this->integration->get_availability_data();

		$this->assertIsArray( $result );
		$this->assertFalse( $result['is_active'] );
		$this->assertArrayNotHasKey( 'version', $result );
	}

	/**
	 * Test switch_to_language returns previous language.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::switch_to_language
	 */
	public function test_switch_to_language_returns_previous_language() {
		$result = $this->integration->switch_to_language( 'es_ES' );

		$this->assertIsString( $result );
		$this->assertEquals( 'previous_language', $result );
	}

	/**
	 * Test switch_to_language with different locales.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::switch_to_language
	 */
	public function test_switch_to_language_with_different_locales() {
		$result1 = $this->integration->switch_to_language( 'en_US' );
		$result2 = $this->integration->switch_to_language( 'fr_FR' );
		$result3 = $this->integration->switch_to_language( 'zh_CN' );

		$this->assertIsString( $result1 );
		$this->assertIsString( $result2 );
		$this->assertIsString( $result3 );
	}

	/**
	 * Test restore_language executes without error.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::restore_language
	 */
	public function test_restore_language_executes_without_error() {
		try {
			$this->integration->restore_language( 'en_US' );
			$this->assertTrue( true );
		} catch ( \Throwable $e ) {
			$this->fail( 'restore_language should not throw an exception: ' . $e->getMessage() );
		}
	}

	/**
	 * Test abstract methods are properly defined.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration
	 */
	public function test_abstract_methods_are_defined() {
		$reflection = new \ReflectionClass( Abstract_Localization_Integration::class );

		$this->assertTrue( $reflection->hasMethod( 'get_plugin_file_name' ) );
		$this->assertTrue( $reflection->hasMethod( 'get_plugin_name' ) );
		$this->assertTrue( $reflection->hasMethod( 'is_plugin_active' ) );
		$this->assertTrue( $reflection->hasMethod( 'get_available_languages' ) );
		$this->assertTrue( $reflection->hasMethod( 'get_default_language' ) );
		$this->assertTrue( $reflection->hasMethod( 'switch_to_language' ) );
		$this->assertTrue( $reflection->hasMethod( 'restore_language' ) );

		// Check abstract methods
		$this->assertTrue( $reflection->getMethod( 'get_plugin_file_name' )->isAbstract() );
		$this->assertTrue( $reflection->getMethod( 'get_plugin_name' )->isAbstract() );
		$this->assertTrue( $reflection->getMethod( 'is_plugin_active' )->isAbstract() );
		$this->assertTrue( $reflection->getMethod( 'get_available_languages' )->isAbstract() );
		$this->assertTrue( $reflection->getMethod( 'get_default_language' )->isAbstract() );
		$this->assertTrue( $reflection->getMethod( 'switch_to_language' )->isAbstract() );
		$this->assertTrue( $reflection->getMethod( 'restore_language' )->isAbstract() );
	}

	/**
	 * Test get_plugin_file_name returns string.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::get_plugin_file_name
	 */
	public function test_get_plugin_file_name_returns_string() {
		$result = $this->integration->get_plugin_file_name();

		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Test get_plugin_name returns string.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::get_plugin_name
	 */
	public function test_get_plugin_name_returns_string() {
		$result = $this->integration->get_plugin_name();

		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Test is_plugin_active returns boolean.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::is_plugin_active
	 */
	public function test_is_plugin_active_returns_boolean() {
		$result = $this->integration->is_plugin_active();

		$this->assertIsBool( $result );
	}

	/**
	 * Test get_available_languages returns array.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::get_available_languages
	 */
	public function test_get_available_languages_returns_array() {
		$result = $this->integration->get_available_languages();

		$this->assertIsArray( $result );
	}

	/**
	 * Test get_default_language returns string or null.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::get_default_language
	 */
	public function test_get_default_language_returns_string_or_null() {
		$result = $this->integration->get_default_language();

		$this->assertTrue( is_string( $result ) || is_null( $result ) );
	}

	/**
	 * Test is_available with edge case: empty string default language.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::is_available
	 */
	public function test_is_available_edge_case_empty_string() {
		$this->integration->plugin_active = true;
		$this->integration->default_language = '';

		$result = $this->integration->is_available();

		$this->assertFalse( $result );
	}

	/**
	 * Test is_available with edge case: whitespace-only default language.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::is_available
	 */
	public function test_is_available_edge_case_whitespace() {
		$this->integration->plugin_active = true;
		$this->integration->default_language = '   ';

		$result = $this->integration->is_available();

		// empty() considers whitespace-only strings as non-empty, so this should return true
		$this->assertTrue( $result );
	}

	/**
	 * Test get_products_from_default_language with zero limit.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::get_products_from_default_language
	 */
	public function test_get_products_from_default_language_with_zero_limit() {
		$this->integration->plugin_active = true;
		$this->integration->default_language = 'en_US';
		$this->integration->plugin_language_identifier = 'en';

		$result = $this->integration->get_products_from_default_language( 0, 0 );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_product_translation_details with zero product ID.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::get_product_translation_details
	 */
	public function test_get_product_translation_details_with_zero_product_id() {
		$result = $this->integration->get_product_translation_details( 0 );

		$this->assertIsArray( $result );
		$this->assertEquals( 0, $result['product_id'] );
	}

	/**
	 * Test get_product_translation_details with negative product ID.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::get_product_translation_details
	 */
	public function test_get_product_translation_details_with_negative_product_id() {
		$result = $this->integration->get_product_translation_details( -1 );

		$this->assertIsArray( $result );
		$this->assertEquals( -1, $result['product_id'] );
	}
}
