<?php

namespace WooCommerce\Facebook\Promotions;

use WooCommerce\Facebook\Feed\FeedInfoToTracker;

/**
 * Responsible detecting feed configuration changes and reporting it to the Tracker.
 */
class PromotionsFeedInfoToTracker extends FeedInfoToTracker {

	/**
	 * Get data needed to set transient in the global Tracker class.
	 */
	protected function get_data_source_feed_tracker_info() {
		// TODO: Implement get_data_source_feed_tracker_info() method.
	}
}
