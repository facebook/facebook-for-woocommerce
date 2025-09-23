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
		$temp_feed_file = null;
		$temp_file_path = '';
		$final_file_path = '';

		try {
			Logger::log(
				'Starting write_language_feed_file',
				array(
					'language_code' => $language_code,
					'step' => 'start',
				),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

			// Get paths for debugging
			$temp_file_path = $this->feed_writer->get_temp_file_path( $language_code );
			$final_file_path = $this->feed_writer->get_file_path( $language_code );
			$directory = $this->feed_writer->get_file_directory();

			Logger::log(
				'File paths determined',
				array(
					'language_code' => $language_code,
					'temp_file_path' => $temp_file_path,
					'final_file_path' => $final_file_path,
					'directory' => $directory,
					'directory_exists' => is_dir( $directory ),
					'directory_writable' => is_writable( $directory ),
					'step' => 'paths_determined',
				),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

			// Step 1: Prepare the temporary empty feed file with header row.
			Logger::log(
				'Step 1: Preparing temporary feed file',
				array(
					'language_code' => $language_code,
					'step' => 'prepare_temp_file_start',
				),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

			$temp_feed_file = $this->prepare_temporary_feed_file( $language_code );

			Logger::log(
				'Step 1 completed: Temporary feed file prepared',
				array(
					'language_code' => $language_code,
					'temp_file_exists' => file_exists( $temp_file_path ),
					'temp_file_size' => file_exists( $temp_file_path ) ? filesize( $temp_file_path ) : 'file not found',
					'temp_file_resource_valid' => is_resource( $temp_feed_file ),
					'step' => 'prepare_temp_file_complete',
				),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

			// Step 2: Write language feed data into the temporary feed file.
			Logger::log(
				'Step 2: Writing data to temporary feed file',
				array(
					'language_code' => $language_code,
					'step' => 'write_data_start',
				),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

			$this->write_language_feed_to_temp_file( $language_code, $temp_feed_file );

			Logger::log(
				'Step 2 completed: Data written to temporary feed file',
				array(
					'language_code' => $language_code,
					'temp_file_exists_after_write' => file_exists( $temp_file_path ),
					'temp_file_size_after_write' => file_exists( $temp_file_path ) ? filesize( $temp_file_path ) : 'file not found',
					'step' => 'write_data_complete',
				),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

			// Step 3: Rename temporary feed file to final feed file.
			Logger::log(
				'Step 3: Renaming temporary file to final file',
				array(
					'language_code' => $language_code,
					'temp_file_exists_before_rename' => file_exists( $temp_file_path ),
					'final_file_exists_before_rename' => file_exists( $final_file_path ),
					'step' => 'rename_start',
				),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

			$this->rename_temporary_feed_file_to_final_feed_file( $language_code );

			Logger::log(
				'Step 3 completed: File renamed successfully',
				array(
					'language_code' => $language_code,
					'temp_file_exists_after_rename' => file_exists( $temp_file_path ),
					'final_file_exists_after_rename' => file_exists( $final_file_path ),
					'final_file_size' => file_exists( $final_file_path ) ? filesize( $final_file_path ) : 'file not found',
					'step' => 'rename_complete',
				),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

			$written = true;

			Logger::log(
				'write_language_feed_file completed successfully',
				array(
					'language_code' => $language_code,
					'final_file_exists' => file_exists( $final_file_path ),
					'final_file_size' => file_exists( $final_file_path ) ? filesize( $final_file_path ) : 'file not found',
					'step' => 'complete_success',
				),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

		} catch ( \Exception $e ) {
			Logger::log(
				'Exception in write_language_feed_file',
				array(
					'language_code' => $language_code,
					'exception_message' => $e->getMessage(),
					'exception_file' => $e->getFile(),
					'exception_line' => $e->getLine(),
					'exception_trace' => $e->getTraceAsString(),
					'temp_file_exists' => file_exists( $temp_file_path ),
					'final_file_exists' => file_exists( $final_file_path ),
					'step' => 'exception_caught',
				),
				array(
					'should_send_log_to_meta'        => true,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
				),
				$e
			);

			$written = false;

			// close the temporary file
			if ( ! empty( $temp_feed_file ) && is_resource( $temp_feed_file ) ) {
				fclose( $temp_feed_file );
			}

			// delete the temporary file
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
		$temp_file_path = $this->feed_writer->get_temp_file_path( $language_code );
		$rows_written = 0;

		try {
			Logger::log(
				'Starting to write language feed data to temp file',
				array(
					'language_code' => $language_code,
					'temp_file_path' => $temp_file_path,
					'file_resource_valid' => is_resource( $temp_feed_file ),
				),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

			// Use LanguageFeedData to get CSV data for this language
			$csv_result = $this->language_feed_data->get_language_csv_data( $language_code, 1000, 0 );

			Logger::log(
				'Retrieved CSV data from LanguageFeedData',
				array(
					'language_code' => $language_code,
					'data_count' => count( $csv_result['data'] ?? [] ),
					'columns' => $csv_result['columns'] ?? [],
				),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

			if ( empty( $csv_result['data'] ) ) {
				Logger::log(
					'No data available for language - writing empty file',
					array( 'language_code' => $language_code ),
					array(
						'should_send_log_to_meta'        => false,
						'should_save_log_in_woocommerce' => true,
						'woocommerce_log_level'          => \WC_Log_Levels::INFO,
					)
				);
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
					throw new PluginException( "Failed to write CSV data row {$rows_written} to temp file.", 500 );
				}
				$rows_written++;
			}

			Logger::log(
				'Successfully wrote language feed data to temp file',
				array(
					'language_code' => $language_code,
					'temp_file_path' => $temp_file_path,
					'rows_written' => $rows_written,
					'file_size' => file_exists( $temp_file_path ) ? filesize( $temp_file_path ) : 'file not found',
				),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

		} catch ( \Exception $exception ) {
			Logger::log(
				'Error while writing language override temporary feed file.',
				array(
					'event'      => 'language_feed_upload',
					'event_type' => 'write_language_temp_feed_file',
					'extra_data' => [
						'language_code'  => $language_code,
						'temp_file_path' => $temp_file_path,
						'rows_written' => $rows_written,
						'exception_message' => $exception->getMessage(),
						'exception_trace' => $exception->getTraceAsString(),
					],
				),
				array(
					'should_send_log_to_meta'        => true,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
				),
				$exception,
			);
			throw $exception;
		} finally {
			if ( $temp_feed_file && is_resource( $temp_feed_file ) ) {
				fclose( $temp_feed_file );

				Logger::log(
					'Closed temp file handle',
					array(
						'language_code' => $language_code,
						'temp_file_path' => $temp_file_path,
						'final_file_exists' => file_exists( $temp_file_path ),
						'final_file_size' => file_exists( $temp_file_path ) ? filesize( $temp_file_path ) : 'file not found',
					),
					array(
						'should_send_log_to_meta'        => false,
						'should_save_log_in_woocommerce' => true,
						'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
					)
				);
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

		Logger::log(
			'Starting file rename process',
			array(
				'language_code' => $language_code,
				'temp_file_path' => $temp_file_path,
				'final_file_path' => $file_path,
				'temp_file_exists' => file_exists( $temp_file_path ),
				'temp_file_size' => file_exists( $temp_file_path ) ? filesize( $temp_file_path ) : 'file not found',
				'final_file_exists_before' => file_exists( $file_path ),
			),
			array(
				'should_send_log_to_meta'        => false,
				'should_save_log_in_woocommerce' => true,
				'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
			)
		);

		if ( ! empty( $temp_file_path ) && ! empty( $file_path ) ) {
			// Check if temp file exists before attempting rename
			if ( ! file_exists( $temp_file_path ) ) {
				throw new PluginException( "Temporary file does not exist: {$temp_file_path}", 500 );
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			$renamed = rename( $temp_file_path, $file_path );

			Logger::log(
				'File rename attempted',
				array(
					'language_code' => $language_code,
					'rename_success' => $renamed,
					'temp_file_exists_after' => file_exists( $temp_file_path ),
					'final_file_exists_after' => file_exists( $file_path ),
					'final_file_size' => file_exists( $file_path ) ? filesize( $file_path ) : 'file not found',
				),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

			if ( empty( $renamed ) ) {
				// Get more info about why rename failed
				$error = error_get_last();
				throw new PluginException(
					"Could not rename the language override feed file from {$temp_file_path} to {$file_path}. Last error: " .
					( $error ? $error['message'] : 'unknown' ),
					500
				);
			}
		} else {
			throw new PluginException( "Invalid file paths provided for rename operation", 500 );
		}

		Logger::log(
			'File rename completed successfully',
			array(
				'language_code' => $language_code,
				'final_file_exists' => file_exists( $file_path ),
				'final_file_size' => file_exists( $file_path ) ? filesize( $file_path ) : 'file not found',
			),
			array(
				'should_send_log_to_meta'        => false,
				'should_save_log_in_woocommerce' => true,
				'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
			)
		);
	}

	/**
	 * Get the language feed data instance.
	 *
	 * @return \WooCommerce\Facebook\Feed\Localization\LanguageFeedData
	 * @since 3.6.0
	 */
	public function get_language_feed_data(): LanguageFeedData {
		return $this->language_feed_data;
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
