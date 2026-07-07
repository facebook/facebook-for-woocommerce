<?php
/**
 * Unit tests for the WhatsApp Settings REST handler onboarding-complete push endpoint.
 */

namespace WooCommerce\Facebook\Tests\API\Plugin\WhatsAppSettings;

use WooCommerce\Facebook\API\Plugin\WhatsAppSettings\Handler;
use WooCommerce\Facebook\API\Plugin\WhatsAppSettings\OnboardingComplete\Request as OnboardingCompleteRequest;
use WooCommerce\Facebook\Handlers\WhatsAppConnection;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Covers the WA_CONNECT push endpoint that marks onboarding complete.
 *
 * @package WooCommerce\Facebook\Tests\Unit\API\Plugin\WhatsAppSettings
 */
class HandlerTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering {

	public function test_handle_onboarding_complete_marks_onboarding_complete() {
		// Precondition: not yet onboarded.
		$connection = facebook_for_woocommerce()->get_whatsapp_connection_handler();
		$this->assertFalse( $connection->is_onboarding_complete() );

		$response = ( new Handler() )->handle_onboarding_complete( new \WP_REST_Request( 'POST' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $connection->is_onboarding_complete() );
	}

	public function test_onboarding_complete_request_js_definition() {
		$request = new OnboardingCompleteRequest( new \WP_REST_Request( 'POST' ) );

		$this->assertSame( 'whatsapp_settings/onboarding_complete', $request->get_endpoint() );
		$this->assertSame( 'POST', $request->get_method() );
		$this->assertSame( 'notifyWhatsAppOnboardingComplete', $request->get_js_function_name() );
		$this->assertTrue( OnboardingCompleteRequest::is_js_exposable() );
	}
}
