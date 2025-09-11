<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\API\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Base REST API Controller.
 *
 * Handles registration of all REST API endpoints.
 *
 * @since 3.5.0
 */
class Controller {

	/** @var string API namespace */
	const API_NAMESPACE = 'wc-facebook/v1';

	/** @var array Endpoint handler classes */
	const ENDPOINT_HANDLERS = [
		Settings\Handler::class,
		WhatsAppSettings\Handler::class,
		// Add other handler classes here
	];

	/** @var array JS-enabled request classes */
	const JS_ENABLED_REQUESTS = [
		'WooCommerce\Facebook\API\Plugin\Settings\Update\Request',
		'WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request',
		'WooCommerce\Facebook\API\Plugin\WhatsAppSettings\Update\Request',
		'WooCommerce\Facebook\API\Plugin\WhatsAppSettings\Uninstall\Request',
		// Add other JS-enabled request classes here
	];

	/** @var array Registered endpoint handlers */
	private static $endpoint_handlers = [];

	/**
	 * Constructor.
	 *
	 * @since 3.5.0
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Registers all REST API routes.
	 *
	 * @since 3.5.0
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
	 * @since 3.5.0
	 */
	private function register_endpoint_handlers() {
		self::$endpoint_handlers = [];

		// Instantiate all handler classes from the constant
		foreach ( self::ENDPOINT_HANDLERS as $handler_class ) {
			self::$endpoint_handlers[] = new $handler_class();
		}

		/**
		 * Filter the REST API endpoint handlers.
		 *
		 * @since 3.5.0
		 *
		 * @param array $endpoint_handlers Array of endpoint handler instances
		 */
		self::$endpoint_handlers = apply_filters( 'wc_facebook_rest_endpoint_handlers', self::$endpoint_handlers );
	}

	/**
	 * Gets the API namespace.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	public static function get_namespace() {
		return self::API_NAMESPACE;
	}

	/**
	 * Gets all JS-enabled request classes.
	 *
	 * @since 3.5.0
	 *
	 * @return array
	 */
	public static function get_js_enabled_requests() {
		return self::JS_ENABLED_REQUESTS;
	}
}
