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

use WC_Facebookcommerce_Utils;
use WooCommerce\Facebook\Framework\Plugin\Exception as PluginException;

defined( 'ABSPATH' ) || exit;

/**
 *
 * CsvFeedFileWriter class
 * To be used by any feed handler whose feed requires a csv file.
 *
 * @since 3.5.0
 */
class CsvFeedFileWriter implements FeedFileWriter {
	/** Feed file directory inside the uploads folder  @var string */
	const UPLOADS_DIRECTORY = 'facebook_for_woocommerce/%s';

	/** Feed file name @var string */
	const FILE_NAME = '%s_feed_%s.csv';

	/**
	 * Use the feed name to distinguish which folder to write to.
	 *
	 * @var string
	 * @since 3.5.0
	 */
	private string $feed_name;

	/**
	 * Header row for the feed file.
	 *
	 * @var string
	 * @since 3.5.0
	 */
	private string $header_row;

	/**
	 * CSV delimiter.
	 *
	 * @var string
	 * @since 3.5.0
	 */
	private string $delimiter;

	/**
	 * CSV enclosure.
	 *
	 * @var string
	 * @since 3.5.0
	 */
	private string $enclosure;

	/**
	 * CSV escape character.
	 *
	 * @var string
	 * @since 3.5.0
	 */
	private string $escape_char;

	/**
	 * Constructor.
	 *
	 * @param string $feed_name The name of the feed.
	 * @param string $header_row The headers for the feed csv.
	 * @param string $delimiter Optional. The field delimiter. Default: comma.
	 * @param string $enclosure Optional. The field enclosure. Default: double quotes.
	 * @param string $escape_char Optional. The escape character. Default: backslash.
	 *
	 * @since 3.5.0
	 */
	public function __construct( string $feed_name, string $header_row, string $delimiter = ',', string $enclosure = '"', string $escape_char = '\\' ) {
		$this->feed_name   = $feed_name;
		$this->header_row  = $header_row;
		$this->delimiter   = $delimiter;
		$this->enclosure   = $enclosure;
		$this->escape_char = $escape_char;
	}

	/**
	 * Write the feed file.
	 *
	 * @param array $data The data to write to the feed file.
	 *
	 * @return void
	 * @since 3.5.0
	 */
	public function write_feed_file( array $data ): void {
		try {
			$this->create_feed_directory();
			$this->create_files_to_protect_feed_directory();

			// Step 1: Prepare the temporary empty feed file with header row.
			$temp_feed_file = $this->prepare_temporary_feed_file();

			// Step 2: Write feed into the temporary feed file.
			$this->write_temp_feed_file( $data );

			// Step 3: Rename temporary feed file to final feed file.
			$this->promote_temp_file();
		} catch ( PluginException $e ) {
			WC_Facebookcommerce_Utils::logExceptionImmediatelyToMeta( $e );
			// Close the temporary file if it is still open.
			if ( ! empty( $temp_feed_file ) && is_resource( $temp_feed_file ) ) {
				fclose( $temp_feed_file ); // phpcs:ignore
			}

			// Delete the temporary file if it exists.
			if ( ! empty( $temp_file_path ) && file_exists( $temp_file_path ) ) {
				unlink( $temp_file_path ); // phpcs:ignore
			}
		}
	}

	/**
	 * Generates the feed file.
	 *
	 * @throws PluginException If the directory could not be created.
	 * @since 3.5.0
	 */
	public function create_feed_directory(): void {
		$file_directory    = $this->get_file_directory();
		$directory_created = wp_mkdir_p( $file_directory );
		if ( ! $directory_created ) {
			// phpcs:ignore -- Escaping function for translated string not available in this context
			throw new PluginException( __( "Could not create feed directory at {$file_directory}", 'facebook-for-woocommerce' ), 500 );
		}
	}

	/**
	 * Write the feed data to the temporary feed file.
	 *
	 * @param array $data The data to write to the feed file.
	 *
	 * @return void
	 * @throws PluginException If the temporary file cannot be opened or row can't be written.
	 * @since 3.5.0
	 */
	public function write_temp_feed_file( array $data ): void {
		$temp_file_path = $this->get_temp_file_path();
		//phpcs:ignore -- use php file i/o functions
		$temp_feed_file = fopen( $temp_file_path, 'a' );
		if ( false === $temp_feed_file ) {
			// phpcs:ignore -- Escaping function for translated string not available in this context
			throw new PluginException( __( "Unable to open temporary file {$temp_file_path} for appending.", 'facebook-for-woocommerce' ), 500 );
		}

		// Convert the header row (CSV string) to an array to use as field accessors.
		$accessors = str_getcsv( $this->header_row );

		// Process and write each data row.
		foreach ( $data as $obj ) {
			$row = [];
			foreach ( $accessors as $accessor ) {
				$row[] = $obj[ $accessor ] ?? '';
			}
			if ( fputcsv( $temp_feed_file, $row, $this->delimiter, $this->enclosure, $this->escape_char ) === false ) {
				throw new PluginException( 'Failed to write a CSV data row.', 500 );
			}
		}

		// phpcs:ignore -- use php file i/o functions
		fclose( $temp_feed_file );
	}

	/**
	 * Creates files in the feed directory to prevent directory listing and hotlinking.
	 *
	 * @since 3.5.0
	 */
	public function create_files_to_protect_feed_directory(): void {
		$feed_directory = trailingslashit( $this->get_file_directory() );

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
			$file_path = trailingslashit( $file['base'] ) . $file['file'];
			if ( wp_mkdir_p( $file['base'] ) && ! file_exists( $file_path ) ) {
				// phpcs:ignore -- use php file i/o functions
				$file_handle = @fopen( $file_path, 'w' );
				if ( $file_handle ) {
					fwrite( $file_handle, $file['content'] ); //phpcs:ignore
					fclose( $file_handle ); //phpcs:ignore
				}
			}
		}
	}

	/**
	 * Gets the feed file path of given feed.
	 *
	 * @return string
	 * @since 3.5.0
	 */
	public function get_file_path(): string {
		return "{$this->get_file_directory()}/{$this->get_file_name()}";
	}


	/**
	 * Gets the temporary feed file path.
	 *
	 * @return string
	 * @since 3.5.0
	 */
	public function get_temp_file_path(): string {
		return "{$this->get_file_directory()}/{$this->get_temp_file_name()}";
	}

	/**
	 * Gets the feed file directory.
	 *
	 * @return string
	 * @since 3.5.0
	 */
	public function get_file_directory(): string {
		$uploads_directory = wp_upload_dir( null, false );

		return trailingslashit( $uploads_directory['basedir'] ) . sprintf( self::UPLOADS_DIRECTORY, $this->feed_name );
	}


	/**
	 * Gets the feed file name.
	 *
	 * @return string
	 * @since 3.5.0
	 */
	public function get_file_name(): string {
		$feed_secret = facebook_for_woocommerce()->feed_manager->get_feed_secret( $this->feed_name );

		return sprintf( self::FILE_NAME, $this->feed_name, $feed_secret );
	}

	/**
	 * Gets the temporary feed file name.
	 *
	 * @return string
	 * @since 3.5.0
	 */
	public function get_temp_file_name(): string {
		$feed_secret = facebook_for_woocommerce()->feed_manager->get_feed_secret( $this->feed_name );

		return sprintf( self::FILE_NAME, $this->feed_name, 'temp_' . wp_hash( $feed_secret ) );
	}

	/**
	 * Prepare a fresh empty temporary feed file with the header row.
	 *
	 * @throws PluginException We can't open the file or the file is not writable.
	 * @return resource A file pointer resource.
	 * @since 3.5.0
	 */
	public function prepare_temporary_feed_file() {
		$temp_file_path = $this->get_temp_file_path();
		// phpcs:ignore -- use php file i/o functions
		$temp_feed_file = @fopen( $temp_file_path, 'w' );

		// Check if we can open the temporary feed file.
		// phpcs:ignore
		if ( false === $temp_feed_file || ! is_writable( $temp_file_path ) ) {
			// phpcs:ignore -- Escaping function for translated string not available in this context
			throw new PluginException( __( "Could not open file {$temp_file_path} for writing.", 'facebook-for-woocommerce' ), 500 );
		}

		$file_path = $this->get_file_path();

		// Check if we will be able to write to the final feed file.
		// phpcs:ignore -- use php file i/o functions
		if ( file_exists( $file_path ) && ! is_writable( $file_path ) ) {
			// phpcs:ignore -- Escaping function for translated string not available in this context
			throw new PluginException( __( "Could not open file {$file_path} for writing.", 'facebook-for-woocommerce' ), 500 );
		}

		$headers = str_getcsv( $this->header_row );
		if ( fputcsv( $temp_feed_file, $headers, $this->delimiter, $this->enclosure, $this->escape_char ) === false ) {
			// phpcs:ignore -- Escaping function for translated string not available in this context
			throw new PluginException( __( "Failed to write header row to {$temp_file_path}.", 'facebook-for-woocommerce' ), 500 );
		}

		return $temp_feed_file;
	}

	/**
	 * Rename temporary feed file into the final feed file.
	 * This is the last step fo the feed generation procedure.
	 *
	 * @throws PluginException If the temporary feed file could not be renamed.
	 * @since 3.5.0
	 */
	public function promote_temp_file(): void {
		$file_path      = $this->get_file_path();
		$temp_file_path = $this->get_temp_file_path();
		if ( ! empty( $temp_file_path ) && ! empty( $file_path ) ) {
			// phpcs:ignore -- use php file i/o functions
			$renamed = rename( $temp_file_path, $file_path );
			if ( empty( $renamed ) ) {
				// phpcs:ignore -- Escaping function for translated string not available in this context
				throw new PluginException( __( "Could not promote temp file: {$temp_file_path}", 'facebook-for-woocommerce' ), 500 );
			}
		}
	}
}
