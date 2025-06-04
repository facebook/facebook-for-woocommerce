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

			$categories = $facebook_category_handler->get_categories();
			
			// Debug logging
			error_log( 'FB DEBUG: Categories count: ' . count( $categories ) );
			error_log( 'FB DEBUG: Input ID: ' . $input_id );

			echo '<div id="wc-facebook-google-product-category-fields"></div>';

			// Embed the initialization script directly in HTML to bypass wp_add_inline_script issues
			echo '<script type="text/javascript">';
			echo 'console.log("FB DEBUG: Direct HTML script starting...");';
			echo 'jQuery(document).ready(function($) {';
			echo '  console.log("FB DEBUG: Direct HTML script jQuery ready");';
			echo '  if (typeof WC_Facebook_Google_Product_Category_Fields === "undefined") {';
			echo '    console.error("FB DEBUG: WC_Facebook_Google_Product_Category_Fields class not found!");';
			echo '    return;';
			echo '  }';
			echo '  console.log("FB DEBUG: Initializing Google Product Category Fields via direct HTML");';
			echo '  try {';
			echo '    window.wc_facebook_google_product_category_fields = new WC_Facebook_Google_Product_Category_Fields(' . wp_json_encode( $categories ) . ', "' . esc_js( $input_id ) . '");';
			echo '    console.log("FB DEBUG: Direct HTML initialization complete successfully");';
			echo '  } catch(error) {';
			echo '    console.error("FB DEBUG: Error during direct HTML initialization:", error);';
			echo '  }';
			echo '});';
			echo '</script>';
			
			error_log( 'FB DEBUG: Direct HTML script embedded successfully' );
		} else {
			error_log( 'FB DEBUG: facebook_category_handler is null!' );
		}
	}
}
