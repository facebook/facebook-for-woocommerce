<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Feed\Localization;

defined( 'ABSPATH' ) || exit;

use Exception;
use WooCommerce\Facebook\Framework\Helper;
use WooCommerce\Facebook\Framework\Plugin\Exception as PluginException;
use WooCommerce\Facebook\Framework\Logger;
use WooCommerce\Facebook\Utilities\Heartbeat;
use WooCommerce\Facebook\Feed\AbstractFeed;

/**
 * Country Override Feed handler.
 *
 * Extends AbstractFeed to be compatible with FeedManager while providing
 * specialized functionality for country override feeds.
 *
 * @since 3.0.18
 */
class CountryOverrideFeed extends AbstractFeed {

	use CountryFeedManagementTrait;

	/** @var string the feed name for creating a new feed by this plugin */
	const FEED_NAME_TEMPLATE = '%s Country Override Feed (%s)';

	/** @var \WooCommerce\Facebook\Feed\Localization\CountryFeedData */
	private $country_feed_data;

	/** @var \WooCommerce\Facebook\API */
	private $api;

	/** Action constants */
	const GENERATE_FEED_ACTION = 'wc_facebook_regenerate_feed_';
	const REQUEST_FEED_ACTION = 'wc_facebook_get_feed_data';
	const FEED_GEN_COMPLETE_ACTION = 'wc_facebook_feed_generation_completed_';
	const LEGACY_API_PREFIX = 'woocommerce_api_';
	const OPTION_FEED_URL_SECRET = 'wc_facebook_feed_url_secret_';

	/**
	 * Constructor
	 *
	 * @since 3.0.18
	 */
	public function __construct() {
		$this->country_feed_data = new CountryFeedData();

		// Check if we have country feeds available before proceeding
		if ( ! $this->country_feed_data->is_available() ) {
			// No country feeds available - create a minimal setup to prevent errors
			$default_country = 'US'; // Fallback country
			$header_row = $this->country_feed_data->get_csv_header_for_columns(['id', 'override', 'price']);
			$this->feed_writer = new CountryOverrideFeedWriter( $default_country, $header_row );
			$this->feed_handler = new CountryOverrideFeedHandler( $this->country_feed_data, $this->feed_writer );

			// Create a basic feed generator for compatibility
			$action_scheduler = new \Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionScheduler();
			$feed_generator = new CountryOverrideFeedGenerator(
				$action_scheduler,
				$this->feed_writer,
				static::get_data_stream_name(),
				$this->country_feed_data,
				$default_country
			);

			// Initialize parent class with proper components
			$this->init( $this->feed_writer, $this->feed_handler, $feed_generator );
			return;
		}

		// Get the first viable country for initialization
		$viable_countries = $this->country_feed_data->get_countries_for_override_feeds();
		$default_country = ! empty( $viable_countries ) ? $viable_countries[0] : 'US';

		$header_row = $this->country_feed_data->get_csv_header_for_columns(['id', 'override', 'price']);
		$this->feed_writer = new CountryOverrideFeedWriter( $default_country, $header_row );

		$this->feed_handler = new CountryOverrideFeedHandler( $this->country_feed_data, $this->feed_writer );

		// Create a basic feed generator for compatibility
		$action_scheduler = new \Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionScheduler();
		$feed_generator = new CountryOverrideFeedGenerator(
			$action_scheduler,
			$this->feed_writer,
			static::get_data_stream_name(),
			$this->country_feed_data,
			$default_country
		);

		// Initialize parent class with proper components
		$this->init( $this->feed_writer, $this->feed_handler, $feed_generator );

		// Add hooks for scheduling (like main Feed.php does)
		$this->add_hooks();
	}

	/**
	 * Adds the necessary hooks for feed generation and data request handling.
	 *
	 * @since 3.0.18
	 */
	protected function add_hooks(): void {
		add_action( static::get_feed_gen_scheduling_interval(), array( $this, 'schedule_feed_generation' ) );
		add_action( self::GENERATE_FEED_ACTION . static::get_data_stream_name(), array( $this, 'regenerate_feed' ) );
		add_action( self::FEED_GEN_COMPLETE_ACTION . static::get_data_stream_name(), array( $this, 'send_request_to_upload_feed' ) );
		add_action(
			self::LEGACY_API_PREFIX . self::REQUEST_FEED_ACTION . '_' . static::get_data_stream_name(),
			array(
				$this,
				'handle_feed_data_request',
			)
		);
	}

	/**
	 * Schedules the recurring feed generation.
	 *
	 * @since 3.0.18
	 */
	public function schedule_feed_generation(): void {
		if ( $this->should_skip_feed() ) {
			// Unschedule any existing actions if we should skip
			$this->unschedule_feed_generation();
			return;
		}

		$schedule_action_hook_name = self::GENERATE_FEED_ACTION . static::get_data_stream_name();

		// Prevent double registration by checking for existing scheduled actions
		if ( ! as_next_scheduled_action( $schedule_action_hook_name ) ) {
			// Use a transient to prevent race conditions during scheduling
			$scheduling_lock_key = 'wc_facebook_country_feed_scheduling_' . static::get_data_stream_name();

			if ( get_transient( $scheduling_lock_key ) ) {
				Logger::log(
					'Country override feed scheduling skipped: Already in progress.',
					[],
					array(
						'should_send_log_to_meta'        => false,
						'should_save_log_in_woocommerce' => true,
						'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
					)
				);
				return;
			}

			// Set lock for 5 minutes
			set_transient( $scheduling_lock_key, true, 5 * MINUTE_IN_SECONDS );

			try {
				as_schedule_recurring_action(
					time(),
					static::get_feed_gen_interval(),
					$schedule_action_hook_name,
					array(),
					facebook_for_woocommerce()->get_id_dasherized()
				);

				Logger::log(
					'Country override feed generation scheduled successfully.',
					[],
					array(
						'should_send_log_to_meta'        => false,
						'should_save_log_in_woocommerce' => true,
						'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
					)
				);
			} finally {
				// Always clear the lock
				delete_transient( $scheduling_lock_key );
			}
		}
	}

	/**
	 * Unschedules the recurring feed generation.
	 *
	 * @since 3.0.18
	 */
	public function unschedule_feed_generation(): void {
		$schedule_action_hook_name = self::GENERATE_FEED_ACTION . static::get_data_stream_name();

		// Unschedule all actions for this feed type
		as_unschedule_all_actions( $schedule_action_hook_name );

		Logger::log(
			'Country override feed generation unscheduled.',
			[],
			array(
				'should_send_log_to_meta'        => false,
				'should_save_log_in_woocommerce' => true,
				'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
			)
		);
	}

	/**
	 * Regenerates the country override feeds based on the defined schedule.
	 * Uses the standard AbstractFeed pattern with FeedGenerator for batched processing.
	 *
	 * @since 3.0.18
	 */
	public function regenerate_feed(): void {
		if ( $this->should_skip_feed() ) {
			return;
		}

		// Use the standard AbstractFeed pattern - let the generator handle batched processing
		$this->feed_generator->queue_start();
	}

	/**
	 * Trigger the upload flow
	 * Once feed regenerated, trigger upload via create_upload API
	 * This will hit the url defined in the class and trigger handle_feed_data_request
	 *
	 * @since 3.0.18
	 */
	public function send_request_to_upload_feed(): void {
		$this->upload_country_override_feeds();
	}

	/**
	 * Gets the secret value that should be included in the legacy WooCommerce REST API URL.
	 *
	 * @return string
	 * @since 3.0.18
	 */
	public function get_feed_secret(): string {
		$secret_option_name = self::OPTION_FEED_URL_SECRET . static::get_data_stream_name();

		$secret = get_option( $secret_option_name, '' );
		if ( ! $secret ) {
			$secret = wp_hash( 'country-override-feed-' . time() );
			update_option( $secret_option_name, $secret );
		}

		return $secret;
	}

	/**
	 * Get the Heartbeat interval to ensure that feed gen is scheduled. Must be shorter than the feed gen interval.
	 *
	 * @return string Heartbeat constant value
	 */
	protected static function get_feed_gen_scheduling_interval(): string {
		return Heartbeat::HOURLY;
	}

	/**
	 * Gets the API instance.
	 *
	 * @since 3.0.18
	 * @return \WooCommerce\Facebook\API
	 */
	private function get_api() {
		if ( ! $this->api ) {
			$this->api = facebook_for_woocommerce()->get_api();
		}
		return $this->api;
	}

	/**
	 * Gets the feed handler instance.
	 *
	 * @since 3.0.18
	 * @return \WooCommerce\Facebook\Feed\Localization\CountryOverrideFeedHandler
	 */
	public function get_feed_handler() {
		return $this->feed_handler;
	}

	/**
	 * Get the data stream name for country override feeds.
	 *
	 * @return string
	 */
	protected static function get_data_stream_name(): string {
		return 'country_override';
	}

	/**
	 * Get the data feed type for country override feeds.
	 *
	 * @return string
	 */
	protected static function get_feed_type(): string {
		return 'COUNTRY_OVERRIDE';
	}

	/**
	 * Override the feed generation interval to be less frequent than product feeds.
	 * Country content doesn't change as often as product data.
	 *
	 * @return int
	 */
	protected static function get_feed_gen_interval(): int {
		/**
		 * Filters the frequency with which the country override feed data is generated.
		 *
		 * @since 3.0.18
		 *
		 * @param int $interval the frequency with which the country override feed data is generated, in seconds.
		 */
		return apply_filters( 'wc_facebook_country_override_feed_generation_interval', DAY_IN_SECONDS * 7 ); // Weekly by default
	}

	/**
	 * Check if feed generation should be skipped.
	 *
	 * @return bool
	 */
	public function should_skip_feed(): bool {
		$connection_handler = facebook_for_woocommerce()->get_connection_handler();
		$cpi_id             = $connection_handler->get_commerce_partner_integration_id();
		// $cms_id             = $connection_handler->get_commerce_merchant_settings_id();

		if ( empty( $cpi_id )) {
			return true;
		}

		// Skip if country feeds are not available
		if ( ! $this->country_feed_data->is_available() ) {
			Logger::log(
				'Country override feed generation skipped: Country feeds not available.',
				[],
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::INFO,
				)
			);
			return true;
		}

		return false;
	}

	/**
	 * Override handle_feed_data_request to add country parameter handling.
	 * This mirrors Feed.php's handle_feed_data_request but adds country support.
	 *
	 * @throws PluginException If the feed secret is invalid, file is not readable, or other errors occur.
	 */
	public function handle_feed_data_request(): void {
		Logger::log(
			'Facebook is requesting a country override feed.',
			[],
			array(
				'should_send_log_to_meta'        => false,
				'should_save_log_in_woocommerce' => true,
				'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
			)
		);

		try {
			// Get the country code from the request
			$country_code = Helper::get_requested_value( 'country' );
			if ( empty( $country_code ) ) {
				throw new PluginException( 'Country code is required.', 400 );
			}

			// Validate the feed secret
			if ( $this->get_feed_secret() !== Helper::get_requested_value( 'secret' ) ) {
				throw new PluginException( 'Invalid feed secret provided.', 401 );
			}

			// Create a country-specific feed writer to get the correct file path
			$header_row = $this->country_feed_data->get_csv_header_for_columns(['id', 'override', 'price']);
			$country_feed_writer = new CountryOverrideFeedWriter( $country_code, $header_row );
			$file_path = $country_feed_writer->get_file_path( $country_code );

			// Regenerate if the file doesn't exist
			if ( ! empty( $_GET['regenerate'] ) || ! file_exists( $file_path ) ) {
				$this->feed_handler->write_country_feed_file( $country_code );
			}

			// Check if the file can be read
			if ( ! is_readable( $file_path ) ) {
				throw new PluginException( 'Country feed file is not readable.', 404 );
			}

			// Set the download headers
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Description: File Transfer' );
			header( 'Content-Disposition: attachment; filename="' . basename( $file_path ) . '"' );
			header( 'Expires: 0' );
			header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
			header( 'Pragma: public' );
			header( 'Content-Length:' . filesize( $file_path ) );

			$file = @fopen( $file_path, 'rb' );
			if ( ! $file ) {
				throw new PluginException( 'Could not open country feed file.', 500 );
			}

			// fpassthru might be disabled in some hosts (like Flywheel)
			if ( \WC_Facebookcommerce_Utils::is_fpassthru_disabled() || ! @fpassthru( $file ) ) {
				Logger::log(
					'fpassthru is disabled: getting file contents',
					[],
					array(
						'should_send_log_to_meta'        => false,
						'should_save_log_in_woocommerce' => true,
						'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
					)
				);
				$contents = @stream_get_contents( $file );
				if ( ! $contents ) {
					throw new PluginException( 'Could not get country feed file contents.', 500 );
				}
				echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			// Close the file handle
			if ( $file ) {
				fclose( $file );
			}

		} catch ( \Exception $exception ) {
			Logger::log(
				'Could not serve country override feed. ' . $exception->getMessage() . ' (' . $exception->getCode() . ')',
				[],
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
				)
			);
			status_header( $exception->getCode() );
		}

		exit; // Important: Exit to prevent WordPress from adding extra content
	}

	/**
	 * Override get_feed_data_url to add country parameter.
	 * This mirrors Feed.php's get_feed_data_url but adds country support.
	 *
	 * @param string $country_code Country code
	 * @return string
	 */
	public function get_country_feed_url( string $country_code ): string {
		$query_args = array(
			'wc-api' => self::REQUEST_FEED_ACTION . '_' . static::get_data_stream_name(),
			'country' => $country_code,
			'secret' => $this->get_feed_secret(),
		);

		return add_query_arg( $query_args, home_url( '/' ) );
	}

	/**
	 * Upload country override feeds to Facebook for all available countries.
	 * This mirrors Feed.php's send_request_to_upload_feed but handles multiple countries.
	 *
	 * @since 3.0.18
	 */
	public function upload_country_override_feeds() {
		if ( ! $this->country_feed_data->should_generate_country_feeds() ) {
			return;
		}

		$countries = $this->country_feed_data->get_countries_for_override_feeds();

		foreach ( $countries as $country_code ) {
			$this->upload_single_country_feed( $country_code );
		}
	}

	/**
	 * Upload a single country override feed to Facebook.
	 * This mirrors Feed.php's send_request_to_upload_feed but for a specific country.
	 *
	 * @param string $country_code Country code (e.g., 'GB', 'DE')
	 * @since 3.0.18
	 */
	private function upload_single_country_feed( string $country_code ) {
		try {
			// Step 1: Create or get the country override feed configuration using trait method
			$feed_id = $this->retrieve_or_create_country_feed_id( $country_code );

			if ( empty( $feed_id ) ) {
				throw new \Exception( 'Could not create or retrieve country override feed ID' );
			}

			// Step 2: Generate the CSV file for this country
			$this->feed_handler->write_country_feed_file( $country_code );

			// Step 3: Tell Facebook to fetch the CSV data from our endpoint (like main product feed does)
			$data = [
				'url' => $this->get_country_feed_url( $country_code ),
			];

			$this->get_api()->create_product_feed_upload( $feed_id, $data );

			Logger::log(
				'Country override feed uploaded successfully.',
				array(
					'event'      => 'country_feed_upload',
					'event_type' => 'upload_single_country_feed',
					'extra_data' => [
						'country_code' => $country_code,
						'feed_id' => $feed_id,
						'feed_url' => $this->get_country_feed_url( $country_code ),
					],
				),
				array(
					'should_send_log_to_meta'        => true,
					'should_save_log_in_woocommerce' => false,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

		} catch ( \Exception $exception ) {
			Logger::log(
				'Country override feed upload failed.',
				array(
					'event'      => 'country_feed_upload',
					'event_type' => 'upload_single_country_feed',
					'extra_data' => [
						'country_code' => $country_code,
					],
				),
				array(
					'should_send_log_to_meta'        => true,
					'should_save_log_in_woocommerce' => false,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				),
				$exception,
			);
		}
	}
}
