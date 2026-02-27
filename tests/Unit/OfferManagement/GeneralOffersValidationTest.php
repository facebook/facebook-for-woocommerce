<?php
/** Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package MetaCommerce
 */

use WooCommerce\Facebook\OfferManagement\OfferManagementEndpointBase;
use WooCommerce\Facebook\RolloutSwitches;


require_once __DIR__ . '/OfferManagementAPITestBase.php';

class GeneralOffersValidationTest extends OfferManagementAPITestBase {

	public function test_offer_management_disabled_by_seller(): void {
		$offer_management_enabled = facebook_for_woocommerce()->get_integration()->is_facebook_managed_coupons_enabled();
		$this->assertTrue($offer_management_enabled);

		update_option(WC_Facebookcommerce_Integration::SETTING_ENABLE_FACEBOOK_MANAGED_COUPONS, 'no');
		$offer_management_enabled = facebook_for_woocommerce()->get_integration()->is_facebook_managed_coupons_enabled();
		$this->assertFalse($offer_management_enabled);
		$expected_error_data = [
			[
				'error_type' => OfferManagementEndpointBase::ERROR_OFFER_MANAGEMENT_DISABLED,
				'offer_code' => null,
				'error_message' => 'Not enabled by seller',
			],
		];
		$this->validate_create_get_delete( $expected_error_data, OfferManagementEndpointBase::HTTP_FORBIDDEN );
	}

	public function test_offer_management_disabled_by_meta(): void {
		$offer_management_enabled = facebook_for_woocommerce()->get_integration()->is_facebook_managed_coupons_enabled();
		$this->assertTrue($offer_management_enabled);


		update_option('wc_facebook_for_woocommerce_rollout_switches', [RolloutSwitches::SWITCH_OFFER_MANAGEMENT_ENABLED => 'no']);
		$expected_error_data = [
			[
				'error_type' => OfferManagementEndpointBase::ERROR_OFFER_MANAGEMENT_DISABLED,
				'offer_code' => null,
				'error_message' => 'Not enabled by Meta',
			],
		];
		$this->validate_create_get_delete( $expected_error_data, OfferManagementEndpointBase::HTTP_FORBIDDEN );
	}

	public function test_catalog_id_mismatch(): void {
		$existing_catalog_id = facebook_for_woocommerce()->get_integration()->get_product_catalog_id();
		$this->assertEquals(self::CATALOG_ID, $existing_catalog_id);
		$expected_empty_error_data = [];
		$this->validate_create_get_delete( $expected_empty_error_data );

		$new_catalog_id = 'new_catalog_id';
		$this->assertNotEquals($new_catalog_id, $existing_catalog_id);
		facebook_for_woocommerce()->get_integration()->update_product_catalog_id($new_catalog_id);
		$this->assertEquals($new_catalog_id, facebook_for_woocommerce()->get_integration()->get_product_catalog_id());

		$expected_error_data = [
			[
				'error_type' => OfferManagementEndpointBase::ERROR_CATALOG_ID_MISMATCH,
				'offer_code' => null,
				'error_message' => 'Platform Catalog ID: new_catalog_id, Request Catalog ID: test_catalog_id',
			],
		];
		$this->validate_create_get_delete( $expected_error_data, OfferManagementEndpointBase::HTTP_FORBIDDEN );
	}

	public function validate_create_get_delete(array $expected_error_data, int $expected_status_code = 200): void
	{
		$create_request_params = CreateOffersEndpointTest::get_request_params([]);
		$create_request = $this->setup_offer_management_request('POST', $create_request_params);
		$create_response = $this->perform_request($create_request);
		$this->assertEqualsCanonicalizing($expected_error_data, $create_response->get_data()['errors']);
		$this->assertEquals($expected_status_code, $create_response->get_status());


		$get_request_params = GetOffersEndpointTest::get_request_params([]);
		$get_request = $this->setup_offer_management_request('GET', $get_request_params);
		$get_response = $this->perform_request($get_request);
		$this->assertEqualsCanonicalizing($expected_error_data, $get_response->get_data()['errors']);
		$this->assertEquals($expected_status_code, $get_response->get_status());

		$delete_request_params = DeleteOffersEndpointTest::get_request_params([]);
		$delete_request = $this->setup_offer_management_request('DELETE', $delete_request_params);
		$delete_response = $this->perform_request($delete_request);
		$this->assertEqualsCanonicalizing($expected_error_data, $delete_response->get_data()['errors']);
		$this->assertEquals($expected_status_code, $delete_response->get_status());
	}
}
