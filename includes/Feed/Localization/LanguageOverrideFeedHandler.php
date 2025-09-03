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
 * Language Override Feed Handler.
 *
 * Handles the generation and management of language override feed files.
 * Extends AbstractFeedHandler to maintain consistency with the project architecture.
 *
 * @since 3.6.0
 */
class LanguageOverrideFeedHandler extends AbstractFeedHandler {

	/** @var \WooCommerce\Facebook\Feed\Localization\LanguageFeedData */
	private $language_feed_data;

	/**
	 * Constructor
	 *
	 * @param \WooCommerce\Facebook\Feed\Localization\LanguageFeedData $language_feed_data
	 * @param \WooCommerce\Facebook\Feed\Localization\LanguageOverrideFeedWriter $feed_writer
	 */
	public function __construct( LanguageFeedData $language_feed_data, LanguageOverrideFeedWriter $feed_writer ) {
		$this->language_feed_data = $language_feed_data;
		$this->feed_writer = $feed_writer;
		$this->feed_type = 'language_override';
	}


	/**
	 * Generates language override feed files for all available languages.
	 * This mirrors WC_Facebook_Product_Feed::generate_feed but for language feeds.
	 *
	 * @since 3.6.0
	 */
	public function generate_feed_file(): void {
		$profiling_logger = facebook_for_woocommerce()->get_profiling_logger();
		$profiling_logger->start( 'generate_language_override_feeds' );

		Logger::log(
			'Generating language override feed files',
			[],
			array(
				'should_send_log_to_meta'        => false,
				'should_save_log_in_woocommerce' => true,
				'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
			)
		);

		try {
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
				return;
			}

			$start_time = microtime( true );

			$this->generate_language_feed_files();

			$generation_time = microtime( true ) - $start_time;
			facebook_for_woocommerce()->get_tracker()->track_feed_file_generation_time( $generation_time );

			Logger::log(
				'Language override feed files generated',
				[],
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

			do_action( 'wc_facebook_language_feed_generation_completed' );

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

		$profiling_logger->stop( 'generate_language_override_feeds' );
	}

	/**
	 * Generates the language override feed files for all available languages.
	 * This mirrors WC_Facebook_Product_Feed::generate_productfeed_file.
	 *
	 * @return bool
	 * @throws PluginException If the feed file directory can't be created or the feed files can't be written.
	 */
	public function generate_language_feed_files() {
		if ( ! wp_mkdir_p( $this->feed_writer->get_file_directory() ) ) {
			throw new PluginException( __( 'Could not create language override feed directory', 'facebook-for-woocommerce' ), 500 );
		}

		$this->create_files_to_protect_feed_directory();

		$languages = $this->language_feed_data->get_available_languages();
		$success = true;

		foreach ( $languages as $language_code ) {
			if ( ! $this->write_language_feed_file( $language_code ) ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Creates files in the language feed directory to prevent directory listing and hotlinking.
	 * This mirrors WC_Facebook_Product_Feed::create_files_to_protect_product_feed_directory.
	 *
	 * @since 3.6.0
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
	 * Writes the language override feed file for a specific language.
	 * This mirrors WC_Facebook_Product_Feed::write_product_feed_file.
	 *
	 * @since 3.6.0
	 *
	 * @param string $language_code Language code
	 * @return bool
	 */
	public function write_language_feed_file( string $language_code ) {
		try {
			// Step 1: Prepare the temporary empty feed file with header row.
			$temp_feed_file = $this->prepare_temporary_feed_file( $language_code );

			// Step 2: Write language feed data into the temporary feed file.
			$this->write_language_feed_to_temp_file( $language_code, $temp_feed_file );

			// Step 3: Rename temporary feed file to final feed file.
			$this->rename_temporary_feed_file_to_final_feed_file( $language_code );

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
			$temp_file_path = $this->feed_writer->get_temp_file_path( $language_code );
			if ( ! empty( $temp_file_path ) && file_exists( $temp_file_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $temp_file_path );
			}
		}

		return $written;
	}

	/**
	 * Prepare a fresh empty temporary feed file with the header row for a specific language.
	 * This mirrors WC_Facebook_Product_Feed::prepare_temporary_feed_file.
	 *
	 * @since 3.6.0
	 *
	 * @param string $language_code Language code
	 * @throws PluginException We can't open the file or the file is not writable.
	 * @return resource A file pointer resource.
	 */
	public function prepare_temporary_feed_file( string $language_code ) {
		$temp_file_path = $this->feed_writer->get_temp_file_path( $language_code );
		$temp_feed_file = @fopen( $temp_file_path, 'w' );

		// check if we can open the temporary feed file
		if ( false === $temp_feed_file || ! is_writable( $temp_file_path ) ) {
			throw new PluginException( __( 'Could not open the language override temporary feed file for writing', 'facebook-for-woocommerce' ), 500 );
		}

		$file_path = $this->feed_writer->get_file_path( $language_code );

		// check if we will be able to write to the final feed file
		if ( file_exists( $file_path ) && ! is_writable( $file_path ) ) {
			throw new PluginException( __( 'Could not open the language override feed file for writing', 'facebook-for-woocommerce' ), 500 );
		}

		// Get dynamic columns for this language using LanguageFeedData
		$csv_result = $this->language_feed_data->get_language_csv_data( $language_code, 5, 0 ); // Get more rows to ensure we get all columns
		$columns = $csv_result['columns'] ?? ['id', 'override'];

		// Generate header using LanguageFeedData
		$header_row = $this->language_feed_data->get_csv_header_for_columns( $columns );

		// Write header with field descriptions (each on separate line)
		fwrite( $temp_feed_file, $header_row . PHP_EOL );

		// Write column headers
		fwrite( $temp_feed_file, implode( ',', $columns ) . PHP_EOL );

		return $temp_feed_file;
	}

	/**
	 * Write language feed data into a file.
	 * This mirrors WC_Facebook_Product_Feed::write_products_feed_to_temp_file.
	 *
	 * @since 3.6.0
	 *
	 * @param string   $language_code Language code
	 * @param resource $temp_feed_file File resource
	 * @return void
	 */
	public function write_language_feed_to_temp_file( string $language_code, $temp_feed_file ) {
		try {
			// Use LanguageFeedData to get CSV data for this language
			$csv_result = $this->language_feed_data->get_language_csv_data( $language_code, 1000, 0 );

			if ( empty( $csv_result['data'] ) ) {
				return; // No data for this language
			}

			$columns = $csv_result['columns'];

			// Process and write each data row using LanguageFeedData's formatting
			foreach ( $csv_result['data'] as $row_data ) {
				$row = [];
				foreach ( $columns as $column ) {
					$value = $row_data[ $column ] ?? '';
					$row[] = $value; // LanguageFeedData already formats the values properly
				}

				if ( fputcsv( $temp_feed_file, $row ) === false ) {
					throw new PluginException( 'Failed to write a CSV data row.', 500 );
				}
			}

		} catch ( \Exception $exception ) {
			Logger::log(
				'Error while writing language override temporary feed file.',
				array(
					'event'      => 'language_feed_upload',
					'event_type' => 'write_language_temp_feed_file',
					'extra_data' => [
						'language_code'  => $language_code,
						'temp_file_path' => $this->feed_writer->get_temp_file_path( $language_code ),
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
	 * Rename temporary feed file into the final feed file for a specific language.
	 * This mirrors WC_Facebook_Product_Feed::rename_temporary_feed_file_to_final_feed_file.
	 *
	 * @since 3.6.0
	 *
	 * @param string $language_code Language code
	 * @return void
	 * @throws PluginException If we can't rename the temporary feed file.
	 */
	public function rename_temporary_feed_file_to_final_feed_file( string $language_code ) {
		$file_path      = $this->feed_writer->get_file_path( $language_code );
		$temp_file_path = $this->feed_writer->get_temp_file_path( $language_code );

		if ( ! empty( $temp_file_path ) && ! empty( $file_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			$renamed = rename( $temp_file_path, $file_path );

			if ( empty( $renamed ) ) {
				throw new PluginException( __( 'Could not rename the language override feed file', 'facebook-for-woocommerce' ), 500 );
			}
		}
	}

	/**
	 * Get the feed data as an array.
	 * Required by AbstractFeedHandler.
	 *
	 * @return array
	 * @since 3.6.0
	 */
	public function get_feed_data(): array {
		// For language override feeds, we handle data generation per language
		// This method is required by the abstract class but not used in our implementation
		// since we generate feeds per language in write_language_feed_file()
		return [];
	}
}
