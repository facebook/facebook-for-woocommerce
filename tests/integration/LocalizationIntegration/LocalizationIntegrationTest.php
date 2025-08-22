<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Integration\LocalizationIntegration;

use WooCommerce\Facebook\Integrations\Abstract_Localization_Integration;
use WooCommerce\Facebook\Tests\Integration\IntegrationTestCase;

/**
 * Generic integration tests for localization functionality.
 *
 * Tests the complete integration between Facebook for WooCommerce and any
 * supported localization plugin (Polylang, WPML, etc.) with the actual
 * plugin activated through WordPress mechanisms.
 *
 * @group localization
 */
class LocalizationIntegrationTest extends IntegrationTestCase {

	/**
	 * Localization integration instance
	 *
	 * @var Abstract_Localization_Integration
	 */
	private $localization_integration;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();

		// Auto-detect which localization plugin is available
		$this->localization_integration = LocalizationIntegrationFactory::create();

		// Skip tests if no supported localization plugin is installed
		if ( ! $this->localization_integration ) {
			$available_plugins = $this->get_available_plugins_message();
			$this->markTestSkipped(
				"No supported localization plugin is installed and active. {$available_plugins}"
			);
		}
	}

	/**
	 * Get message about available plugins for skip message
	 */
	private function get_available_plugins_message(): string {
		$available_plugins = LocalizationIntegrationFactory::detect_installed_plugins();

		if ( empty( $available_plugins ) ) {
			return 'Supported plugins: Polylang, WPML. Install one and run tests again.';
		}

		$messages = [];
		foreach ( $available_plugins as $plugin ) {
			$status = $plugin['active'] ? 'active' : 'installed but not active';
			$messages[] = "{$plugin['name']} ({$status})";
		}

		return 'Found: ' . implode( ', ', $messages );
	}

	/**
	 * Test plugin detection mechanisms
	 */
	public function test_plugin_detection_mechanisms(): void {
		// Ensure plugin.php functions are loaded
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_file = $this->localization_integration->get_plugin_file_name();
		$plugin_name = $this->localization_integration->get_plugin_name();

		$wordpress_detection = is_plugin_active( $plugin_file );
		$integration_detection = $this->localization_integration->is_plugin_active();

		$this->assertTrue(
			$wordpress_detection,
			"WordPress should detect {$plugin_name} as active"
		);
		$this->assertTrue(
			$integration_detection,
			"Integration should detect {$plugin_name} as active"
		);

		// Test Facebook WooCommerce detection (if available)
		if ( function_exists( 'facebook_for_woocommerce' ) ) {
			// Extract just the filename for Facebook WooCommerce detection
			$filename = basename( $plugin_file );
			$facebook_detection = facebook_for_woocommerce()->is_plugin_active( $filename );

			$this->assertTrue(
				$facebook_detection,
				"Facebook WooCommerce should detect {$plugin_name} as active"
			);
		}
	}

	/**
	 * Test integration methods work and return expected types
	 */
	public function test_integration_methods_work(): void {
		$default_language = $this->localization_integration->get_default_language();
		$available_languages = $this->localization_integration->get_available_languages();

		$this->assertTrue(
			is_string( $default_language ),
			'get_default_language should return string'
		);

		$this->assertIsArray(
			$available_languages,
			'get_available_languages should return array'
		);

		// Verify languages are not empty
		$this->assertNotEmpty( $default_language, 'Default language should not be empty' );
		$this->assertNotEmpty( $available_languages, 'Available languages should not be empty' );
	}

}
