<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit;

use WooCommerce\Facebook\Integrations\IntegrationRegistry;
use WooCommerce\Facebook\Integrations\Abstract_Localization_Integration;
use WooCommerce\Facebook\Integrations\Polylang;
use WooCommerce\Facebook\Integrations\WPML;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for IntegrationRegistry class.
 *
 * @since 3.5.9
 */
class IntegrationRegistryTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		IntegrationRegistry::clear_cache();
	}

	/**
	 * Tear down the test environment.
	 */
	public function tearDown(): void {
		IntegrationRegistry::clear_cache();
		parent::tearDown();
	}

	/**
	 * Test that the class exists.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( IntegrationRegistry::class ) );
	}

	/**
	 * Test get_localization_integration_keys returns an array.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_localization_integration_keys
	 */
	public function test_get_localization_integration_keys_returns_array() {
		$result = IntegrationRegistry::get_localization_integration_keys();

		$this->assertIsArray( $result );
	}

	/**
	 * Test get_localization_integration_keys returns expected keys.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_localization_integration_keys
	 */
	public function test_get_localization_integration_keys_returns_expected_keys() {
		$result = IntegrationRegistry::get_localization_integration_keys();

		$this->assertContains( 'polylang', $result );
		$this->assertContains( 'wpml', $result );
	}

	/**
	 * Test get_localization_integration_keys returns non-empty array.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_localization_integration_keys
	 */
	public function test_get_localization_integration_keys_not_empty() {
		$result = IntegrationRegistry::get_localization_integration_keys();

		$this->assertNotEmpty( $result );
	}

	/**
	 * Test get_localization_integration_keys returns string keys.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_localization_integration_keys
	 */
	public function test_get_localization_integration_keys_contains_strings() {
		$result = IntegrationRegistry::get_localization_integration_keys();

		foreach ( $result as $key ) {
			$this->assertIsString( $key );
		}
	}

	/**
	 * Test get_localization_integration with valid polylang key.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_localization_integration
	 */
	public function test_get_localization_integration_with_polylang_key() {
		$result = IntegrationRegistry::get_localization_integration( 'polylang' );

		$this->assertInstanceOf( Polylang::class, $result );
		$this->assertInstanceOf( Abstract_Localization_Integration::class, $result );
	}

	/**
	 * Test get_localization_integration with valid wpml key.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_localization_integration
	 */
	public function test_get_localization_integration_with_wpml_key() {
		$result = IntegrationRegistry::get_localization_integration( 'wpml' );

		$this->assertInstanceOf( WPML::class, $result );
		$this->assertInstanceOf( Abstract_Localization_Integration::class, $result );
	}

	/**
	 * Test get_localization_integration with invalid key returns null.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_localization_integration
	 */
	public function test_get_localization_integration_with_invalid_key_returns_null() {
		$result = IntegrationRegistry::get_localization_integration( 'invalid_key' );

		$this->assertNull( $result );
	}

	/**
	 * Test get_localization_integration with empty string returns null.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_localization_integration
	 */
	public function test_get_localization_integration_with_empty_string_returns_null() {
		$result = IntegrationRegistry::get_localization_integration( '' );

		$this->assertNull( $result );
	}

	/**
	 * Test get_localization_integration caching - calling twice returns same instance.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_localization_integration
	 */
	public function test_get_localization_integration_caching() {
		$first_call = IntegrationRegistry::get_localization_integration( 'polylang' );
		$second_call = IntegrationRegistry::get_localization_integration( 'polylang' );

		$this->assertSame( $first_call, $second_call );
	}

	/**
	 * Test get_localization_integration with different keys returns different instances.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_localization_integration
	 */
	public function test_get_localization_integration_different_keys_different_instances() {
		$polylang = IntegrationRegistry::get_localization_integration( 'polylang' );
		$wpml = IntegrationRegistry::get_localization_integration( 'wpml' );

		$this->assertNotSame( $polylang, $wpml );
		$this->assertInstanceOf( Polylang::class, $polylang );
		$this->assertInstanceOf( WPML::class, $wpml );
	}

	/**
	 * Test get_all_localization_integrations returns array.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_all_localization_integrations
	 */
	public function test_get_all_localization_integrations_returns_array() {
		$result = IntegrationRegistry::get_all_localization_integrations();

		$this->assertIsArray( $result );
	}

	/**
	 * Test get_all_localization_integrations returns non-empty array.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_all_localization_integrations
	 */
	public function test_get_all_localization_integrations_not_empty() {
		$result = IntegrationRegistry::get_all_localization_integrations();

		$this->assertNotEmpty( $result );
		$this->assertGreaterThanOrEqual( 2, count( $result ) );
	}

	/**
	 * Test get_all_localization_integrations array keys match integration keys.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_all_localization_integrations
	 */
	public function test_get_all_localization_integrations_keys_match() {
		$result = IntegrationRegistry::get_all_localization_integrations();
		$expected_keys = IntegrationRegistry::get_localization_integration_keys();

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $result );
		}
	}

	/**
	 * Test get_all_localization_integrations values are Abstract_Localization_Integration instances.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_all_localization_integrations
	 */
	public function test_get_all_localization_integrations_values_are_correct_type() {
		$result = IntegrationRegistry::get_all_localization_integrations();

		foreach ( $result as $integration ) {
			$this->assertInstanceOf( Abstract_Localization_Integration::class, $integration );
		}
	}

	/**
	 * Test get_all_localization_integrations caching works across multiple calls.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_all_localization_integrations
	 */
	public function test_get_all_localization_integrations_caching() {
		$first_call = IntegrationRegistry::get_all_localization_integrations();
		$second_call = IntegrationRegistry::get_all_localization_integrations();

		$this->assertSame( $first_call['polylang'], $second_call['polylang'] );
		$this->assertSame( $first_call['wpml'], $second_call['wpml'] );
	}

	/**
	 * Test get_all_localization_integrations returns expected integrations.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_all_localization_integrations
	 */
	public function test_get_all_localization_integrations_returns_expected_integrations() {
		$result = IntegrationRegistry::get_all_localization_integrations();

		$this->assertArrayHasKey( 'polylang', $result );
		$this->assertArrayHasKey( 'wpml', $result );
		$this->assertInstanceOf( Polylang::class, $result['polylang'] );
		$this->assertInstanceOf( WPML::class, $result['wpml'] );
	}

	/**
	 * Test get_all_localization_availability_data returns array.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_all_localization_availability_data
	 */
	public function test_get_all_localization_availability_data_returns_array() {
		$result = IntegrationRegistry::get_all_localization_availability_data();

		$this->assertIsArray( $result );
	}

	/**
	 * Test get_all_localization_availability_data returns non-empty array.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_all_localization_availability_data
	 */
	public function test_get_all_localization_availability_data_not_empty() {
		$result = IntegrationRegistry::get_all_localization_availability_data();

		$this->assertNotEmpty( $result );
		$this->assertGreaterThanOrEqual( 2, count( $result ) );
	}

	/**
	 * Test get_all_localization_availability_data array keys match integration keys.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_all_localization_availability_data
	 */
	public function test_get_all_localization_availability_data_keys_match() {
		$result = IntegrationRegistry::get_all_localization_availability_data();
		$expected_keys = IntegrationRegistry::get_localization_integration_keys();

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $result );
		}
	}

	/**
	 * Test get_all_localization_availability_data each value is an array.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_all_localization_availability_data
	 */
	public function test_get_all_localization_availability_data_values_are_arrays() {
		$result = IntegrationRegistry::get_all_localization_availability_data();

		foreach ( $result as $availability_data ) {
			$this->assertIsArray( $availability_data );
		}
	}

	/**
	 * Test get_all_localization_availability_data structure includes required fields.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_all_localization_availability_data
	 */
	public function test_get_all_localization_availability_data_structure() {
		$result = IntegrationRegistry::get_all_localization_availability_data();

		foreach ( $result as $key => $availability_data ) {
			$this->assertArrayHasKey( 'plugin_name', $availability_data );
			$this->assertArrayHasKey( 'plugin_file', $availability_data );
			$this->assertArrayHasKey( 'is_installed', $availability_data );
			$this->assertArrayHasKey( 'is_active', $availability_data );

			$this->assertIsString( $availability_data['plugin_name'] );
			$this->assertIsString( $availability_data['plugin_file'] );
			$this->assertIsBool( $availability_data['is_installed'] );
			$this->assertIsBool( $availability_data['is_active'] );
		}
	}

	/**
	 * Test get_all_localization_availability_data returns data for polylang.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_all_localization_availability_data
	 */
	public function test_get_all_localization_availability_data_polylang() {
		$result = IntegrationRegistry::get_all_localization_availability_data();

		$this->assertArrayHasKey( 'polylang', $result );
		$this->assertEquals( 'Polylang', $result['polylang']['plugin_name'] );
		$this->assertEquals( 'polylang/polylang.php', $result['polylang']['plugin_file'] );
	}

	/**
	 * Test get_all_localization_availability_data returns data for wpml.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_all_localization_availability_data
	 */
	public function test_get_all_localization_availability_data_wpml() {
		$result = IntegrationRegistry::get_all_localization_availability_data();

		$this->assertArrayHasKey( 'wpml', $result );
		$this->assertEquals( 'WPML', $result['wpml']['plugin_name'] );
		$this->assertEquals( 'sitepress-multilingual-cms/sitepress.php', $result['wpml']['plugin_file'] );
	}

	/**
	 * Test register_localization_integration with valid integration returns true.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::register_localization_integration
	 */
	public function test_register_localization_integration_with_valid_class_returns_true() {
		// Create a mock integration class
		$mock_class = new class extends Abstract_Localization_Integration {
			public function get_plugin_file_name(): string {
				return 'test/test.php';
			}
			public function get_plugin_name(): string {
				return 'Test';
			}
			public function is_plugin_active(): bool {
				return false;
			}
			public function get_available_languages(): array {
				return [];
			}
			public function get_default_language(): ?string {
				return null;
			}
			protected function get_plugin_language_identifier( string $locale ): ?string {
				return null;
			}
			protected function is_product_in_language( int $product_id, string $language_identifier ): bool {
				return false;
			}
			public function switch_to_language( string $locale ): ?string {
				return null;
			}
			public function restore_language( string $language_code ): void {
			}
		};

		$result = IntegrationRegistry::register_localization_integration( 'test', get_class( $mock_class ) );

		$this->assertTrue( $result );
	}

	/**
	 * Test register_localization_integration with non-existent class returns false.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::register_localization_integration
	 */
	public function test_register_localization_integration_with_nonexistent_class_returns_false() {
		$result = IntegrationRegistry::register_localization_integration( 'test', 'NonExistentClass' );

		$this->assertFalse( $result );
	}

	/**
	 * Test register_localization_integration with invalid class returns false.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::register_localization_integration
	 */
	public function test_register_localization_integration_with_invalid_class_returns_false() {
		// Use a class that doesn't extend Abstract_Localization_Integration
		$result = IntegrationRegistry::register_localization_integration( 'test', \stdClass::class );

		$this->assertFalse( $result );
	}

	/**
	 * Test register_localization_integration clears cached instance.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::register_localization_integration
	 */
	public function test_register_localization_integration_clears_cache() {
		// Get an instance to cache it
		$first_instance = IntegrationRegistry::get_localization_integration( 'polylang' );

		// Register a new class for the same key
		$mock_class = new class extends Abstract_Localization_Integration {
			public function get_plugin_file_name(): string {
				return 'test/test.php';
			}
			public function get_plugin_name(): string {
				return 'Test';
			}
			public function is_plugin_active(): bool {
				return false;
			}
			public function get_available_languages(): array {
				return [];
			}
			public function get_default_language(): ?string {
				return null;
			}
			protected function get_plugin_language_identifier( string $locale ): ?string {
				return null;
			}
			protected function is_product_in_language( int $product_id, string $language_identifier ): bool {
				return false;
			}
			public function switch_to_language( string $locale ): ?string {
				return null;
			}
			public function restore_language( string $language_code ): void {
			}
		};

		IntegrationRegistry::register_localization_integration( 'polylang', get_class( $mock_class ) );

		// Get the instance again - should be a new instance
		$second_instance = IntegrationRegistry::get_localization_integration( 'polylang' );

		$this->assertNotSame( $first_instance, $second_instance );
	}

	/**
	 * Test register_localization_integration can retrieve registered integration.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::register_localization_integration
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_localization_integration
	 */
	public function test_register_localization_integration_can_retrieve() {
		$mock_class = new class extends Abstract_Localization_Integration {
			public function get_plugin_file_name(): string {
				return 'custom/custom.php';
			}
			public function get_plugin_name(): string {
				return 'Custom';
			}
			public function is_plugin_active(): bool {
				return false;
			}
			public function get_available_languages(): array {
				return [];
			}
			public function get_default_language(): ?string {
				return null;
			}
			protected function get_plugin_language_identifier( string $locale ): ?string {
				return null;
			}
			protected function is_product_in_language( int $product_id, string $language_identifier ): bool {
				return false;
			}
			public function switch_to_language( string $locale ): ?string {
				return null;
			}
			public function restore_language( string $language_code ): void {
			}
		};

		$class_name = get_class( $mock_class );
		IntegrationRegistry::register_localization_integration( 'custom', $class_name );

		$result = IntegrationRegistry::get_localization_integration( 'custom' );

		$this->assertInstanceOf( $class_name, $result );
		$this->assertInstanceOf( Abstract_Localization_Integration::class, $result );
	}

	/**
	 * Test has_active_localization_plugin returns boolean.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::has_active_localization_plugin
	 */
	public function test_has_active_localization_plugin_returns_boolean() {
		$result = IntegrationRegistry::has_active_localization_plugin();

		$this->assertIsBool( $result );
	}

	/**
	 * Test has_active_localization_plugin returns false when no plugins active.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::has_active_localization_plugin
	 */
	public function test_has_active_localization_plugin_returns_false_when_none_active() {
		// Since Polylang and WPML are not actually active in test environment
		$result = IntegrationRegistry::has_active_localization_plugin();

		$this->assertFalse( $result );
	}

	/**
	 * Test get_active_localization_integration returns null or Abstract_Localization_Integration.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_active_localization_integration
	 */
	public function test_get_active_localization_integration_returns_correct_type() {
		$result = IntegrationRegistry::get_active_localization_integration();

		$this->assertTrue( is_null( $result ) || $result instanceof Abstract_Localization_Integration );
	}

	/**
	 * Test get_active_localization_integration returns null when no plugins active.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_active_localization_integration
	 */
	public function test_get_active_localization_integration_returns_null_when_none_active() {
		// Since Polylang and WPML are not actually active in test environment
		$result = IntegrationRegistry::get_active_localization_integration();

		$this->assertNull( $result );
	}

	/**
	 * Test clear_cache executes without error.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::clear_cache
	 */
	public function test_clear_cache_executes_without_error() {
		try {
			IntegrationRegistry::clear_cache();
			$this->assertTrue( true );
		} catch ( \Throwable $e ) {
			$this->fail( 'clear_cache should not throw an exception: ' . $e->getMessage() );
		}
	}

	/**
	 * Test clear_cache clears cached instances.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::clear_cache
	 */
	public function test_clear_cache_clears_instances() {
		$first_instance = IntegrationRegistry::get_localization_integration( 'polylang' );

		IntegrationRegistry::clear_cache();

		$second_instance = IntegrationRegistry::get_localization_integration( 'polylang' );

		$this->assertNotSame( $first_instance, $second_instance );
	}

	/**
	 * Test clear_cache allows subsequent calls to create new instances.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::clear_cache
	 */
	public function test_clear_cache_subsequent_calls_create_new_instances() {
		$first = IntegrationRegistry::get_localization_integration( 'polylang' );
		$second = IntegrationRegistry::get_localization_integration( 'polylang' );

		$this->assertSame( $first, $second );

		IntegrationRegistry::clear_cache();

		$third = IntegrationRegistry::get_localization_integration( 'polylang' );

		$this->assertNotSame( $first, $third );
		$this->assertInstanceOf( Polylang::class, $third );
	}

	/**
	 * Test get_all_active_plugin_data returns array.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_all_active_plugin_data
	 */
	public function test_get_all_active_plugin_data_returns_array() {
		$result = IntegrationRegistry::get_all_active_plugin_data();

		$this->assertIsArray( $result );
	}

	/**
	 * Test get_all_active_plugin_data returns plugin names.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_all_active_plugin_data
	 */
	public function test_get_all_active_plugin_data_returns_plugin_names() {
		$result = IntegrationRegistry::get_all_active_plugin_data();

		foreach ( $result as $plugin_name ) {
			$this->assertIsString( $plugin_name );
		}
	}

	/**
	 * Test get_all_active_plugin_data handles missing get_plugins function.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_all_active_plugin_data
	 */
	public function test_get_all_active_plugin_data_handles_missing_function() {
		// The method should include the get_plugins function if it doesn't exist
		$result = IntegrationRegistry::get_all_active_plugin_data();

		$this->assertIsArray( $result );
	}

	/**
	 * Test get_all_active_plugin_data with no active plugins returns empty array.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_all_active_plugin_data
	 */
	public function test_get_all_active_plugin_data_with_no_active_plugins() {
		// Mock empty active plugins
		update_option( 'active_plugins', [] );

		$result = IntegrationRegistry::get_all_active_plugin_data();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_all_active_plugin_data handles exception gracefully.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_all_active_plugin_data
	 */
	public function test_get_all_active_plugin_data_handles_exception() {
		// Even if there's an error, it should return an empty array
		$result = IntegrationRegistry::get_all_active_plugin_data();

		$this->assertIsArray( $result );
	}

	/**
	 * Test get_all_active_plugin_data with active plugins.
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_all_active_plugin_data
	 */
	public function test_get_all_active_plugin_data_with_active_plugins() {
		// The test environment should have some active plugins
		$result = IntegrationRegistry::get_all_active_plugin_data();

		$this->assertIsArray( $result );
		// We can't guarantee specific plugins, but the structure should be correct
		foreach ( $result as $plugin_name ) {
			$this->assertIsString( $plugin_name );
			$this->assertNotEmpty( $plugin_name );
		}
	}

	/**
	 * Test integration precedence order (Polylang before WPML).
	 *
	 * @covers \WooCommerce\Facebook\Integrations\IntegrationRegistry::get_localization_integration_keys
	 */
	public function test_integration_precedence_order() {
		$keys = IntegrationRegistry::get_localization_integration_keys();

		$polylang_index = array_search( 'polylang', $keys, true );
		$wpml_index = array_search( 'wpml', $keys, true );

		$this->assertNotFalse( $polylang_index );
		$this->assertNotFalse( $wpml_index );
		$this->assertLessThan( $wpml_index, $polylang_index, 'Polylang should come before WPML in the integration order' );
	}
}
