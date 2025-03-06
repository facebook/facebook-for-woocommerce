<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\API\REST;

defined( 'ABSPATH' ) || exit;

/**
 * Base REST API Controller.
 *
 * Handles registration of all REST API endpoints.
 *
 * @since 2.3.5
 */
class Controller {

	/** @var string API namespace */
	const API_NAMESPACE = 'wc-facebook/v1';

	/** @var array Registered endpoint handlers */
	private static $endpoint_handlers = [];

	/**
	 * Constructor.
	 *
	 * @since 2.3.5
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Registers all REST API routes.
	 *
	 * @since 2.3.5
	 */
	public function register_routes() {
		// Register all endpoint handlers
		$this->register_endpoint_handlers();

		// Loop through registered handlers and register their routes
		foreach ( self::$endpoint_handlers as $handler ) {
			if ( method_exists( $handler, 'register_routes' ) ) {
				$handler->register_routes();
			}
		}
	}

	/**
	 * Registers all endpoint handlers.
	 *
	 * @since 2.3.5
	 */
	private function register_endpoint_handlers() {
		self::$endpoint_handlers = [
			new Settings\Handler(),
			new WebHook\Handler(),
			new Connection\Handler(),
		];

		/**
		 * Filter the REST API endpoint handlers.
		 *
		 * @since 2.3.5
		 *
		 * @param array $endpoint_handlers Array of endpoint handler instances
		 */
		self::$endpoint_handlers = apply_filters( 'wc_facebook_rest_endpoint_handlers', self::$endpoint_handlers );
	}

	/**
	 * Gets the API namespace.
	 *
	 * @since 2.3.5
	 *
	 * @return string
	 */
	public static function get_namespace() {
		return self::API_NAMESPACE;
	}
} 