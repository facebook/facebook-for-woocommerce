<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__ ) . '/fbproductfeed.php';
require_once dirname( __DIR__ ) . '/fbutils.php';

if ( ! class_exists( 'WC_Facebook_Product_Feed_Test' ) ) :
	/**
	 * Mock for Facebook feed class
	 */
	class WC_Facebook_Product_Feed_Test_Mock extends WC_Facebook_Product_Feed {

		/** @var int|null Product post ID for testing */
		public static $product_post_wpid = null;

		/**
		 * Return test product post id.
		 * Don't mess up actual products.
		 *
		 * @return int|null Product post ID
		 */
		public function get_product_wpid() {
			return self::$product_post_wpid;
		}

		/**
		 * Log progress in local log file for testing.
		 * Not to overwhelm DB log to track important signals.
		 *
		 * @param string $msg  Message to log
		 * @param array  $data Optional data to include in the log
		 */
		public function log_feed_progress( $msg, $data = array() ) {
			$msg = empty( $data ) ? $msg : $msg . wp_json_encode( $data );
			WC_Facebookcommerce_Utils::log( 'Test - ' . $msg );
		}
	}

endif;
