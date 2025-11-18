<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook;

use WooCommerce\Facebook\Framework\Helper;
use WooCommerce\Facebook\Admin\Settings_Screens\Product_Sync;
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
		// maybe output a modal prompt when toggling product sync in bulk
		add_action( 'wp_ajax_facebook_for_woocommerce_set_product_sync_bulk_action_prompt', array( $this, 'handle_set_product_sync_bulk_action_prompt' ) );

		// maybe output a modal prompt when setting excluded terms
		add_action( 'wp_ajax_facebook_for_woocommerce_set_excluded_terms_prompt', array( $this, 'handle_set_excluded_terms_prompt' ) );

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


	/**
	 * Maybe triggers a modal warning when the merchant toggles sync enabled status in bulk.
	 *
	 * @internal
	 *
	 * @since 1.10.0
	 */
	public function handle_set_product_sync_bulk_action_prompt() {
		check_ajax_referer( 'set-product-sync-bulk-action-prompt', 'security' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$product_ids = isset( $_POST['products'] ) ? (array) wc_clean( wp_unslash( $_POST['products'] ) ) : array();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$toggle = isset( $_POST['toggle'] ) ? (string) wc_clean( wp_unslash( $_POST['toggle'] ) ) : '';

		if ( ! empty( $product_ids ) && ! empty( $toggle ) && 'facebook_include' === $toggle ) {

			$has_excluded_term = false;

			foreach ( $product_ids as $product_id ) {
				$product = wc_get_product( $product_id );

				if ( $product instanceof \WC_Product && ! facebook_for_woocommerce()->get_product_sync_validator( $product )->passes_product_terms_check() ) {
					$has_excluded_term = true;
					break;
				}
			}

			// show modal if there's at least one product that belongs to an excluded term
			if ( $has_excluded_term ) {
				ob_start();

				?>
				<a
					id="facebook-for-woocommerce-go-to-settings"
					class="button button-large"
					href="<?php echo esc_url( add_query_arg( 'tab', Product_Sync::ID, facebook_for_woocommerce()->get_settings_url() ) ); ?>"
				><?php esc_html_e( 'Go to Settings', 'facebook-for-woocommerce' ); ?></a>
				<button
					id="facebook-for-woocommerce-cancel-sync"
					class="button button-large button-primary"
					onclick="jQuery( '.modal-close' ).trigger( 'click' )"
				><?php esc_html_e( 'Cancel', 'facebook-for-woocommerce' ); ?></button>
				<?php

				$buttons = ob_get_clean();

				wp_send_json_error(
					array(
						'message' => __( 'One or more of the selected products belongs to a category or tag that is excluded from the Facebook catalog sync. To sync these products to Facebook, please remove the category or tag exclusion from the plugin settings.', 'facebook-for-woocommerce' ),
						'buttons' => $buttons,
					)
				);
			}
		} else {
			wp_send_json_success();
		}
	}


	/**
	 * Maybe triggers a modal warning when the merchant adds terms to the excluded terms.
	 *
	 * @internal
	 *
	 * @since 1.10.0
	 */
	public function handle_set_excluded_terms_prompt() {
		check_ajax_referer( 'set-excluded-terms-prompt', 'security' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$posted_categories = isset( $_POST['categories'] ) ? wc_clean( wp_unslash( $_POST['categories'] ) ) : array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$posted_tags = isset( $_POST['tags'] ) ? wc_clean( wp_unslash( $_POST['tags'] ) ) : array();

		$new_category_ids = array();
		$new_tag_ids      = array();

		if ( ! empty( $posted_categories ) ) {
			foreach ( $posted_categories as $posted_category_id ) {
				$new_category_ids[] = sanitize_text_field( $posted_category_id );
			}
		}

		if ( ! empty( $posted_tags ) ) {
			foreach ( $posted_tags as $posted_tag_id ) {
				$new_tag_ids[] = sanitize_text_field( $posted_tag_id );
			}
		}

		$products = $this->get_products_to_be_excluded( $new_category_ids, $new_tag_ids );
		if ( ! empty( $products ) ) {

			ob_start();

			?>
			<button
				id="facebook-for-woocommerce-confirm-settings-change"
				class="button button-large button-primary facebook-for-woocommerce-confirm-settings-change"
			><?php esc_html_e( 'Exclude Products', 'facebook-for-woocommerce' ); ?></button>

			<button
				id="facebook-for-woocommerce-cancel-settings-change"
				class="button button-large button-primary"
				onclick="jQuery( '.modal-close' ).trigger( 'click' )"
			><?php esc_html_e( 'Cancel', 'facebook-for-woocommerce' ); ?></button>
			<?php

			$buttons = ob_get_clean();

			wp_send_json_error(
				array(
					'message' => sprintf(
					/* translators: Placeholder %s - <br/> tags */
						__( 'The categories and/or tags that you have selected to exclude from sync contain products that are currently synced to Facebook.%sTo exclude these products from the Facebook sync, click Exclude Products. To review the category / tag exclusion settings, click Cancel.', 'facebook-for-woocommerce' ),
						'<br/><br/>'
					),
					'buttons' => $buttons,
				)
			);

		} else {

			// the modal should not be displayed
			wp_send_json_success();
		}
	}


	/**
	 * Get the IDs of the products that would be excluded with the new settings.
	 *
	 * Queries products with sync enabled, belonging to the added term IDs
	 * and not belonging to the term IDs that are already stored in the setting.
	 *
	 * @since 1.10.0
	 *
	 * @param string[] $new_excluded_categories
	 * @param string[] $new_excluded_tags
	 * @return int[]
	 */
	private function get_products_to_be_excluded( $new_excluded_categories = array(), $new_excluded_tags = array() ) {
		$sync_enabled_meta_query = array(
			'relation' => 'OR',
			array(
				'key'   => Products::SYNC_ENABLED_META_KEY,
				'value' => 'yes',
			),
			array(
				'key'     => Products::SYNC_ENABLED_META_KEY,
				'compare' => 'NOT EXISTS',
			),
		);

		$products_query_vars = array(
			'post_type'  => 'product',
			'fields'     => 'ids',
			'meta_query' => $sync_enabled_meta_query,
		);

		if ( ! empty( $new_excluded_categories ) ) {
			$categories_tax_query = array(
				'taxonomy' => 'product_cat',
				'terms'    => $new_excluded_categories,
			);

			$integration = facebook_for_woocommerce()->get_integration();
			if ( $integration ) {
				$saved_excluded_categories = $integration->get_excluded_product_category_ids();
				if ( ! empty( $saved_excluded_categories ) ) {
					$categories_tax_query = array(
						'relation' => 'AND',
						$categories_tax_query,
						array(
							'taxonomy' => 'product_cat',
							'terms'    => $saved_excluded_categories,
							'operator' => 'NOT IN',
						),
					);
				}
			}

			$products_query_vars['tax_query'] = $categories_tax_query;
		}

		if ( ! empty( $new_excluded_tags ) ) {
			$tags_tax_query = array(
				'taxonomy' => 'product_tag',
				'terms'    => $new_excluded_tags,
			);

			$integration = facebook_for_woocommerce()->get_integration();
			if ( $integration ) {
				$save_excluded_tags = $integration->get_excluded_product_tag_ids();
				if ( ! empty( $save_excluded_tags ) ) {
					$tags_tax_query = array(
						'relation' => 'AND',
						$tags_tax_query,
						array(
							'taxonomy' => 'product_tag',
							'terms'    => $save_excluded_tags,
							'operator' => 'NOT IN',
						),
					);
				}
			}

			if ( empty( $products_query_vars['tax_query'] ) ) {
				$products_query_vars['tax_query'] = $tags_tax_query;
			} else {
				$products_query_vars['tax_query'] = array(
					'relation' => 'OR',
					$products_query_vars,
					$tags_tax_query,
				);
			}
		}

		$products_query = new \WP_Query( $products_query_vars );

		return $products_query->posts;
	}
}
