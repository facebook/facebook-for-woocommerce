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
 * Abstract REST API Endpoint.
 *
 * Provides common functionality for all REST API endpoints.
 *
 * @since 2.3.5
 */
abstract class Endpoint {

	/**
	 * Register routes for this endpoint.
	 *
	 * @since 2.3.5
	 *
	 * @return void
	 */
	abstract public function register_routes();

	/**
	 * Check if the current user has permission to access this endpoint.
	 *
	 * @since 2.3.5
	 *
	 * @return bool
	 */
	public function permission_callback() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Get the REST API namespace.
	 *
	 * @since 2.3.5
	 *
	 * @return string
	 */
	protected function get_namespace() {
		return Controller::get_namespace();
	}

	/**
	 * Format a successful response.
	 *
	 * @since 2.3.5
	 *
	 * @param mixed $data Response data.
	 * @param int   $status HTTP status code.
	 * @return \WP_REST_Response
	 */
	protected function success_response( $data = null, $status = 200 ) {
		return new \WP_REST_Response(
			[
				'success' => true,
				'data'    => $data,
			],
			$status
		);
	}

	/**
	 * Format an error response.
	 *
	 * @since 2.3.5
	 *
	 * @param string $message Error message.
	 * @param int    $status HTTP status code.
	 * @param array  $additional_data Additional data to include in the response.
	 * @return \WP_REST_Response
	 */
	protected function error_response( $message, $status = 400, $additional_data = [] ) {
		$response = [
			'success' => false,
			'message' => $message,
		];

		if ( ! empty( $additional_data ) ) {
			$response['data'] = $additional_data;
		}

		return new \WP_REST_Response( $response, $status );
	}

	/**
	 * Get JavaScript API definitions for this endpoint.
	 *
	 * @since 2.3.5
	 *
	 * @return array
	 */
	public function get_js_api_definitions() {
		$reflection = new \ReflectionClass( $this );
		$methods = $reflection->getMethods( \ReflectionMethod::IS_PUBLIC );
		$definitions = [];
		
		foreach ( $methods as $method ) {
			// Only process methods that start with "handle_"
			if ( strpos( $method->getName(), 'handle_' ) !== 0 ) {
				continue;
			}
			
			$endpoint_name = str_replace( 'handle_', '', $method->getName() );
			
			// Check if the corresponding request class is JS exposable
			if ( ! $this->is_request_js_exposable( $endpoint_name ) ) {
				continue;
			}
			
			$js_method_name = $this->get_js_method_name( $endpoint_name );
			
			$definitions[ $js_method_name ] = [
				'endpoint' => $this->get_endpoint_path( $endpoint_name ),
				'method' => $this->extract_http_method( $method ),
				'params' => $this->extract_parameters( $method ),
				'description' => $this->extract_description( $method ),
			];
		}
		
		return $definitions;
	}

	/**
	 * Check if a request class is exposable to JavaScript.
	 *
	 * @since 2.3.5
	 *
	 * @param string $endpoint_name The endpoint name.
	 * @return bool
	 */
	protected function is_request_js_exposable( $endpoint_name ) {
		// Get the request class name based on the endpoint name
		$request_class = $this->get_request_class_name( $endpoint_name );
		
		// If the class doesn't exist, it's not exposable
		if ( ! class_exists( $request_class ) ) {
			return false;
		}
		
		// Check if the class has the is_js_exposable method
		if ( ! method_exists( $request_class, 'is_js_exposable' ) ) {
			return false;
		}
		
		// Call the method to check if it's exposable
		return $request_class::is_js_exposable();
	}

	/**
	 * Get the request class name for an endpoint.
	 *
	 * @since 2.3.5
	 *
	 * @param string $endpoint_name The endpoint name.
	 * @return string
	 */
	protected function get_request_class_name( $endpoint_name ) {
		// Convert snake_case to PascalCase
		$class_name = str_replace( '_', '', ucwords( $endpoint_name, '_' ) );
		
		// Get the namespace of the current class
		$reflection = new \ReflectionClass( $this );
		$namespace = $reflection->getNamespaceName();
		
		// Build the request class name
		return $namespace . '\\' . $class_name . '\\Request';
	}

	/**
	 * Extract HTTP method from docblock.
	 *
	 * @since 2.3.5
	 *
	 * @param \ReflectionMethod $method Method reflection.
	 * @return string
	 */
	private function extract_http_method( \ReflectionMethod $method ) {
		$docblock = $method->getDocComment();
		preg_match( '/@http_method\s+([A-Z]+)/', $docblock, $matches );
		return isset( $matches[1] ) ? $matches[1] : 'POST';
	}

	/**
	 * Extract parameters from method.
	 *
	 * @since 2.3.5
	 *
	 * @param \ReflectionMethod $method Method reflection.
	 * @return array
	 */
	private function extract_parameters( \ReflectionMethod $method ) {
		$parameters = $method->getParameters();
		$param_names = [];
		
		foreach ( $parameters as $param ) {
			// Skip the WP_REST_Request parameter
			if ( $param->getType() && $param->getType()->getName() === 'WP_REST_Request' ) {
				continue;
			}
			$param_names[] = $param->getName();
		}
		
		return $param_names;
	}

	/**
	 * Extract description from docblock.
	 *
	 * @since 2.3.5
	 *
	 * @param \ReflectionMethod $method Method reflection.
	 * @return string
	 */
	private function extract_description( \ReflectionMethod $method ) {
		$docblock = $method->getDocComment();
		preg_match( '/@description\s+(.+)/', $docblock, $matches );
		return isset( $matches[1] ) ? trim( $matches[1] ) : '';
	}

	/**
	 * Get JavaScript method name for an endpoint.
	 *
	 * @since 2.3.5
	 *
	 * @param string $endpoint_name Endpoint name.
	 * @return string
	 */
	private function get_js_method_name( $endpoint_name ) {
		// Convert snake_case to camelCase
		return lcfirst( str_replace( '_', '', ucwords( $endpoint_name, '_' ) ) );
	}

	/**
	 * Get endpoint path for a method.
	 *
	 * @since 2.3.5
	 *
	 * @param string $endpoint_name Endpoint name.
	 * @return string
	 */
	protected function get_endpoint_path( $endpoint_name ) {
		// Convert camelCase or snake_case to kebab-case
		$path = strtolower( preg_replace( '/(?<!^)[A-Z]/', '-$0', $endpoint_name ) );
		$path = str_replace( '_', '-', $path );
		
		// Get the handler class name without namespace and "Handler" suffix
		$reflection = new \ReflectionClass( $this );
		$class_name = $reflection->getShortName();
		$group_name = strtolower( str_replace( 'Handler', '', $class_name ) );
		
		return $group_name . '/' . $path;
	}
} 