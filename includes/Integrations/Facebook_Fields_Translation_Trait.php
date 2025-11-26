<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Integrations;

/**
 * Shared Facebook field translation logic for localization integrations.
 *
 * This trait contains the common logic for analyzing which Facebook-specific
 * fields are translated between original and translated products. It mirrors
 * the exact approach used in WC_Facebook_Product::prepare_product() to ensure
 * consistency.
 *
 * @since 3.6.0
 */
trait Facebook_Fields_Translation_Trait {

	/**
	 * Get fields that have different values between original and translated products.
	 *
	 * Compares Facebook-specific fields between original and translated products
	 * to determine which fields have been translated.
	 *
	 * @param int    $original_id Original product ID
	 * @param int    $translated_id Translated product ID
	 * @param string $target_language Target language code for permalink translation (optional)
	 * @return array Array of field names that have different values
	 */
	protected function get_translated_fields( int $original_id, int $translated_id, ?string $target_language = null ): array {
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

				// Special handling for permalinks - switch language context to get correct URL
				if ( 'link' === $field_name && $target_language ) {
					// Use integration's language switching methods
					$original_lang = $this->switch_to_language( $target_language );
					$switched_lang = null !== $original_lang;
				}

				$translated_value = $translated_fb_product->$method();

				// Switch back to original language after getting permalink
				if ( 'link' === $field_name && $target_language && isset( $switched_lang ) && $switched_lang && isset( $original_lang ) ) {
					$this->restore_language( $original_lang );
				}

				// Handle array values
				if ( is_array( $original_value ) && is_array( $translated_value ) ) {
					if ( $original_value !== $translated_value ) {
						$translated_fields[] = $field_name;
					}
				} else {
					// Convert to string for comparison
					$original_str = (string) $original_value;
					$translated_str = (string) $translated_value;

					// FIXED: Only require translated value to be non-empty
					// This allows detecting when a field was translated from empty to non-empty
					if ( trim( $original_str ) !== trim( $translated_str ) && ! empty( trim( $translated_str ) ) ) {
						$translated_fields[] = $field_name;
					}
				}
			} catch ( \Exception $e ) {
				// Skip fields that cause errors
				continue;
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
			'name' => 'get_name',
			'description' => 'get_fb_description',
			'short_description' => 'get_fb_short_description',
			'rich_text_description' => 'get_rich_text_description',

			// Images
			'image_id' => 'get_all_image_urls',
			'gallery_image_ids' => 'get_all_image_urls',

			// Video
			'video' => 'get_all_video_urls',

			// Product link
			'link' => 'get_permalink',
		];
	}
}
