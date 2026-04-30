<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Helper for emitting the inline JavaScript that validates `event.origin`
 * before any embedded Commerce Partner Hub `postMessage` payload is processed.
 *
 * The emitted validator recognises three families of allowed origins:
 *
 *   1. An exact-match allowlist of fully-qualified origins
 *      (e.g. `https://www.commercepartnerhub.com`). Comparison is exact
 *      string equality against `event.origin`.
 *
 *   2. *Numeric* Facebook on-demand (OD) instances:
 *      `https://www.<digits>.od.<base>`
 *      where `<digits>` is one or more ASCII digits and `<base>` is a known
 *      multi-label base (e.g. `commercepartnerhub.com`).
 *
 *   3. *Aliased* Facebook on-demand instances:
 *      `https://www.<prefix>.<base>` or `https://www.<prefix>-<N>.<base>`
 *      where `<prefix>` is a fixed, server-emitted literal label (default
 *      `my-od`) and `<N>` is one or more ASCII digits used to disambiguate
 *      multiple OD instances assigned to the same developer.
 *
 * The OD validator parses `event.origin` with the URL constructor and then
 * walks the hostname **label-by-label**. We deliberately do NOT use any of
 * the unsafe patterns that have caused production incidents in the past:
 *
 *   * No `String.prototype.endsWith` against the raw origin.
 *   * No `RegExp` over the raw origin.
 *   * No `String.prototype.indexOf` / `split` to "extract" the host.
 *
 * Meta SEV S649287 was a subdomain-confusion bypass against Commerce Partner
 * Hub caused exactly by suffix matching (`endsWith('.commercepartnerhub.com')`)
 * combined with a `split('commercepartnerhub.com')[0]`-based origin
 * reconstruction: an attacker who could register
 * `evilcommercepartnerhub.com` could trivially satisfy the suffix check via
 * `evilcommercepartnerhub.commercepartnerhub.com`, and then have the
 * reconstruction yield their attacker-controlled domain. The remediation
 * (D100887044) replaced suffix matching with strict label-aware checks. This
 * helper enforces the same discipline on the WooCommerce side.
 *
 * @since 3.6.4
 */
class Postmessage_Origin_Validation {

	/**
	 * Returns the allowlist configuration consumed by {@see generate_inline_js()}.
	 *
	 * The shape is intentionally small and explicit so the inline JS does not
	 * have to do any string parsing of base domains at runtime.
	 *
	 * @since 3.6.4
	 *
	 * @return array{exact: string[], od_bases: string[], od_aliases: array<int, array{prefix: string, base: string}>}
	 */
	public static function get_config(): array {
		/**
		 * Filters the postMessage origin allowlist used by inline onboarding scripts.
		 *
		 * Returning extra origins here is the supported way to add support for
		 * additional Commerce Partner Hub deployments (e.g. a private staging
		 * environment) without forking the plugin. Anything added here is
		 * subjected to the same strict label-by-label validation.
		 *
		 * @since 3.6.4
		 *
		 * @param array $config {
		 *     @type string[] $exact      Exact-match origin allowlist (e.g. `https://www.foo.com`).
		 *                                Comparison is byte-for-byte equality with `event.origin`.
		 *     @type string[] $od_bases   Base domains under which numeric Facebook on-demand
		 *                                instances of the form `https://www.<digits>.od.<base>`
		 *                                are accepted. `<digits>` is constrained to `[0-9]+`
		 *                                and validation is performed label-by-label against
		 *                                the parsed URL hostname (never via `endsWith` /
		 *                                regex / substring on the raw origin).
		 *     @type array[]  $od_aliases List of `{prefix, base}` pairs for aliased OD
		 *                                instances of the form `https://www.<prefix>.<base>`
		 *                                or `https://www.<prefix>-<N>.<base>`, where the
		 *                                prefix is the fixed literal label and `<N>` is
		 *                                `[0-9]+`. Both `prefix` and the labels of `base`
		 *                                must match `[a-z0-9]([a-z0-9-]*[a-z0-9])?`; entries
		 *                                that don't are silently dropped.
		 * }
		 */
		return apply_filters(
			'wc_facebook_commerce_partner_allowed_origins',
			array(
				// Commerce Partner Hub is the only origin that legitimately
				// drives the embedded onboarding flow via postMessage. The
				// `www.facebook.com` / `business.facebook.com` origins were
				// trusted by the first cut of this allowlist (see PR #3913)
				// but the iframe is hosted at `commercepartnerhub.com`, so
				// the broader trust grant is unnecessary surface area.
				'exact'      => array(
					'https://www.commercepartnerhub.com',
				),
				'od_bases'   => array(
					'commercepartnerhub.com',
				),
				'od_aliases' => array(
					array(
						'prefix' => 'my-od',
						'base'   => 'commercepartnerhub.com',
					),
				),
			)
		);
	}

	/**
	 * Builds the JavaScript prelude that defines `isAllowedOrigin(origin)`.
	 *
	 * The returned snippet declares two top-level constants
	 * (`STATIC_ALLOWED_ORIGINS`, `OD_ALLOWED_BASE_LABELS`) and an
	 * `isAllowedOrigin` function. Callers are expected to invoke
	 * `isAllowedOrigin(event.origin)` as the FIRST check inside their
	 * `message` handler — *before* dereferencing any field of `event.data`.
	 *
	 * @since 3.6.4
	 *
	 * @return string JavaScript source, safe to embed inside a `<script>` block.
	 */
	public static function generate_inline_js(): string {
		$config = self::get_config();

		$exact = array_values( array_filter( (array) ( $config['exact'] ?? array() ), 'is_string' ) );

		// Pre-split each OD base into a list of lowercase labels so the JS
		// validator never has to call `String.prototype.split` on data that
		// originated from `event.origin`. We also drop any base whose labels
		// contain anything outside [a-z0-9-] so a misconfigured filter cannot
		// inject `*` / quotes / backslashes / etc. into the emitted JSON.
		$od_label_lists = array();
		foreach ( (array) ( $config['od_bases'] ?? array() ) as $base ) {
			$labels = self::sanitize_label_list( $base );
			if ( null !== $labels ) {
				$od_label_lists[] = $labels;
			}
		}

		// Aliased OD form (`www.<prefix>[-<N>].<base>`). Both the prefix label
		// and every base label must independently survive the same DNS-label
		// allowlist before we trust them in the emitted JSON.
		$od_alias_label_lists = array();
		foreach ( (array) ( $config['od_aliases'] ?? array() ) as $alias ) {
			if ( ! is_array( $alias ) ) {
				continue;
			}
			$prefix_input = isset( $alias['prefix'] ) ? $alias['prefix'] : null;
			$base_input   = isset( $alias['base'] ) ? $alias['base'] : null;
			if ( ! is_string( $prefix_input ) || ! is_string( $base_input ) ) {
				continue;
			}
			$prefix_labels = self::sanitize_label_list( $prefix_input );
			$base_labels   = self::sanitize_label_list( $base_input );
			// The alias prefix must be exactly one label (we match it against
			// `labels[1]` directly), and the base must be at least two labels.
			if ( null === $prefix_labels || 1 !== count( $prefix_labels ) ) {
				continue;
			}
			if ( null === $base_labels ) {
				continue;
			}
			$od_alias_label_lists[] = array(
				'prefix' => $prefix_labels[0],
				'base'   => $base_labels,
			);
		}

		$exact_json    = wp_json_encode( $exact );
		$od_bases_json = wp_json_encode( $od_label_lists );
		$od_alias_json = wp_json_encode( $od_alias_label_lists );

		/*
		 * The validator below intentionally avoids:
		 *   - String.prototype.endsWith / startsWith / includes against `origin`.
		 *   - RegExp over the raw origin.
		 *   - String slicing to "find" the hostname.
		 * Instead it (a) tries exact-match against the static allowlist, then
		 * (b) parses the origin with `new URL(...)` and walks hostname labels
		 * against an explicit per-base label list. See SEV S649287 for the
		 * class of bypasses this is designed to prevent.
		 */
		return <<<JAVASCRIPT
const STATIC_ALLOWED_ORIGINS = {$exact_json};
const OD_ALLOWED_BASE_LABELS = {$od_bases_json};
const OD_ALLOWED_ALIAS_LABELS = {$od_alias_json};
function isAllowedOrigin(origin) {
	if (typeof origin !== 'string' || origin.length === 0) {
		return false;
	}
	if (STATIC_ALLOWED_ORIGINS.indexOf(origin) !== -1) {
		return true;
	}
	const hasOdBases = OD_ALLOWED_BASE_LABELS && OD_ALLOWED_BASE_LABELS.length > 0;
	const hasOdAliases = OD_ALLOWED_ALIAS_LABELS && OD_ALLOWED_ALIAS_LABELS.length > 0;
	if (!hasOdBases && !hasOdAliases) {
		return false;
	}
	let parsed;
	try {
		parsed = new URL(origin);
	} catch (e) {
		return false;
	}
	if (parsed.protocol !== 'https:' || parsed.port !== '' || parsed.username !== '' || parsed.password !== '') {
		return false;
	}
	let host = parsed.hostname.toLowerCase();
	while (host.length > 0 && host.charAt(host.length - 1) === '.') {
		host = host.substring(0, host.length - 1);
	}
	if (host.length === 0) {
		return false;
	}
	const labels = host.split('.');
	if (labels.length < 4 || labels[0] !== 'www') {
		return false;
	}
	const DIGITS = /^[0-9]+\$/;
	// Format A: www.<digits>.od.<base>
	for (let i = 0; i < OD_ALLOWED_BASE_LABELS.length; i++) {
		const base = OD_ALLOWED_BASE_LABELS[i];
		if (labels.length !== 3 + base.length) {
			continue;
		}
		if (labels[1].length === 0 || labels[1].length > 63 || !DIGITS.test(labels[1])) {
			continue;
		}
		if (labels[2] !== 'od') {
			continue;
		}
		let baseMatches = true;
		for (let j = 0; j < base.length; j++) {
			if (labels[3 + j] !== base[j]) {
				baseMatches = false;
				break;
			}
		}
		if (baseMatches) {
			return true;
		}
	}
	// Format B: www.<prefix>[-<digits>].<base>
	for (let i = 0; i < OD_ALLOWED_ALIAS_LABELS.length; i++) {
		const alias = OD_ALLOWED_ALIAS_LABELS[i];
		const base = alias.base;
		if (labels.length !== 2 + base.length) {
			continue;
		}
		const idLabel = labels[1];
		if (idLabel.length === 0 || idLabel.length > 63) {
			continue;
		}
		if (idLabel !== alias.prefix) {
			const sep = alias.prefix.length;
			if (idLabel.length <= sep + 1) {
				continue;
			}
			if (idLabel.charAt(sep) !== '-') {
				continue;
			}
			if (idLabel.substring(0, sep) !== alias.prefix) {
				continue;
			}
			if (!DIGITS.test(idLabel.substring(sep + 1))) {
				continue;
			}
		}
		let baseMatches = true;
		for (let j = 0; j < base.length; j++) {
			if (labels[2 + j] !== base[j]) {
				baseMatches = false;
				break;
			}
		}
		if (baseMatches) {
			return true;
		}
	}
	return false;
}
JAVASCRIPT;
	}

	/**
	 * Normalises and validates a dotted DNS-name fragment (or single label) into
	 * a list of lowercase labels, returning `null` if any label is invalid.
	 *
	 * Each label must independently match
	 * `[a-z0-9]([a-z0-9-]*[a-z0-9])?`, mirroring RFC 1123 label rules
	 * restricted to ASCII (no IDN, no underscores). This is the gate that keeps
	 * a misconfigured filter from injecting non-label characters into the
	 * server-emitted JSON literal.
	 *
	 * @param string $value Dotted name (e.g. `commercepartnerhub.com`) or
	 *                      single label (e.g. `my-od`).
	 *
	 * @return array<int, string>|null List of validated labels, or null on failure.
	 */
	private static function sanitize_label_list( $value ): ?array {
		if ( ! is_string( $value ) || '' === $value ) {
			return null;
		}
		$labels = explode( '.', strtolower( trim( $value, '.' ) ) );
		if ( empty( $labels ) ) {
			return null;
		}
		foreach ( $labels as $label ) {
			if ( '' === $label || strlen( $label ) > 63 ) {
				return null;
			}
			if ( ! preg_match( '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $label ) ) {
				return null;
			}
		}
		return array_values( $labels );
	}
}
