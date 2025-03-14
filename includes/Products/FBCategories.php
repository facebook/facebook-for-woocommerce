<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Products;

defined( 'ABSPATH' ) || exit;

/**
 * The main product feed handler.
 *
 * This will eventually replace \WC_Facebook_Product_Feed as we refactor and move its functionality here.
 *
 * @since 1.11.0
 */
class FBCategories {

	/**
	 * List of keys to exclude from general attribute processing.
	 * These are special attributes handled separately by Facebook catalog.
	 *
	 * @var array $keys_to_exclude Associative array of attribute keys to exclude
	 *                            Keys include: brand, color/colour, material, gender,
	 *                            condition, size, age_group, and pattern
	 */
	private $keys_to_exclude = [
		'brand'     => true,
		'color'     => true,
		'material'  => true,
		'gender'    => true,
		'condition' => true,
		'size'      => true,
		'colour'    => true,
		'age_group' => true,
		'pattern'   => true,
	];

	/**
	 * Fetches the attribute from a category using attribute key.
	 *
	 * @param string $category_id          Id of the category for which attribute we want to fetch.
	 * @param string $target_attribute_key The key of the attribute.
	 *
	 * @return null|array Attribute.
	 */
	private function get_attribute( $category_id, $target_attribute_key ) {
		$attributes = $this->get_attributes( $category_id );
		if ( ! is_array( $attributes ) ) {
			return null;
		}

		foreach ( $attributes as $attribute ) {
			if ( $target_attribute_key === $attribute['key'] ) {
				return $attribute;
			}
		}

		return null;
	}

	/**
	 * Checks if $value is correct for a given category attribute.
	 *
	 * @param string $category_id   Id of the category for which attribute we want to check the value.
	 * @param string $attribute_key The key of the attribute.
	 * @param string $value         Value of the attribute.
	 *
	 * @return boolean Is this a valid value for the attribute.
	 */
	public function is_valid_value_for_attribute( $category_id, $attribute_key, $value ) {
		$attribute = $this->get_attribute( $category_id, $attribute_key );

		if ( is_null( $attribute ) ) {
			return false;
		}

		// TODO: can perform more validations here.
		switch ( $attribute['type'] ) {
			case 'enum':
				return in_array( strtolower( $value ), $attribute['enum_values'] );
			case 'boolean':
				return in_array( $value, array( 'yes', 'no' ) );
			default:
				return true;
		}
	}

	/**
	 * Fetches given category.
	 *
	 * @param string $category_id Id of the category we want to fetch.
	 *
	 * @return null|array Null if category was not found or the category array.
	 */
	public function get_category( $category_id ) {
		if ( $this->is_category( $category_id ) ) {
			return GoogleProductTaxonomy::TAXONOMY[ $category_id ];
		} else {
			return null;
		}
	}

	/**
	 * Checks if category is root category - it has no parents.
	 *
	 * @param string $category_id   Id of the category for which attribute we want to check the value.
	 *
	 * @return null|boolean Null if category was not found or boolean that determines if this is a root category or not.
	 */
	public function is_root_category( $category_id ) {
		if ( ! $this->is_category( $category_id ) ) {
			return null;
		}

		$category = $this->get_category( $category_id );
		return empty( $category['parent'] );
	}

	/**
	 * Get the attributes data array for a specific category.
	 *
	 * @param string $category_id Id of the category for which we want to fetch attributes.
	 *
	 * @return null|array Null if no attributes were found or category is invalid, otherwise array of attributes.
	 */
	public function get_attributes( $category_id ) {
		if ( ! $this->is_category( $category_id ) ) {
			return null;
		}

		$all_attributes_data = $this->get_raw_attributes_data();
		if ( ! isset( $all_attributes_data[ $category_id ] ) ) {
			return null;
		}

		$category = $all_attributes_data[ $category_id ];

		if ( ! isset( $category['attributes'] ) || ! is_array( $category['attributes'] ) ) {
			return null;
		}

		$return_attributes = array();
		foreach ( $category['attributes'] as $attribute_hash ) {
			// Get attribute array from the stored hash version
			$attribute = $this->get_attribute_field_by_hash( $attribute_hash );

			// Skip if attribute is invalid or its key is in the exclude list
			if ( ! is_array( $attribute ) || empty( $attribute['key'] ) || isset( $this->keys_to_exclude[ $attribute['key'] ] ) ) {
				continue;
			}

			$return_attributes[] = $attribute;
		}

		return $return_attributes;
	}

	/**
	 * Get the attributes data array for a specific category but if they are not found fallback to the parent categories attributes.
	 *
	 * @param string $category_id Id of the category for which we want to fetch attributes.
	 *
	 * @return null|array Null if no attributes were found or category is invalid, otherwise array of attributes.
	 */
	public function get_attributes_with_fallback_to_parent_category( $category_id ) {
		if ( ! $this->is_category( $category_id ) ) {
			return null;
		}

		$attributes = $this->get_attributes( $category_id );
		if ( $attributes ) {
			return $attributes;
		}

		facebook_for_woocommerce()->log( sprintf( 'Google Product Category to Facebook attributes mapping for category with id: %s not found', $category_id ) );
		// Category has no attributes entry - it should be added but for now check parent category.
		if ( $this->is_root_category( $category_id ) ) {
			return null;
		}

		$parent_category_id         = GoogleProductTaxonomy::TAXONOMY[ $category_id ]['parent'];
		$parent_category_attributes = $this->get_attributes( $parent_category_id );
		if ( $parent_category_attributes ) {
			return $parent_category_attributes;
		}

		// We could check further as we have 3 levels of product categories.
		// This would meant that we have a big problem with mapping - let this fail and log the problem.
		facebook_for_woocommerce()->log( sprintf( 'Google Product Category to Facebook attributes mapping for parent category with id: %s not found', $parent_category_id ) );

		return null;
	}

	/**
	 * Checks if given category id is valid.
	 *
	 * @param string $category_id   Id of the category which we check.
	 *
	 * @return boolean Is the id a valid category id.
	 */
	public function is_category( $category_id ) {
		return isset( GoogleProductTaxonomy::TAXONOMY[ $category_id ] );
	}

	/**
	 * Get all categories.
	 *
	 * @return array All categories data.
	 */
	public function get_categories() {
		return GoogleProductTaxonomy::TAXONOMY;
	}

	/**
	 * Get category attribute field by it's hash.
	 *
	 * @param string $hash
	 *
	 * @return array|null
	 */
	protected function get_attribute_field_by_hash( $hash ) {
		$fields_data = $this->get_raw_attributes_fields_data();

		if ( isset( $fields_data[ $hash ] ) ) {
			return $fields_data[ $hash ];
		} else {
			return null;
		}
	}

	/**
	 * Get the raw category attributes data from the JSON file.
	 *
	 * @return array
	 */
	protected function get_raw_attributes_data() {
		static $data = null;
		if ( null === $data ) {
			$contents = file_get_contents( facebook_for_woocommerce()->get_plugin_path() . '/data/google_category_to_attribute_mapping.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( $contents ) {
				$data = json_decode( $contents, true );
			} else {
				$data = array();
				facebook_for_woocommerce()->log( 'Error reading category attributes JSON data.' );
			}
		}
		return $data;
	}

	/**
	 * Get the raw category attributes fields data from the JSON file.
	 *
	 * @retrun array
	 */
	protected function get_raw_attributes_fields_data() {
		static $data = null;

		if ( null === $data ) {
			$contents = file_get_contents( facebook_for_woocommerce()->get_plugin_path() . '/data/google_category_to_attribute_mapping_fields.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( $contents ) {
				$data = json_decode( $contents, true );
			} else {
				$data = array();
				facebook_for_woocommerce()->log( 'Error reading category attributes fields JSON data.' );
			}
		}

		return $data;
	}
}
