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
		$create_discount_inputs = $params['create_discounts_input'];

		$created_discounts = array();
		$errors            = array();
		foreach ( $create_discount_inputs as $create_discount_input ) {
			$coupon = self::create_coupon_from_discount_input( $create_discount_input );
			$coupon->save();
			do_action( 'woocommerce_coupon_options_save', $coupon->get_code(), $coupon );
			$created_discounts[] = self::get_discount_response_data( $coupon );
		}

		return array(
			'discounts' => $created_discounts,
			'errors'    => $errors,
		);
	}

	public function get_discounts( WP_REST_Request $request ): array {
		$params    = $request->get_json_params();
		$errors    = array();
		$discounts = array();

		foreach ( $params['codes'] as $code ) {
			$coupon_id = wc_get_coupon_id_by_code( $code );
			if ( 0 === $coupon_id ) {
				$errors[] = self::get_error_response_object( 'DISCOUNT_NOT_FOUND', '', $code );
			}
			$coupon      = new WC_Coupon( $code );
			$discounts[] = self::get_discount_response_data( $coupon );
		}

		return array(
			'discounts' => $discounts,
			'errors'    => $errors,
		);
	}

	private static function create_coupon_from_discount_input( array $create_discount_input ): WC_Coupon {
		$code        = $create_discount_input['code'];
		$percent_off = $create_discount_input['percent_off'] ?? 0;

		if ( $percent_off > 0 ) {
			$discount_type = 'percent';
			$amount        = $percent_off;
		} else {
			$discount_type = 'fixed_cart';
			$amount        = $create_discount_input['fixed_amount_off'];
		}

		$coupon = new WC_Coupon( $code );
		$coupon->set_props(
			array(
				'discount_type' => $discount_type,
				'amount'        => $amount,
				'usage_limit'   => $create_discount_input['usage_limit'] ?? 1,
				'expiry_date'   => $create_discount_input['expiry_date'] ?? null,
			)
		);
		return $coupon;
	}

	private static function get_discount_response_data( WC_Coupon $coupon ): array {
		$is_percent_off   = 'percent' === $coupon->get_discount_type();
		$percent_off      = $is_percent_off ? $coupon->get_amount() : 0;
		$fixed_amount_off = $is_percent_off ? 0 : $coupon->get_amount();
		$value_type       = $is_percent_off ? 'PERCENTAGE' : 'FIXED_AMOUNT';

		return array(
			'code'             => $coupon->get_code(),
			'percent_off'      => $percent_off,
			'fixed_amount_off' => $fixed_amount_off,
			'value_type'       => $value_type,
			'expiration_time'  => $coupon->get_date_expires(),
			'usage_limit'      => $coupon->get_usage_limit(),
			'usage_count'      => $coupon->get_usage_count(),
		);
	}

	private static function get_error_response_object( string $error_type, string $error_message, string $code ): array {
		return array(
			'error_type'    => $error_type,
			'error_message' => $error_message,
			'code'          => $code,
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
