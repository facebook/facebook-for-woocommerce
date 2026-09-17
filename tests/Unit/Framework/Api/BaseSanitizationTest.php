<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Framework\Api;

use WooCommerce\Facebook\API\Request;
use WooCommerce\Facebook\API\Response;
use WooCommerce\Facebook\Framework\Api\Base;
use WooCommerce\Facebook\Framework\Api\SensitiveData;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;

/**
 * Regression tests for credential redaction in API request logging.
 *
 * A bug bounty report found that the full Graph API request URI — access token
 * included — was broadcast to the logging action and written to the WooCommerce
 * log, which CI then published as a public build artifact. These tests pin the
 * two halves of the fix: the logged copy is redacted, and the URI actually sent
 * over the wire is not.
 *
 * @see Base::broadcast_request()
 * @see Base::get_sanitized_request_uri()
 */
class BaseSanitizationTest extends AbstractWPUnitTestWithSafeFiltering {

	/** @var string a token-shaped value, long enough to look like the real thing */
	const TOKEN = 'EAABsbCS1i2gBA1ZCZASomeVeryLongLiveTokenValue0123456789ZDZD';

	/** @var string the API id used to namespace the broadcast action */
	const API_ID = 'sanitization_test';

	/**
	 * Captured payloads from the request-performed action.
	 *
	 * @var array
	 */
	private $broadcasts = [];

	/**
	 * Builds an API client that returns a canned response instead of making a
	 * real HTTP request, and records everything broadcast to the logging action.
	 *
	 * @param array  $params        query params for the request.
	 * @param string $response_body raw body the fake transport should return.
	 * @return Base
	 */
	private function get_test_api( array $params, string $response_body = '{"success":true}' ) : Base {
		$this->broadcasts = [];

		$this->add_filter_with_safe_teardown(
			'wc_' . self::API_ID . '_api_request_performed',
			function ( $request_data, $response_data ) {
				$this->broadcasts[] = [
					'request'  => $request_data,
					'response' => $response_data,
				];
			},
			10,
			2
		);

		$request = new Request( '/me', 'GET' );
		$request->set_params( $params );

		return new class( $request, $response_body ) extends Base {
			private $test_request;
			private $test_response_body;

			public function __construct( $request, $response_body ) {
				$this->test_request       = $request;
				$this->test_response_body = $response_body;
				$this->request_uri        = 'https://graph.facebook.com/v21.0';
				$this->response_handler   = Response::class;
			}

			/** Skips the network entirely — we only care about what gets logged. */
			protected function do_remote_request( string $request_uri, array $request_args ) {
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'headers'  => [],
					'body'     => $this->test_response_body,
				];
			}

			protected function get_api_id() {
				return BaseSanitizationTest::API_ID;
			}

			protected function get_request_user_agent() {
				return 'Test/1.0';
			}

			protected function get_new_request( $args = [] ) {
				return $this->test_request;
			}

			protected function get_plugin() {
				return null;
			}

			/** Test seam: drives a full request/response cycle. */
			public function run() {
				return $this->perform_request( $this->test_request );
			}

			/** Test seam: exposes the URI that would actually be sent. */
			public function wire_uri() {
				$this->request = $this->test_request;
				return $this->get_request_uri();
			}
		};
	}

	/**
	 * The logged URI must not contain the access token.
	 *
	 * This is the exact leak the bug bounty researcher exploited.
	 */
	public function test_broadcast_request_redacts_access_token_from_logged_uri() {
		$api = $this->get_test_api( [ 'access_token' => self::TOKEN ] );
		$api->run();

		$this->assertCount( 1, $this->broadcasts );

		$logged_uri = $this->broadcasts[0]['request']['uri'];

		$this->assertStringNotContainsString( self::TOKEN, $logged_uri );
		$this->assertStringContainsString( 'access_token=' . SensitiveData::REDACTED, $logged_uri );
	}

	/**
	 * Redaction must not change the request that actually goes to Graph — only
	 * the copy handed to the logger.
	 */
	public function test_wire_uri_still_carries_the_real_token() {
		$api = $this->get_test_api( [ 'access_token' => self::TOKEN ] );

		$this->assertStringContainsString( 'access_token=' . self::TOKEN, $api->wire_uri() );
	}

	/**
	 * Non-credential params must survive redaction so the log stays debuggable.
	 */
	public function test_broadcast_request_preserves_non_sensitive_params() {
		$api = $this->get_test_api(
			[
				'access_token' => self::TOKEN,
				'catalog_id'   => '27002437791140',
			]
		);
		$api->run();

		$logged_uri = $this->broadcasts[0]['request']['uri'];

		$this->assertStringContainsString( 'catalog_id=27002437791140', $logged_uri );
		$this->assertStringContainsString( '/me', $logged_uri );
	}

	/**
	 * A response that hands back a credential (as /me/accounts does with page
	 * access tokens) must not be logged in the clear either.
	 */
	public function test_broadcast_request_redacts_credentials_in_response_body() {
		$body = wp_json_encode(
			[
				'data' => [
					[
						'id'           => '30602582348331',
						'access_token' => self::TOKEN,
					],
				],
			]
		);

		$api = $this->get_test_api( [ 'access_token' => self::TOKEN ], $body );
		$api->run();

		$logged_body = $this->broadcasts[0]['response']['body'];

		$this->assertStringNotContainsString( self::TOKEN, $logged_body );
		$this->assertStringContainsString( '30602582348331', $logged_body );
	}

	/**
	 * Nothing anywhere in the broadcast payload may contain the token.
	 *
	 * Deliberately broad: this is the assertion that catches a future field
	 * being added to the payload without being routed through the redactor.
	 */
	public function test_no_part_of_the_broadcast_payload_contains_the_token() {
		$api = $this->get_test_api( [ 'access_token' => self::TOKEN ] );
		$api->run();

		$this->assertStringNotContainsString(
			self::TOKEN,
			wp_json_encode( $this->broadcasts )
		);
	}
}
