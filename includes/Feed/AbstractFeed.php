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

use WooCommerce\Facebook\Framework\Helper;
use WooCommerce\Facebook\Framework\Plugin\Exception as PluginException;
use WooCommerce\Facebook\Utilities\Heartbeat;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract class AbstractFeed
 *
 * Provides the base functionality for handling Metadata feed requests and generation for Facebook integration.
 *
 * @package WooCommerce\Facebook\Feed
 * @since 3.5.0
 */
abstract class AbstractFeed {
	/** The action callback for generating a feed */
	const GENERATE_FEED_ACTION = 'wc_facebook_regenerate_feed_';
	/** The action slug for getting the feed */
	const REQUEST_FEED_ACTION = 'wc_facebook_get_feed_data_';
	/** The action slug for triggering file upload */
	const FEED_GEN_COMPLETE_ACTION = 'wc_facebook_feed_generation_completed_';

	/** Hook prefix for Legacy REST API hook name */
	const LEGACY_API_PREFIX = 'woocommerce_api_';
	/** @var string the WordPress option name where the secret included in the feed URL is stored */
	const OPTION_FEED_URL_SECRET = 'wc_facebook_feed_url_secret_';


	/**
	 * The feed writer instance for the given feed.
	 *
	 * @var AbstractFeedFileWriter
	 * @since 3.5.0
	 */
	protected AbstractFeedFileWriter $feed_writer;

	/**
	 * The feed generator instance for the given feed.
	 *
	 * @var FeedGenerator
	 * @since 3.5.0
	 */
	protected FeedGenerator $feed_generator;

	/**
	 * The feed handler instance for the given feed.
	 *
	 * @var AbstractFeedHandler
	 * @since 3.5.0
	 */
	protected AbstractFeedHandler $feed_handler;

	/**
	 * Initialize feed properties.
	 *
	 * @param AbstractFeedFileWriter $feed_writer The feed file writer instance.
	 * @param AbstractFeedHandler    $feed_handler The feed handler instance.
	 * @param FeedGenerator          $feed_generator The feed generator instance.
	 */
	protected function init( AbstractFeedFileWriter $feed_writer, AbstractFeedHandler $feed_handler, FeedGenerator $feed_generator ): void {
		$this->feed_writer    = $feed_writer;
		$this->feed_handler   = $feed_handler;
		$this->feed_generator = $feed_generator;

		$this->feed_generator->init();
		$this->add_hooks();
	}

	/**
	 * Adds the necessary hooks for feed generation and data request handling.
	 *
	 * @since 3.5.0
	 */
	protected function add_hooks(): void {
		add_action( static::get_feed_gen_scheduling_interval(), array( $this, 'schedule_feed_generation' ) );
		add_action( self::GENERATE_FEED_ACTION . static::get_data_stream_name(), array( $this, 'regenerate_feed' ) );
		add_action( self::FEED_GEN_COMPLETE_ACTION . static::get_data_stream_name(), array( $this, 'send_request_to_upload_feed' ) );
		add_action(
			self::LEGACY_API_PREFIX . self::REQUEST_FEED_ACTION . static::get_data_stream_name(),
			array(
				$this,
				'handle_feed_data_request',
			)
		);
	}

	/**
	 * Schedules the recurring feed generation.
	 *
	 * @since 3.5.0
	 */
	public function schedule_feed_generation(): void {
		if ( $this->should_skip_feed() ) {
			return;
		}

		$schedule_action_hook_name = self::GENERATE_FEED_ACTION . static::get_data_stream_name();
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
	 * Regenerates the example feed based on the defined schedule.
	 * New style feed will use the FeedGenerator to queue the feed generation. Use for batched feed generation.
	 * Old style feed will use the FeedHandler to generate the feed file. Use if batch not needed or new style not enabled.
	 *
	 * @since 3.5.0
	 */
	public function regenerate_feed(): void {
		if ( $this->should_skip_feed() ) {
			return;
		}

		$this->feed_generator->queue_start();
	}

	/**
	 * The feed should be skipped if there isn't a Commerce Partner Integration ID set as the ID is required for
	 * calls to the GraphCommercePartnerIntegrationFileUpdatePost endpoint.
	 * Overwrite this function if your feed upload uses a different endpoint with different requirements.
	 *
	 * @since 3.5.0
	 */
	public function should_skip_feed(): bool {
		$connection_handler = facebook_for_woocommerce()->get_connection_handler();
		$cpi_id             = $connection_handler->get_commerce_partner_integration_id();
		$cms_id             = $connection_handler->get_commerce_merchant_settings_id();

		return empty( $cpi_id ) || empty( $cms_id );
	}

	/**
	 * Trigger the upload flow
	 * Once feed regenerated, trigger upload via create_upload API
	 * This will hit the url defined in the class and trigger handle_feed_data_request
	 *
	 * @since 3.5.0
	 */
	public function send_request_to_upload_feed(): void {
		$name = static::get_data_stream_name();
		$data = array(
			'url'         => self::get_feed_data_url(),
			'feed_type'   => static::get_feed_type(),
			'update_type' => 'CREATE',
		);

		try {
			$cpi_id = facebook_for_woocommerce()->get_connection_handler()->get_commerce_partner_integration_id();
			facebook_for_woocommerce()->
			get_api()->
			create_common_data_feed_upload( $cpi_id, $data );
		} catch ( \Exception $exception ) {
			\WC_Facebookcommerce_Utils::log_exception_immediately_to_meta(
				$exception,
				[
					'event'      => 'feed_upload',
					'event_type' => 'send_request_to_upload_feed',
					'extra_data' => [
						'feed_name' => $name,
						'data'      => wp_json_encode( $data ),
					],
				]
			);
		}
	}

	/**
	 * Gets the URL for retrieving the feed data using legacy WooCommerce REST API.
	 * Sample url:
	 * https://your-site-url.com/?wc-api=wc_facebook_get_feed_data_example&secret=your_generated_secret
	 *
	 * @return string
	 * @since 3.5.0
	 */
	public function get_feed_data_url(): string {
		$query_args = array(
			'wc-api' => self::REQUEST_FEED_ACTION . static::get_data_stream_name(),
			'secret' => self::get_feed_secret(),
		);

		// phpcs:ignore
		// nosemgrep: audit.php.wp.security.xss.query-arg
		return add_query_arg( $query_args, home_url( '/' ) );
	}


	/**
	 * Gets the secret value that should be included in the legacy WooCommerce REST API URL.
	 *
	 * @return string
	 * @since 3.5.0
	 */
	public function get_feed_secret(): string {
		$secret_option_name = self::OPTION_FEED_URL_SECRET . static::get_data_stream_name();

		$secret = get_option( $secret_option_name, '' );
		if ( ! $secret ) {
			$secret = wp_hash( 'example-feed-' . time() );
			update_option( $secret_option_name, $secret );
		}

		return $secret;
	}

	/**
	 * Callback function that streams the feed file to the GraphPartnerIntegrationFileUpdatePost
	 * Ex: https://your-site-url.com/?wc-api=wc_facebook_get_feed_data_example&secret=your_generated_secret
	 * The above WooC Legacy REST API will trigger the handle_feed_data_request method
	 * See LegacyRequestApiStub.php for more details
	 *
	 * @throws PluginException If file issue comes up.
	 * @since 3.5.0
	 */
	public function handle_feed_data_request(): void {
		$name = static::get_data_stream_name();
		\WC_Facebookcommerce_Utils::log_with_debug_mode_enabled( "{$name} feed: Meta is requesting feed file." );

		$file_path = $this->feed_writer->get_file_path();
		$file      = false;

		// regenerate if the file doesn't exist using the legacy flow.
		if ( ! file_exists( $file_path ) ) {
			$this->feed_handler->generate_feed_file();
		}

		try {
			// bail early if the feed secret is not included or is not valid.
			if ( self::get_feed_secret() !== Helper::get_requested_value( 'secret' ) ) {
				throw new PluginException( "{$name} feed: Invalid secret provided.", 401 );
			}

			// bail early if the file can't be read.
			if ( ! is_readable( $file_path ) ) {
				throw new PluginException( "{$name}: File at path ' . $file_path . ' is not readable.", 404 );
			}

			if ( $this->feed_writer instanceof JsonFeedFileWriter ) {
				$content_type = 'Content-Type: application/json; charset=utf-8';
			} else {
				$content_type = 'Content-Type: text/csv; charset=utf-8';
			}

			// set the download headers.
			header( $content_type );
			header( 'Content-Description: File Transfer' );
			header( 'Content-Disposition: attachment; filename="' . basename( $file_path ) . '"' );
			header( 'Expires: 0' );
			header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
			header( 'Pragma: public' );
			header( 'Content-Length:' . filesize( $file_path ) );

			// phpcs:ignore -- use php file i/o functions
			$file = fopen( $file_path, 'rb' );
			if ( ! $file ) {
				throw new PluginException( "{$name} feed: Could not open feed file.", 500 );
			}

			// fpassthru might be disabled in some hosts (like Flywheel).
			// phpcs:ignore
			if ( \WC_Facebookcommerce_Utils::is_fpassthru_disabled() || ! @fpassthru( $file ) ) {
				\WC_Facebookcommerce_Utils::log_with_debug_mode_enabled( "{$name} feed: fpassthru is disabled: getting file contents." );
				//phpcs:ignore
				$contents = @stream_get_contents( $file );
				if ( ! $contents ) {
					throw new PluginException( "{$name} feed: Could not get feed file contents.", 500 );
				}
				echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		} catch ( \Exception $exception ) {
			\WC_Facebookcommerce_Utils::log_exception_immediately_to_meta(
				$exception,
				[
					'event'      => 'feed_upload',
					'event_type' => 'handle_feed_data_request',
					'extra_data' => [
						'feed_name' => $name,
						'file_path' => $file_path,
					],
				]
			);
			status_header( $exception->getCode() );
		} finally {
			if ( $file ) {
				// phpcs:ignore -- use php file i/o functions
				fclose($file);
			}
		}
		exit;
	}

	/**
	 * Get the data stream name for the given feed.
	 *
	 * @return string
	 */
	abstract protected static function get_data_stream_name(): string;

	/**
	 * Get the data feed type.
	 *
	 * @return string
	 */
	abstract protected static function get_feed_type(): string;

	/**
	 * Get the feed generation interval. Must be longer than the heartbeat.
	 *
	 * @return int
	 */
	protected static function get_feed_gen_interval(): int {
		return DAY_IN_SECONDS;
	}

	/**
	 * Get the Heartbeat interval to ensure that feed gen is scheduled. Must be shorter than the feed gen interval.
	 *
	 * @return string Heartbeat constant value
	 */
	protected static function get_feed_gen_scheduling_interval(): string {
		return Heartbeat::HOURLY;
	}
}
