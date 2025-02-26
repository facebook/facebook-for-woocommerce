<?php
/** Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Feed;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionScheduler;
use WooCommerce\Facebook\Framework\Api\Exception;
use WooCommerce\Facebook\Framework\Helper;
use WooCommerce\Facebook\Utilities\Heartbeat;
use WooCommerce\Facebook\Framework\Plugin\Exception as PluginException;

/**
 * Example Feed class
 *
 * Extends Abstract Feed class to handle example feed requests and generation for Facebook integration.
 *
 * @package WooCommerce\Facebook\Feed
 * @since 3.5.0
 */
class ExampleFeed extends AbstractFeed {

	const EXAMPLE_FEED_INTERVAL  = 'wc_facebook_example_feed_generation_interval';
	const OPTION_FEED_URL_SECRET = 'wc_facebook_example_feed_url_secret';

	/**
	 * Constructor.
	 *
	 * @since 3.5.0
	 */
	public function __construct() {
		// Using the headers for ratings and reviews for this proof of concept.
		$header = 'aggregator,store.name,store.id,store.store_urls,review_id,rating,title,content,created_at,' .
			'incentivized,has_verified_purchase,language,reviewer.name,reviewer.is_anonymous,product.name,product.url,' .
			'product.image_urls,product.product_identifiers.skus,country' . PHP_EOL;

		$this->feed_handler   = new ExampleFeedHandler( new CsvFeedFileWriter( FeedManager::EXAMPLE, $header ) );
		$scheduler            = new ActionScheduler();
		$this->feed_generator = new ExampleFeedGenerator( $scheduler, $this->feed_handler );
		$this->feed_generator->init();

		parent::__construct( FeedManager::EXAMPLE, Heartbeat::HOURLY );
	}

	/**
	 * Schedules the recurring feed generation.
	 * This method must be implemented by the concrete feed class, usually by providing a recurring interval
	 *
	 * @since 3.5.0
	 */
	public function schedule_feed_generation() {
		/**
		 * Filter the interval for generating the example feed.
		 *
		 * @param int $interval The interval in seconds.
		 * @since 3.5.0
		 */
		$interval = apply_filters( self::EXAMPLE_FEED_INTERVAL, DAY_IN_SECONDS );

		$schedule_action_hook_name = self::modify_action_name( self::GENERATE_FEED_ACTION );
		if ( ! as_next_scheduled_action( $schedule_action_hook_name ) ) {
			as_schedule_recurring_action(
				time(),
				max( 2, $interval ),
				$schedule_action_hook_name,
				array(),
				facebook_for_woocommerce()->get_id_dasherized()
			);
		}
	}

	/**
	 * Allows an admin to schedule the feed generation immediately.
	 *
	 * @since 3.5.0
	 */
	public function schedule_feed_generation_immediately() {
		$schedule_action_hook_name = self::modify_action_name( self::GENERATE_FEED_ACTION );
		if ( ! as_next_scheduled_action( $schedule_action_hook_name ) ) {
			$this->regenerate_feed();
		}
	}

	/**
	 * Regenerates the example feed based on the defined schedule.
	 *
	 * @since 3.5.0
	 */
	public function regenerate_feed() {
		// Maybe use new ( experimental ), feed generation framework.
		if ( facebook_for_woocommerce()->get_integration()->is_new_style_feed_generation_enabled() ) {
			$this->feed_generator->queue_start();
		} else {
			$this->feed_handler->generate_feed_file();
		}
	}

	/**
	 * Trigger the upload flow
	 * Once feed regenerated, trigger upload via create_upload API
	 * This will hit the url defined in the class and trigger the handle streaming file
	 *
	 * @since 3.5.0
	 */
	public function send_request_to_upload_feed() {
		$data = array(
			'url'         => self::get_feed_data_url(),
			'feed_type'   => 'PRODUCT_RATINGS_AND_REVIEWS',
			'update_type' => 'CREATE',
		);

		try {
			$cpi_id = facebook_for_woocommerce()->get_integration()->get_commerce_partner_integration_id();
			facebook_for_woocommerce()
				->get_api()
				->create_common_upload( $cpi_id, $data );
		} catch ( Exception $e ) {
			// Log the error and continue.
			facebook_for_woocommerce()->log( 'Failed to create example feed upload request: ' . $e->getMessage() );
		}
	}

	/**
	 * Callback function that streams the feed file to the GraphPartnerIntegrationFileUpdatePost
	 * Ex: https://your-site-url.com/?wc-api=wc_facebook_get_feed_data_example&secret=your_generated_secret
	 * The above WooC Legacy REST API will trigger the handle_feed_data_request method
	 * See LegacyRequestApiStub.php for more details
	 *
	 * @since 3.5.0
	 */
	public function handle_feed_data_request() {
		\WC_Facebookcommerce_Utils::log( 'ExampleFeed: Meta is requesting feed file.' );

		$file_path = $this->feed_handler->get_feed_writer()->get_file_path();

		// regenerate if the file doesn't exist.
		if ( ! file_exists( $file_path ) ) {
			$this->feed_handler->generate_feed_file();
		}

		try {
			// bail early if the feed secret is not included or is not valid.
			if ( self::get_feed_secret() !== Helper::get_requested_value( 'secret' ) ) {
				throw new PluginException( 'ExampleFeed: Invalid secret provided.', 401 );
			}

			// bail early if the file can't be read.
			if ( ! is_readable( $file_path ) ) {
				throw new PluginException( 'ExampleFeed: File at path ' . $file_path . ' is not readable.', 404 );
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
				throw new PluginException( 'ExampleFeed: Could not open feed file.', 500 );
			}

			// fpassthru might be disabled in some hosts (like Flywheel).
			// phpcs:ignore
			if ( \WC_Facebookcommerce_Utils::is_fpassthru_disabled() || ! @fpassthru( $file ) ) {
				\WC_Facebookcommerce_Utils::log( 'ExampleFeed: fpassthru is disabled: getting file contents' );
				//phpcs:ignore
				$contents = @stream_get_contents( $file );
				if ( ! $contents ) {
					throw new PluginException( 'Could not get feed file contents.', 500 );
				}
				echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		} catch ( \Exception $exception ) {
			\WC_Facebookcommerce_Utils::log( 'ExampleFeed: Could not serve feed. ' . $exception->getMessage() . ' (' . $exception->getCode() . ')' );
			status_header( $exception->getCode() );
		}
		exit;
	}

	/**
	 * Gets the URL for retrieving the product feed data using legacy WooCommerce REST API.
	 * Sample url:
	 * https://your-site-url.com/?wc-api=wc_facebook_get_feed_data_example&secret=your_generated_secret
	 *
	 * @since 3.5.0
	 * @return string
	 */
	public function get_feed_data_url(): string {
		$query_args = array(
			'wc-api' => self::modify_action_name( self::REQUEST_FEED_ACTION ),
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
		$secret = get_option( self::OPTION_FEED_URL_SECRET, '' );
		if ( ! $secret ) {
			$secret = wp_hash( 'example-feed-' . time() );
			update_option( self::OPTION_FEED_URL_SECRET, $secret );
		}

		return $secret;
	}
}
