<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit;

use WooCommerce\Facebook\Handlers\Connection;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for Connection handler.
 */
class ConnectionTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	private $plugin_mock;
	private $integration_mock;

	public function setUp(): void {
		parent::setUp();
		$this->plugin_mock = $this->createMock( \WC_Facebookcommerce::class );
		$this->integration_mock = $this->createMock( \WC_Facebookcommerce_Integration::class );
		$this->plugin_mock->method( 'get_integration' )->willReturn( $this->integration_mock );
		$this->plugin_mock->method( 'log' )->willReturn( true );
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	public function test_get_access_token_returns_empty_string_by_default(): void {
		$connection = new Connection( $this->plugin_mock );
		$this->assertSame( '', $connection->get_access_token() );
	}

	public function test_get_access_token_returns_stored_value(): void {
		$this->mock_set_option( Connection::OPTION_ACCESS_TOKEN, 'test_token' );
		$connection = new Connection( $this->plugin_mock );
		$this->assertSame( 'test_token', $connection->get_access_token() );
	}

	public function test_update_access_token_stores_value(): void {
		$connection = new Connection( $this->plugin_mock );
		$connection->update_access_token( 'new_token' );
		$this->assertOptionUpdated( Connection::OPTION_ACCESS_TOKEN, 'new_token' );
	}

	public function test_is_connected_returns_false_when_no_token(): void {
		$connection = new Connection( $this->plugin_mock );
		$this->assertFalse( $connection->is_connected() );
	}

	public function test_is_connected_returns_true_when_token_exists(): void {
		$this->mock_set_option( Connection::OPTION_ACCESS_TOKEN, 'token' );
		$connection = new Connection( $this->plugin_mock );
		$this->assertTrue( $connection->is_connected() );
	}

	public function test_get_plugin_returns_plugin_instance(): void {
		$connection = new Connection( $this->plugin_mock );
		$this->assertSame( $this->plugin_mock, $connection->get_plugin() );
	}

	public function test_get_client_id_returns_default(): void {
		$connection = new Connection( $this->plugin_mock );
		$this->assertSame( Connection::CLIENT_ID, $connection->get_client_id() );
	}

	public function test_get_scopes_returns_default_scopes(): void {
		$connection = new Connection( $this->plugin_mock );
		$scopes = $connection->get_scopes();
		$this->assertIsArray( $scopes );
		$this->assertContains( 'manage_business_extension', $scopes );
		$this->assertContains( 'catalog_management', $scopes );
	}

	public function test_prepare_connect_server_message_for_user_display_returns_plain_string(): void {
		$connection = new Connection( $this->plugin_mock );
		$message = $connection->prepare_connect_server_message_for_user_display( 'Test message' );
		$this->assertSame( 'Test message', $message );
	}

	public function test_prepare_connect_server_message_for_user_display_formats_json(): void {
		$connection = new Connection( $this->plugin_mock );
		$json_message = json_encode( array( 'error' => array( 'message' => 'Error occurred' ) ) );
		$result = $connection->prepare_connect_server_message_for_user_display( $json_message );
		$this->assertStringContainsString( 'Error occurred', $result );
	}

	public function test_extras_permission_callback_returns_true_for_admin(): void {
		$connection = new Connection( $this->plugin_mock );
		wp_set_current_user( 1 );
		$this->assertTrue( $connection->extras_permission_callback() );
	}
}
