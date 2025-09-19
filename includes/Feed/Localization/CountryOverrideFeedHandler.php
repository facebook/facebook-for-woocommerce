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
use WooCommerce\Facebook\Framework\Logger;
use WooCommerce\Facebook\Framework\Plugin\Exception as PluginException;
use WooCommerce\Facebook\Feed\AbstractFeedHandler;

/**
 * Country Override Feed Handler.
 *
 * Handles the generation and management of country override feed files.
 * Extends AbstractFeedHandler to maintain consistency with the project architecture.
 *
 * @since 3.0.18
 */
class CountryOverrideFeedHandler extends AbstractFeedHandler {

	/** @var \WooCommerce\Facebook\Feed\Localization\CountryFeedData */
	private $country_feed_data;

	/**
	 * Constructor
	 *
	 * @param \WooCommerce\Facebook\Feed\Localization\CountryFeedData $country_feed_data
	 * @param \WooCommerce\Facebook\Feed\Localization\CountryOverrideFeedWriter $feed_writer
	 */
	public function __construct( CountryFeedData $country_feed_data, CountryOverrideFeedWriter $feed_writer ) {
		$this->country_feed_data = $country_feed_data;
		$this->feed_writer = $feed_writer;
		$this->feed_type = 'country_override';
	}

	/**
	 * Generates country override feed files for all available countries.
	 * This mirrors LanguageOverrideFeedHandler::generate_feed_file but for countries.
	 *
	 * @since 3.0.18
	 */
	public function generate_feed_file(): void {
		$profiling_logger = facebook_for_woocommerce()->get_profiling_logger();
		$profiling_logger->start( 'generate_country_override_feeds' );

		Logger::log(
			'Generating country override feed files',
			[],
			array(
				'should_send_log_to_meta'        => false,
				'should_save_log_in_woocommerce' => true,
				'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
			)
		);

		try {
			if ( ! $this->country_feed_data->should_generate_country_feeds() ) {
				Logger::log(
					'Country override feed generation skipped: Prerequisites not met.',
					[],
					array(
						'should_send_log_to_meta'        => false,
						'should_save_log_in_woocommerce' => true,
						'woocommerce_log_level'          => \WC_Log_Levels::INFO,
					)
				);
				return;
			}

			$start_time = microtime( true );

			$this->generate_country_feed_files();

			$generation_time = microtime( true ) - $start_time;
			facebook_for_woocommerce()->get_tracker()->track_feed_file_generation_time( $generation_time );

			Logger::log(
				'Country override feed files generated',
				[],
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

			do_action( 'wc_facebook_country_feed_generation_completed' );

		} catch ( \Exception $exception ) {
			Logger::log(
				$exception->getMessage(),
				[],
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
				)
			);
		}

		$profiling_logger->stop( 'generate_country_override_feeds' );
	}

	/**
	 * Generates the country override feed files for all available countries.
	 * This mirrors LanguageOverrideFeedHandler::generate_language_feed_files.
	 *
	 * @return bool
	 * @throws PluginException If the feed file directory can't be created or the feed files can't be written.
	 */
	public function generate_country_feed_files() {
		if ( ! wp_mkdir_p( $this->feed_writer->get_file_directory() ) ) {
			throw new PluginException( __( 'Could not create country override feed directory', 'facebook-for-woocommerce' ), 500 );
		}

		$this->create_files_to_protect_feed_directory();

		$countries = $this->country_feed_data->get_countries_for_override_feeds();
		$success = true;

		foreach ( $countries as $country_code ) {
			if ( ! $this->write_country_feed_file( $country_code ) ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Creates files in the country feed directory to prevent directory listing and hotlinking.
	 * This mirrors LanguageOverrideFeedHandler::create_files_to_protect_feed_directory.
	 *
	 * @since 3.0.18
	 */
	public function create_files_to_protect_feed_directory() {
		$feed_directory = trailingslashit( $this->feed_writer->get_file_directory() );

		$files = array(
			array(
				'base'    => $feed_directory,
				'file'    => 'index.html',
				'content' => '',
			),
			array(
				'base'    => $feed_directory,
				'file'    => '.htaccess',
				'content' => 'deny from all',
			),
		);

		foreach ( $files as $file ) {
			if ( wp_mkdir_p( $file['base'] ) && ! file_exists( trailingslashit( $file['base'] ) . $file['file'] ) ) {
				$file_handle = @fopen( trailingslashit( $file['base'] ) . $file['file'], 'w' );
				if ( $file_handle ) {
					fwrite( $file_handle, $file['content'] );
					fclose( $file_handle );
				}
			}
		}
	}

	/**
	 * Writes the country override feed file for a specific country.
	 * This mirrors LanguageOverrideFeedHandler::write_language_feed_file.
	 *
	 * @since 3.0.18
	 *
	 * @param string $country_code Country code
	 * @return bool
	 */
	public function write_country_feed_file( string $country_code ) {
		try {
			// Step 1: Prepare the temporary empty feed file with header row.
			$temp_feed_file = $this->prepare_temporary_feed_file( $country_code );

			// Step 2: Write country feed data into the temporary feed file.
			$this->write_country_feed_to_temp_file( $country_code, $temp_feed_file );

			// Step 3: Rename temporary feed file to final feed file.
			$this->rename_temporary_feed_file_to_final_feed_file( $country_code );

			$written = true;

		} catch ( \Exception $e ) {
			Logger::log(
				wp_json_encode( $e->getMessage() ),
				[],
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
				)
			);

			$written = false;

			// close the temporary file
			if ( ! empty( $temp_feed_file ) && is_resource( $temp_feed_file ) ) {
				fclose( $temp_feed_file );
			}

			// delete the temporary file
			$temp_file_path = $this->feed_writer->get_temp_file_path_for_country( $country_code );
			if ( ! empty( $temp_file_path ) && file_exists( $temp_file_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $temp_file_path );
			}
		}

		return $written;
	}

	/**
	 * Prepare a fresh empty temporary feed file with the header row for a specific country.
	 * This mirrors LanguageOverrideFeedHandler::prepare_temporary_feed_file.
	 *
	 * @since 3.0.18
	 *
	 * @param string $country_code Country code
	 * @throws PluginException We can't open the file or the file is not writable.
	 * @return resource A file pointer resource.
	 */
	public function prepare_temporary_feed_file( string $country_code ) {
		$temp_file_path = $this->feed_writer->get_temp_file_path_for_country( $country_code );
		$temp_feed_file = @fopen( $temp_file_path, 'w' );

		// check if we can open the temporary feed file
		if ( false === $temp_feed_file || ! is_writable( $temp_file_path ) ) {
			throw new PluginException( __( 'Could not open the country override temporary feed file for writing', 'facebook-for-woocommerce' ), 500 );
		}

		$file_path = $this->feed_writer->get_file_path( $country_code );

		// check if we will be able to write to the final feed file
		if ( file_exists( $file_path ) && ! is_writable( $file_path ) ) {
			throw new PluginException( __( 'Could not open the country override feed file for writing', 'facebook-for-woocommerce' ), 500 );
		}

		// Define columns for country feeds (id, override, price)
		$columns = ['id', 'override', 'price'];

		// Generate header using CountryFeedData
		$header_row = $this->country_feed_data->get_csv_header_for_columns( $columns );

		// Write header with field descriptions (each on separate line)
		fwrite( $temp_feed_file, $header_row . PHP_EOL );

		// Write column headers
		fwrite( $temp_feed_file, implode( ',', $columns ) . PHP_EOL );

		return $temp_feed_file;
	}

	/**
	 * Write country feed data into a file.
	 * This mirrors LanguageOverrideFeedHandler::write_language_feed_to_temp_file.
	 *
	 * @since 3.0.18
	 *
	 * @param string   $country_code Country code
	 * @param resource $temp_feed_file File resource
	 * @return void
	 */
	public function write_country_feed_to_temp_file( string $country_code, $temp_feed_file ) {
		try {
			// Get sample product IDs for generating the feed
			$product_ids = $this->get_sample_product_ids( 1000 );

			if ( empty( $product_ids ) ) {
				return; // No products to process
			}

			// Get country-specific CSV data
			$csv_data = $this->country_feed_data->get_country_csv_data( $country_code, $product_ids );

			if ( empty( $csv_data ) ) {
				return; // No data for this country
			}

			// Process and write each data row
			foreach ( $csv_data as $row_data ) {
				$row = [
					$row_data['id'],
					$row_data['override'],
					$row_data['price']
				];

				if ( fputcsv( $temp_feed_file, $row ) === false ) {
					throw new PluginException( 'Failed to write a CSV data row.', 500 );
				}
			}

		} catch ( \Exception $exception ) {
			Logger::log(
				'Error while writing country override temporary feed file.',
				array(
					'event'      => 'country_feed_upload',
					'event_type' => 'write_country_temp_feed_file',
					'extra_data' => [
						'country_code'  => $country_code,
						'temp_file_path' => $this->feed_writer->get_temp_file_path_for_country( $country_code ),
					],
				),
				array(
					'should_send_log_to_meta'        => true,
					'should_save_log_in_woocommerce' => false,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				),
				$exception,
			);
			throw $exception;
		} finally {
			if ( $temp_feed_file ) {
				fclose( $temp_feed_file );
			}
		}
	}

	/**
	 * Rename temporary feed file into the final feed file for a specific country.
	 * This mirrors LanguageOverrideFeedHandler::rename_temporary_feed_file_to_final_feed_file.
	 *
	 * @since 3.0.18
	 *
	 * @param string $country_code Country code
	 * @return void
	 * @throws PluginException If we can't rename the temporary feed file.
	 */
	public function rename_temporary_feed_file_to_final_feed_file( string $country_code ) {
		$file_path      = $this->feed_writer->get_file_path( $country_code );
		$temp_file_path = $this->feed_writer->get_temp_file_path_for_country( $country_code );

		if ( ! empty( $temp_file_path ) && ! empty( $file_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			$renamed = rename( $temp_file_path, $file_path );

			if ( empty( $renamed ) ) {
				throw new PluginException( __( 'Could not rename the country override feed file', 'facebook-for-woocommerce' ), 500 );
			}
		}
	}

	/**
	 * Get sample product IDs for feed generation.
	 *
	 * @param int $limit Maximum number of product IDs to return
	 * @return array Array of product IDs
	 */
	private function get_sample_product_ids( int $limit = 1000 ): array {
		$args = array(
			'post_type' => 'product',
			'post_status' => 'publish',
			'posts_per_page' => $limit,
			'fields' => 'ids',
			'meta_query' => array(
				array(
					'key' => '_price',
					'value' => '',
					'compare' => '!=',
				),
			),
		);

		return get_posts( $args );
	}

	/**
	 * Get the feed data as an array.
	 * Required by AbstractFeedHandler.
	 *
	 * @return array
	 * @since 3.0.18
	 */
	public function get_feed_data(): array {
		// For country override feeds, we handle data generation per country
		// This method is required by the abstract class but not used in our implementation
		// since we generate feeds per country in write_country_feed_file()
		return [];
	}
}
