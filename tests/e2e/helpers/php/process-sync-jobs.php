<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * E2E Helper — Process pending Facebook sync background jobs directly.
 *
 * The background job handler dispatches via a loopback HTTP request to
 * admin-ajax.php, which fails on single-threaded PHP servers (like the
 * built-in dev server used in CI). This script bypasses the loopback by
 * invoking the job handler directly.
 *
 * Usage: php process-sync-jobs.php
 */

// Simulate cron context so is_queue_empty() doesn't bail early.
define( 'DOING_CRON', true );

$wp_path = getenv( 'WORDPRESS_PATH' ) . '/wp-load.php';

if ( ! file_exists( $wp_path ) ) {
	echo json_encode( [ 'success' => false, 'error' => 'WordPress not found at: ' . $wp_path ] );
	exit( 1 );
}

require_once $wp_path;

/**
 * Waits until Meta has finished processing all submitted Catalog Batch API handles.
 *
 * @param string[] $handles Batch handles returned by /items_batch.
 * @return array<int, array<string, mixed>> Sanitized final batch statuses.
 * @throws Exception When a batch fails, contains invalid requests, or times out.
 */
function wait_for_meta_batch_completion( array $handles ) {
	$handles = array_values( array_unique( array_filter( $handles ) ) );
	if ( empty( $handles ) ) {
		return [];
	}

	$integration = facebook_for_woocommerce()->get_integration();
	$catalog_id  = $integration ? $integration->get_product_catalog_id() : '';
	$api          = facebook_for_woocommerce()->get_api();
	$access_token = $api ? $api->get_access_token() : '';

	if ( empty( $catalog_id ) || empty( $access_token ) ) {
		throw new Exception( 'Facebook catalog connection is not configured for batch status checks' );
	}

	$pending_handles = array_fill_keys( $handles, true );
	$final_statuses  = [];
	$deadline        = microtime( true ) + 300;

	while ( ! empty( $pending_handles ) ) {
		foreach ( array_keys( $pending_handles ) as $handle ) {
			$url = add_query_arg(
				[
					'handle'                       => $handle,
					'fields'                       => 'status,errors_total_count,warnings_total_count,ids_of_invalid_requests',
					'load_ids_of_invalid_requests' => 'true',
				],
				\WooCommerce\Facebook\API::GRAPH_API_URL . \WooCommerce\Facebook\API::API_VERSION . "/{$catalog_id}/check_batch_request_status"
			);

			$response = wp_remote_get(
				$url,
				[
					'headers' => [ 'Authorization' => "Bearer {$access_token}" ],
					'timeout' => 30,
				]
			);

			if ( is_wp_error( $response ) ) {
				throw new Exception( 'Meta batch status request failed: ' . $response->get_error_message() );
			}

			$http_code = wp_remote_retrieve_response_code( $response );
			$body      = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( 200 !== $http_code ) {
				$message = $body['error']['message'] ?? "HTTP {$http_code}";
				throw new Exception( 'Meta batch status request failed: ' . $message );
			}

			$data          = $body['data'][0] ?? $body;
			$status        = strtolower( (string) ( $data['status'] ?? '' ) );
			$error_count   = (int) ( $data['errors_total_count'] ?? 0 );
			$invalid_count = count( (array) ( $data['ids_of_invalid_requests'] ?? [] ) );

			if ( in_array( $status, [ 'failed', 'error' ], true ) ) {
				throw new Exception( "Meta catalog batch ended with status {$status}" );
			}

			if ( 'finished' === $status ) {
				if ( $error_count > 0 || $invalid_count > 0 ) {
					throw new Exception( "Meta catalog batch finished with {$error_count} error(s) and {$invalid_count} invalid request(s)" );
				}

				$final_statuses[] = [
					'status'                => $status,
					'errors_total_count'    => $error_count,
					'invalid_request_count' => $invalid_count,
				];
				unset( $pending_handles[ $handle ] );
			}
		}

		if ( empty( $pending_handles ) ) {
			break;
		}

		if ( microtime( true ) >= $deadline ) {
			throw new Exception( 'Timed out waiting for Meta to finish catalog batches' );
		}

		sleep( 5 );
	}

	return $final_statuses;
}

try {
	if ( ! function_exists( 'facebook_for_woocommerce' ) ) {
		throw new Exception( 'Facebook plugin not loaded' );
	}

	$handler        = facebook_for_woocommerce()->get_products_sync_background_handler();
	$jobs_processed = 0;
	$batch_handles  = [];

	while ( true ) {
		$job = $handler->get_job();
		if ( ! $job ) {
			break;
		}

		$processed_job = $handler->process_job( $job );
		if ( ! empty( $processed_job->handles ) && is_array( $processed_job->handles ) ) {
			$batch_handles = array_merge( $batch_handles, $processed_job->handles );
		}
		$jobs_processed++;
	}

	$batch_handles  = array_values( array_unique( $batch_handles ) );
	$batch_statuses = '1' === getenv( 'FB_E2E_WAIT_FOR_BATCH_COMPLETION' )
		? wait_for_meta_batch_completion( $batch_handles )
		: [];

	echo json_encode( [
		'success'        => true,
		'jobs_processed' => $jobs_processed,
		'batch_count'    => count( $batch_handles ),
		'batch_statuses' => $batch_statuses,
		'message'        => $jobs_processed > 0
			? "Processed {$jobs_processed} job(s)"
			: 'No pending jobs found',
	] );

} catch ( Exception $e ) {
	echo json_encode( [
		'success' => false,
		'error'   => $e->getMessage(),
	] );
	exit( 1 );
}
