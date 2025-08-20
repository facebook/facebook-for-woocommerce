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
use WP_REST_Request;
use WP_REST_Response;
use Firebase\JWT\ExpiredException;
use WooCommerce\Facebook\RolloutSwitches;

/**
 * Base endpoint which offer management endpoints extend.
 */
abstract class OfferManagementEndpointBase {
	/**
	 * Error types defined by Facebook spec
	 */
	const ERROR_CATALOG_ID_MISMATCH               = 'CATALOG_ID_MISMATCH';
	const ERROR_JWT_DECODE_FAILURE                = 'JWT_DECODE_FAILURE';
	const ERROR_JWT_EXPIRED                       = 'JWT_EXPIRED';
	const ERROR_JWT_NOT_FOUND                     = 'JWT_NOT_FOUND';
	const ERROR_OFFER_CODE_ALREADY_EXISTS         = 'OFFER_CODE_ALREADY_EXISTS';
	const ERROR_OFFER_CONFIGURATION_NOT_SUPPORTED = 'OFFER_CONFIGURATION_NOT_SUPPORTED';
	const ERROR_OFFER_CREATE_FAILURE              = 'OFFER_CREATE_FAILURE';
	const ERROR_OFFER_DELETE_FAILURE              = 'OFFER_DELETE_FAILURE';
	const ERROR_OFFER_MANAGEMENT_DISABLED         = 'OFFER_MANAGEMENT_DISABLED';
	const ERROR_OFFER_MANAGEMENT_ERROR            = 'OFFER_MANAGEMENT_ERROR';
	const ERROR_OFFER_NOT_FOUND                   = 'OFFER_NOT_FOUND';

	const API_NAMESPACE                    = 'fb_api';
	const ROUTE                            = 'offers';
	const IS_FACEBOOK_MANAGED_METADATA_KEY = 'wc_facebook_is_facebook_managed';
	const OFFER_TAGS                       = 'wc_facebook_offer_tags';

	const HTTP_OK           = 200;
	const HTTP_BAD_REQUEST  = 400;
	const HTTP_UNAUTHORIZED = 401;
	const HTTP_FORBIDDEN    = 403;
	const HTTP_ERROR        = 500;


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
		CreateOffersEndpoint::register_endpoint();
		GetOffersEndpoint::register_endpoint();
		DeleteOffersEndpoint::register_endpoint();
	}

	private static function register_endpoint() {
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					self::API_NAMESPACE,
					self::ROUTE,
					[
						'methods'             => static::get_method(),
						'callback'            => [ static::class, 'execute_static' ],
						'permission_callback' => '__return_true',
					],
				);
			}
		);
	}

	abstract protected static function get_method(): string;

	abstract protected function execute_endpoint( array $params ): array;

	final public static function execute_static( WP_REST_Request $request ): WP_REST_Response {
		$endpoint = new static();
		return $endpoint->execute_with_validation( $request );
	}

	private function execute_with_validation( WP_REST_Request $request ): WP_REST_Response {
		$fb_integration                       = facebook_for_woocommerce()->get_integration();
		$offer_management_enabled_by_merchant = $fb_integration->is_facebook_managed_coupons_enabled();
		if ( ! $offer_management_enabled_by_merchant ) {
			$this->add_error( self::get_error_response_data( self::ERROR_OFFER_MANAGEMENT_DISABLED, 'Not enabled by seller' ) );
			return $this->get_request_response( [], self::HTTP_FORBIDDEN );
		}

		$offer_management_enabled_by_fb = facebook_for_woocommerce()->get_rollout_switches()->is_switch_enabled(
			RolloutSwitches::SWITCH_OFFER_MANAGEMENT_ENABLED
		);
		if ( ! $offer_management_enabled_by_fb ) {
			$this->add_error( self::get_error_response_data( self::ERROR_OFFER_MANAGEMENT_DISABLED, 'Not enabled by Meta' ) );
			return $this->get_request_response( [], self::HTTP_FORBIDDEN );
		}

		$jwt = $request->get_params()['jwt_params'] ?? null;
		if ( null === $jwt ) {
			$this->add_error( self::get_error_response_data( self::ERROR_JWT_NOT_FOUND ) );
			return $this->get_request_response( [], self::HTTP_BAD_REQUEST );
		}

		$params = $this->get_decoded_request_params( $jwt );
		if ( null === $params ) {
			return $this->get_request_response( [], self::HTTP_UNAUTHORIZED );
		}

		$jwt_catalog_id = $params['aud'] ?? '';
		if ( empty( $jwt_catalog_id ) || $jwt_catalog_id !== $fb_integration->get_product_catalog_id() ) {
			$this->add_error(
				self::get_error_response_data(
					self::ERROR_CATALOG_ID_MISMATCH,
					sprintf( 'Platform Catalog ID: %s, Request Catalog ID: %s', $fb_integration->get_product_catalog_id(), $jwt_catalog_id )
				)
			);
			return $this->get_request_response( [], self::HTTP_FORBIDDEN );
		}

		try {
			$response_data = $this->execute_endpoint( $params['payload'] );
			return $this->get_request_response( $response_data );
		} catch ( \Exception $ex ) {
			$this->add_error( self::get_error_response_data( self::ERROR_OFFER_MANAGEMENT_ERROR, $ex->getMessage() ) );
			return $this->get_request_response( [], self::HTTP_ERROR );
		}
	}

	protected function get_decoded_request_params( string $jwt ): ?array {
		try {
			$decoded_params = RequestVerification::decode_jwt_with_retries( $jwt );
		} catch ( ExpiredException $ex ) {
			$this->add_error( self::get_error_response_data( self::ERROR_JWT_EXPIRED ) );
			return null;
		} catch ( \Exception $ex ) {
			$this->add_error( self::get_error_response_data( self::ERROR_JWT_DECODE_FAILURE, $ex->getMessage() ) );
			return null;
		}
		return $decoded_params;
	}


	protected static function get_offer_response_data( WC_Coupon $coupon ): array {
		$is_percent_off   = 'percent' === $coupon->get_discount_type();
		$percent_off      = $is_percent_off ? (int) $coupon->get_amount() : null;
		$fixed_amount_off = $is_percent_off ? null : [
			'amount'   => $coupon->get_amount(),
			'currency' => get_woocommerce_currency(),
		];
		$offer_class      = 'order';

		return array(
			'offer_id'         => $coupon->get_id(),
			'code'             => $coupon->get_code(),
			'percent_off'      => $percent_off,
			'fixed_amount_off' => $fixed_amount_off,
			'offer_class'      => $offer_class,
			'end_time'         => $coupon->get_date_expires() ? $coupon->get_date_expires()->getTimestamp() : 0,
			'usage_limit'      => $coupon->get_usage_limit() ?? 0,
			'usage_count'      => $coupon->get_usage_count() ?? 0,
		);
	}

	protected static function get_error_response_data( string $error_type, string $error_message = '', ?string $offer_code = null ): array {
		return [
			'error_type'    => $error_type,
			'error_message' => $error_message,
			'offer_code'    => $offer_code,
		];
	}

	protected static function get_params_value_enforced( string $field_name, array $params ) {
		if ( array_key_exists( $field_name, $params ) ) {
			return $params[ $field_name ];
		}
		throw new \OutOfBoundsException( sprintf( 'Field: %s does not exist in request params. Params fields: %s', $field_name, wp_json_encode( array_keys( $params ) ) ) );
	}

	private function get_request_response( array $response_data, int $status_code = self::HTTP_OK ): WP_REST_Response {
		$response_data = [
			'data'   => $response_data,
			'errors' => $this->errors,
		];
		return new WP_REST_Response( $response_data, $status_code );
	}
}
