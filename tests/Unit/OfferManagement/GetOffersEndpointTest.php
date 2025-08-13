<?php
/** Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

use WooCommerce\Facebook\OfferManagement\CreateOffersEndpoint;
use WooCommerce\Facebook\OfferManagement\OfferManagementEndpointBase;

require_once __DIR__ . '/OfferManagementAPITestBase.php';

class GetOffersEndpointTest extends OfferManagementAPITestBase
{
	const ENDPOINT_METHOD = 'GET';

	public function test_get_single_offer(): void {
		$code = 'test_code';
		$end_time = time() + 5000;

		$coupon = new WC_Coupon( $code );
		$coupon->set_props(
			array(
				'discount_type' => 'percent',
				'amount'        => '10',
				'usage_limit'   => 1,
			)
		);
		$coupon->add_meta_data( OfferManagementEndpointBase::IS_FACEBOOK_MANAGED_METADATA_KEY, 'yes', true );
		$coupon->set_date_expires( $end_time );
		$coupon->save();

		$response = $this->perform_offer_get_request( [$code] );
		$response_offers = $response->get_data()['data']['offers'];
		$this->assertCount(1, $response_offers);
		$expected_offer_data = [
			'offer_id' => $coupon->get_id(),
			'code' => $code,
			'fixed_amount_off' => null,
			'percent_off' => 10,
			'offer_class' => 'order',
			'end_time' => $end_time,
			'usage_count' => 0,
			'usage_limit' => 1,
		];
		$this->assertEqualsCanonicalizing($expected_offer_data, $response_offers[0]);
	}

	public function test_get_multiple_offers(): void {
		$code_1 = 'test_code_1';
		$code_2 = 'test_code_2';
		$end_time = time() + 5000;

		$code_to_coupon_map = [];
		foreach([ $code_1, $code_2 ] as $code) {
			$coupon = new WC_Coupon( $code );
			$coupon->set_props(
				array(
					'discount_type' => 'fixed_cart',
					'amount'        => '12',
					'usage_limit'   => 5,
				)
			);
			$coupon->add_meta_data( OfferManagementEndpointBase::IS_FACEBOOK_MANAGED_METADATA_KEY, 'yes', true );
			$coupon->set_date_expires( $end_time );
			$coupon->save();
			$code_to_coupon_map[$code] = $coupon;
		}

		$response = $this->perform_offer_get_request( [$code_1, $code_2] );
		$response_offers = $response->get_data()['data']['offers'];
		$this->assertCount(2, $response_offers);
		$expected_offer_data = [
			[
				'offer_id' => $code_to_coupon_map[$code_1]->get_id(),
				'code' => $code_1,
				'fixed_amount_off' => ['amount' => '12', 'currency' => 'USD'],
				'percent_off' => null,
				'offer_class' => 'order',
				'end_time' => $end_time,
				'usage_count' => 0,
				'usage_limit' => 5,
			],
			[
				'offer_id' => $code_to_coupon_map[$code_2]->get_id(),
				'code' => $code_2,
				'fixed_amount_off' => ['amount' => '12', 'currency' => 'USD'],
				'percent_off' => null,
				'offer_class' => 'order',
				'end_time' => $end_time,
				'usage_count' => 0,
				'usage_limit' => 5,
			]
		];
		$this->assertEqualsCanonicalizing($expected_offer_data, $response_offers);
	}

	public function test_get_multiple_offers_one_missing(): void {
		$code = 'test_code';
		$missing_code = 'missing_code';
		$end_time = time() + 5000;


		$coupon = new WC_Coupon( $code );
		$coupon->set_props(
			array(
				'discount_type' => 'fixed_cart',
				'amount'        => '12',
				'usage_limit'   => 5,
			)
		);
		$coupon->set_date_expires( $end_time );
		$coupon->add_meta_data( OfferManagementEndpointBase::IS_FACEBOOK_MANAGED_METADATA_KEY, 'yes', true );
		$coupon->save();

		$response = $this->perform_offer_get_request( [$code, $missing_code] );
		$response_offers = $response->get_data()['data']['offers'];
		$response_errors = $response->get_data()['errors'];
		$this->assertCount(1, $response_offers);
		$this->assertCount(1, $response_errors);

		$expected_offer_data = [
			[
				'offer_id' => $coupon->get_id(),
				'code' => $code,
				'fixed_amount_off' => ['amount' => '12', 'currency' => 'USD'],
				'percent_off' => null,
				'offer_class' => 'order',
				'end_time' => $end_time,
				'usage_count' => 0,
				'usage_limit' => 5,
			],
		];
		$this->assertEqualsCanonicalizing($expected_offer_data, $response_offers);

		$expected_error_data = [
			[
				'error_type' => OfferManagementEndpointBase::ERROR_OFFER_NOT_FOUND,
				'offer_code' => $missing_code,
				'error_message' => null,
			],
		];
		$this->assertEqualsCanonicalizing($expected_error_data, $response_errors);
	}

	public function test_get_non_facebook_offer(): void {
		$code = 'test_code';
		$coupon = new WC_Coupon( $code );
		$coupon->set_props(
			array(
				'discount_type' => 'fixed_cart',
				'amount'        => '12',
				'usage_limit'   => 5,
			)
		);
		$coupon->set_date_expires( time() + 5000 );
		$coupon->save();

		$this->assertNotEquals(0, wc_get_coupon_id_by_code( $code ) );

		$response = $this->perform_offer_get_request( [$code] );

		$this->assertEmpty($response->get_data()['data']['offers']);

		$response_errors = $response->get_data()['errors'];
		$expected_error_data = [
			[
				'error_type' => OfferManagementEndpointBase::ERROR_OFFER_NOT_FOUND,
				'offer_code' => $code,
				'error_message' => null,
			],
		];
		$this->assertEqualsCanonicalizing($expected_error_data, $response_errors);
	}

	private function perform_offer_get_request(array $offer_codes): WP_REST_Response {
		$request_params = self::get_request_params($offer_codes);
		$request = $this->setup_offer_management_request(self::ENDPOINT_METHOD, $request_params);
		return $this->perform_request($request);
	}

	public static function get_request_params(array $offer_codes): array {
		return [
			'payload' => [
				'offer_codes' =>  $offer_codes,
			],
			'exp' =>  time() + 120,
			'jti' => wp_generate_uuid4(),
			'key_name' => self::KEY_NAME,
			'aud' => self::CATALOG_ID,
		];
	}
}
