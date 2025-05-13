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
 * OfferManagement endpoint used to retrieve created offers.
 */
class GetOffersEndpoint extends OfferManagementEndpointBase {

	final protected static function get_method(): string {
		return 'GET';
	}

	final protected function execute_endpoint( array $params ): array {
		$codes_to_fetch = self::get_params_value_enforced( 'offer_codes', $params );
		$offers         = [];

		foreach ( $codes_to_fetch as $code ) {
			$coupon_id          = wc_get_coupon_id_by_code( $code );
			$is_managed_by_meta = 'yes' === get_post_meta( $coupon_id, self::IS_FACEBOOK_MANAGED_METADATA_KEY, true );
			$offer_missing      = 0 === $coupon_id;
			if ( $offer_missing || ! $is_managed_by_meta ) {
				$this->add_error( self::get_error_response_data( self::ERROR_OFFER_NOT_FOUND, '', $code ) );
				continue;
			}
			$coupon   = new WC_Coupon( $code );
			$offers[] = self::get_offer_response_data( $coupon );
		}

		return self::get_response_data( $offers );
	}


	private static function get_response_data( array $offers ): array {
		return [ 'offers' => $offers ];
	}

	protected static function get_empty_response_data() {
		return self::get_response_data( [] );
	}
}
