<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API\Exceptions\Request_Limit_Reached;
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

	public const API_VERSION = 'v21.0';

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
		$this->access_token    = $access_token;
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
	 * @throws API\Exceptions\Request_Limit_Reached|ApiException In case of a general API error or rate limit error.
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
	 * @throws ApiException In case of an invalid token error.
	 * @throws API\Exceptions\Request_Limit_Reached In case of a rate limit error.
	 */
	protected function do_post_parse_response_validation() {
		/** @var API\Response $response */
		$response = $this->get_response();
		$request  = $this->get_request();
		if ( $response && $response->has_api_error() ) {
			$code = $response->get_api_error_code();
			// phpcs:ignore Universal.Operators.DisallowShortTernary.Found
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
	 * @throws API\Exceptions\Request_Limit_Reached In case of a rate limit error.
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
	 * @throws ApiException In case of a general API error or rate limit error.
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
	 * @throws ApiException In case of a general API error or rate limit error.
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
	 * @throws ApiException In case of a general API error or rate limit error.
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
	 * @throws ApiException In case of a general API error or rate limit error.
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
	 * @throws ApiException In case of a general API error or rate limit error.
	 */
	public function delete_mbe_connection( string $external_business_id ): API\FBE\Installation\Delete\Response {
		$request = new API\FBE\Installation\Delete\Request( $external_business_id );
		$this->set_response_handler( API\FBE\Installation\Delete\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * Gets the business configuration.
	 *
	 * @param string $external_business_id external business ID
	 * @param string $access_token Optional access token to use for this request. If not provided, will use the instance token.
	 * @param array  $fields Optional. Fields to request from the API. Default empty array returns all fields.
	 * @return API\Response|API\FBE\Configuration\Read\Response
	 * @throws ApiException In case of a general API error or rate limit error.
	 */
	public function get_business_configuration( $external_business_id, $access_token = '', $fields = [] ) {
		$request = new API\FBE\Configuration\Request( $external_business_id, 'GET' );
		$params  = [];
		// Use provided access token or fall back to the instance token
		if ( ! empty( $access_token ) ) {
			$params['access_token'] = $access_token;
		}
		// Add fields parameter if specified
		if ( ! empty( $fields ) ) {
			$params['fields'] = is_array( $fields ) ? implode( ',', $fields ) : $fields;
		}
		// Set parameters if we have any
		if ( ! empty( $params ) ) {
			$request->set_params( $params );
		}
		$this->set_response_handler( API\FBE\Configuration\Read\Response::class );
		return $this->perform_request( $request );
	}

	/**
	 * Gets rollout switches
	 *
	 * @param string $external_business_id
	 * @return API\FBE\RolloutSwitches\Response
	 * @throws ApiException In case of a general API error or rate limit error.
	 */
	public function get_rollout_switches( string $external_business_id ) {
		if ( ! $this->get_access_token() ) {
			return null;
		}

		$request = new API\FBE\RolloutSwitches\Request( $external_business_id );
		$request->set_params(
			array(
				'access_token'             => $this->get_access_token(),
				'fbe_external_business_id' => $external_business_id,
			)
		);
		$this->set_response_handler( API\FBE\RolloutSwitches\Response::class );
		return $this->perform_request( $request );
	}

	/**
	 * Updates the plugin version configuration.
	 *
	 * @param string $external_business_id external business ID
	 * @param bool   $is_opted_out The plugin version.
	 * @param string $plugin_version The plugin version.
	 * @return Response|API\FBE\Configuration\Update\Response
	 * @throws ApiException In case of a general API error or rate limit error.
	 */
	public function update_plugin_version_configuration( string $external_business_id, bool $is_opted_out, string $plugin_version ): API\FBE\Configuration\Update\Response {
		$request = new API\FBE\Configuration\Update\Request( $external_business_id );
		$request->set_external_client_metadata(
			array(
				'version_id'                    => $plugin_version,
				'is_multisite'                  => is_multisite(),
				'is_woo_all_products_opted_out' => $is_opted_out,
			)
		);
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
	 * @throws ApiException In case of a general API error or rate limit error.
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
	 * @throws ApiException In case of a general API error or rate limit error.
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
	 * @throws ApiException In case of a general API error or rate limit error.
	 */
	public function update_product_group( string $product_group_id, array $data ): API\ProductCatalog\ProductGroups\Update\Response {
		$request = new API\ProductCatalog\ProductGroups\Update\Request( $product_group_id, $data );
		$this->set_response_handler( API\ProductCatalog\ProductGroups\Update\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * Gets a list of Product Items in the given Product Group.
	 *
	 * @param string $product_group_id product group ID
	 * @param int    $limit max number of results returned per page of data
	 * @return API\Response|API\ProductCatalog\ProductGroups\Read\Response
	 * @throws ApiException In case of a general API error or rate limit error.
	 */
	public function get_product_group_products( string $product_group_id, int $limit = 1000 ): API\ProductCatalog\ProductGroups\Read\Response {
		$request = new API\ProductCatalog\ProductGroups\Read\Request( $product_group_id, $limit );
		$this->set_response_handler( API\ProductCatalog\ProductGroups\Read\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * Creates a Product under the specified Product Group.
	 *
	 * @since 3.4.9
	 *
	 * @param string $product_catalog_id Facebook Product Catalog ID.
	 * @param array  $data Facebook Product Data.
	 * @return API\Response|API\ProductCatalog\Products\Create\Response
	 * @throws ApiException In case of network request error.
	 */
	public function create_product_item( string $product_catalog_id, array $data ): API\ProductCatalog\Products\Create\Response {
		$request = new API\ProductCatalog\Products\Create\Request( $product_catalog_id, $data );
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
	 * Returns product Facebook ID and Facebook Group ID.
	 *
	 * @param string $facebook_product_catalog_id
	 * @param string $facebook_retailer_id
	 * @return API\Response|API\ProductCatalog\Products\Id\Response
	 * @throws ApiException In case of network request error.
	 * @throws API\Exceptions\Request_Limit_Reached In case of rate limit error.
	 */
	public function get_product_facebook_ids( string $facebook_product_catalog_id, string $facebook_retailer_id ): API\ProductCatalog\Products\Id\Response {
		$request = new API\ProductCatalog\Products\Id\Request( $facebook_product_catalog_id, $facebook_retailer_id );
		$this->set_response_handler( API\ProductCatalog\Products\Id\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * Returns requested fields from Facebook for the given product.
	 *
	 * @param string $facebook_product_catalog_id
	 * @param string $facebook_retailer_id
	 * @param string $fields_string Comma-separated string of fields to request from Facebook API.
	 * @return API\Response|API\ProductCatalog\Products\Id\Response
	 * @throws ApiException In case of network request error.
	 * @throws API\Exceptions\Request_Limit_Reached In case of rate limit error.
	 */
	public function get_product_facebook_fields( string $facebook_product_catalog_id, string $facebook_retailer_id, string $fields_string = 'id,product_group{id}' ): API\ProductCatalog\Products\Id\Response {
		$request = new API\ProductCatalog\Products\Id\Request( $facebook_product_catalog_id, $facebook_retailer_id, $fields_string );
		$this->set_response_handler( API\ProductCatalog\Products\Id\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * @param string $product_catalog_id
	 * @param array  $data
	 * @return API\Response|API\ProductCatalog\ProductSets\Create\Response
	 * @throws ApiException In case of network request error.
	 * @throws API\Exceptions\Request_Limit_Reached In case of rate limit error.
	 */
	public function create_product_set_item( string $product_catalog_id, array $data ): API\ProductCatalog\ProductSets\Create\Response {
		$request = new API\ProductCatalog\ProductSets\Create\Request( $product_catalog_id, $data );
		$this->set_response_handler( API\ProductCatalog\ProductSets\Create\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * @param string $product_set_id
	 * @param array  $data
	 * @return API\Response|API\ProductCatalog\ProductSets\Update\Response
	 * @throws ApiException In case of network request error.
	 * @throws API\Exceptions\Request_Limit_Reached In case of rate limit error.
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
	 * @throws ApiException In case of network request error.
	 * @throws API\Exceptions\Request_Limit_Reached In case of rate limit error.
	 */
	public function delete_product_set_item( string $product_set_id, bool $allow_live_deletion ): API\ProductCatalog\ProductSets\Delete\Response {
		$request = new API\ProductCatalog\ProductSets\Delete\Request( $product_set_id, $allow_live_deletion );
		$this->set_response_handler( API\ProductCatalog\ProductSets\Delete\Response::class );
		return $this->perform_request( $request );
	}

	/**
	 * @param string $product_catalog_id Facebook Product Catalog ID.
	 * @param string $retailer_id Facebook Product Set Retailer ID.
	 * @return API\Response|API\ProductCatalog\ProductSets\Read\Response
	 * @throws ApiException In case of network request error.
	 * @throws API\Exceptions\Request_Limit_Reached In case of rate limit error.
	 */
	public function read_product_set_item( string $product_catalog_id, string $retailer_id ): API\ProductCatalog\ProductSets\Read\Response {
		$request = new API\ProductCatalog\ProductSets\Read\Request( $product_catalog_id, $retailer_id );
		$this->set_response_handler( API\ProductCatalog\ProductSets\Read\Response::class );
		return $this->perform_request( $request );
	}

	/**
	 * @param string $product_catalog_id
	 * @return API\Response|API\ProductCatalog\ProductFeeds\ReadAll\Response
	 * @throws ApiException In case of network request error.
	 * @throws API\Exceptions\Request_Limit_Reached In case of rate limit error.
	 */
	public function read_feeds( string $product_catalog_id ): API\ProductCatalog\ProductFeeds\ReadAll\Response {
		$request = new API\ProductCatalog\ProductFeeds\ReadAll\Request( $product_catalog_id );
		$this->set_response_handler( API\ProductCatalog\ProductFeeds\ReadAll\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * @param string $product_feed_id Facebook Product Feed ID.
	 * @return Response
	 * @throws ApiException In case of network request error.
	 * @throws API\Exceptions\Request_Limit_Reached In case of rate limit error.
	 */
	public function read_feed( string $product_feed_id ) {
		$request = new API\ProductCatalog\ProductFeeds\Read\Request( $product_feed_id );
		$this->set_response_handler( API\ProductCatalog\ProductFeeds\Read\Response::class );
		return $this->perform_request( $request );
	}

	/**
	 * @param string $product_catalog_id Facebook Product Catalog ID.
	 * @param array  $data Product Feed Data.
	 * @return Response
	 * @throws ApiException In case of network request error.
	 * @throws API\Exceptions\Request_Limit_Reached In case of rate limit error.
	 */
	public function create_feed( string $product_catalog_id, array $data ) {
		$request = new API\ProductCatalog\ProductFeeds\Create\Request( $product_catalog_id, $data );
		$this->set_response_handler( API\ProductCatalog\ProductFeeds\Create\Response::class );
		return $this->perform_request( $request );
	}


	/**
	 * @param string $product_feed_upload_id
	 * @return Response
	 * @throws ApiException In case of network request error.
	 * @throws API\Exceptions\Request_Limit_Reached In case of rate limit error.
	 */
	public function read_upload( string $product_feed_upload_id ) {
		$request = new API\ProductCatalog\ProductFeedUploads\Read\Request( $product_feed_upload_id );
		$this->set_response_handler( API\ProductCatalog\ProductFeedUploads\Read\Response::class );
		return $this->perform_request( $request );
	}

	/**
	 * @param string $product_feed_id Facebook Product Feed ID.
	 * @param array  $data Product Feed Data.
	 * @return Response
	 * @throws ApiException In case of network request error.
	 * @throws API\Exceptions\Request_Limit_Reached In case of rate limit error.
	 */
	public function create_product_feed_upload( string $product_feed_id, array $data ): Response {
		$request = new API\ProductCatalog\ProductFeedUploads\Create\Request( $product_feed_id, $data );
		$this->set_response_handler( API\ProductCatalog\ProductFeedUploads\Create\Response::class );
		return $this->perform_request( $request );
	}

	/**
	 * @param string $cpi_id The commerce partner integration id.
	 * @param array  $data The json body for the Generic Feed Upload endpoint.
	 *
	 * @return Response
	 * @throws Request_Limit_Reached In case of rate limit error.
	 * @throws ApiException In case of network request error.
	 */
	public function create_common_data_feed_upload( string $cpi_id, array $data ): Response {
		$request = new API\CommonFeedUploads\Create\Request( $cpi_id, $data );
		$this->set_response_handler( API\CommonFeedUploads\Create\Response::class );
		return $this->perform_request( $request );
	}

	public function log_to_meta( $context ) {
		$request = new API\MetaLog\Request( $context );
		$this->set_response_handler( API\MetaLog\Response::class );
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
	 * @throws ApiException In case of a general API error or rate limit error.
	 */
	public function send_pixel_events( $pixel_id, array $events ) {
		$request = new API\Pixel\Events\Request( $pixel_id, $events );
		$this->set_response_handler( Response::class );
		return $this->perform_request( $request );
	}

	/**
	 * @param string $key_project The key project.
	 * @return Response
	 * @throws ApiException In case of network request error.
	 * @throws API\Exceptions\Request_Limit_Reached In case of rate limit error.
	 */
	public function get_public_key( string $key_project ): Response {
		$request = new API\PublicKeyGet\Request( $key_project );
		$this->set_response_handler( API\Response::class );
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
	 * @throws ApiException In case of a general API error or rate limit error.
	 */
	public function next( API\Response $response, int $additional_pages = 0 ) {
		$next_response = null;
		// get the next page if we haven't reached the limit of pages to retrieve and the endpoint for the next page is available
		if ( ( 0 === $additional_pages || $response->get_pages_retrieved() <= $additional_pages ) && $response->get_next_page_endpoint() ) {
			$components = parse_url( str_replace( $this->request_uri, '', $response->get_next_page_endpoint() ) );
			$request    = $this->get_new_request(
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
		$args     = wp_parse_args( $args, $defaults );
		$request  = new Request( $args['path'], $args['method'] );
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

	/**
	 * Repairs the commerce integration connection.
	 *
	 * @param string $fbe_external_business_id The external business ID associated with the Facebook Business Extension
	 * @param string $shop_domain The domain of the WooCommerce site
	 * @param string $admin_url The admin URL of the WooCommerce site
	 * @param string $extension_version The version of the Facebook for WooCommerce extension
	 *
	 * @return API\Response|API\CommerceIntegration\Repair\Response
	 * @throws ApiException In case of a general API error or rate limit error.
	 */
	public function repair_commerce_integration( string $fbe_external_business_id, string $shop_domain, string $admin_url, string $extension_version ): API\CommerceIntegration\Repair\Response {
		$request = new API\CommerceIntegration\Repair\RepairRequest( $fbe_external_business_id, $shop_domain, $admin_url, $extension_version );
		$this->set_response_handler( API\CommerceIntegration\Repair\Response::class );
		return $this->perform_request( $request );
	}

	/**
	 * Updates the commerce integration configuration.
	 *
	 * @param string      $commerce_integration_id The ID of the commerce integration to update
	 * @param string|null $extension_version The version of the Facebook for WooCommerce extension
	 * @param string|null $admin_url The admin URL of the WooCommerce site
	 * @param string|null $country_code ISO2 country code
	 * @param string|null $currency ISO currency code
	 * @param string|null $platform_store_id The ID of the current website on a multisite setup
	 * @param string      $commerce_partner_seller_platform_type The type of commerce partner platform
	 * @param string      $installation_status The installation status of the integration
	 * @return API\Response|API\CommerceIntegration\Configuration\Update\Response
	 * @throws ApiException In case of a general API error or rate limit error.
	 */
	public function update_commerce_integration(
		string $commerce_integration_id,
		?string $extension_version = null,
		?string $admin_url = null,
		?string $country_code = null,
		?string $currency = null,
		?string $platform_store_id = null,
		string $commerce_partner_seller_platform_type = 'SELF_SERVE',
		string $installation_status = 'ACCESS_TOKEN_DEPOSITED'
	): API\CommerceIntegration\Configuration\Update\Response {
		$request = new API\CommerceIntegration\Configuration\Update\UpdateRequest(
			$commerce_integration_id,
			$extension_version,
			$admin_url,
			$country_code,
			$currency,
			$platform_store_id,
			$commerce_partner_seller_platform_type,
			$installation_status
		);
		$this->set_response_handler( API\CommerceIntegration\Configuration\Update\Response::class );
		return $this->perform_request( $request );
	}
}
