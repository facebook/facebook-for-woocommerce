<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\Jobs;

use WooCommerce\Facebook\Utilities\Heartbeat;

defined( 'ABSPATH' ) || exit;

/**
 * Class CleanupSkyvergeFrameworkJobOptions
 *
 * Responsible for cleaning up old completed and failed background sync jobs from SkyVerge background job system.
 * Each job is represented by a row in wp_options table, and these can accumulate over time.
 *
 * Note - this is closely coupled to the SkyVerge background job system, and is essentially a patch to improve it.
 *
 * @see BackgroundJobHandler
 *
 * @since 2.6.0
 */
class CleanupSkyvergeFrameworkJobOptions {

	/**
	 * Add hooks.
	 */
	public function init() {
		// Register our cleanup routine to run regularly.
		add_action( Heartbeat::DAILY, [ $this, 'clean_up_old_completed_options' ] );
	}

	/**
	 * Delete old completed/failed product sync job rows from options table.
	 *
	 * Logic and database query are adapted from SV_WP_Background_Job_Handler::get_jobs().
	 *
	 * @see BackgroundJobHandler
	 * @see Products\Sync\Background
	 */
	public function clean_up_old_completed_options() {

		$flag_name = '_wc_facebook_for_woocommerce_cleanup_sky_verge';
		if ( 'yes' === get_transient( $flag_name ) ) {
			return;
		}
		set_transient( $flag_name, 'yes', DAY_IN_SECONDS );

		global $wpdb;

		/**
		 * Query notes:
		 * - Matching product sync job only (Products\Sync\Background class).
		 * - Matching "completed" or "failed" status by sniffing json option value.
		 * - Order by lowest id, to delete older rows first.
		 * - Limit number of rows (periodic task will eventually remove all).
		 * Using `get_results` so we can limit number of items; `delete` doesn't allow this.
		 */
		$wpdb->query(
			"DELETE
			FROM {$wpdb->options}
			WHERE option_name LIKE 'wc_facebook_background_product_sync_job_%'
			AND ( option_value LIKE '%\"status\":\"completed\"%' OR option_value LIKE '%\"status\":\"failed\"%' )
			ORDER BY option_id ASC
			LIMIT 500"
		);
	}
}
