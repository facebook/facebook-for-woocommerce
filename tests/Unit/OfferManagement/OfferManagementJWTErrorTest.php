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

class OfferManagementJWTErrorTest extends OfferManagementAPITestBase
{
	const ENDPOINT_METHOD = 'GET';

	public function test_jwt_not_found(): void {
		$request = $this->setup_offer_management_request(self::ENDPOINT_METHOD, []);
		$request->set_query_params([]);
		$response = $this->perform_request($request);

		$this->assertEmpty($response->get_data()['data']);
		$response_errors = $response->get_data()['errors'];
		$expected_error_data = [
			[
				'error_type' => OfferManagementEndpointBase::ERROR_JWT_NOT_FOUND,
				'offer_code' => null,
				'error_message' => null,
			],
		];
		$this->assertEqualsCanonicalizing($expected_error_data, $response_errors);
		$this->assertEquals(OfferManagementEndpointBase::HTTP_BAD_REQUEST, $response->get_status());
	}

	public function test_jwt_expired(): void {
		$request = $this->setup_offer_management_request(self::ENDPOINT_METHOD, self::get_request_params( time() - 200));
		$response = $this->perform_request($request);

		$this->assertEmpty($response->get_data()['data']);
		$response_errors = $response->get_data()['errors'];
		$expected_error_data = [
			[
				'error_type' => OfferManagementEndpointBase::ERROR_JWT_EXPIRED,
				'offer_code' => null,
				'error_message' => null,
			],
		];
		$this->assertEqualsCanonicalizing($expected_error_data, $response_errors);
		$this->assertEquals(OfferManagementEndpointBase::HTTP_UNAUTHORIZED, $response->get_status());
	}

	public function test_jwt_invalid(): void {
		$request = $this->setup_offer_management_request(self::ENDPOINT_METHOD, self::get_request_params( ));

		$token = $request->get_query_params()['jwt_params'];
		$token_parts = explode('.', $token);
		$malformed_token = implode('.', [$token_parts[0], $token_parts[1]]);

		$request->set_query_params(['jwt_params' => $malformed_token]);


		$response = $this->perform_request($request);

		$this->assertEmpty($response->get_data()['data']);
		$response_errors = $response->get_data()['errors'];
		$this->assertCount(1, $response_errors);
		$response_error = $response_errors[0];
		$this->assertEquals(OfferManagementEndpointBase::ERROR_JWT_DECODE_FAILURE, $response_error['error_type']);
		$this->assertEquals(null, $response_error['offer_code']);
		$this->assertEquals(OfferManagementEndpointBase::HTTP_UNAUTHORIZED, $response->get_status());
	}



	private static function get_request_params(?int $exp = null): array {
		$exp = $exp ?? time() + 120;
		return [
			'payload' => [
				'offer_codes' =>  [],
			],
			'exp' => $exp,
			'jti' => wp_generate_uuid4(),
			'aud' => self::CATALOG_ID,
		];
	}
}
