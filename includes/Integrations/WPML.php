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
			// Use WPML filter to check if this product is in the default language
			$product_language = apply_filters( 'wpml_post_language_details', null, $product_id );

			if ( $product_language && isset( $product_language['language_code'] ) ) {
				// Only include products that are in the default language (compare WPML codes)
				if ( $product_language['language_code'] === $default_language_code ) {
					$default_language_products[] = $product_id;
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
				'multicurrency' => $this->supports_multicurrency(),
			];

			$data['languages'] = $this->get_available_languages();
			$data['default_language'] = $this->get_default_language();
			$data['currencies'] = $this->get_available_currencies();
			$data['countries'] = $this->get_available_countries();
		}

		return $data;
	}

	/**
	 * Check if WPML multicurrency is available and enabled.
	 *
	 * @since 3.0.18
	 *
	 * @return bool
	 */
	public function supports_multicurrency(): bool {
		global $woocommerce_wpml;

		if ( ! $woocommerce_wpml || ! isset( $woocommerce_wpml->multi_currency ) ) {
			return false;
		}

		// Check if multicurrency is enabled in WPML settings
		$wcml_settings = get_option( '_wcml_settings', array() );
		return ! empty( $wcml_settings['enable_multi_currency'] );
	}

	/**
	 * Get all available currencies configured in WPML.
	 * Includes the default currency even if it has a rate of 0.
	 *
	 * @since 3.0.18
	 *
	 * @return array Array of currency codes and their settings
	 */
	public function get_available_currencies(): array {
		global $woocommerce_wpml;

		if ( ! $this->supports_multicurrency() ) {
			return array();
		}

		// Get active currencies from WPML (excludes default currency with rate 0)
		$active_currencies = $woocommerce_wpml->multi_currency->get_currencies();

		// Get all configured currencies from settings (includes default currency)
		$wcml_settings = get_option( '_wcml_settings', array() );
		if ( empty( $wcml_settings['currency_options'] ) ) {
			return $active_currencies;
		}

		$all_currencies = array();
		$default_currency = $this->get_default_currency();

		// Process all configured currencies
		foreach ( $wcml_settings['currency_options'] as $currency_code => $currency_data ) {
			// For the default currency, set rate to 1.0 if it's 0
			if ( $currency_code === $default_currency && ( $currency_data['rate'] === 0 || $currency_data['rate'] === '0' ) ) {
				$currency_data['rate'] = 1.0;
			}

			$all_currencies[ $currency_code ] = $currency_data;
		}

		return $all_currencies;
	}

	/**
	 * Get all available countries that have currency configurations.
	 *
	 * @since 3.0.18
	 *
	 * @return array Array of country codes
	 */
	public function get_available_countries(): array {
		$currencies = $this->get_available_currencies();
		$countries  = array();

		foreach ( $currencies as $currency_code => $currency_data ) {
			if ( ! empty( $currency_data['countries'] ) && is_array( $currency_data['countries'] ) ) {
				$countries = array_merge( $countries, $currency_data['countries'] );
			}
		}

		return array_unique( $countries );
	}

	/**
	 * Get the default currency for the site.
	 *
	 * @since 3.0.18
	 *
	 * @return string Currency code
	 */
	public function get_default_currency(): string {
		global $woocommerce_wpml;

		if ( ! $this->supports_multicurrency() ) {
			return get_woocommerce_currency();
		}

		return $woocommerce_wpml->multi_currency->get_default_currency();
	}

	/**
	 * Get the primary currency for a specific country.
	 * Uses WPML's internal priority system and location modes to determine the best currency.
	 *
	 * @since 3.0.18
	 *
	 * @param string $country_code Two-letter country code (e.g., 'GB', 'US')
	 * @return string|null Currency code or null if no currency found for country
	 */
	public function get_currency_for_country( string $country_code ): ?string {
		if ( ! $this->supports_multicurrency() ) {
			return null;
		}

		$config = $this->wcml_get_currency_config();

		foreach ( $config['priority'] as $currency_code ) {
			if ( ! isset( $config['currencies'][ $currency_code ] ) ) {
				continue;
			}

			$currency  = $config['currencies'][ $currency_code ];
			$mode      = $currency['location_mode'];
			$countries = $currency['countries'];

			// Check for "all countries" assignment
			if ( in_array( 'ALL', $countries, true ) ) {
				return $currency_code;
			}

			// Check include mode
			if ( $mode === 'include' && in_array( $country_code, $countries, true ) ) {
				return $currency_code;
			}

			// Check exclude mode
			if ( $mode === 'exclude' && ! in_array( $country_code, $countries, true ) ) {
				return $currency_code;
			}
		}

		return null; // no matching currency
	}

	/**
	 * Get WPML currency configuration with normalized structure.
	 * Reads WCML's internal settings and normalizes into a structured array.
	 *
	 * @since 3.0.18
	 *
	 * @return array Normalized currency configuration
	 */
	private function wcml_get_currency_config(): array {
		$currency_mode = get_option( 'wcml_currency_mode', 'site_language' );
		$config        = get_option( '_wcml_settings', [] );

		$mode = ( $currency_mode === 'by_location' ) ? 'country_based' : 'language_based';

		$currencies = [];
		$priorities = [];

		if ( isset( $config['currency_options'] ) && is_array( $config['currency_options'] ) ) {
			foreach ( $config['currency_options'] as $code => $data ) {
				$location_mode = $data['location_mode'] ?? 'include';
				$countries     = $data['countries'] ?? [];

				// Normalize "all countries" to ['ALL']
				if ( $location_mode === 'all' ) {
					$countries = ['ALL'];
				}

				$currencies[ $code ] = [
					'rate'          => $data['rate'] ?? 1,
					'location_mode' => $location_mode,
					'countries'     => $countries,
					'decimals'      => $data['num_decimals'] ?? 2,
					'rounding'      => $data['rounding'] ?? 'disabled',
					'rounding_step' => $data['rounding_increment'] ?? 1,
					'last_updated'  => $data['updated'] ?? null,
				];
			}
		}

		if ( isset( $config['currencies_order'] ) && is_array( $config['currencies_order'] ) ) {
			$priorities = $config['currencies_order'];
		}

		return [
			'mode'       => $mode,
			'currencies' => $currencies,
			'priority'   => $priorities,
		];
	}

	/**
	 * Get pricing information for a product in a specific currency.
	 *
	 * @since 3.0.18
	 *
	 * @param int    $product_id Product ID
	 * @param string $currency_code Currency code
	 * @return array|null Array with price information or null if not available
	 */
	public function get_product_pricing_for_currency( int $product_id, string $currency_code ): ?array {
		global $woocommerce_wpml;

		if ( ! $this->supports_multicurrency() ) {
			return null;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return null;
		}

		$currencies = $this->get_available_currencies();
		if ( ! isset( $currencies[ $currency_code ] ) ) {
			return null;
		}

		// Get the product's price in the specified currency
		$currency_data = $currencies[ $currency_code ];
		$exchange_rate = isset( $currency_data['rate'] ) ? (float) $currency_data['rate'] : 1.0;

		// Get the base price
		$base_price = $product->get_price();
		$sale_price = $product->get_sale_price();
		$regular_price = $product->get_regular_price();

		// Apply currency conversion
		$converted_price = $base_price * $exchange_rate;
		$converted_sale_price = $sale_price ? $sale_price * $exchange_rate : null;
		$converted_regular_price = $regular_price ? $regular_price * $exchange_rate : null;

		return array(
			'currency_code'    => $currency_code,
			'price'           => $converted_price,
			'sale_price'      => $converted_sale_price,
			'regular_price'   => $converted_regular_price,
			'exchange_rate'   => $exchange_rate,
			'currency_symbol' => isset( $currency_data['symbol'] ) ? $currency_data['symbol'] : $currency_code,
			'formatted_price' => wc_price( $converted_price, array( 'currency' => $currency_code ) ),
		);
	}

	/**
	 * Check if a product can have multiple currency pricing (multiple currencies are available).
	 * For country feed generation, we consider that any product can have country-specific pricing
	 * if multiple currencies are configured, regardless of exchange rates.
	 *
	 * @since 3.0.18
	 *
	 * @param int $product_id Product ID
	 * @return bool True if product can have multiple currency pricing
	 */
	public function product_has_multiple_currency_pricing( int $product_id ): bool {
		if ( ! $this->supports_multicurrency() ) {
			return false;
		}

		$currencies = $this->get_available_currencies();

		// If we have multiple currencies configured, then country feeds can be generated
		// regardless of whether the exchange rates are different
		return count( $currencies ) > 1;
	}


	/**
	 * Check if all prerequisites for country feeds are met.
	 * Country feeds work regardless of the WPML currency mode.
	 *
	 * @since 3.0.18
	 *
	 * @return array Prerequisites check results
	 */
	public function check_country_feed_prerequisites(): array {
		$results = array(
			'wpml_multicurrency_enabled' => false,
			'multiple_currencies' => false,
			'different_exchange_rates' => false,
			'all_met' => false,
			'issues' => array(),
			'warnings' => array(),
		);

		// Check 1: WPML Multicurrency enabled
		if ( ! $this->supports_multicurrency() ) {
			$results['issues'][] = 'WPML Multicurrency is not enabled';
			return $results;
		}
		$results['wpml_multicurrency_enabled'] = true;

		// Check 2: Multiple currencies
		$currencies = $this->get_available_currencies();
		if ( count( $currencies ) <= 1 ) {
			$results['issues'][] = 'Need more than 1 currency configured in WPML';
		} else {
			$results['multiple_currencies'] = true;
		}

		// Check 3: Different exchange rates (informational only, not required)
		if ( $results['multiple_currencies'] ) {
			$exchange_rates = array();
			foreach ( $currencies as $currency_code => $currency_data ) {
				$rate = isset( $currency_data['rate'] ) ? (float) $currency_data['rate'] : 1.0;
				$exchange_rates[] = $rate;
			}

			$unique_rates = array_unique( $exchange_rates, SORT_NUMERIC );
			if ( count( $unique_rates ) <= 1 ) {
				$results['warnings'][] = 'All currencies have the same exchange rate - prices will be identical across countries';
			} else {
				$results['different_exchange_rates'] = true;
			}
		}

		// All critical checks passed
		$results['all_met'] = $results['wpml_multicurrency_enabled'] &&
							  $results['multiple_currencies'];

		return $results;
	}
}
