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
	/** Product catalog feed file directory inside the uploads folder  @var string*/
	const UPLOADS_DIRECTORY = 'facebook_for_woocommerce';

	/** Feed file name @var string*/
	const FILE_NAME = '%s_catalog_%s.csv';

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
	 * @var array
	 * @since 3.5.0
	 */
	private string $header_row;

	/**
	 * Constructor.
	 *
	 * @param string $feed_name The name of the feed.
	 * @param string $header_row The headers for the feed csv.
	 * @since 3.5.0
	 */
	public function __construct( string $feed_name, string $header_row ) {
		$this->feed_name  = $feed_name;
		$this->header_row = $header_row;
	}

	/**
	 * Write the feed file.
	 *
	 * @return void
	 * @since 3.5.0
	 */
	public function write_feed_file() {
		// TODO: Implement write_feed_file() method.
	}

	/**
	 * Creates files in the given feed directory to prevent directory listing and hotlinking.
	 *
	 * @since 3.5.0
	 */
	public function create_files_to_protect_feed_directory() {
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
		return trailingslashit( $uploads_directory['basedir'] ) . self::UPLOADS_DIRECTORY;
	}


	/**
	 * Gets the feed file name.
	 *
	 * @return string
	 * @since 3.5.0
	 */
	public function get_file_name(): string {
		$feed_secret = facebook_for_woocommerce()->feed_manager->get_feed_secret( $this->feed_name );
		return sprintf( self::FILE_NAME, $this->feed_name, wp_hash( $feed_secret ) );
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
		//phpcs:ignore -- current product feed does not use Wordpress file i/o functions
		$temp_feed_file = @fopen( $temp_file_path, 'w' );

		// Check if we can open the temporary feed file.
		// phpcs:ignore
		if ( false === $temp_feed_file || ! is_writable( $temp_file_path ) ) {
			// phpcs:ignore -- Escaping function for translated string not available in this context
			throw new PluginException( __( 'Could not open feed file for writing.', 'facebook-for-woocommerce' ), 500 );
		}

		$file_path = $this->get_file_path();

		// Check if we will be able to write to the final feed file.
		//phpcs:ignore -- current product feed does not use Wordpress file i/o functions
		if ( file_exists( $file_path ) && ! is_writable( $file_path ) ) {
			// phpcs:ignore -- Escaping function for translated string not available in this context
			throw new PluginException( __( 'Could not open the product catalog feed file for writing', 'facebook-for-woocommerce' ), 500 );
		}

		//phpcs:ignore -- current product feed does not use Wordpress file i/o functions
		fwrite( $temp_feed_file, $this->header_row);
		return $temp_feed_file;
	}

	/**
	 * Rename temporary feed file into the final feed file.
	 * This is the last step fo the feed generation procedure.
	 *
	 * @since 3.5.0
	 * @throws PluginException If the temporary feed file could not be renamed.
	 */
	public function promote_temp_file() {
		$file_path      = $this->get_file_path();
		$temp_file_path = $this->get_temp_file_path();
		if ( ! empty( $temp_file_path ) && ! empty( $file_path ) ) {

			// phpcs:ignore -- current product feed does not use Wordpress file i/o functions
			$renamed = rename( $temp_file_path, $file_path );

			if ( empty( $renamed ) ) {
				// phpcs:ignore -- Escaping function for translated string not available in this context
				throw new PluginException( __( 'Could not rename the product catalog feed file', 'facebook-for-woocommerce' ), 500 );
			}
		}
	}
}
