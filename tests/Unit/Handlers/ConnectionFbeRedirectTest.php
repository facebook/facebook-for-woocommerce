<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Handlers;

use WooCommerce\Facebook\Handlers\Connection;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Security regression tests for Connection::handle_fbe_redirect().
 *
 * Covers CVE-2026-49059 (CWE-601 open redirect): the FBE App Store login redirect
 * must only forward the browser to allowed Meta domains, and must reject crafted
 * URLs that previously bypassed the host check.
 */
class ConnectionFbeRedirectTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * @var Connection
	 */
	private $connection;

	/**
	 * Runs before each test is executed.
	 */
	public function setUp(): void {
		parent::setUp();

		// handle_fbe_redirect() requires the manage_woocommerce capability.
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$integration_mock = $this->getMockBuilder( \WC_Facebookcommerce_Integration::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_external_merchant_settings_id' ] )
			->getMock();
		$integration_mock->method( 'get_external_merchant_settings_id' )->willReturn( '' );

		$plugin_mock = $this->createMock( \WC_Facebookcommerce::class );
		$plugin_mock->method( 'get_integration' )->willReturn( $integration_mock );
		$this->add_filter_with_safe_teardown(
			'wc_facebook_instance',
			static function () use ( $plugin_mock ) {
				return $plugin_mock;
			}
		);

		$this->mock_set_option( Connection::OPTION_EXTERNAL_BUSINESS_ID, 'test-business' );
		$this->connection = new Connection( $plugin_mock );
	}

	/**
	 * Runs after each test is executed.
	 */
	public function tearDown(): void {
		unset( $_REQUEST['redirect_uri'], $_REQUEST['success'] );
		parent::tearDown();
	}

	/**
	 * Invokes the handler and returns the location it attempted to redirect to.
	 *
	 * The handler calls exit after redirecting, so we throw from the wp_redirect
	 * filter (which fires before the header/exit) to capture the destination.
	 *
	 * @param string $redirect_uri the raw (un-encoded) redirect_uri value
	 * @param bool   $success      whether to set the success flag
	 * @return string the captured redirect location
	 */
	private function capture_redirect( string $redirect_uri, bool $success = false ): string {
		$_REQUEST['redirect_uri'] = base64_encode( $redirect_uri );
		if ( $success ) {
			$_REQUEST['success'] = '1';
		} else {
			unset( $_REQUEST['success'] );
		}

		global $wp_filter;

		$captured                      = '';
		$allowed_redirect_hosts_filter = $wp_filter['allowed_redirect_hosts'] ?? null;
		$catcher                       = static function ( $location ) {
			throw new \Exception( (string) $location );
		};
		add_filter( 'wp_redirect', $catcher, 1 );
		try {
			$this->connection->handle_fbe_redirect();
		} catch ( \Exception $e ) {
			$captured = $e->getMessage();
		} finally {
			remove_filter( 'wp_redirect', $catcher, 1 );
			if ( null === $allowed_redirect_hosts_filter ) {
				unset( $wp_filter['allowed_redirect_hosts'] );
			} else {
				$wp_filter['allowed_redirect_hosts'] = $allowed_redirect_hosts_filter;
			}
		}

		return $captured;
	}

	/**
	 * Crafted URLs that must NOT be honored as Meta redirects.
	 *
	 * @return array<string, array{0:string}>
	 */
	public function malicious_redirect_provider(): array {
		return [
			'userinfo authority bypass'       => [ 'https://facebook.com@evil.com/' ],
			'domain suffix bypass'            => [ 'https://facebook.com.evil.com/' ],
			'subdomain label bypass'          => [ 'https://notfacebook.com/' ],
			'unrelated host'                  => [ 'https://evil.com/' ],
			'embedded allowed URL in path'     => [ 'https://evil.com/path/https://facebook.com/' ],
			'arbitrary facebook subdomain'     => [ 'https://developers.facebook.com/path' ],
			'arbitrary instagram subdomain'    => [ 'https://help.instagram.com/path' ],
			'arbitrary whatsapp subdomain'     => [ 'https://faq.whatsapp.com/path' ],
			'arbitrary commerce hub subdomain' => [ 'https://evil.commercepartnerhub.com/path' ],
			'malformed od subdomain'           => [ 'https://123456.od.facebook.com/path' ],
			'plaintext meta URL'                => [ 'http://facebook.com/path' ],
			'non-http scheme'                  => [ 'javascript:alert(1)//facebook.com' ],
			'empty value'                      => [ '' ],
		];
	}

	/**
	 * @dataProvider malicious_redirect_provider
	 *
	 * @param string $redirect_uri the crafted redirect target
	 */
	public function test_rejects_open_redirect_bypasses( string $redirect_uri ): void {
		$location = $this->capture_redirect( $redirect_uri );

		$this->assertSame(
			site_url(),
			$location,
			'A non-Meta redirect target must fall back to the site URL.'
		);
	}

	/**
	 * Legitimate Meta hosts (including the legacy allowed subdomains) must pass validation.
	 *
	 * Uses the error branch (no success flag) so the assertion does not depend on
	 * the connect-parameters side effects; that branch forwards to the trusted App
	 * Store login URL, proving the redirect_uri host cleared validation.
	 *
	 * @return array<string, array{0:string}>
	 */
	public function legitimate_redirect_provider(): array {
		return [
			'apex facebook'       => [ 'https://facebook.com/' ],
			'www subdomain'       => [ 'https://www.facebook.com/path' ],
			'mobile subdomain'    => [ 'https://m.facebook.com/path' ],
			'link shim subdomain' => [ 'https://l.facebook.com/path' ],
			'od subdomain'        => [ 'https://12345.od.facebook.com/path' ],
			'prefixed od host'    => [ 'https://www.12345.od.facebook.com/path' ],
			'instagram'           => [ 'https://instagram.com/path' ],
			'instagram mobile'    => [ 'https://m.instagram.com/path' ],
			'whatsapp'              => [ 'https://whatsapp.com/path' ],
			'whatsapp link shim'    => [ 'https://l.whatsapp.com/path' ],
			'commerce partner hub'  => [ 'https://commercepartnerhub.com/path' ],
			'www commerce hub'      => [ 'https://www.commercepartnerhub.com/path' ],
		];
	}

	/**
	 * @dataProvider legitimate_redirect_provider
	 *
	 * @param string $redirect_uri a legitimate Meta redirect target
	 */
	public function test_allows_legitimate_meta_hosts( string $redirect_uri ): void {
		$location = $this->capture_redirect( $redirect_uri );

		$this->assertNotSame(
			site_url(),
			$location,
			'A legitimate Meta host must not be rejected to the site URL.'
		);
		$this->assertStringContainsString(
			'api.woocommerce.com',
			$location,
			'A validated Meta host should proceed to the App Store login URL.'
		);
	}

	/**
	 * The success branch must only append extras to an allowed Meta host.
	 */
	public function test_success_branch_redirects_to_allowed_meta_host_with_extras(): void {
		$location = $this->capture_redirect( 'https://facebook.com/connect?foo=bar', true );

		$this->assertStringStartsWith(
			'https://facebook.com/connect?foo=bar&extras=',
			$location,
			'Extras should be appended to an allowed Meta redirect URI.'
		);
	}

	/**
	 * The success branch must not allow arbitrary Meta subdomains to receive extras.
	 */
	public function test_success_branch_rejects_arbitrary_meta_subdomain(): void {
		$location = $this->capture_redirect( 'https://developers.facebook.com/connect?foo=bar', true );

		$this->assertSame(
			site_url(),
			$location,
			'Arbitrary Meta subdomains must not receive connection extras.'
		);
	}
}
