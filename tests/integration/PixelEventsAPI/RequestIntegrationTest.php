<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Integration\PixelEventsAPI;

use WooCommerce\Facebook\Tests\Integration\IntegrationTestCase;
use WooCommerce\Facebook\API\Pixel\Events\Request;
use WooCommerce\Facebook\API;
use WooCommerce\Facebook\Events\Event;

/**
 * Integration tests for Pixel Events API Request.
 *
 * RUNNING THE TESTS:
 * ==================
 * Basic testing (test credentials - auth will fail but validates HTTP layer):
 *   ./run-tests-php82.sh --testsuite=integration --filter=RequestIntegrationTest
 *
 * Full integration testing (with valid Facebook credentials):
 *   export FB_TEST_ACCESS_TOKEN="your_real_token"
 *   export FB_TEST_PIXEL_ID="your_real_pixel_id"
 *   ./run-tests-php82.sh --testsuite=integration --filter=RequestIntegrationTest
 *
 *  * Steps to get your TEST_ACCESS_TOKEN
 *  1. Go to events manager
 *  2. Select "Datasets" tab from left panel
 *  3. Select your business
 *  4. Go to the Test events tab -> Graph API Explorer
 *  5. Copy Access Token
 */
class RequestIntegrationTest extends IntegrationTestCase {

	/**
	 * @var API
	 */
	private $api;

	/**
	 * @var bool Whether we have valid credentials for full integration testing
	 */
	private $has_valid_credentials = false;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();

		// Initialize API with test access token
		$this->api = new API( $this->get_test_access_token() );

		// Check if we have valid credentials
		$this->has_valid_credentials = ! empty( getenv( 'FB_TEST_ACCESS_TOKEN' ) )
			&& ! empty( getenv( 'FB_TEST_PIXEL_ID' ) );

		if ( $this->has_valid_credentials ) {
			error_log( 'Running tests with VALID Facebook credentials - full integration testing enabled' );
		} else {
			error_log( 'Running tests with TEST credentials - HTTP layer testing only (auth will fail)' );
		}
	}

	/**
	 * Get test access token from environment or use dummy value
	 *
	 * @return string
	 */
	private function get_test_access_token(): string {
		// Try to get from environment variable first
		$token = getenv( 'FB_TEST_ACCESS_TOKEN' );
		if ( ! empty( $token ) ) {
			return $token;
		}

		// Use a test token that will allow request formatting but may fail authentication
		return 'test_access_token_' . uniqid();
	}

	/**
	 * Get test pixel ID from environment or use default
	 *
	 * @return string
	 */
	private function get_test_pixel_id(): string {
		$pixel_id = getenv( 'FB_TEST_PIXEL_ID' );
		if ( ! empty( $pixel_id ) ) {
			return $pixel_id;
		}

		return 'test_pixel_123456789';
	}

	/**
	 * Test sending HTTP request with custom fbc and fbp values in user_data
	 *
	 * This test demonstrates how to create a Request with custom fbc/fbp values
	 * by using the click_id and browser_id parameters, which get transformed to fbc/fbp.
	 *
	 * Note: This test makes a REAL HTTP request to Facebook API.
	 */
	public function test_Given_Single_Purcahse_Event_When_SendingEvent_Then_RequestContainsValues() {
		$pixel_id = $this->get_test_pixel_id();

		// Custom fbc and fbp values we want to send
		$custom_fbc = 'fb.1.1554763741205.CustomFBCValue123';
		$custom_fbp = 'fb.1.1554763741205.CustomFBPValue456';

		// Create event with custom click_id and browser_id (these will become fbc/fbp)
		$event = new Event( array(
			'event_name'  => 'Purchase',
			'event_time'  => time(),
			'custom_data' => array(
				'value'        => '99.99',
				'currency'     => 'USD',
				'content_ids'  => array( 'product_123' ),
				'content_type' => 'product',
			),
			'user_data'   => array(
				'em'                   => 'test@example.com',
				'client_ip_address'    => '127.0.0.1',
				'client_user_agent'    => 'Mozilla/5.0 Test',
				'click_id'             => $custom_fbc,  // Will be transformed to fbc
				'browser_id'           => $custom_fbp,  // Will be transformed to fbp
			),
		) );

		// Create the Request object directly and verify the data transformation
		$request = new Request( $pixel_id, array( $event ) );
		$request_data = $request->get_data();

		// Verify our custom fbc/fbp values are in the request data
		$this->assertArrayHasKey( 'data', $request_data );
		$this->assertArrayHasKey( 'user_data', $request_data['data'][0] );

		$user_data = $request_data['data'][0]['user_data'];
		$this->assertArrayHasKey( 'fbc', $user_data );
		$this->assertArrayHasKey( 'fbp', $user_data );
		$this->assertEquals( $custom_fbc, $user_data['fbc'], 'Custom fbc value should be set' );
		$this->assertEquals( $custom_fbp, $user_data['fbp'], 'Custom fbp value should be set' );

		// Verify click_id and browser_id are removed (as per get_data transformation)
		$this->assertArrayNotHasKey( 'click_id', $user_data, 'click_id should be removed after transformation' );
		$this->assertArrayNotHasKey( 'browser_id', $user_data, 'browser_id should be removed after transformation' );

		error_log( 'Request data with custom fbc/fbp: ' . print_r( $request_data, true ) );

		// Make the actual HTTP request
		try {
			$response = $this->api->send_pixel_events( $pixel_id, array( $event ) );

			// If we get here, the request was sent successfully
			$this->assertInstanceOf( 'WooCommerce\Facebook\API\Response', $response );
			// Log response for debugging
			error_log( 'Facebook Pixel API Response (custom fbc/fbp): ' . print_r( $response->response_data, true ) );

			// If we have a valid token, check for success
			if ( $this->has_valid_credentials ) {
				$response_data = $response->response_data;
				$this->assertIsArray( $response_data );
			}
		} catch ( \Exception $e ) {
			$this->fail( 'Unexpected exception: ' . $e->getMessage() );
		}
	}

	/**
	 * Test sending multiple events in a single HTTP request (batch)
	 */
	public function test_Given_Multiple_Events_When_SendingBatch_Then_RequestSucceeds() {
		$pixel_id = $this->get_test_pixel_id();

		// Create multiple events with custom fbc/fbp values
		$events = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			// Generate unique fbc/fbp for each event
			$timestamp = time() + $i; // Unique timestamp for each event
			$custom_fbc = "fb.1.{$timestamp}.CustomBatchFBC{$i}";
			$custom_fbp = "fb.1.{$timestamp}.CustomBatchFBP{$i}";

			$events[] = new Event( array(
				'event_name'  => 'ViewContent',
				'event_time'  => time(),
				'custom_data' => array(
					'content_ids'  => array( "product_{$i}" ),
					'content_type' => 'product',
					'value'        => (string) ( $i * 10 ),
					'currency'     => 'USD',
				),
				'user_data'   => array(
					'em'                   => "test{$i}@example.com",
					'client_ip_address'    => '127.0.0.1',
					'client_user_agent'    => 'Mozilla/5.0 Test',
					'click_id'             => $custom_fbc,
					'browser_id'           => $custom_fbp,
				),
			) );
		}

		// Make the actual HTTP request with multiple events
		try {
			$response = $this->api->send_pixel_events( $pixel_id, $events );

			// If we get here, the batch request was sent successfully
			$this->assertInstanceOf( 'WooCommerce\Facebook\API\Response', $response );

			// Log response for debugging
			error_log( 'Facebook Pixel API Batch Response: ' . print_r( $response->response_data, true ) );

		} catch ( \Exception $e ) {
			$this->fail( 'Unexpected exception in batch request: ' . $e->getMessage() );
		}
	}

	/**
	 * Clean up after each test
	 */
	public function tearDown(): void {
		// Clean up any remaining cookies/session/request variables
		unset( $_COOKIE['_fbc'] );
		unset( $_COOKIE['_fbp'] );
		unset( $_SESSION['_fbc'] );
		unset( $_SESSION['_fbp'] );
		unset( $_REQUEST['fbclid'] );
		unset( $_SERVER['HTTP_USER_AGENT'] );

		parent::tearDown();
	}
}
