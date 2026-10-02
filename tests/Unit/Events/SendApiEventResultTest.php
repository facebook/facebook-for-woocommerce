<?php
/**
 * Copyright (c) Meta, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Events;

use WC_Facebookcommerce_EventsTracker;
use WooCommerce\Facebook\Events\AAMSettings;
use WooCommerce\Facebook\Events\Event;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;

/**
 * Tests that send_api_event() reports whether an event was delivered.
 *
 * Offline events are only marked as reported once delivered, so this has to be
 * right. Graph API errors other than rate limits do not throw — they come back as
 * an error response — so a rejected event must still be reported as not delivered.
 *
 * Requests are answered through pre_http_request, so they run through the real API
 * client and response parsing without leaving the test.
 *
 * @covers WC_Facebookcommerce_EventsTracker::send_api_event
 */
class SendApiEventResultTest extends AbstractWPUnitTestWithSafeFiltering {

	/** @var WC_Facebookcommerce_EventsTracker */
	private $tracker;

	/** @var string|null */
	private $original_user_agent;

	public function setUp(): void {
		parent::setUp();

		$this->original_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

		delete_transient( 'wc_facebook_connection_invalid' );

		$this->add_filter_with_safe_teardown(
			'facebook_for_woocommerce_integration_pixel_enabled',
			static function () {
				return true;
			}
		);

		// get_api() needs an access token to build the client.
		$this->add_filter_with_safe_teardown(
			'wc_facebook_connection_access_token',
			static function () {
				return 'test-access-token';
			}
		);

		$this->tracker = new WC_Facebookcommerce_EventsTracker(
			array(),
			new AAMSettings(
				array(
					'enableAutomaticMatching'        => false,
					'enabledAutomaticMatchingFields' => array(),
					'pixelId'                        => 'test_pixel_123',
				)
			)
		);
	}

	public function tearDown(): void {
		delete_transient( 'wc_facebook_connection_invalid' );

		// get_api() builds the API client once and keeps it on the plugin singleton.
		// The client built here with a test token would otherwise outlive this test:
		// later tests expecting "no access token, no API" would get it instead and
		// send real requests.
		$api = new \ReflectionProperty( \WC_Facebookcommerce::class, 'api' );
		$api->setAccessible( true );
		$api->setValue( facebook_for_woocommerce(), null );

		if ( null === $this->original_user_agent ) {
			unset( $_SERVER['HTTP_USER_AGENT'] );
		} else {
			$_SERVER['HTTP_USER_AGENT'] = $this->original_user_agent;
		}

		parent::tearDown();
	}

	/**
	 * Answers the next Graph API request with the given response.
	 *
	 * @param array|\WP_Error $response a pre_http_request response, or an error.
	 */
	private function answer_requests_with( $response ): void {
		$this->add_filter_with_safe_teardown(
			'pre_http_request',
			static function () use ( $response ) {
				return $response;
			},
			10,
			3
		);
	}

	/**
	 * Calls send_api_event(), which is protected.
	 *
	 * @param Event $event    the event.
	 * @param bool  $send_now whether to send immediately.
	 * @return mixed
	 */
	private function send( Event $event, bool $send_now = true ) {
		$method = new \ReflectionMethod( WC_Facebookcommerce_EventsTracker::class, 'send_api_event' );
		$method->setAccessible( true );

		return $method->invoke( $this->tracker, $event, $send_now );
	}

	/**
	 * Builds an offline Purchase event.
	 *
	 * @return Event
	 */
	private function event(): Event {
		return new Event(
			array(
				'event_name'    => 'Purchase',
				'action_source' => 'physical_store',
				'user_data'     => array( 'em' => 'buyer@example.com' ),
			)
		);
	}

	public function test_accepted_event_is_delivered() {
		$this->answer_requests_with(
			array(
				'headers'  => array(),
				'body'     => wp_json_encode( array( 'events_received' => 1, 'fbtrace_id' => 'abc' ) ),
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'cookies'  => array(),
			)
		);

		$this->assertTrue( $this->send( $this->event() ) );
	}

	public function test_event_rejected_by_meta_is_not_delivered() {
		// The real-world case: Meta refused an event it could not match.
		$this->answer_requests_with(
			array(
				'headers'  => array(),
				'body'     => wp_json_encode(
					array(
						'error' => array(
							'message'       => 'Invalid parameter',
							'type'          => 'OAuthException',
							'code'          => 100,
							'error_subcode' => 2804050,
						),
					)
				),
				'response' => array( 'code' => 400, 'message' => 'Bad Request' ),
				'cookies'  => array(),
			)
		);

		$this->assertFalse( $this->send( $this->event() ) );
	}

	public function test_failed_request_is_not_delivered() {
		$this->answer_requests_with( new \WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ) );

		$this->assertFalse( $this->send( $this->event() ) );
	}

	public function test_failed_non_blocking_request_is_not_delivered() {
		$this->add_filter_with_safe_teardown( 'wc_facebook_pixel_events_non_blocking', '__return_true' );
		$this->answer_requests_with( new \WP_Error( 'http_request_failed', 'Could not resolve host: graph.facebook.com' ) );

		$this->assertFalse( $this->send( $this->event() ) );
	}

	public function test_non_blocking_request_without_transport_error_counts_as_handed_over() {
		$this->add_filter_with_safe_teardown( 'wc_facebook_pixel_events_non_blocking', '__return_true' );
		$this->answer_requests_with(
			array(
				'headers'  => array(),
				'body'     => '',
				'response' => array( 'code' => false, 'message' => false ),
				'cookies'  => array(),
			)
		);

		$this->assertTrue( $this->send( $this->event() ) );
	}

	public function test_event_is_not_delivered_while_the_connection_is_known_invalid() {
		set_transient( 'wc_facebook_connection_invalid', time(), HOUR_IN_SECONDS );

		$this->assertFalse( $this->send( $this->event() ) );
	}

	public function test_event_from_a_crawler_is_not_delivered() {
		$_SERVER['HTTP_USER_AGENT'] = 'meta-externalagent/1.1';

		$this->assertFalse( $this->send( $this->event() ) );
	}

	public function test_deferred_event_counts_as_handed_over() {
		// Deferred events are sent at shutdown, so there is no outcome to report yet.
		$this->assertTrue( $this->send( $this->event(), false ) );
	}
}
