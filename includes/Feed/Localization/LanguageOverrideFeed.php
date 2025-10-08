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
use WooCommerce\Facebook\Integrations\IntegrationRegistry;

/**
 * Language Override Feed handler.
 *
 * Specialized functionality for language override feeds.
 *
 * @since 3.6.0
 */
class LanguageOverrideFeed {

	use LanguageFeedManagementTrait;

	/** @var string the feed name for creating a new feed by this plugin */
	const FEED_NAME_TEMPLATE = '%s Language Override Feed (%s)';

	/** @var \WooCommerce\Facebook\Feed\Localization\LanguageFeedData */
	private $language_feed_data;

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
		// Avoid circular dependency by checking option directly instead of calling integration method
		if ( 'yes' !== get_option( 'wc_facebook_language_override_feed_generation_enabled', 'yes' ) ) {
			Logger::log(
				'Language override feed initialization skipped - feature disabled in settings',
				[],
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);
			return;
		}

		// Check if we have an active localization plugin before proceeding
		if ( ! IntegrationRegistry::has_active_localization_plugin() ) {
			Logger::log(
				'Language override feed initialization skipped - no active localization plugin',
				[],
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
				)
			);
			return;
		}

		$this->language_feed_data = new LanguageFeedData();

		// Get the default language from the active localization plugin
		$default_language = $this->language_feed_data->get_default_language();

		// Ensure we have a valid language code
		if ( empty( $default_language ) ) {
			Logger::log(
				'Language override feed initialization failed - no valid default language',
				[],
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
				)
			);
			return;
		}

		$this->add_hooks();
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
		// TESTING ONLY: Using 15-minute transient to match feed generation interval. In production, this should be HOUR_IN_SECONDS.
		set_transient( $flag_name, 'yes', 15 * MINUTE_IN_SECONDS );

		$integration   = facebook_for_woocommerce()->get_integration();
		$configured_ok = $integration && $integration->is_configured();

		// Only schedule feed job if store has not opted out of product sync.
		$store_allows_sync = ( $configured_ok && $integration->is_product_sync_enabled() ) || $integration->is_woo_all_products_enabled();

		// Only schedule if has not opted out of language override feed generation.
		$store_allows_language_feeds = $configured_ok && $this->is_language_override_feed_generation_enabled();

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
				$message = 'Feed should be skipped.';
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
			as_schedule_recurring_action(
				time(),
				static::get_feed_gen_interval(),
				$schedule_action_hook_name,
				array(),
				facebook_for_woocommerce()->get_id_dasherized()
			);
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
	 * Uses the feed handler directly instead of the feed generator to create
	 * multiple language files in a single action.
	 *
	 * @since 3.6.0
	 */
	public function regenerate_all_language_feeds(): void {
		if ( $this->should_skip_feed() ) {
			return;
		}

		// Get all available languages
		$languages = $this->language_feed_data->get_available_languages();

		if ( empty( $languages ) ) {
			return;
		}

		$successful_languages = [];
		$failed_languages = [];

		// Generate feed file for each language using the feed handler directly
		foreach ( $languages as $language_code ) {
			try {
				// Generate the feed file for this language
				$header_row = $this->language_feed_data->get_csv_header_for_columns(['id', 'override']);
				$feed_handler = new LanguageOverrideFeedHandler( $this->language_feed_data, new LanguageOverrideFeedWriter( $language_code, $header_row ) );
				$success = $feed_handler->write_language_feed_file( $language_code );

				if ( $success ) {
					$successful_languages[] = $language_code;
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

		// Log summary only if there are failures
		if ( ! empty( $failed_languages ) ) {
			Logger::log(
				sprintf(
					'Language override feed regeneration completed with failures. Success: %d, Failed: %d (%s)',
					count( $successful_languages ),
					count( $failed_languages ),
					implode( ', ', $failed_languages )
				),
				[
					'failed_languages' => $failed_languages,
					'total_languages' => count( $languages ),
				],
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::WARNING,
				)
			);
		}

		// Trigger the upload hook if any languages were successful
		if ( ! empty( $successful_languages ) ) {
			do_action( self::FEED_GEN_COMPLETE_ACTION . static::get_data_stream_name() );
		}
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
		add_action( self::GENERATE_FEED_ACTION . static::get_data_stream_name(), array( $this, 'regenerate_all_language_feeds' ) );
		add_action( self::FEED_GEN_COMPLETE_ACTION . static::get_data_stream_name(), array( $this, 'upload_language_override_feeds' ) );
		add_action(
			self::LEGACY_API_PREFIX . static::REQUEST_FEED_ACTION,
			array(
				$this,
				'handle_feed_data_request',
			)
		);
	}

	/**
	 * Gets the feed secret used for feed requests.
	 * Reuses the existing Feed class's secret for consistency.
	 *
	 * @return string
	 */
	protected function get_feed_secret(): string {
		return \WooCommerce\Facebook\Products\Feed::get_feed_secret();
	}

	/**
	 * Checks if language override feed generation is enabled in the admin settings.
	 *
	 * @return bool
	 * @since 3.6.0
	 */
	private function is_language_override_feed_generation_enabled(): bool {
		$integration = facebook_for_woocommerce()->get_integration();
		return $integration && $integration->is_language_override_feed_generation_enabled();
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
	 * Get the data stream name for language override feeds.
	 *
	 * @return string
	 */
	protected static function get_data_stream_name(): string {
		return 'language_override';
	}

	/**
	 * Override the feed generation interval to match product feeds frequency.
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
		// TESTING ONLY: Using 15-minute generation for testing purposes. In production, this should be DAY_IN_SECONDS (daily).
		return apply_filters( 'wc_facebook_language_override_feed_generation_interval', 15 * MINUTE_IN_SECONDS ); // Every 15 minutes for testing
	}

	/**
	 * Check if feed generation should be skipped.
	 *
	 * @return bool
	 */
	public function should_skip_feed(): bool {
		// Check if language override feed generation is enabled
		if (!$this->is_language_override_feed_generation_enabled()) {
			return true;
		}

		$connection_handler = facebook_for_woocommerce()->get_connection_handler();

		// Check connection methods
		$has_valid_connection = !empty($connection_handler->get_commerce_partner_integration_id()) ||
		                       !empty($connection_handler->get_commerce_merchant_settings_id()) ||
		                       !empty($connection_handler->get_access_token());

		if (!$has_valid_connection) {
			return true;
		}

		// Check localization plugin
		if (!IntegrationRegistry::has_active_localization_plugin()) {
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
				throw new PluginException( 'Language code is required', 400 );
			}

			// Validate the feed secret
			if ( $this->get_feed_secret() !== Helper::get_requested_value( 'secret' ) ) {
				throw new PluginException( 'Invalid feed secret provided', 401 );
			}

			// Create language-specific feed writer to get file path
			$header_row = $this->language_feed_data->get_csv_header_for_columns(['id', 'override']);
			$language_feed_writer = new LanguageOverrideFeedWriter( $language_code, $header_row );
			$file_path = $language_feed_writer->get_file_path();

			// Regenerate if the file doesn't exist or if explicitly requested
			if ( ! empty( $_GET['regenerate'] ) || ! file_exists( $file_path ) ) {
				$feed_handler = new LanguageOverrideFeedHandler( $this->language_feed_data, $language_feed_writer );
				$success = $feed_handler->write_language_feed_file( $language_code );
				if ( !$success ) {
					throw new PluginException( 'Failed to regenerate language feed file', 500 );
				}
			}

			// Check if the file can be read
			if ( ! is_readable( $file_path ) ) {
				throw new PluginException( 'Language feed file is not readable', 404 );
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
				throw new PluginException( 'Could not open language feed file', 500 );
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
					throw new PluginException( 'Could not get language feed file contents', 500 );
				}
				echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			@fclose( $file );

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
			status_header( $exception->getCode() ?: 500 );
		}

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
	 * Alias method for backward compatibility with debug scripts.
	 *
	 * @param string $language_code Language code
	 * @return string
	 * @since 3.6.0
	 */
	public function get_language_feed_data_url( string $language_code ): string {
		return $this->get_language_feed_url( $language_code );
	}


	/**
	 * Upload language override feeds to Facebook for all available languages.
	 * This mirrors Feed.php's send_request_to_upload_feed but handles multiple languages.
	 *
	 * @since 3.6.0
	 */
	public function upload_language_override_feeds() {
		Logger::log(
			'Hook triggered: upload_language_override_feeds called',
			[],
			array(
				'should_send_log_to_meta'        => false,
				'should_save_log_in_woocommerce' => true,
				'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
			)
		);

		if ( ! IntegrationRegistry::has_active_localization_plugin() ) {
			Logger::log(
				'upload_language_override_feeds skipped - no active localization plugin',
				[],
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);
			return;
		}

		$languages = $this->language_feed_data->get_available_languages();

		Logger::log(
			'upload_language_override_feeds - starting uploads for languages',
			['languages' => $languages],
			array(
				'should_send_log_to_meta'        => false,
				'should_save_log_in_woocommerce' => true,
				'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
			)
		);

		foreach ( $languages as $language_code ) {
			Logger::log(
				'upload_language_override_feeds - uploading language',
				['language_code' => $language_code],
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);
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

			// Step 2: Tell Facebook to fetch the CSV data from our endpoint (feed files are already generated)
			$data = [
				'url' => $this->get_language_feed_url( $language_code ),
			];

			facebook_for_woocommerce()->get_api()->create_product_feed_upload( $feed_id, $data );

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
