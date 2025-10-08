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
use WooCommerce\Facebook\Integrations\IntegrationRegistry;

/**
 * Language Override Feed Handler.
 *
 * Handles the generation and management of language override feed files.
 * This class is specifically designed for generating multiple language-specific feed files,
 * which differs from the single-file approach used by AbstractFeedHandler.
 *
 * @since 3.6.0
 */
class LanguageOverrideFeedHandler {

	/** @var \WooCommerce\Facebook\Feed\Localization\LanguageFeedData */
	private $language_feed_data;

	/** @var \WooCommerce\Facebook\Feed\Localization\LanguageOverrideFeedWriter */
	private $feed_writer;

	/**
	 * Constructor
	 *
	 * @param \WooCommerce\Facebook\Feed\Localization\LanguageFeedData $language_feed_data
	 * @param \WooCommerce\Facebook\Feed\Localization\LanguageOverrideFeedWriter $feed_writer
	 */
	public function __construct( LanguageFeedData $language_feed_data, LanguageOverrideFeedWriter $feed_writer ) {
		$this->language_feed_data = $language_feed_data;
		$this->feed_writer = $feed_writer;
	}

	/**
	 * Writes the language override feed file for a specific language.
	 * Creates a language-specific writer instance and uses the parent's orchestration.
	 *
	 * @since 3.6.0
	 *
	 * @param string $language_code Language code
	 * @return bool
	 */
	public function write_language_feed_file( string $language_code ): bool {
		try {
			Logger::log(
				'Starting write_language_feed_file',
				array( 'language_code' => $language_code ),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

			// Get language feed data
			$csv_result = $this->language_feed_data->get_language_csv_data( $language_code, 5000, 0 );

			if ( empty( $csv_result['data'] ) ) {
				Logger::log(
					'No data available for language',
					array( 'language_code' => $language_code ),
					array(
						'should_send_log_to_meta'        => false,
						'should_save_log_in_woocommerce' => true,
						'woocommerce_log_level'          => \WC_Log_Levels::INFO,
					)
				);
				// Still create an empty file with headers
				$csv_result = array(
					'data' => array(),
					'columns' => array( 'id', 'override' )
				);
			}

			$columns = $csv_result['columns'];
			$header_row = $this->language_feed_data->get_csv_header_for_columns( $columns );

			// Create a language-specific writer instance
			$language_writer = new LanguageOverrideFeedWriter( $language_code, $header_row );

			// Prepare data in the format expected by the writer
			$data = array();
			foreach ( $csv_result['data'] as $row_data ) {
				$row = array();
				foreach ( $columns as $column ) {
					$row[] = $row_data[ $column ] ?? '';
				}
				$data[] = $row;
			}

			// Use the writer's write_feed_file method (from AbstractFeedFileWriter)
			$language_writer->write_feed_file( $data );

			Logger::log(
				'Language feed file written successfully',
				array( 'language_code' => $language_code ),
				array(
					'should_send_log_to_meta'        => false,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
				)
			);

			return true;

		} catch ( \Exception $e ) {
			Logger::log(
				'Exception in write_language_feed_file',
				array(
					'language_code' => $language_code,
					'exception_message' => $e->getMessage(),
				),
				array(
					'should_send_log_to_meta'        => true,
					'should_save_log_in_woocommerce' => true,
					'woocommerce_log_level'          => \WC_Log_Levels::ERROR,
				),
				$e
			);
			return false;
		}
	}




}
