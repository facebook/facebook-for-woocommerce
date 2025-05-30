<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Handlers;

defined( 'ABSPATH' ) || exit;

/**
 * The WebHook handler.
 *
 * @since 2.3.0
 */
class WebHook {

	/** @var string auth page ID */
	const WEBHOOK_PAGE_ID = 'wc-facebook-webhook';

	/**
	 * Constructs a new WebHook.
	 *
	 * @since 2.3.0
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'init_webhook_endpoint' ) );
	}


	/**
	 * Register WebHook REST API endpoint
	 *
	 * @since 2.3.0
	 */
	public function init_webhook_endpoint() {
		register_rest_route(
			'wc-facebook/v1',
			'webhook',
			array(
				array(
					'methods'             => array( 'GET', 'POST' ),
					'callback'            => array( $this, 'webhook_callback' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);
	}


	/**
	 * Endpoint permissions
	 * Woo Connect Bridge is sending the WebHook request using generated key.
	 *
	 * @since 2.3.0
	 *
	 * @return boolean
	 */
	public function permission_callback() {
		return current_user_can( 'manage_woocommerce' );
	}


	/**
	 * WebHook Listener
	 *
	 * @since 2.3.0
	 * @see Connection
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function webhook_callback( \WP_REST_Request $request ) {
		$request_body = json_decode( $request->get_body() );
		if ( empty( $request_body ) ) {
			return new \WP_REST_Response( null, 204 );
		}
		do_action( 'fbe_webhook', $request_body );
		return new \WP_REST_Response( null, 200 );
	}
}
