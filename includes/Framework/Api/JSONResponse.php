<?php
/**
 * Meta for WooCommerce.
 */

namespace WooCommerce\Facebook\Framework\Api;

use ArrayAccess;

defined( 'ABSPATH' ) || exit;

/**
 * Base JSON API response class.
 *
 * @since 4.3.0
 */
abstract class JSONResponse implements Response, ArrayAccess {

	/** @var string string representation of this response */
	protected $raw_response_json;

	/** @var array decoded response data */
	public $response_data;

	/**
	 * Build the data object from the raw JSON.
	 *
	 * @since 4.3.0
	 *
	 * @param string $raw_response_json The raw JSON
	 */
	public function __construct( string $raw_response_json ) {
		$this->raw_response_json = $raw_response_json;
		$this->response_data     = json_decode( $raw_response_json, true );
	}

	/**
	 * Magic accessor for response data attributes
	 *
	 * @since 4.3.0
	 *
	 * @param string $name The attribute name to get.
	 *
	 * @return mixed The attribute value
	 */
	public function __get( string $name ) {
		// accessing the response_data object indirectly via attribute (useful when it's a class)
		return $this->response_data[ $name ] ?? null;
	}

	/**
	 * Get the string representation of this response.
	 *
	 * @since 4.3.0
	 * @return string
	 * @see   SV_Response::to_string()
	 */
	public function to_string() {
		return $this->raw_response_json;
	}

	/**
	 * Get the string representation of this response with any and all sensitive elements masked
	 * or removed.
	 *
	 * @since 4.3.0
	 * @return string
	 * @see   Response::to_string_safe()
	 */
	public function to_string_safe() {
		return $this->to_string();
	}

	/**
	 * Determine whether the given offset exists.
	 *
	 * @since 3.0.2
	 *
	 * @param int|string $offset The offset key.
	 *
	 * @return bool Whether the offset exists.
	 */
	public function offsetExists( $offset ): bool {
		return array_key_exists( $offset, $this->response_data );
	}

	/**
	 * Get the given offset.
	 *
	 * @since 3.0.2
	 *
	 * @param int|string $offset The offset key.
	 *
	 * @return mixed The offset value.
	 */
	#[\ReturnTypeWillChange]
	public function offsetGet( $offset ) {
		return $this->response_data[ $offset ];
	}

	/**
	 * Set the offset to the given value.
	 *
	 * @since 3.0.2
	 *
	 * @param int|string $offset The offset key.
	 * @param mixed      $value  The offset value.
	 *
	 * @return void
	 */
	public function offsetSet( $offset, $value ): void {
		$this->response_data[ $offset ] = $value;
	}

	/**
	 * Unset the given offset.
	 *
	 * @since 3.0.2
	 *
	 * @param int|string $offset The offset key.
	 *
	 * @return void
	 */
	public function offsetUnset( $offset ): void {
		unset( $this->response_data[ $offset ] );
	}
}
