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

use WooCommerce\Facebook\Feed\AbstractFeedFileWriter;

/**
 * Country Override Feed Writer.
 *
 * Handles file path management and naming conventions for country override feeds.
 * Extends AbstractFeedFileWriter to maintain consistency with the project architecture.
 *
 * @since 3.0.18
 */
class CountryOverrideFeedWriter extends AbstractFeedFileWriter {

	/** @var string File name template for country override feeds */
	const FILE_NAME = 'country_override_%s_%s.csv';

	/** @var string Current country code for this writer instance */
	private string $country_code;

	/**
	 * Constructor.
	 *
	 * @param string $country_code Country code for this feed writer
	 * @param string $header_row The headers for the feed csv
	 * @param string $delimiter Optional. The field delimiter. Default: comma.
	 * @param string $enclosure Optional. The field enclosure. Default: double quotes.
	 * @param string $escape_char Optional. The escape character. Default: backslash.
	 *
	 * @since 3.0.18
	 */
	public function __construct( string $country_code, string $header_row, string $delimiter = ',', string $enclosure = '"', string $escape_char = '\\' ) {
		$this->country_code = strtoupper( $country_code );

		$feed_name = 'country_override_' . strtolower( $country_code );

		parent::__construct( $feed_name, $header_row, $delimiter, $enclosure, $escape_char );
	}

	/**
	 * Gets the country override feed file path for the current country.
	 *
	 * @since 3.0.18
	 *
	 * @param string $country_code Optional. Country code (for compatibility)
	 * @return string
	 */
	public function get_file_path( string $country_code = '' ): string {
		// Use the instance country code if none provided
		if ( empty( $country_code ) ) {
			$country_code = $this->country_code;
		}

		return parent::get_file_path();
	}

	/**
	 * Gets the country override feed file name for the current country.
	 *
	 * @since 3.0.18
	 *
	 * @return string
	 */
	public function get_file_name(): string {
		// Use the country override feed secret
		$feed_secret = $this->get_country_feed_secret();
		$file_name = sprintf( self::FILE_NAME, strtolower( $this->country_code ), $feed_secret );

		/**
		 * Filters the country override feed file name.
		 *
		 * @since 3.0.18
		 *
		 * @param string $file_name the file name
		 * @param string $country_code the country code
		 */
		return apply_filters( 'wc_facebook_country_override_feed_file_name', $file_name, $this->country_code );
	}

	/**
	 * Gets the country override temporary feed file name for the current country.
	 *
	 * @since 3.0.18
	 *
	 * @return string
	 */
	public function get_temp_file_name(): string {
		// Use the country override feed secret
		$feed_secret = $this->get_country_feed_secret();
		$file_name = sprintf( self::FILE_NAME, 'temp_' . strtolower( $this->country_code ), wp_hash( $feed_secret ) );

		/**
		 * Filters the country override temporary feed file name.
		 *
		 * @since 3.0.18
		 *
		 * @param string $file_name the temporary file name
		 * @param string $country_code the country code
		 */
		return apply_filters( 'wc_facebook_country_override_temp_feed_file_name', $file_name, $this->country_code );
	}

	/**
	 * Gets the country override feed file path for a specific country.
	 *
	 * @since 3.0.18
	 *
	 * @param string $specific_country_code Country code to get path for
	 * @return string
	 */
	public function get_file_path_for_country( string $specific_country_code ): string {
		$feed_secret = $this->get_country_feed_secret();
		$file_name = sprintf( self::FILE_NAME, strtolower( $specific_country_code ), $feed_secret );

		return trailingslashit( $this->get_file_directory() ) . $file_name;
	}

	/**
	 * Gets the country override temporary feed file path for a specific country.
	 *
	 * @since 3.0.18
	 *
	 * @param string $specific_country_code Country code to get temp path for
	 * @return string
	 */
	public function get_temp_file_path_for_country( string $specific_country_code ): string {
		$feed_secret = $this->get_country_feed_secret();
		$file_name = sprintf( self::FILE_NAME, 'temp_' . strtolower( $specific_country_code ), wp_hash( $feed_secret ) );

		return trailingslashit( $this->get_file_directory() ) . $file_name;
	}

	/**
	 * Get country feed secret from the CountryOverrideFeed class.
	 *
	 * @since 3.0.18
	 * @return string
	 */
	private function get_country_feed_secret(): string {
		$secret_option_name = 'wc_facebook_feed_url_secret_country_override';

		$secret = get_option( $secret_option_name, '' );
		if ( ! $secret ) {
			$secret = wp_hash( 'country-override-feed-' . time() );
			update_option( $secret_option_name, $secret );
		}

		return $secret;
	}

	/**
	 * Write to the temp feed file.
	 *
	 * @param array $data The data to write to the feed file.
	 * @since 3.0.18
	 */
	public function write_temp_feed_file( array $data ): void {
		$temp_file_path = $this->get_temp_file_path();

		// phpcs:ignore -- use php file i/o functions
		$temp_feed_file = fopen( $temp_file_path, 'a' );

		if ( ! $temp_feed_file ) {
			throw new \WooCommerce\Facebook\Framework\Plugin\Exception( "Could not open temp file for writing: {$temp_file_path}", 500 );
		}

		try {
			foreach ( $data as $row ) {
				if ( fputcsv( $temp_feed_file, $row, $this->delimiter, $this->enclosure, $this->escape_char ) === false ) {
					throw new \WooCommerce\Facebook\Framework\Plugin\Exception( "Failed to write row to temp file: {$temp_file_path}", 500 );
				}
			}
		} finally {
			// phpcs:ignore -- use php file i/o functions
			fclose( $temp_feed_file );
		}
	}

	/**
	 * Get the current country code.
	 *
	 * @since 3.0.18
	 * @return string
	 */
	public function get_country_code(): string {
		return $this->country_code;
	}

	/**
	 * Get the feed directory for country feeds.
	 * Override parent to use a specific directory for country feeds.
	 *
	 * @since 3.0.18
	 * @return string
	 */
	public function get_file_directory(): string {
		$upload_dir = wp_upload_dir( null, false );
		$facebook_upload_path = trailingslashit( $upload_dir['basedir'] ) . 'facebook_for_woocommerce';

		return trailingslashit( $facebook_upload_path ) . 'country_feeds';
	}
}
