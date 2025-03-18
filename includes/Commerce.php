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

/**
 * Base handler for Commerce-specific functionality.
 *
 * @since 2.1.0
 */
class Commerce {


	/** @var string option that stores the plugin-level fallback Google product category ID */
	const OPTION_GOOGLE_PRODUCT_CATEGORY_ID = 'wc_facebook_google_product_category_id';


	/**
	 * Gets the plugin-level fallback Google product category ID.
	 *
	 * This will be used when the category or product-level settings don't override it.
	 *
	 * @since 2.1.0
	 *
	 * @return string
	 */
	public function get_default_google_product_category_id() {

		$category_id = get_option( self::OPTION_GOOGLE_PRODUCT_CATEGORY_ID, '' );

		/**
		 * Filters the plugin-level fallback Google product category ID.
		 *
		 * @since 2.1.0
		 *
		 * @param string $category_id default Google product category ID
		 * @param Commerce $commerce commerce handler instance
		 */
		return (string) apply_filters( 'wc_facebook_commerce_default_google_product_category_id', $category_id, $this );
	}


	/**
	 * Updates the plugin-level fallback Google product category ID.
	 *
	 * @since 2.1.0
	 *
	 * @param string $id category ID
	 */
	public function update_default_google_product_category_id( $id ) {

		update_option( self::OPTION_GOOGLE_PRODUCT_CATEGORY_ID, is_string( $id ) ? $id : '' );
	}
}
