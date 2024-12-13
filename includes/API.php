<?php
// phpcs:ignoreFile
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook;

defined( 'ABSPATH' ) or exit;

use WooCommerce\Facebook\API\Request;
use WooCommerce\Facebook\API\Response;
use WooCommerce\Facebook\Events\Event;

use WooCommerce\Facebook\Framework\Api\Base;
use WooCommerce\Facebook\Framework\Api\Exception as ApiException;

/**
 * API handler.
 *
 * @since 2.0.0
 *
 * @method Framework\Api\Request get_request()
 */
class API extends Base {

	use API\Traits\Rate_Limited_API;

	public const GRAPH_API_URL = 'https://graph.facebook.com/';

	public const API_VERSION = 'v20.0';

	/** @var string URI used for the request */
	protected $request_uri = self::GRAPH_API_URL . self::API_VERSION;

	/** @var string the configured access token */
	protected $access_token;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param string $access_token access token to use for API requests
	 */
	public function __construct( $access_token ) {
		$this->access_token = $access_token;
		$this->request_headers = array(
			'Authorization' => "Bearer {$access_token}",
		);
		$this->set_request_content_type_header( 'application/json' );
		$this->set_request_accept_header( 'application/json' );
	}


	/**
	 * Gets the access token being used for API requests.
	 *
	 * @since 2.1.0
	 *
	 * @return string
	 */
	public function get_access_token() {
		return $this->access_token;
	}


	/**
	 * Sets the access token to use for API requests.
	 *
	 * @since 2.1.0
	 *
	 * @param string $access_token access token to set
	 */
	public function set_access_token( $access_token ) {
		$this->access_token = $access_token;
	}


	/**
	 * Performs an API request.
	 *
	 * @param API\Request $request request object
	 * @return API\Response
	 * @throws API\Exceptions\Request_Limit_Reached|ApiException
	 */
	protected function perform_request( $request ): API\Response {
		$rate_limit_id   = $request::get_rate_limit_id();
		$delay_timestamp = $this->get_rate_limit_delay( $rate_limit_id );
		// if there is a delayed timestamp in the future, throw an exception
		if ( $delay_timestamp >= time() ) {
			$this->handle_throttled_request( $rate_limit_id, $delay_timestamp );
		} else {
			$this->set_rate_limit_delay( $rate_limit_id, 0 );
		}
		return parent::perform_request( $request );
	}


	/**
	 * Validates a response after it has been parsed and instantiated.
	 *
	 * Throws an exception if a rate limit or general API error is included in the response.
	 *
	 * @since 2.0.0
	 *
	 * @throws ApiException
	 */
	protected function do_post_parse_response_validation() {
		/** @var API\Response $response */
		$response = $this->get_response();
		$request  = $this->get_request();
		if ( $response && $response->has_api_error() ) {
			$code    = $response->get_api_error_code();
			$message = sprintf( '%s: %s', $response->get_api_error_type(), $response->get_user_error_message() ?: $response->get_api_error_message() );
			/**
			 * Graph API
			 *
			 * 4 - API Too Many Calls
			 * 17 - API User Too Many Calls
			 * 32 - Page-level throttling
			 * 613 - Custom-level throttling
			 *
			 * Marketing API (Catalog Batch API)
			 *
			 * 80004 - There have been too many calls to this ad-account
			 *
			 * @link https://developers.facebook.com/docs/graph-api/using-graph-api/error-handling#errorcodes
			 * @link https://developers.facebook.com/docs/graph-api/using-graph-api/error-handling#rate-limiting-error-codes
			 * @link https://developers.facebook.com/docs/marketing-api/reference/product-catalog/batch/#validation-rules
			 */
			if ( in_array( $code, array( 4, 17, 32, 613, 80001, 80004 ), true ) ) {
				$delay_in_seconds = $this->calculate_rate_limit_delay( $response, $this->get_response_headers() );
				if ( $delay_in_seconds > 0 ) {
					$rate_limit_id = $request::get_rate_limit_id();
					$timestamp     = time() + $delay_in_seconds;
					$this->set_rate_limit_delay( $rate_limit_id, $timestamp );
					$this->handle_throttled_request( $rate_limit_id, $timestamp );
				} else {
					throw new API\Exceptions\Request_Limit_Reached( $message, $code );
				}
			}

			/**
			 * Handle invalid token errors
			 *
			 * @link https://developers.facebook.com/docs/graph-api/using-graph-api/error-handling#errorcodes
			 */
			if ( ( $code >= 200 && $code < 300 ) || in_array( $code, array( 10, 102, 190 ), false ) ) {
				set_transient( 'wc_facebook_connection_invalid', time(), DAY_IN_SECONDS );
			} else {
				// this was an unrelated error, so the OAuth connection may still be valid
				delete_transient( 'wc_facebook_connection_invalid' );
			}
			// if the code indicates a retry and we've not hit the retry limit, perform the request again
			if ( in_array( $code, $request->get_retry_codes(), false ) && $request->get_retry_count() < $request->get_retry_limit() ) {
				$request->mark_retry();
				$this->response = $this->perform_request( $request );
				return;
			}
			throw new ApiException( $message, $code );
		}
		// if we get this far we're connected, so delete any invalid connection flag
		delete_transient( 'wc_facebook_connection_invalid' );
	}


	/**
	 * Handles a throttled API request.
	 *
	 * @since 2.1.0
	 *
	 * @param string $rate_limit_id ID for the API request
	 * @param int    $timestamp timestamp until the delay is over
	 * @throws API\Exceptions\Request_Limit_Reached
	 */
	private function handle_throttled_request( $rate_limit_id, $timestamp ) {
		if ( time() > $timestamp ) {
			return;
		}
		$exception = new API\Exceptions\Request_Limit_Reached( "{$rate_limit_id} requests are currently throttled.", 401 );
		$date_time = new \DateTime();
		$date_time->setTimestamp( $timestamp );
		$exception->set_throttle_end( $date_time );
		throw $exception;
	}


	/**
	 * Gets the FBE installation IDs.
	 *
	 * @param string $external_business_id External business id.
	 * @return API\Response|API\FBE\Installation\Read\Response
	 * @throws ApiException
	 */
	public function get_installation_ids( string $external_business_id ): API\FBE\Installation\Read\Response {
		$request = new API\FBE\Installation\Read\Request( $external_business_id );
		$this->set_response_handler( API\FBE\Installation\Read\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * Gets a Page object from Facebook.
	 *
	 * @since 2.0.0
	 *
	 * @param string $page_id page ID
	 * @return API\Response|API\Pages\Read\Response
	 * @throws ApiException
	 */
	public function get_page( $page_id ): API\Pages\Read\Response {
		$request = new API\Pages\Read\Request( $page_id );
		$this->set_response_handler( API\Pages\Read\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * Gets a Catalog object from Facebook.
	 *
	 * @param string $catalog_id Facebook catalog id.
	 * @return API\Response|API\Catalog\Response
	 * @throws ApiException
	 */
	public function get_catalog( string $catalog_id ): API\Catalog\Response {
		$request = new API\Catalog\Request( $catalog_id );
		$this->set_response_handler( API\Catalog\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * Gets a user object from Facebook.
	 *
	 * @param string $user_id user ID. Defaults to the currently authenticated user
	 * @return API\Response|API\User\Response
	 * @throws ApiException
	 */
	public function get_user( string $user_id = '' ): API\User\Response {
		$request = new API\User\Request( $user_id );
		$this->set_response_handler( API\User\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * Deletes FBE/MBE connection API.
	 *
	 * This is their form of "revoke".
	 *
	 * @param string $external_business_id external business ID
	 * @return API\Response|API\FBE\Installation\Delete\Response
	 * @throws ApiException
	 */
	public function delete_mbe_connection( string $external_business_id): API\FBE\Installation\Delete\Response {
		$request = new API\FBE\Installation\Delete\Request( $external_business_id);
		$this->set_response_handler( API\FBE\Installation\Delete\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * Gets the business configuration.
	 *
	 * @param string $external_business_id external business ID
	 * @return API\Response|API\FBE\Configuration\Read\Response
	 * @throws ApiException
	 */
	public function get_business_configuration( $external_business_id ) {
		$request = new API\FBE\Configuration\Request( $external_business_id, 'GET' );
		$this->set_response_handler( API\FBE\Configuration\Read\Response::class );
		return $this->perform_request( $request );
	}

	/**
	 * Updates the plugin version configuration.
	 *
	 * @param string $external_business_id external business ID
	 * @param string $plugin_version The plugin version.
	 * @return API\Response|API\FBE\Configuration\Update\Response
	 * @throws WooCommerce\Facebook\Framework\Api\Exception
	 */
	public function update_plugin_version_configuration( string $external_business_id, string $plugin_version ): API\FBE\Configuration\Update\Response {
		$request = new API\FBE\Configuration\Update\Request( $external_business_id );
		$request->set_plugin_version( $plugin_version );
		$this->set_response_handler( API\FBE\Configuration\Update\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * Uses the Catalog Batch API to update or remove items from catalog.
	 *
	 * @see Sync::create_or_update_products()
	 *
	 * @param string $facebook_product_catalog_id Facebook Product Catalog ID.
	 * @param array  $requests array of prefixed product IDs to create, update or remove.
	 * @return API\Response|API\ProductCatalog\ItemsBatch\Create\Response
	 * @throws ApiException
	 */
	public function send_item_updates( string $facebook_product_catalog_id, array $requests ) {
		$request = new API\ProductCatalog\ItemsBatch\Create\Request( $facebook_product_catalog_id, $requests );
		$this->set_response_handler( API\ProductCatalog\ItemsBatch\Create\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * Creates Facebook Product Group.
	 *
	 * @param string $product_catalog_id Facebook Product Catalog ID.
	 * @param array  $data Facebook Product Group Data.
	 * @return API\Response|API\ProductCatalog\ProductGroups\Create\Response
	 * @throws ApiException
	 */
	public function create_product_group( string $product_catalog_id, array $data ): API\ProductCatalog\ProductGroups\Create\Response {
		$request = new API\ProductCatalog\ProductGroups\Create\Request( $product_catalog_id, $data );
		$this->set_response_handler( API\ProductCatalog\ProductGroups\Create\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * Updates the default product item and the available variation attributes of a product group.
	 *
	 * @param string $product_group_id Facebook Product Group ID.
	 * @param array  $data Facebook Product Group Data.
	 * @return API\ProductCatalog\ProductGroups\Update\Response
	 * @throws ApiException
	 */
	public function update_product_group( string $product_group_id, array $data ): API\ProductCatalog\ProductGroups\Update\Response {
		$request = new API\ProductCatalog\ProductGroups\Update\Request( $product_group_id , $data );
		$this->set_response_handler( API\ProductCatalog\ProductGroups\Update\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * Deletes a Facebook Product Group object.
	 *
	 * @param string $product_group_id Facebook Product Group ID.
	 * @return API\ProductCatalog\ProductGroups\Delete\Response
	 * @throws ApiException
	 */
	public function delete_product_group( string $product_group_id ): API\ProductCatalog\ProductGroups\Delete\Response {
		$request = new API\ProductCatalog\ProductGroups\Delete\Request( $product_group_id );
		$this->set_response_handler( API\ProductCatalog\ProductGroups\Delete\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * Gets a list of Product Items in the given Product Group.
	 *
	 * @param string $product_group_id product group ID
	 * @param int    $limit max number of results returned per page of data
	 * @return API\Response|API\ProductCatalog\ProductGroups\Read\Response
	 * @throws ApiException
	 */
	public function get_product_group_products( string $product_group_id, int $limit = 1000 ): API\ProductCatalog\ProductGroups\Read\Response {
		$request = new API\ProductCatalog\ProductGroups\Read\Request( $product_group_id, $limit );
		$this->set_response_handler( API\ProductCatalog\ProductGroups\Read\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * Finds a Product Item using the Catalog ID and the Retailer ID of the product or product variation.
	 *
	 * @since 2.0.0
	 *
	 * @param string $catalog_id catalog ID
	 * @param string $retailer_id retailer ID of the product
	 * @return Response
	 * @throws ApiException
	 */
	public function find_product_item( $catalog_id, $retailer_id ) {
		$request = new \WooCommerce\Facebook\API\Catalog\Product_Item\Find\Request( $catalog_id, $retailer_id );
		$this->set_response_handler( \WooCommerce\Facebook\API\Catalog\Product_Item\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * Creates a Product under the specified Product Group.
	 *
	 * @since 2.0.0
	 *
	 * @param string $product_group_id Facebook Product Group ID.
	 * @param array  $data Facebook Product Data.
	 * @return API\Response|API\ProductCatalog\Products\Create\Response
	 * @throws ApiException In case of network request error.
	 */
	public function create_product_item( string $product_group_id, array $data ): API\ProductCatalog\Products\Create\Response {
		$request = new API\ProductCatalog\Products\Create\Request( $product_group_id, $data );
		$this->set_response_handler( API\ProductCatalog\Products\Create\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * Updates a Product Item object.
	 *
	 * @param string $facebook_product_id Facebook Product ID.
	 * @param array  $data Product Data.
	 * @return API\Response|API\ProductCatalog\Products\Update\Response
	 * @throws ApiException In case of network request error.
	 */
	public function update_product_item( string $facebook_product_id, array $data ): API\ProductCatalog\Products\Update\Response {
		$request = new API\ProductCatalog\Products\Update\Request( $facebook_product_id, $data );
		$this->set_response_handler( API\ProductCatalog\Products\Update\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * Deletes a Product Item object.
	 *
	 * @param string $facebook_product_id Facebook Product ID.
	 * @return API\Response|API\ProductCatalog\Products\Delete\Response
	 * @throws ApiException In case of network request error.
	 */
	public function delete_product_item( string $facebook_product_id ): API\ProductCatalog\Products\Delete\Response {
		$request = new API\ProductCatalog\Products\Delete\Request( $facebook_product_id );
		$this->set_response_handler( API\ProductCatalog\Products\Delete\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * Returns product Facebook ID and Facebook Group ID.
	 *
	 * @param string $facebook_product_catalog_id
	 * @param string $facebook_retailer_id
	 * @return API\Response|API\ProductCatalog\Products\Id\Response
	 * @throws ApiException In case of network request error.
	 * @throws API\Exceptions\Request_Limit_Reached
	 */
	public function get_product_facebook_ids( string $facebook_product_catalog_id, string $facebook_retailer_id ): API\ProductCatalog\Products\Id\Response {
		$request = new API\ProductCatalog\Products\Id\Request( $facebook_product_catalog_id, $facebook_retailer_id );
		$this->set_response_handler( API\ProductCatalog\Products\Id\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * @param string $product_catalog_id
	 * @param array $data
	 * @return API\Response|API\ProductCatalog\ProductSets\Create\Response
	 * @throws ApiException
	 * @throws API\Exceptions\Request_Limit_Reached
	 */
	public function create_product_set_item( string $product_catalog_id, array $data ): API\ProductCatalog\ProductSets\Create\Response {
		$request = new API\ProductCatalog\ProductSets\Create\Request( $product_catalog_id, $data );
		$this->set_response_handler( API\ProductCatalog\ProductSets\Create\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * @param string $product_set_id
	 * @param array $data
	 * @return API\Response|API\ProductCatalog\ProductSets\Update\Response
	 * @throws ApiException
	 * @throws API\Exceptions\Request_Limit_Reached
	 */
	public function update_product_set_item( string $product_set_id, array $data ): API\ProductCatalog\ProductSets\Update\Response {
		$request = new API\ProductCatalog\ProductSets\Update\Request( $product_set_id, $data );
		$this->set_response_handler( API\ProductCatalog\ProductSets\Update\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * @param string $product_set_id Facebook Product Set ID.
	 * @param bool   $allow_live_deletion Allow live Facebook Product Set Deletion.
	 * @return API\Response|API\ProductCatalog\ProductSets\Delete\Response
	 * @throws ApiException
	 * @throws API\Exceptions\Request_Limit_Reached
	 */
	public function delete_product_set_item( string $product_set_id, bool $allow_live_deletion ): API\ProductCatalog\ProductSets\Delete\Response {
		$request = new API\ProductCatalog\ProductSets\Delete\Request( $product_set_id, $allow_live_deletion );
		$this->set_response_handler( API\ProductCatalog\ProductSets\Delete\Response::class );
		return $this->perform_request( $request );
	}

	/**
	 * @param string $product_catalog_id
	 * @return API\Response|API\ProductCatalog\ProductFeeds\ReadAll\Response
	 * @throws ApiException
	 * @throws API\Exceptions\Request_Limit_Reached
	 */
	public function read_feeds( string $product_catalog_id ): API\ProductCatalog\ProductFeeds\ReadAll\Response {
		$request = new API\ProductCatalog\ProductFeeds\ReadAll\Request( $product_catalog_id );
		$this->set_response_handler( API\ProductCatalog\ProductFeeds\ReadAll\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * @param string $product_feed_id Facebook Product Feed ID.
	 * @return Response
	 * @throws ApiException
	 * @throws API\Exceptions\Request_Limit_Reached
	 */
	public function read_feed( string $product_feed_id ) {
		$request = new API\ProductCatalog\ProductFeeds\Read\Request( $product_feed_id );
		$this->set_response_handler( API\ProductCatalog\ProductFeeds\Read\Response::class );
		return $this->perform_request( $request );
	}

	/**
	 * @param string $product_catalog_id Facebook Product Catalog ID.
	 * @return Response
	 * @throws ApiException
	 * @throws API\Exceptions\Request_Limit_Reached
	 */
	public function create_feed( string $product_catalog_id, array $data ) {
		$request = new API\ProductCatalog\ProductFeeds\Create\Request( $product_catalog_id, $data );
		$this->set_response_handler( API\ProductCatalog\ProductFeeds\Create\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * @param string $product_feed_upload_id
	 * @return Response
	 * @throws ApiException
	 * @throws API\Exceptions\Request_Limit_Reached
	 */
	public function read_upload( string $product_feed_upload_id ) {
		$request = new API\ProductCatalog\ProductFeedUploads\Read\Request( $product_feed_upload_id );
		$this->set_response_handler( API\ProductCatalog\ProductFeedUploads\Read\Response::class );
		return $this->perform_request( $request );
	}

	/**
	 * @param string $product_feed_id Facebook Product Feed ID.
	 * @return Response
	 * @throws ApiException
	 * @throws API\Exceptions\Request_Limit_Reached
	 */
	public function create_upload( string $product_feed_id, array $data ) {
		$request = new API\ProductCatalog\ProductFeedUploads\Create\Request( $product_feed_id, $data );
		$this->set_response_handler( API\ProductCatalog\ProductFeedUploads\Create\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * @param string $external_merchant_settings_id
	 * @return API\Response|API\Tip\Read\Response
	 * @throws ApiException
	 * @throws API\Exceptions\Request_Limit_Reached
	 */
	public function get_tip_info( string $external_merchant_settings_id ): API\Tip\Read\Response {
		$request = new API\Tip\Read\Request( $external_merchant_settings_id );
		$this->set_response_handler( API\Tip\Read\Response::class );
		return $this->perform_request( $request );
	}


	public function log_tip_event( $tip_id, $channel_id, $event ) {
		$request = new API\Tip\Log\Request( $tip_id, $channel_id, $event );
		$this->set_response_handler( API\Tip\Log\Response::class );
		return $this->perform_request( $request );
	}


	public function log( $facebook_external_merchant_settings_id, $message, $error ) {
		$request = new API\Log\Create\Request( $facebook_external_merchant_settings_id, $message, $error );
		$this->set_response_handler( API\Log\Create\Response::class );
		return $this->perform_request( $request );
	}

	/**
	 * Sends Pixel events.
	 *
	 * @since 2.0.0
	 *
	 * @param string  $pixel_id pixel ID
	 * @param Event[] $events events to send
	 * @return Response
	 * @throws ApiException
	 */
	public function send_pixel_events( $pixel_id, array $events ) {
		$request = new API\Pixel\Events\Request( $pixel_id, $events );
		$this->set_response_handler( Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * Gets the next page of results for a paginated response.
	 *
	 * @since 2.0.0
	 *
	 * @param API\Response $response previous response object
	 * @param int          $additional_pages number of additional pages of results to retrieve
	 * @return API\Response|null
	 * @throws ApiException
	 */
	public function next( API\Response $response, int $additional_pages = 0 ) {
		$next_response = null;
		// get the next page if we haven't reached the limit of pages to retrieve and the endpoint for the next page is available
		if ( ( 0 === $additional_pages || $response->get_pages_retrieved() <= $additional_pages ) && $response->get_next_page_endpoint() ) {
			$components = parse_url( str_replace( $this->request_uri, '', $response->get_next_page_endpoint() ) );
			$request = $this->get_new_request(
				[
					'path'   => $components['path'] ?? '',
					'method' => 'GET',
					'params' => isset( $components['query'] ) ? wp_parse_args( $components['query'] ) : [],
				]
			);
			$this->set_response_handler( get_class( $response ) );
			$next_response = $this->perform_request( $request );
			// this is the n + 1 page of results for the original response
			$next_response->set_pages_retrieved( $response->get_pages_retrieved() + 1 );
		}
		return $next_response;
	}


	/**
	 * Returns a new request object.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args {
	 *     Optional. An array of request arguments.
	 *
	 *     @type string $path request path
	 *     @type string $method request method
	 *     @type array $params request parameters
	 * }
	 * @return Request
	 */
	protected function get_new_request( $args = [] ) {
		$defaults = array(
			'path'   => '/',
			'method' => 'GET',
			'params' => [],
		);
		$args    = wp_parse_args( $args, $defaults );
		$request = new Request( $args['path'], $args['method'] );
		if ( $args['params'] ) {
			$request->set_params( $args['params'] );
		}
		return $request;
	}


	/**
	 * Returns the plugin class instance associated with this API.
	 *
	 * @since 2.0.0
	 *
	 * @return \WC_Facebookcommerce
	 */
	protected function get_plugin() {
		return facebook_for_woocommerce();
	}
}
