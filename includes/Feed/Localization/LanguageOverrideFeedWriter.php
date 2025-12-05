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
	 * @param string $delimiter Optional. The field delimiter. Default: comma.
	 * @param string $enclosure Optional. The field enclosure. Default: double quotes.
	 * @param string $escape_char Optional. The escape character. Default: backslash.
	 * @since 3.6.0
	 */
	public function __construct( string $language_code, string $delimiter = ',', string $enclosure = '"', string $escape_char = '\\' ) {
		$this->language_code = $language_code;

		$fb_language_code = \WooCommerce\Facebook\Locale::convert_to_facebook_language_code( $language_code );
		$feed_name = "language_override_{$fb_language_code}";

		// Use dummy header - real headers will be generated dynamically in write_language_feed_file
		$dummy_header = 'id,override'; // Minimal required columns
		parent::__construct( $feed_name, $dummy_header, $delimiter, $enclosure, $escape_char );
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
	 * @throws \WooCommerce\Facebook\Framework\Plugin\Exception If file operations fail.
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

	/**
	 * Writes the language override feed file for a specific language.
	 *
	 * @param LanguageFeedData $language_feed_data Data source
	 * @param string           $language_code Language code
	 * @return array {
	 *     @type bool $success Success status
	 *     @type int  $count   Number of products written to the feed
	 * }
	 * @since 3.6.0
	 */
	public function write_language_feed_file( LanguageFeedData $language_feed_data, string $language_code ): array {
		try {
			// Get ALL language feed data (no limit, matching legacy feed approach)
			$csv_result = $language_feed_data->get_language_csv_data( $language_code, -1, 0 );

			$product_count = count( $csv_result['data'] ?? [] );

			if ( empty( $csv_result['data'] ) ) {
				// Still create an empty file with headers
				$csv_result = array(
					'data' => array(),
					'columns' => array( 'id', 'override' ),
				);
			}

			$columns = $csv_result['columns'];

			// DYNAMIC HEADER GENERATION: Override the dummy header with actual columns
			$this->header_row = implode( ',', $columns );

			// Prepare data in the format expected by the writer
			$data = array();
			foreach ( $csv_result['data'] as $row_data ) {
				$row = array();
				foreach ( $columns as $column ) {
					$row[] = $row_data[ $column ] ?? '';
				}
				$data[] = $row;
			}

			// Use the inherited write_feed_file method from AbstractFeedFileWriter
			$this->write_feed_file( $data );

			return array(
				'success' => true,
				'count'   => $product_count,
			);

		} catch ( \Exception $e ) {
			// Log the error to Meta
			if ( class_exists( '\WooCommerce\Facebook\Framework\Logger' ) ) {
				\WooCommerce\Facebook\Framework\Logger::log(
					sprintf( 'Failed to write language feed file for %s: %s', $language_code, $e->getMessage() ),
					array(
						'language_code' => $language_code,
						'exception_message' => $e->getMessage(),
						'exception_trace' => $e->getTraceAsString(),
					),
					array(
						'should_send_log_to_meta'        => true,
						'should_save_log_in_woocommerce' => true,
						'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
					),
					$e
				);
			}

			return array(
				'success' => false,
				'count'   => 0,
			);
		}
	}
}
