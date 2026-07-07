<?php
/**
 * Unit tests for the WhatsApp onboarding-state HEAD backfill (pull path).
 */

namespace WooCommerce\Facebook\Tests\Handlers;

use WooCommerce\Facebook\Handlers\WhatsAppConnection;
use WooCommerce\Facebook\Handlers\WhatsAppExtension;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Covers WhatsAppExtension::maybe_backfill_onboarding_state() — the upgrade-hook
 * pull path that asks Meta's HEAD existence endpoint whether onboarding is
 * complete for installs that connected before the push signal existed.
 *
 * @package WooCommerce\Facebook\Tests\Unit\Handlers
 */
class WhatsAppExtensionBackfillTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/** @var int HTTP status the stubbed HEAD endpoint returns. */
	private $head_status = 200;

	/** @var array recorded [url, method] of intercepted requests. */
	private $requests = [];

	/** @var bool whether to short-circuit HTTP with a WP_Error. */
	private $return_wp_error = false;

	public function setUp(): void {
		parent::setUp();
		$this->requests        = [];
		$this->return_wp_error = false;

		$this->add_filter_with_safe_teardown(
			'pre_http_request',
			function ( $pre, $args, $url ) {
				$this->requests[] = [
					'url'    => $url,
					'method' => $args['method'] ?? 'GET',
				];
				if ( $this->return_wp_error ) {
					return new \WP_Error( 'http_request_failed', 'boom' );
				}
				return array(
					'response' => array( 'code' => $this->head_status ),
					'body'     => '',
				);
			},
			10,
			3
		);
	}

	private function connection(): WhatsAppConnection {
		return facebook_for_woocommerce()->get_whatsapp_connection_handler();
	}

	private function connect(): void {
		$this->mock_set_option( WhatsAppConnection::OPTION_WA_UTILITY_ACCESS_TOKEN, 'test-token' );
		$this->mock_set_option( WhatsAppConnection::OPTION_WA_INSTALLATION_ID, '123456789' );
	}

	public function test_backfill_sets_complete_on_head_200() {
		$this->connect();
		$this->head_status = 200;

		WhatsAppExtension::maybe_backfill_onboarding_state( facebook_for_woocommerce() );

		$this->assertSame( WhatsAppConnection::ONBOARDING_STATE_COMPLETE, $this->connection()->get_onboarding_state() );
		$this->assertCount( 1, $this->requests );
		$this->assertSame( 'HEAD', $this->requests[0]['method'] );
		$this->assertStringContainsString( 'message_integrations/installations/123456789/integration_config', $this->requests[0]['url'] );
	}

	public function test_backfill_sets_incomplete_on_head_404() {
		$this->connect();
		$this->head_status = 404;

		WhatsAppExtension::maybe_backfill_onboarding_state( facebook_for_woocommerce() );

		$this->assertSame( WhatsAppConnection::ONBOARDING_STATE_INCOMPLETE, $this->connection()->get_onboarding_state() );
	}

	public function test_backfill_leaves_unknown_on_error_fail_open() {
		$this->connect();
		$this->return_wp_error = true;

		WhatsAppExtension::maybe_backfill_onboarding_state( facebook_for_woocommerce() );

		$this->assertSame( WhatsAppConnection::ONBOARDING_STATE_UNKNOWN, $this->connection()->get_onboarding_state() );
	}

	public function test_backfill_skips_when_not_connected() {
		// No token → not connected.
		WhatsAppExtension::maybe_backfill_onboarding_state( facebook_for_woocommerce() );

		$this->assertSame( WhatsAppConnection::ONBOARDING_STATE_UNKNOWN, $this->connection()->get_onboarding_state() );
		$this->assertCount( 0, $this->requests, 'No HEAD request when not connected.' );
	}

	public function test_backfill_skips_when_state_already_known() {
		$this->connect();
		$this->mock_set_option( WhatsAppConnection::OPTION_WA_ONBOARDING_COMPLETE, WhatsAppConnection::ONBOARDING_STATE_COMPLETE );

		WhatsAppExtension::maybe_backfill_onboarding_state( facebook_for_woocommerce() );

		$this->assertCount( 0, $this->requests, 'No HEAD request when state already known (monotonic cache).' );
		$this->assertSame( WhatsAppConnection::ONBOARDING_STATE_COMPLETE, $this->connection()->get_onboarding_state() );
	}
}
