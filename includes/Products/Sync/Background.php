<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Products\Sync;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\Framework\Api\Exception as ApiException;
use WooCommerce\Facebook\Framework\Plugin\Exception as PluginException;
use WooCommerce\Facebook\Framework\Utilities\BackgroundJobHandler;
use WooCommerce\Facebook\Products;
use WooCommerce\Facebook\Products\Sync;

/**
 * The background sync handler.
 */
class Background extends BackgroundJobHandler {

	/** @var string async request prefix */
	protected $prefix = 'wc_facebook';

	/** @var string async request action */
	protected $action = 'background_product_sync';

	/** @var string data key */
	protected $data_key = 'requests';

	public function __construct() {
		parent::__construct();

		add_action( 'facebook_sync_poll_handle', array( $this, 'handle_poll_action' ), 10, 1 );
	}

	/**
	 * Processes a job.
	 *
	 * @since 2.0.0
	 *
	 * @param \stdClass|object $job
	 * @param int|null         $items_per_batch number of items to process in a single request (defaults to null for unlimited)
	 * @throws \Exception When job data is incorrect.
	 * @return \stdClass $job
	 */
	public function process_job( $job, $items_per_batch = null ) {
		$profiling_logger = facebook_for_woocommerce()->get_profiling_logger();
		$profiling_logger->start( 'background_product_sync__process_job' );

		if ( ! $this->start_time ) {
			$this->start_time = time();
		}

		// Indicate that the job has started processing
		if ( 'processing' !== $job->status ) {

			$job->status                = 'processing';
			$job->started_processing_at = current_time( 'mysql' );

			$job = $this->update_job( $job );
		}

		$data_key = $this->data_key;

		if ( ! isset( $job->{$data_key} ) ) {
			/* translators: Placeholders: %s - user-friendly error message */
			throw new \Exception( sprintf( __( 'Job data key "%s" not set', 'facebook-for-woocommerce' ), $data_key ) );
		}

		if ( ! is_array( $job->{$data_key} ) ) {
			/* translators: Placeholders: %s - user-friendly error message */
			throw new \Exception( sprintf( __( 'Job data key "%s" is not an array', 'facebook-for-woocommerce' ), $data_key ) );
		}

		$data = $job->{$data_key};

		$job->total = count( $data );

		// progress indicates how many items have been processed, it
		// does NOT indicate the processed item key in any way
		if ( ! isset( $job->progress ) ) {
			$job->progress = 0;
		}

		// skip already processed items
		if ( $job->progress && ! empty( $data ) ) {
			$data = array_slice( $data, $job->progress, null, true );
		}

		// loop over unprocessed items and process them
		if ( ! empty( $data ) ) {
			$this->process_items( $job, $data, (int) $items_per_batch );
		}

		// complete current job
		if ( $job->progress >= count( $job->{$data_key} ) ) {
			$job = $this->complete_job( $job );
		}

		$profiling_logger->stop( 'background_product_sync__process_job' );

		return $job;
	}


	/**
	 * Processes multiple items.
	 *
	 * @since 2.0.0
	 *
	 * @param \stdClass|object $job
	 * @param array            $data
	 * @param int|null         $items_per_batch number of items to process in a single request (defaults to null for unlimited)
	 */
	public function process_items( $job, $data, $items_per_batch = null ) {
		$processed = 0;
		$requests  = [];

		foreach ( $data as $item_id => $method ) {
			try {
				$request = $this->process_item( [ $item_id, $method ], $job );
				if ( $request ) {
					$requests[] = $request;
				}
			} catch ( PluginException $e ) {
				facebook_for_woocommerce()->log( "Background sync error: {$e->getMessage()}" );
			}

			++$processed;
			++$job->progress;
			// update job progress
			$job = $this->update_job( $job );
			// job limits reached
			if ( ( $items_per_batch && $processed >= $items_per_batch ) || $this->time_exceeded() || $this->memory_exceeded() ) {
				break;
			}
		}

		// send item updates to Facebook and update the job with the returned array of batch handles
		if ( ! empty( $requests ) ) {
			try {
				$handles      = $this->send_item_updates( $requests );
				$job->handles = ! isset( $job->handles ) || ! is_array( $job->handles ) ? $handles : array_merge( $job->handles, $handles );
				$this->update_job( $job );
			} catch ( ApiException $e ) {
				/* translators: Placeholders: %1$s - <string  job ID, %2$s - <strong> error message */
				$message = sprintf( __( 'There was an error trying sync products using the Catalog Batch API for job %1$s: %2$s', 'facebook-for-woocommerce' ), $job->id, $e->getMessage() );
				facebook_for_woocommerce()->log( $message );
			}
		}
	}

	/**
	 * Processes a single item.
	 *
	 * @param mixed            $item
	 * @param object|\stdClass $job
	 * @return array|null
	 * @throws PluginException In case of invalid sync request method.
	 */
	public function process_item( $item, $job ) {
		list( $item_id, $method ) = $item;
		if ( ! in_array( $method, [ Sync::ACTION_UPDATE, Sync::ACTION_DELETE ], true ) ) {
			throw new PluginException( "Invalid sync request method: {$method}." );
		}

		if ( Sync::ACTION_UPDATE === $method ) {
			$request = $this->process_item_update( $item_id );
		} else {
			$request = $this->process_item_delete( $item_id );
		}
		return $request;
	}

	/**
	 * Processes an UPDATE sync request for the given product.
	 *
	 * @since 2.0.0
	 *
	 * @param string $prefixed_product_id prefixed product ID
	 * @return array|null
	 * @throws PluginException In case no product was found.
	 */
	private function process_item_update( $prefixed_product_id ) {
		$product_id = (int) str_replace( Sync::PRODUCT_INDEX_PREFIX, '', $prefixed_product_id );
		$product    = wc_get_product( $product_id );

		if ( ! $product instanceof \WC_Product ) {
			throw new PluginException( "No product found with ID equal to {$product_id}." );
		}

		$request = null;
		if ( ! Products::product_should_be_deleted( $product ) && Products::product_should_be_synced( $product ) ) {

			if ( $product->is_type( 'variation' ) ) {
				$product_data = \WC_Facebookcommerce_Utils::prepare_product_variation_data_items_batch( $product );
			} else {
				$product_data = \WC_Facebookcommerce_Utils::prepare_product_data_items_batch( $product );
			}

			// extract the retailer_id
			$retailer_id = $product_data['retailer_id'];

			// NB: Changing this to get items_batch to work
			// retailer_id cannot be included in the data object
			unset( $product_data['retailer_id'] );
			$product_data['id'] = $retailer_id;

			$request = [
				'method' => Sync::ACTION_UPDATE,
				'data'   => $product_data,
			];

			/**
			 * Filters the data that will be included in a UPDATE sync request.
			 *
			 * @since 2.0.0
			 *
			 * @param array $request request data
			 * @param \WC_Product $product product object
			 */
			$request = apply_filters( 'wc_facebook_sync_background_item_update_request', $request, $product );
		}

		return $request;
	}

	/**
	 * Processes a DELETE sync request for the given product.
	 *
	 * @param string $prefixed_retailer_id Product retailer ID.
	 */
	private function process_item_delete( $prefixed_retailer_id ) {
		$retailer_id = str_replace( Sync::PRODUCT_INDEX_PREFIX, '', $prefixed_retailer_id );
		$request     = [
			'data'   => [ 'id' => $retailer_id ],
			'method' => Sync::ACTION_DELETE,
		];

		/**
		 * Filters the data that will be included in a DELETE sync request.
		 *
		 * @since 2.0.0
		 *
		 * @param array $request request data
		 * @param string $retailer prefixed product retailer ID
		 */
		return apply_filters( 'wc_facebook_sync_background_item_delete_request', $request, $prefixed_retailer_id );
	}

	/**
	 * Sends item updates to Facebook.
	 *
	 * @param array $requests Array of JSON objects containing batch requests. Each batch request consists of method and data fields.
	 * @return array An array of handles.
	 * @throws ApiException In case of failed API request.
	 */
	private function send_item_updates( array $requests ): array {
		$facebook_catalog_id = facebook_for_woocommerce()->get_integration()->get_product_catalog_id();
		$response            = facebook_for_woocommerce()->get_api()->send_item_updates( $facebook_catalog_id, $requests );
		$response_handles    = $response->handles;
		$handles             = ( isset( $response_handles ) && is_array( $response_handles ) ) ? $response_handles : array();

		foreach ( $handles as $handle ) {
			set_transient( 'facebook_batch_handle_' . $handle, $requests, 12 * HOUR_IN_SECONDS );

			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( time() + 60, 'facebook_sync_poll_handle', array( $handle ) );
			} else {
				wp_schedule_single_event( time() + 60, 'facebook_sync_poll_handle', array( $handle ) );
			}
		}

		return $handles;
	}


	/**
	 * Poll Facebook API for the batch job status using the handle.
	 *
	 * @param string $handle The batch handle returned by Facebook.
	 * @return array|null Returns status data array on success, or null on failure.
	 */
	public function poll_batch_status( string $handle ): ?array {
		try {
			$catalog_id = facebook_for_woocommerce()->get_integration()->get_product_catalog_id();
			$response = facebook_for_woocommerce()->get_api()->get_batch_status( $catalog_id, $handle );

			$data = $response->get_data();
			return $data;

		} catch ( \Exception $e ) {
			error_log( 'Exception in poll_batch_status: ' . $e->getMessage() );
			facebook_for_woocommerce()->log( 'Error polling batch status for handle ' . $handle . ': ' . $e->getMessage() );
			return null;
		}
	}


	/**
	 * Callback triggered by scheduled action to poll batch status.
	 *
	 * @param string $handle The batch handle to poll.
	 */
	public function handle_poll_action( string $handle ) {

		$status = $this->poll_batch_status( $handle );

		if ( ! $status ) {
			return;
		}

		if ( isset( $status['status'] ) && 'IN_PROGRESS' === $status['status'] ) {
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( time() + 60, 'facebook_sync_poll_handle', array( $handle ) );
			} else {
				wp_schedule_single_event( time() + 60, 'facebook_sync_poll_handle', array( $handle ) );
			}
			return;
		}

		$this->process_batch_status_results( $status, $handle );

		delete_transient( 'facebook_batch_handle_' . $handle );
	}


	/**
	 * Processes the results of a batch status update for product synchronization.
	 *
	 * This method handles the status array returned from a batch operation,
	 * performing any necessary actions based on the results and the provided handle.
	 *
	 * @param array  $status  The status results from the batch operation.
	 * @param string $handle The unique identifier for the batch process.
	 */
	protected function process_batch_status_results( array $status, string $handle ) {

		if ( empty( $status['items'] ) ) {
			return;
		}

		foreach ( $status['items'] as $item ) {
			$external_id = $item['id'] ?? '';
			$result      = $item['result'] ?? array();
			$status_val  = $item['status'] ?? 'UNKNOWN';
		}
	}
}
