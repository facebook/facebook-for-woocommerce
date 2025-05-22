<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API;

use WooCommerce\Facebook\Framework\Api\JSONRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Base API request object.
 *
 * @since 2.0.0
 */
class Request extends JSONRequest {

	use Traits\Rate_Limited_Request;

	/** @var int maximum number of retries to attempt if told to do so by Facebook */
	protected $retry_limit = 5;

	/** @var int number of times this request has been retried */
	protected $retry_count = 0;

	/** @var int[] the response codes that should trigger a retry */
	protected $retry_codes = [];

	/**
	 * API request constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param string $path endpoint route
	 * @param string $method HTTP method
	 */
	public function __construct( $path, $method ) {
		$this->method = $method;
		$this->path   = $path;
	}


	/**
	 * Sets the request parameters.
	 *
	 * @since 2.0.0
	 *
	 * @param array $params request parameters
	 */
	public function set_params( $params ) {
		$this->params = $params;
	}


	/**
	 * Sets the request data.
	 *
	 * @since 2.0.0
	 *
	 * @param array $data request data
	 */
	public function set_data( $data ) {
		$this->data = $data;
	}


	/**
	 * Gets the number of times this request has been retried.
	 *
	 * @since 2.1.0
	 *
	 * @return int
	 */
	public function get_retry_count() {
		return $this->retry_count;
	}


	/**
	 * Marks the request as having been retried.
	 *
	 * @since 2.1.0
	 */
	public function mark_retry() {
		++$this->retry_count;
	}


	/**
	 * Gets the maximum number of retries to attempt if told to do so by Facebook.
	 *
	 * @since 2.1.0
	 *
	 * @return int
	 */
	public function get_retry_limit() {
		/**
		 * Filters the maximum number of retries allowed for the request.
		 *
		 * @since 2.1.0
		 *
		 * @param int $retry_limit maximum number of retries
		 * @param Request $request request object
		 */
		return (int) apply_filters( 'wc_facebook_api_request_retry_limit', $this->retry_limit, $this );
	}


	/**
	 * Response codes that should trigger a retry for this request.
	 *
	 * @since 2.1.0
	 *
	 * @return int[]
	 */
	public function get_retry_codes() {
		return $this->retry_codes;
	}

	/**
	 * @return string|null
	 */
	public function get_base_path_override(): ?string {
		return null;
	}

	/**
	 * @return array|null
	 */
	public function get_request_specific_headers(): array {
		return [];
	}
}
