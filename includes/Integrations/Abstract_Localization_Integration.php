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
	 * Log integration status for debugging
	 *
	 * @return array Status information
	 */
	public function get_status(): array {
		return [
			'plugin_name' => $this->get_plugin_name(),
			'plugin_file' => $this->get_plugin_file_name(),
			'is_installed' => $this->is_plugin_installed(),
			'is_active' => $this->is_plugin_active(),
			'version' => $this->get_plugin_version(),
			'default_language' => $this->get_default_language(),
			'available_languages' => $this->get_available_languages(),
		];
	}
}
