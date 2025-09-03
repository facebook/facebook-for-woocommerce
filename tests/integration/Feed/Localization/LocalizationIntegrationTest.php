<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Integration\Feed\Localization;

use WooCommerce\Facebook\Feed\Localization\LanguageFeedData;
use WooCommerce\Facebook\Feed\Localization\LanguageOverrideFeed;
use WooCommerce\Facebook\Feed\FeedManager;
use WooCommerce\Facebook\Integrations\IntegrationRegistry;
use WooCommerce\Facebook\Tests\Integration\IntegrationTestCase;

/**
 * Integration tests for localization feed functionality
 *
 * @since 3.6.0
 */
class LocalizationIntegrationTest extends IntegrationTestCase {

	/**
	 * @var string|null Expected plugin to be active for this test run
	 */
	private static $expected_plugin = null;

	/**
	 * Set up the test class
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		// Check for command line argument or environment variable to specify expected plugin
		self::$expected_plugin = self::getExpectedPlugin();

		// Output which plugin we're testing
		if ( self::$expected_plugin ) {
			echo "\n=== Testing with " . strtoupper( self::$expected_plugin ) . " plugin ===\n";
		} else {
			echo "\n=== Testing without specific plugin requirement ===\n";
		}
	}

	/**
	 * Get the expected plugin from command line arguments or environment variables
	 *
	 * @return string|null
	 */
	private static function getExpectedPlugin(): ?string {
		// Check environment variable first
		$env_plugin = getenv( 'FB_TEST_PLUGIN' );
		if ( $env_plugin && in_array( strtolower( $env_plugin ), [ 'wpml', 'polylang' ], true ) ) {
			return strtolower( $env_plugin );
		}

		// Check command line arguments
		global $argv;
		if ( isset( $argv ) && is_array( $argv ) ) {
			foreach ( $argv as $arg ) {
				if ( strpos( $arg, '--plugin=' ) === 0 ) {
					$plugin = substr( $arg, 9 );
					if ( in_array( strtolower( $plugin ), [ 'wpml', 'polylang' ], true ) ) {
						return strtolower( $plugin );
					}
				}
			}
		}

		return null;
	}

	/**
	 * Test that the expected plugin is active if specified
	 */
	public function test_expected_plugin_is_active() {
		if ( ! self::$expected_plugin ) {
			$this->markTestSkipped( 'No specific plugin expected for this test run' );
			return;
		}

		$detected_plugin = $this->detectActiveLocalizationPlugin();

		$this->assertEquals(
			self::$expected_plugin,
			$detected_plugin,
			sprintf(
				'Expected %s plugin to be active, but %s was detected. Make sure you\'re using the correct bootstrap file.',
				strtoupper( self::$expected_plugin ),
				$detected_plugin ? strtoupper( $detected_plugin ) : 'no plugin'
			)
		);

		echo "\n✓ Confirmed " . strtoupper( self::$expected_plugin ) . " plugin is active\n";
	}

	/**
	 * Detect which localization plugin is currently active using the IntegrationRegistry
	 *
	 * @return string|null 'wpml', 'polylang', or null if none detected
	 */
	private function detectActiveLocalizationPlugin(): ?string {
		// Use the IntegrationRegistry to check availability
		$integrations = IntegrationRegistry::get_all_localization_integrations();

		foreach ( $integrations as $plugin_key => $integration ) {
			// Check if the integration is available (active + properly configured)
			if ( $integration->is_available() ) {
				return $plugin_key;
			}
		}

		return null;
	}


	/**
	 * Test integration registry functionality
	 */
	public function test_integration_registry() {
		// Test getting all localization integration keys
		$integration_keys = IntegrationRegistry::get_localization_integration_keys();
		$this->assertIsArray( $integration_keys );
		$this->assertContains( 'wpml', $integration_keys );
		$this->assertContains( 'polylang', $integration_keys );

		// Test getting specific integration
		$wpml_integration = IntegrationRegistry::get_localization_integration( 'wpml' );
		$this->assertInstanceOf(
			\WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::class,
			$wpml_integration
		);

		$polylang_integration = IntegrationRegistry::get_localization_integration( 'polylang' );
		$this->assertInstanceOf(
			\WooCommerce\Facebook\Integrations\Abstract_Localization_Integration::class,
			$polylang_integration
		);

		// Test getting all integrations
		$all_integrations = IntegrationRegistry::get_all_localization_integrations();
		$this->assertIsArray( $all_integrations );
		$this->assertArrayHasKey( 'wpml', $all_integrations );
		$this->assertArrayHasKey( 'polylang', $all_integrations );

		// Test availability data
		$availability_data = IntegrationRegistry::get_all_localization_availability_data();
		$this->assertIsArray( $availability_data );
		$this->assertArrayHasKey( 'wpml', $availability_data );
		$this->assertArrayHasKey( 'polylang', $availability_data );
	}

	/**
	 * Test basic integration properties and metadata
	 */
	public function test_integration_basic_properties() {
		$active_integration = $this->getActiveIntegration();
		if ( ! $active_integration ) {
			$this->markTestSkipped( 'No active localization integration found' );
			return;
		}

		// Test plugin file name
		$plugin_file = $active_integration->get_plugin_file_name();
		$this->assertIsString( $plugin_file );
		$this->assertNotEmpty( $plugin_file );
		$this->assertStringContainsString( '/', $plugin_file, 'Plugin file should contain directory separator' );

		// Test plugin name
		$plugin_name = $active_integration->get_plugin_name();
		$this->assertIsString( $plugin_name );
		$this->assertNotEmpty( $plugin_name );

		// Test plugin installation status
		$is_installed = $active_integration->is_plugin_installed();
		$this->assertTrue( $is_installed, 'Active integration should be installed' );

		// Test plugin active status
		$is_active = $active_integration->is_plugin_active();
		$this->assertTrue( $is_active, 'Active integration should be active' );

		// Test plugin version
		$version = $active_integration->get_plugin_version();
		if ( $version !== null ) {
			$this->assertIsString( $version );
			$this->assertNotEmpty( $version );
		}
	}

	/**
	 * Test language detection and configuration
	 */
	public function test_language_detection() {
		$active_integration = $this->getActiveIntegration();
		if ( ! $active_integration ) {
			$this->markTestSkipped( 'No active localization integration found' );
			return;
		}

		// Test available languages
		$available_languages = $active_integration->get_available_languages();
		$this->assertIsArray( $available_languages );
		$this->assertNotEmpty( $available_languages, 'Should have at least one language available' );

		// Test default language
		$default_language = $active_integration->get_default_language();
		$this->assertIsString( $default_language );
		$this->assertNotEmpty( $default_language );

		// Test current language
		$current_language = $active_integration->get_current_language();
		$this->assertIsString( $current_language );
		$this->assertNotEmpty( $current_language );

		// Test that default language is in available languages
		$this->assertContains(
			$default_language,
			$available_languages,
			'Default language should be in available languages list'
		);
	}

	/**
	 * Test integration availability logic
	 */
	public function test_integration_availability() {
		$active_integration = $this->getActiveIntegration();
		if ( ! $active_integration ) {
			$this->markTestSkipped( 'No active localization integration found' );
			return;
		}

		// Test availability (should be true for active integration)
		$is_available = $active_integration->is_available();
		$this->assertTrue( $is_available, 'Active integration should be available' );

		// Test availability data structure
		$availability_data = $active_integration->get_availability_data();
		$this->assertIsArray( $availability_data );

		// Check required fields
		$this->assertArrayHasKey( 'plugin_name', $availability_data );
		$this->assertArrayHasKey( 'plugin_file', $availability_data );
		$this->assertArrayHasKey( 'is_installed', $availability_data );
		$this->assertArrayHasKey( 'is_active', $availability_data );

		// Verify data types and values
		$this->assertIsString( $availability_data['plugin_name'] );
		$this->assertIsString( $availability_data['plugin_file'] );
		$this->assertTrue( $availability_data['is_installed'] );
		$this->assertTrue( $availability_data['is_active'] );
	}

	/**
	 * Test product retrieval from default language
	 */
	public function test_get_products_from_default_language() {
		$active_integration = $this->getActiveIntegration();
		if ( ! $active_integration ) {
			$this->markTestSkipped( 'No active localization integration found' );
			return;
		}

		// Test getting products from default language (without creating products to avoid rollout switches)
		$default_products = $active_integration->get_products_from_default_language( 10, 0 );
		$this->assertIsArray( $default_products );

		// Test with limit
		$limited_products = $active_integration->get_products_from_default_language( 2, 0 );
		$this->assertIsArray( $limited_products );
		$this->assertLessThanOrEqual( 2, count( $limited_products ), 'Should respect limit parameter' );

		// Test with offset
		$offset_products = $active_integration->get_products_from_default_language( 10, 1 );
		$this->assertIsArray( $offset_products );
	}

	/**
	 * Test product translation details with real product
	 */
	public function test_get_product_translation_details() {
		$active_integration = $this->getActiveIntegration();
		if ( ! $active_integration ) {
			$this->markTestSkipped( 'No active localization integration found' );
			return;
		}

		// Create a real test product using IntegrationTestCase helper
		$product = $this->create_simple_product([
			'name' => 'Test Product for Translation',
			'regular_price' => '19.99',
			'status' => 'publish',
			'catalog_visibility' => 'visible'
		]);

		// Test getting translation details
		$translation_details = $active_integration->get_product_translation_details( $product->get_id() );
		$this->assertIsArray( $translation_details );

		// Check required structure
		$this->assertArrayHasKey( 'product_id', $translation_details );
		$this->assertArrayHasKey( 'default_language', $translation_details );
		$this->assertArrayHasKey( 'translations', $translation_details );
		$this->assertArrayHasKey( 'translation_status', $translation_details );

		// Verify data types
		$this->assertIsInt( $translation_details['product_id'] );
		$this->assertIsString( $translation_details['default_language'] );
		$this->assertIsArray( $translation_details['translations'] );
		$this->assertIsArray( $translation_details['translation_status'] );

		// Verify product ID matches
		$this->assertEquals( $product->get_id(), $translation_details['product_id'] );

		// Verify default language is not empty
		$this->assertNotEmpty( $translation_details['default_language'], 'Default language should not be empty' );
	}

	/**
	 * Test integration with multiple languages
	 */
	public function test_multiple_language_support() {
		$active_integration = $this->getActiveIntegration();
		if ( ! $active_integration ) {
			$this->markTestSkipped( 'No active localization integration found' );
			return;
		}

		$available_languages = $active_integration->get_available_languages();

		if ( count( $available_languages ) < 2 ) {
			$this->markTestSkipped( 'Multiple languages not configured for testing' );
			return;
		}

		// Test that we can get language information for each available language
		foreach ( $available_languages as $language_code ) {
			$this->assertIsString( $language_code );
			$this->assertNotEmpty( $language_code );
		}

		// Test default language is properly set
		$default_language = $active_integration->get_default_language();
		$this->assertContains(
			$default_language,
			$available_languages,
			'Default language should be in available languages'
		);
	}

	/**
	 * Get the currently active integration for testing
	 *
	 * @return \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration|null
	 */
	private function getActiveIntegration(): ?\WooCommerce\Facebook\Integrations\Abstract_Localization_Integration {
		$integrations = IntegrationRegistry::get_all_localization_integrations();

		foreach ( $integrations as $integration ) {
			if ( $integration->is_available() ) {
				return $integration;
			}
		}

		return null;
	}



}
