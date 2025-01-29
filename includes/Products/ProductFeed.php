<?php
// phpcs:ignoreFile
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Products;

use WooCommerce\Facebook\Framework\Helper;
use WooCommerce\Facebook\Feed\AbstractFeed;
use WooCommerce\Facebook\Framework\Plugin\Exception as PluginException;

/**
 * Class ProductFeed
 *
 * Handles the product feed requests and generation for Facebook integration.
 *
 * @package WooCommerce\Facebook\Products
 * @since 1.11.0
 */
class ProductFeed extends AbstractFeed {

	public function __construct( string $data_stream_name ) {
		parent::__construct( $data_stream_name );
		$this->feed_generator = facebook_for_woocommerce()->job_manager->generate_product_feed_job;
		$this->feed_handler   = new \WC_Facebook_Product_Feed();
	}

	/**
	 *
	 * Logs the request, tracks the feed file request, and serves the product feed file.
	 * If the feed file does not exist or the regenerate parameter is set, it regenerates the feed.
	 * Validates the feed secret and checks if the file is readable before serving it.
	 * Uses `fpassthru` to output the file contents if available, otherwise reads the file contents and echoes them.
	 * Catches and logs any exceptions that occur during the process.
	 *
	 * @throws PluginException When the feed secret is invalid, the file is not readable, or the file contents cannot be output.
	 * Caught in same function to log the exception and set the response status code.
	 *
	 * @since 1.11.0
	 *
	 * @internal
	 */
	public function handle_feed_data_request() {
		\WC_Facebookcommerce_Utils::log( 'Facebook is requesting the product feed.' );
		facebook_for_woocommerce()->get_tracker()->track_feed_file_requested();

		$feed_handler = new \WC_Facebook_Product_Feed();
		$file_path    = $feed_handler->get_file_path();

		if ( ! empty( $_GET['regenerate'] ) || ! file_exists( $file_path ) ) {
			$feed_handler->generate_feed();
		}

		try {
			if ( self::get_feed_secret() !== Helper::get_requested_value( 'secret' ) ) {
				throw new PluginException( 'Invalid feed secret provided.', 401 );
			}

			if ( ! is_readable( $file_path ) ) {
				throw new PluginException( 'File is not readable.', 404 );
			}

			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Description: File Transfer' );
			header( 'Content-Disposition: attachment; filename="' . basename( $file_path ) . '"' );
			header( 'Expires: 0' );
			header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
			header( 'Pragma: public' );
			header( 'Content-Length:' . filesize( $file_path ) );

			$file = @fopen( $file_path, 'rb' );
			if ( ! $file ) {
				throw new PluginException( 'Could not open feed file.', 500 );
			}

			if ( $this->is_fpassthru_disabled() || ! @fpassthru( $file ) ) {
				\WC_Facebookcommerce_Utils::log( 'fpassthru is disabled: getting file contents' );
				$contents = @stream_get_contents( $file );
				if ( ! $contents ) {
					throw new PluginException( 'Could not get feed file contents.', 500 );
				}
				echo $contents;
			}
		} catch ( \Exception $exception ) {
			\WC_Facebookcommerce_Utils::log( 'Could not serve product feed. ' . $exception->getMessage() . ' (' . $exception->getCode() . ')' );
			status_header( $exception->getCode() );
		}
		exit;
	}

	/**
	 * Regenerates the product feed.
	 *
	 * Uses the new feed generation framework if enabled, otherwise falls back to the legacy method.
	 *
	 * @since 1.11.0
	 * @internal
	 */
	public function regenerate_feed() {
		if ( facebook_for_woocommerce()->get_integration()->is_new_style_feed_generation_enabled() ) {
			$this->feed_generator->queue_start();
		} else {
			$this->feed_handler->generate_feed();
		}
	}

	/**
	 * Schedules the recurring feed generation.
	 *
	 * Only schedules the feed generation if the store allows product sync and feed generation.
	 * If the store does not allow sync or feed generation, it unschedules all actions.
	 * The interval for feed generation can be customized using the `wc_facebook_feed_generation_interval` filter.
	 *
	 * @since 1.11.0
	 * @internal
	 */
	public function schedule_feed_generation() {
		$integration   = facebook_for_woocommerce()->get_integration();
		$configured_ok = $integration && $integration->is_configured();

		$store_allows_sync = $configured_ok && $integration->is_product_sync_enabled();
		$store_allows_feed = $configured_ok && $integration->is_legacy_feed_file_generation_enabled();

		if ( ! $store_allows_sync || ! $store_allows_feed ) {
			as_unschedule_all_actions( self::GENERATE_FEED_ACTION );

			return;
		}

		/**
		 * Filters the interval for feed generation.
		 *
		 * Allows customization of the interval at which the feed generation is scheduled.
		 * The default interval is set to one day (DAY_IN_SECONDS).
		 *
		 * @param int $interval The interval in seconds for feed generation. Default is DAY_IN_SECONDS.
		 *
		 * @since 1.11.0
		 */
		$interval = apply_filters( 'wc_facebook_product_feed_generation_interval', DAY_IN_SECONDS );
		if ( ! as_next_scheduled_action( self::GENERATE_FEED_ACTION ) ) {
			as_schedule_recurring_action( time(), max( 2, $interval ), self::GENERATE_FEED_ACTION, array(), facebook_for_woocommerce()->get_id_dasherized() );
		}
	}

	/**
	 * Gets the URL for retrieving the product feed data.
	 *
	 * @return string
	 * TODO: abstract query_args into an object
	 * @since 1.11.0
	 */
	public static function get_feed_data_url(): string {
		$query_args = array(
			'wc-api' => self::REQUEST_FEED_ACTION,
			'secret' => self::get_feed_secret(),
		);
		// phpcs:ignore
		// nosemgrep: audit.php.wp.security.xss.query-arg
		return add_query_arg( $query_args, home_url( '/' ) );
	}


	/**
	 * Gets the secret value that should be included in the ProductFeed URL.
	 *
	 * Generates a new secret and stores it in the database if no value is set.
	 *
	 * @return string
	 * @since 1.11.0
	 */
	public static function get_feed_secret(): string {
		$secret = get_option( self::OPTION_FEED_URL_SECRET, '' );
		if ( ! $secret ) {
			$secret = wp_hash( 'products-feed-' . time() );
			update_option( self::OPTION_FEED_URL_SECRET, $secret );
		}

		return $secret;
	}

	/**
	 * Checks whether `fpassthru` has been disabled in PHP.
	 *
	 * This method checks the PHP configuration to determine if the `fpassthru` function
	 * is listed in the `disable_functions` directive. If it is, the method returns true,
	 * indicating that `fpassthru` is disabled.
	 *
	 * @return bool True if `fpassthru` is disabled, false otherwise.
	 * @since 1.11.0
	 */
	private function is_fpassthru_disabled(): bool {
		$disabled = false;
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.ini_get
		if ( function_exists( 'ini_get' ) ) {
			// phpcs:ignore
			$disabled_functions = @ini_get( 'disable_functions' );
			// Todo: check if can use strict comparison.
			$disabled = is_string( $disabled_functions ) && in_array( 'fpassthru', explode( ',', $disabled_functions ), false );
		}

		return $disabled;
	}
}
