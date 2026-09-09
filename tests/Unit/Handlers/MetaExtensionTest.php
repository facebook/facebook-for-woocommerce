<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * Unit tests for Meta Extension handler.
 */

namespace WooCommerce\Facebook\Tests\Handlers;

use WooCommerce\Facebook\Handlers\MetaExtension;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * The Meta Extension unit test class.
 */
class MetaExtensionTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Tests the Commerce Hub splash URL.
	 */
	public function test_generate_iframe_splash_url() {
		$plugin = facebook_for_woocommerce();
		$url    = MetaExtension::generate_iframe_splash_url( true, $plugin, 'test-business' );

		$this->assertStringContainsString( 'access_client_token=' . MetaExtension::CLIENT_TOKEN, $url );
		$this->assertStringContainsString( 'business_vertical=ECOMMERCE', $url );
		$this->assertStringContainsString( 'channel=COMMERCE', $url );
		$this->assertStringContainsString( 'external_business_id=test-business', $url );
		$this->assertStringContainsString( 'installed=1', $url );
		$this->assertStringContainsString( 'https://www.commercepartnerhub.com/commerce_extension/splash/', $url );
	}

	/**
	 * Tests generating the Commerce Hub overview URL with a delegated token.
	 */
	public function test_generate_iframe_management_url_uses_token_endpoint_without_fallback() {
		$commerce_partner_integration_id = 'test-cpi';
		$external_business_id            = 'test-external-business';
		$long_lived_access_token          = 'long-lived-bisu-token';
		$delegate_access_token            = 'short-lived-delegate-token';
		$expected_endpoint                = 'https://api.facebook.com/commerce-partner-integrations/test-cpi/commerce-extension-token';
		$request_count                    = 0;

		set_transient( 'wc_facebook_connection_invalid', time(), DAY_IN_SECONDS );
		$this->mock_set_option( MetaExtension::OPTION_ACCESS_TOKEN, $long_lived_access_token );
		$this->mock_set_option( MetaExtension::OPTION_COMMERCE_PARTNER_INTEGRATION_ID, $commerce_partner_integration_id );
		$this->add_filter_with_safe_teardown(
			'pre_http_request',
			function ( $preempt, $request_args, $url ) use ( $delegate_access_token, $expected_endpoint, $long_lived_access_token, &$request_count ) {
				++$request_count;
				$this->assertSame( $expected_endpoint, $url );
				$this->assertSame( 'POST', $request_args['method'] );
				$this->assertSame( 'application/json', $request_args['headers']['Accept'] );
				$this->assertSame( 'Bearer ' . $long_lived_access_token, $request_args['headers']['Authorization'] );
				$this->assertNull( $request_args['body'] );
				$this->assertSame( 0, $request_args['redirection'] );
				$this->assertTrue( $request_args['sslverify'] );

				return array(
					'headers'  => array(),
					'body'     => wp_json_encode( array( 'access_token' => $delegate_access_token ) ),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
				);
			},
			10,
			3
		);

		$url       = MetaExtension::generate_iframe_management_url( $external_business_id );
		$url_parts = wp_parse_url( $url );
		$query      = array();
		parse_str( $url_parts['query'], $query );

		$this->assertSame( 'https', $url_parts['scheme'] );
		$this->assertSame( 'www.commercepartnerhub.com', $url_parts['host'] );
		$this->assertSame( '/commerce_extension/overview/', $url_parts['path'] );
		$this->assertSame(
			array(
				'access_token'         => $delegate_access_token,
				'external_business_id' => $external_business_id,
				'locale'               => get_user_locale(),
			),
			$query
		);
		$this->assertStringNotContainsString( $long_lived_access_token, $url );
		$this->assertSame( 1, $request_count );
		$this->assertFalse( get_transient( 'wc_facebook_connection_invalid' ) );
	}

	/**
	 * Required-value scenarios for the management URL.
	 *
	 * @return array<string, array<string>>
	 */
	public static function required_management_value_provider(): array {
		return array(
			'missing BISU token'  => array( '', 'test-cpi', 'test-external-business' ),
			'missing external ID' => array( 'long-lived-bisu-token', 'test-cpi', '' ),
		);
	}

	/**
	 * Tests that missing inputs fail before an HTTP request is made.
	 *
	 * @dataProvider required_management_value_provider
	 *
	 * @param string $access_token Commerce integration BISU token.
	 * @param string $commerce_partner_integration_id Commerce Partner Integration ID.
	 * @param string $external_business_id External business ID.
	 */
	public function test_generate_iframe_management_url_requires_all_values( $access_token, $commerce_partner_integration_id, $external_business_id ) {
		$request_count = 0;
		$this->mock_set_option( MetaExtension::OPTION_ACCESS_TOKEN, $access_token );
		$this->mock_set_option( MetaExtension::OPTION_COMMERCE_PARTNER_INTEGRATION_ID, $commerce_partner_integration_id );
		$this->add_filter_with_safe_teardown(
			'pre_http_request',
			function () use ( &$request_count ) {
				++$request_count;
				return new \WP_Error( 'unexpected_request' );
			},
			10,
			3
		);

		$url = MetaExtension::generate_iframe_management_url( $external_business_id );

		$this->assertSame( '', $url );
		$this->assertSame( 0, $request_count );
	}

	/**
	 * Tests that a missing CPI ID skips the new endpoint and uses the legacy fallback.
	 */
	public function test_generate_iframe_management_url_uses_legacy_fallback_without_cpi_id() {
		$legacy_url   = 'https://www.facebook.com/commerce/app/management/test-external-business/';
		$request_urls = array();

		$this->mock_set_option( MetaExtension::OPTION_ACCESS_TOKEN, 'long-lived-bisu-token' );
		$this->mock_set_option( MetaExtension::OPTION_COMMERCE_PARTNER_INTEGRATION_ID, '' );
		$this->add_filter_with_safe_teardown(
			'pre_http_request',
			function ( $preempt, $request_args, $url ) use ( $legacy_url, &$request_urls ) {
				$request_urls[] = $url;
				$this->assertSame( 'Bearer long-lived-bisu-token', $request_args['headers']['Authorization'] );
				$this->assertStringNotContainsString( 'access_token=', $url );
				return array(
					'headers'  => array(),
					'body'     => wp_json_encode(
						array(
							'commerce_extension' => array( 'uri' => $legacy_url ),
						)
					),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
				);
			},
			10,
			3
		);

		$url = MetaExtension::generate_iframe_management_url( 'test-external-business' );

		$this->assertSame( $legacy_url, $url );
		$this->assertCount( 1, $request_urls );
		$this->assertStringContainsString( 'graph.facebook.com', $request_urls[0] );
		$this->assertStringContainsString( 'fbe_external_business_id=test-external-business', $request_urls[0] );
	}

	/**
	 * Invalid endpoint response scenarios.
	 *
	 * @return array<string, array<int|string>>
	 */
	public static function invalid_management_response_provider(): array {
		return array(
			'invalid session'    => array( 401, '{"type":"invalidSession"}' ),
			'forbidden response' => array( 403, '{"title":"Cannot mint Commerce Extension token"}' ),
			'malformed JSON'     => array( 200, 'not-json' ),
			'missing token'      => array( 200, '{}' ),
			'non-string token'   => array( 200, '{"access_token":[]}' ),
		);
	}

	/**
	 * Tests that invalid endpoint responses use the legacy fallback.
	 *
	 * @dataProvider invalid_management_response_provider
	 *
	 * @param int    $status_code HTTP response status.
	 * @param string $body HTTP response body.
	 */
	public function test_generate_iframe_management_url_falls_back_after_invalid_responses( $status_code, $body ) {
		$legacy_url   = 'https://www.facebook.com/commerce/app/management/test-external-business/';
		$request_urls = array();
		delete_transient( 'wc_facebook_connection_invalid' );
		$this->mock_set_option( MetaExtension::OPTION_ACCESS_TOKEN, 'long-lived-bisu-token' );
		$this->add_filter_with_safe_teardown(
			'pre_http_request',
			function ( $preempt, $request_args, $url ) use ( $status_code, $body, $legacy_url, &$request_urls ) {
				$request_urls[] = $url;
				if ( false !== strpos( $url, 'api.facebook.com' ) ) {
					return array(
						'headers'  => array(),
						'body'     => $body,
						'response' => array(
							'code'    => $status_code,
							'message' => 'Test response',
						),
						'cookies'  => array(),
					);
				}
				$this->assertFalse( get_transient( 'wc_facebook_connection_invalid' ) );
				$this->assertSame( 'Bearer long-lived-bisu-token', $request_args['headers']['Authorization'] );
				$this->assertStringNotContainsString( 'access_token=', $url );

				return array(
					'headers'  => array(),
					'body'     => wp_json_encode(
						array(
							'commerce_extension' => array( 'uri' => $legacy_url ),
						)
					),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
				);
			},
			10,
			3
		);

		$url = MetaExtension::generate_iframe_management_url( 'test-external-business', 'test-cpi' );

		$this->assertSame( $legacy_url, $url );
		$this->assertCount( 2, $request_urls );
		$this->assertStringContainsString( 'api.facebook.com', $request_urls[0] );
		$this->assertStringContainsString( 'graph.facebook.com', $request_urls[1] );
		$this->assertFalse( get_transient( 'wc_facebook_connection_invalid' ) );
	}

	/**
	 * Tests that transport failures use the legacy fallback.
	 */
	public function test_generate_iframe_management_url_falls_back_after_transport_failure() {
		$legacy_url   = 'https://www.facebook.com/commerce/app/management/test-external-business/';
		$request_urls = array();
		$this->mock_set_option( MetaExtension::OPTION_ACCESS_TOKEN, 'long-lived-bisu-token' );
		$this->add_filter_with_safe_teardown(
			'pre_http_request',
			function ( $preempt, $request_args, $url ) use ( $legacy_url, &$request_urls ) {
				$request_urls[] = $url;
				if ( false !== strpos( $url, 'api.facebook.com' ) ) {
					return new \WP_Error( 'http_request_failed', 'Test transport failure' );
				}

				return array(
					'headers'  => array(),
					'body'     => wp_json_encode(
						array(
							'commerce_extension' => array( 'uri' => $legacy_url ),
						)
					),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
				);
			},
			10,
			3
		);

		$url = MetaExtension::generate_iframe_management_url( 'test-external-business', 'test-cpi' );

		$this->assertSame( $legacy_url, $url );
		$this->assertCount( 2, $request_urls );
		$this->assertStringContainsString( 'api.facebook.com', $request_urls[0] );
		$this->assertStringContainsString( 'graph.facebook.com', $request_urls[1] );
	}

	/**
	 * Tests that the management URL is empty when both endpoint flows fail.
	 */
	public function test_generate_iframe_management_url_returns_empty_when_both_flows_fail() {
		$request_urls = array();
		$this->mock_set_option( MetaExtension::OPTION_ACCESS_TOKEN, 'long-lived-bisu-token' );
		$this->add_filter_with_safe_teardown(
			'pre_http_request',
			function ( $preempt, $request_args, $url ) use ( &$request_urls ) {
				$request_urls[] = $url;
				return new \WP_Error( 'http_request_failed', 'Test transport failure' );
			},
			10,
			3
		);

		$url = MetaExtension::generate_iframe_management_url( 'test-external-business', 'test-cpi' );

		$this->assertSame( '', $url );
		$this->assertCount( 2, $request_urls );
		$this->assertStringContainsString( 'api.facebook.com', $request_urls[0] );
		$this->assertStringContainsString( 'graph.facebook.com', $request_urls[1] );
		$this->assertFalse( get_transient( 'wc_facebook_connection_invalid' ) );
	}

	/**
	 * Tests that a token endpoint 401 plus a legacy transport failure does not mark the connection invalid.
	 *
	 * The legacy API framework owns connection-invalid state and only sets it for
	 * actual Graph authentication errors.
	 */
	public function test_generate_iframe_management_url_does_not_mark_connection_invalid_for_legacy_transport_failure() {
		$request_count = 0;
		delete_transient( 'wc_facebook_connection_invalid' );
		$this->mock_set_option( MetaExtension::OPTION_ACCESS_TOKEN, 'long-lived-bisu-token' );
		$this->add_filter_with_safe_teardown(
			'pre_http_request',
			function ( $preempt, $request_args, $url ) use ( &$request_count ) {
				++$request_count;
				if ( false !== strpos( $url, 'api.facebook.com' ) ) {
					return array(
						'headers'  => array(),
						'body'     => '{"type":"invalidSession"}',
						'response' => array(
							'code'    => 401,
							'message' => 'Unauthorized',
						),
						'cookies'  => array(),
					);
				}

				$this->assertFalse( get_transient( 'wc_facebook_connection_invalid' ) );
				return new \WP_Error( 'http_request_failed', 'Test legacy transport failure' );
			},
			10,
			3
		);

		$url = MetaExtension::generate_iframe_management_url( 'test-external-business', 'test-cpi' );

		$this->assertSame( '', $url );
		$this->assertSame( 2, $request_count );
		$this->assertFalse( get_transient( 'wc_facebook_connection_invalid' ) );
	}

	/**
	 * Tests that an OAuth error from the legacy fallback marks the connection invalid.
	 */
	public function test_generate_iframe_management_url_preserves_legacy_oauth_error_state() {
		$request_count = 0;
		delete_transient( 'wc_facebook_connection_invalid' );
		$this->mock_set_option( MetaExtension::OPTION_ACCESS_TOKEN, 'long-lived-bisu-token' );
		$this->add_filter_with_safe_teardown(
			'pre_http_request',
			function ( $preempt, $request_args, $url ) use ( &$request_count ) {
				++$request_count;
				if ( false !== strpos( $url, 'api.facebook.com' ) ) {
					return array(
						'headers'  => array(),
						'body'     => '{"title":"Cannot mint Commerce Extension token"}',
						'response' => array(
							'code'    => 403,
							'message' => 'Forbidden',
						),
						'cookies'  => array(),
					);
				}

				return array(
					'headers'  => array(),
					'body'     => wp_json_encode(
						array(
							'error' => array(
								'code'    => 190,
								'message' => 'Invalid OAuth access token.',
								'type'    => 'OAuthException',
							),
						)
					),
					'response' => array(
						'code'    => 400,
						'message' => 'Bad Request',
					),
					'cookies'  => array(),
				);
			},
			10,
			3
		);

		$url = MetaExtension::generate_iframe_management_url( 'test-external-business', 'test-cpi' );

		$this->assertSame( '', $url );
		$this->assertSame( 2, $request_count );
		$this->assertNotFalse( get_transient( 'wc_facebook_connection_invalid' ) );
		delete_transient( 'wc_facebook_connection_invalid' );
	}
}
