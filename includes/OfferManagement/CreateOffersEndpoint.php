<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\OfferManagement;

use WC_Coupon;

/**
 * OfferManagement endpoint used by Meta to create new offers.
 */
class CreateOffersEndpoint extends OfferManagementEndpointBase {

	final protected static function get_method(): string {
		return 'POST';
	}

	final protected function execute_endpoint( array $params ): array {
		$create_offers_data = self::get_params_value_enforced( 'create_offers_data', $params );
		$created_offers     = [];
		foreach ( $create_offers_data as $create_offer_data ) {
			try {
				$coupon = self::create_coupon_from_offer_data( $create_offer_data );
				if ( null === $coupon ) {
					continue;
				}
				$coupon_id = $coupon->save();
				add_post_meta( $coupon_id, self::IS_FACEBOOK_MANAGED_POST_META_KEY, 'yes', true );
				do_action( 'woocommerce_coupon_options_save', $coupon->get_code(), $coupon );
				$created_offers[] = self::get_offer_response_data( $coupon );
			} catch ( \Exception $ex ) {
				$this->add_error( self::get_error_response_data( self::ERROR_OFFER_CREATE_FAILURE, $ex->getMessage(), $create_offer_data['code'] ) );
			}
		}

		return [ 'created_offers' => $created_offers ];
	}

	/**
	 * @param array $create_offer_data
	 * @return ?WC_Coupon A WC_Coupon object if one was able to be created.
	 */
	private function create_coupon_from_offer_data( array $create_offer_data ): ?WC_Coupon {
		$coupon = null;
		$errors = [];

		$code      = $create_offer_data['code'];
		$coupon_id = wc_get_coupon_id_by_code( $code );
		if ( 0 !== $coupon_id ) {
			$errors[] = self::get_error_response_data( self::ERROR_OFFER_CODE_ALREADY_EXISTS, '', $code );
		}

		// A target_type of line_item indicates the coupon applies to products.
		$target_type = $create_offer_data['target_type'];
		if ( 'line_item' !== $target_type ) {
			$errors[] = self::get_error_response_data( self::ERROR_OFFER_CONFIGURATION_NOT_SUPPORTED, 'Only product targeted coupons are supported' );
		}

		$percent_off            = $create_offer_data['percent_off'] ?? 0;
		$fixed_amount_off_input = $create_offer_data['fixed_amount_off'] ?? '';

		if ( ! ( ( 0 === $percent_off ) xor empty( $fixed_amount_off_input ) ) ) {
			$errors[] = self::get_error_response_data( self::ERROR_OFFER_CREATE_FAILURE, 'Exactly one of fixed amount off or percent off is required' );
		}

		if ( $percent_off > 0 ) {
			$discount_type = 'percent';
			$amount        = $percent_off;
		} else {
			// Pass errors reference to add currency parsing errors.
			$coupon_amount_with_errors = $this->parse_currency_amount_string( $fixed_amount_off_input, $errors );
			$discount_type             = 'fixed_cart';
			$amount                    = $coupon_amount_with_errors['amount'];
		}

		// Validation errors for the creation of this coupon.
		if ( empty( $errors ) ) {
			$coupon = new WC_Coupon( $code );
			$coupon->set_props(
				array(
					'discount_type' => $discount_type,
					'amount'        => $amount,
					'usage_limit'   => $create_offer_data['usage_limit'] ?? 1,
				)
			);
			$coupon->set_date_expires( $create_offer_data['end_time'] ?? null );
		}

		$this->add_errors( $errors );
		return $coupon;
	}

	private static function parse_currency_amount_string( string $amount_string, array &$errors ): ?string {
		// In the form of "10.50 USD"
		$amount_string_parts  = explode( ' ', $amount_string );
		$currency             = $amount_string_parts[1];
		$woocommerce_currency = get_woocommerce_currency();
		if ( strtolower( $currency ) !== strtolower( $woocommerce_currency ) ) {
			$errors[] = self::get_error_response_data( self::ERROR_OFFER_CREATE_FAILURE, sprintf( 'Provided currency (%s) does not match store currency (%s)', $currency, $woocommerce_currency ) );
			return null;
		}

		$amount_string = $amount_string_parts[0];
		if ( ! is_numeric( trim( $amount_string ) ) ) {
			$errors[] = self::get_error_response_data( self::ERROR_OFFER_CREATE_FAILURE, sprintf( 'Invalid amount string: %s', $amount_string ) );
			return null;
		}
		return $amount_string;
	}
}
