<?php
/**
 * Copyright (c) Meta, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

use WooCommerce\Facebook\AJAX;
use WooCommerce\Facebook\Events\POS\POS_Integration_Interface;

/**
 * Tests the AJAX handlers that toggle offline (physical store) purchase events.
 *
 * @covers \WooCommerce\Facebook\AJAX
 */
class OfflineEventsAjaxTest extends WP_Ajax_UnitTestCase {

	/** @var AJAX */
	private $ajax;

	/** @var callable|null */
	private $integrations_filter;

	public function setUp(): void {
		parent::setUp();

		$this->ajax = new AJAX();

		// A nonce left behind by a previous test would let a case that should fail
		// authorization quietly succeed.
		$_POST    = array();
		$_GET     = array();
		$_REQUEST = array();

		delete_option( \WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS );
	}

	public function tearDown(): void {
		if ( $this->integrations_filter ) {
			remove_filter( 'wc_facebook_pos_integrations', $this->integrations_filter );
			$this->integrations_filter = null;
		}

		delete_option( \WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS );

		$_POST    = array();
		$_GET     = array();
		$_REQUEST = array();

		parent::tearDown();
	}

	/**
	 * Registers a stub POS integration so detection can be controlled.
	 *
	 * @param bool   $is_supported whether the POS plugin should report as active.
	 * @param string $slug         the integration slug.
	 */
	private function register_pos( bool $is_supported, string $slug = 'stub' ): void {
		$integration = $this->createMock( POS_Integration_Interface::class );
		$integration->method( 'get_slug' )->willReturn( $slug );
		$integration->method( 'get_name' )->willReturn( strtoupper( $slug ) );
		$integration->method( 'is_supported' )->willReturn( $is_supported );
		$integration->method( 'is_pos_order' )->willReturn( false );
		$integration->method( 'get_event_data' )->willReturn( array() );

		$this->integrations_filter = static function () use ( $integration ) {
			return array( $integration );
		};

		add_filter( 'wc_facebook_pos_integrations', $this->integrations_filter );
	}

	/**
	 * Dispatches an AJAX action and returns the decoded response.
	 *
	 * @param string $action the AJAX action to run.
	 * @return object
	 */
	private function dispatch( string $action ) {
		// _handleAjax() rebuilds $_REQUEST as array_merge( $_POST, $_GET ), so a nonce
		// written straight to $_REQUEST is discarded before the handler runs. It has
		// to go into $_POST to survive.
		$_POST['action']      = $action;
		$_POST['_ajax_nonce'] = wp_create_nonce( $action );
		$_POST['nonce']       = $_POST['_ajax_nonce'];
		$_REQUEST             = array_merge( $_REQUEST, $_POST );

		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected: wp_send_json_* terminates the handler. The response is read below.
			unset( $e );
		}

		return json_decode( $this->_last_response );
	}

	public function test_given_pos_active_when_enable_called_then_option_is_set() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->register_pos( true, 'wcpos' );

		$response = $this->dispatch( AJAX::ACTION_ENABLE_OFFLINE_EVENTS );

		$this->assertTrue( $response->success );
		$this->assertTrue( $response->data->enabled );
		$this->assertTrue( $response->data->supported );
		$this->assertSame( array( 'wcpos' ), (array) $response->data->integrations );
		$this->assertSame( 'yes', get_option( \WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS ) );
	}

	public function test_given_no_pos_active_when_enable_called_then_it_is_refused() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->register_pos( false );

		$response = $this->dispatch( AJAX::ACTION_ENABLE_OFFLINE_EVENTS );

		$this->assertFalse( $response->success );
		$this->assertFalse( $response->data->status->enabled );
		$this->assertNotEquals(
			'yes',
			get_option( \WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS ),
			'Refusing to enable must not write the option.'
		);
	}

	public function test_given_enabled_when_disable_called_then_option_is_cleared() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->register_pos( true );
		update_option( \WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS, 'yes' );

		$response = $this->dispatch( AJAX::ACTION_DISABLE_OFFLINE_EVENTS );

		$this->assertTrue( $response->success );
		$this->assertFalse( $response->data->enabled );
		$this->assertSame( 'no', get_option( \WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS ) );
	}

	public function test_given_no_pos_active_when_disable_called_then_it_still_succeeds() {
		// A merchant must be able to clear the setting after deactivating their POS plugin.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->register_pos( false );
		update_option( \WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS, 'yes' );

		$response = $this->dispatch( AJAX::ACTION_DISABLE_OFFLINE_EVENTS );

		$this->assertTrue( $response->success );
		$this->assertSame( 'no', get_option( \WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS ) );
	}

	public function test_status_reports_current_state_without_changing_it() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->register_pos( true, 'wcpos' );

		$response = $this->dispatch( AJAX::ACTION_GET_OFFLINE_EVENTS_STATUS );

		$this->assertTrue( $response->success );
		$this->assertFalse( $response->data->enabled );
		$this->assertTrue( $response->data->supported );
		$this->assertFalse(
			get_option( \WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS ),
			'Reading the status must not create the option.'
		);
	}

	public function test_given_user_without_capability_when_enable_called_then_permission_denied() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->register_pos( true );

		$response = $this->dispatch( AJAX::ACTION_ENABLE_OFFLINE_EVENTS );

		$this->assertFalse( $response->success );
		$this->assertNotEquals(
			'yes',
			get_option( \WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS )
		);
	}

	public function test_given_logged_out_user_when_enable_called_then_permission_denied() {
		wp_set_current_user( 0 );
		$this->register_pos( true );

		$response = $this->dispatch( AJAX::ACTION_ENABLE_OFFLINE_EVENTS );

		$this->assertFalse( $response->success );
		$this->assertNotEquals(
			'yes',
			get_option( \WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS )
		);
	}

	public function test_given_missing_nonce_when_enable_called_then_request_is_rejected() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->register_pos( true );

		unset(
			$_POST['_ajax_nonce'], $_POST['nonce'], $_POST['_wpnonce'],
			$_GET['_ajax_nonce'], $_GET['nonce'], $_GET['_wpnonce'],
			$_REQUEST['_ajax_nonce'], $_REQUEST['nonce'], $_REQUEST['_wpnonce']
		);

		// check_ajax_referer() halts with a 403 rather than returning false.
		$this->expectException( WPAjaxDieStopException::class );

		try {
			$this->_handleAjax( AJAX::ACTION_ENABLE_OFFLINE_EVENTS );
		} finally {
			$this->assertNotEquals(
				'yes',
				get_option( \WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS ),
				'A request without a nonce must not write the option.'
			);
		}
	}

	public function test_given_nonce_for_another_action_when_enable_called_then_request_is_rejected() {
		// A nonce minted for the disable action must not authorize enabling.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->register_pos( true );

		$_POST['_ajax_nonce'] = wp_create_nonce( AJAX::ACTION_DISABLE_OFFLINE_EVENTS );
		$_POST['nonce']       = $_POST['_ajax_nonce'];
		$_REQUEST             = array_merge( $_REQUEST, $_POST );

		$this->expectException( WPAjaxDieStopException::class );

		try {
			$this->_handleAjax( AJAX::ACTION_ENABLE_OFFLINE_EVENTS );
		} finally {
			$this->assertNotEquals(
				'yes',
				get_option( \WC_Facebookcommerce_Integration::SETTING_ENABLE_OFFLINE_PURCHASE_EVENTS ),
				'A nonce for a different action must not be accepted.'
			);
		}
	}

	public function test_offline_events_actions_are_not_registered_for_logged_out_users() {
		// Guards against a wp_ajax_nopriv_ registration being added later.
		foreach (
			array(
				AJAX::ACTION_ENABLE_OFFLINE_EVENTS,
				AJAX::ACTION_DISABLE_OFFLINE_EVENTS,
				AJAX::ACTION_GET_OFFLINE_EVENTS_STATUS,
			) as $action
		) {
			$this->assertFalse(
				has_action( 'wp_ajax_nopriv_' . $action ),
				$action . ' must not be exposed to logged-out callers.'
			);
		}
	}
}
