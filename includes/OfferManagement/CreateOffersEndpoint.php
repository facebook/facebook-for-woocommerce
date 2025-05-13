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
				$coupon->add_meta_data( self::IS_FACEBOOK_MANAGED_METADATA_KEY, 'yes', true );
				$coupon->save();
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
		$offer_class = $create_offer_data['offer_class'];
		if ( 'order' !== $offer_class ) {
			$errors[] = self::get_error_response_data( self::ERROR_OFFER_CONFIGURATION_NOT_SUPPORTED, 'Only product targeted coupons are supported', $code );
		}

		$percent_off            = $create_offer_data['percent_off'] ?? 0;
		$fixed_amount_off_input = $create_offer_data['fixed_amount_off'] ?? null;

		// Exclusive or (XOR) on presence of percent off and fixed amount off.
		if ( ( 0 === $percent_off ) === empty( $fixed_amount_off_input ) ) {
			$errors[] = self::get_error_response_data( self::ERROR_OFFER_CREATE_FAILURE, 'Exactly one of fixed amount off or percent off is required', $code );
		}

		if ( 0 !== $percent_off ) {
			if ( ! is_numeric( $percent_off ) || $percent_off < 0 ) {
				$errors[] = self::get_error_response_data( self::ERROR_OFFER_CREATE_FAILURE, sprintf( 'Invalid percent off: %s', $percent_off ), $code );
			}
			$discount_type = 'percent';
			$amount        = $percent_off;
		} else {
			// Pass errors reference to add currency parsing errors.
			$discount_type = 'fixed_cart';
			$amount        = $this->parse_currency_amount( $fixed_amount_off_input, $errors );
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

			$tags = $create_offer_data['tags'] ?? [];
			$coupon->add_meta_data( self::OFFER_TAGS, $tags );

			if ( isset( $create_offer_data['email'] ) ) {
				$coupon->set_email_restrictions( [ $create_offer_data['email'] ] );
			}
		}

		foreach ( $errors as &$error ) {
			$error['offer_code'] = $code;
		}

		$this->add_errors( $errors );
		return $coupon;
	}


	public static function parse_currency_amount( ?array $amount_with_currency, array &$errors ): ?string {
		if ( null === $amount_with_currency ) {
			return null;
		}

		// Amount with decimal separator
		$amount   = $amount_with_currency['amount'] ?? '';
		$currency = $amount_with_currency['currency'] ?? '';
		if ( ! is_numeric( $amount ) ) {
			$errors[] = self::get_error_response_data( self::ERROR_OFFER_CREATE_FAILURE, sprintf( 'Invalid amount string: %s', $amount ) );
			return null;
		}

		$woocommerce_currency = get_woocommerce_currency();
		if ( strtolower( $currency ) !== strtolower( $woocommerce_currency ) ) {
			$errors[] = self::get_error_response_data( self::ERROR_OFFER_CREATE_FAILURE, sprintf( 'Provided currency (%s) does not match store currency (%s)', $currency, $woocommerce_currency ) );
			return null;
		}

		return $amount;
	}
}
