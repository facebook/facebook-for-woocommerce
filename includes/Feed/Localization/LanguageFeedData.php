<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Feed\Localization;

// Manually include the integration classes
require_once __DIR__ . '/../../Integrations/Abstract_Localization_Integration.php';
require_once __DIR__ . '/../../Integrations/IntegrationRegistry.php';
require_once __DIR__ . '/../../Integrations/WPML.php';
require_once __DIR__ . '/../../Integrations/Polylang.php';

use WooCommerce\Facebook\Integrations\IntegrationRegistry;
use WooCommerce\Facebook\Framework\Logger;

/**
 * Language Feed Data Handler for Facebook Language Override Feeds
 *
 * Handles translation data extraction from localization plugins and CSV generation
 * for Facebook language override feeds. Consolidates both data extraction and formatting.
 *
 * @since 3.6.0
 */
class LanguageFeedData {


	// ===========================================
	// TRANSLATION DATA EXTRACTION METHODS
	// ====================================


	/**
	 * Get all available languages from active localization plugins
	 *
	 * @return array Array of language codes
	 */
	public function get_available_languages(): array {
		$all_languages = [];
		$integrations = IntegrationRegistry::get_all_localization_integrations();

		foreach ( $integrations as $integration ) {
			if ( $integration->is_plugin_active() ) {
				$languages = $integration->get_available_languages();
				$all_languages = array_merge( $all_languages, $languages );
			}
		}

		// Remove duplicates and default language
		$all_languages = array_unique( $all_languages );
		$default_language = $this->get_default_language();

		if ( $default_language ) {
			$all_languages = array_filter( $all_languages, function( $lang ) use ( $default_language ) {
				return $lang !== $default_language;
			});
		}

		return array_values( $all_languages );
	}


	/**
	 * Get the default language from the first active localization plugin
	 *
	 * @return string|null Default language code or null if not available
	 */
	public function get_default_language(): ?string {
		$integrations = IntegrationRegistry::get_all_localization_integrations();

		foreach ( $integrations as $integration ) {
			if ( $integration->is_plugin_active() ) {
				$default = $integration->get_default_language();
				if ( $default ) {
					return $default;
				}
			}
		}

		return null;
	}

	/**
	 * Get products from the default language
	 *
	 * Uses the first active localization plugin to get products from the default language.
	 *
	 * @param int $limit Maximum number of products to return
	 * @param int $offset Offset for pagination
	 * @return array Array of product IDs from the default language
	 */
	public function get_products_from_default_language( int $limit = 10, int $offset = 0 ): array {
		$integrations = IntegrationRegistry::get_all_localization_integrations();

		foreach ( $integrations as $integration ) {
			if ( $integration->is_plugin_active() ) {
				return $integration->get_products_from_default_language( $limit, $offset );
			}
		}

		// Fallback: get regular products if no localization plugin is active
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
	 * Uses the first active localization plugin to get translation details.
	 *
	 * @param int $product_id Product ID (should be from default language)
	 * @return array Detailed translation information
	 */
	public function get_product_translation_details( int $product_id ): array {
		$integrations = IntegrationRegistry::get_all_localization_integrations();

		foreach ( $integrations as $integration ) {
			if ( $integration->is_plugin_active() ) {
				return $integration->get_product_translation_details( $product_id );
			}
		}

		// Fallback: return basic structure if no localization plugin is active
		return [
			'product_id' => $product_id,
			'default_language' => null,
			'translations' => [],
			'translation_status' => [],
			'translated_fields' => []
		];
	}

	/**
	 * Get total count of products with translations for a specific language
	 *
	 * Uses the same logic as CSV generation to ensure consistency
	 *
	 * @param string $language_code Language code
	 * @return int Total count of products with translations
	 */
	public function get_translated_products_count( string $language_code ): int {
		if ( ! IntegrationRegistry::has_active_localization_plugin() ) {
			return 0;
		}

		// Use the same filtering logic as CSV generation for consistency
		// Use a large limit instead of -1 to avoid issues with unlimited queries
		$all_product_ids = $this->get_products_from_default_language( 10000, 0 );
		$count = 0;

		foreach ( $all_product_ids as $product_id ) {
			// Apply the same validation as CSV generation
			$original_product = wc_get_product( $product_id );
			if ( ! $original_product ) {
				continue;
			}

			// Use Facebook's product sync validator if available (same as CSV generation)
			if ( function_exists( 'facebook_for_woocommerce' ) ) {
				$sync_validator = facebook_for_woocommerce()->get_product_sync_validator( $original_product );
				if ( ! $sync_validator->passes_all_checks() ) {
					continue;
				}
			}

			$details = $this->get_product_translation_details( $product_id );

			if ( empty( $details['translations'] ) || ! isset( $details['translations'][ $language_code ] ) ) {
				continue;
			}

			$translated_id = $details['translations'][ $language_code ];
			$product_translated_fields = $details['translated_fields'][ $language_code ] ?? [];

			// Only include products that have actual translated content (same as CSV generation)
			if ( empty( $product_translated_fields ) ) {
				continue;
			}

			$translated_product = wc_get_product( $translated_id );
			if ( ! $translated_product ) {
				continue;
			}

			// Verify that at least one translated field has actual content
			try {
				if ( ! class_exists( 'WC_Facebook_Product' ) ) {
					require_once WC_FACEBOOKCOMMERCE_PLUGIN_DIR . '/includes/fbproduct.php';
				}

				$translated_fb_product = new \WC_Facebook_Product( $translated_product );

				// Check if at least one translated field has actual content
				$has_content = false;
				$field_mapping = [
					'name' => 'title',
					'description' => 'description',
					'short_description' => 'description',
					'rich_text_description' => 'description',
				];

				foreach ( $product_translated_fields as $field ) {
					if ( isset( $field_mapping[ $field ] ) ) {
						$csv_column = $field_mapping[ $field ];
						switch ( $csv_column ) {
							case 'title':
								$value = $translated_fb_product->get_name();
								if ( ! empty( $value ) ) {
									$has_content = true;
								}
								break;
							case 'description':
								$value = $translated_fb_product->get_fb_description();
								if ( ! empty( $value ) ) {
									$has_content = true;
								}
								break;
						}
						if ( $has_content ) break;
					}
				}

				if ( $has_content ) {
					$count++;
				}

			} catch ( Exception $e ) {
				// Skip products that can't create Facebook products
				continue;
			}
		}

		return $count;
	}


	/**
	 * Get statistics for language feeds
	 *
	 * @return array Statistics for all available languages
	 */
	public function get_language_feed_statistics(): array {
		$languages = $this->get_available_languages();
		$statistics = [];

		foreach ( $languages as $language_code ) {
			$count = $this->get_translated_products_count( $language_code );
			$statistics[ $language_code ] = [
				'language_code' => $language_code,
				'translated_products_count' => $count,
				'estimated_csv_size' => $this->estimate_csv_size( $count ),
			];
		}

		return $statistics;
	}

	/**
	 * Estimate CSV file size based on product count
	 *
	 * @param int $product_count Number of products
	 * @return string Human-readable file size estimate
	 */
	private function estimate_csv_size( int $product_count ): string {
		// Rough estimate: ~200 bytes per product row (including headers)
		$estimated_bytes = ( $product_count * 200 ) + 1000; // Add 1KB for headers

		if ( $estimated_bytes < 1024 ) {
			return $estimated_bytes . ' B';
		} elseif ( $estimated_bytes < 1048576 ) {
			return round( $estimated_bytes / 1024, 1 ) . ' KB';
		} else {
			return round( $estimated_bytes / 1048576, 1 ) . ' MB';
		}
	}

	// ===========================================
	// CSV FORMATTING AND GENERATION METHODS
	// ===========================================


	/**
	 * Get Facebook product ID in the same format as the main feed
	 *
	 * @param \WC_Facebook_Product $fb_product Facebook product object
	 * @return string Facebook product ID
	 */
	private function get_facebook_product_id( \WC_Facebook_Product $fb_product ): string {
		// Use the same ID format as the main Facebook feed
		return \WC_Facebookcommerce_Utils::get_fb_retailer_id( $fb_product );
	}

	/**
	 * Get all unique translated fields across all products for a language
	 *
	 * @param string $language_code Language code
	 * @param int $limit Maximum number of products to check
	 * @return array Array of unique field names that have translations
	 */
	public function get_translated_fields_for_language( string $language_code, int $limit = 100 ): array {
		if ( ! IntegrationRegistry::has_active_localization_plugin() ) {
			return [];
		}

		$product_ids = $this->get_products_from_default_language( $limit, 0 );
		$all_translated_fields = [];

		foreach ( $product_ids as $product_id ) {
			$details = $this->get_product_translation_details( $product_id );

			if ( isset( $details['translated_fields'][ $language_code ] ) ) {
				$translated_fields = $details['translated_fields'][ $language_code ];
				$all_translated_fields = array_merge( $all_translated_fields, $translated_fields );
			}
		}

		return array_unique( $all_translated_fields );
	}

	/**
	 * Map translated field names to Facebook CSV column names
	 *
	 * @param array $translated_fields Array of translated field names
	 * @return array Array of Facebook CSV column names
	 */
	private function map_translated_fields_to_csv_columns( array $translated_fields ): array {
		// Mapping from WPML field names to Facebook CSV column names
		$field_mapping = [
			'name' => 'title',
			'description' => 'description',
			'short_description' => 'description', // Both map to description
			'brand' => 'brand',
			'mpn' => 'mpn',
			'condition' => 'condition',
			'size' => 'size',
			'color' => 'color',
			'pattern' => 'pattern',
			'age_group' => 'age_group',
			'gender' => 'gender',
			'material' => 'material',
			'image_id' => 'image_link',
			'gallery_image_ids' => 'additional_image_link',
			'link' => 'link',
		];

		$csv_columns = [];
		foreach ( $translated_fields as $field ) {
			if ( isset( $field_mapping[ $field ] ) ) {
				$csv_columns[] = $field_mapping[ $field ];
			}
		}

		// Remove duplicates and ensure required columns
		$csv_columns = array_unique( $csv_columns );

		return $csv_columns;
	}

	/**
	 * Extract translation data and convert to CSV format for a specific language
	 *
	 * @param string $language_code Language code (e.g., 'es_ES', 'fr_FR')
	 * @param int $limit Maximum number of products to process
	 * @param int $offset Offset for pagination
	 * @return array CSV data ready for conversion to CSV string with dynamic columns
	 */
	public function get_language_csv_data( string $language_code, int $limit = 100, int $offset = 0 ): array {
		if ( ! IntegrationRegistry::has_active_localization_plugin() ) {
			return [
				'data' => [],
				'columns' => ['id', 'override'],
				'translated_fields' => [],
			];
		}

		// First, determine which fields are translated for this language
		$translated_fields = $this->get_translated_fields_for_language( $language_code, $limit );
		$csv_columns = $this->map_translated_fields_to_csv_columns( $translated_fields );

		$product_ids = $this->get_products_from_default_language( $limit, $offset );
		$csv_data = [];

		foreach ( $product_ids as $product_id ) {
			// Skip products that don't pass Facebook sync validation
			$original_product = wc_get_product( $product_id );
			if ( ! $original_product ) {
				continue;
			}

			// Use Facebook's product sync validator if available
			if ( function_exists( 'facebook_for_woocommerce' ) ) {
				$sync_validator = facebook_for_woocommerce()->get_product_sync_validator( $original_product );
				if ( ! $sync_validator->passes_all_checks() ) {
					continue;
				}
			}

			$details = $this->get_product_translation_details( $product_id );

			if ( empty( $details['translations'] ) || ! isset( $details['translations'][ $language_code ] ) ) {
				continue;
			}

			$translated_id = $details['translations'][ $language_code ];
			$product_translated_fields = $details['translated_fields'][ $language_code ] ?? [];

			// Only include products that have actual translated content
			if ( empty( $product_translated_fields ) ) {
				continue;
			}

			$translated_product = wc_get_product( $translated_id );
			if ( ! $translated_product ) {
				continue;
			}

			// Create Facebook product instances for proper field extraction
			if ( ! class_exists( 'WC_Facebook_Product' ) ) {
				require_once WC_FACEBOOKCOMMERCE_PLUGIN_DIR . '/includes/fbproduct.php';
			}

			$original_fb_product = new \WC_Facebook_Product( $original_product );
			$translated_fb_product = new \WC_Facebook_Product( $translated_product );

			// Generate Facebook product ID (same format as main feed)
			$facebook_id = $this->get_facebook_product_id( $original_fb_product );

			// Start with required columns
			$csv_row = [
				'id' => $facebook_id,
				'override' => \WooCommerce\Facebook\Locale::convert_to_facebook_language_code( $language_code ),
			];

			// Add dynamic columns based on what's actually translated
			foreach ( $csv_columns as $column ) {
				$csv_row[ $column ] = $this->get_translated_field_value(
					$column,
					$original_fb_product,
					$translated_fb_product,
					$product_translated_fields,
					$language_code
				);
			}

			// Only add row if at least one translatable field has content
			$has_content = false;
			foreach ( $csv_columns as $column ) {
				if ( ! empty( $csv_row[ $column ] ) ) {
					$has_content = true;
					break;
				}
			}

			if ( $has_content ) {
				$csv_data[] = $csv_row;
			}
		}

		return [
			'data' => $csv_data,
			'columns' => array_merge( ['id', 'override'], $csv_columns ),
			'translated_fields' => $translated_fields,
		];
	}

	/**
	 * Get the value for a specific translated field using Facebook product methods
	 *
	 * @param string $column CSV column name
	 * @param \WC_Facebook_Product $original_fb_product Original Facebook product
	 * @param \WC_Facebook_Product $translated_fb_product Translated Facebook product
	 * @param array $product_translated_fields Fields that are translated for this product
	 * @param string $language_code Target language code for permalink translation
	 * @return string Field value with proper validation and cleaning
	 */
	private function get_translated_field_value(
		string $column,
		\WC_Facebook_Product $original_fb_product,
		\WC_Facebook_Product $translated_fb_product,
		array $product_translated_fields,
		string $language_code = ''
	): string {
		// Import required classes for validation
		if ( ! class_exists( '\WooCommerce\Facebook\Framework\Helper' ) ) {
			require_once WC_FACEBOOKCOMMERCE_PLUGIN_DIR . '/includes/Framework/Helper.php';
		}

		$value = '';

		switch ( $column ) {
			case 'title':
				if ( in_array( 'name', $product_translated_fields, true ) ) {
					$title = $translated_fb_product->get_name();
					$value = \WooCommerce\Facebook\Framework\Helper::str_truncate(
						\WC_Facebookcommerce_Utils::clean_string( $title ),
						\WC_Facebook_Product::MAX_TITLE_LENGTH
					);
				}
				break;

			case 'description':
				if ( in_array( 'description', $product_translated_fields, true ) ||
					 in_array( 'short_description', $product_translated_fields, true ) ||
					 in_array( 'rich_text_description', $product_translated_fields, true ) ) {
					$description = $translated_fb_product->get_fb_description();
					$value = \WooCommerce\Facebook\Framework\Helper::str_truncate(
						$description,
						\WC_Facebook_Product::MAX_DESCRIPTION_LENGTH
					);
				}
				break;

			case 'brand':
				if ( in_array( 'brand', $product_translated_fields, true ) ) {
					$brand = $translated_fb_product->get_fb_brand();
					$value = \WooCommerce\Facebook\Framework\Helper::str_truncate(
						\WC_Facebookcommerce_Utils::clean_string( $brand ),
						100
					);
				}
				break;

			case 'price':
				if ( in_array( 'price', $product_translated_fields, true ) ) {
					$price = $translated_fb_product->get_fb_price();
					$currency = get_woocommerce_currency();
					$value = $this->format_price_for_csv( $price, $currency );
				}
				break;

			case 'product_type':
				if ( in_array( 'product_categories', $product_translated_fields, true ) ) {
					$categories = \WC_Facebookcommerce_Utils::get_product_categories( $translated_fb_product->get_id() );
					$value = \WC_Facebookcommerce_Utils::clean_string( $categories['categories'] ?? '' );
				}
				break;

			case 'image_link':
				if ( in_array( 'image_id', $product_translated_fields, true ) ) {
					$image_urls = $translated_fb_product->get_all_image_urls();
					$value = $image_urls[0] ?? '';
				}
				break;

			case 'additional_image_link':
				if ( in_array( 'gallery_image_ids', $product_translated_fields, true ) ) {
					$image_urls = $translated_fb_product->get_all_image_urls();
					$additional_images = array_slice( $image_urls, 1, 5 ); // Max 5 additional images
					$value = ! empty( $additional_images ) ? implode( ',', $additional_images ) : '';
				}
				break;

			// Handle other Facebook attributes
			case 'mpn':
				if ( in_array( 'mpn', $product_translated_fields, true ) ) {
					$mpn = $translated_fb_product->get_fb_mpn();
					$value = \WooCommerce\Facebook\Framework\Helper::str_truncate(
						\WC_Facebookcommerce_Utils::clean_string( $mpn ),
						100
					);
				}
				break;

			case 'condition':
				if ( in_array( 'condition', $product_translated_fields, true ) ) {
					$value = $translated_fb_product->get_fb_condition();
				}
				break;

			case 'size':
				if ( in_array( 'size', $product_translated_fields, true ) ) {
					$size = $translated_fb_product->get_fb_size();
					$value = \WC_Facebookcommerce_Utils::clean_string( $size );
				}
				break;

			case 'color':
				if ( in_array( 'color', $product_translated_fields, true ) ) {
					$color = $translated_fb_product->get_fb_color();
					$value = \WC_Facebookcommerce_Utils::clean_string( $color );
				}
				break;

			case 'pattern':
				if ( in_array( 'pattern', $product_translated_fields, true ) ) {
					$pattern = $translated_fb_product->get_fb_pattern();
					$value = \WooCommerce\Facebook\Framework\Helper::str_truncate(
						\WC_Facebookcommerce_Utils::clean_string( $pattern ),
						100
					);
				}
				break;

			case 'age_group':
				if ( in_array( 'age_group', $product_translated_fields, true ) ) {
					$value = $translated_fb_product->get_fb_age_group();
				}
				break;

			case 'gender':
				if ( in_array( 'gender', $product_translated_fields, true ) ) {
					$value = $translated_fb_product->get_fb_gender();
				}
				break;

			case 'material':
				if ( in_array( 'material', $product_translated_fields, true ) ) {
					$material = $translated_fb_product->get_fb_material();
					$value = \WooCommerce\Facebook\Framework\Helper::str_truncate(
						\WC_Facebookcommerce_Utils::clean_string( $material ),
						100
					);
				}
				break;

			case 'link':
				if ( in_array( 'link', $product_translated_fields, true ) ) {
					// Use the translated product's built-in get_permalink() method
					// This automatically handles the correct URL structure and translated slugs
					$value = $translated_fb_product->get_permalink();
				}
				break;
		}

		return $value;
	}




	/**
	 * Format price for CSV using Facebook's approach
	 *
	 * @param int $price Price in cents
	 * @param string $currency Currency code
	 * @return string Formatted price
	 */
	private function format_price_for_csv( $price, $currency ): string {
		return (string) ( round( $price / 100.0, 2 ) ) . ' ' . $currency;
	}


}
