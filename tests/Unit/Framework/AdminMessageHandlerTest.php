<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Framework;

use WooCommerce\Facebook\Framework\AdminMessageHandler;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for AdminMessageHandler class.
 *
 * @since 3.5.2
 */
class AdminMessageHandlerTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * Test that the class can be instantiated.
	 */
	public function test_class_exists_and_can_be_instantiated() {
		$this->assertTrue( class_exists( AdminMessageHandler::class ) );
		
		$handler = new AdminMessageHandler();
		$this->assertInstanceOf( AdminMessageHandler::class, $handler );
	}

	/**
	 * Test constructor with custom message ID.
	 */
	public function test_constructor_with_custom_message_id() {
		$custom_id = 'test_message_id';
		$handler = new AdminMessageHandler( $custom_id );
		
		// The message ID is private, so we can't directly test it
		// But we can verify the object was created successfully
		$this->assertInstanceOf( AdminMessageHandler::class, $handler );
	}

	/**
	 * Test adding a message.
	 */
	public function test_add_message() {
		$handler = new AdminMessageHandler();
		$message = 'Test message';
		
		$handler->add_message( $message );
		
		$this->assertEquals( 1, $handler->message_count() );
		$this->assertEquals( $message, $handler->get_message( 0 ) );
	}

	/**
	 * Test adding multiple messages.
	 */
	public function test_add_multiple_messages() {
		$handler = new AdminMessageHandler();
		$messages = [ 'First message', 'Second message', 'Third message' ];
		
		foreach ( $messages as $message ) {
			$handler->add_message( $message );
		}
		
		$this->assertEquals( 3, $handler->message_count() );
		$this->assertEquals( $messages, $handler->get_messages() );
	}

	/**
	 * Test adding an error.
	 */
	public function test_add_error() {
		$handler = new AdminMessageHandler();
		$error = 'Test error';
		
		$handler->add_error( $error );
		
		$this->assertEquals( 1, $handler->error_count() );
		$this->assertEquals( $error, $handler->get_error( 0 ) );
	}

	/**
	 * Test adding multiple errors.
	 */
	public function test_add_multiple_errors() {
		$handler = new AdminMessageHandler();
		$errors = [ 'First error', 'Second error', 'Third error' ];
		
		foreach ( $errors as $error ) {
			$handler->add_error( $error );
		}
		
		$this->assertEquals( 3, $handler->error_count() );
		$this->assertEquals( $errors, $handler->get_errors() );
	}

	/**
	 * Test adding a warning.
	 */
	public function test_add_warning() {
		$handler = new AdminMessageHandler();
		$warning = 'Test warning';
		
		$handler->add_warning( $warning );
		
		$this->assertEquals( 1, $handler->warning_count() );
		$this->assertEquals( $warning, $handler->get_warning( 0 ) );
	}

	/**
	 * Test adding multiple warnings.
	 */
	public function test_add_multiple_warnings() {
		$handler = new AdminMessageHandler();
		$warnings = [ 'First warning', 'Second warning', 'Third warning' ];
		
		foreach ( $warnings as $warning ) {
			$handler->add_warning( $warning );
		}
		
		$this->assertEquals( 3, $handler->warning_count() );
		$this->assertEquals( $warnings, $handler->get_warnings() );
	}

	/**
	 * Test adding an info message.
	 */
	public function test_add_info() {
		$handler = new AdminMessageHandler();
		$info = 'Test info';
		
		$handler->add_info( $info );
		
		$this->assertEquals( 1, $handler->info_count() );
		$this->assertEquals( $info, $handler->get_info( 0 ) );
	}

	/**
	 * Test adding multiple info messages.
	 */
	public function test_add_multiple_infos() {
		$handler = new AdminMessageHandler();
		$infos = [ 'First info', 'Second info', 'Third info' ];
		
		foreach ( $infos as $info ) {
			$handler->add_info( $info );
		}
		
		$this->assertEquals( 3, $handler->info_count() );
		$this->assertEquals( $infos, $handler->get_infos() );
	}

	/**
	 * Test getting a non-existent message.
	 */
	public function test_get_non_existent_message() {
		$handler = new AdminMessageHandler();
		
		$this->assertEquals( '', $handler->get_message( 999 ) );
		$this->assertEquals( '', $handler->get_error( 999 ) );
		$this->assertEquals( '', $handler->get_warning( 999 ) );
		$this->assertEquals( '', $handler->get_info( 999 ) );
	}

	/**
	 * Test mixed message types.
	 */
	public function test_mixed_message_types() {
		$handler = new AdminMessageHandler();
		
		$handler->add_message( 'Success message' );
		$handler->add_error( 'Error message' );
		$handler->add_warning( 'Warning message' );
		$handler->add_info( 'Info message' );
		
		$this->assertEquals( 1, $handler->message_count() );
		$this->assertEquals( 1, $handler->error_count() );
		$this->assertEquals( 1, $handler->warning_count() );
		$this->assertEquals( 1, $handler->info_count() );
	}

	/**
	 * Test set_messages with no messages.
	 */
	public function test_set_messages_with_no_messages() {
		$handler = new AdminMessageHandler( 'test_id' );
		
		$result = $handler->set_messages();
		
		$this->assertFalse( $result );
	}

	/**
	 * Test set_messages with messages.
	 */
	public function test_set_messages_with_messages() {
		$handler = new AdminMessageHandler( 'test_id' );
		
		$handler->add_message( 'Test message' );
		$handler->add_error( 'Test error' );
		
		$result = $handler->set_messages();
		
		$this->assertTrue( $result );
		
		// Verify transient was set
		$transient_key = AdminMessageHandler::MESSAGE_TRANSIENT_PREFIX . wp_create_nonce( 'test_id' );
		$transient_data = get_transient( $transient_key );
		
		$this->assertIsArray( $transient_data );
		$this->assertArrayHasKey( 'messages', $transient_data );
		$this->assertArrayHasKey( 'errors', $transient_data );
		$this->assertArrayHasKey( 'warnings', $transient_data );
		$this->assertArrayHasKey( 'infos', $transient_data );
	}

	/**
	 * Test clear_messages.
	 */
	public function test_clear_messages() {
		$message_id = 'test_clear_id';
		$transient_key = AdminMessageHandler::MESSAGE_TRANSIENT_PREFIX . $message_id;
		
		// Set a transient
		set_transient( $transient_key, [ 'test' => 'data' ], 3600 );
		
		// Create handler and clear messages
		$handler = new AdminMessageHandler();
		$handler->clear_messages( $message_id );
		
		// Verify transient was deleted
		$this->assertFalse( get_transient( $transient_key ) );
	}

	/**
	 * Test redirect filter.
	 */
	public function test_redirect_filter() {
		$handler = new AdminMessageHandler( 'test_redirect' );
		$original_url = 'https://example.com/admin';
		
		// Add some messages
		$handler->add_message( 'Test message' );
		
		// Call redirect method
		$filtered_url = $handler->redirect( $original_url, 302 );
		
		// Should add message ID parameter
		$this->assertStringContainsString( AdminMessageHandler::MESSAGE_ID_GET_NAME, $filtered_url );
		$this->assertStringContainsString( $original_url, $filtered_url );
	}

	/**
	 * Test redirect filter without messages.
	 */
	public function test_redirect_filter_without_messages() {
		$handler = new AdminMessageHandler( 'test_redirect_no_msg' );
		$original_url = 'https://example.com/admin';
		
		// Call redirect method without adding messages
		$filtered_url = $handler->redirect( $original_url, 302 );
		
		// Should return original URL unchanged
		$this->assertEquals( $original_url, $filtered_url );
	}

	/**
	 * Test show_messages output.
	 */
	public function test_show_messages_output() {
		$handler = new AdminMessageHandler();
		
		// Add various message types
		$handler->add_message( 'Success message' );
		$handler->add_error( 'Error message' );
		$handler->add_warning( 'Warning message' );
		$handler->add_info( 'Info message' );
		
		// Capture output
		ob_start();
		$handler->show_messages();
		$output = ob_get_clean();
		
		// Verify output contains expected elements
		$this->assertStringContainsString( 'notice-success', $output );
		$this->assertStringContainsString( 'notice-error', $output );
		$this->assertStringContainsString( 'notice-warning', $output );
		$this->assertStringContainsString( 'notice-info', $output );
		
		$this->assertStringContainsString( 'Success message', $output );
		$this->assertStringContainsString( 'Error message', $output );
		$this->assertStringContainsString( 'Warning message', $output );
		$this->assertStringContainsString( 'Info message', $output );
	}

	/**
	 * Test show_messages with no messages.
	 */
	public function test_show_messages_with_no_messages() {
		$handler = new AdminMessageHandler();
		
		// Capture output
		ob_start();
		$handler->show_messages();
		$output = ob_get_clean();
		
		// Should output nothing
		$this->assertEquals( '', $output );
	}

	/**
	 * Test show_messages with custom capabilities.
	 */
	public function test_show_messages_with_custom_capabilities() {
		$handler = new AdminMessageHandler();
		$handler->add_message( 'Test message' );
		
		// Mock current user to not have the required capability
		$user_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );
		
		// Capture output with default capabilities (manage_woocommerce)
		ob_start();
		$handler->show_messages();
		$output = ob_get_clean();
		
		// Should not show messages to users without capability
		$this->assertEquals( '', $output );
		
		// Now test with a capability the user has
		ob_start();
		$handler->show_messages( [ 'capabilities' => [ 'read' ] ] );
		$output = ob_get_clean();
		
		// Should show messages
		$this->assertStringContainsString( 'Test message', $output );
	}

	/**
	 * Test message constants.
	 */
	public function test_message_constants() {
		$this->assertEquals( '_wp_admin_message_', AdminMessageHandler::MESSAGE_TRANSIENT_PREFIX );
		$this->assertEquals( 'wpamhid', AdminMessageHandler::MESSAGE_ID_GET_NAME );
	}

	/**
	 * Test special characters in messages.
	 */
	public function test_special_characters_in_messages() {
		$handler = new AdminMessageHandler();
		
		$special_messages = [
			'Message with <strong>HTML</strong>',
			'Message with "quotes"',
			'Message with \'apostrophes\'',
			'Message with & ampersand',
			'Message with < and > symbols'
		];
		
		foreach ( $special_messages as $message ) {
			$handler->add_message( $message );
		}
		
		$messages = $handler->get_messages();
		$this->assertEquals( $special_messages, $messages );
		
		// Test output escaping
		ob_start();
		$handler->show_messages();
		$output = ob_get_clean();
		
		// Verify messages are present (they should be escaped by wp_kses_post)
		foreach ( $special_messages as $message ) {
			$this->assertStringContainsString( $message, $output );
		}
	}

	/**
	 * Test empty string messages.
	 */
	public function test_empty_string_messages() {
		$handler = new AdminMessageHandler();
		
		$handler->add_message( '' );
		$handler->add_error( '' );
		$handler->add_warning( '' );
		$handler->add_info( '' );
		
		$this->assertEquals( 1, $handler->message_count() );
		$this->assertEquals( 1, $handler->error_count() );
		$this->assertEquals( 1, $handler->warning_count() );
		$this->assertEquals( 1, $handler->info_count() );
		
		$this->assertEquals( '', $handler->get_message( 0 ) );
		$this->assertEquals( '', $handler->get_error( 0 ) );
		$this->assertEquals( '', $handler->get_warning( 0 ) );
		$this->assertEquals( '', $handler->get_info( 0 ) );
	}

	/**
	 * Test load_messages with valid message ID.
	 */
	public function test_load_messages_with_valid_id() {
		// Set up a transient with messages
		$message_id = wp_create_nonce( 'test_load' );
		$transient_key = AdminMessageHandler::MESSAGE_TRANSIENT_PREFIX . $message_id;
		$test_data = [
			'messages' => [ 'Loaded message' ],
			'errors' => [ 'Loaded error' ],
			'warnings' => [ 'Loaded warning' ],
			'infos' => [ 'Loaded info' ]
		];
		set_transient( $transient_key, $test_data, 3600 );
		
		// Simulate GET parameter
		$_GET[ AdminMessageHandler::MESSAGE_ID_GET_NAME ] = $message_id;
		
		// Create handler which should load messages in constructor
		$handler = new AdminMessageHandler( 'test_load' );
		
		// Verify messages were loaded
		$this->assertEquals( 1, $handler->message_count() );
		$this->assertEquals( 1, $handler->error_count() );
		$this->assertEquals( 1, $handler->warning_count() );
		$this->assertEquals( 1, $handler->info_count() );
		
		$this->assertEquals( 'Loaded message', $handler->get_message( 0 ) );
		$this->assertEquals( 'Loaded error', $handler->get_error( 0 ) );
		$this->assertEquals( 'Loaded warning', $handler->get_warning( 0 ) );
		$this->assertEquals( 'Loaded info', $handler->get_info( 0 ) );
		
		// Clean up
		unset( $_GET[ AdminMessageHandler::MESSAGE_ID_GET_NAME ] );
	}
} 