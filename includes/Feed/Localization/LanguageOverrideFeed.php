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
 * Language Override Feed handler.
 *
 * Extends AbstractFeed to be compatible with FeedManager while providing
 * specialized functionality for language override feeds.
 *
 * @since 3.6.0
 */
class LanguageOverrideFeed extends AbstractFeed {

	use LanguageFeedManagementTrait;

	/** @var string the feed name for creating a new feed by this plugin */
	const FEED_NAME_TEMPLATE = '%s Language Override Feed (%s)';

	/** @var \WooCommerce\Facebook\Feed\Localization\LanguageFeedData */
	private $language_feed_data;

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
	 * @since 3.6.0
	 */
	public function __construct() {
		$this->language_feed_data = new LanguageFeedData();

		// Check if we have an active localization plugin before proceeding
		if ( ! $this->language_feed_data->has_active_localization_plugin() ) {
			// No localization plugin active - create a minimal setup to prevent errors
			$default_language = 'en_US'; // Fallback language
			$header_row = $this->language_feed_data->get_csv_header_for_columns(['id', 'override']);
			$this->feed_writer = new LanguageOverrideFeedWriter( $default_language, $header_row );
			$this->feed_handler = new LanguageOverrideFeedHandler( $this->language_feed_data, $this->feed_writer );

			// Create a basic feed generator for compatibility
			$action_scheduler = new \Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionScheduler();
			$feed_generator = new LanguageOverrideFeedGenerator(
				$action_scheduler,
				$this->feed_writer,
				static::get_data_stream_name(),
				$this->language_feed_data,
				$default_language
			);

			// Initialize parent class with proper components
			$this->init( $this->feed_writer, $this->feed_handler, $feed_generator );
			return;
		}

		// Get the default language from the active localization plugin
		$default_language = $this->language_feed_data->get_default_language();

		// Ensure we have a valid language code
		if ( empty( $default_language ) ) {
			// Plugin is active but misconfigured - use fallback for initialization
			$default_language = 'en_US';
		}

		$header_row = $this->language_feed_data->get_csv_header_for_columns(['id', 'override']);
		$this->feed_writer = new LanguageOverrideFeedWriter( $default_language, $header_row );

		$this->feed_handler = new LanguageOverrideFeedHandler( $this->language_feed_data, $this->feed_writer );

		// Create a basic feed generator for compatibility
		$action_scheduler = new \Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionScheduler();
		$feed_generator = new LanguageOverrideFeedGenerator(
			$action_scheduler,
			$this->feed_writer,
			static::get_data_stream_name(),
			$this->language_feed_data,
			$default_language
		);

		// Initialize parent class with proper components
		$this->init( $this->feed_writer, $this->feed_handler, $feed_generator );
	}

	/**
	 * Adds the necessary hooks for feed generation and data request handling.
	 *
	 * @since 3.6.0
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
	 * @since 3.6.0
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
			$scheduling_lock_key = 'wc_facebook_language_feed_scheduling_' . static::get_data_stream_name();

			if ( get_transient( $scheduling_lock_key ) ) {
				Logger::log(
					'Language override feed scheduling skipped: Already in progress.',
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
					'Language override feed generation scheduled successfully.',
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
	 * @since 3.6.0
	 */
	public function unschedule_feed_generation(): void {
		$schedule_action_hook_name = self::GENERATE_FEED_ACTION . static::get_data_stream_name();

		// Unschedule all actions for this feed type
		as_unschedule_all_actions( $schedule_action_hook_name );

		Logger::log(
			'Language override feed generation unscheduled.',
			[],
			array(
				'should_send_log_to_meta'        => false,
				'should_save_log_in_woocommerce' => true,
				'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
			)
		);
	}

	/**
	 * Regenerates the language override feeds based on the defined schedule.
	 *
	 * @since 3.6.0
	 */
	public function regenerate_feed(): void {
		if ( $this->should_skip_feed() ) {
			return;
		}

		$this->feed_handler->queue_start();
	}

	/**
	 * Trigger the upload flow
	 * Once feed regenerated, trigger upload via create_upload API
	 * This will hit the url defined in the class and trigger handle_feed_data_request
	 *
	 * @since 3.6.0
	 */
	public function send_request_to_upload_feed(): void {
		$this->upload_language_override_feeds();
	}


	/**
	 * Gets the secret value that should be included in the legacy WooCommerce REST API URL.
	 *
	 * @return string
	 * @since 3.6.0
	 */
	public function get_feed_secret(): string {
		$secret_option_name = self::OPTION_FEED_URL_SECRET . static::get_data_stream_name();

		$secret = get_option( $secret_option_name, '' );
		if ( ! $secret ) {
			$secret = wp_hash( 'language-override-feed-' . time() );
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
	 * @since 3.6.0
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
	 * @since 3.6.0
	 * @return \WooCommerce\Facebook\Feed\Localization\LanguageOverrideFeedHandler
	 */
	public function get_feed_handler() {
		return $this->feed_handler;
	}

	/**
	 * Get the data stream name for language override feeds.
	 *
	 * @return string
	 */
	protected static function get_data_stream_name(): string {
		return 'language_override';
	}

	/**
	 * Get the data feed type for language override feeds.
	 *
	 * @return string
	 */
	protected static function get_feed_type(): string {
		return 'LANGUAGE_OVERRIDE';
	}

	/**
	 * Override the feed generation interval to be less frequent than product feeds.
	 * Language content doesn't change as often as product data.
	 *
	 * @return int
	 */
	protected static function get_feed_gen_interval(): int {
		/**
		 * Filters the frequency with which the language override feed data is generated.
		 *
		 * @since 3.6.0
		 *
		 * @param int $interval the frequency with which the language override feed data is generated, in seconds.
		 */
		return apply_filters( 'wc_facebook_language_override_feed_generation_interval', DAY_IN_SECONDS * 7 ); // Weekly by default
	}

	/**
	 * Check if feed generation should be skipped.
	 *
	 * @return bool
	 */
	public function should_skip_feed(): bool {
		$connection_handler = facebook_for_woocommerce()->get_connection_handler();
		$cpi_id             = $connection_handler->get_commerce_partner_integration_id();
		$cms_id             = $connection_handler->get_commerce_merchant_settings_id();

		if ( empty( $cpi_id ) || empty( $cms_id ) ) {
			return true;
		}

		// Skip if no active localization plugin
		if ( ! $this->language_feed_data->has_active_localization_plugin() ) {
			Logger::log(
				'Language override feed generation skipped: No active localization plugin found.',
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
	 * Override handle_feed_data_request to add language parameter handling.
	 * This mirrors Feed.php's handle_feed_data_request but adds language support.
	 *
	 * @throws PluginException If the feed secret is invalid, file is not readable, or other errors occur.
	 */
	public function handle_feed_data_request(): void {
		Logger::log(
			'Facebook is requesting a language override feed.',
			[],
			array(
				'should_send_log_to_meta'        => false,
				'should_save_log_in_woocommerce' => true,
				'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
			)
		);

		try {
			// Get the language code from the request
			$language_code = Helper::get_requested_value( 'language' );
			if ( empty( $language_code ) ) {
				throw new PluginException( 'Language code is required.', 400 );
			}

			// Validate the feed secret
			if ( $this->get_feed_secret() !== Helper::get_requested_value( 'secret' ) ) {
				throw new PluginException( 'Invalid feed secret provided.', 401 );
			}

			// Create a language-specific feed writer to get the correct file path
			$header_row = $this->language_feed_data->get_csv_header_for_columns(['id', 'override']);
			$language_feed_writer = new LanguageOverrideFeedWriter( $language_code, $header_row );
			$file_path = $language_feed_writer->get_file_path( $language_code );

			// Regenerate if the file doesn't exist
			if ( ! empty( $_GET['regenerate'] ) || ! file_exists( $file_path ) ) {
				$this->feed_handler->write_language_feed_file( $language_code );
			}

			// Check if the file can be read
			if ( ! is_readable( $file_path ) ) {
				throw new PluginException( 'Language feed file is not readable.', 404 );
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
				throw new PluginException( 'Could not open language feed file.', 500 );
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
					throw new PluginException( 'Could not get language feed file contents.', 500 );
				}
				echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			// Close the file handle
			if ( $file ) {
				fclose( $file );
			}

		} catch ( \Exception $exception ) {
			Logger::log(
				'Could not serve language override feed. ' . $exception->getMessage() . ' (' . $exception->getCode() . ')',
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
	 * Override get_feed_data_url to add language parameter.
	 * This mirrors Feed.php's get_feed_data_url but adds language support.
	 *
	 * @param string $language_code Language code
	 * @return string
	 */
	public function get_language_feed_url( string $language_code ): string {
		$query_args = array(
			'wc-api' => self::REQUEST_FEED_ACTION . '_' . static::get_data_stream_name(),
			'language' => $language_code,
			'secret' => $this->get_feed_secret(),
		);

		return add_query_arg( $query_args, home_url( '/' ) );
	}

	/**
	 * Upload language override feeds to Facebook for all available languages.
	 * This mirrors Feed.php's send_request_to_upload_feed but handles multiple languages.
	 *
	 * @since 3.6.0
	 */
	public function upload_language_override_feeds() {
		if ( ! $this->language_feed_data->has_active_localization_plugin() ) {
			return;
		}

		$languages = $this->language_feed_data->get_available_languages();

		foreach ( $languages as $language_code ) {
			$this->upload_single_language_feed( $language_code );
		}
	}

	/**
	 * Upload a single language override feed to Facebook.
	 * This mirrors Feed.php's send_request_to_upload_feed but for a specific language.
	 *
	 * @param string $language_code Language code (e.g., 'es_ES', 'fr_FR')
	 * @since 3.6.0
	 */
	private function upload_single_language_feed( string $language_code ) {
		try {
			// Step 1: Create or get the language override feed configuration using trait method
			$feed_id = $this->retrieve_or_create_language_feed_id( $language_code );

			if ( empty( $feed_id ) ) {
				throw new \Exception( 'Could not create or retrieve language override feed ID' );
			}

			// Step 2: Generate the CSV file for this language
			$this->feed_handler->write_language_feed_file( $language_code );

			// Step 3: Tell Facebook to fetch the CSV data from our endpoint (like main product feed does)
			$data = [
				'url' => $this->get_language_feed_url( $language_code ),
			];

			$this->get_api()->create_product_feed_upload( $feed_id, $data );

			Logger::log(
				'Language override feed uploaded successfully.',
				array(
					'event'      => 'language_feed_upload',
					'event_type' => 'upload_single_language_feed',
					'extra_data' => [
						'language_code' => $language_code,
						'feed_id' => $feed_id,
						'feed_url' => $this->get_language_feed_url( $language_code ),
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
				'Language override feed upload failed.',
				array(
					'event'      => 'language_feed_upload',
					'event_type' => 'upload_single_language_feed',
					'extra_data' => [
						'language_code' => $language_code,
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
