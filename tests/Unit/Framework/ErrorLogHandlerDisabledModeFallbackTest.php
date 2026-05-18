<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Framework;

use ReflectionClass;
use WooCommerce\Facebook\Framework\ErrorLogHandler;
use WooCommerce\Facebook\Framework\Logger;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Unit tests for disabled-mode reporting fallbacks in ErrorLogHandler.
 *
 * @since 3.6.4
 */
class ErrorLogHandlerDisabledModeFallbackTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	/** @var ReflectionClass */
	private $reflection;

	public function setUp(): void {
		parent::setUp();
		$this->reflection = new ReflectionClass( ErrorLogHandler::class );

		delete_option( Logger::SETTING_ENABLE_META_DIAGNOSIS );
		delete_option( 'wc_facebook_access_token' );
		delete_option( 'wc_facebook_merchant_access_token' );
		delete_option( 'wc_facebook_page_access_token' );
	}

	public function tearDown(): void {
		delete_option( Logger::SETTING_ENABLE_META_DIAGNOSIS );
		delete_option( 'wc_facebook_access_token' );
		delete_option( 'wc_facebook_merchant_access_token' );
		delete_option( 'wc_facebook_page_access_token' );
		parent::tearDown();
	}

	public function test_meta_diagnosis_gate_reads_plugin_option_setting(): void {
		$method = $this->reflection->getMethod( 'is_meta_diagnosis_enabled_for_reporting' );
		$method->setAccessible( true );

		update_option( Logger::SETTING_ENABLE_META_DIAGNOSIS, 'yes' );
		$this->assertTrue( (bool) $method->invoke( null ) );

		update_option( Logger::SETTING_ENABLE_META_DIAGNOSIS, 'no' );
		$this->assertFalse( (bool) $method->invoke( null ) );
	}

	public function test_get_reporting_access_token_prefers_primary_then_fallback_options(): void {
		$method = $this->reflection->getMethod( 'get_reporting_access_token' );
		$method->setAccessible( true );

		$this->assertSame( '', (string) $method->invoke( null ) );

		update_option( 'wc_facebook_page_access_token', 'page_token_value' );
		$this->assertSame( 'page_token_value', (string) $method->invoke( null ) );

		update_option( 'wc_facebook_merchant_access_token', 'merchant_token_value' );
		$this->assertSame( 'merchant_token_value', (string) $method->invoke( null ) );

		update_option( 'wc_facebook_access_token', 'primary_token_value' );
		$this->assertSame( 'primary_token_value', (string) $method->invoke( null ) );
	}

	public function test_send_log_to_meta_request_returns_null_without_available_access_token(): void {
		$method = $this->reflection->getMethod( 'send_log_to_meta_request' );
		$method->setAccessible( true );

		$payload = [
			'event'             => 'plugin_crash',
			'event_type'        => 'fatal_error',
			'exception_message' => 'test',
			'extra_data'        => [
				'fingerprint' => 'fp-test',
			],
		];

		$result = $method->invoke( null, $payload );
		$this->assertNull( $result );
	}
}
