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

class CreateOffersEndpointTest extends OfferManagementAPITestBase
{
	const ENDPOINT_METHOD = 'POST';

	public function test_fixed_off_offer_success(): void {
		$offer_data = [
			'code' => 'test_code',
			'fixed_amount_off' => '12 USD',
			'percent_off' => null,
			'offer_class' => 'order',
			'end_time' => time() + 5000,
			'usage_limit' => 1,
		];

		$response = $this->perform_offer_creation_request( [$offer_data] );
		$this->check_valid_offer( $offer_data, $response );
	}

	public function test_fixed_off_with_decimal_and_0_percent(): void {
		$offer_data = [
			'code' => 'test_code',
			'fixed_amount_off' => '12.46 USD',
			'percent_off' => 0,
			'offer_class' => 'order',
			'end_time' => time() + 5000,
			'usage_limit' => 1,
		];

		$response = $this->perform_offer_creation_request( [$offer_data] );
		$this->check_valid_offer( $offer_data, $response );
	}

	public function test_percent_off_offer_success(): void {
		$offer_data = [
			'code' => 'test_code',
			'fixed_amount_off' => null,
			'percent_off' => 15,
			'offer_class' => 'order',
			'end_time' => time() + 5000,
			'usage_limit' => 1,
		];

		$response = $this->perform_offer_creation_request( [$offer_data] );
		$this->check_valid_offer( $offer_data, $response );
	}

	public function test_percent_off_not_provided(): void {
		$offer_data = [
			'code' => 'test_code',
			'fixed_amount_off' => '12 USD',
			'offer_class' => 'order',
			'end_time' => time() + 5000,
			'usage_limit' => 1,
		];

		$response = $this->perform_offer_creation_request( [$offer_data] );
		$this->check_valid_offer( $offer_data, $response );
	}

	public function test_fixed_amount_off_not_provided(): void {
		$offer_data = [
			'code' => 'test_code',
			'percent_off' => 15,
			'offer_class' => 'order',
			'end_time' => time() + 5000,
			'usage_limit' => 1,
		];

		$response = $this->perform_offer_creation_request( [$offer_data] );
		$this->check_valid_offer( $offer_data, $response );
	}

	public function test_fixed_amount_off_empty_string(): void
	{
		$offer_data = [
			'code' => 'test_code',
			'fixed_amount_off' => '',
			'percent_off' => 15,
			'offer_class' => 'order',
			'end_time' => time() + 5000,
			'usage_limit' => 1,
		];

		$response = $this->perform_offer_creation_request([$offer_data]);
		$this->check_valid_offer($offer_data, $response );
	}

	public function test_no_usage_limit(): void {
		$offer_data = [
			'code' => 'test_code',
			'percent_off' => 15,
			'offer_class' => 'order',
			'end_time' => time() + 5000,
			'usage_limit' => 0,
		];

		$response = $this->perform_offer_creation_request( [$offer_data] );
			$this->check_valid_offer( $offer_data, $response );

	}

	public function test_multiple_valid(): void {
		$offer_data_1 = [
			'code' => 'test_code_1',
			'percent_off' => 15,
			'offer_class' => 'order',
			'end_time' => time() + 5000,
			'usage_limit' => 0,
		];

		$offer_data_2 = [
			'code' => 'test_code_2',
			'percent_off' => 15,
			'offer_class' => 'order',
			'end_time' => time() + 5000,
			'usage_limit' => 0,
		];

		$response = $this->perform_offer_creation_request( [$offer_data_1, $offer_data_2] );
		$this->check_valid_offer( $offer_data_1, $response );
		$this->check_valid_offer( $offer_data_2, $response );
	}

	public function test_one_valid_one_invalid(): void
	{
		$offer_data_valid = [
			'code' => 'test_code_valid',
			'percent_off' => 15,
			'offer_class' => 'order',
			'end_time' => time() + 5000,
			'usage_limit' => 0,
		];

		$offer_data_invalid = [
			'code' => 'test_code_invalid',
			'fixed_amount_off' => 'INVALID_FIXED_AMOUNT_OFF',
			'offer_class' => 'order',
			'end_time' => time() + 5000,
			'usage_limit' => 0,
		];

		$response = $this->perform_offer_creation_request([$offer_data_valid, $offer_data_invalid]);
		$this->check_valid_offer($offer_data_valid, $response);
		$this->check_invalid_offer($offer_data_invalid,  $response, OfferManagementEndpointBase::ERROR_OFFER_CREATE_FAILURE, '');
	}

	public function test_code_already_exists(): void {
		$offer_data = [
			'code' => 'test_code_valid',
			'percent_off' => 15,
			'offer_class' => 'order',
			'end_time' => time() + 5000,
			'usage_limit' => 0,
		];

		$response = $this->perform_offer_creation_request([$offer_data]);
		$this->check_valid_offer($offer_data, $response);
		$response = $this->perform_offer_creation_request([$offer_data]);

		$response_errors = $response->get_data()['errors'];
		$this->assertNotNull(array_find($response_errors, function($response_error) {
			return $response_error['error_type'] === OfferManagementEndpointBase::ERROR_OFFER_CODE_ALREADY_EXISTS;
		}));
	}

	public function test_invalid_offer_classe(): void {
		$offer_data = [
			'code' => 'test_code_invalid',
			'percent_off' => 15,
			'offer_class' => 'shipping',
			'end_time' => time() + 5000,
			'usage_limit' => 0,
		];

		$response = $this->perform_offer_creation_request([$offer_data]);
		$this->check_invalid_offer($offer_data, $response, OfferManagementEndpointBase::ERROR_OFFER_CONFIGURATION_NOT_SUPPORTED);
	}

	public function test_invalid_percent_and_fixed(): void {
		$offer_data = [
			'code' => 'test_code_invalid',
			'percent_off' => 15,
			'fixed_amount_off' => '12 USD',
			'offer_class' => 'order',
			'end_time' => time() + 5000,
			'usage_limit' => 0,
		];

		$response = $this->perform_offer_creation_request([$offer_data]);
		$this->check_invalid_offer($offer_data, $response, OfferManagementEndpointBase::ERROR_OFFER_CREATE_FAILURE, 'Exactly one of fixed amount off or percent off');
	}

	public function test_invalid_no_percent_or_fixed(): void {
		$offer_data = [
			'code' => 'test_code_invalid',
			'offer_class' => 'order',
			'end_time' => time() + 5000,
			'usage_limit' => 0,
		];

		$response = $this->perform_offer_creation_request([$offer_data]);
		$this->check_invalid_offer($offer_data, $response, OfferManagementEndpointBase::ERROR_OFFER_CREATE_FAILURE, 'Exactly one of fixed amount off or percent off');
	}


	public function test_missing_creation_params(): void {
		$params = self::get_request_params([]);
		unset($params['create_offers_data']);

		$request = $this->setup_offer_management_request(self::ENDPOINT_METHOD, $params);
		$response = $this->perform_request($request);

		$errors = $response->get_data()['errors'];

		$this->assertEquals(1, sizeof($errors));
		$error = $errors[0];
		$this->assertEquals(OfferManagementEndpointBase::ERROR_OFFER_MANAGEMENT_ERROR, $error['error_type']);
		$this->assertStringContainsString('create_offers_data does not exist in request params', $error['error_message']);
		$this->assertEmpty($error['offer_code']);
	}


	private function perform_offer_creation_request(array $create_offers_data): WP_REST_Response {
		$request_params = self::get_request_params($create_offers_data);
		$request = $this->setup_offer_management_request(self::ENDPOINT_METHOD, $request_params);
		return $this->perform_request($request);
	}

	private function check_valid_offer(array $create_offer_data, WP_REST_Response $response): void {
		$code = $create_offer_data['code'];
		$coupon   = new WC_Coupon( $code );
		$coupon_expiration_time = $coupon->get_date_expires() ? $coupon->get_date_expires()->getTimestamp() : 0;

		$this->assertNotEquals($coupon->get_id(), 0);
		$this->assertEquals($create_offer_data['end_time'] ?? 0, $coupon_expiration_time);
		$this->assertEquals(0, $coupon->get_usage_count());
		$this->assertEquals($create_offer_data['usage_limit'], $coupon->get_usage_limit());
		$this->assertEquals('yes', $coupon->get_meta(CreateOffersEndpoint::IS_FACEBOOK_MANAGED_POST_META_KEY));
		if (!empty($create_offer_data['fixed_amount_off'] ?? '')) {
			$this->assertEquals( explode( ' ', $create_offer_data['fixed_amount_off'])[0], $coupon->get_amount());
			$this->assertEquals('fixed_cart', $coupon->get_discount_type());
		} else {
			$this->assertEquals($create_offer_data['percent_off'], $coupon->get_amount());
			$this->assertEquals('percent',$coupon->get_discount_type());
		}

		$response_offers = $response->get_data()['data']['created_offers'];
		$response_offer = array_find($response_offers, function($response_offer) use (&$code){
			return $response_offer['code'] === $code;
		});

		$expected_fixed_amount_off = empty($create_offer_data['fixed_amount_off'] ?? '') ? null : $create_offer_data['fixed_amount_off'];
		$expected_percent_off = empty($create_offer_data['percent_off'] ?? 0) ? null : $create_offer_data['percent_off'];
		$expected_response_offer = [
			'code' => $code,
			'percent_off'      => $expected_percent_off,
			'fixed_amount_off' => $expected_fixed_amount_off,
			'offer_class'      => $create_offer_data['offer_class'],
			'end_time'         => $create_offer_data['end_time'],
			'usage_limit'      => $create_offer_data['usage_limit'],
			'usage_count'      => $coupon->get_usage_count() ?? 0,
		];
		$this->assertEqualsCanonicalizing($expected_response_offer, $response_offer);
	}

	private function check_invalid_offer(array $create_offer_data,  WP_REST_Response $response, string $expected_error_type, ?string $expected_error_message_fragment = '' ): void {
		$code = $create_offer_data['code'];
		$coupon   = new WC_Coupon( $code );
		$this->assertEquals($coupon->get_id(), 0);

		$response_offers = $response->get_data()['data']['created_offers'];
		$this->assertNull(array_find($response_offers, function($response_offer) use (&$code){
			return $response_offer['code'] === $code;
		}));

		$response_errors = $response->get_data()['errors'];
		$this->assertNotNull(array_find($response_errors, function($response_error) use (&$code, &$expected_error_type, &$expected_error_message_fragment){
			return $response_error['offer_code'] === $code
				&& $response_error['error_type'] === $expected_error_type
				&& str_contains($response_error['error_message'], $expected_error_message_fragment);
		}));
	}


	private static function get_request_params(array $create_offers_data, ?int $exp = null): array {
		$exp = time() + 120;
		return [
			'create_offers_data' =>  $create_offers_data,
			'exp' => $exp,
			'jti' => wp_generate_uuid4(),
			'key_project' => 'test_key_project',
		];
	}

}
