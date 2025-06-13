<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Utilities;

defined( 'ABSPATH' ) || exit;

/**
 * Class for adding diagnostic info to WooCommerce Tracker snapshot.
 *
 * See https://woocommerce.com/usage-tracking/ for more information.
 *
 * @since 2.3.4
 */
class Tracker {

	/**
	 * Life time for transients used for temporary caching of values we want to add to tracker snapshot.
	 *
	 * @var string
	 */
	public const TRANSIENT_WCTRACKER_LIFE_TIME = 2 * WEEK_IN_SECONDS;

	/**
	 * Transient key name; how long it took to generate the most recent feed file, or minus one if it failed.
	 *
	 * @var string
	 */
	public const TRANSIENT_WCTRACKER_FEED_GENERATION_TIME = 'facebook_for_woocommerce_wctracker_feed_generation_time';

	/**
	 * Transient key name; how long it took to generate already generated batches.
	 *
	 * @var string
	 */
	public const TRANSIENT_WCTRACKER_FEED_GENERATION_BATCH_TIME = 'facebook_for_woocommerce_wctracker_feed_generation_batch_time';

	/**
	 * Transient key name; how much wall ( clock ) time it took to generate the feed file.
	 *
	 * @var string
	 */
	public const TRANSIENT_WCTRACKER_FEED_GENERATION_BATCH_WALL_TIME = 'facebook_for_woocommerce_wctracker_feed_generation_batch_wall_time';

	/**
	 * Transient key name; the time when was the batched feed generation started.
	 *
	 * @var string
	 */
	public const TRANSIENT_WCTRACKER_FEED_GENERATION_BATCH_WALL_START_TIME = 'facebook_for_woocommerce_wctracker_feed_generation_batch_wall_time';

	/**
	 * Transient key name; true if feed has been requested by Facebook.
	 *
	 * @var string
	 */
	public const TRANSIENT_WCTRACKER_FEED_REQUESTED = 'facebook_for_woocommerce_wctracker_feed_requested';

	/**
	 * Transient key name; stores various FBE business settings.
	 *
	 * @var string
	 */
	public const TRANSIENT_WCTRACKER_FBE_BUSINESS_CONFIG = 'facebook_for_woocommerce_wctracker_fbe_business_config';

	/**
	 * Transient key name; stores feed (data source) settings for catalog sync.
	 *
	 * @var string
	 */
	public const TRANSIENT_WCTRACKER_FB_FEED_CONFIG = 'facebook_for_woocommerce_wctracker_fb_feed_config';

	/**
	 * Constructor.
	 *
	 * @since 2.3.4
	 */
	public function __construct() {
		add_filter(
			'woocommerce_tracker_data',
			[ $this, 'add_tracker_data' ]
		);
	}

	/**
	 * Append our tracker properties.
	 *
	 * @param array $data The current tracker snapshot data.
	 * @return array $data Snapshot updated with our data.
	 * @since 2.3.4
	 */
	public function add_tracker_data( array $data = [] ) {
		if ( ! isset( $data['extensions'] ) ) {
			$data['extensions'] = [];
		}

		/**
		 * Is the site connected?
		 *
		 * @since 2.3.4
		 */
		$connection_is_happy = false;
		$connection_handler  = facebook_for_woocommerce()->get_connection_handler();
		if ( $connection_handler ) {
			$connection_is_happy = $connection_handler->is_connected() && ! get_transient( 'wc_facebook_connection_invalid' );
		}
		$data['extensions']['facebook-for-woocommerce']['is-connected'] = wc_bool_to_string( $connection_is_happy );

		/**
		 * What features are enabled on this site?
		 *
		 * @since 2.3.4
		 */
		$product_sync_enabled = facebook_for_woocommerce()->get_integration()->is_product_sync_enabled();
		$data['extensions']['facebook-for-woocommerce']['product-sync-enabled'] = wc_bool_to_string( $product_sync_enabled );

		/**
		 * How long did the last feed generation take (or did it fail - 0)? This counts just the time when the batches have been generated.
		 * It does not take into account the time between the batches.
		 *
		 * @since 2.6.0
		 */
		$feed_generation_time = get_transient( self::TRANSIENT_WCTRACKER_FEED_GENERATION_TIME );
		$data['extensions']['facebook-for-woocommerce']['feed-generation-time'] = floatval( $feed_generation_time );

		/**
		 * How long did the last feed generation take in wall time. This is the whole duration. Batches plus time in between.
		 *
		 * @since 2.6.6
		 */
		if ( facebook_for_woocommerce()->get_integration()->is_new_style_feed_generation_enabled() ) {
			$feed_generation_batch_wall_time = intval( get_transient( self::TRANSIENT_WCTRACKER_FEED_GENERATION_BATCH_WALL_TIME ) );
		} else {
			// For old feed generator this is the same thing $feed_generation_time because the process is done in one step.
			$feed_generation_batch_wall_time = intval( $feed_generation_time );
		}
		$data['extensions']['facebook-for-woocommerce']['feed-generation-wall-time'] = $feed_generation_batch_wall_time;

		/**
		 * Has the feed file been requested since the last snapshot?
		 *
		 * @since 2.6.0
		 */
		$feed_file_requested = get_transient( self::TRANSIENT_WCTRACKER_FEED_REQUESTED );
		$data['extensions']['facebook-for-woocommerce']['feed-file-requested'] = wc_bool_to_string( $feed_file_requested );
		// Manually delete the transient. This prop tracks if feed has been requested _since last snapshot_.
		delete_transient( self::TRANSIENT_WCTRACKER_FEED_REQUESTED );

		/**
		 * Miscellaneous Facebook config settings.
		 *
		 * @since 2.6.0
		 */
		$config = get_transient( self::TRANSIENT_WCTRACKER_FBE_BUSINESS_CONFIG );
		$data['extensions']['facebook-for-woocommerce']['instagram-shopping-enabled'] = wc_bool_to_string( $config ? $config['ig_shopping_enabled'] : false );
		$data['extensions']['facebook-for-woocommerce']['instagram-cta-enabled']      = wc_bool_to_string( $config ? $config['ig_cta_enabled'] : false );

		/**
		 * Feed pull / upload settings configured in Facebook UI.
		 *
		 * @since 2.6.0
		 */
		$data['extensions']['facebook-for-woocommerce']['product-feed-config'] = get_transient( self::TRANSIENT_WCTRACKER_FB_FEED_CONFIG );

		/**
		 * Detect if the user has enabled the new experimental feed generator feature.
		 *
		 * @since 2.6.6
		 */
		$data['extensions']['facebook-for-woocommerce']['new-feed-generator-enabled'] = wc_bool_to_string( facebook_for_woocommerce()->get_integration()->is_new_style_feed_generation_enabled() );

		return $data;
	}

	/**
	 * Update transient with feed file generation time.
	 *
	 * Note this is used to clear the transient (set to -1) to track feed generation failure.
	 *
	 * @param float $time_in_seconds Time taken to generate feed file (in seconds).
	 * @since 2.6.0
	 */
	public function track_feed_file_generation_time( $time_in_seconds ) {
		set_transient( self::TRANSIENT_WCTRACKER_FEED_GENERATION_TIME, $time_in_seconds, self::TRANSIENT_WCTRACKER_LIFE_TIME );
	}

	/**
	 * Reset  the feed generation in batch time counter.
	 *
	 * @since 2.6.6
	 */
	public function reset_batch_generation_time() {
		// Reset the main counter.
		$this->track_feed_file_generation_time( -1 );
		set_transient( self::TRANSIENT_WCTRACKER_FEED_GENERATION_BATCH_TIME, 0, self::TRANSIENT_WCTRACKER_LIFE_TIME );
		set_transient( self::TRANSIENT_WCTRACKER_FEED_GENERATION_BATCH_WALL_TIME, 0, self::TRANSIENT_WCTRACKER_LIFE_TIME );
		set_transient( self::TRANSIENT_WCTRACKER_FEED_GENERATION_BATCH_WALL_START_TIME, time(), self::TRANSIENT_WCTRACKER_LIFE_TIME );
	}

	/**
	 * Add time to batch feed_generation_time.
	 * This accumulates time over all of the calculated feed batches.
	 *
	 * @param float $time_in_seconds Time to add to the generation time(in seconds).
	 * @since 2.6.6
	 */
	public function increment_batch_generation_time( $time_in_seconds ) {
		$tracked_generation_time = floatval( get_transient( self::TRANSIENT_WCTRACKER_FEED_GENERATION_BATCH_TIME ) );
		set_transient(
			self::TRANSIENT_WCTRACKER_FEED_GENERATION_BATCH_TIME,
			floatval( $time_in_seconds ) + $tracked_generation_time,
			self::TRANSIENT_WCTRACKER_LIFE_TIME
		);
	}

	/**
	 * Save batch generation time.
	 *
	 * This is the last step in batch feed generation time tracking.
	 * The accumulated time value is copied to the main transient
	 * that is later used by the tracking code to track feed generation time.
	 *
	 * @since 2.6.6
	 */
	public function save_batch_generation_time() {
		$this->track_feed_file_generation_time(
			get_transient( self::TRANSIENT_WCTRACKER_FEED_GENERATION_BATCH_TIME )
		);

		$start = intval( get_transient( self::TRANSIENT_WCTRACKER_FEED_GENERATION_BATCH_WALL_START_TIME ) );
		$end   = time();
		set_transient(
			self::TRANSIENT_WCTRACKER_FEED_GENERATION_BATCH_WALL_TIME,
			$end - $start,
			self::TRANSIENT_WCTRACKER_LIFE_TIME
		);
	}

	/**
	 * Store the fact that the feed has been requested by Facebook in a transient.
	 * This will later be added to next tracker snapshot.
	 *
	 * @since 2.6.0
	 */
	public function track_feed_file_requested() {
		set_transient( self::TRANSIENT_WCTRACKER_FEED_REQUESTED, true, self::TRANSIENT_WCTRACKER_LIFE_TIME );
	}

	/**
	 * Store some Facebook config settings for tracking.
	 *
	 * @param bool $ig_shopping_enabled True if Instagram Shopping is configured.
	 * @param bool $ig_cta_enabled True if `ig_cta` config option is enabled.
	 * @since 2.6.0
	 */
	public function track_facebook_business_config(
		bool $ig_shopping_enabled,
		bool $ig_cta_enabled
	) {
		$transient = [
			'ig_shopping_enabled' => $ig_shopping_enabled,
			'ig_cta_enabled'      => $ig_cta_enabled,
		];
		set_transient( self::TRANSIENT_WCTRACKER_FBE_BUSINESS_CONFIG, $transient, self::TRANSIENT_WCTRACKER_LIFE_TIME );
	}

	/**
	 * Store Facebook feed config for tracking.
	 *
	 * @param array $feed_settings Key-value array of settings to add to tracker snapshot.
	 * @since 2.6.0
	 */
	public function track_facebook_feed_config(
		array $feed_settings
	) {
		set_transient( self::TRANSIENT_WCTRACKER_FB_FEED_CONFIG, $feed_settings, self::TRANSIENT_WCTRACKER_LIFE_TIME );
	}
}
