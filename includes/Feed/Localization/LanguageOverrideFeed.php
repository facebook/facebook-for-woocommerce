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
	const REQUEST_FEED_ACTION = 'wc_facebook_get_feed_data_language_override';
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
	 * Schedules the recurring feed generation.
	 *
	 * @since 3.6.0
	 */
	public function schedule_feed_generation(): void {
		$flag_name = '_wc_facebook_language_override_schedule_feed_generation';
		if ( 'yes' === get_transient( $flag_name ) ) {
			return;
		}
		set_transient( $flag_name, 'yes', HOUR_IN_SECONDS );

		$integration   = facebook_for_woocommerce()->get_integration();
		$configured_ok = $integration && $integration->is_configured();

		// Only schedule feed job if store has not opted out of product sync.
		$store_allows_sync = ( $configured_ok && $integration->is_product_sync_enabled() ) || $integration->is_woo_all_products_enabled();

		// Only schedule if has not opted out of language override feed generation.
		$store_allows_language_feeds = $configured_ok && $integration->is_language_override_feed_generation_enabled();

		$schedule_action_hook_name = self::GENERATE_FEED_ACTION . static::get_data_stream_name();

		if ( ! $store_allows_sync || ! $store_allows_language_feeds || $this->should_skip_feed() ) {
			as_unschedule_all_actions( $schedule_action_hook_name );

			$message = '';
			if ( ! $configured_ok ) {
				$message = 'Integration not configured.';
			} elseif ( ! $store_allows_language_feeds ) {
				$message = 'Store does not allow language override feeds.';
			} elseif ( ! $store_allows_sync ) {
				$message = 'Store does not allow sync.';
			} elseif ( $this->should_skip_feed() ) {
				$message = 'Prerequisites not met (missing localization plugin or commerce IDs).';
			}

			Logger::log(
				sprintf( 'Language override feed scheduling failed: %s', $message ),
				array(
					'flow_name' => 'language_override_feed',
					'flow_step' => 'schedule_feed_generation',
				),
				array(
					'should_send_log_to_meta'        => true,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::WARNING,
				)
			);
			return;
		}

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
	 * Regenerates language override feeds for all available languages.
	 * This overrides the parent method to generate feeds for multiple languages
	 * in a single scheduled action.
	 *
	 * @since 3.6.0
	 */
	public function regenerate_feed(): void {
		if ( $this->should_skip_feed() ) {
			return;
		}

		Logger::log(
			'Starting language override feed regeneration for all languages',
			[],
			array(
				'should_send_log_to_meta'        => false,
				'should_save_log_in_woocommerce' => true,
				'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
			)
		);

		// Generate feeds for all available languages
		$this->regenerate_all_language_feeds();
	}

	/**
	 * Regenerates language override feeds for all available languages.
	 * Uses the feed handler directly instead of the feed generator to create
	 * multiple language files in a single action.
	 *
	 * @since 3.6.0
	 */
	private function regenerate_all_language_feeds(): void {
		// Check if we have an active localization plugin
		if ( ! $this->language_feed_data->has_active_localization_plugin() ) {
			Logger::log(
				'Language override feed regeneration skipped: No active localization plugin found.',
				[],
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::INFO,
				)
			);
			return;
		}

		// Get all available languages
		$languages = $this->language_feed_data->get_available_languages();

		if ( empty( $languages ) ) {
			Logger::log(
				'Language override feed regeneration skipped: No alternative languages found.',
				[],
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::INFO,
				)
			);
			return;
		}

		$successful_languages = [];
		$failed_languages = [];

		Logger::log(
			sprintf( 'Generating language override feeds for %d languages: %s', count( $languages ), implode( ', ', $languages ) ),
			[ 'languages' => $languages ],
			array(
				'should_send_log_to_meta'        => false,
				'should_save_log_in_woocommerce' => true,
				'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
			)
		);

		// Generate feed file for each language using the feed handler directly
		foreach ( $languages as $language_code ) {
			try {
				Logger::log(
					sprintf( 'Generating language override feed for: %s', $language_code ),
					[ 'language_code' => $language_code ],
					array(
						'should_send_log_to_meta'        => false,
						'should_save_log_in_woocommerce' => true,
						'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
					)
				);

				// Generate the feed file for this language
				$success = $this->feed_handler->write_language_feed_file( $language_code );

				if ( $success ) {
					$successful_languages[] = $language_code;
					Logger::log(
						sprintf( 'Successfully generated language override feed for: %s', $language_code ),
						[ 'language_code' => $language_code ],
						array(
							'should_send_log_to_meta'        => false,
							'should_save_log_in_woocommerce' => true,
							'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
						)
					);
				} else {
					$failed_languages[] = $language_code;
					Logger::log(
						sprintf( 'Failed to generate language override feed for: %s', $language_code ),
						[ 'language_code' => $language_code ],
						array(
							'should_send_log_to_meta'        => true,
							'should_save_log_in_woocommerce' => true,
							'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
						)
					);
				}
			} catch ( \Exception $e ) {
				$failed_languages[] = $language_code;
				Logger::log(
					sprintf( 'Exception while generating language override feed for %s: %s', $language_code, $e->getMessage() ),
					[
						'language_code' => $language_code,
						'exception_message' => $e->getMessage(),
						'exception_trace' => $e->getTraceAsString(),
					],
					array(
						'should_send_log_to_meta'        => true,
						'should_save_log_in_woocommerce' => true,
						'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
					),
					$e
				);
			}
		}

		// Log summary
		Logger::log(
			sprintf(
				'Language override feed regeneration completed. Success: %d (%s), Failed: %d (%s)',
				count( $successful_languages ),
				implode( ', ', $successful_languages ),
				count( $failed_languages ),
				implode( ', ', $failed_languages )
			),
			[
				'successful_languages' => $successful_languages,
				'failed_languages' => $failed_languages,
				'total_languages' => count( $languages ),
			],
			array(
				'should_send_log_to_meta'        => false,
				'should_save_log_in_woocommerce' => true,
				'woocommerce_log_level'          => count( $failed_languages ) > 0 ? \WC_Log_Levels::WARNING : \WC_Log_Levels::INFO,
			)
		);

		// Trigger the upload hook if any languages were successful
		if ( ! empty( $successful_languages ) ) {
			do_action( self::FEED_GEN_COMPLETE_ACTION . static::get_data_stream_name() );
		}
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
	 * Override add_hooks to use the correct REQUEST_FEED_ACTION constant.
	 * This ensures the WooCommerce API hook is registered with the proper action name.
	 *
	 * @since 3.6.0
	 */
	protected function add_hooks(): void {
		add_action( static::get_feed_gen_scheduling_interval(), array( $this, 'schedule_feed_generation' ) );
		add_action( self::GENERATE_FEED_ACTION . static::get_data_stream_name(), array( $this, 'regenerate_feed' ) );
		add_action( self::FEED_GEN_COMPLETE_ACTION . static::get_data_stream_name(), array( $this, 'send_request_to_upload_feed' ) );
		add_action(
			self::LEGACY_API_PREFIX . static::REQUEST_FEED_ACTION,
			array(
				$this,
				'handle_feed_data_request',
			)
		);
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
		// $cms_id             = $connection_handler->get_commerce_merchant_settings_id();

		if ( empty( $cpi_id )) {
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
		// Set up debug info
		$debug_info = array(
			'hook_triggered' => true,
			'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
			'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
			'query_string' => $_SERVER['QUERY_STRING'] ?? 'unknown',
			'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
			'timestamp' => current_time('Y-m-d H:i:s'),
		);

		// Check if this is actually a language override feed request
		$wc_api = Helper::get_requested_value( 'wc-api' );
		if ( $wc_api !== static::REQUEST_FEED_ACTION ) {
			$debug_info['error'] = 'Wrong wc-api action';
			$debug_info['expected_action'] = static::REQUEST_FEED_ACTION;
			$debug_info['received_action'] = $wc_api;
			$this->output_debug_response( $debug_info, 400 );
			return;
		}

		Logger::log(
			'Facebook is requesting a language override feed.',
			$debug_info,
			array(
				'should_send_log_to_meta'        => false,
				'should_save_log_in_woocommerce' => true,
				'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
			)
		);

		try {
			// Get the language code from the request
			$language_code = Helper::get_requested_value( 'language' );
			$debug_info['language_code'] = $language_code;

			if ( empty( $language_code ) ) {
				$debug_info['error'] = 'Language code is required but was not provided';
				$this->output_debug_response( $debug_info, 400 );
				return;
			}

			// Get and validate the feed secret
			$provided_secret = Helper::get_requested_value( 'secret' );
			$expected_secret = $this->get_feed_secret();
			$debug_info['secret_provided'] = !empty($provided_secret);
			$debug_info['secret_valid'] = ($expected_secret === $provided_secret);

			if ( $expected_secret !== $provided_secret ) {
				$debug_info['error'] = 'Invalid feed secret provided';
				$debug_info['expected_secret_length'] = strlen($expected_secret);
				$debug_info['provided_secret_length'] = strlen($provided_secret);
				$this->output_debug_response( $debug_info, 401 );
				return;
			}

			// Check localization plugin
			$debug_info['has_localization_plugin'] = $this->language_feed_data->has_active_localization_plugin();
			if ( ! $debug_info['has_localization_plugin'] ) {
				$debug_info['error'] = 'No active localization plugin found';
				$this->output_debug_response( $debug_info, 500 );
				return;
			}

			// Create a language-specific feed writer to get the correct file path
			try {
				$header_row = $this->language_feed_data->get_csv_header_for_columns(['id', 'override']);
				$language_feed_writer = new LanguageOverrideFeedWriter( $language_code, $header_row );
				$file_path = $language_feed_writer->get_file_path( $language_code );
				$debug_info['file_path'] = $file_path;
				$debug_info['file_exists'] = file_exists( $file_path );
			} catch ( \Exception $e ) {
				$debug_info['error'] = 'Failed to create feed writer or get file path';
				$debug_info['exception_message'] = $e->getMessage();
				$debug_info['exception_trace'] = $e->getTraceAsString();
				$this->output_debug_response( $debug_info, 500 );
				return;
			}

			// Regenerate if the file doesn't exist or if explicitly requested
			if ( ! empty( $_GET['regenerate'] ) || ! file_exists( $file_path ) ) {
				$debug_info['regenerating_file'] = true;

				// Capture detailed file generation steps
				try {
					$file_generation_result = $this->write_language_feed_file_with_debug( $language_code );
					$debug_info['file_generation_steps'] = $file_generation_result['steps'];
					$debug_info['file_regenerated'] = $file_generation_result['success'];
					$debug_info['file_exists_after_regen'] = file_exists( $file_path );

					if ( !$file_generation_result['success'] ) {
						$debug_info['error'] = 'File generation reported failure';
						$debug_info['generation_error'] = $file_generation_result['error'] ?? 'Unknown error';
						$this->output_debug_response( $debug_info, 500 );
						return;
					}
				} catch ( \Exception $e ) {
					$debug_info['error'] = 'Failed to regenerate feed file';
					$debug_info['exception_message'] = $e->getMessage();
					$debug_info['exception_trace'] = $e->getTraceAsString();
					$this->output_debug_response( $debug_info, 500 );
					return;
				}
			}

			// Check if the file can be read
			if ( ! file_exists( $file_path ) ) {
				$debug_info['error'] = 'Language feed file does not exist';
				$this->output_debug_response( $debug_info, 404 );
				return;
			}

			if ( ! is_readable( $file_path ) ) {
				$debug_info['error'] = 'Language feed file is not readable';
				$debug_info['file_permissions'] = substr(sprintf('%o', fileperms($file_path)), -4);
				$this->output_debug_response( $debug_info, 404 );
				return;
			}

			$debug_info['file_size'] = filesize( $file_path );

			// If this is a debug request, return debug info instead of the file
			if ( ! empty( $_GET['debug'] ) ) {
				$debug_info['success'] = true;
				$debug_info['file_first_100_chars'] = substr(file_get_contents($file_path), 0, 100);
				$this->output_debug_response( $debug_info, 200 );
				return;
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
				$debug_info['error'] = 'Could not open language feed file';
				$this->output_debug_response( $debug_info, 500 );
				return;
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
					$debug_info['error'] = 'Could not get language feed file contents';
					fclose( $file );
					$this->output_debug_response( $debug_info, 500 );
					return;
				}
				echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			// Close the file handle
			if ( $file ) {
				fclose( $file );
			}

		} catch ( \Exception $exception ) {
			$debug_info['error'] = 'Unexpected exception occurred';
			$debug_info['exception_message'] = $exception->getMessage();
			$debug_info['exception_code'] = $exception->getCode();
			$debug_info['exception_file'] = $exception->getFile();
			$debug_info['exception_line'] = $exception->getLine();
			$debug_info['exception_trace'] = $exception->getTraceAsString();

			Logger::log(
				'Could not serve language override feed. ' . $exception->getMessage() . ' (' . $exception->getCode() . ')',
				$debug_info,
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
				)
			);

			$this->output_debug_response( $debug_info, $exception->getCode() ?: 500 );
		}

		exit; // Important: Exit to prevent WordPress from adding extra content
	}

	/**
	 * Output debug response with detailed error information.
	 *
	 * @param array $debug_info Debug information to output
	 * @param int   $status_code HTTP status code
	 * @since 3.6.0
	 */
	private function output_debug_response( array $debug_info, int $status_code ): void {
		status_header( $status_code );
		header( 'Content-Type: application/json; charset=utf-8' );

		$response = array(
			'status' => $status_code,
			'timestamp' => current_time('Y-m-d H:i:s'),
			'debug_info' => $debug_info,
		);

		echo wp_json_encode( $response, JSON_PRETTY_PRINT );
		exit;
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
			'wc-api' => static::REQUEST_FEED_ACTION,
			'language' => $language_code,
			'secret' => $this->get_feed_secret(),
		);

		return add_query_arg( $query_args, home_url( '/' ) );
	}

	/**
	 * Gets the URL for retrieving the language feed data for direct URL upload.
	 * This is used specifically for Facebook direct URL upload endpoints.
	 *
	 * @param string $language_code Language code
	 * @return string
	 * @since 3.6.0
	 */
	public function get_language_feed_data_url( string $language_code ): string {
		$query_args = array(
			'wc-api' => static::REQUEST_FEED_ACTION,
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

	/**
	 * Write language feed file with detailed debug steps captured for JSON response.
	 *
	 * @param string $language_code Language code
	 * @return array Debug result with steps and success status
	 * @since 3.6.0
	 */
	private function write_language_feed_file_with_debug( string $language_code ): array {
		$steps = [];
		$success = false;
		$error = null;

		try {
			// Create a language-specific feed writer and handler for this request
			$header_row = $this->language_feed_data->get_csv_header_for_columns(['id', 'override']);
			$language_feed_writer = new LanguageOverrideFeedWriter( $language_code, $header_row );
			$language_feed_handler = new LanguageOverrideFeedHandler( $this->language_feed_data, $language_feed_writer );

			// Get paths for debugging
			$temp_file_path = $language_feed_writer->get_temp_file_path( $language_code );
			$final_file_path = $language_feed_writer->get_file_path( $language_code );
			$directory = $language_feed_writer->get_file_directory();

			$steps['start'] = [
				'step' => 'Initialization',
				'language_code' => $language_code,
				'temp_file_path' => $temp_file_path,
				'final_file_path' => $final_file_path,
				'directory' => $directory,
				'directory_exists' => is_dir( $directory ),
				'directory_writable' => is_writable( $directory ),
				'status' => 'completed'
			];

			// Step 0: Create directory if it doesn't exist (this was missing!)
			$steps['step0_start'] = [
				'step' => 'Creating feed directory',
				'status' => 'started'
			];

			try {
				// Create the directory
				if ( ! wp_mkdir_p( $directory ) ) {
					throw new \Exception( "Could not create feed directory at {$directory}" );
				}

				// Create protection files
				$language_feed_handler->create_files_to_protect_feed_directory();

				$steps['step0_complete'] = [
					'step' => 'Feed directory created successfully',
					'directory_exists_after_creation' => is_dir( $directory ),
					'directory_writable_after_creation' => is_writable( $directory ),
					'status' => 'completed'
				];
			} catch ( \Exception $e ) {
				$steps['step0_error'] = [
					'step' => 'Feed directory creation failed',
					'error' => $e->getMessage(),
					'exception_trace' => $e->getTraceAsString(),
					'status' => 'failed'
				];
				throw $e;
			}

			// Step 1: Prepare temporary file
			$steps['step1_start'] = [
				'step' => 'Preparing temporary feed file',
				'status' => 'started'
			];

			try {
				$temp_feed_file = $language_feed_handler->prepare_temporary_feed_file( $language_code );
				$steps['step1_complete'] = [
					'step' => 'Temporary feed file prepared',
					'temp_file_exists' => file_exists( $temp_file_path ),
					'temp_file_size' => file_exists( $temp_file_path ) ? filesize( $temp_file_path ) : 'file not found',
					'temp_file_resource_valid' => is_resource( $temp_feed_file ),
					'status' => 'completed'
				];
			} catch ( \Exception $e ) {
				$steps['step1_error'] = [
					'step' => 'Temporary feed file preparation failed',
					'error' => $e->getMessage(),
					'exception_trace' => $e->getTraceAsString(),
					'status' => 'failed'
				];
				throw $e;
			}

			// Step 2: Write data to temp file
			$steps['step2_start'] = [
				'step' => 'Writing data to temporary feed file',
				'status' => 'started'
			];

			try {
				$language_feed_handler->write_language_feed_to_temp_file( $language_code, $temp_feed_file );
				$steps['step2_complete'] = [
					'step' => 'Data written to temporary feed file',
					'temp_file_exists_after_write' => file_exists( $temp_file_path ),
					'temp_file_size_after_write' => file_exists( $temp_file_path ) ? filesize( $temp_file_path ) : 'file not found',
					'status' => 'completed'
				];
			} catch ( \Exception $e ) {
				$steps['step2_error'] = [
					'step' => 'Writing data to temporary file failed',
					'error' => $e->getMessage(),
					'exception_trace' => $e->getTraceAsString(),
					'status' => 'failed'
				];
				throw $e;
			}

			// Step 3: Rename temp file to final file
			$steps['step3_start'] = [
				'step' => 'Renaming temporary file to final file',
				'temp_file_exists_before_rename' => file_exists( $temp_file_path ),
				'final_file_exists_before_rename' => file_exists( $final_file_path ),
				'status' => 'started'
			];

			try {
				$language_feed_handler->rename_temporary_feed_file_to_final_feed_file( $language_code );
				$steps['step3_complete'] = [
					'step' => 'File renamed successfully',
					'temp_file_exists_after_rename' => file_exists( $temp_file_path ),
					'final_file_exists_after_rename' => file_exists( $final_file_path ),
					'final_file_size' => file_exists( $final_file_path ) ? filesize( $final_file_path ) : 'file not found',
					'status' => 'completed'
				];
			} catch ( \Exception $e ) {
				$steps['step3_error'] = [
					'step' => 'File rename failed',
					'error' => $e->getMessage(),
					'exception_trace' => $e->getTraceAsString(),
					'temp_file_exists' => file_exists( $temp_file_path ),
					'final_file_exists' => file_exists( $final_file_path ),
					'status' => 'failed'
				];
				throw $e;
			}

			$success = true;
			$steps['completion'] = [
				'step' => 'File generation completed successfully',
				'final_file_exists' => file_exists( $final_file_path ),
				'final_file_size' => file_exists( $final_file_path ) ? filesize( $final_file_path ) : 'file not found',
				'status' => 'completed'
			];

		} catch ( \Exception $e ) {
			$error = $e->getMessage();
			$steps['exception'] = [
				'step' => 'Exception occurred during file generation',
				'exception_message' => $e->getMessage(),
				'exception_file' => $e->getFile(),
				'exception_line' => $e->getLine(),
				'exception_trace' => $e->getTraceAsString(),
				'status' => 'failed'
			];
		}

		return [
			'success' => $success,
			'error' => $error,
			'steps' => $steps
		];
	}

}
