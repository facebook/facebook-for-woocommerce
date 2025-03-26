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
 * The Whatsapp WebHook handler to receive POST request from Meta Hosted Connectbridge.
 *
 * @since 2.3.0
 */
class Whatsapp_Webhook {

	/**
	 * Constructs a new Whatsapp WebHook.
	 *
	 * @param \WC_Facebookcommerce $plugin Plugin instance.
	 *
	 * @since 2.3.0
	 */
	public function __construct( \WC_Facebookcommerce $plugin ) {
		add_action( 'rest_api_init', array( $this, 'init_whatsapp_webhook_endpoint' ) );
	}


	/**
	 * Register Whatsapp WebHook REST API endpoint
	 *
	 * @since 2.3.0
	 */
	public function init_whatsapp_webhook_endpoint() {
		register_rest_route(
			'wc-facebook/v1',
			'whatsapp_webhook',
			array(
				array(
					'methods'             => array( 'POST' ),
					'callback'            => array( $this, 'whatsapp_webhook_callback' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}



	/**
	 * Whatsapp Webhook Listener
	 *
	 * @since 2.3.0
	 * @see Connection
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function whatsapp_webhook_callback( \WP_REST_Request $request ) {
		$request_params = $request->get_query_params();
		// Note: The log statements will be removed in follow up PRs. This is just added to verify the whatsapp webhook is called successfully.
		wc_get_logger()->error( 'Webhook is called successfully', $request_params );
		// TODO: Validate the request and store the data(business_id,waba_id,access_token) received in the WooCommerce DB
		return new \WP_REST_Response( null, 200 );
	}
}
