<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Feed\Localization;

use WooCommerce\Facebook\Integrations\IntegrationRegistry;
use WooCommerce\Facebook\Locale;

/**
 * Country Feed Data Handler
 *
 * Handles country-specific currency and pricing data for Facebook feeds.
 * Streamlined version focusing on core functionality.
 */
class CountryFeedData {

	/**
	 * Cached integration instance
	 *
	 * @var \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration|null
	 */
	private $integration = null;

	/**
	 * Check if country feed functionality is available.
	 * Country feeds work with both "currency by country" and "currency by language" modes.
	 * The difference is in currency selection logic: country mode has fallbacks, language mode uses native currency only.
	 *
	 * @return bool True if country feeds can be generated
	 */
	public function is_available(): bool {
		$integration = $this->get_multicurrency_integration();
		return $integration && $integration->supports_multicurrency();
	}

	/**
	 * Check if country feeds should be generated.
	 *
	 * @return bool True if country feeds should be generated
	 */
	public function should_generate_country_feeds(): bool {
		$prerequisites = $this->check_country_feed_prerequisites();
		return $prerequisites['all_met'];
	}

	/**
	 * Check all prerequisites for country feed generation.
	 *
	 * @return array Prerequisites check results
	 */
	public function check_country_feed_prerequisites(): array {
		$results = array(
			'wpml_multicurrency_enabled' => false,
			'currency_mode_by_country' => false,
			'multiple_currencies' => false,
			'multiple_shipping_countries' => false,
			'has_viable_countries' => false,
			'all_met' => false,
			'issues' => array(),
		);

		$integration = $this->get_multicurrency_integration();
		if ( ! $integration ) {
			$results['issues'][] = 'No multicurrency integration found';
			return $results;
		}

		// Check WPML prerequisites if it's WPML
		if ( method_exists( $integration, 'check_country_feed_prerequisites' ) ) {
			$wpml_prereqs = $integration->check_country_feed_prerequisites();
			$results = array_merge( $results, $wpml_prereqs );
		} else {
			// Basic checks for other integrations
			if ( ! $integration->supports_multicurrency() ) {
				$results['issues'][] = 'Multicurrency not supported';
				return $results;
			}
			$results['wpml_multicurrency_enabled'] = true;
		}

		// Check shipping countries (>1 required)
		$shipping_countries = $this->get_shipping_countries();
		if ( count( $shipping_countries ) <= 1 ) {
			$results['issues'][] = 'Need more than 1 country in shipping zones';
		} else {
			$results['multiple_shipping_countries'] = true;
		}

		// Check viable countries (intersection of shipping + currency + Meta supported)
		$viable_countries = $this->get_viable_country_feed_countries();
		if ( empty( $viable_countries ) ) {
			$results['issues'][] = 'No countries are both shippable and have currency configured';
		} else {
			$results['has_viable_countries'] = true;
		}

		// All critical checks passed
		$results['all_met'] = $results['wpml_multicurrency_enabled'] &&
							  $results['multiple_currencies'] &&
							  $results['multiple_shipping_countries'] &&
							  $results['has_viable_countries'];

		return $results;
	}

	/**
	 * Get all available countries that have currency configurations.
	 *
	 * @return array Array of country codes
	 */
	public function get_available_countries(): array {
		$integration = $this->get_multicurrency_integration();
		if ( ! $integration || ! method_exists( $integration, 'get_available_countries' ) ) {
			return array();
		}

		return $integration->get_available_countries();
	}

	/**
	 * Get all configured currencies.
	 *
	 * @return array Array of currency codes and their data
	 */
	public function get_available_currencies(): array {
		$integration = $this->get_multicurrency_integration();
		if ( ! $integration || ! method_exists( $integration, 'get_available_currencies' ) ) {
			return array();
		}

		return $integration->get_available_currencies();
	}

	/**
	 * Get the primary currency for a specific country.
	 *
	 * @param string $country_code Two-letter country code (e.g., 'GB', 'US')
	 * @return string|null Currency code or null if no currency found for country
	 */
	public function get_currency_for_country( string $country_code ): ?string {
		$integration = $this->get_multicurrency_integration();
		if ( ! $integration || ! method_exists( $integration, 'get_currency_for_country' ) ) {
			return null;
		}

		return $integration->get_currency_for_country( $country_code );
	}

	/**
	 * Get countries that are viable for country feeds:
	 * - In a valid shipping zone
	 * - Supported by Meta/Facebook
	 * - Have their currency available
	 *
	 * @return array Array of country codes that are viable for country feeds
	 */
	public function get_viable_country_feed_countries(): array {
		$shipping_countries = $this->get_shipping_countries();
		$meta_supported_countries = Locale::get_meta_supported_countries();
		$currency_countries = $this->get_available_countries();

		// Get countries that are shipped to, Meta-supported, AND have currency
		$viable_countries = array_intersect( $shipping_countries, $meta_supported_countries, $currency_countries );

		return $viable_countries;
	}

	/**
	 * Get countries that would receive country override feeds.
	 * This considers the currency mode and fallback logic.
	 *
	 * @return array Array of country codes that will have override feeds generated
	 */
	public function get_countries_for_override_feeds(): array {
		$viable_countries = $this->get_viable_country_feed_countries();
		$integration = $this->get_multicurrency_integration();

		if ( ! $integration ) {
			return array();
		}

		$countries_with_feeds = array();

		foreach ( $viable_countries as $country_code ) {
			$currency_code = $this->get_currency_for_country( $country_code );

			// Only include countries that have a currency assigned
			if ( $currency_code ) {
				$countries_with_feeds[] = $country_code;
			}
		}

		return $countries_with_feeds;
	}

	/**
	 * Get product country overrides for viable countries only.
	 *
	 * @param int $product_id Product ID
	 * @return array Array of country override data for viable countries only
	 */
	public function get_product_country_overrides( int $product_id ): array {
		$integration = $this->get_multicurrency_integration();
		if ( ! $integration || ! method_exists( $integration, 'product_has_multiple_currency_pricing' ) ) {
			return array();
		}

		if ( ! $integration->product_has_multiple_currency_pricing( $product_id ) ) {
			return array();
		}

		$viable_countries = $this->get_viable_country_feed_countries();
		$overrides = array();

		foreach ( $viable_countries as $country_code ) {
			$currency_code = $this->get_currency_for_country( $country_code );

			// Skip if no currency found for this country
			if ( ! $currency_code ) {
				continue;
			}

			// Get pricing information for this currency
			if ( method_exists( $integration, 'get_product_pricing_for_currency' ) ) {
				$pricing_data = $integration->get_product_pricing_for_currency( $product_id, $currency_code );

				if ( $pricing_data ) {
					$overrides[ $country_code ] = array(
						'currency_code' => $currency_code,
						'price' => $pricing_data['price'],
						'sale_price' => $pricing_data['sale_price'],
						'regular_price' => $pricing_data['regular_price'],
						'formatted_price' => $pricing_data['formatted_price'],
						'currency_symbol' => $pricing_data['currency_symbol'],
					);
				}
			}
		}

		return $overrides;
	}

	/**
	 * Get CSV data for a specific country.
	 *
	 * @param string $country_code Two-letter country code
	 * @param array  $product_ids Array of product IDs to process
	 * @return array Array of CSV rows for the specific country
	 */
	public function get_country_csv_data( string $country_code, array $product_ids ): array {
		if ( ! $this->should_generate_country_feeds() ) {
			return array();
		}

		$csv_data = array();
		$viable_countries = $this->get_countries_for_override_feeds();

		// Check if the requested country is viable for feeds
		if ( ! in_array( $country_code, $viable_countries, true ) ) {
			return array();
		}

		foreach ( $product_ids as $product_id ) {
			$country_overrides = $this->get_product_country_overrides( $product_id );

			// Only include data for the requested country
			if ( isset( $country_overrides[ $country_code ] ) ) {
				$override_data = $country_overrides[ $country_code ];
				$csv_data[] = array(
					'id' => 'wc_post_id_' . $product_id,
					'override' => $country_code,
					'price' => $this->format_price_for_facebook( $override_data['price'], $override_data['currency_code'] ),
				);
			}
		}

		return $csv_data;
	}

	/**
	 * Generate CSV content for a specific country.
	 *
	 * @param string $country_code Two-letter country code
	 * @param array  $product_ids Array of product IDs to process (optional)
	 * @return string CSV content as string
	 */
	public function generate_country_csv_content( string $country_code, array $product_ids = null ): string {
		if ( null === $product_ids ) {
			// Get sample product IDs for testing
			$product_ids = $this->get_sample_product_ids( 10 );
		}

		$csv_data = $this->get_country_csv_data( $country_code, $product_ids );

		if ( empty( $csv_data ) ) {
			return '';
		}

		// Start with CSV header and comment
		$csv_content = "# Required | A unique content ID for the item. Use the item's SKU if you can. Each content ID must appear only once in your catalog. To run dynamic ads this ID must exactly match the content ID for the same item in your Meta Pixel code. Character limit: 100,# Required | ISO country code. Supported codes: https://www.facebook.com/business/help/2144286692311411,# Optional | The price of the item. Format the price as a number followed by the 3-letter currency code (ISO 4217 standards). Use a period (.') as the decimal point; don't use a comma.\n";
		$csv_content .= "id,override,price\n";

		// Add CSV rows
		foreach ( $csv_data as $row ) {
			$csv_content .= $row['id'] . ',' . $row['override'] . ',' . $row['price'] . "\n";
		}

		return $csv_content;
	}

	/**
	 * Generate all country CSV files.
	 *
	 * @param array $product_ids Array of product IDs to process (optional)
	 * @return array Array of country codes and their CSV content
	 */
	public function generate_all_country_csv_files( array $product_ids = null ): array {
		if ( ! $this->should_generate_country_feeds() ) {
			return array();
		}

		if ( null === $product_ids ) {
			// Get sample product IDs for testing
			$product_ids = $this->get_sample_product_ids( 10 );
		}

		$country_feeds = array();
		$countries_for_feeds = $this->get_countries_for_override_feeds();

		foreach ( $countries_for_feeds as $country_code ) {
			$csv_content = $this->generate_country_csv_content( $country_code, $product_ids );

			if ( ! empty( $csv_content ) ) {
				$country_feeds[ $country_code ] = $csv_content;
			}
		}

		return $country_feeds;
	}

	/**
	 * Format price for Facebook country feeds.
	 * Format: "5.99 EUR" (number with period as decimal + space + 3-letter currency code)
	 *
	 * @param float  $price Price value
	 * @param string $currency_code 3-letter currency code
	 * @return string Formatted price for Facebook
	 */
	private function format_price_for_facebook( float $price, string $currency_code ): string {
		// Format price with 2 decimal places and period as decimal separator
		$formatted_price = number_format( $price, 2, '.', '' );

		// Return in Facebook format: "5.99 EUR"
		return $formatted_price . ' ' . strtoupper( $currency_code );
	}

	/**
	 * Get sample product IDs for testing.
	 *
	 * @param int $limit Maximum number of product IDs to return
	 * @return array Array of product IDs
	 */
	private function get_sample_product_ids( int $limit = 10 ): array {
		$args = array(
			'post_type' => 'product',
			'post_status' => 'publish',
			'posts_per_page' => $limit,
			'fields' => 'ids',
			'meta_query' => array(
				array(
					'key' => '_price',
					'value' => '',
					'compare' => '!=',
				),
			),
		);

		return get_posts( $args );
	}

	/**
	 * Get summary information about country feeds.
	 *
	 * @return array Summary data for debugging and admin display
	 */
	public function get_country_feed_summary(): array {
		$integration = $this->get_multicurrency_integration();
		$available_currencies = $this->get_available_currencies();
		$shipping_countries = $this->get_shipping_countries();
		$viable_countries = $this->get_viable_country_feed_countries();

		return array(
			'available' => $this->is_available(),
			'should_generate' => $this->should_generate_country_feeds(),
			'integration' => $integration ? $integration->get_plugin_name() : null,
			'currency_count' => count( $available_currencies ),
			'shipping_country_count' => count( $shipping_countries ),
			'viable_country_count' => count( $viable_countries ),
			'viable_countries' => $viable_countries,
			'currencies' => array_keys( $available_currencies ),
		);
	}

	/**
	 * Get all countries that the site ships to based on WooCommerce shipping zones.
	 * Enhanced to handle all zone types: countries, continents, states, postcodes, and "rest of world".
	 *
	 * @return array Array of country codes that have shipping zones configured
	 */
	public function get_shipping_countries(): array {
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return array();
		}

		// Get regular zones
		$zones = \WC_Shipping_Zones::get_zones();
		$shipping_countries = array();
		$has_rest_of_world_coverage = false;

		// Check regular zones first
		foreach ( $zones as $zone ) {
			$locations = $zone['zone_locations'] ?? array();

			// Only process zones that have active shipping methods
			if ( ! $this->zone_has_active_shipping_methods( $zone ) ) {
				continue;
			}

			// Empty locations usually means this zone covers "everywhere"
			if ( empty( $locations ) ) {
				$has_rest_of_world_coverage = true;
				continue;
			}

			$zone_countries = $this->extract_countries_from_locations( $locations );
			$shipping_countries = array_merge( $shipping_countries, $zone_countries );
		}

		// Also check the dedicated "Rest of the World" zone (zone ID 0)
		try {
			$rest_of_world_zone = \WC_Shipping_Zones::get_zone( 0 );
			if ( $rest_of_world_zone && $this->rest_of_world_zone_has_active_methods( $rest_of_world_zone ) ) {
				$has_rest_of_world_coverage = true;
			}
		} catch ( \Exception $e ) {
			// Rest of world zone doesn't exist or error accessing it
		}

		// If we have rest of world coverage, return all world countries
		if ( $has_rest_of_world_coverage ) {
			return array_keys( Locale::get_world_countries() );
		}

		return array_unique( $shipping_countries );
	}

	/**
	 * Get CSV header for the specified columns.
	 *
	 * @param array $columns Array of column names
	 * @return string CSV header with field descriptions
	 */
	public function get_csv_header_for_columns( array $columns ): string {
		$headers = array(
			'id' => '# Required | A unique content ID for the item. Use the item\'s SKU if you can. Each content ID must appear only once in your catalog. To run dynamic ads this ID must exactly match the content ID for the same item in your Meta Pixel code. Character limit: 100',
			'override' => '# Required | ISO country code. Supported codes: https://www.facebook.com/business/help/2144286692311411',
			'price' => '# Optional | The price of the item. Format the price as a number followed by the 3-letter currency code (ISO 4217 standards). Use a period (.) as the decimal point; don\'t use a comma.',
		);

		$header_lines = array();
		foreach ( $columns as $column ) {
			if ( isset( $headers[ $column ] ) ) {
				$header_lines[] = $headers[ $column ];
			}
		}

		return implode( ',', $header_lines );
	}

	/**
	 * Get the active multicurrency integration.
	 *
	 * @return \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration|null
	 */
	private function get_multicurrency_integration() {
		// Cache the integration to avoid repeated lookups
		if ( null === $this->integration ) {
			$integrations = IntegrationRegistry::get_all_localization_integrations();

			foreach ( $integrations as $integration ) {
				if ( $integration->is_plugin_active() && method_exists( $integration, 'supports_multicurrency' ) && $integration->supports_multicurrency() ) {
					$this->integration = $integration;
					break;
				}
			}

			// Set to false if no integration found to avoid repeated lookups
			if ( null === $this->integration ) {
				$this->integration = false;
			}
		}

		return $this->integration !== false ? $this->integration : null;
	}

	/**
	 * Extract countries from shipping zone locations.
	 * Enhanced to handle all WooCommerce zone location types:
	 * - continent: Get all countries in the continent
	 * - country: Add the specific country
	 * - state: Extract country from state code (US:CA format)
	 * - postcode: Extract country from postcode (still includes the country)
	 *
	 * @param array $locations Zone locations array
	 * @return array Array of country codes
	 */
	private function extract_countries_from_locations( array $locations ): array {
		$countries = array();

		foreach ( $locations as $location ) {
			$location = (array) $location;
			$location_type = $location['type'] ?? '';
			$location_code = $location['code'] ?? '';

			if ( empty( $location_type ) || empty( $location_code ) ) {
				continue;
			}

			switch ( $location_type ) {
				case 'continent':
					// Get all countries in this continent
					$continents = WC()->countries->get_continents();
					if ( isset( $continents[ $location_code ]['countries'] ) ) {
						$continent_countries = $continents[ $location_code ]['countries'];
						$countries = array_merge( $countries, $continent_countries );
					}
					break;

				case 'country':
					// Add this specific country
					$countries[] = $location_code;
					break;

				case 'state':
					// Extract country from state code (format: "US:CA" for California, USA)
					$parts = explode( ':', $location_code );
					if ( count( $parts ) >= 2 ) {
						$countries[] = $parts[0];
					}
					break;

				case 'postcode':
					// Postcodes are defined per country, so extract country from the postcode location
					// WooCommerce stores postcodes with country prefix in some cases
					$parts = explode( ':', $location_code );
					if ( count( $parts ) >= 2 ) {
						// Format is likely "US:90210" or similar
						$countries[] = $parts[0];
					} else {
						// If no country prefix, this might be a legacy format
						// We can't reliably determine the country, so skip this location
					}
					break;

				default:
					// Unknown location type, skip
					break;
			}
		}

		return array_unique( $countries );
	}

	/**
	 * Check if a shipping zone has active shipping methods.
	 *
	 * @param array $zone Shipping zone data
	 * @return bool True if zone has at least one enabled shipping method
	 */
	private function zone_has_active_shipping_methods( array $zone ): bool {
		if ( ! isset( $zone['shipping_methods'] ) || empty( $zone['shipping_methods'] ) ) {
			return false;
		}

		foreach ( $zone['shipping_methods'] as $method ) {
			// Check if method is enabled
			if ( isset( $method->enabled ) && 'yes' === $method->enabled ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if the dedicated rest of world zone (ID: 0) has active shipping methods.
	 *
	 * @param \WC_Shipping_Zone $zone Rest of world shipping zone object
	 * @return bool True if zone has at least one enabled shipping method
	 */
	private function rest_of_world_zone_has_active_methods( $zone ): bool {
		if ( ! is_object( $zone ) ) {
			return false;
		}

		$methods = $zone->get_shipping_methods();
		if ( empty( $methods ) ) {
			return false;
		}

		foreach ( $methods as $method ) {
			// Check if method is enabled
			if ( isset( $method->enabled ) && 'yes' === $method->enabled ) {
				return true;
			}
		}

		return false;
	}
}
