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
 * Language Override Feed Writer.
 *
 * Extends AbstractFeedFileWriter to leverage its file handling methods.
 * Creates a separate writer instance per language to work with the parent's design.
 * This approach keeps the benefits of inheritance while working with the language requirement.
 *
 * @since 3.6.0
 */
class LanguageOverrideFeedWriter extends AbstractFeedFileWriter {

	// Use the trait for consistent file naming
	use LanguageFeedManagementTrait;

	/** @var string FILE_NAME constant required by parent class */
	const FILE_NAME = 'facebook_language_feed_%s_%s.csv';

	/** @var string Language code for this writer instance */
	private $language_code;

	/**
	 * Constructor.
	 *
	 * @param string $language_code Language code for this writer instance
	 * @param string $header_row CSV header row
	 * @param string $delimiter Optional. The field delimiter. Default: comma.
	 * @param string $enclosure Optional. The field enclosure. Default: double quotes.
	 * @param string $escape_char Optional. The escape character. Default: backslash.
	 * @since 3.6.0
	 */
	public function __construct( string $language_code, string $header_row, string $delimiter = ',', string $enclosure = '"', string $escape_char = '\\' ) {
		$this->language_code = $language_code;

		$fb_language_code = \WooCommerce\Facebook\Locale::convert_to_facebook_language_code( $language_code );
		$feed_name = "language_override_{$fb_language_code}";

		parent::__construct( $feed_name, $header_row, $delimiter, $enclosure, $escape_char );
	}

	/**
	 * Override the parent's get_file_name method to use the trait's naming convention.
	 *
	 * @return string
	 * @since 3.6.0
	 */
	public function get_file_name(): string {
		// Use consistent naming from the trait
		$file_name = self::generate_language_feed_filename( $this->language_code, false, false );

		/**
		 * Filters the language override feed file name.
		 *
		 * @since 3.6.0
		 *
		 * @param string $file_name the file name
		 * @param string $language_code the language code
		 */
		return apply_filters( 'wc_facebook_language_override_feed_file_name', $file_name, $this->language_code );
	}

	/**
	 * Override the parent's get_temp_file_name method to use the trait's naming convention.
	 *
	 * @return string
	 * @since 3.6.0
	 */
	public function get_temp_file_name(): string {
		// Use consistent naming from the trait for temp files
		$file_name = self::generate_language_feed_filename( $this->language_code, false, true );

		/**
		 * Filters the language override temporary feed file name.
		 *
		 * @since 3.6.0
		 *
		 * @param string $file_name the temporary file name
		 * @param string $language_code the language code
		 */
		return apply_filters( 'wc_facebook_language_override_temp_feed_file_name', $file_name, $this->language_code );
	}

	/**
	 * Implement the parent's abstract write_temp_feed_file method.
	 * This gets called by the parent's write_feed_file orchestration method.
	 *
	 * @param array $data The data to write to the feed file.
	 * @since 3.6.0
	 */
	public function write_temp_feed_file( array $data ): void {
		$temp_file_path = $this->get_temp_file_path();
		$temp_feed_file = @fopen( $temp_file_path, 'a' );

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
			fclose( $temp_feed_file );
		}
	}

	/**
	 * Get the current language code for this writer instance.
	 *
	 * @return string
	 * @since 3.6.0
	 */
	public function get_language_code(): string {
		return $this->language_code;
	}
}
