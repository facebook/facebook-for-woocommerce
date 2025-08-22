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
			return array_keys( $languages );
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
		$default = apply_filters( 'wpml_default_language', null );
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
	 * Get translation IDs for a product
	 *
	 * @param int $product_id Product ID
	 * @return array Array of translation IDs keyed by language code
	 */
	public function get_product_translations( int $product_id ): array {
		if ( ! $this->is_plugin_active() ) {
			return [];
		}

		$translations = [];
		$languages = $this->get_available_languages();

		foreach ( $languages as $language_code ) {
			// Use WPML filter to get object ID in specific language
			$translated_id = apply_filters( 'wpml_object_id', $product_id, 'post', false, $language_code );
			if ( $translated_id && is_numeric( $translated_id ) ) {
				$translations[ $language_code ] = (int) $translated_id;
			}
		}

		return $translations;
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

		$default_language = $this->get_default_language();
		if ( ! $default_language ) {
			return [];
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
				// Only include products that are in the default language
				if ( $product_language['language_code'] === $default_language ) {
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

		$languages = $this->get_available_languages();
		$default_language = $this->get_default_language();

		foreach ( $languages as $language_code ) {
			// Skip the default language
			if ( $language_code === $default_language ) {
				continue;
			}

			// Get translated product ID
			$translated_id = apply_filters( 'wpml_object_id', $product_id, 'post', false, $language_code );

			if ( $translated_id && $translated_id !== $product_id ) {
				$details['translations'][ $language_code ] = $translated_id;

				// Get translation status using WPML's API
				$translation_status = apply_filters( 'wpml_translation_status', null, $product_id, $language_code );
				$details['translation_status'][ $language_code ] = $translation_status;

				// Get which fields are translated
				$details['translated_fields'][ $language_code ] = $this->get_translated_fields( $product_id, $translated_id );
			}
		}

		return $details;
	}

	/**
	 * Get which Facebook meta fields are translated between original and translated product
	 *
	 * Mirrors the exact approach used in WC_Facebook_Product::prepare_product() to ensure
	 * we only check fields that are actually sent to Meta/Facebook.
	 *
	 * @param int $original_id Original product ID
	 * @param int $translated_id Translated product ID
	 * @return array Array of field names that have different values
	 */
	private function get_translated_fields( int $original_id, int $translated_id ): array {
		$original_product = wc_get_product( $original_id );
		$translated_product = wc_get_product( $translated_id );

		if ( ! $original_product || ! $translated_product ) {
			return [];
		}

		$translated_fields = [];

		// Create WC_Facebook_Product instances to use their methods
		if ( ! class_exists( 'WC_Facebook_Product' ) ) {
			require_once WC_FACEBOOKCOMMERCE_PLUGIN_DIR . '/includes/fbproduct.php';
		}

		$original_fb_product = new \WC_Facebook_Product( $original_product );
		$translated_fb_product = new \WC_Facebook_Product( $translated_product );

		// Core fields that are sent to Facebook (from prepare_product method)
		$core_facebook_fields = [
			// Basic product information
			'name' => 'get_name',
			'description' => 'get_fb_description',
			'short_description' => 'get_fb_short_description',
			'rich_text_description' => 'get_rich_text_description',

			// Pricing
			'price' => 'get_fb_price',

			// Facebook-specific attributes
			'brand' => 'get_fb_brand',
			'mpn' => 'get_fb_mpn',
			'condition' => 'get_fb_condition',
			'size' => 'get_fb_size',
			'color' => 'get_fb_color',
			'pattern' => 'get_fb_pattern',
			'age_group' => 'get_fb_age_group',
			'gender' => 'get_fb_gender',
			'material' => 'get_fb_material',
		];

		// Check each core Facebook field using the actual Facebook product methods
		foreach ( $core_facebook_fields as $field_name => $method ) {
			if ( method_exists( $original_fb_product, $method ) && method_exists( $translated_fb_product, $method ) ) {
				$original_value = $original_fb_product->$method();
				$translated_value = $translated_fb_product->$method();

				// Handle array values
				if ( is_array( $original_value ) && is_array( $translated_value ) ) {
					if ( $original_value !== $translated_value ) {
						$translated_fields[] = $field_name;
					}
				} else {
					// Convert to string for comparison
					$original_str = (string) $original_value;
					$translated_str = (string) $translated_value;

					// Compare values (trim whitespace and check for meaningful differences)
					if ( trim( $original_str ) !== trim( $translated_str ) && ! empty( trim( $translated_str ) ) ) {
						$translated_fields[] = $field_name;
					}
				}
			}
		}

		// Facebook custom labels (0-4) - these are sent as custom_data
		for ( $i = 0; $i <= 4; $i++ ) {
			$original_label = $original_product->get_meta( "custom_label_{$i}" );
			$translated_label = $translated_product->get_meta( "custom_label_{$i}" );

			if ( trim( $original_label ) !== trim( $translated_label ) && ! empty( trim( $translated_label ) ) ) {
				$translated_fields[] = "custom_label_{$i}";
			}
		}

		// Enhanced catalog attributes (sent to Facebook when Google product category is set)
		$google_category_id = null;
		if ( class_exists( '\WooCommerce\Facebook\Products' ) ) {
			$google_category_id = \WooCommerce\Facebook\Products::get_google_product_category_id( $original_product );
		}

		if ( $google_category_id && function_exists( 'facebook_for_woocommerce' ) ) {
			$category_handler = facebook_for_woocommerce()->get_facebook_category_handler();
			if ( $category_handler && method_exists( $category_handler, 'get_attributes_with_fallback_to_parent_category' ) ) {
				$all_attributes = $category_handler->get_attributes_with_fallback_to_parent_category( $google_category_id );

				if ( ! empty( $all_attributes ) ) {
					foreach ( $all_attributes as $attribute ) {
						if ( isset( $attribute['key'] ) ) {
							$original_enhanced = null;
							$translated_enhanced = null;

							if ( class_exists( '\WooCommerce\Facebook\Products' ) ) {
								$original_enhanced = \WooCommerce\Facebook\Products::get_enhanced_catalog_attribute( $attribute['key'], $original_product );
								$translated_enhanced = \WooCommerce\Facebook\Products::get_enhanced_catalog_attribute( $attribute['key'], $translated_product );
							}

							if ( trim( $original_enhanced ) !== trim( $translated_enhanced ) && ! empty( trim( $translated_enhanced ) ) ) {
								$translated_fields[] = "enhanced_catalog_{$attribute['key']}";
							}
						}
					}
				}
			}
		}

		// Product categories and tags (sent as product_type and additional_variant_attributes)
		$original_categories = wp_get_post_terms( $original_id, 'product_cat', ['fields' => 'names'] );
		$translated_categories = wp_get_post_terms( $translated_id, 'product_cat', ['fields' => 'names'] );

		if ( ! is_wp_error( $original_categories ) && ! is_wp_error( $translated_categories ) ) {
			if ( $original_categories !== $translated_categories ) {
				$translated_fields[] = 'product_categories';
			}
		}

		$original_tags = wp_get_post_terms( $original_id, 'product_tag', ['fields' => 'names'] );
		$translated_tags = wp_get_post_terms( $translated_id, 'product_tag', ['fields' => 'names'] );

		if ( ! is_wp_error( $original_tags ) && ! is_wp_error( $translated_tags ) ) {
			if ( $original_tags !== $translated_tags ) {
				$translated_fields[] = 'product_tags';
			}
		}

		// Images (image_url and additional_image_urls are sent to Facebook)
		$original_image_id = $original_product->get_image_id();
		$translated_image_id = $translated_product->get_image_id();

		if ( $original_image_id !== $translated_image_id ) {
			$translated_fields[] = 'image_id';
		}

		$original_gallery_ids = $original_product->get_gallery_image_ids();
		$translated_gallery_ids = $translated_product->get_gallery_image_ids();

		if ( $original_gallery_ids !== $translated_gallery_ids ) {
			$translated_fields[] = 'gallery_image_ids';
		}

		// Stock quantity (quantity_to_sell_on_facebook is sent to Facebook)
		if ( $original_product->managing_stock() && $translated_product->managing_stock() ) {
			$original_stock = $original_product->get_stock_quantity();
			$translated_stock = $translated_product->get_stock_quantity();

			if ( $original_stock !== $translated_stock ) {
				$translated_fields[] = 'stock_quantity';
			}
		}

		// Variation attributes (sent as custom_data for variations)
		if ( $original_product->is_type( 'variation' ) && $translated_product->is_type( 'variation' ) ) {
			$original_attributes = $original_product->get_variation_attributes();
			$translated_attributes = $translated_product->get_variation_attributes();

			if ( $original_attributes !== $translated_attributes ) {
				$translated_fields[] = 'variation_attributes';
			}
		}

		return $translated_fields;
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
