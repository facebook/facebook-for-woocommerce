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
use WooCommerce\Facebook\Feed\AbstractFeedFileWriter;
use WP_REST_Request;
use Firebase\JWT\ExpiredException;


/**
 * Based endpoint which offer management endpoints extend.
 */
abstract class OfferManagementEndpointBase {
	/**
	 * Error types defined by Facebook spec
	 */
	const ERROR_OFFER_MANAGEMENT_ERROR            = 'OFFER_MANAGEMENT_ERROR';
	const ERROR_JWT_NOT_FOUND                     = 'JWT_NOT_FOUND';
	const ERROR_JWT_DECODE_FAILURE                = 'JWT_DECODE_FAILURE';
	const ERROR_JWT_EXPIRED                       = 'JWT_EXPIRED';
	const ERROR_OFFER_MANAGEMENT_DISABLED         = 'OFFER_MANAGEMENT_DISABLED';
	const ERROR_OFFER_CREATE_FAILURE              = 'OFFER_CREATE_FAILURE';
	const ERROR_OFFER_CONFIGURATION_NOT_SUPPORTED = 'OFFER_CONFIGURATION_NOT_SUPPORTED';
	const ERROR_OFFER_DELETE_FAILURE              = 'OFFER_DELETE_FAILURE';
	const ERROR_OFFER_CODE_ALREADY_EXISTS         = 'OFFER_CODE_ALREADY_EXISTS';
	const ERROR_OFFER_NOT_FOUND                   = 'OFFER_NOT_FOUND';
	const ERROR_OFFER_NOT_MANAGED_BY_FACEBOOK     = 'OFFER_NOT_MANAGED_BY_FACEBOOK';

	const API_NAMESPACE                     = 'fb_api';
	const ROUTE                             = 'offers';
	const IS_FACEBOOK_MANAGED_POST_META_KEY = 'fb_is_facebook_managed';


	/**
	 * A list of errors encountered while executing the request
	 *
	 * @var array
	 */
	private array $errors;

	public function __construct() {
		$this->errors = [];
	}


	protected function add_error( array $error ): void {
		$this->errors[] = $error;
	}

	protected function add_errors( array $errors ): void {
		$this->errors = array_merge( $this->errors, $errors );
	}

	protected function get_errors(): array {
		return $this->errors;
	}

	final public static function register_endpoints(): void {
		( new CreateOffersEndpoint() )->register_endpoint();
		( new GetOffersEndpoint() )->register_endpoint();
		( new DeleteOffersEndpoint() )->register_endpoint();
	}

	final private function register_endpoint() {
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					self::API_NAMESPACE,
					self::ROUTE,
					[
						'methods'             => static::get_method(),
						'callback'            => [ $this, 'execute_with_validation' ],
						'permission_callback' => '__return_true',
					]
				);
			}
		);
	}

	abstract protected static function get_method(): string;

	abstract protected function execute_endpoint( array $params );

	public function execute_with_validation( WP_REST_Request $request ): array {
		$offer_management_enabled = facebook_for_woocommerce()->get_integration()->is_facebook_managed_coupons_enabled();
		if ( ! $offer_management_enabled ) {
			$this->add_error( self::get_error_response_data( self::ERROR_OFFER_MANAGEMENT_DISABLED, '' ) );
			return $this->get_request_response( [] );
		}

		$params = self::get_decoded_request_params( $request );
		if ( null === $params ) {
			return $this->get_request_response( [] );
		}

		try {
			$data = $this->execute_endpoint( $params );
			return $this->get_request_response( $data );
		} catch ( \Exception $ex ) {
			$this->add_error( self::get_error_response_data( self::ERROR_OFFER_MANAGEMENT_ERROR, $ex->getMessage() ) );
			return $this->get_request_response( [] );
		}
	}

	protected function get_decoded_request_params( WP_REST_Request $request ): ?array {
		$decoded_params = null;
		// This will retrieve the 'jwt_params' param from either the request url or from the body (POST)
		$jwt = $request->get_params()['jwt_params'] ?? null;
		if ( null === $jwt ) {
			$this->add_error( self::get_error_response_data( self::ERROR_JWT_NOT_FOUND ) );
			return null;
		}

		try {
			$decoded_params = RequestVerification::decode_jwt_with_retries( $jwt );
		} catch ( ExpiredException $ex ) {
			$this->add_error( self::get_error_response_data( self::ERROR_JWT_EXPIRED, $ex->getMessage() ) );
		} catch ( \Exception $ex ) {
			$this->add_error( self::get_error_response_data( self::ERROR_JWT_DECODE_FAILURE, $ex->getMessage() ) );
		}

		return $decoded_params;
	}


	protected static function get_offer_response_data( WC_Coupon $coupon ): array {
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

	protected static function get_error_response_data( string $error_type, string $error_message = '', ?string $offer_code = null ): array {
		$error = [
			'error_type'    => $error_type,
			'error_message' => $error_message,
		];
		if ( $offer_code ) {
			$error['offer_code'] = $offer_code;
		}
		return $error;
	}

	protected static function get_params_value_enforced( string $field_name, array $params ) {
		if ( array_key_exists( $field_name, $params ) ) {
			return $params[ $field_name ];
		}
		throw new \OutOfBoundsException( sprintf( 'Field: %s does not exist in request params. Params fields: %s', $field_name, wp_json_encode( array_keys( $params ) ) ) );
	}


	protected static function validate_offer_management_enabled(): ?array {
		$offer_management_enabled = facebook_for_woocommerce()->get_integration()->is_facebook_managed_coupons_enabled();
		if ( ! $offer_management_enabled ) {
			return self::get_error_response_data( self::ERROR_OFFER_MANAGEMENT_DISABLED, '' );
		}
		return null;
	}

	private function get_request_response( $response_data ): array {
		return [
			'data'   => $response_data,
			'errors' => $this->errors,
		];
	}
}
