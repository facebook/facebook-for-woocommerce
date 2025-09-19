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
 * Country Override Feed Generator.
 *
 * Extends FeedGenerator to handle country override feed generation using the Action Scheduler framework.
 *
 * @since 3.0.18
 */
class CountryOverrideFeedGenerator extends FeedGenerator {

	/** @var \WooCommerce\Facebook\Feed\Localization\CountryFeedData */
	private $country_feed_data;

	/** @var string Current country code being processed */
	private $current_country_code;

	/**
	 * Constructor.
	 *
	 * @param ActionSchedulerInterface $action_scheduler The action scheduler instance.
	 * @param AbstractFeedFileWriter   $feed_writer The feed writer instance.
	 * @param string                   $feed_name The name of the feed.
	 * @param CountryFeedData          $country_feed_data The country feed data instance.
	 * @param string                   $country_code The country code for this generator instance.
	 *
	 * @since 3.0.18
	 */
	public function __construct(
		ActionSchedulerInterface $action_scheduler,
		AbstractFeedFileWriter $feed_writer,
		string $feed_name,
		CountryFeedData $country_feed_data,
		string $country_code
	) {
		parent::__construct( $action_scheduler, $feed_writer, $feed_name );
		$this->country_feed_data = $country_feed_data;
		$this->current_country_code = $country_code;
	}

	/**
	 * Get a set of items for the batch.
	 *
	 * @param int   $batch_number The batch number increments for each new batch in the job cycle.
	 * @param array $args The args for the job.
	 * @return array
	 * @since 3.0.18
	 */
	protected function get_items_for_batch( int $batch_number, array $args ): array {
		$batch_size = $this->get_batch_size();
		$offset = ( $batch_number - 1 ) * $batch_size;

		// Get product IDs for this batch
		$product_ids = $this->get_product_ids_for_batch( $batch_size, $offset );

		if ( empty( $product_ids ) ) {
			return [];
		}

		// Get CSV data for the current country
		$csv_data = $this->country_feed_data->get_country_csv_data( $this->current_country_code, $product_ids );

		if ( empty( $csv_data ) ) {
			return [];
		}

		// Convert the CSV data to the format expected by the feed writer
		$items = [];
		foreach ( $csv_data as $row_data ) {
			$row = [
				$row_data['id'],
				$row_data['override'],
				$row_data['price']
			];
			$items[] = $row;
		}

		return $items;
	}

	/**
	 * Get product IDs for a specific batch.
	 *
	 * @param int $batch_size Number of products to get
	 * @param int $offset Starting offset
	 * @return array Array of product IDs
	 */
	private function get_product_ids_for_batch( int $batch_size, int $offset ): array {
		$args = array(
			'post_type' => 'product',
			'post_status' => 'publish',
			'posts_per_page' => $batch_size,
			'offset' => $offset,
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
	 * Get the job's batch size.
	 *
	 * @return int
	 * @since 3.0.18
	 */
	protected function get_batch_size(): int {
		/**
		 * Filters the batch size for country override feed generation.
		 *
		 * @since 3.0.18
		 *
		 * @param int $batch_size The batch size.
		 */
		return apply_filters( 'wc_facebook_country_override_feed_batch_size', 100 );
	}

	/**
	 * Get the name/slug of the job.
	 *
	 * @return string
	 * @since 3.0.18
	 */
	public function get_name(): string {
		return $this->feed_name . '_' . $this->current_country_code . '_feed_generator';
	}
}
