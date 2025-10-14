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

	use Facebook_Fields_Translation_Trait;

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

		// Get languages with full details to extract locales
		$languages = pll_languages_list( [ 'fields' => '' ] ); // Get full language objects
		if ( ! is_array( $languages ) ) {
			return [];
		}

		$locales = [];
		foreach ( $languages as $language ) {
			// Use locale if available, fallback to slug
			if ( isset( $language->locale ) && ! empty( $language->locale ) ) {
				$locales[] = $language->locale;
			} elseif ( isset( $language->slug ) ) {
				$locales[] = $language->slug;
			}
		}

		return $locales;
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

		$default_slug = pll_default_language();
		if ( ! $default_slug ) {
			return null;
		}

		// Get the full locale for the default language
		$languages = pll_languages_list( [ 'fields' => '' ] );
		if ( is_array( $languages ) ) {
			foreach ( $languages as $language ) {
				if ( isset( $language->slug ) && $language->slug === $default_slug ) {
					// Return locale if available, fallback to slug
					if ( isset( $language->locale ) && ! empty( $language->locale ) ) {
						return $language->locale;
					}
					return $language->slug;
				}
			}
		}

		// Fallback to the slug if no locale is found
		return $default_slug;
	}

	/**
	 * Check if Polylang Pro features are available
	 *
	 * @return bool True if Polylang Pro is active
	 */
	public function is_pro_version(): bool {
		return defined( 'POLYLANG_PRO' ) && POLYLANG_PRO;
	}

	/**
	 * Get products from the default language
	 *
	 * Uses Polylang's API to find products that are in the default language.
	 * This ensures we're working with the original products, not translations.
	 *
	 * @param int $limit Maximum number of products to return
	 * @param int $offset Offset for pagination
	 * @return array Array of product IDs from the default language
	 */
	public function get_products_from_default_language( int $limit = 10, int $offset = 0 ): array {
		if ( ! $this->is_plugin_active() ) {
			return [];
		}

		$default_language_locale = $this->get_default_language(); // This now returns full locale
		if ( ! $default_language_locale ) {
			return [];
		}

		// Get the Polylang language slug for the default language
		$polylang_languages = pll_languages_list( [ 'fields' => '' ] );
		$default_language_slug = null;

		if ( is_array( $polylang_languages ) ) {
			foreach ( $polylang_languages as $language ) {
				$locale = $language->locale ?? $language->slug;
				if ( $locale === $default_language_locale ) {
					$default_language_slug = $language->slug;
					break;
				}
			}
		}

		// Fallback: if we can't find the mapping, try using the locale as the slug
		if ( ! $default_language_slug ) {
			$default_language_slug = $default_language_locale;
		}

		// Get published products
		$args = [
			'post_type' => 'product',
			'post_status' => 'publish',
			'posts_per_page' => $limit,
			'offset' => $offset,
			'fields' => 'ids',
		];

		$all_products = get_posts( $args );
		$default_language_products = [];

		foreach ( $all_products as $product_id ) {
			// Use Polylang function to check if this product is in the default language
			$product_language = pll_get_post_language( $product_id );

			if ( $product_language ) {
				// Only include products that are in the default language
				if ( $product_language === $default_language_slug ) {
					$default_language_products[] = $product_id;
				}
			}
		}

		return $default_language_products;
	}

	/**
	 * Get detailed translation information for a product
	 *
	 * Uses Polylang's API to get comprehensive translation data including
	 * which fields are translated and translation status.
	 *
	 * @param int $product_id Product ID (should be from default language)
	 * @return array Detailed translation information
	 */
	public function get_product_translation_details( int $product_id ): array {
		if ( ! $this->is_plugin_active() ) {
			return [];
		}

		$details = [
			'product_id' => $product_id,
			'default_language' => $this->get_default_language(),
			'translations' => [],
			'translation_status' => []
		];

		// Get the mapping between full locales and Polylang language slugs
		$polylang_languages = pll_languages_list( [ 'fields' => '' ] );
		if ( ! is_array( $polylang_languages ) ) {
			return $details;
		}

		$locale_to_slug_map = [];
		$slug_to_locale_map = [];
		foreach ( $polylang_languages as $language ) {
			$locale = $language->locale ?? $language->slug;
			$locale_to_slug_map[ $locale ] = $language->slug;
			$slug_to_locale_map[ $language->slug ] = $locale;
		}

		$languages = $this->get_available_languages(); // This now returns full locales
		$default_language = $this->get_default_language(); // This now returns full locale

		foreach ( $languages as $full_locale ) {
			// Skip the default language
			if ( $full_locale === $default_language ) {
				continue;
			}

			// Get the Polylang language slug for this locale
			$polylang_slug = $locale_to_slug_map[ $full_locale ] ?? $full_locale;

			// Get translated product ID using Polylang function
			$translated_id = pll_get_post( $product_id, $polylang_slug );

			if ( $translated_id && $translated_id !== $product_id ) {
				// Store using the full locale as the key
				$details['translations'][ $full_locale ] = $translated_id;

				// Polylang doesn't have built-in translation status like WPML
				// We'll mark as 'complete' if translation exists
				$details['translation_status'][ $full_locale ] = 'complete';

				// Get which fields are translated
				$details['translated_fields'][ $full_locale ] = $this->get_translated_fields( $product_id, $translated_id, $full_locale );
			}
		}

		return $details;
	}

	/**
	 * Get availability data for telemetry reporting
	 *
	 * Extends the base method to include Polylang-specific features.
	 *
	 * @return array Integration availability data
	 */
	public function get_availability_data(): array {
		$data = parent::get_availability_data();

		if ( $this->is_plugin_active() ) {
			$data['features'] = [
				'is_pro_version' => $this->is_pro_version(),
			];

			$data['languages'] = $this->get_available_languages();
			$data['default_language'] = $this->get_default_language();
		}

		return $data;
	}
}
