<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook;

use WooCommerce\Facebook\Framework\Helper;
use WooCommerce\Facebook\Admin\Settings_Screens\Shops;
use WooCommerce\Facebook\Framework\Plugin\Exception as PluginException;

defined( 'ABSPATH' ) || exit;

/**
 * AJAX handler.
 *
 * @since 1.10.0
 */
class AJAX {

	/** @var string the product attribute search AJAX action */
	const ACTION_SEARCH_PRODUCT_ATTRIBUTES = 'wc_facebook_search_product_attributes';

	/**
	 * AJAX handler constructor.
	 *
	 * @since 1.10.0
	 */
	public function __construct() {
		// sync all products via AJAX
		add_action( 'wp_ajax_wc_facebook_sync_products', array( $this, 'sync_products' ) );

		// sync all coupons via AJAX
		add_action( 'wp_ajax_wc_facebook_sync_coupons', array( $this, 'sync_coupons' ) );

		// sync all shipping profiles via AJAX
		add_action( 'wp_ajax_wc_facebook_sync_shipping_profiles', array( $this, 'sync_shipping_profiles' ) );

		// sync navigation menu via AJAX
		add_action( 'wp_ajax_wc_facebook_sync_navigation_menu', array( $this, 'sync_navigation_menu' ) );

		// get the current sync status
		add_action( 'wp_ajax_wc_facebook_get_sync_status', array( $this, 'get_sync_status' ) );

		// search a product's attributes for the given term
		add_action( 'wp_ajax_' . self::ACTION_SEARCH_PRODUCT_ATTRIBUTES, array( $this, 'admin_search_product_attributes' ) );
	}


	/**
	 * Searches a product's attributes for the given term.
	 *
	 * @internal
	 *
	 * @since 2.1.0
	 *
	 * @throws PluginException If the nonce is invalid or a search term is not provided.
	 */
	public function admin_search_product_attributes() {
		try {
			if ( ! wp_verify_nonce( Helper::get_requested_value( 'security' ), self::ACTION_SEARCH_PRODUCT_ATTRIBUTES ) ) {
				throw new PluginException( 'Invalid nonce' );
			}

			$term = Helper::get_requested_value( 'term' );
			if ( ! $term ) {
				throw new PluginException( 'A search term is required' );
			}

			$product = wc_get_product( (int) Helper::get_requested_value( 'request_data' ) );
			if ( ! $product instanceof \WC_Product ) {
				throw new PluginException( 'A valid product ID is required' );
			}

			$attributes = Admin\Products::get_available_product_attribute_names( $product );
			// filter out any attributes whose slug or proper name don't at least partially match the search term
			$results = array_filter(
				$attributes,
				function ( $name, $slug ) use ( $term ) {
					return false !== stripos( $name, $term ) || false !== stripos( $slug, $term );
				},
				ARRAY_FILTER_USE_BOTH
			);
			wp_send_json( $results );
		} catch ( PluginException $exception ) {
			die();
		}
	}

	/**
	 * Syncs all products via AJAX.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function sync_products() {
		if ( ! \WC_Facebookcommerce_Utils::is_legit_ajax_call( Shops::ACTION_SYNC_PRODUCTS ) ) {
			wp_send_json_error( 'Permission denied' );
		}
		// Allow opt-out of full batch-API sync, for example if store has a large number of products.
		if ( ! facebook_for_woocommerce()->get_integration()->allow_full_batch_api_sync() ) {
			wp_send_json_error( __( 'Full product sync disabled by filter.', 'facebook-for-woocommerce' ) );
			return;
		}

		try {
			facebook_for_woocommerce()->get_products_sync_handler()->create_or_update_all_products();
			wp_send_json_success();
		} catch ( \Exception $exception ) {
			wp_send_json_error( $exception->getMessage() );
		}
	}

	/**
	 * Syncs all coupons via AJAX.
	 *
	 * @internal
	 *
	 * @since 3.5.0
	 */
	public function sync_coupons() {
		if ( ! \WC_Facebookcommerce_Utils::is_legit_ajax_call( Shops::ACTION_SYNC_COUPONS ) ) {
			wp_send_json_error( 'Permission denied' );
		}
		try {
			facebook_for_woocommerce()->feed_manager->get_feed_instance( 'promotions' )->regenerate_feed();
			wp_send_json_success();
		} catch ( \Exception $exception ) {
			wp_send_json_error( $exception->getMessage() );
		}
	}

	/**
	 * Syncs all shipping profiles via AJAX.
	 *
	 * @internal
	 *
	 * @since 3.5.0
	 */
	public function sync_shipping_profiles() {
		if ( ! \WC_Facebookcommerce_Utils::is_legit_ajax_call( Shops::ACTION_SYNC_SHIPPING_PROFILES ) ) {
			wp_send_json_error( 'Permission denied' );
		}
		try {
			facebook_for_woocommerce()->feed_manager->get_feed_instance( 'shipping_profiles' )->regenerate_feed();
			wp_send_json_success();
		} catch ( \Exception $exception ) {
			wp_send_json_error( $exception->getMessage() );
		}
	}

	/**
	 * Syncs navigation menu via AJAX.
	 *
	 * @internal
	 *
	 * @since 3.5.0
	 */
	public function sync_navigation_menu() {
		if ( ! \WC_Facebookcommerce_Utils::is_legit_ajax_call( Shops::ACTION_SYNC_NAVIGATION_MENU ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		try {
			facebook_for_woocommerce()->feed_manager->get_feed_instance( 'navigation_menu' )->regenerate_feed();
			wp_send_json_success();
		} catch ( \Exception $exception ) {
			wp_send_json_error( $exception->getMessage() );
		}
	}

	/**
	 * Gets the current sync status.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function get_sync_status() {
		if ( ! \WC_Facebookcommerce_Utils::is_legit_ajax_call( Product_Sync::ACTION_GET_SYNC_STATUS ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$remaining_products = 0;

		$jobs = facebook_for_woocommerce()->get_products_sync_background_handler()->get_jobs(
			array(
				'status' => 'processing',
			)
		);

		if ( ! empty( $jobs ) ) {
			// there should only be one processing job at a time, pluck the latest to convey status
			$job = $jobs[0];

			$remaining_products = ! empty( $job->total ) ? $job->total : count( $job->requests );

			if ( ! empty( $job->progress ) ) {
				$remaining_products -= $job->progress;
			}
		}

		wp_send_json_success( $remaining_products );
	}

}
