<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Google product category field.
 *
 * @since 2.1.0
 */
class Google_Product_Category_Field {

	/**
	 * Instantiates the JS handler for the Google product category field.
	 *
	 * @since 2.1.0
	 *
	 * @param string $input_id element that should receive the latest concrete category ID value.
	 */
	public function render( $input_id ) {
		$facebook_category_handler = facebook_for_woocommerce()->get_facebook_category_handler();
		
		if ( $facebook_category_handler ) {
			$facebook_category_fields = sprintf(
				"window.wc_facebook_google_product_category_fields = new WC_Facebook_Google_Product_Category_Fields( %s, '%s' );",
				wp_json_encode( $facebook_category_handler->get_categories() ),
				esc_js( $input_id )
			);
			wc_enqueue_js( $facebook_category_fields );
		}
	}
}
