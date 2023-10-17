<?php
// phpcs:ignoreFile
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
use WooCommerce\Facebook\AdvertiseASC\NewBuyers;
use WooCommerce\Facebook\AdvertiseASC\Retargeting;
use WooCommerce\Facebook\Admin\Settings_Screens\Product_Sync;
use WooCommerce\Facebook\Framework\Plugin\Exception as PluginException;

defined( 'ABSPATH' ) or exit;

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

		// get the current sync status
		add_action( 'wp_ajax_wc_facebook_get_sync_status', array( $this, 'get_sync_status' ) );

		// get the ad preview
		add_action( 'wp_ajax_wc_facebook_get_ad_preview', array( $this, 'get_ad_preview' ) );

		add_action( 'wp_ajax_wc_facebook_generate_ad_preview', array( $this, 'generate_ad_preview' ) );

		add_action( 'wp_ajax_wc_facebook_update_ad_status', array( $this, 'update_ad_status' ) );

		// sync the ad/campaign changes with the marketing api.
		add_action ( 'wp_ajax_wc_facebook_advertise_asc_publish_changes', array( $this, 'publish_ad_changes' ));

		// search a product's attributes for the given term
		add_action( 'wp_ajax_' . self::ACTION_SEARCH_PRODUCT_ATTRIBUTES, array( $this, 'admin_search_product_attributes' ) );
	}


	/**
	 * Searches a product's attributes for the given term.
	 *
	 * @internal
	 *
	 * @since 2.1.0
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
				function( $name, $slug ) use ( $term ) {

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
	 * Syncs the changes with the Marketing Api for different ASC campaigns.
	 *
	 * Retrieves the changeset for each campaign type and posts them to the backend.
	 * Makes sure that there is something to be sent.
	 *
	 * @since x.x.x
	 *
	 * @return string
	 */
	public function publish_ad_changes() {

		$data = json_decode(file_get_contents('php://input'), true);
		$campaign_type = $data[ 'campaignType' ] ;
		$ad_message = $data[ 'adMessage' ] ;
		$daily_budget = $data[ 'dailyBudget' ] ;
		$country = $data[ 'countryList' ];
		$is_update = $data[ 'isUpdate' ] == "true";
		$status = $data['status'];
		
		try {

			if ( $is_update ) {
				$result = facebook_for_woocommerce()->get_advertise_asc_handler( $campaign_type )->update_asc_campaign( array('daily_budget' => $daily_budget, 'ad_message' => $ad_message, 'country' => $country, 'state' => $status) );
			} else {
				$result = facebook_for_woocommerce()->get_advertise_asc_handler( $campaign_type )->create_asc_campaign( array('daily_budget' => $daily_budget, 'ad_message' => $ad_message, 'country' => $country, 'state' => $status) );
		    }
			wp_send_json_success( $result );

		}
		catch ( PluginException $e ) {

			wp_send_json_error( $e->getMessage() );

		}
	}

	public function update_ad_status() {
		$data = json_decode(file_get_contents('php://input'), true);
		$campaign_type = $data[ 'campaignType' ] ;
		$status = $data['status'];

		try {

			$result = facebook_for_woocommerce()->get_advertise_asc_handler( $campaign_type )->update_ad_status( $status );
			wp_send_json_success( $result );

		}
		catch ( PluginException $e ) {

			wp_send_json_error( $e->getMessage() );

		}
	}

	/**
	 * Gets the Ad Preview for a given ad in different formats and merges the results.
	 *
	 * @since x.x.x
	 *
	 * @return string
	 */
	public function get_ad_preview() {

		$view = isset( $_GET[ 'view' ] ) ? $_GET[ 'view' ] : null;
		if ( ! $view ) {
			wp_send_json_error( " No view is selected. " );
		}

		$result = $this->get_previews_and_merge( $view );

		wp_send_json_success( $result );
	}


	public function generate_ad_preview() {
		$view = $_GET[ 'view' ];
		$message = $_GET[ 'message' ];
		if ( ! $view ) {
			wp_send_json_error( " No view is selected. " );
		}
		$result = array();

		$result[] = facebook_for_woocommerce()->get_advertise_asc_handler( $view )->generate_ad_preview($message, 'MOBILE_FEED_STANDARD');
		$result[] = facebook_for_woocommerce()->get_advertise_asc_handler( $view )->generate_ad_preview($message, 'INSTAGRAM_STANDARD');

		wp_send_json_success( $result );
	}

	/**
	 * Gets the Ad Preview for an ASC Campaign in different formats and merges the results.
	 *
	 * @since x.x.x
	 *
	 * @param string $asc_campaign ASC Campaign type.
	 * @return string
	 */
	private function get_previews_and_merge ( $asc_campaign ){
		$previews = array();

		$previews[] = $this->retrieve_ad_preview( $asc_campaign, 'MOBILE_FEED_STANDARD' );
		$previews[] = $this->retrieve_ad_preview( $asc_campaign, 'INSTAGRAM_STANDARD' );

		return $previews;
	}


	/**
	 * Gets the Ad Preview for an ASC Campaign in a specific format.
	 *
	 * @since x.x.x
	 *
	 * @param string $asc_campaign ASC Campaign type.
	 * @param string $ad_format Ad Preview Format.
	 * @return string
	 */
	private function retrieve_ad_preview( $asc_campaign, $ad_format ){
		return facebook_for_woocommerce()->get_advertise_asc_handler( $asc_campaign )->get_ad_preview( $ad_format );
	}


	/**
	 * Syncs all products via AJAX.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function sync_products() {
		// Allow opt-out of full batch-API sync, for example if store has a large number of products.
		if ( ! facebook_for_woocommerce()->get_integration()->allow_full_batch_api_sync() ) {
			wp_send_json_error( __( 'Full product sync disabled by filter.', 'facebook-for-woocommerce' ) );
			return;
		}

		check_admin_referer( Product_Sync::ACTION_SYNC_PRODUCTS, 'nonce' );

		facebook_for_woocommerce()->get_products_sync_handler()->create_or_update_all_products();

		wp_send_json_success();
	}


	/**
	 * Gets the current sync status.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function get_sync_status() {

		check_admin_referer( Product_Sync::ACTION_GET_SYNC_STATUS, 'nonce' );

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
		}

		wp_send_json_success();
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

		// query for products with sync enabled, belonging to the added term IDs and not belonging to the term IDs that are already stored in the setting
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

		// products with sync enabled
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

			// products that belong to the new excluded categories
			$categories_tax_query = array(
				'taxonomy' => 'product_cat',
				'terms'    => $new_excluded_categories,
			);

			if ( $integration = facebook_for_woocommerce()->get_integration() ) {

				// products that do not belong to the saved excluded categories
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

			// products that belong to the new excluded tags
			$tags_tax_query = array(
				'taxonomy' => 'product_tag',
				'terms'    => $new_excluded_tags,
			);

			if ( $integration = facebook_for_woocommerce()->get_integration() ) {

				$save_excluded_tags = $integration->get_excluded_product_tag_ids();

				if ( ! empty( $save_excluded_tags ) ) {

					// products that do not belong to the saved excluded tags
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
