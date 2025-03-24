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

use WC_Coupon;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * The checkout permalink.
 *
 * @since 3.3.0
 */
class DiscountManagementAPI {


	/**
	 * Checkout constructor.
	 *
	 * @since 3.3.0
	 */
	public function __construct() {
		$this->add_hooks();
	}

	/**
	 * Adds the necessary action and filter hooks.
	 *
	 * @since 3.3.0
	 */
	public function add_hooks() {
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'fb',
					'discounts',
					array(
						'methods'  => 'POST',
						'callback' => [ $this, 'create_discounts' ],
					)
				);
			}
		);

		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'fb',
					'discounts',
					array(
						'methods'  => 'GET',
						'callback' => [ $this, 'get_discounts' ],
					)
				);
			}
		);

		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'fb',
					'discounts',
					array(
						'methods'  => 'DELETE',
						'callback' => [ $this, 'delete_discounts' ],
					)
				);
			}
		);
	}

	public function create_discounts( WP_REST_Request $request ): array {
		$params                 = $request->get_json_params();
		$create_discount_inputs = $params['create_discount_inputs'];

		foreach ( $create_discount_inputs as $create_discount_input ) {
			$code = $create_discount_input['code'];
			$coupon = new WC_Coupon( $code );
			$coupon->set_props(
				array(
					'code'                 => $code,
					'discount_type'        => 'percent',
					'amount'               => '10',
					'usage_limit'          => 1,
					'usage_limit_per_user' => 1,
				)
			);
			$coupon->save();
			do_action( 'woocommerce_coupon_options_save', $code, $coupon );
		}

		return array( 'data' => '5' );
	}

	public function get_discounts( WP_REST_Request $request ): array {
		$params       = $request->get_json_params();
		$codes_to_get = $params['codes'];

		return array_map(
			function ( $code ) {
				// NEED TO FIGURE OUT HOW TO CHECK IF COUPON IS IN DB
				$coupon_object = new WC_Coupon( $code );
				return $coupon_object->get_code();
			},
			$codes_to_get
		);
	}


	public function delete_discounts( WP_REST_Request $request ): array {
		$params          = $request->get_json_params();
		$codes_to_delete = $params['codes'];

		foreach ( $codes_to_delete as $code ) {
			$coupon_id = wc_get_coupon_id_by_code( $code );
			wp_delete_post( $coupon_id );
		}

		return array( 'status' => 'Ok' );
	}
}
