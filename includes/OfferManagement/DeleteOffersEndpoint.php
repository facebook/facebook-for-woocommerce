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

/**
 * OfferManagement endpoint used to delete Meta managed offers.
 */
class DeleteOffersEndpoint extends OfferManagementEndpointBase {

	final protected static function get_method(): string {
		return 'DELETE';
	}

	final protected function execute_endpoint( array $params ): array {
		$codes_to_delete = self::get_params_value_enforced( 'offer_codes', $params );
		$deleted_codes   = [];
		foreach ( $codes_to_delete as $code ) {
			try {
				$coupon_id          = wc_get_coupon_id_by_code( $code );
				$is_managed_by_meta = 'yes' === get_post_meta( $coupon_id, self::IS_FACEBOOK_MANAGED_METADATA_KEY, true );
				$offer_missing      = 0 === $coupon_id;
				if ( $offer_missing || ! $is_managed_by_meta ) {
					$this->add_error( self::get_error_response_data( self::ERROR_OFFER_NOT_FOUND, '', $code ) );
					continue;
				}
				wp_delete_post( $coupon_id );
				$deleted_codes[] = $code;
			} catch ( \Exception $ex ) {
				$this->add_error( self::get_error_response_data( self::ERROR_OFFER_DELETE_FAILURE, $ex->getMessage(), $code ) );
			}
		}

		return [ 'deleted_offer_codes' => $deleted_codes ];
	}
}
