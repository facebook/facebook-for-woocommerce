<?php
/** Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;
use Firebase\JWT\JWT;
use WooCommerce\Facebook\FBSignedData\PublicKeyStorageHelper;
use WooCommerce\Facebook\FBSignedData\FBPublicKey;
use WooCommerce\Facebook\OfferManagement\OfferManagementEndpointBase;
use WooCommerce\Facebook\OfferManagement\RequestVerification;
use WooCommerce\Facebook\RolloutSwitches;



/**
 * Class FBPublicKeyTest
 */
abstract class OfferManagementAPITestBase extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering
{
    const CATALOG_ID = 'test_catalog_id';
	const KEY_NAME = 'test_key_name';

	private string $private_key = '';


	private \WP_REST_Server $server;

	public function setUp(): void {
		parent::setUp();
		OfferManagementEndpointBase::register_endpoints();
		// Initiating the REST API.
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server;
		$this->server = $wp_rest_server;

		do_action( 'rest_api_init' );

        facebook_for_woocommerce()->get_integration()->update_product_catalog_id(self::CATALOG_ID);
		update_option(WC_Facebookcommerce_Integration::SETTING_ENABLE_FACEBOOK_MANAGED_COUPONS, 'yes');

		update_option('wc_facebook_for_woocommerce_rollout_switches', [RolloutSwitches::SWITCH_OFFER_MANAGEMENT_ENABLED => 'yes']);

		// Setup auth
		$key_pair = self::get_new_key_pair();
		$public_key = $key_pair['public_key'];
		$this->private_key = $key_pair['private_key'];
		self::store_public_key($public_key, PublicKeyStorageHelper::SETTING_CURRENT_PUBLIC_KEY);
	}

	protected function setup_offer_management_request(string $method, array $params): WP_REST_Request {
		$route = sprintf("/%s/%s", OfferManagementEndpointBase::API_NAMESPACE, OfferManagementEndpointBase::ROUTE);
		$request = new WP_REST_Request($method, $route);


		$jwt = JWT::encode($params, $this->private_key, 'ES256');
		$encoded_params = ['jwt_params' => $jwt];

		if ('POST' === $method) {
			$request->set_body_params($encoded_params);
		} else {
			$request->set_query_params($encoded_params);
		}

		return $request;
	}

	protected function perform_request(WP_REST_Request $request): WP_REST_Response {
		return $this->server->dispatch($request);
	}

	protected static function get_new_key_pair():array {
		$private_key = null;
		$key_config = [
			'private_key_type' => OPENSSL_KEYTYPE_EC,
			'curve_name' => 'prime256v1'
		];
		$key_pair = openssl_pkey_new($key_config);
		$public_key = openssl_pkey_get_details($key_pair)['key'];
		openssl_pkey_export($key_pair, $private_key); // Sets instance variable
		return ['public_key' => $public_key, 'private_key' => $private_key];
	}

	protected static function store_public_key(string $public_key, string $storage_key):void {
		$data =  [
			'project' => self::KEY_NAME,
			'key' => $public_key,
			'algorithm' => FBPublicKey::ALGORITHM_ES256,
			'encoding_format' => FBPublicKey::ENCODING_FORMAT_PEM
		];

		update_option($storage_key, $data );
	}

	protected function set_private_key_for_encoding(string $private_key):void {
		$this->private_key = $private_key;
	}
}
