<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * Meta for WooCommerce.
 */

namespace WooCommerce\Facebook\Framework\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Redacts credentials from API request/response data before it is logged.
 *
 * The plugin writes API request and response data to the WooCommerce log when debug
 * mode is enabled. Anything that reaches that log can end up in a support bundle, a
 * CI artifact, or a merchant's uploads directory, so credentials must be stripped
 * before the data is handed to the logger — never after.
 *
 * This is a deliberately narrow, key-based redactor: it masks the values of known
 * credential parameters and leaves everything else (paths, catalog IDs, product IDs,
 * field lists) untouched, so the logs remain useful for debugging.
 *
 * @since 3.7.3
 */
class SensitiveData {

	/** @var string written in place of a credential value */
	const REDACTED = '***REDACTED***';

	/**
	 * Parameter/field names whose values are credentials and must never be logged.
	 *
	 * Compared case-insensitively. Keep this list in sync with the backstop patterns
	 * in {@see self::redact_text()}.
	 *
	 * @var string[]
	 */
	const SENSITIVE_KEYS = [
		'access_token',
		'merchant_access_token',
		'page_access_token',
		'system_user_access_token',
		'user_access_token',
		'client_secret',
		'client_token',
		'access_client_token',
		'appsecret_proof',
		'input_token',
		'token',
		'secret',
		'password',
		'authorization',
	];

	/**
	 * Determines whether a parameter name holds a credential.
	 *
	 * @since 3.7.3
	 *
	 * @param string $key parameter name.
	 * @return bool
	 */
	public static function is_sensitive_key( $key ) {
		return in_array( strtolower( trim( (string) $key ) ), self::SENSITIVE_KEYS, true );
	}

	/**
	 * Recursively masks credential values in an array of parameters or data.
	 *
	 * @since 3.7.3
	 *
	 * @param array $params parameters to redact.
	 * @return array redacted copy of the parameters
	 */
	public static function redact_params( array $params ) {
		foreach ( $params as $key => $value ) {
			if ( self::is_sensitive_key( $key ) ) {
				$params[ $key ] = self::REDACTED;
			} elseif ( is_array( $value ) ) {
				$params[ $key ] = self::redact_params( $value );
			}
		}

		return $params;
	}

	/**
	 * Masks credential values in a URL's query string.
	 *
	 * Operates on the raw query string rather than parsing and rebuilding the URL, so
	 * that every non-sensitive part of the URL is preserved byte for byte and the log
	 * still shows exactly which endpoint was called.
	 *
	 * @since 3.7.3
	 *
	 * @param string $url URL that may carry credentials in its query string.
	 * @return string URL with credential values masked
	 */
	public static function redact_url( $url ) {
		$url = (string) $url;

		if ( '' === $url || false === strpos( $url, '=' ) ) {
			return $url;
		}

		return (string) preg_replace_callback(
			'/([?&])([^=&#]+)=([^&#]*)/',
			static function ( $matches ) {
				if ( ! self::is_sensitive_key( rawurldecode( $matches[2] ) ) ) {
					return $matches[0];
				}

				return $matches[1] . $matches[2] . '=' . self::REDACTED;
			},
			$url
		);
	}

	/**
	 * Masks credential values in a JSON string.
	 *
	 * Falls back to {@see self::redact_text()} when the input is not valid JSON, so a
	 * malformed or truncated payload is still scrubbed rather than passed through.
	 *
	 * @since 3.7.3
	 *
	 * @param string $json JSON string that may carry credentials.
	 * @return string JSON with credential values masked
	 */
	public static function redact_json( $json ) {
		$json = (string) $json;

		if ( '' === $json ) {
			return $json;
		}

		$decoded = json_decode( $json, true );

		if ( ! is_array( $decoded ) ) {
			return self::redact_text( $json );
		}

		$encoded = wp_json_encode( self::redact_params( $decoded ) );

		return false === $encoded ? self::redact_text( $json ) : $encoded;
	}

	/**
	 * Last-resort scrub for arbitrary text that is about to be logged.
	 *
	 * Catches credentials in free-form text — `key=value` pairs, `key: value` lines,
	 * JSON members and `Bearer` headers — for call paths that bypass the structured
	 * redactors above. This is a backstop, not the primary defence: prefer
	 * {@see self::redact_params()}, {@see self::redact_url()} or
	 * {@see self::redact_json()} wherever the structure is known.
	 *
	 * @since 3.7.3
	 *
	 * @param string $text text to redact.
	 * @return string text with credential values masked
	 */
	public static function redact_text( $text ) {
		$text = (string) $text;

		if ( '' === $text ) {
			return $text;
		}

		$keys = implode( '|', array_map( 'preg_quote', self::SENSITIVE_KEYS ) );

		$patterns = [
			// JSON members, e.g. "access_token":"EAA…".
			'/(["\'](?:' . $keys . ')["\']\s*:\s*)"[^"]*"/i',
			// Query string and form-encoded pairs, e.g. ?access_token=EAA….
			'/\b(' . $keys . ')=[^&\s"\'<>]*/i',
			// Key/value lines, e.g. "access_token: EAA…".
			'/^(\s*(?:' . $keys . ')\s*:\s*).*$/im',
			// Authorization header values.
			'/\bBearer\s+\S+/i',
		];

		$replacements = [
			'$1"' . self::REDACTED . '"',
			'$1=' . self::REDACTED,
			'${1}' . self::REDACTED,
			'Bearer ' . self::REDACTED,
		];

		return (string) preg_replace( $patterns, $replacements, $text );
	}
}
