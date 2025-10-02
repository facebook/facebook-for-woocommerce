<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Integrations;

/**
 * Shared Facebook field translation logic for localization integrations.
 *
 * This trait contains the common logic for analyzing which Facebook-specific
 * fields are translated between original and translated products. It mirrors
 * the exact approach used in WC_Facebook_Product::prepare_product() to ensure
 * we only check fields that are actually sent to Meta/Facebook.
 *
 * @since 3.6.0
 */
trait Facebook_Fields_Translation_Trait {

	/**
	 * Get which Facebook meta fields are translated between original and translated product
	 *
	 * Mirrors the exact approach used in WC_Facebook_Product::prepare_product() to ensure
	 * we only check fields that are actually sent to Meta/Facebook.
	 *
	 * @param int $original_id Original product ID
	 * @param int $translated_id Translated product ID
	 * @param string $target_language Target language code for permalink translation (optional)
	 * @return array Array of field names that have different values
	 */
	protected function get_translated_fields( int $original_id, int $translated_id, string $target_language = null ): array {
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

		// Get core Facebook fields mapping
		$core_facebook_fields = $this->get_facebook_field_mapping();

		// Check each core Facebook field using the actual Facebook product methods
		foreach ( $core_facebook_fields as $field_name => $method ) {
			// Skip method_exists check for magic methods - just try to call them
			try {
				$original_value = $original_fb_product->$method();
				$translated_value = $translated_fb_product->$method();

				// Debug logging for the name field specifically (only for WPML to avoid duplicate logs)
				if ( $field_name === 'name' && $this instanceof WPML ) {
					error_log( "WPML Debug - Name field comparison:" );
					error_log( "  Original value: '" . $original_value . "'" );
					error_log( "  Translated value: '" . $translated_value . "'" );
					error_log( "  Original trimmed: '" . trim( (string) $original_value ) . "'" );
					error_log( "  Translated trimmed: '" . trim( (string) $translated_value ) . "'" );
					error_log( "  Are different: " . ( trim( (string) $original_value ) !== trim( (string) $translated_value ) ? 'YES' : 'NO' ) );
					error_log( "  Translated not empty: " . ( ! empty( trim( (string) $translated_value ) ) ? 'YES' : 'NO' ) );
					error_log( "  Original not empty: " . ( ! empty( trim( (string) $original_value ) ) ? 'YES' : 'NO' ) );
				}

				// Handle array values
				if ( is_array( $original_value ) && is_array( $translated_value ) ) {
					if ( $original_value !== $translated_value ) {
						$translated_fields[] = $field_name;
						if ( $field_name === 'name' && $this instanceof WPML ) {
							error_log( "  RESULT: Added to translated_fields (array comparison)" );
						}
					}
				} else {
					// Convert to string for comparison
					$original_str = (string) $original_value;
					$translated_str = (string) $translated_value;

					// Compare values (trim whitespace and check for meaningful differences)
					// Also ensure we're not comparing empty values
					if ( trim( $original_str ) !== trim( $translated_str ) &&
						 ! empty( trim( $translated_str ) ) &&
						 ! empty( trim( $original_str ) ) ) {
						$translated_fields[] = $field_name;
						if ( $field_name === 'name' && $this instanceof WPML ) {
							error_log( "  RESULT: Added to translated_fields (string comparison)" );
						}
					} else {
						if ( $field_name === 'name' && $this instanceof WPML ) {
							error_log( "  RESULT: NOT added to translated_fields" );
							if ( trim( $original_str ) === trim( $translated_str ) ) {
								error_log( "    Reason: Values are the same" );
							} elseif ( empty( trim( $translated_str ) ) ) {
								error_log( "    Reason: Translated value is empty" );
							} elseif ( empty( trim( $original_str ) ) ) {
								error_log( "    Reason: Original value is empty" );
							}
						}
					}
				}
			} catch ( \Exception $e ) {
				if ( $field_name === 'name' && $this instanceof WPML ) {
					error_log( "WPML Debug - Name field method error:" );
					error_log( "  Method: " . $method );
					error_log( "  Error: " . $e->getMessage() );
				}
				// Skip fields that cause errors
				continue;
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

		// Always check permalink translation when we have translations
		// The link should be translated even if content fields aren't different
		if ( $target_language && method_exists( $this, 'get_translated_permalink' ) ) {
			$original_permalink = $original_fb_product->get_permalink();
			$translated_permalink = $this->get_translated_permalink( $original_permalink, $target_language );

			if ( trim( $original_permalink ) !== trim( $translated_permalink ) &&
				 ! empty( trim( $translated_permalink ) ) ) {
				$translated_fields[] = 'link';
			}
		}

		// If we have any translation but no fields detected, still include link for permalink translation
		// This handles cases where products exist in multiple languages but content is identical
		if ( empty( $translated_fields ) && $translated_id && $translated_id !== $original_id ) {
			if ( $target_language && method_exists( $this, 'get_translated_permalink' ) ) {
				$original_permalink = $original_fb_product->get_permalink();
				$translated_permalink = $this->get_translated_permalink( $original_permalink, $target_language );

				if ( trim( $original_permalink ) !== trim( $translated_permalink ) &&
					 ! empty( trim( $translated_permalink ) ) ) {
					$translated_fields[] = 'link';
				}
			}
		}

		return $translated_fields;
	}

	/**
	 * Get the mapping of Facebook field names to their corresponding methods
	 *
	 * @return array Array mapping field names to WC_Facebook_Product method names
	 */
	protected function get_facebook_field_mapping(): array {
		return [
			// Basic product information
			'name' => 'get_name',  // Use get_name() which delegates to WooCommerce product
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

			// Product link
			'link' => 'get_permalink',
		];
	}
}
