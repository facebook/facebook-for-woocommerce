<?php
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

try {
	if ( ! function_exists( 'facebook_for_woocommerce' ) ) {
		throw new Exception( 'Facebook plugin not loaded' );
	}

	$handler        = facebook_for_woocommerce()->get_products_sync_background_handler();
	$jobs_processed = 0;

	while ( true ) {
		$job = $handler->get_job();
		if ( ! $job ) {
			break;
		}

		$handler->process_job( $job );
		$jobs_processed++;
	}

	echo json_encode( [
		'success'        => true,
		'jobs_processed' => $jobs_processed,
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
