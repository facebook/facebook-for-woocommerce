<?php
/**
 * Unit tests for the WhatsApp customer_events onboarding gate.
 */

namespace WooCommerce\Facebook\Tests\Handlers;

use WooCommerce\Facebook\Handlers\WhatsAppConnection;
use WooCommerce\Facebook\Handlers\WhatsAppExtension;
use WooCommerce\Facebook\RolloutSwitches;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Covers WhatsAppExtension::process_whatsapp_utility_message_event() — specifically
 * the gate that suppresses customer_events POSTs for stores Meta has confirmed are
 * not onboarded, behind the SWITCH_WA_CUSTOMER_EVENTS_GATING_ENABLED rollout switch.
 *
 * @package WooCommerce\Facebook\Tests\Unit\Handlers
 */
class WhatsAppExtensionTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * URLs an outbound HTTP request was attempted against during the test.
	 *
	 * @var string[]
	 */
	private $http_requests = [];

	public function setUp(): void {
		parent::setUp();
		$this->http_requests = [];

		// Intercept all outbound HTTP: record the URL and short-circuit with a fake 200
		// so we can assert whether a customer_events POST was attempted.
		$this->add_filter_with_safe_teardown(
			'pre_http_request',
			function ( $pre, $args, $url ) {
				$this->http_requests[] = $url;
				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'body'     => '{}',
				);
			},
			10,
			3
		);
	}

	/**
	 * Sets the rollout switch state by writing the option RolloutSwitches reads.
	 * The switch is active (in ACTIVE_SWITCHES), so the stored value decides on/off.
	 */
	private function set_gating_switch( bool $enabled ): void {
		$this->mock_set_option(
			'wc_facebook_for_woocommerce_rollout_switches',
			array( RolloutSwitches::SWITCH_WA_CUSTOMER_EVENTS_GATING_ENABLED => $enabled ? 'yes' : 'no' )
		);
	}

	/**
	 * Builds a plugin mock whose connection handler reports the given connection
	 * and onboarding state.
	 */
	private function build_plugin( bool $is_connected, string $onboarding_state ): \WC_Facebookcommerce {
		$connection = $this->getMockBuilder( WhatsAppConnection::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'is_connected', 'get_onboarding_state', 'get_wa_installation_id', 'get_access_token' ) )
			->getMock();
		$connection->method( 'is_connected' )->willReturn( $is_connected );
		$connection->method( 'get_onboarding_state' )->willReturn( $onboarding_state );
		$connection->method( 'get_wa_installation_id' )->willReturn( '123456789' );
		$connection->method( 'get_access_token' )->willReturn( 'test-token' );

		$plugin = $this->getMockBuilder( \WC_Facebookcommerce::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_whatsapp_connection_handler' ) )
			->getMock();
		$plugin->method( 'get_whatsapp_connection_handler' )->willReturn( $connection );

		return $plugin;
	}

	private function fire_order_placed_event( \WC_Facebookcommerce $plugin ): void {
		WhatsAppExtension::process_whatsapp_utility_message_event(
			$plugin,
			'ORDER_PLACED',
			'123',
			'https://example.com/order/123',
			'15551234567',
			'Jane',
			0,
			'USD',
			'US'
		);
	}

	/**
	 * @return string[] recorded request URLs that targeted the customer_events endpoint
	 */
	private function customer_events_requests(): array {
		return array_values(
			array_filter(
				$this->http_requests,
				static function ( $url ) {
					return false !== strpos( (string) $url, 'customer_events' );
				}
			)
		);
	}

	/** Switch OFF + INCOMPLETE: gate is inert, the event still POSTs. */
	public function test_gate_disabled_sends_even_when_onboarding_incomplete() {
		$this->set_gating_switch( false );
		$this->fire_order_placed_event(
			$this->build_plugin( true, WhatsAppConnection::ONBOARDING_STATE_INCOMPLETE )
		);
		$this->assertCount( 1, $this->customer_events_requests(), 'Switch off: customer_events should still be sent.' );
	}

	/** Switch ON + INCOMPLETE: the event is suppressed. */
	public function test_gate_enabled_suppresses_when_onboarding_incomplete() {
		$this->set_gating_switch( true );
		$this->fire_order_placed_event(
			$this->build_plugin( true, WhatsAppConnection::ONBOARDING_STATE_INCOMPLETE )
		);
		$this->assertCount( 0, $this->customer_events_requests(), 'Switch on + INCOMPLETE: customer_events should be suppressed.' );
	}

	/** Switch ON + COMPLETE: the event POSTs. */
	public function test_gate_enabled_sends_when_onboarding_complete() {
		$this->set_gating_switch( true );
		$this->fire_order_placed_event(
			$this->build_plugin( true, WhatsAppConnection::ONBOARDING_STATE_COMPLETE )
		);
		$this->assertCount( 1, $this->customer_events_requests(), 'Switch on + COMPLETE: customer_events should be sent.' );
	}

	/** Switch ON + UNKNOWN: fail open, the event POSTs. */
	public function test_gate_enabled_fails_open_when_onboarding_unknown() {
		$this->set_gating_switch( true );
		$this->fire_order_placed_event(
			$this->build_plugin( true, WhatsAppConnection::ONBOARDING_STATE_UNKNOWN )
		);
		$this->assertCount( 1, $this->customer_events_requests(), 'Switch on + UNKNOWN: should fail open and send.' );
	}

	/** Not connected: nothing is sent (pre-existing behavior, regression guard). */
	public function test_not_connected_sends_nothing() {
		$this->set_gating_switch( true );
		$this->fire_order_placed_event(
			$this->build_plugin( false, WhatsAppConnection::ONBOARDING_STATE_COMPLETE )
		);
		$this->assertCount( 0, $this->customer_events_requests(), 'Not connected: no customer_events request.' );
	}
}
