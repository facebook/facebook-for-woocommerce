<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Feed;

/**
 * Responsible for creating and managing feeds.
 * Global manipulations of the feed such as updating feed and upload ID to be made through this class.
 * Add feed type names as constant strings here.
 *
 * @since 3.5.0
 */
class FeedManager {
	const PROMOTIONS          = 'promotions';
	const RATINGS_AND_REVIEWS = 'ratings_and_reviews';
	const SHIPPING_PROFILES   = 'shipping_profiles';
	const NAVIGATION_MENU     = 'navigation_menu';
	const LANGUAGE_OVERRIDE   = 'language_override';

	/**
	 * The map of feed types to their instances.
	 *
	 * @var array<string, AbstractFeed> The map of feed types to their instances.
	 * @since 3.5.0
	 */
	private array $feed_instances = array();

	/**
	 * FeedManager constructor.
	 * Instantiates all the registered feed types and keeps in map.
	 *
	 * @since 3.5.0
	 */
	public function __construct() {
		foreach ( $this->get_active_feed_types() as $feed_type ) {
			$this->feed_instances[ $feed_type ] = $this->create_feed( $feed_type );
		}
	}

	/**
	 * Create a feed based on the data stream name.
	 *
	 * @param string $data_stream_name The name of the data stream.
	 *
	 * @return AbstractFeed The created feed instance derived from AbstractFeed.
	 * @throws \InvalidArgumentException If the data stream doesn't correspond to a FeedType.
	 * @since 3.5.0
	 */
	private function create_feed( string $data_stream_name ): AbstractFeed {
		switch ( $data_stream_name ) {
			case self::PROMOTIONS:
				return new PromotionsFeed();
			case self::RATINGS_AND_REVIEWS:
				return new RatingsAndReviewsFeed();
			case self::SHIPPING_PROFILES:
				return new ShippingProfilesFeed();
			case self::NAVIGATION_MENU:
				return new NavigationMenuFeed();
			case self::LANGUAGE_OVERRIDE:
				return new Localization\LanguageOverrideFeed();
			default:
				throw new \InvalidArgumentException( "Invalid feed type {$data_stream_name}" );
		}
	}

	/**
	 * Get the list of feed types.
	 *
	 * @return array
	 * @since 3.5.0
	 */
	public static function get_active_feed_types(): array {
		return array( self::PROMOTIONS, self::RATINGS_AND_REVIEWS, self::SHIPPING_PROFILES, self::NAVIGATION_MENU, self::LANGUAGE_OVERRIDE );
	}

	/**
	 * Get specific feed instance.
	 *
	 * @param string $feed_type The name of the feed type instance to fetch.
	 *
	 * @return AbstractFeed
	 * @throws \InvalidArgumentException If the feed_type isn't set.
	 * @since 3.5.0
	 */
	public function get_feed_instance( string $feed_type ): AbstractFeed {
		if ( ! isset( $this->feed_instances[ $feed_type ] ) ) {
			throw new \InvalidArgumentException( "Feed type {$feed_type} does not exist." );
		}
		return $this->feed_instances[ $feed_type ];
	}

	/**
	 * Run all feed uploads.
	 *
	 * @return void
	 * @since 3.5.0
	 */
	public function run_all_feed_uploads(): void {
		foreach ( $this->feed_instances as $feed_type ) {
			$feed_type->regenerate_feed();
		}
	}

	/**
	 * Upload language override feeds to Facebook
	 *
	 * @return void
	 * @since 3.6.0
	 */
	public function upload_language_override_feeds(): void {
		// Prevent double registration by checking if upload is already in progress
		$upload_lock_key = 'wc_facebook_language_feed_upload_in_progress';

		if ( get_transient( $upload_lock_key ) ) {
			error_log( 'Language override feed upload skipped: Already in progress.' );
			return;
		}

		// Set lock for 15 minutes (upload can take time)
		set_transient( $upload_lock_key, true, 15 * MINUTE_IN_SECONDS );

		try {
			// Use the new modular language override feed class
			$language_feed = facebook_for_woocommerce()->get_language_override_feed();

			if ( ! $language_feed ) {
				error_log( 'Language override feed upload skipped: Feed instance not available.' );
				return;
			}

			// Generate the feed files first
			$language_feed->regenerate_feed();

			// Upload will be triggered automatically via the feed generation completion hook
		} finally {
			// Always clear the lock when done
			delete_transient( $upload_lock_key );
		}
	}

	/**
	 * Get the feed instance for the given feed type.
	 *
	 * @param string $feed_type the specific feed in question.
	 *
	 * @return string
	 * @since 3.5.0
	 */
	public function get_feed_secret( string $feed_type ): string {
		$instance = $this->feed_instances[ $feed_type ];

		return $instance->get_feed_secret();
	}
}
