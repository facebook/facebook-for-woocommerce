<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\API\CommerceIntegration;

use WooCommerce\Facebook\API\CommerceIntegration\Client;
use WooCommerce\Facebook\API\CommerceIntegration\Exception;
use WooCommerce\Facebook\API\CommerceIntegration\Finalize\Response;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;

/**
 * Tests for the Commerce Partner Integration finalize-install client.
 */
class ClientTest extends AbstractWPUnitTestWithSafeFiltering {

	/**
	 * Tests the endpoint request and response field mapping.
	 */
	public function test_finalize_install_uses_expected_contract_and_maps_assets(): void {
		$access_token         = 'test-suat';
		$external_business_id = 'test-store-123';
		$extension_version    = '3.7.6';

		$this->add_filter_with_safe_teardown(
			'pre_http_request',
			function( $preempt, $request_args, $url ) use ( $access_token, $external_business_id, $extension_version ) {
				$this->assertSame( Client::ENDPOINT, $url );
				$this->assertSame( 'POST', $request_args['method'] );
				$this->assertSame( 'Bearer ' . $access_token, $request_args['headers']['Authorization'] );
				$this->assertSame( 'application/json', $request_args['headers']['Content-Type'] );
				$this->assertSame(
					array(
						'external_business_id' => $external_business_id,
						'extension_version'    => $extension_version,
					),
					json_decode( $request_args['body'], true )
				);
				$this->assertStringNotContainsString( $access_token, $request_args['body'] );

				return array(
					'body'     => wp_json_encode(
						array(
							'id'                            => 'cpi-123',
							'external_business_id'          => $external_business_id,
							'installation_status'           => 'ACCESS_TOKEN_DEPOSITED',
							'commerce_merchant_settings_id' => 'cms-123',
							'catalog_id'                    => 'catalog-123',
							'pixel_id'                      => 'pixel-123',
						)
					),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
				);
			},
			10,
			3
		);

		$response = ( new Client() )->finalize_install( $access_token, $external_business_id, $extension_version );

		$this->assertInstanceOf( Response::class, $response );
		$this->assertSame(
			array(
				'commerce_partner_integration_id' => 'cpi-123',
				'commerce_merchant_settings_id'   => 'cms-123',
				'product_catalog_id'              => 'catalog-123',
				'pixel_id'                        => 'pixel-123',
			),
			$response->get_installation_assets()
		);
	}

	/**
	 * Tests that an empty extension version is omitted.
	 */
	public function test_finalize_install_omits_empty_extension_version(): void {
		$this->add_filter_with_safe_teardown(
			'pre_http_request',
			function( $preempt, $request_args ) {
				$this->assertSame(
					array( 'external_business_id' => 'test-store-123' ),
					json_decode( $request_args['body'], true )
				);

				return array(
					'body'     => '{"id":"cpi-123","external_business_id":"test-store-123","installation_status":"ACCESS_TOKEN_DEPOSITED"}',
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
				);
			},
			10,
			2
		);

		$response = ( new Client() )->finalize_install( 'test-suat', 'test-store-123', '' );

		$this->assertTrue( $response->is_successful() );
		$this->assertSame(
			array( 'commerce_partner_integration_id' => 'cpi-123' ),
			$response->get_installation_assets()
		);
	}

	/**
	 * Tests that HTTP failures retain their status for fallback decisions.
	 */
	public function test_finalize_install_throws_for_http_error(): void {
		$this->add_filter_with_safe_teardown(
			'pre_http_request',
			function() {
				return array(
					'body'     => '{"type":"commercePartnerIntegrationNotFound"}',
					'response' => array(
						'code'    => 404,
						'message' => 'Not Found',
					),
				);
			},
			10,
			3
		);

		try {
			( new Client() )->finalize_install( 'test-suat', 'test-store-123', '3.7.6' );
			$this->fail( 'Expected finalize-install to throw.' );
		} catch ( Exception $exception ) {
			$this->assertSame( 404, $exception->getCode() );
			$this->assertSame( 'not_found', $exception->get_failure_reason() );
		}
	}

	/**
	 * Tests that WordPress HTTP failures are classified for fallback monitoring.
	 */
	public function test_finalize_install_throws_for_transport_error(): void {
		$this->add_filter_with_safe_teardown(
			'pre_http_request',
			function() {
				return new \WP_Error( 'http_request_failed', 'Connection timed out.' );
			},
			10,
			3
		);

		try {
			( new Client() )->finalize_install( 'test-suat', 'test-store-123', '3.7.6' );
			$this->fail( 'Expected finalize-install to throw.' );
		} catch ( Exception $exception ) {
			$this->assertSame( 0, $exception->getCode() );
			$this->assertSame( 'transport_error', $exception->get_failure_reason() );
		}
	}

	/**
	 * Tests that malformed success responses fail closed.
	 */
	public function test_finalize_install_rejects_invalid_success_response(): void {
		$this->add_filter_with_safe_teardown(
			'pre_http_request',
			function() {
				return array(
					'body'     => '{"id":"cpi-123","external_business_id":"test-store-123"}',
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
				);
			},
			10,
			3
		);

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'missing required installation data' );

		( new Client() )->finalize_install( 'test-suat', 'test-store-123', '3.7.6' );
	}

	/**
	 * Tests that invalid JSON cannot be treated as a successful finalization.
	 */
	public function test_finalize_install_rejects_invalid_json(): void {
		$this->add_filter_with_safe_teardown(
			'pre_http_request',
			function() {
				return array(
					'body'     => 'not-json',
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
				);
			},
			10,
			3
		);

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'missing required installation data' );

		( new Client() )->finalize_install( 'test-suat', 'test-store-123', '3.7.6' );
	}

	/**
	 * Tests that a response cannot bind this store to a different EBID.
	 */
	public function test_finalize_install_rejects_external_business_id_mismatch(): void {
		$this->add_filter_with_safe_teardown(
			'pre_http_request',
			function() {
				return array(
					'body'     => '{"id":"cpi-123","external_business_id":"another-store","installation_status":"ACCESS_TOKEN_DEPOSITED"}',
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
				);
			},
			10,
			3
		);

		try {
			( new Client() )->finalize_install( 'test-suat', 'test-store-123', '3.7.6' );
			$this->fail( 'Expected finalize-install to throw.' );
		} catch ( Exception $exception ) {
			$this->assertSame( 'external_business_id_mismatch', $exception->get_failure_reason() );
		}
	}
}
