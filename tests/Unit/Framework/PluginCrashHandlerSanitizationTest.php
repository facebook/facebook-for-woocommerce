<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Framework;

use ReflectionClass;
use WooCommerce\Facebook\Framework\PluginCrashHandler;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Focused tests for parser-first crash sanitization.
 *
 * @since 3.6.4
 */
class PluginCrashHandlerSanitizationTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/**
	 * @var PluginCrashHandler
	 */
	private $handler;

	/**
	 * @var ReflectionClass
	 */
	private $reflection;

	public function setUp(): void {
		parent::setUp();
		$this->handler    = new PluginCrashHandler();
		$this->reflection = new ReflectionClass( PluginCrashHandler::class );
	}

	public function test_sanitize_message_redacts_sensitive_headers_and_tokens_and_paths(): void {
		$sanitize_message = $this->reflection->getMethod( 'sanitize_message' );
		$sanitize_message->setAccessible( true );

		$input = "Authorization: Bearer abc.def.ghi\nCookie: foo=bar\nrequest_body: {\"token\":\"secret\"}\nPath: /var/www/html/wp-content/plugins/facebook-for-woocommerce/file.php";
		$out   = (string) $sanitize_message->invoke( $this->handler, $input );

		$this->assertStringContainsString( 'Authorization: [redacted]', $out );
		$this->assertStringContainsString( 'Cookie: [redacted]', $out );
		$this->assertStringContainsString( 'request_body: [redacted]', $out );
		$this->assertStringContainsString( '[path]', $out );
	}

	public function test_sanitize_message_handles_windows_paths_and_basic_pii(): void {
		$sanitize_message = $this->reflection->getMethod( 'sanitize_message' );
		$sanitize_message->setAccessible( true );

		$input = "File C:\\inetpub\\wwwroot\\wp-content\\plugins\\facebook-for-woocommerce\\foo.php for john@example.com and +1 (555) 123-4567";
		$out   = (string) $sanitize_message->invoke( $this->handler, $input );

		$this->assertStringContainsString( '[path]', $out );
		$this->assertStringContainsString( '[redacted_email]', $out );
		$this->assertStringContainsString( '[redacted_phone]', $out );
	}

	public function test_sanitize_message_redacts_phone_header_line_with_parser(): void {
		$sanitize_message = $this->reflection->getMethod( 'sanitize_message' );
		$sanitize_message->setAccessible( true );

		$input = "Phone: +1 (555) 123-4567";
		$out   = (string) $sanitize_message->invoke( $this->handler, $input );

		$this->assertStringContainsString( 'Phone: [redacted]', $out );
		$this->assertStringNotContainsString( '+1 (555) 123-4567', $out );
	}

	public function test_extract_plugin_stack_frames_skips_malformed_frames(): void {
		$extract = $this->reflection->getMethod( 'extract_plugin_stack_frames' );
		$extract->setAccessible( true );

		$trace = [
			'not-an-array',
			[ 'file' => 123, 'line' => 10 ],
			[ 'line' => 20 ],
			[ 'file' => '/tmp/random.php', 'line' => 30 ],
			[ 'file' => trailingslashit( wp_normalize_path( WC_FACEBOOK_PLUGIN_PATH ) ) . 'includes/Framework/PluginCrashHandler.php', 'line' => 40 ],
		];

		$frames = $extract->invoke( $this->handler, $trace );

		$this->assertIsArray( $frames );
		$this->assertCount( 1, $frames );
		$this->assertStringStartsWith( 'plugin:', $frames[0]['file'] );
		$this->assertSame( 40, $frames[0]['line'] );
	}

	public function test_normalize_crash_report_payload_keeps_expected_shape(): void {
		$normalize = $this->reflection->getMethod( 'normalize_crash_report_payload' );
		$normalize->setAccessible( true );

		$error = [
			'type'    => E_ERROR,
			'message' => 'Fatal token=abcd1234abcd1234abcd1234abcd1234',
			'file'    => trailingslashit( wp_normalize_path( WC_FACEBOOK_PLUGIN_PATH ) ) . 'includes/Framework/PluginCrashHandler.php',
			'line'    => 88,
			'trace'   => [],
			'source'  => 'fatal_error',
		];

		$payload = $normalize->invoke( $this->handler, $error );

		$this->assertIsArray( $payload );
		$this->assertArrayHasKey( 'event', $payload );
		$this->assertArrayHasKey( 'event_type', $payload );
		$this->assertArrayHasKey( 'exception_message', $payload );
		$this->assertArrayHasKey( 'extra_data', $payload );
		$this->assertIsArray( $payload['extra_data'] );
		$this->assertArrayHasKey( 'file', $payload['extra_data'] );
		$this->assertArrayHasKey( 'line', $payload['extra_data'] );
		$this->assertArrayHasKey( 'plugin_stack', $payload['extra_data'] );
	}
}
