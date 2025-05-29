<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Products;

defined( 'ABSPATH' ) || exit;

/**
 * The product sync handler.
 *
 * @since 2.0.0
 */
class Sync {


	/** @var string the prefix used in the array indexes */
	const PRODUCT_INDEX_PREFIX = 'p-';

	/** @var string the update action */
	const ACTION_UPDATE = 'UPDATE';

	/** @var string the delete action */
	const ACTION_DELETE = 'DELETE';

	/** @var array the array of requests to schedule for sync */
	protected $requests = array();


	/**
	 * Sync constructor.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		$this->add_hooks();
	}


	/**
	 * Adds needed hooks to support product sync.
	 *
	 * @since 2.0.0
	 */
	public function add_hooks() {

		add_action( 'shutdown', array( $this, 'schedule_sync' ) );

		// stock update actions
		add_action( 'woocommerce_product_set_stock', array( $this, 'handle_stock_update' ) );
		add_action( 'woocommerce_variation_set_stock', array( $this, 'handle_stock_update' ) );
	}


	/**
	 * Adds all eligible product IDs to the requests array to be created or updated.
	 *
	 * Uses the same logic that the feed handler uses to get a list of product IDs to sync.
	 *
	 * TODO: consolidate the logic to decide whether a product should be synced in one or a couple of helper methods - right now we have slightly different versions of the same code in different places {WV 2020-05-25}
	 *
	 * @see \WC_Facebook_Product_Feed::get_product_ids()
	 * @see \WC_Facebook_Product_Feed::write_product_feed_file()
	 *
	 * @since 2.0.0
	 */
	public function create_or_update_all_products() {
		$profiling_logger = facebook_for_woocommerce()->get_profiling_logger();
		$profiling_logger->start( 'create_or_update_all_products' );

		// Queue up these IDs for sync. they will only be included in the final requests if they should be synced.
		$this->create_or_update_products( \WC_Facebookcommerce_Utils::get_all_product_ids_for_sync() );

		$profiling_logger->stop( 'create_or_update_all_products' );
	}


	/**
	 * Adds the given product IDs to the requests array to be updated.
	 *
	 * @since 2.0.0
	 *
	 * @param int[] $product_ids
	 */
	public function create_or_update_products( array $product_ids ) {
		foreach ( $product_ids as $product_id ) {
			$this->requests[ $this->get_product_index( $product_id ) ] = self::ACTION_UPDATE;
		}
	}


	/**
	 * Adds the given retailer IDs to the requests array to be deleted.
	 *
	 * @since 2.0.0
	 *
	 * @param int[] $retailer_ids retailer IDs to delete
	 */
	public function delete_products( array $retailer_ids ) {

		foreach ( $retailer_ids as $retailer_id ) {
			$this->requests[ $this->get_product_index( $retailer_id ) ] = self::ACTION_DELETE;
		}
	}


	/**
	 * Adds the products with stock changes to the requests array to be updated.
	 *
	 * @since 2.1.0
	 *
	 * @param \WC_Product $product product object
	 */
	public function handle_stock_update( \WC_Product $product ) {

		// bail if not connected
		if ( ! facebook_for_woocommerce()->get_connection_handler()->is_connected() ) {
			return;
		}

		// bail if admin and not AJAX
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		// add the product to the list of products to be updated
		$this->create_or_update_products( array( $product->get_id() ) );
	}


	/**
	 * Creates a background job to sync the products in the requests array.
	 *
	 * @since 2.0.0
	 *
	 * @return \stdClass|object|null
	 */
	public function schedule_sync() {

		if ( ! empty( $this->requests ) ) {

			$job_handler = facebook_for_woocommerce()->get_products_sync_background_handler();
			$job         = $job_handler->create_job( array( 'requests' => $this->requests ) );

			$job_handler->dispatch();

			return $job;
		}
	}


	/**
	 * Gets the prefixed product ID used as the array index.
	 *
	 * @since 2.0.0
	 *
	 * @param int|string $product_id product ID
	 * @return string prefixed product index
	 */
	private function get_product_index( $product_id ) {

		return self::PRODUCT_INDEX_PREFIX . $product_id;
	}


	/**
	 * Determines whether a sync is currently in progress.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function is_sync_in_progress() {

		$jobs = facebook_for_woocommerce()->get_products_sync_background_handler()->get_jobs(
			array(
				'status' => 'processing',
			)
		);

		return ! empty( $jobs );
	}
}
