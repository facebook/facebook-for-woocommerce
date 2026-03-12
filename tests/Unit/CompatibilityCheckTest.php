<?php

namespace WooCommerce\Facebook\Tests;

class CompatibilityCheckTest extends AbstractWPUnitTestWithSafeFiltering {

	/**
	 * @var \WC_Facebook_Loader
	 */
	private $loader;

	private $basename = 'facebook-for-woocommerce/facebook-for-woocommerce.php';

	public function setUp(): void {
		parent::setUp();
		$this->loader = \WC_Facebook_Loader::instance();
	}

	public function tearDown(): void {
		// Reset cached entry via deactivation_cleanup side effect.
		$ref = new \ReflectionProperty( \WC_Facebook_Loader::class, 'compat_cached_entry' );
		$ref->setAccessible( true );
		$ref->setValue( null, null );
		parent::tearDown();
	}

	private function make_transient( string $installed_version = '3.5.18' ): object {
		$transient            = new \stdClass();
		$transient->checked   = [ $this->basename => $installed_version ];
		$transient->response  = [];
		$transient->no_update = [];

		return $transient;
	}

	private function make_wporg_entry( string $version = '3.6.0' ): object {
		$entry               = new \stdClass();
		$entry->slug         = 'facebook-for-woocommerce';
		$entry->plugin       = $this->basename;
		$entry->new_version  = $version;
		$entry->package      = 'https://downloads.wordpress.org/plugin/facebook-for-woocommerce.' . $version . '.zip';
		$entry->url          = 'https://wordpress.org/plugins/facebook-for-woocommerce/';
		$entry->tested       = '6.9';
		$entry->requires_php = '7.4';
		$entry->requires     = '5.6';

		return $entry;
	}

	private function make_woocom_entry( string $version = '3.5.18' ): object {
		$entry               = new \stdClass();
		$entry->slug         = 'facebook-for-woocommerce';
		$entry->plugin       = $this->basename;
		$entry->new_version  = $version;
		$entry->package      = 'https://woocommerce.com/wp-content/uploads/facebook-for-woocommerce.zip';
		$entry->url          = 'https://woocommerce.com/products/facebook-for-woocommerce/';

		return $entry;
	}

	// ─── capture tests ───

	public function test_capture_stashes_wporg_response_entry() {
		$transient = $this->make_transient();
		$transient->response[ $this->basename ] = $this->make_wporg_entry( '3.6.0' );

		$this->loader->compat_capture_entry( $transient );

		$tampered = $this->make_transient();
		$tampered->response[ $this->basename ] = $this->make_woocom_entry( '3.5.18' );

		$result = $this->loader->compat_verify_entry( $tampered );

		$this->assertEquals( '3.6.0', $result->response[ $this->basename ]->new_version );
		$this->assertStringContainsString( 'downloads.wordpress.org', $result->response[ $this->basename ]->package );
	}

	public function test_capture_stashes_wporg_no_update_entry() {
		$transient = $this->make_transient( '3.6.0' );
		$transient->no_update[ $this->basename ] = $this->make_wporg_entry( '3.6.0' );

		$this->loader->compat_capture_entry( $transient );

		$tampered = $this->make_transient( '3.5.18' );

		$result = $this->loader->compat_verify_entry( $tampered );

		$this->assertEquals( '3.6.0', $result->response[ $this->basename ]->new_version );
	}

	public function test_capture_ignores_non_wporg_entry() {
		$transient = $this->make_transient();
		$transient->response[ $this->basename ] = $this->make_woocom_entry( '3.6.0' );

		$this->loader->compat_capture_entry( $transient );

		$this->add_filter_with_safe_teardown( 'pre_http_request', function () {
			return new \WP_Error( 'timeout', 'Request timed out' );
		}, 10, 3 );

		$tampered = $this->make_transient();
		$result   = $this->loader->compat_verify_entry( $tampered );

		$this->assertArrayNotHasKey( $this->basename, $result->response );
	}

	public function test_capture_returns_transient_unchanged() {
		$transient = $this->make_transient();
		$transient->response[ $this->basename ] = $this->make_wporg_entry();

		$result = $this->loader->compat_capture_entry( $transient );

		$this->assertSame( $transient, $result );
	}

	public function test_capture_handles_non_object_transient() {
		$result = $this->loader->compat_capture_entry( false );
		$this->assertFalse( $result );
	}

	// ─── verify tests: normal operation ───

	public function test_verify_does_not_modify_valid_wporg_entry() {
		$transient = $this->make_transient( '3.5.18' );
		$transient->response[ $this->basename ] = $this->make_wporg_entry( '3.6.0' );

		$result = $this->loader->compat_verify_entry( $transient );

		$this->assertEquals( '3.6.0', $result->response[ $this->basename ]->new_version );
		$this->assertStringContainsString( 'downloads.wordpress.org', $result->response[ $this->basename ]->package );
	}

	public function test_verify_does_not_inject_when_up_to_date() {
		$transient = $this->make_transient( '3.6.0' );
		$transient->no_update[ $this->basename ] = $this->make_wporg_entry( '3.6.0' );
		$this->loader->compat_capture_entry( $transient );

		$check  = $this->make_transient( '3.6.0' );
		$result = $this->loader->compat_verify_entry( $check );

		$this->assertArrayNotHasKey( $this->basename, $result->response );
	}

	public function test_verify_returns_non_object_unchanged() {
		$this->assertFalse( $this->loader->compat_verify_entry( false ) );
	}

	public function test_verify_skips_when_checked_is_empty() {
		$transient          = new \stdClass();
		$transient->checked = [];

		$result = $this->loader->compat_verify_entry( $transient );
		$this->assertSame( $transient, $result );
	}

	public function test_verify_skips_when_plugin_not_in_checked() {
		$transient           = new \stdClass();
		$transient->checked  = [ 'other-plugin/other-plugin.php' => '1.0.0' ];
		$transient->response = [];

		$result = $this->loader->compat_verify_entry( $transient );
		$this->assertSame( $transient, $result );
	}

	// ─── verify tests: interference detection ───

	public function test_verify_replaces_non_wporg_entry_with_stashed() {
		$stash = $this->make_transient();
		$stash->response[ $this->basename ] = $this->make_wporg_entry( '3.6.0' );
		$this->loader->compat_capture_entry( $stash );

		$tampered = $this->make_transient( '3.5.18' );
		$tampered->response[ $this->basename ] = $this->make_woocom_entry( '3.5.18' );

		$result = $this->loader->compat_verify_entry( $tampered );

		$this->assertEquals( '3.6.0', $result->response[ $this->basename ]->new_version );
		$this->assertStringContainsString( 'downloads.wordpress.org', $result->response[ $this->basename ]->package );
	}

	public function test_verify_restores_when_entry_removed() {
		$stash = $this->make_transient();
		$stash->response[ $this->basename ] = $this->make_wporg_entry( '3.6.0' );
		$this->loader->compat_capture_entry( $stash );

		$tampered = $this->make_transient( '3.5.18' );

		$result = $this->loader->compat_verify_entry( $tampered );

		$this->assertArrayHasKey( $this->basename, $result->response );
		$this->assertEquals( '3.6.0', $result->response[ $this->basename ]->new_version );
	}

	public function test_verify_restores_when_package_is_empty() {
		$stash = $this->make_transient();
		$stash->response[ $this->basename ] = $this->make_wporg_entry( '3.6.0' );
		$this->loader->compat_capture_entry( $stash );

		$tampered = $this->make_transient( '3.5.18' );
		$broken   = $this->make_woocom_entry( '3.6.0' );
		$broken->package = '';
		$tampered->response[ $this->basename ] = $broken;

		$result = $this->loader->compat_verify_entry( $tampered );

		$this->assertStringContainsString( 'downloads.wordpress.org', $result->response[ $this->basename ]->package );
	}

	public function test_verify_removes_from_no_update_when_injecting() {
		$stash = $this->make_transient();
		$stash->response[ $this->basename ] = $this->make_wporg_entry( '3.6.0' );
		$this->loader->compat_capture_entry( $stash );

		$tampered = $this->make_transient( '3.5.18' );
		$tampered->no_update[ $this->basename ] = $this->make_woocom_entry( '3.5.18' );

		$result = $this->loader->compat_verify_entry( $tampered );

		$this->assertArrayNotHasKey( $this->basename, $result->no_update );
		$this->assertArrayHasKey( $this->basename, $result->response );
	}

	// ─── version staleness ───

	public function test_verify_corrects_stale_wporg_version() {
		$stash = $this->make_transient();
		$stash->response[ $this->basename ] = $this->make_wporg_entry( '3.7.0' );
		$this->loader->compat_capture_entry( $stash );

		$transient = $this->make_transient( '3.5.18' );
		$transient->response[ $this->basename ] = $this->make_wporg_entry( '3.6.0' );

		$result = $this->loader->compat_verify_entry( $transient );

		$this->assertEquals( '3.7.0', $result->response[ $this->basename ]->new_version );
	}

	public function test_verify_does_not_downgrade_wporg_version() {
		$stash = $this->make_transient();
		$stash->response[ $this->basename ] = $this->make_wporg_entry( '3.5.0' );
		$this->loader->compat_capture_entry( $stash );

		$transient = $this->make_transient( '3.5.18' );
		$transient->response[ $this->basename ] = $this->make_wporg_entry( '3.6.0' );

		$result = $this->loader->compat_verify_entry( $transient );

		$this->assertEquals( '3.6.0', $result->response[ $this->basename ]->new_version );
	}

	// ─── API fallback ───

	public function test_verify_uses_api_fallback_when_no_stash() {
		$this->add_filter_with_safe_teardown( 'pre_http_request', function ( $result, $parsed_args, $url ) {
			if ( strpos( $url, 'api.wordpress.org' ) !== false ) {
				return [
					'body'     => json_encode( [
						'version'       => '3.6.0',
						'download_link' => 'https://downloads.wordpress.org/plugin/facebook-for-woocommerce.3.6.0.zip',
						'homepage'      => 'https://wordpress.org/plugins/facebook-for-woocommerce/',
						'tested'        => '6.9',
						'requires_php'  => '7.4',
						'requires'      => '5.6',
					] ),
					'response' => [ 'code' => 200, 'message' => 'OK' ],
				];
			}
			return $result;
		}, 10, 3 );

		$tampered = $this->make_transient( '3.5.18' );

		$result = $this->loader->compat_verify_entry( $tampered );

		$this->assertArrayHasKey( $this->basename, $result->response );
		$this->assertEquals( '3.6.0', $result->response[ $this->basename ]->new_version );
		$this->assertStringContainsString( 'downloads.wordpress.org', $result->response[ $this->basename ]->package );
	}

	public function test_verify_handles_api_failure_gracefully() {
		$this->add_filter_with_safe_teardown( 'pre_http_request', function () {
			return new \WP_Error( 'http_request_failed', 'Connection timed out' );
		}, 10, 3 );

		$tampered = $this->make_transient( '3.5.18' );
		$result   = $this->loader->compat_verify_entry( $tampered );

		$this->assertArrayNotHasKey( $this->basename, $result->response );
	}

	public function test_verify_handles_api_non_200_gracefully() {
		$this->add_filter_with_safe_teardown( 'pre_http_request', function () {
			return [
				'body'     => 'Not Found',
				'response' => [ 'code' => 404, 'message' => 'Not Found' ],
			];
		}, 10, 3 );

		$tampered = $this->make_transient( '3.5.18' );
		$result   = $this->loader->compat_verify_entry( $tampered );

		$this->assertArrayNotHasKey( $this->basename, $result->response );
	}

	// ─── full flow ───

	public function test_full_flow_stash_then_tamper_then_verify() {
		$transient = $this->make_transient( '3.5.18' );
		$transient->response[ $this->basename ] = $this->make_wporg_entry( '3.6.0' );

		$transient = $this->loader->compat_capture_entry( $transient );

		$transient->response[ $this->basename ] = $this->make_woocom_entry( '3.5.18' );

		$result = $this->loader->compat_verify_entry( $transient );

		$this->assertEquals( '3.6.0', $result->response[ $this->basename ]->new_version );
		$this->assertStringContainsString( 'downloads.wordpress.org', $result->response[ $this->basename ]->package );
		$this->assertArrayNotHasKey( $this->basename, $result->no_update );
	}

	public function test_full_flow_no_interference_leaves_transient_intact() {
		$transient = $this->make_transient( '3.5.18' );
		$transient->response[ $this->basename ] = $this->make_wporg_entry( '3.6.0' );

		$transient = $this->loader->compat_capture_entry( $transient );

		$result = $this->loader->compat_verify_entry( $transient );

		$this->assertEquals( '3.6.0', $result->response[ $this->basename ]->new_version );
		$this->assertStringContainsString( 'downloads.wordpress.org', $result->response[ $this->basename ]->package );
	}
}
