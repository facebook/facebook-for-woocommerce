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

class DeleteOffersEndpointTest extends OfferManagementAPITestBase
{
	const ENDPOINT_METHOD = 'DELETE';

	public function test_delete_single_offer(): void {
		$code_to_delete = 'code_1';
		$code_to_keep = 'code_2';
		$end_time = time() + 5000;


		foreach([ $code_to_delete, $code_to_keep ] as $code) {
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
		}

		$this->assertNotEquals(0, wc_get_coupon_id_by_code( $code_to_delete ) );
		$this->assertNotEquals(0, wc_get_coupon_id_by_code( $code_to_keep ) );

		$response = $this->perform_offer_delete_request( [$code_to_delete] );

		$this->assertEquals(0, wc_get_coupon_id_by_code( $code_to_delete ) );
		$this->assertNotEquals(0, wc_get_coupon_id_by_code( $code_to_keep ) );

		$response_deleted_codes = $response->get_data()['data']['deleted_offer_codes'];
		$this->assertEquals($response_deleted_codes, [$code_to_delete]);
	}

	public function test_delete_multiple_offers(): void {
		$code_1 = 'code_1';
		$code_2 = 'code_2';
		$end_time = time() + 5000;

		foreach([ $code_1, $code_2 ] as $code) {
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
		}

		$this->assertNotEquals(0, wc_get_coupon_id_by_code( $code_1 ) );
		$this->assertNotEquals(0, wc_get_coupon_id_by_code( $code_2 ) );

		$response = $this->perform_offer_delete_request( [$code_1, $code_2] );


		$this->assertEquals(0, wc_get_coupon_id_by_code( $code_1 ) );
		$this->assertEquals(0, wc_get_coupon_id_by_code( $code_2 ) );

		$response_deleted_codes = $response->get_data()['data']['deleted_offer_codes'];
		$this->assertEquals($response_deleted_codes, [$code_1, $code_2]);
	}

	public function test_delete_multiple_offers_one_missing(): void {
		$code = 'test_code';
		$missing_code = 'missing_code';

		$coupon = new WC_Coupon( $code );
		$coupon->set_props(
			array(
				'discount_type' => 'fixed_cart',
				'amount'        => '12',
				'usage_limit'   => 5,
			)
		);
		$coupon->set_date_expires( time() + 5000 );
		$coupon->add_meta_data( OfferManagementEndpointBase::IS_FACEBOOK_MANAGED_METADATA_KEY, 'yes', true );
		$coupon->save();

		$this->assertNotEquals(0, wc_get_coupon_id_by_code( $code ) );
		$this->assertEquals(0, wc_get_coupon_id_by_code( $missing_code ) );

		$response = $this->perform_offer_delete_request( [$code, $missing_code] );

		$response_deleted_codes = $response->get_data()['data']['deleted_offer_codes'];
		$this->assertEquals([$code], $response_deleted_codes);

		$response_errors = $response->get_data()['errors'];
		$expected_error_data = [
			[
				'error_type' => OfferManagementEndpointBase::ERROR_OFFER_NOT_FOUND,
				'offer_code' => $missing_code,
				'error_message' => null,
			],
		];
		$this->assertEqualsCanonicalizing($expected_error_data, $response_errors);
	}

	public function test_delete_non_facebook_offer(): void {
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

		$response = $this->perform_offer_delete_request( [$code] );

		$this->assertEmpty($response->get_data()['data']['deleted_offer_codes']);

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

	private function perform_offer_delete_request(array $offer_codes): WP_REST_Response {
		$request_params = self::get_request_params($offer_codes);
		$request = $this->setup_offer_management_request(self::ENDPOINT_METHOD, $request_params);
		$response =  $this->perform_request($request);
		wp_cache_flush();
		return $response;
	}

	public static function get_request_params(array $offer_codes): array {
		$exp = time() + 120;
		return [
			'payload' => [
				'offer_codes' =>  $offer_codes,
			],
			'exp' => $exp,
			'jti' => wp_generate_uuid4(),
			'key_name' => self::KEY_NAME,
			'aud' => self::CATALOG_ID,
		];
	}
}
