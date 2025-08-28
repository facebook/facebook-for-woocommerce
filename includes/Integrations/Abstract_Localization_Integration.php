<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Integrations;

/**
 * Abstract base class for localization plugin integrations.
 *
 * Provides a common interface for integrating with various WordPress
 * localization/multilingual plugins like Polylang, WPML, etc.
 *
 */
abstract class Abstract_Localization_Integration {

	/**
	 * Get the plugin file name (relative to plugins directory)
	 *
	 * @return string Plugin file name (e.g., 'polylang/polylang.php')
	 */
	abstract public function get_plugin_file_name(): string;

	/**
	 * Get the human-readable plugin name
	 *
	 * @return string Plugin name (e.g., 'Polylang')
	 */
	abstract public function get_plugin_name(): string;

	/**
	 * Check if the localization plugin is active and available
	 *
	 * @return bool True if plugin is active and functions are available
	 */
	abstract public function is_plugin_active(): bool;

	/**
	 * Check if the integration is available (alias for is_plugin_active)
	 *
	 * @return bool True if integration is available
	 */
	public function is_available(): bool {
		return $this->is_plugin_active();
	}

	/**
	 * Get all available languages
	 *
	 * @return array Array of language data
	 */
	abstract public function get_available_languages(): array;

	/**
	 * Get the default language code
	 *
	 * @return string|null Default language code or null if not set
	 */
	abstract public function get_default_language(): ?string;

	/**
	 * Get the current language code
	 *
	 * @return string|null Current language code or null if not available
	 */
	abstract public function get_current_language(): ?string;


	/**
	 * Check if the plugin is installed (but not necessarily active)
	 *
	 * @return bool True if plugin files exist
	 */
	public function is_plugin_installed(): bool {
		$plugin_file = WP_PLUGIN_DIR . '/' . $this->get_plugin_file_name();
		return file_exists( $plugin_file );
	}

	/**
	 * Get plugin version if available
	 *
	 * @return string|null Plugin version or null if not available
	 */
	public function get_plugin_version(): ?string {
		if ( ! $this->is_plugin_active() ) {
			return null;
		}

		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $this->get_plugin_file_name() );
		return $plugin_data['Version'] ?? null;
	}

	/**
	 * Get products from the default language
	 *
	 * Default implementation that can be overridden by specific integrations.
	 * This method should return products that are in the default language only.
	 *
	 * @param int $limit Maximum number of products to return
	 * @param int $offset Offset for pagination
	 * @return array Array of product IDs from the default language
	 */
	public function get_products_from_default_language( int $limit = 10, int $offset = 0 ): array {
		// Default implementation - just return regular products
		// Specific integrations should override this method
		$args = [
			'post_type' => 'product',
			'post_status' => 'publish',
			'posts_per_page' => $limit,
			'offset' => $offset,
			'fields' => 'ids',
		];

		return get_posts( $args );
	}

	/**
	 * Get detailed translation information for a product
	 *
	 * Default implementation that can be overridden by specific integrations.
	 *
	 * @param int $product_id Product ID (should be from default language)
	 * @return array Detailed translation information
	 */
	public function get_product_translation_details( int $product_id ): array {
		// Default implementation - return basic structure
		// Specific integrations should override this method
		return [
			'product_id' => $product_id,
			'default_language' => $this->get_default_language(),
			'translations' => [],
			'translation_status' => [],
			'translated_fields' => []
		];
	}

	/**
	 * Get availability data for telemetry reporting
	 *
	 * Provides standardized data collection for integration availability logging.
	 * This method is used by the IntegrationAvailabilityLogger to collect
	 * telemetry data about which integrations are available and active.
	 *
	 * @return array Integration availability data
	 */
	public function get_availability_data(): array {
		$data = [
			'plugin_name' => $this->get_plugin_name(),
			'plugin_file' => $this->get_plugin_file_name(),
			'is_installed' => $this->is_plugin_installed(),
			'is_active' => $this->is_plugin_active(),
		];

		// Add version if available
		$version = $this->get_plugin_version();
		if ( $version ) {
			$data['version'] = $version;
		}

		return $data;
	}
}
