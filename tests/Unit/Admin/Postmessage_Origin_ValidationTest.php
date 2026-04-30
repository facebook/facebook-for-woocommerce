<?php
/**
 * Tests for {@see \WooCommerce\Facebook\Admin\Postmessage_Origin_Validation}.
 *
 * The validator is the trust boundary for the inline `message` handlers in
 * `Shops` and `WhatsApp_Integration_Settings`. We test it at two levels:
 *
 *   1. PHP-level shape: the emitted JS string declares the expected globals
 *      and references safe primitives (the `URL` constructor and label-by-label
 *      walking) but never references the unsafe primitives that have caused
 *      production incidents (`String.prototype.endsWith`, regex over the raw
 *      origin, `split` on the origin string). See SEV S649287.
 *
 *   2. Filter behavior: extending `wc_facebook_commerce_partner_allowed_origins`
 *      with a new OD base or a new exact origin propagates into the emitted JS,
 *      and entries with characters outside `[a-z0-9-]` are silently dropped to
 *      prevent a misconfigured filter from injecting JSON into the inline script.
 *
 * @package WooCommerce\Facebook\Tests\Unit\Admin
 */

namespace WooCommerce\Facebook\Tests\Admin;

use WooCommerce\Facebook\Admin\Postmessage_Origin_Validation;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * @covers \WooCommerce\Facebook\Admin\Postmessage_Origin_Validation
 */
class Postmessage_Origin_ValidationTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	public function test_default_config_includes_only_commerce_partner_origins() {
		$config = Postmessage_Origin_Validation::get_config();

		$this->assertIsArray( $config );
		$this->assertArrayHasKey( 'exact', $config );
		$this->assertArrayHasKey( 'od_bases', $config );
		$this->assertArrayHasKey( 'od_aliases', $config );

		$this->assertContains( 'https://www.commercepartnerhub.com', $config['exact'] );

		// `www.facebook.com` / `business.facebook.com` were trusted by PR #3913 but
		// the iframe is hosted at commercepartnerhub.com — the broader grant is
		// unnecessary surface area and must be off by default.
		$this->assertNotContains( 'https://www.facebook.com', $config['exact'] );
		$this->assertNotContains( 'https://business.facebook.com', $config['exact'] );

		$this->assertContains( 'commercepartnerhub.com', $config['od_bases'] );

		// `my-od` aliasing should be on by default for commercepartnerhub.com.
		$found_my_od_alias = false;
		foreach ( $config['od_aliases'] as $alias ) {
			if ( isset( $alias['prefix'], $alias['base'] )
				&& 'my-od' === $alias['prefix']
				&& 'commercepartnerhub.com' === $alias['base'] ) {
				$found_my_od_alias = true;
				break;
			}
		}
		$this->assertTrue( $found_my_od_alias, '`my-od` alias for commercepartnerhub.com must be in defaults' );
	}

	public function test_emitted_js_declares_expected_globals_and_function() {
		$js = Postmessage_Origin_Validation::generate_inline_js();

		$this->assertStringContainsString( 'const STATIC_ALLOWED_ORIGINS = ', $js );
		$this->assertStringContainsString( 'const OD_ALLOWED_BASE_LABELS = ', $js );
		$this->assertStringContainsString( 'const OD_ALLOWED_ALIAS_LABELS = ', $js );
		$this->assertStringContainsString( 'function isAllowedOrigin(origin)', $js );
	}

	public function test_emitted_js_contains_default_static_origins_and_od_base() {
		$js = Postmessage_Origin_Validation::generate_inline_js();

		// Origins are JSON-encoded — the assertions match the JSON form.
		$this->assertStringContainsString( '"https:\/\/www.commercepartnerhub.com"', $js );

		// `www.facebook.com` / `business.facebook.com` were trusted by PR #3913 and
		// must NOT leak into the emitted JS now that they're out of the defaults.
		$this->assertStringNotContainsString( '"https:\/\/www.facebook.com"', $js );
		$this->assertStringNotContainsString( '"https:\/\/business.facebook.com"', $js );

		// OD base is pre-split into label arrays so the JS does not have to.
		$this->assertStringContainsString( '[["commercepartnerhub","com"]]', $js );

		// my-od alias is exposed as a `{prefix, base}` object.
		$this->assertStringContainsString( '{"prefix":"my-od","base":["commercepartnerhub","com"]}', $js );
	}

	/**
	 * Format A `<id>` is now restricted to ASCII digits. The emitted JS must
	 * use a `^[0-9]+$` regex (rather than the looser
	 * `[a-z0-9]([a-z0-9-]*[a-z0-9])?` that was in the first cut).
	 */
	public function test_emitted_js_uses_digit_only_id_label_regex() {
		$js = Postmessage_Origin_Validation::generate_inline_js();

		$this->assertStringContainsString( '/^[0-9]+$/', $js );
		$this->assertStringNotContainsString( '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $js );
	}

	/**
	 * SEV S649287 was a subdomain-confusion bypass caused by `endsWith` matching
	 * + `split('commercepartnerhub.com')[0]` reconstruction. The validator must
	 * never reach for those primitives — it must parse the URL with `new URL(...)`
	 * and walk hostname labels.
	 */
	public function test_emitted_js_does_not_use_unsafe_origin_primitives() {
		$js = Postmessage_Origin_Validation::generate_inline_js();

		$this->assertStringNotContainsString( '.endsWith(', $js );
		$this->assertStringNotContainsString( ".endsWith('", $js );
		$this->assertStringNotContainsString( "indexOf('.commercepartnerhub", $js );
		$this->assertStringNotContainsString( "indexOf('.od.", $js );
		$this->assertStringNotContainsString( ".match(/", $js );

		$this->assertStringContainsString( 'new URL(origin)', $js );
		$this->assertStringContainsString( "parsed.protocol !== 'https:'", $js );
		$this->assertStringContainsString( 'host.split(', $js );
	}

	public function test_filter_can_add_extra_exact_origin() {
		$callback = static function ( array $config ) {
			$config['exact'][] = 'https://staging.example.com';
			return $config;
		};
		add_filter( 'wc_facebook_commerce_partner_allowed_origins', $callback );

		try {
			$config = Postmessage_Origin_Validation::get_config();
			$js     = Postmessage_Origin_Validation::generate_inline_js();
		} finally {
			remove_filter( 'wc_facebook_commerce_partner_allowed_origins', $callback );
		}

		$this->assertContains( 'https://staging.example.com', $config['exact'] );
		$this->assertStringContainsString( '"https:\/\/staging.example.com"', $js );
	}

	public function test_filter_can_add_extra_od_base() {
		$callback = static function ( array $config ) {
			$config['od_bases'][] = 'staging-cph.example.com';
			return $config;
		};
		add_filter( 'wc_facebook_commerce_partner_allowed_origins', $callback );

		try {
			$js = Postmessage_Origin_Validation::generate_inline_js();
		} finally {
			remove_filter( 'wc_facebook_commerce_partner_allowed_origins', $callback );
		}

		$this->assertStringContainsString( '["staging-cph","example","com"]', $js );
	}

	public function test_filter_can_add_extra_od_alias() {
		$callback = static function ( array $config ) {
			$config['od_aliases'][] = array(
				'prefix' => 'staging-od',
				'base'   => 'staging-cph.example.com',
			);
			return $config;
		};
		add_filter( 'wc_facebook_commerce_partner_allowed_origins', $callback );

		try {
			$js = Postmessage_Origin_Validation::generate_inline_js();
		} finally {
			remove_filter( 'wc_facebook_commerce_partner_allowed_origins', $callback );
		}

		$this->assertStringContainsString( '{"prefix":"staging-od","base":["staging-cph","example","com"]}', $js );
	}

	public function test_filter_drops_od_bases_with_unsafe_characters() {
		$callback = static function ( array $config ) {
			$config['od_bases'] = array(
				'commercepartnerhub.com',
				// All of these must be silently dropped — they would otherwise
				// allow a misconfigured filter to inject arbitrary JSON.
				'evil.com"; alert(1); var x = "',
				'foo<script>.com',
				'foo bar.com',
				'foo*.com',
				'.leadingdot.com',
				'',
			);
			return $config;
		};
		add_filter( 'wc_facebook_commerce_partner_allowed_origins', $callback );

		try {
			$js = Postmessage_Origin_Validation::generate_inline_js();
		} finally {
			remove_filter( 'wc_facebook_commerce_partner_allowed_origins', $callback );
		}

		// The valid base survives.
		$this->assertStringContainsString( '[["commercepartnerhub","com"]]', $js );

		// None of the unsafe substrings should leak into the emitted JS.
		$this->assertStringNotContainsString( 'alert(1)', $js );
		$this->assertStringNotContainsString( '<script>', $js );
		$this->assertStringNotContainsString( 'foo bar', $js );
		$this->assertStringNotContainsString( 'foo*', $js );
		$this->assertStringNotContainsString( 'leadingdot', $js );
	}

	public function test_filter_drops_od_aliases_with_unsafe_characters_or_shapes() {
		$callback = static function ( array $config ) {
			$config['od_aliases'] = array(
				array(
					'prefix' => 'my-od',
					'base'   => 'commercepartnerhub.com',
				),
				// Multi-label prefix is not allowed — alias prefix must be a single label.
				array(
					'prefix' => 'foo.bar',
					'base'   => 'commercepartnerhub.com',
				),
				// Underscore is not in the [a-z0-9-] allowlist.
				array(
					'prefix' => 'bad_prefix',
					'base'   => 'commercepartnerhub.com',
				),
				// Unsafe base characters.
				array(
					'prefix' => 'good',
					'base'   => 'evil.com"; alert(1); var x = "',
				),
				// Empty / non-string fields.
				array(
					'prefix' => '',
					'base'   => 'commercepartnerhub.com',
				),
				array(
					'prefix' => 'good',
					'base'   => '',
				),
				'not even an array',
			);
			return $config;
		};
		add_filter( 'wc_facebook_commerce_partner_allowed_origins', $callback );

		try {
			$js = Postmessage_Origin_Validation::generate_inline_js();
		} finally {
			remove_filter( 'wc_facebook_commerce_partner_allowed_origins', $callback );
		}

		$this->assertStringContainsString( '[{"prefix":"my-od","base":["commercepartnerhub","com"]}]', $js );

		$this->assertStringNotContainsString( 'foo.bar', $js );
		$this->assertStringNotContainsString( 'bad_prefix', $js );
		$this->assertStringNotContainsString( 'alert(1)', $js );
	}

	public function test_filter_returning_empty_arrays_is_handled() {
		$callback = static function () {
			return array(
				'exact'      => array(),
				'od_bases'   => array(),
				'od_aliases' => array(),
			);
		};
		add_filter( 'wc_facebook_commerce_partner_allowed_origins', $callback );

		try {
			$js = Postmessage_Origin_Validation::generate_inline_js();
		} finally {
			remove_filter( 'wc_facebook_commerce_partner_allowed_origins', $callback );
		}

		$this->assertStringContainsString( 'const STATIC_ALLOWED_ORIGINS = []', $js );
		$this->assertStringContainsString( 'const OD_ALLOWED_BASE_LABELS = []', $js );
		$this->assertStringContainsString( 'const OD_ALLOWED_ALIAS_LABELS = []', $js );
		// `isAllowedOrigin` is still emitted and short-circuits to false on
		// every origin when both lists are empty.
		$this->assertStringContainsString( 'function isAllowedOrigin(origin)', $js );
	}

	/**
	 * The validator is consumed by an inline `<script>` block, so it must
	 * always parse on its own as a syntactically-valid script. We sanity-check
	 * by piping through `node --check`.
	 */
	public function test_emitted_js_parses_as_valid_javascript() {
		$js = Postmessage_Origin_Validation::generate_inline_js();

		$which_node = shell_exec( 'command -v node 2>/dev/null' );
		if ( null === $which_node || '' === trim( (string) $which_node ) ) {
			$this->markTestSkipped( 'node is not available in this environment' );
		}

		$tmp = tempnam( sys_get_temp_dir(), 'fbwc_validator_' ) . '.js';
		file_put_contents( $tmp, $js . "\nisAllowedOrigin('https://example.com');\n" );

		$cmd    = sprintf( 'node --check %s 2>&1', escapeshellarg( $tmp ) );
		$output = shell_exec( $cmd );
		$exit   = 0;
		exec( $cmd, $unused, $exit );

		@unlink( $tmp );

		$this->assertSame( 0, $exit, 'Emitted JS failed to parse: ' . (string) $output );
	}

	/**
	 * End-to-end runtime check: render the validator JS, evaluate it in Node,
	 * and assert the actual `isAllowedOrigin` decision against representative
	 * positive cases and S649287-class negative cases for both Format A
	 * (`www.<digits>.od.<base>`) and Format B (`www.my-od[-<N>].<base>`).
	 *
	 * This complements the string-shape assertions above by exercising the
	 * actual control flow of the emitted JS.
	 */
	public function test_runtime_decisions_for_static_format_a_and_format_b() {
		$which_node = shell_exec( 'command -v node 2>/dev/null' );
		if ( null === $which_node || '' === trim( (string) $which_node ) ) {
			$this->markTestSkipped( 'node is not available in this environment' );
		}

		$js = Postmessage_Origin_Validation::generate_inline_js();

		$cases = array(
			// Static (allow)
			array( 'https://www.commercepartnerhub.com', true ),
			// Removed from defaults (PR #3913 trusted these; we no longer do).
			array( 'https://www.facebook.com', false ),
			array( 'https://business.facebook.com', false ),

			// Format A (allow)
			array( 'https://www.12345.od.commercepartnerhub.com', true ),
			array( 'https://www.0.od.commercepartnerhub.com', true ),
			// Format A digits-only is strict (deny letters/dashes).
			array( 'https://www.dev1.od.commercepartnerhub.com', false ),
			array( 'https://www.abc-123.od.commercepartnerhub.com', false ),

			// Format B (allow)
			array( 'https://www.my-od.commercepartnerhub.com', true ),
			array( 'https://www.my-od-2.commercepartnerhub.com', true ),
			array( 'https://www.my-od-37.commercepartnerhub.com', true ),
			// Format B is strict about the prefix shape.
			array( 'https://www.my-odd.commercepartnerhub.com', false ),
			array( 'https://www.my-od2.commercepartnerhub.com', false ),
			array( 'https://www.my-od-.commercepartnerhub.com', false ),
			array( 'https://www.my-od-2-3.commercepartnerhub.com', false ),
			array( 'https://www.my-od-abc.commercepartnerhub.com', false ),
			array( 'https://www.notmy-od.commercepartnerhub.com', false ),

			// SEV S649287-class bypasses (deny) for both formats.
			array( 'https://josiptestingcommercepartnerhub.com', false ),
			array( 'https://evilcommercepartnerhub.com', false ),
			array( 'https://www.commercepartnerhub.com.attacker.com', false ),
			array( 'https://www.12345.od.commercepartnerhub.com.attacker.com', false ),
			array( 'https://www.12345.od.evilcommercepartnerhub.com', false ),
			array( 'https://www.12345.od2.commercepartnerhub.com', false ),
			array( 'https://www.my-od.commercepartnerhub.com.attacker.com', false ),
			array( 'https://www.my-od-2.evilcommercepartnerhub.com', false ),

			// Protocol / port / userinfo (deny).
			array( 'http://www.commercepartnerhub.com', false ),
			array( 'http://www.12345.od.commercepartnerhub.com', false ),
			array( 'http://www.my-od.commercepartnerhub.com', false ),
			array( 'https://www.commercepartnerhub.com:8443', false ),
			array( 'https://attacker@www.my-od.commercepartnerhub.com', false ),
			array( 'javascript://www.my-od.commercepartnerhub.com/%0aalert(1)', false ),

			// Bogus.
			array( '', false ),
			array( 'null', false ),
			array( 'about:blank', false ),
			array( 'file:///etc/passwd', false ),
		);

		$tmp_js     = tempnam( sys_get_temp_dir(), 'fbwc_validator_' ) . '.js';
		$tmp_runner = tempnam( sys_get_temp_dir(), 'fbwc_runner_' ) . '.js';
		try {
			file_put_contents( $tmp_js, $js );
			$runner_template = <<<'NODE_HARNESS'
const fs = require('fs');
const src = fs.readFileSync(%JS_PATH%, 'utf8');
const factory = new Function(src + '\nreturn isAllowedOrigin;');
const isAllowedOrigin = factory();
const cases = %CASES%;
const results = cases.map(function (c) {
	return { origin: c[0], expected: c[1], actual: isAllowedOrigin(c[0]) };
});
process.stdout.write(JSON.stringify(results));
NODE_HARNESS;
			$runner = strtr(
				$runner_template,
				array(
					'%JS_PATH%' => wp_json_encode( $tmp_js ),
					'%CASES%'   => wp_json_encode( $cases ),
				)
			);
			file_put_contents( $tmp_runner, $runner );

			$cmd    = sprintf( 'node %s 2>&1', escapeshellarg( $tmp_runner ) );
			$output = shell_exec( $cmd );
			$this->assertNotEmpty( $output, 'node runtime harness produced no output' );

			$results = json_decode( (string) $output, true );
			$this->assertIsArray( $results, 'node runtime harness produced non-JSON output: ' . (string) $output );

			foreach ( $results as $r ) {
				$this->assertSame(
					$r['expected'],
					$r['actual'],
					sprintf( 'isAllowedOrigin(%s) expected %s got %s', $r['origin'], var_export( $r['expected'], true ), var_export( $r['actual'], true ) )
				);
			}
		} finally {
			@unlink( $tmp_js );
			@unlink( $tmp_runner );
		}
	}
}
