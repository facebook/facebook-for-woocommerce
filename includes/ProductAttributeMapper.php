<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook;

defined( 'ABSPATH' ) || exit;

use WC_Facebook_Product;
use WC_Product;
use WooCommerce\Facebook\Products;

/**
 * Product Attribute Mapper for WooCommerce to Meta.
 *
 * This class provides a comprehensive mapping system for WooCommerce product attributes
 * to Meta catalog fields, enhancing the default mapping with more flexibility
 * and better support for custom attributes.
 *
 * @since 3.0.0
 */
class ProductAttributeMapper {

	/** @var array Standard Facebook fields that WooCommerce attributes can map to */
	private static $standard_facebook_fields = array(
		'size' => array('size'),
		'color' => array('color', 'colour'),
		'pattern' => array('pattern'),
		'material' => array('material'),
		'gender' => array('gender'),
		'age_group' => array('age_group'),
		'brand' => array('brand', 'manufacturer'),
		'condition' => array('condition', 'state'),
		'mpn' => array('mpn', 'manufacturer_part_number'),
		'gtin' => array('gtin', 'upc', 'ean', 'jan', 'isbn'),
		'google_product_category' => array('google_product_category', 'product_category', 'category'),
		'fb_product_category' => array('fb_product_category', 'facebook_product_category', 'fb_category'),
	);

	/** @var array Extended Facebook fields based on Meta commerce platform catalog fields */
	private static $extended_facebook_fields = array(
		'availability' => array('availability', 'stock_status'),
		'description' => array('description', 'product_description'),
		'title' => array('title', 'product_name'),
		'price' => array('price', 'regular_price'),
		'sale_price' => array('sale_price', 'discount_price', 'offer_price'),
		'sale_price_effective_date' => array('sale_price_effective_date', 'sale_dates'),
		'inventory' => array('inventory', 'stock', 'quantity'),
		'quantity_to_sell_on_fb' => array('quantity_to_sell_on_fb', 'fb_quantity'),
		'url' => array('url', 'product_url', 'link'),
		'image_link' => array('image_link', 'image', 'featured_image'),
		'additional_image_link' => array('additional_image_link', 'additional_images', 'gallery'),
		'item_group_id' => array('item_group_id', 'product_group', 'variant_group'),
		'shipping_weight' => array('shipping_weight', 'weight'),
		'shipping' => array('shipping', 'shipping_info'),
		'tax' => array('tax', 'tax_info'),
		'custom_label_0' => array('custom_label_0', 'label0'),
		'custom_label_1' => array('custom_label_1', 'label1'),
		'custom_label_2' => array('custom_label_2', 'label2'),
		'custom_label_3' => array('custom_label_3', 'label3'),
		'custom_label_4' => array('custom_label_4', 'label4'),
	);

	/** @var array Maps WooCommerce attribute naming variations to standardized Meta field names */
	private static $attribute_name_mapping = array(
		// Common naming variations for color
		'product_color' => 'color',
		'item_color' => 'color',
		'color_family' => 'color',
		
		// Common naming variations for size
		'product_size' => 'size',
		'item_size' => 'size',
		'shoe_size' => 'size',
		'clothing_size' => 'size',
		
		// Common naming variations for gender
		'target_gender' => 'gender',
		'product_gender' => 'gender',
		
		// Common naming variations for material
		'product_material' => 'material',
		'fabric' => 'material',
		'item_material' => 'material',
		
		// Common naming variations for pattern
		'product_pattern' => 'pattern',
		'design' => 'pattern',
		
		// Common naming variations for age group
		'product_age_group' => 'age_group',
		'target_age' => 'age_group',
		'age_range' => 'age_group',
		
		// Common naming variations for brand
		'product_brand' => 'brand',
		'manufacturer_name' => 'brand',
		
		// Common naming variations for condition
		'product_condition' => 'condition',
		'item_condition' => 'condition',
	);

	/**
	 * Gets all standardized Meta catalog fields.
	 *
	 * @since 3.0.0
	 *
	 * @return array Array of all supported Meta fields with their variations
	 */
	public static function get_all_facebook_fields() {
		return array_merge(self::$standard_facebook_fields, self::$extended_facebook_fields);
	}

	/**
	 * Check if a WooCommerce attribute maps to a standard Facebook field
	 *
	 * @since 3.0.0
	 *
	 * @param string $attribute_name The WooCommerce attribute name
	 * @return bool|string False if not mapped, or the Facebook field name if mapped
	 */
	public static function check_attribute_mapping($attribute_name) {
		// Clean the attribute name
		$sanitized_name = self::sanitize_attribute_name($attribute_name);
		
		// Check if there's a direct mapping in our attribute_name_mapping
		if (isset(self::$attribute_name_mapping[$sanitized_name])) {
			return self::$attribute_name_mapping[$sanitized_name];
		}
		
		// Check for exact matches in standard fields
		foreach (self::$standard_facebook_fields as $fb_field => $possible_matches) {
			if (in_array($sanitized_name, $possible_matches, true)) {
				return $fb_field;
			}
		}
		
		// If no exact match in standard fields, check if the attribute is a standard field itself
		if (isset(self::$standard_facebook_fields[$sanitized_name])) {
			return $sanitized_name;
		}
		
		return false;
	}

	/**
	 * Sanitizes an attribute name for use in custom data fields
	 *
	 * @since 2.6.0
	 *
	 * @param string $attribute_name The raw attribute name
	 * @return string
	 */
	public static function sanitize_attribute_name( $attribute_name ) {
		// Remove pa_ prefix from taxonomy attributes
		$attribute_name = preg_replace( '/^pa_/', '', $attribute_name );
		
		// Convert spaces and special characters to underscores
		$attribute_name = strtolower( $attribute_name );
		$attribute_name = preg_replace( '/[^a-z0-9_]/', '_', $attribute_name );
		$attribute_name = preg_replace( '/_+/', '_', $attribute_name );
		$attribute_name = trim( $attribute_name, '_' );
		
		return $attribute_name;
	}

	/**
	 * Get all attributes that are not mapped to standard Facebook fields
	 *
	 * @since 3.0.0
	 *
	 * @param WC_Product $product The WooCommerce product
	 * @return array Array of unmapped attributes with 'name' and 'value' keys
	 */
	public static function get_unmapped_attributes(WC_Product $product) {
		$unmapped_attributes = array();
		$attributes = $product->get_attributes();

		foreach ($attributes as $attribute_name => $_) {
			$value = $product->get_attribute($attribute_name);

			if (!empty($value)) {
				// For the purposes of the unmapped attributes feature, we only consider an attribute
				// as "mapped" if it's one of the standard Facebook fields. This prevents
				// attributes like "weight" from being incorrectly categorized as mapped just because
				// they're part of a compound field like "shipping_weight".
				$clean_name = self::sanitize_attribute_name($attribute_name);
				$is_standard_field = false;
				
				// First check for direct mapping in our attribute_name_mapping
				if (isset(self::$attribute_name_mapping[$clean_name])) {
					$is_standard_field = true;
				} else {
					// Then check if it exactly matches one of the standard field options
					foreach (self::$standard_facebook_fields as $fb_field => $possible_matches) {
						if (in_array($clean_name, $possible_matches, true)) {
							$is_standard_field = true;
							break;
						}
					}
				}
				
				if (!$is_standard_field) {
					$unmapped_attributes[] = array(
						'name' => $attribute_name,
						'value' => $value
					);
				}
			}
		}

		return $unmapped_attributes;
	}

	/**
	 * Gets all mapped attributes for a product.
	 *
	 * @since 3.0.0
	 *
	 * @param WC_Product $product The WooCommerce product
	 * @return array Array of mapped attributes with Meta field name as key and attribute value as value
	 */
	public static function get_mapped_attributes(WC_Product $product) {
		$mapped_attributes = array();
		$attributes = $product->get_attributes();
		$default_values = get_option('wc_facebook_attribute_defaults', array());
		
		// Debug log
		$log_file = WP_CONTENT_DIR . '/uploads/fb-product-debug.log';
		$log_message = "ProductAttributeMapper: Starting attribute mapping for product " . $product->get_id() . "\n";
		$log_message .= "Available attributes: " . json_encode(array_keys($attributes)) . "\n";
		file_put_contents($log_file, $log_message, FILE_APPEND);

		// Create a map to track exact slug matches first
		$slug_to_fb_field = array(
			'brand' => 'brand',
			'age-group' => 'age_group',
			'age_group' => 'age_group',
			'agegroup' => 'age_group',
			'gender' => 'gender',
			'material' => 'material',
			'condition' => 'condition',
			'color' => 'color',
			'colour' => 'color',
			'size' => 'size',
			'pattern' => 'pattern',
			'mpn' => 'mpn'
		);

		// Store attributes that have already been processed to avoid duplicates
		$processed_fb_fields = array();

		// PHASE 1: First look for EXACT matches by attribute name or label
		foreach ($attributes as $attribute_name => $_) {
			$value = $product->get_attribute($attribute_name);
			
			if (!empty($value)) {
				// Clean up attribute name for matching
				$clean_name = self::sanitize_attribute_name($attribute_name);
				
				// Check for exact match in our slug mapping
				if (isset($slug_to_fb_field[$clean_name]) && !isset($processed_fb_fields[$slug_to_fb_field[$clean_name]])) {
					$fb_field = $slug_to_fb_field[$clean_name];
					$mapped_attributes[$fb_field] = $value;
					$processed_fb_fields[$fb_field] = true;
				}
			}
		}
		
		// PHASE 2: Now look for partial matches
		foreach ($attributes as $attribute_name => $_) {
			$value = $product->get_attribute($attribute_name);
			
			if (!empty($value) && !empty($attribute_name)) {
				$mapped_field = self::check_attribute_mapping($attribute_name);
				
				// Skip if we've already processed this field or no mapping found
				if ($mapped_field !== false && !isset($processed_fb_fields[$mapped_field])) {
					// Normalize certain field values to conform to Facebook requirements
					switch ($mapped_field) {
						case 'gender':
							$value = self::normalize_gender_value($value);
							break;
						case 'age_group':
							$value = self::normalize_age_group_value($value);
							break;
						case 'condition':
							$value = self::normalize_condition_value($value);
							break;
					}
					
					$mapped_attributes[$mapped_field] = $value;
					$processed_fb_fields[$mapped_field] = true;
				}
			}
		}
		
		// PHASE 3: For fields not found in product attributes, check post meta or use defaults
		$std_fields = array('brand', 'color', 'size', 'pattern', 'material', 'gender', 'age_group', 'condition', 'mpn');
		
		foreach ($std_fields as $field) {
			if (!isset($processed_fb_fields[$field])) {
				// First check if there's a default value
				if (isset($default_values[$field]) && !empty($default_values[$field])) {
					$mapped_attributes[$field] = $default_values[$field];
					$processed_fb_fields[$field] = true;
				}
				
				// Check for alternative storage in dedicated meta fields
				$meta_key = '_wc_facebook_enhanced_catalog_attributes_' . $field;
				$meta_value = $product->get_meta($meta_key, true);
				
				if (!empty($meta_value)) {
					$mapped_attributes[$field] = $meta_value;
					$processed_fb_fields[$field] = true;
				}
			}
		}
		
		return $mapped_attributes;
	}

	/**
	 * Normalizes gender values to Facebook's expected format.
	 *
	 * @since 3.0.0
	 *
	 * @param string $value The original gender value
	 * @return string Normalized gender value
	 */
	public static function normalize_gender_value($value) {
		$value = strtolower(trim($value));
		
		// Map common gender values to Facebook's expected values
		$gender_map = array(
			'men' => WC_Facebook_Product::GENDER_MALE,
			'man' => WC_Facebook_Product::GENDER_MALE,
			'boy' => WC_Facebook_Product::GENDER_MALE,
			'boys' => WC_Facebook_Product::GENDER_MALE,
			'masculine' => WC_Facebook_Product::GENDER_MALE,
			
			'women' => WC_Facebook_Product::GENDER_FEMALE,
			'woman' => WC_Facebook_Product::GENDER_FEMALE,
			'girl' => WC_Facebook_Product::GENDER_FEMALE,
			'girls' => WC_Facebook_Product::GENDER_FEMALE,
			'feminine' => WC_Facebook_Product::GENDER_FEMALE,
			
			'unisex' => WC_Facebook_Product::GENDER_UNISEX,
			'uni sex' => WC_Facebook_Product::GENDER_UNISEX,
			'uni-sex' => WC_Facebook_Product::GENDER_UNISEX,
			'neutral' => WC_Facebook_Product::GENDER_UNISEX,
			'all' => WC_Facebook_Product::GENDER_UNISEX,
		);
		
		return isset($gender_map[$value]) ? $gender_map[$value] : $value;
	}

	/**
	 * Normalizes age group values to Facebook's expected format.
	 *
	 * @since 3.0.0
	 *
	 * @param string $value The original age group value
	 * @return string Normalized age group value
	 */
	public static function normalize_age_group_value($value) {
		$value = strtolower(trim($value));
		
		// Map common age group values to Facebook's expected values
		$age_group_map = array(
			'adult' => WC_Facebook_Product::AGE_GROUP_ADULT,
			'adults' => WC_Facebook_Product::AGE_GROUP_ADULT,
			'grown-up' => WC_Facebook_Product::AGE_GROUP_ADULT,
			'grownup' => WC_Facebook_Product::AGE_GROUP_ADULT,
			
			'all ages' => WC_Facebook_Product::AGE_GROUP_ALL_AGES,
			'everyone' => WC_Facebook_Product::AGE_GROUP_ALL_AGES,
			'any' => WC_Facebook_Product::AGE_GROUP_ALL_AGES,
			
			'teen' => WC_Facebook_Product::AGE_GROUP_TEEN,
			'teens' => WC_Facebook_Product::AGE_GROUP_TEEN,
			'teenager' => WC_Facebook_Product::AGE_GROUP_TEEN,
			'teenagers' => WC_Facebook_Product::AGE_GROUP_TEEN,
			'adolescent' => WC_Facebook_Product::AGE_GROUP_TEEN,
			
			'kid' => WC_Facebook_Product::AGE_GROUP_KIDS,
			'kids' => WC_Facebook_Product::AGE_GROUP_KIDS,
			'child' => WC_Facebook_Product::AGE_GROUP_KIDS,
			'children' => WC_Facebook_Product::AGE_GROUP_KIDS,
			
			'toddler' => WC_Facebook_Product::AGE_GROUP_TODDLER,
			'toddlers' => WC_Facebook_Product::AGE_GROUP_TODDLER,
			
			'infant' => WC_Facebook_Product::AGE_GROUP_INFANT,
			'infants' => WC_Facebook_Product::AGE_GROUP_INFANT,
			'baby' => WC_Facebook_Product::AGE_GROUP_INFANT,
			'babies' => WC_Facebook_Product::AGE_GROUP_INFANT,
			
			'newborn' => WC_Facebook_Product::AGE_GROUP_NEWBORN,
			'newborns' => WC_Facebook_Product::AGE_GROUP_NEWBORN,
		);
		
		return isset($age_group_map[$value]) ? $age_group_map[$value] : $value;
	}

	/**
	 * Normalizes condition values to Facebook's expected format.
	 *
	 * @since 3.0.0
	 *
	 * @param string $value The original condition value
	 * @return string Normalized condition value
	 */
	private static function normalize_condition_value($value) {
		$value = strtolower(trim($value));
		
		// Map common condition values to Facebook's expected values
		$condition_map = array(
			'new' => WC_Facebook_Product::CONDITION_NEW,
			'brand new' => WC_Facebook_Product::CONDITION_NEW,
			'brand-new' => WC_Facebook_Product::CONDITION_NEW,
			'newest' => WC_Facebook_Product::CONDITION_NEW,
			'sealed' => WC_Facebook_Product::CONDITION_NEW,
			
			'used' => WC_Facebook_Product::CONDITION_USED,
			'pre-owned' => WC_Facebook_Product::CONDITION_USED,
			'preowned' => WC_Facebook_Product::CONDITION_USED,
			'pre owned' => WC_Facebook_Product::CONDITION_USED,
			'second hand' => WC_Facebook_Product::CONDITION_USED,
			'secondhand' => WC_Facebook_Product::CONDITION_USED,
			'second-hand' => WC_Facebook_Product::CONDITION_USED,
			
			'refurbished' => WC_Facebook_Product::CONDITION_REFURBISHED,
			'renewed' => WC_Facebook_Product::CONDITION_REFURBISHED,
			'refreshed' => WC_Facebook_Product::CONDITION_REFURBISHED,
			'reconditioned' => WC_Facebook_Product::CONDITION_REFURBISHED,
		);
		
		return isset($condition_map[$value]) ? $condition_map[$value] : $value;
	}

	/**
	 * Adds a custom mapping from a WooCommerce attribute to a Facebook field.
	 *
	 * @since 3.0.0
	 *
	 * @param string $wc_attribute The WooCommerce attribute name
	 * @param string $fb_field The Facebook field to map to
	 * @return bool Whether the mapping was added successfully
	 */
	public static function add_custom_attribute_mapping($wc_attribute, $fb_field) {
		$sanitized_attribute = self::sanitize_attribute_name($wc_attribute);
		
		// Make sure the Facebook field is valid
		$all_fields = array_keys(self::get_all_facebook_fields());
		if (!in_array($fb_field, $all_fields)) {
			return false;
		}
		
		// Add the mapping
		self::$attribute_name_mapping[$sanitized_attribute] = $fb_field;
		return true;
	}

	/**
	 * Removes a custom attribute mapping.
	 *
	 * @since 3.0.0
	 *
	 * @param string $wc_attribute The WooCommerce attribute name
	 * @return bool Whether the mapping was removed successfully
	 */
	public static function remove_custom_attribute_mapping($wc_attribute) {
		$sanitized_attribute = self::sanitize_attribute_name($wc_attribute);
		
		if (isset(self::$attribute_name_mapping[$sanitized_attribute])) {
			unset(self::$attribute_name_mapping[$sanitized_attribute]);
			return true;
		}
		
		return false;
	}

	/**
	 * Sets all custom mappings from an associative array.
	 *
	 * @since 3.0.0
	 *
	 * @param array $mappings Associative array of WooCommerce attribute => Facebook field
	 * @return int Number of successfully added mappings
	 */
	public static function set_custom_attribute_mappings(array $mappings) {
		$success_count = 0;
		
		foreach ($mappings as $wc_attribute => $fb_field) {
			if (self::add_custom_attribute_mapping($wc_attribute, $fb_field)) {
				$success_count++;
			}
		}
		
		return $success_count;
	}

	/**
	 * Gets all currently defined custom attribute mappings.
	 *
	 * @since 3.0.0
	 *
	 * @return array Associative array of custom attribute mappings
	 */
	public static function get_custom_attribute_mappings() {
		return self::$attribute_name_mapping;
	}

	/**
	 * Prepares a product's attributes for Facebook according to the mapping.
	 *
	 * @since 3.0.0
	 *
	 * @param WC_Product $product The WooCommerce product
	 * @return array Array of Facebook-mapped attributes ready for the API
	 */
	public static function prepare_product_attributes_for_facebook(WC_Product $product) {
		$mapped_attributes = self::get_mapped_attributes($product);
		$fb_ready_attributes = array();
		
		// Process each mapped attribute according to Facebook's requirements
		foreach ($mapped_attributes as $fb_field => $value) {
			switch ($fb_field) {
				case 'gender':
				case 'age_group':
				case 'condition':
					// These fields are already normalized
					$fb_ready_attributes[$fb_field] = $value;
					break;
					
				case 'color':
				case 'size':
				case 'pattern':
				case 'material':
				case 'brand':
				case 'mpn':
					// These fields should be trimmed and limited
					$fb_ready_attributes[$fb_field] = substr(trim($value), 0, 100);
					break;
					
				default:
					// For all other fields, just pass the value
					$fb_ready_attributes[$fb_field] = $value;
					break;
			}
		}
		
		return $fb_ready_attributes;
	}

	/**
	 * Gets mapped attributes that correspond to the standard Facebook product fields.
	 *
	 * @since 2.6.0
	 *
	 * @param \WC_Product $product the product object
	 * @return array
	 */
	public static function get_mapped_standard_attributes( \WC_Product $product ) {
		$all_mapped_attributes = self::get_mapped_attributes( $product );
		$standard_field_names = array(
			'brand',
			'condition',
			'gender',
			'color',
			'size',
			'pattern',
			'material',
			'age_group',
		);
		
		$standard_attributes = array();
		foreach ( $standard_field_names as $field ) {
			if ( isset( $all_mapped_attributes[ $field ] ) ) {
				$standard_attributes[ $field ] = $all_mapped_attributes[ $field ];
			}
		}
		
		return $standard_attributes;
	}

	/**
	 * Saves mapped attributes to product meta.
	 *
	 * @since 3.0.0
	 *
	 * @param WC_Product $product The WooCommerce product
	 * @param array $mapped_attributes Array of mapped attributes to save (optional, if not provided will map attributes first)
	 * @return array The mapped attributes that were saved
	 */
	public static function save_mapped_attributes(WC_Product $product, $mapped_attributes = null) {
		if ($mapped_attributes === null) {
			$mapped_attributes = self::get_mapped_attributes($product);
		}
		
		$product_id = $product->get_id();
		
		// Save each mapped attribute to product meta
		foreach ($mapped_attributes as $field_name => $value) {
			switch ($field_name) {
				case 'material':
					update_post_meta($product_id, \WC_Facebook_Product::FB_MATERIAL, $value);
					break;
				case 'color':
					update_post_meta($product_id, \WC_Facebook_Product::FB_COLOR, $value);
					break;
				case 'size':
					update_post_meta($product_id, \WC_Facebook_Product::FB_SIZE, $value);
					break;
				case 'pattern':
					update_post_meta($product_id, \WC_Facebook_Product::FB_PATTERN, $value);
					break;
				case 'brand':
					update_post_meta($product_id, \WC_Facebook_Product::FB_BRAND, $value);
					break;
				case 'mpn':
					update_post_meta($product_id, \WC_Facebook_Product::FB_MPN, $value);
					break;
				case 'age_group':
					update_post_meta($product_id, \WC_Facebook_Product::FB_AGE_GROUP, $value);
					break;
				case 'gender':
					update_post_meta($product_id, \WC_Facebook_Product::FB_GENDER, $value);
					break;
				case 'condition':
					update_post_meta($product_id, \WC_Facebook_Product::FB_PRODUCT_CONDITION, $value);
					break;
			}
		}
		
		return $mapped_attributes;
	}
	
	/**
	 * Gets mapped attributes and saves them to product meta in one operation.
	 * 
	 * @since 3.0.0
	 *
	 * @param WC_Product $product The WooCommerce product
	 * @return array The mapped attributes that were saved
	 */
	public static function get_and_save_mapped_attributes(WC_Product $product) {
		$mapped_attributes = self::get_mapped_attributes($product);
		return self::save_mapped_attributes($product, $mapped_attributes);
	}
} 