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

use WooCommerce\Facebook\Framework\Api\Exception;
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

	/** Schedule feed generation on some interval hook name for children classes. */
	const SCHEDULE_CALL_BACK = 'schedule_feed_generation';
	/** Schedule an immediate file generator on the scheduler hook name. For testing mostly. */
	const REGENERATE_CALL_BACK = 'regenerate_feed';
	/** Make upload call to Meta hook name for children classes. */
	const UPLOAD_CALL_BACK = 'send_request_to_upload_feed';
	/** Stream file to upload endpoint hook name for children classes. */
	const STREAM_CALL_BACK = 'handle_feed_data_request';
	/** Hook prefix for Legacy REST API hook name */
	const LEGACY_API_PREFIX = 'woocommerce_api_';
	/** @var string the WordPress option name where the secret included in the feed URL is stored */
	const OPTION_FEED_URL_SECRET = 'wc_facebook_feed_url_secret_';


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
	 * @var FeedHandler
	 * @since 3.5.0
	 */
	protected FeedHandler $feed_handler;

	/**
	 * The name of the data feed.
	 *
	 * @var string
	 */
	protected string $data_stream_name;

	/**
	 * The type of feed as per the endpoint requirements.
	 *
	 * @var string
	 */
	protected string $feed_type;

	/**
	 * The interval in seconds for the feed generation.
	 *
	 * @var int
	 */
	protected int $gen_feed_interval;

	/**
	 * Initialize feed properties.
	 *
	 * @param string      $data_stream_name              The data stream name.
	 * @param string      $feed_type                     The feed type.
	 * @param int         $gen_feed_interval             The feed generation interval in seconds.
	 * @param FeedHandler $feed_handler The feed handler instance.
	 */
	protected function init( string $data_stream_name, string $feed_type, int $gen_feed_interval, FeedHandler $feed_handler ): void {
		$this->data_stream_name  = $data_stream_name;
		$this->feed_type         = $feed_type;
		$this->gen_feed_interval = $gen_feed_interval;
		$this->feed_handler      = $feed_handler;

		$this->add_hooks( Heartbeat::HOURLY );
	}

	/**
	 * Adds the necessary hooks for feed generation and data request handling.
	 *
	 * @param string $heartbeat How often to check that the feed generation is scheduled.
	 *
	 * @since 3.5.0
	 */
	protected function add_hooks( string $heartbeat ): void {
		add_action( $heartbeat, array( $this, self::SCHEDULE_CALL_BACK ) );
		add_action( self::GENERATE_FEED_ACTION . $this->data_stream_name, array( $this, self::REGENERATE_CALL_BACK ) );
		add_action( self::FEED_GEN_COMPLETE_ACTION . $this->data_stream_name, array( $this, self::UPLOAD_CALL_BACK ) );
		add_action(
			self::LEGACY_API_PREFIX . self::REQUEST_FEED_ACTION . $this->data_stream_name,
			array(
				$this,
				self::STREAM_CALL_BACK,
			)
		);
	}

	/**
	 * Schedules the recurring feed generation.
	 *
	 * @since 3.5.0
	 */
	public function schedule_feed_generation(): void {
		$schedule_action_hook_name = self::GENERATE_FEED_ACTION . $this->data_stream_name;
		if ( ! as_next_scheduled_action( $schedule_action_hook_name ) ) {
			as_schedule_recurring_action(
				time(),
				$this->gen_feed_interval,
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
		// Maybe use new ( experimental ), feed generation framework.
		if ( \WC_Facebookcommerce::instance()->get_integration()->is_new_style_feed_generation_enabled() ) {
			$this->feed_generator->queue_start();
		} else {
			$this->feed_handler->generate_feed_file();
		}
	}

	/**
	 * Trigger the upload flow
	 * Once feed regenerated, trigger upload via create_upload API
	 * This will hit the url defined in the class and trigger handle_feed_data_request
	 *
	 * @since 3.5.0
	 */
	public function send_request_to_upload_feed(): void {
		$name = $this->data_stream_name;
		$data = array(
			'url'         => self::get_feed_data_url(),
			'feed_type'   => $this->feed_type,
			'update_type' => 'CREATE',
		);

		try {
			$cpi_id = get_option( 'wc_facebook_commerce_partner_integration_id', '' );
			facebook_for_woocommerce()->
				get_api()->
				create_common_data_feed_upload( $cpi_id, $data );
		} catch ( Exception $e ) {
			// Log the error and continue.
			\WC_Facebookcommerce_Utils::log( "{$name} feed: Failed to create feed upload request: " . $e->getMessage() );
		}
	}

	/**
	 * Gets the URL for retrieving the feed data using legacy WooCommerce REST API.
	 * Sample url:
	 * https://your-site-url.com/?wc-api=wc_facebook_get_feed_data_example&secret=your_generated_secret
	 *
	 * @since 3.5.0
	 * @return string
	 */
	public function get_feed_data_url(): string {
		$query_args = array(
			'wc-api' => self::REQUEST_FEED_ACTION . $this->data_stream_name,
			'secret' => self::get_feed_secret(),
		);

		// phpcs:ignore
		// nosemgrep: audit.php.wp.security.xss.query-arg
		return add_query_arg( $query_args, home_url( '/' ) );
	}


	/**
	 * Gets the secret value that should be included in the legacy WooCommerce REST API URL.
	 *
	 * @since 3.5.0
	 * @return string
	 */
	public function get_feed_secret(): string {
		$secret_option_name = self::OPTION_FEED_URL_SECRET . $this->data_stream_name;

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
		$name = $this->data_stream_name;
		\WC_Facebookcommerce_Utils::log( "{$name} feed: Meta is requesting feed file." );

		$file_path = $this->feed_handler->get_feed_writer()->get_file_path();

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

			// set the download headers.
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Description: File Transfer' );
			header( 'Content-Disposition: attachment; filename="' . basename( $file_path ) . '"' );
			header( 'Expires: 0' );
			header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
			header( 'Pragma: public' );
			header( 'Content-Length:' . filesize( $file_path ) );

			// phpcs:ignore
			$file = @fopen( $file_path, 'rb' );
			if ( ! $file ) {
				throw new PluginException( "{$name} feed: Could not open feed file.", 500 );
			}

			// fpassthru might be disabled in some hosts (like Flywheel).
			// phpcs:ignore
			if ( \WC_Facebookcommerce_Utils::is_fpassthru_disabled() || ! @fpassthru( $file ) ) {
				\WC_Facebookcommerce_Utils::log( "{$name} feed: fpassthru is disabled: getting file contents" );
				//phpcs:ignore
				$contents = @stream_get_contents( $file );
				if ( ! $contents ) {
					throw new PluginException( 'Could not get feed file contents.', 500 );
				}
				echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		} catch ( \Exception $exception ) {
			\WC_Facebookcommerce_Utils::log( "{$name} feed: Could not serve feed. " . $exception->getMessage() . ' (' . $exception->getCode() . ')' );
			status_header( $exception->getCode() );
		}
		exit;
	}
}
