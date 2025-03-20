<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\API\Plugin\Traits;

defined( 'ABSPATH' ) || exit;

/**
 * Trait for request classes that should be exposed to JavaScript.
 *
 * @since 3.5.0
 */
trait JS_Exposable {
	/**
	 * Determines if this request class should be exposed to JavaScript.
	 *
	 * @since 3.5.0
	 *
	 * @return bool
	 */
	public static function is_js_exposable() {
		return true;
	}

	/**
	 * Gets the API endpoint for this request.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	abstract public function get_endpoint();

	/**
	 * Gets the HTTP method for this request.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	abstract public function get_method();

	/**
	 * Gets the parameter schema for this request.
	 *
	 * @since 3.5.0
	 *
	 * @return array Array of parameters with their types and whether they're required
	 */
	abstract public function get_param_schema();

	/**
	 * Gets the JavaScript function name for this request.
	 *
	 * By default, converts the class name from RequestClassName to functionName.
	 * Override this method to customize the function name.
	 *
	 * @since 3.5.0
	 *
	 * @return string
	 */
	abstract public function get_js_function_name();

	/**
	 * Gets the JS API definition for this request.
	 *
	 * @since 3.5.0
	 *
	 * @return array
	 */
	public function get_js_api_definition() {
		$schema   = $this->get_param_schema();
		$required = [];

		// Extract required parameters from schema
		foreach ( $schema as $param => $config ) {
			if ( ! empty( $config['required'] ) ) {
				$required[] = $param;
			}
		}

		return [
			'path'      => $this->get_endpoint(),
			'method'    => $this->get_method(),
			'className' => $this->get_js_function_name(),
			'params'    => array_map(
				function ( $config ) {
					return $config['type'];
				},
				$schema
			),
			'required'  => $required,
		];
	}
}
