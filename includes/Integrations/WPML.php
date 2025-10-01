<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Integrations;

/**
 * WPML integration for Facebook for WooCommerce.
 *
 * Handles integration with the WPML multilingual plugin to manage
 * product synchronization based on language settings.
 *
 */
class WPML extends Abstract_Localization_Integration {

	use Facebook_Fields_Translation_Trait;

	/**
	 * Get the plugin file name
	 *
	 * @return string
	 */
	public function get_plugin_file_name(): string {
		return 'sitepress-multilingual-cms/sitepress.php';
	}

	/**
	 * Get the plugin name
	 *
	 * @return string
	 */
	public function get_plugin_name(): string {
		return 'WPML';
	}

	/**
	 * Check if WPML is active and functions are available
	 *
	 * @return bool
	 */
	public function is_plugin_active(): bool {

		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
			return false;
		}

		// Check for required constants
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
			return false;
		}

		// For basic detection, we don't require the full sitepress object
		// This allows the integration to be detected even if WPML isn't fully initialized
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

		// Use WPML filter to get active languages
		$languages = apply_filters( 'wpml_active_languages', null );
		if ( is_array( $languages ) ) {
			$locales = [];
			foreach ( $languages as $language_data ) {
				// Use default_locale if available, fallback to language code
				if ( isset( $language_data['default_locale'] ) && ! empty( $language_data['default_locale'] ) ) {
					$locales[] = $language_data['default_locale'];
				} elseif ( isset( $language_data['code'] ) ) {
					$locales[] = $language_data['code'];
				}
			}
			return $locales;
		}

		return [];
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

		// Use WPML filter to get default language
		$default_code = apply_filters( 'wpml_default_language', null );

		if ( ! $default_code ) {
			return null;
		}

		// Get the full locale for the default language
		$languages = apply_filters( 'wpml_active_languages', null );
		if ( is_array( $languages ) && isset( $languages[ $default_code ] ) ) {
			$language_data = $languages[ $default_code ];
			if ( isset( $language_data['default_locale'] ) && ! empty( $language_data['default_locale'] ) ) {
				return $language_data['default_locale'];
			}
		}

		// Fallback to the short code if no locale is found
		return $default_code;
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

		// Use WPML filter to get current language
		$current = apply_filters( 'wpml_current_language', null );

		// Try ICL_LANGUAGE_CODE constant as fallback
		if ( ! $current && defined( 'ICL_LANGUAGE_CODE' ) ) {
			$current = ICL_LANGUAGE_CODE;
		}

		return $current ?: null;
	}

	/**
	 * Get language information for a specific language code
	 *
	 * @param string $language_code Language code
	 * @return array|null Language information or null if not found
	 */
	public function get_language_info( string $language_code ): ?array {
		if ( ! $this->is_plugin_active() ) {
			return null;
		}

		// Use WPML filter to get active languages
		$languages = apply_filters( 'wpml_active_languages', null );

		if ( ! is_array( $languages ) || ! isset( $languages[ $language_code ] ) ) {
			return null;
		}

		return $languages[ $language_code ];
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
	 * Get translation IDs for a product with comprehensive details
	 *
	 * @param int $product_id Product ID
	 * @return array Array of translation data with id, title, and slug for each language
	 */
	public function get_product_translations( int $product_id ): array {
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
			wc_get_logger()->debug( 'WPML is not active.', [ 'source' => 'wpml-helper' ] );
			return [];
		}

		// Get TRID for the product
		$trid = apply_filters( 'wpml_element_trid', null, $product_id, 'post_product' );
		if ( ! $trid ) {
			wc_get_logger()->debug( "No TRID found for product ID $product_id", [ 'source' => 'wpml-helper' ] );
			return [];
		}

		// Get translations
		$translations = apply_filters( 'wpml_get_element_translations', null, $trid, 'post_product' );

		if ( empty( $translations ) ) {
			wc_get_logger()->debug( "No translations found for product ID $product_id", [ 'source' => 'wpml-helper' ] );
			return [];
		}

		$output = [];
		foreach ( $translations as $lang_code => $translation ) {
			$output[ $lang_code ] = [
				'id'    => $translation->element_id,
				'title' => get_the_title( $translation->element_id ),
				'slug'  => get_post_field( 'post_name', $translation->element_id ),
			];
		}
		return $output;
	}

	/**
	 * Get WPML version
	 *
	 * @return string|null WPML version or null if not available
	 */
	public function get_wpml_version(): ?string {
		if ( ! $this->is_plugin_active() ) {
			return null;
		}

		if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
			return ICL_SITEPRESS_VERSION;
		}

		return null;
	}

	/**
	 * Check if Translation Management is available
	 *
	 * @return bool True if Translation Management is available
	 */
	public function has_translation_management(): bool {
		return defined( 'WPML_TM_VERSION' ) || class_exists( 'WPML_Translation_Management' );
	}

	/**
	 * Check if String Translation is available
	 *
	 * @return bool True if String Translation is available
	 */
	public function has_string_translation(): bool {
		return class_exists( 'WPML_String_Translation' );
	}

	/**
	 * Get products from the default language
	 *
	 * Uses WPML's API to find products that are in the default language.
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

		// Get the WPML language code for the default language
		$wpml_languages = apply_filters( 'wpml_active_languages', null );
		$default_language_code = null;

		if ( is_array( $wpml_languages ) ) {
			foreach ( $wpml_languages as $code => $language_data ) {
				$locale = $language_data['default_locale'] ?? $code;
				if ( $locale === $default_language_locale ) {
					$default_language_code = $code;
					break;
				}
			}
		}

		// Fallback: if we can't find the mapping, try using the locale as the code
		if ( ! $default_language_code ) {
			$default_language_code = $default_language_locale;
		}

		// Get published products - use a larger batch size to account for translations
		// We need to get more products initially because some will be filtered out
		$batch_size = max( $limit * 3, 50 ); // Get 3x the requested amount or minimum 50
		$args = [
			'post_type' => 'product',
			'post_status' => 'publish',
			'posts_per_page' => $batch_size,
			'offset' => $offset,
			'fields' => 'ids',
		];

		$all_products = get_posts( $args );
		$default_language_products = [];

		foreach ( $all_products as $product_id ) {
			// Use WPML filter to check if this product is in the default language
			$product_language = apply_filters( 'wpml_post_language_details', null, $product_id );

			if ( $product_language && isset( $product_language['language_code'] ) ) {
				// Only include products that are in the default language (compare WPML codes)
				if ( $product_language['language_code'] === $default_language_code ) {
					$default_language_products[] = $product_id;

					// Stop when we have enough products
					if ( count( $default_language_products ) >= $limit ) {
						break;
					}
				}
			}
		}

		return $default_language_products;
	}

	/**
	 * Get detailed translation information for a product
	 *
	 * Uses WPML's API to get comprehensive translation data including
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

		// Get the mapping between full locales and WPML language codes
		$wpml_languages = apply_filters( 'wpml_active_languages', null );
		if ( ! is_array( $wpml_languages ) ) {
			return $details;
		}

		$locale_to_code_map = [];
		$code_to_locale_map = [];
		foreach ( $wpml_languages as $code => $language_data ) {
			$locale = $language_data['default_locale'] ?? $code;
			$locale_to_code_map[ $locale ] = $code;
			$code_to_locale_map[ $code ] = $locale;
		}

		$languages = $this->get_available_languages(); // This now returns full locales
		$default_language = $this->get_default_language(); // This now returns full locale

		foreach ( $languages as $full_locale ) {
			// Skip the default language
			if ( $full_locale === $default_language ) {
				continue;
			}

			// Get the WPML language code for this locale
			$wpml_code = $locale_to_code_map[ $full_locale ] ?? $full_locale;

			// Get translated product ID using the WPML language code
			$translated_id = apply_filters( 'wpml_object_id', $product_id, 'post', false, $wpml_code );

			if ( $translated_id && $translated_id !== $product_id ) {
				// Store using the full locale as the key
				$details['translations'][ $full_locale ] = $translated_id;

				// Get translation status using WPML's API with the WPML code
				$translation_status = apply_filters( 'wpml_translation_status', null, $product_id, $wpml_code );
				$details['translation_status'][ $full_locale ] = $translation_status;

				// Get which fields are translated
				$details['translated_fields'][ $full_locale ] = $this->get_translated_fields( $product_id, $translated_id );
			}
		}

		return $details;
	}

	/**
	 * Get availability data for telemetry reporting
	 *
	 * Extends the base method to include WPML-specific features.
	 *
	 * @return array Integration availability data
	 */
	public function get_availability_data(): array {
		$data = parent::get_availability_data();

		if ( $this->is_plugin_active() ) {
			$data['features'] = [
				'translation_management' => $this->has_translation_management(),
				'string_translation' => $this->has_string_translation(),
			];

			$data['languages'] = $this->get_available_languages();
			$data['default_language'] = $this->get_default_language();
		}

		return $data;
	}

}
