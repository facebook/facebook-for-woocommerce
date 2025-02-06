<?php

namespace WooCommerce\Facebook\Feed;

defined( 'ABSPATH' ) || exit;

use Exception;

/**
 * Responsible detecting feed configuration changes and reporting it to the Tracker.
 */
abstract class FeedInfoToTracker {

	/**
	 * Constructor.
	 *
	 * @param string $heartbeat_hook_name The interval to run the heartbeat by name.
	 */
	public function __construct( string $heartbeat_hook_name ) {
		add_action( $heartbeat_hook_name, array( $this, 'get_data_source_feed_tracker_info' ) );
	}

	/**
	 * Store config settings for feed-based sync for WooCommerce Tracker.
	 *
	 * Gets various settings related to the feed, and data about recent uploads.
	 * This is formatted into an array of keys/values, and saved to a transient for inclusion in tracker snapshot.
	 * Note this does not send the data to tracker - this happens later (see Tracker class).
	 *
	 * @return void
	 * @since 2.6.0
	 */
	public function track_data_source_feed_tracker_info() {
		try {
			$info = $this->get_data_source_feed_tracker_info();
			facebook_for_woocommerce()->get_tracker()->track_facebook_feed_config( $info );
		} catch ( Exception $error ) {
			facebook_for_woocommerce()->log( 'Unable to detect valid feed configuration: ' . $error->getMessage() );
		}
	}

	/**
	 * Get data needed to set transient in the global Tracker class.
	 */
	abstract protected function get_data_source_feed_tracker_info();
}
