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

use Doctrine\Instantiator\Exception\UnexpectedValueException;
use FG\ASN1\Exception\NotImplementedException;
use Firebase\JWT\BeforeValidException;
use WC_Coupon;
use WooCommerce\Facebook\API\Exceptions\Request_Limit_Reached;
use WooCommerce\Facebook\FBSignedData\FBPublicKey;
use WooCommerce\Facebook\FBSignedData\PublicKeyStorageHelper;
use WooCommerce\Facebook\Framework\Api\Exception;
use WP_REST_Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;


defined( 'ABSPATH' ) || exit;

/**
 * The checkout permalink.
 *
 * @since 3.3.0
 */
class OfferManagementAPI {
	/**
	 * Error types defined by Facebook spec
	 */
	const ERROR_OFFER_MANAGEMENT_ERROR                   = 'OFFER_MANAGEMENT_ERROR';
	const ERROR_JWT_NOT_FOUND                            = 'JWT_NOT_FOUND';
	const ERROR_JWT_DECODE_FAILURE                       = 'JWT_DECODE_FAILURE';
	const ERROR_JWT_EXPIRED                              = 'JWT_EXPIRED';
	const ERROR_OFFER_MANAGEMENT_DISABLED                = 'OFFER_MANAGEMENT_DISABLED';
	const ERROR_OFFER_CREATE_FAILURE                     = 'OFFER_CREATE_FAILURE';
	const ERROR_OFFER_CREATE_CONFIGURATION_NOT_SUPPORTED = 'OFFER_CREATE_CONFIGURATION_NOT_SUPPORTED';
	const ERROR_OFFER_DELETE_FAILURE                     = 'OFFER_DELETE_FAILURE';
	const ERROR_OFFER_CREATE_CODE_ALREADY_EXISTS         = 'OFFER_CREATE_CODE_ALREADY_EXISTS';
	const ERROR_OFFER_NOT_FOUND                          = 'OFFER_NOT_FOUND';

	const API_NAMESPACE = 'fb_api';

	/**
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
					self::API_NAMESPACE,
					'offers',
					[
						'methods'             => 'POST',
						'callback'            => [ $this, 'create_offers' ],
						'permission_callback' => '__return_true',
					]
				);
			}
		);

		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					self::API_NAMESPACE,
					'offers',
					[
						'methods'             => 'GET',
						'callback'            => [ $this, 'get_offers' ],
						'permission_callback' => '__return_true',
					]
				);
			}
		);

		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					self::API_NAMESPACE,
					'offers',
					[
						'methods'             => 'DELETE',
						'callback'            => [ $this, 'delete_offers' ],
						'permission_callback' => '__return_true',
					]
				);
			}
		);
	}

	public function create_offers( WP_REST_Request $request ): array {
		try {
			return $this->create_offers_impl( $request );
		} catch ( \Exception $ex ) {
			$error = self::get_error_response_object( self::ERROR_OFFER_MANAGEMENT_ERROR, $ex->getMessage() );
			return self::returned_offers_response( [], [ $error ] );
		}
	}

	public function get_offers( WP_REST_Request $request ): array {
		try {
			return $this->get_offers_impl( $request );
		} catch ( \Exception $ex ) {
			$error = self::get_error_response_object( self::ERROR_OFFER_MANAGEMENT_ERROR, $ex->getMessage() );
			return self::returned_offers_response( [], [ $error ] );
		}
	}

	public function delete_offers( WP_REST_Request $request ): array {
		try {
			return $this->delete_offers_impl( $request );
		} catch ( \Exception $ex ) {
			$error = self::get_error_response_object( self::ERROR_OFFER_MANAGEMENT_ERROR, $ex->getMessage() );
			return self::delete_offers_response( [], [ $error ] );
		}
	}

	private function create_offers_impl( WP_REST_Request $request ): array {
		$params_with_errors = self::get_request_params( $request );
		$params             = $params_with_errors['params'];
		if ( null === $params ) {
			return self::returned_offers_response( [], [ $params_with_errors['errors'] ] );
		}

		$create_offers_data = self::get_params_value_enforced( 'create_offers_data', $params );
		$created_offers     = [];
		$errors             = [];
		foreach ( $create_offers_data as $create_offer_data ) {
			try {
				$coupon_with_errors = self::create_coupon_from_offer_data( $create_offer_data );
				$coupon             = $coupon_with_errors['coupon'];
				if ( null === $coupon ) {
					$errors = array_merge( $errors, $coupon_with_errors['errors'] );
					continue;
				}
				$coupon->save();
				do_action( 'woocommerce_coupon_options_save', $coupon->get_code(), $coupon );
				$created_offers[] = self::get_offer_response_data( $coupon );
			} catch ( \Exception $ex ) {
				$errors[] = self::get_error_response_object( self::ERROR_OFFER_CREATE_FAILURE, $ex->getMessage(), $create_offer_data['code'] );
			}
		}
		return self::returned_offers_response( $created_offers, $errors );
	}


	private function get_offers_impl( WP_REST_Request $request ): array {
		$params_with_errors = self::get_request_params( $request );
		$params             = $params_with_errors['params'];
		if ( null === $params ) {
			return self::returned_offers_response( [], [ $params_with_errors['errors'] ] );
		}

		$codes_to_fetch = self::get_params_value_enforced( 'offer_codes', $params );
		$errors         = [];
		$offers         = [];
		foreach ( $codes_to_fetch as $code ) {
			$coupon_id = wc_get_coupon_id_by_code( $code );
			if ( 0 === $coupon_id ) {
				$errors[] = self::get_error_response_object( self::ERROR_OFFER_NOT_FOUND, '', $code );
			} else {
				$coupon   = new WC_Coupon( $code );
				$offers[] = self::get_offer_response_data( $coupon );
			}
		}

		return self::returned_offers_response( $offers, $errors );
	}

	private function delete_offers_impl( WP_REST_Request $request ): array {
		$params_with_errors = self::get_request_params( $request );
		$params             = $params_with_errors['params'];
		if ( null === $params ) {
			return self::delete_offers_response( [], [ $params_with_errors['errors'] ] );
		}

		$codes_to_delete = self::get_params_value_enforced( 'offer_codes', $params );
		$deleted_codes   = [];
		$errors          = [];
		foreach ( $codes_to_delete as $code ) {
			try {
				$coupon_id = wc_get_coupon_id_by_code( $code );
				if ( 0 === $coupon_id ) {
					$errors[] = self::get_error_response_object( self::ERROR_OFFER_NOT_FOUND, '', $code );
				} else {
					wp_delete_post( $coupon_id );
					$deleted_codes[] = $code;
				}
			} catch ( \Exception $ex ) {
				$errors[] = self::get_error_response_object( self::ERROR_OFFER_DELETE_FAILURE, $ex->getMessage(), $code );
			}
		}
		return self::delete_offers_response( $deleted_codes, $errors );
	}


	private static function returned_offers_response( array $offers, array $errors ): array {
		return [
			'offers' => $offers,
			'errors' => $errors,
		];
	}

	private static function delete_offers_response( array $deleted_codes, array $errors ): array {
		return [
			'deleted_offer_codes' => $deleted_codes,
			'errors'              => $errors,
		];
	}

	/**
	 * @param array $create_offer_data
	 * @return array An array containing WC_Coupon or error
	 * @throws NotImplementedException Functionality not implemented.
	 */
	private static function create_coupon_from_offer_data( array $create_offer_data ): array {
		$errors = [];
		$coupon = null;

		$code      = $create_offer_data['code'];
		$coupon_id = wc_get_coupon_id_by_code( $code );
		if ( 0 !== $coupon_id ) {
			$errors[] = self::get_error_response_object( self::ERROR_OFFER_CREATE_CODE_ALREADY_EXISTS, '', $code );
		}

		// A target_type of line_item indicates the coupon applies to products.
		$target_type = $create_offer_data['target_type'];
		if ( 'line_item' !== $target_type ) {
			$errors[] = self::get_error_response_object( self::ERROR_OFFER_CREATE_CONFIGURATION_NOT_SUPPORTED, 'Only product targeted coupons are supported' );
		}

		$percent_off            = $create_offer_data['percent_off'] ?? 0;
		$fixed_amount_off_input = $create_offer_data['fixed_amount_off'] ?? '';

		if ( ! ( ( 0 === $percent_off ) xor empty( $fixed_amount_off_input ) ) ) {
			$errors[] = self::get_error_response_object( self::ERROR_OFFER_CREATE_FAILURE, 'Exactly one of fixed amount off or percent off is required' );
		}

		if ( $percent_off > 0 ) {
			$discount_type = 'percent';
			$amount        = $percent_off;
		} else {
			$coupon_amount_with_errors = self::parse_currency_amount_string( $fixed_amount_off_input );
			$errors                    = array_merge( $errors, $coupon_amount_with_errors['errors'] );
			$discount_type             = 'fixed_cart';
			$amount                    = $coupon_amount_with_errors['amount'];
		}

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
		return [
			'coupon' => $coupon,
			'errors' => $errors,
		];
	}

	private static function get_offer_response_data( WC_Coupon $coupon ): array {
		$is_percent_off   = 'percent' === $coupon->get_discount_type();
		$percent_off      = $is_percent_off ? $coupon->get_amount() : 0;
		$fixed_amount_off = $is_percent_off ? 0 : $coupon->get_amount();
		$target_type      = 'line_item';

		return array(
			'code'             => $coupon->get_code(),
			'percent_off'      => $percent_off,
			'fixed_amount_off' => $fixed_amount_off,
			'target_type'      => $target_type,
			'end_time'         => $coupon->get_date_expires() ? $coupon->get_date_expires()->getTimestamp() : 0,
			'usage_limit'      => $coupon->get_usage_limit() ?? 0,
			'usage_count'      => $coupon->get_usage_count() ?? 0,
		);
	}

	private static function parse_currency_amount_string( string $amount_string ): array {
		$amount_with_errors = [
			'amount' => null,
			'errors' => [],
		];
		// In the form of "10.50 USD"
		$amount_string_parts  = explode( ' ', $amount_string );
		$currency             = $amount_string_parts[1];
		$woocommerce_currency = get_woocommerce_currency();
		if ( strtolower( $currency ) !== strtolower( $woocommerce_currency ) ) {
			$amount_with_errors['errors'][] = self::get_error_response_object( self::ERROR_OFFER_CREATE_FAILURE, sprintf( 'Provided currency (%s) does not match store currency (%s)', $currency, $woocommerce_currency ) );
			return $amount_with_errors;
		}
		$amount_with_errors['amount'] = $amount_string_parts[0];
		return $amount_with_errors;
	}

	private static function get_request_params( WP_REST_Request $request ): array {
		$params_with_errors = [
			'errors' => [],
			'params' => null,
		];

		// This will retrieve the 'jwt_params' param from either the request url or from the body (POST)
		$jwt = $request->get_params()['jwt_params'] ?? null;
		if ( null === $jwt ) {
			$params_with_errors['errors'][] =
				self::get_error_response_object( self::ERROR_JWT_NOT_FOUND );
			return $params_with_errors;
		}

		try {
			$params_with_errors['params'] = self::decode_jwt_with_retries( $jwt );
		} catch ( ExpiredException $ex ) {
			$params_with_errors['errors'][] =
				self::get_error_response_object(
					self::ERROR_JWT_EXPIRED,
					$ex->getMessage(),
				);
		} catch ( \Exception $ex ) {
			$params_with_errors['errors'][] =
				self::get_error_response_object(
					self::ERROR_JWT_DECODE_FAILURE,
					$ex->getMessage(),
				);
		}

		return $params_with_errors;
	}

	/**
	 * Attempts to decode the JWT using stored public keys. We have multiple retries in this flow.
	 * We will attempt to use both current and next public keys, and if those don't work we will retrieve public keys
	 * and retry refreshed current and next keys
	 *
	 * @param string $jwt
	 * @return array
	 * @throws Exception Throws any non-swallowed exception during JWT decoding.
	 * @throws Request_Limit_Reached If request to get public key is rate limited.
	 */
	private static function decode_jwt_with_retries( string $jwt ): array {
		$public_key = PublicKeyStorageHelper::get_current_public_key();
		$params     = self::decode_jwt_retryable( $jwt, $public_key );
		if ( null !== $params ) {
			return $params;
		}

		$public_key = PublicKeyStorageHelper::get_next_public_key();
		$params     = self::decode_jwt_retryable( $jwt, $public_key );
		if ( null !== $params ) {
			return $params;
		}

		// Extract the project header to query for refreshed keys
		$b64_header   = explode( '.', $jwt )[0];
		$header_array = json_decode( JWT::urlsafeB64Decode( $b64_header ), true );
		$key_project  = $header_array['key_project'];

		PublicKeyStorageHelper::request_and_store_public_key( facebook_for_woocommerce(), $key_project );
		$public_key = PublicKeyStorageHelper::get_current_public_key();
		$params     = self::decode_jwt_retryable( $jwt, $public_key );
		if ( null !== $params ) {
			return $params;
		}

		// This is the last attempt, so the params result is no longer nullable (We no longer swallow exceptions)
		$public_key = PublicKeyStorageHelper::get_next_public_key();
		$params     = self::decode_jwt_final( $jwt, $public_key );

		// json_encode -> json_decode converts nested stdClass objects into nested arrays
		return json_decode( wp_json_encode( $params ), true );
	}

	/**
	 * Swallows exceptions that could result from an invalid stored key so that we can retry with an alternate key.
	 *
	 * @param string           $jwt
	 * @param null|FBPublicKey $fb_public_key
	 * @throws ExpiredException Re-throws if JWT is expired.
	 * @throws BeforeValidException Re-throws if JWT is not valid yet.
	 * @throws UnexpectedValueException Re-throws if the formatting of the JWT headers/body is invalid.
	 * @return array|null
	 */
	private static function decode_jwt_retryable( string $jwt, ?FBPublicKey $fb_public_key ): ?array {
		if ( null === $fb_public_key ) {
			return null;
		}

		try {
			return self::decode_jwt_with_public_key( $jwt, $fb_public_key );
		} catch ( ExpiredException | BeforeValidException $ex ) {
			throw $ex;
		} catch ( UnexpectedValueException $ex ) {
			if ( $ex->getCode() !== 'Incorrect key for this algorithm' ) {
				throw $ex;
			}
		} catch ( \Exception $ex ) {
			return null;
		}
	}

	private static function decode_jwt_final( string $jwt, FBPublicKey $fb_public_key ): array {
		return self::decode_jwt_with_public_key( $jwt, $fb_public_key );
	}

	private static function decode_jwt_with_public_key( string $jwt, FBPublicKey $fb_public_key ): array {
		$jwt_key  = new Key( $fb_public_key->get_key(), $fb_public_key->get_algorithm() );
		$jwt_data = JWT::decode( $jwt, $jwt_key );
		return json_decode( wp_json_encode( $jwt_data ), true );
	}


	private static function get_error_response_object( string $error_type, string $error_message = '', ?string $offer_code = null ): array {
		$error = [
			'error_type'    => $error_type,
			'error_message' => $error_message,
		];
		if ( $offer_code ) {
			$error['offer_code'] = $offer_code;
		}
		return $error;
	}

	private static function get_params_value_enforced( string $field_name, array $params ) {
		if ( array_key_exists( $field_name, $params ) ) {
			return $params[ $field_name ];
		}
		throw new \OutOfBoundsException( sprintf( 'Field: %s does not exist in request params. Params fields: %s', $field_name, wp_json_encode( array_keys( $params ) ) ) );
	}
}
