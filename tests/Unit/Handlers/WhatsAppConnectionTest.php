<?php
/**
 * Unit tests for WhatsAppConnection onboarding-state reader.
 */

namespace WooCommerce\Facebook\Tests\Handlers;

use WooCommerce\Facebook\Handlers\WhatsAppConnection;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Covers WhatsAppConnection::get_onboarding_state() — the tri-state reader the
 * customer_events gate relies on.
 *
 * @package WooCommerce\Facebook\Tests\Unit\Handlers
 */
class WhatsAppConnectionTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	private function build_connection(): WhatsAppConnection {
		return new WhatsAppConnection( $this->createMock( \WC_Facebookcommerce::class ) );
	}

	public function test_onboarding_state_is_unknown_when_option_unset() {
		$this->assertSame(
			WhatsAppConnection::ONBOARDING_STATE_UNKNOWN,
			$this->build_connection()->get_onboarding_state()
		);
	}

	public function test_onboarding_state_complete_when_option_yes() {
		$this->mock_set_option(
			WhatsAppConnection::OPTION_WA_ONBOARDING_COMPLETE,
			WhatsAppConnection::ONBOARDING_STATE_COMPLETE
		);
		$this->assertSame(
			WhatsAppConnection::ONBOARDING_STATE_COMPLETE,
			$this->build_connection()->get_onboarding_state()
		);
	}

	public function test_onboarding_state_incomplete_when_option_no() {
		$this->mock_set_option(
			WhatsAppConnection::OPTION_WA_ONBOARDING_COMPLETE,
			WhatsAppConnection::ONBOARDING_STATE_INCOMPLETE
		);
		$this->assertSame(
			WhatsAppConnection::ONBOARDING_STATE_INCOMPLETE,
			$this->build_connection()->get_onboarding_state()
		);
	}

	/**
	 * Any value that is not exactly 'yes' or 'no' must normalize to UNKNOWN so the
	 * gate fails open rather than acting on a corrupt/legacy value.
	 *
	 * @dataProvider provider_garbage_values
	 *
	 * @param mixed $stored_value the raw stored option value
	 */
	public function test_onboarding_state_is_unknown_for_unexpected_values( $stored_value ) {
		$this->mock_set_option( WhatsAppConnection::OPTION_WA_ONBOARDING_COMPLETE, $stored_value );
		$this->assertSame(
			WhatsAppConnection::ONBOARDING_STATE_UNKNOWN,
			$this->build_connection()->get_onboarding_state()
		);
	}

	public function provider_garbage_values(): array {
		return array(
			'empty string'  => array( '' ),
			'numeric one'   => array( '1' ),
			'arbitrary str' => array( 'maybe' ),
			'boolean true'  => array( true ),
		);
	}
}
