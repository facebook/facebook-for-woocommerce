<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Integrations;

/**
 * Polylang integration for Facebook for WooCommerce.
 *
 * Handles integration with the Polylang multilingual plugin to manage
 * product synchronization based on language settings.
 *
 */
class Polylang extends Abstract_Localization_Integration {

	/**
	 * Get the plugin file name
	 *
	 * @return string
	 */
	public function get_plugin_file_name(): string {
		return 'polylang/polylang.php';
	}

	/**
	 * Get the plugin name
	 *
	 * @return string
	 */
	public function get_plugin_name(): string {
		return 'Polylang';
	}

	/**
	 * Check if Polylang is active and functions are available
	 *
	 * @return bool
	 */
	public function is_plugin_active(): bool {

		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active( 'polylang/polylang.php' ) ) {
			return false;
		}

		// Secondary check: Ensure core functions are available
		$required_functions = [
			'pll_get_post_language',
			'pll_default_language',
			'pll_languages_list',
			'pll_current_language',
			'pll_get_post',
			'pll_get_post_translations',
			'pll_save_post_translations'
		];

		foreach ( $required_functions as $function ) {
			if ( ! function_exists( $function ) ) {
				return false;
			}
		}

		if ( ! defined( 'POLYLANG_VERSION' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get all available languages
	 *
	 * @return array Array of language data
	 */
	public function get_available_languages(): array {
		if ( ! $this->is_plugin_active() ) {
			return [];
		}

		$languages = pll_languages_list();
		return is_array( $languages ) ? $languages : [];
	}

	/**
	 * Get the default language code
	 *
	 * @return string|null Default language code or null if not set
	 */
	public function get_default_language(): ?string {
		if ( ! $this->is_plugin_active() ) {
			return null;
		}

		$default = pll_default_language();
		return $default ?: null;
	}

	/**
	 * Get the current language code
	 *
	 * @return string|null Current language code or null if not available
	 */
	public function get_current_language(): ?string {
		if ( ! $this->is_plugin_active() ) {
			return null;
		}

		$current = pll_current_language();
		return $current ?: null;
	}

	/**
	 * Get language information for a specific language code
	 *
	 * @param string $language_code Language code
	 * @return array|null Language information or null if not found
	 */
	public function get_language_info( string $language_code ): ?array {
		$languages = $this->get_available_languages();

		foreach ( $languages as $language ) {
			if ( isset( $language['slug'] ) && $language['slug'] === $language_code ) {
				return $language;
			}
		}

		return null;
	}

	/**
	 * Check if a specific language is available
	 *
	 * @param string $language_code Language code to check
	 * @return bool True if language is available
	 */
	public function is_language_available( string $language_code ): bool {
		return null !== $this->get_language_info( $language_code );
	}

	/**
	 * Get translation IDs for a product
	 *
	 * @param int $product_id Product ID
	 * @return array Array of translation IDs keyed by language code
	 */
	public function get_product_translations( int $product_id ): array {
		if ( ! $this->is_plugin_active() || ! function_exists( 'pll_get_post_translations' ) ) {
			return [];
		}

		$translations = pll_get_post_translations( $product_id );
		return is_array( $translations ) ? $translations : [];
	}

	/**
	 * Check if Polylang Pro features are available
	 *
	 * @return bool True if Polylang Pro is active
	 */
	public function is_pro_version(): bool {
		return defined( 'POLYLANG_PRO' ) && POLYLANG_PRO;
	}
}
