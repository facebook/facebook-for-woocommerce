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

use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionSchedulerInterface;
use WooCommerce\Facebook\Feed\FeedGenerator;
use WooCommerce\Facebook\Feed\AbstractFeedFileWriter;

/**
 * Language Override Feed Generator.
 *
 * Extends FeedGenerator to handle language override feed generation using the Action Scheduler framework.
 *
 * @since 3.6.0
 */
class LanguageOverrideFeedGenerator extends FeedGenerator {

	/** @var \WooCommerce\Facebook\Feed\Localization\LanguageFeedData */
	private $language_feed_data;

	/** @var string Current language code being processed */
	private $current_language_code;

	/**
	 * Constructor.
	 *
	 * @param ActionSchedulerInterface $action_scheduler The action scheduler instance.
	 * @param AbstractFeedFileWriter   $feed_writer The feed writer instance.
	 * @param string                   $feed_name The name of the feed.
	 * @param LanguageFeedData         $language_feed_data The language feed data instance.
	 * @param string                   $language_code The language code for this generator instance.
	 *
	 * @since 3.6.0
	 */
	public function __construct(
		ActionSchedulerInterface $action_scheduler,
		AbstractFeedFileWriter $feed_writer,
		string $feed_name,
		LanguageFeedData $language_feed_data,
		string $language_code
	) {
		parent::__construct( $action_scheduler, $feed_writer, $feed_name );
		$this->language_feed_data = $language_feed_data;
		$this->current_language_code = $language_code;
	}

	/**
	 * Get a set of items for the batch.
	 *
	 * @param int   $batch_number The batch number increments for each new batch in the job cycle.
	 * @param array $args The args for the job.
	 * @return array
	 * @since 3.6.0
	 */
	protected function get_items_for_batch( int $batch_number, array $args ): array {
		$batch_size = $this->get_batch_size();
		$offset = ( $batch_number - 1 ) * $batch_size;

		// Get CSV data for the current language
		$csv_result = $this->language_feed_data->get_language_csv_data(
			$this->current_language_code,
			$batch_size,
			$offset
		);

		if ( empty( $csv_result['data'] ) ) {
			return [];
		}

		// Convert the CSV data to the format expected by the feed writer
		$items = [];
		$columns = $csv_result['columns'];

		foreach ( $csv_result['data'] as $row_data ) {
			$row = [];
			foreach ( $columns as $column ) {
				$row[] = $row_data[ $column ] ?? '';
			}
			$items[] = $row;
		}

		return $items;
	}

	/**
	 * Get the job's batch size.
	 *
	 * @return int
	 * @since 3.6.0
	 */
	protected function get_batch_size(): int {
		/**
		 * Filters the batch size for language override feed generation.
		 *
		 * @since 3.6.0
		 *
		 * @param int $batch_size The batch size.
		 */
		return apply_filters( 'wc_facebook_language_override_feed_batch_size', 100 );
	}

	/**
	 * Get the name/slug of the job.
	 *
	 * @return string
	 * @since 3.6.0
	 */
	public function get_name(): string {
		return $this->feed_name . '_' . $this->current_language_code . '_feed_generator';
	}
}
