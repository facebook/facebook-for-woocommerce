<?php

namespace WooCommerce\Facebook\Feed;

interface FeedConfigurationDetectorForTracker {

	/**
	 * Store config settings for feed-based sync for WooCommerce Tracker.
	 *
	 * @return void
	 * @since 2.6.0
	 */
	public function track_data_source_feed_tracker_info();
}
