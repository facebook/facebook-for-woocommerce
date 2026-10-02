<?php
/**
 * Copyright (c) Meta, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Events;

use ReflectionClass;
use WC_Facebookcommerce_EventsTracker;
use WooCommerce\Facebook\Events\AAMSettings;
use WooCommerce\Facebook\Events\Event;
use WooCommerce\Facebook\Events\FacebookSignalsState;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;

/**
 * Tests that the signals hold does not strand offline (physical store) events.
 *
 * The hold represents a web visitor's consent state. A sale rung up at a till has
 * no browser session that consent could describe, and queueing one would strand it:
 * the queue is drained by a storefront AJAX call a point-of-sale terminal never makes.
 *
 * @covers WC_Facebookcommerce_EventsTracker::send_api_event
 */
class OfflineEventSignalsHoldTest extends AbstractWPUnitTestWithSafeFiltering {

	/** @var WC_Facebookcommerce_EventsTracker|null */
	private $instance;

	/** @var string|null */
	private $original_user_agent;

	public function setUp(): void {
		parent::setUp();

		// FacebookSignalsState keeps the queue in a static that release() does not
		// clear, and the suite shares one WordPress instance, so reset it explicitly.
		$this->reset_queued_events();

		$this->original_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

		// Avoid being classified as a crawler, which would suppress events outright.
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

		$this->add_filter_with_safe_teardown(
			'facebook_for_woocommerce_integration_pixel_enabled',
			static function () {
				return true;
			}
		);

		$aam_settings = new AAMSettings(
			array(
				'enableAutomaticMatching'        => true,
				'enabledAutomaticMatchingFields' => array( 'em' ),
				'pixelId'                        => 'test_pixel_123',
			)
		);

		$this->instance = new WC_Facebookcommerce_EventsTracker( array(), $aam_settings );
	}

	public function tearDown(): void {
		FacebookSignalsState::release();
		$this->reset_queued_events();

		$this->instance = null;

		if ( null === $this->original_user_agent ) {
			unset( $_SERVER['HTTP_USER_AGENT'] );
		} else {
			$_SERVER['HTTP_USER_AGENT'] = $this->original_user_agent;
		}

		parent::tearDown();
	}

	/**
	 * Calls send_api_event(), which is protected.
	 *
	 * @param Event $event the event to send.
	 */
	private function send( Event $event ): void {
		$method = ( new ReflectionClass( $this->instance ) )->getMethod( 'send_api_event' );
		$method->setAccessible( true );
		$method->invoke( $this->instance, $event );
	}

	/**
	 * Gets the reflected static holding queued events.
	 *
	 * @return \ReflectionProperty
	 */
	private function queued_events_property(): \ReflectionProperty {
		$property = ( new ReflectionClass( FacebookSignalsState::class ) )->getProperty( 'queued_events' );
		$property->setAccessible( true );

		return $property;
	}

	/**
	 * Reads the queue the hold diverts events into.
	 *
	 * @return array
	 */
	private function get_queued_events(): array {
		return (array) $this->queued_events_property()->getValue();
	}

	/**
	 * Empties the queue so state cannot leak between tests.
	 */
	private function reset_queued_events(): void {
		$this->queued_events_property()->setValue( null, array() );
	}

	public function test_given_signals_held_when_offline_event_sent_then_it_is_not_queued() {
		FacebookSignalsState::hold();

		$this->send(
			new Event(
				array(
					'event_name'    => 'Purchase',
					'action_source' => 'physical_store',
				)
			)
		);

		$this->assertSame(
			array(),
			$this->get_queued_events(),
			'A physical store event must not be held behind web-visitor consent.'
		);
	}

	public function test_given_signals_held_when_web_event_sent_then_it_is_queued() {
		// Guards against the physical_store carve-out leaking into the web path.
		FacebookSignalsState::hold();

		$this->send( new Event( array( 'event_name' => 'Purchase' ) ) );

		$this->assertCount(
			1,
			$this->get_queued_events(),
			'A website event must still be held while signals are held.'
		);
	}

	public function test_event_reports_whether_it_is_a_physical_store_sale() {
		$offline = new Event(
			array(
				'event_name'    => 'Purchase',
				'action_source' => 'physical_store',
			)
		);
		$web     = new Event( array( 'event_name' => 'Purchase' ) );

		$this->assertTrue( $offline->is_physical_store() );
		$this->assertFalse( $web->is_physical_store() );
	}
}
