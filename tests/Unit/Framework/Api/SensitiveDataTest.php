<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Framework\Api;

use WooCommerce\Facebook\Framework\Api\SensitiveData;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;

/**
 * Unit tests for the SensitiveData redactor.
 *
 * @see SensitiveData
 */
class SensitiveDataTest extends AbstractWPUnitTestWithSafeFiltering {

	/** @var string a token-shaped value, long enough to look like the real thing */
	const TOKEN = 'EAABsbCS1i2gBA1ZCZASomeVeryLongLiveTokenValue0123456789ZDZD';

	/**
	 * Credential parameter names are recognised regardless of case.
	 */
	public function test_is_sensitive_key_matches_credentials_case_insensitively() {
		$this->assertTrue( SensitiveData::is_sensitive_key( 'access_token' ) );
		$this->assertTrue( SensitiveData::is_sensitive_key( 'ACCESS_TOKEN' ) );
		$this->assertTrue( SensitiveData::is_sensitive_key( 'merchant_access_token' ) );
		$this->assertTrue( SensitiveData::is_sensitive_key( 'appsecret_proof' ) );
	}

	/**
	 * Non-credential parameter names are left alone, so logs stay useful.
	 */
	public function test_is_sensitive_key_ignores_non_credentials() {
		$this->assertFalse( SensitiveData::is_sensitive_key( 'catalog_id' ) );
		$this->assertFalse( SensitiveData::is_sensitive_key( 'fields' ) );
		$this->assertFalse( SensitiveData::is_sensitive_key( 'fbe_external_business_id' ) );
	}

	/**
	 * The exact URL shape reported in the bug bounty report is redacted.
	 */
	public function test_redact_url_masks_access_token_in_query_string() {
		$url = 'https://graph.facebook.com/v21.0/fbe_business/fbe_installs?access_token=' . self::TOKEN . '&fbe_external_business_id=wc-abc123';

		$redacted = SensitiveData::redact_url( $url );

		$this->assertStringNotContainsString( self::TOKEN, $redacted );
		$this->assertStringContainsString( 'access_token=' . SensitiveData::REDACTED, $redacted );
	}

	/**
	 * Everything that isn't a credential survives, otherwise the log is useless
	 * for debugging.
	 */
	public function test_redact_url_preserves_endpoint_and_non_sensitive_params() {
		$url = 'https://graph.facebook.com/v21.0/27002437791140/products?fields=id,name&access_token=' . self::TOKEN . '&limit=25';

		$redacted = SensitiveData::redact_url( $url );

		$this->assertStringContainsString( 'https://graph.facebook.com/v21.0/27002437791140/products', $redacted );
		$this->assertStringContainsString( 'fields=id,name', $redacted );
		$this->assertStringContainsString( 'limit=25', $redacted );
	}

	/**
	 * Multiple distinct credentials in one URL are all masked.
	 */
	public function test_redact_url_masks_every_credential_param() {
		$url = 'https://graph.facebook.com/v21.0/me?access_token=' . self::TOKEN . '&appsecret_proof=deadbeef&pretty=1';

		$redacted = SensitiveData::redact_url( $url );

		$this->assertStringNotContainsString( self::TOKEN, $redacted );
		$this->assertStringNotContainsString( 'deadbeef', $redacted );
		$this->assertStringContainsString( 'pretty=1', $redacted );
	}

	/**
	 * A URL with no query string is returned untouched.
	 */
	public function test_redact_url_leaves_query_less_url_untouched() {
		$url = 'https://graph.facebook.com/v21.0/me';

		$this->assertSame( $url, SensitiveData::redact_url( $url ) );
	}

	/**
	 * Nested credentials are masked at any depth.
	 */
	public function test_redact_params_masks_credentials_recursively() {
		$redacted = SensitiveData::redact_params(
			[
				'access_token' => self::TOKEN,
				'catalog_id'   => '27002437791140',
				'nested'       => [
					'merchant_access_token' => self::TOKEN,
					'keep_me'               => 'visible',
				],
			]
		);

		$this->assertSame( SensitiveData::REDACTED, $redacted['access_token'] );
		$this->assertSame( SensitiveData::REDACTED, $redacted['nested']['merchant_access_token'] );
		$this->assertSame( '27002437791140', $redacted['catalog_id'] );
		$this->assertSame( 'visible', $redacted['nested']['keep_me'] );
	}

	/**
	 * Credentials inside a JSON payload are masked, structure preserved.
	 */
	public function test_redact_json_masks_credentials_in_payload() {
		$json = wp_json_encode(
			[
				'data' => [
					[
						'id'           => '1',
						'access_token' => self::TOKEN,
					],
				],
				'ok'   => true,
			]
		);

		$redacted = SensitiveData::redact_json( $json );

		$this->assertStringNotContainsString( self::TOKEN, $redacted );
		$this->assertStringContainsString( SensitiveData::REDACTED, $redacted );
		$this->assertStringContainsString( '"id":"1"', $redacted );
	}

	/**
	 * A truncated or malformed payload still gets scrubbed rather than passed
	 * through verbatim.
	 */
	public function test_redact_json_falls_back_to_text_scrub_on_malformed_input() {
		$malformed = '{"access_token":"' . self::TOKEN . '", TRUNCATED';

		$redacted = SensitiveData::redact_json( $malformed );

		$this->assertStringNotContainsString( self::TOKEN, $redacted );
	}

	/**
	 * The free-form backstop catches credentials in query strings, key/value
	 * lines and Bearer headers alike.
	 */
	public function test_redact_text_masks_credentials_in_free_form_log_output() {
		$text = "method: GET\n"
			. 'uri: https://graph.facebook.com/v21.0/me?access_token=' . self::TOKEN . "\n"
			. 'headers: Authorization: Bearer ' . self::TOKEN . "\n"
			. 'access_token: ' . self::TOKEN . "\n"
			. 'duration: 0.5s';

		$redacted = SensitiveData::redact_text( $text );

		$this->assertStringNotContainsString( self::TOKEN, $redacted );
		$this->assertStringContainsString( 'duration: 0.5s', $redacted );
		$this->assertStringContainsString( 'method: GET', $redacted );
	}

	/**
	 * Empty input is handled without error.
	 */
	public function test_redactors_handle_empty_input() {
		$this->assertSame( '', SensitiveData::redact_url( '' ) );
		$this->assertSame( '', SensitiveData::redact_json( '' ) );
		$this->assertSame( '', SensitiveData::redact_text( '' ) );
		$this->assertSame( [], SensitiveData::redact_params( [] ) );
	}
}
