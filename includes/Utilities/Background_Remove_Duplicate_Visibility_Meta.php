<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Utilities;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\Framework\Utilities\BackgroundJobHandler;

/**
 * Background job handler to remove duplicate fb_visibility entries from the postmeta table.
 *
 * The background job handler to hide virtual products from the catalog had a bug that allowed it to create many entries for each product.
 *
 * @since 2.0.3
 */
class Background_Remove_Duplicate_Visibility_Meta extends BackgroundJobHandler {


	/**
	 * Background job constructor.
	 *
	 * @since 2.0.3
	 */
	public function __construct() {

		$this->prefix = 'wc_facebook';
		$this->action = 'background_remove_duplicate_visibility_meta';

		parent::__construct();
	}


	/**
	 * Processes job.
	 *
	 * This job continues to update products and product variations meta data until we run out of memory
	 * or exceed the time limit. There is no list of items to loop over.
	 *
	 * @since 2.0.3
	 *
	 * @param object $job
	 * @param int    $items_per_batch number of items to process in a single request. Defaults to unlimited.
	 * @return object
	 */
	public function process_job( $job, $items_per_batch = null ) {

		// don't do anything until the job used to hide virtual variations is done
		$handler = facebook_for_woocommerce()->get_background_handle_virtual_products_variations_instance();

		if ( $handler && $handler->get_jobs( array( 'status' => array( 'processing', 'queued' ) ) ) ) {
			return $job;
		}

		if ( ! isset( $job->total ) ) {
			$job->total = $this->count_remaining_products();
		}

		if ( ! isset( $job->progress ) ) {
			$job->progress = 0;
		}

		while ( $job->progress < $job->total ) {

			$job->progress += $this->remove_duplicates();

			// update job progress
			$job = $this->update_job( $job );

			if ( $this->time_exceeded() || $this->memory_exceeded() ) {
				break;
			}
		}

		// job complete! :)
		if ( $this->count_remaining_products() === 0 ) {

			update_option( 'wc_facebook_background_remove_duplicate_visibility_meta_complete', 'yes' );

			$this->complete_job( $job );
		}

		return $job;
	}


	/**
	 * Counts the number of virtual products or product variations with sync enabled and visible.
	 *
	 * @since 2.0.3
	 *
	 * @return bool
	 */
	private function count_remaining_products() {
		global $wpdb;

		$sql = "
			SELECT COUNT(post_id)
			FROM (
				SELECT post_id, COUNT(meta_key) entries
				FROM {$wpdb->postmeta}
				WHERE meta_key = 'fb_visibility'
				GROUP BY post_id
				HAVING entries > 1
			) AS duplicate_entries
		";

		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}


	/**
	 * Removes duplicate visibility meta data entries for products.
	 *
	 * @since 2.0.3
	 *
	 * @return int
	 */
	private function remove_duplicates() {
		global $wpdb;

		$results = $this->get_posts_to_update();

		if ( empty( $results ) ) {
			facebook_for_woocommerce()->log( 'There are no products or products variations with duplicate visibility meta data.' );
			return 0;
		}

		$products_updated = 0;

		foreach ( $results as $result ) {

			$sql = "DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = 'fb_visibility' AND meta_id != %d";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( false === $wpdb->query( $wpdb->prepare( $sql, $result->post_id, $result->last_meta_id ) ) ) {

				facebook_for_woocommerce()->log(
					sprintf(
						'There was an error trying to set products and variations meta data. %s',
						$wpdb->last_error
					)
				);

				continue;
			}

			++$products_updated;
		}

		return $products_updated;
	}


	/**
	 * Gets the ID of products that duplicate visibility meta data.
	 *
	 * The method also returns the number of meta data entries and ID of the last meta data entry for each product.
	 *
	 * @since 2.0.3
	 *
	 * @return array|null
	 */
	private function get_posts_to_update() {
		global $wpdb;

		$sql = "
			SELECT post_id, COUNT(meta_key) entries, MAX(meta_id) last_meta_id
			FROM {$wpdb->postmeta}
			WHERE meta_key = 'fb_visibility'
			GROUP BY post_id
			HAVING entries > 1
		";

		return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}


	/**
	 * No-op
	 *
	 * @since 2.0.3
	 * @param mixed  $item The item to process.
	 * @param object $job  The job object.
	 */
	protected function process_item( $item, $job ) {
		// void
	}
}
