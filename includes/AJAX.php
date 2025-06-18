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
use WooCommerce\Facebook\Handlers\WhatsAppUtilityConnection;

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

		// check the status of whatsapp onboarding and update the progress
		add_action( 'wp_ajax_wc_facebook_whatsapp_onboarding_progress_check', array( $this, 'whatsapp_onboarding_progress_check' ) );

		// update the wp_options with wc_facebook_whatsapp_consent_collection_setting_status to enabled
		add_action( 'wp_ajax_wc_facebook_whatsapp_consent_collection_enable', array( $this, 'whatsapp_consent_collection_enable' ) );

		// fetch url info - waba id and business id
		add_action( 'wp_ajax_wc_facebook_whatsapp_fetch_url_info', array( $this, 'wc_facebook_whatsapp_fetch_url_info' ) );

		// action to fetch required info and make api call to meta to finish onboarding
		add_action( 'wp_ajax_wc_facebook_whatsapp_finish_onboarding', array( $this, 'wc_facebook_whatsapp_finish_onboarding' ) );

		// fetch configured library template info
		add_action( 'wp_ajax_wc_facebook_whatsapp_fetch_library_template_info', array( $this, 'whatsapp_fetch_library_template_info' ) );

		// action to create or update utility event config info
		add_action( 'wp_ajax_wc_facebook_whatsapp_upsert_event_config', array( $this, 'whatsapp_upsert_event_config' ) );

		// search a product's attributes for the given term
		add_action( 'wp_ajax_' . self::ACTION_SEARCH_PRODUCT_ATTRIBUTES, array( $this, 'admin_search_product_attributes' ) );

		// update the wp_options with wc_facebook_whatsapp_consent_collection_setting_status to disabled
		add_action( 'wp_ajax_wc_facebook_whatsapp_consent_collection_disable', array( $this, 'whatsapp_consent_collection_disable' ) );

		// disconnect whatsapp account from woocommcerce app
		add_action( 'wp_ajax_wc_facebook_disconnect_whatsapp', array( $this, 'wc_facebook_disconnect_whatsapp' ) );

		// get supported languages for whatsapp templates
		add_action( 'wp_ajax_wc_facebook_whatsapp_fetch_supported_languages', array( $this, 'whatsapp_fetch_supported_languages' ) );
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
		// Allow opt-out of full batch-API sync, for example if store has a large number of products.
		if ( ! facebook_for_woocommerce()->get_integration()->allow_full_batch_api_sync() ) {
			wp_send_json_error( __( 'Full product sync disabled by filter.', 'facebook-for-woocommerce' ) );
			return;
		}

		check_admin_referer( Product_Sync::ACTION_SYNC_PRODUCTS, 'nonce' );

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
		check_admin_referer( Shops::ACTION_SYNC_COUPONS, 'nonce' );

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
		check_admin_referer( Shops::ACTION_SYNC_SHIPPING_PROFILES, 'nonce' );

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
		check_admin_referer( Shops::ACTION_SYNC_NAVIGATION_MENU, 'nonce' );

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
	 * Get data for creating the billing or whatsapp manager url for whatsapp account.
	 *
	 * @internal
	 *
	 * @since 1.10.0
	 */
	public function wc_facebook_whatsapp_fetch_url_info() {
		wc_get_logger()->info(
			sprintf(
				__( 'Fetching url info(WABA ID+BusinessID) for whatsapp pages', 'facebook-for-woocommerce' )
			)
		);
		facebook_for_woocommerce()->log( '' );
		if ( ! check_ajax_referer( 'facebook-for-wc-whatsapp-billing-nonce', 'nonce', false ) && ! check_ajax_referer( 'facebook-for-wc-whatsapp-templates-nonce', 'nonce', false ) && ! check_ajax_referer( 'facebook-for-wc-whatsapp-disconnect-nonce', 'nonce', false ) ) {
			wc_get_logger()->info(
				sprintf(
					__( 'Nonce Verification Error while Fetching Url Info', 'facebook-for-woocommerce' )
				)
			);
			wp_send_json_error( 'Invalid security token sent.' );
		}

		$waba_id     = get_option( 'wc_facebook_wa_integration_waba_id', null );
		$business_id = get_option( 'wc_facebook_wa_integration_business_id', null );

		if ( empty( $waba_id ) || empty( $business_id ) ) {
			wc_get_logger()->info(
				sprintf(
					__( 'Missing Waba ID + Business ID during Fetch Url Info. Whatsapp Onboarding is not complete or has failed.', 'facebook-for-woocommerce' )
				)
			);
			wp_send_json_error( 'Whatsapp onboarding is not complete or has failed.' );
		}

		$response = array(
			'waba_id'     => $waba_id,
			'business_id' => $business_id,
		);

		wp_send_json_success( $response );
	}

	/**
	 * Get data for for finish onboarding call and make api call.
	 *
	 * @internal
	 *
	 * @since 1.10.0
	 */
	public function wc_facebook_whatsapp_finish_onboarding() {
		wc_get_logger()->info(
			sprintf(
				__( 'Getting data for Whatsapp Finish Onboarding Done Button Click', 'facebook-for-woocommerce' )
			)
		);
		if ( ! check_ajax_referer( 'facebook-for-wc-whatsapp-finish-nonce', 'nonce', false ) ) {
			wc_get_logger()->info(
				sprintf(
					__( 'Nonce Verification Error in Finish Onboarding Flow', 'facebook-for-woocommerce' )
				)
			);
			wp_send_json_error( 'Invalid security token sent.' );
		}
		$external_business_id = get_option( 'wc_facebook_external_business_id', null );
		$wacs_id              = get_option( 'wc_facebook_wa_integration_wacs_id', null );
		$waba_id              = get_option( 'wc_facebook_wa_integration_waba_id', null );
		$bisu_token           = get_option( 'wc_facebook_wa_integration_bisu_access_token', null );
		if ( empty( $external_business_id ) || empty( $wacs_id ) || empty( $waba_id ) || empty( $bisu_token ) ) {
			wc_get_logger()->info(
				sprintf(
					__( 'Finish Onboarding - Onboarding is not complete or has failed.', 'facebook-for-woocommerce' ),
				)
			);
			wp_send_json_error( 'Onboarding Flow is not complete or has failed.' );
		}
		WhatsAppUtilityConnection::wc_facebook_whatsapp_connect_utility_messages_call( $waba_id, $wacs_id, $external_business_id, $bisu_token );
	}


	/**
	 * Checks if the onboarding for whatsapp is complete once business has initiated onboarding.
	 *
	 * @internal
	 *
	 * @since 1.10.0
	 */
	public function whatsapp_onboarding_progress_check() {
		if ( ! check_ajax_referer( 'facebook-for-wc-whatsapp-onboarding-progress-nonce', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid security token sent.' );
		}
		$waba_id          = get_option( 'wc_facebook_wa_integration_waba_id', null );
		$is_payment_setup = (bool) get_option( 'wc_facebook_wa_integration_is_payment_setup', null );
		if ( ! empty( $waba_id ) ) {
			wp_send_json_success(
				array(
					'message'          => 'WhatsApp onboarding is complete',
					'is_payment_setup' => $is_payment_setup,
				)
			);
		}
		wp_send_json_error( 'WhatsApp onboarding is not complete' );
	}

	public function whatsapp_consent_collection_enable() {
		wc_get_logger()->info(
			sprintf(
				__( 'Enabling Whatsapp Consent Collection in Checkout Flow', 'facebook-for-woocommerce' )
			)
		);
		if ( ! check_ajax_referer( 'facebook-for-wc-whatsapp-consent-nonce', 'nonce', false ) ) {
			wc_get_logger()->info(
				sprintf(
					__( 'Nonce Verification Error in Whatsapp Consent Collection', 'facebook-for-woocommerce' )
				)
			);
			wp_send_json_error( 'Invalid security token sent.' );
		}
		if ( get_option( 'wc_facebook_whatsapp_consent_collection_setting_status' ) !== 'enabled' ) {
			update_option( 'wc_facebook_whatsapp_consent_collection_setting_status', 'enabled' );
		}
		$is_payment_setup = (bool) get_option( 'wc_facebook_wa_integration_is_payment_setup', null );
		wc_get_logger()->info(
			sprintf(
				__( 'Whatsapp Consent Collection Enabled Successfully in Checkout Flow', 'facebook-for-woocommerce' )
			)
		);
		wp_send_json_success(
			array(
				'message'          => 'Whatsapp Consent Collection Enabled Successfully in Checkout Flow',
				'is_payment_setup' => $is_payment_setup,
			)
		);
	}

	public function whatsapp_consent_collection_disable() {
		wc_get_logger()->info(
			sprintf(
				__( 'Disabling Whatsapp Consent Collection in Utility Settings View', 'facebook-for-woocommerce' )
			)
		);
		if ( ! check_ajax_referer( 'facebook-for-wc-whatsapp-consent-disable-nonce', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid security token sent.' );
		}
		if ( get_option( 'wc_facebook_whatsapp_consent_collection_setting_status' ) !== 'disabled' ) {
			update_option( 'wc_facebook_whatsapp_consent_collection_setting_status', 'disabled' );
		}
		wc_get_logger()->info(
			sprintf(
				__( 'Whatsapp Consent Collection Disabled Successfully in Utility Settings View', 'facebook-for-woocommerce' )
			)
		);
		wp_send_json_success();
	}

	/**
	 * Disconnect Whatsapp from WooCommerce.
	 *
	 * @internal
	 *
	 * @since 1.10.0
	 */
	public function wc_facebook_disconnect_whatsapp() {
		wc_get_logger()->info(
			sprintf(
				__( 'Diconnecting Whatsapp From Woocommerce', 'facebook-for-woocommerce' )
			)
		);
		if ( ! check_ajax_referer( 'facebook-for-wc-whatsapp-disconnect-nonce', 'nonce', false ) ) {
			wc_get_logger()->info(
				sprintf(
					__( 'Nonce Verification Failed while Diconnecting Whatsapp From Woocommerce', 'facebook-for-woocommerce' )
				)
			);
			wp_send_json_error( 'Invalid security token sent.' );
		}

		$integration_config_id = get_option( 'wc_facebook_wa_integration_config_id', null );
		$bisu_token            = get_option( 'wc_facebook_wa_integration_bisu_access_token', null );
		$waba_id               = get_option( 'wc_facebook_wa_integration_waba_id', null );
		if ( empty( $integration_config_id ) || empty( $bisu_token ) || empty( $waba_id ) ) {
			wc_get_logger()->info(
				sprintf(
					__( 'Missing Integration Config ID, BISU token, WABA ID while Diconnecting Whatsapp From Woocommerce', 'facebook-for-woocommerce' )
				)
			);
			wp_send_json_error( 'Missing integration_config_id or bisu_token or waba_id for Disconnect API call' );
		}
		WhatsAppUtilityConnection::wc_facebook_disconnect_whatsapp( $waba_id, $integration_config_id, $bisu_token );
	}

	public function whatsapp_fetch_library_template_info() {
		facebook_for_woocommerce()->log( 'Fetching library template data for whatsapp utility event' );
		if ( ! check_ajax_referer( 'facebook-for-wc-whatsapp-events-nonce', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid security token sent.' );
		}
		$bisu_token = get_option( 'wc_facebook_wa_integration_bisu_access_token', null );
		if ( empty( $bisu_token ) ) {
			wp_send_json_error( 'Missing access token for Library template API call' );
		}
		// Get POST parameters from the request
		$event = isset( $_POST['event'] ) ? wc_clean( wp_unslash( $_POST['event'] ) ) : '';
		WhatsAppUtilityConnection::get_template_library_content( $event, $bisu_token );
	}

	public function whatsapp_fetch_supported_languages() {
		wc_get_logger()->info(
			sprintf(
				__( 'Fetching supported languages for WhatsApp Utility Templates', 'facebook-for-woocommerce' )
			)
		);
		if ( ! check_ajax_referer( 'facebook-for-wc-whatsapp-events-nonce', 'nonce', false ) ) {
			wc_get_logger()->info(
				sprintf(
					__( 'Nonce Verification Failed while fetching supported languages for WhatsApp Utility Templates', 'facebook-for-woocommerce' )
				)
			);
			wp_send_json_error( 'Invalid security token sent.' );
		}
		$bisu_token            = get_option( 'wc_facebook_wa_integration_bisu_access_token', null );
		$integration_config_id = get_option( 'wc_facebook_wa_integration_config_id', null );
		if ( empty( $bisu_token ) || empty( $integration_config_id ) ) {
			wc_get_logger()->info(
				sprintf(
					__( 'Missing Integration Config ID, BISU token, WABA ID for Integration Config Get API call', 'facebook-for-woocommerce' )
				)
			);
			wp_send_json_error( 'Missing integration_config_id or bisu_token for Integration Config Get API call', 'facebook-for-woocommerce' );
		}
		WhatsAppUtilityConnection::get_supported_languages_for_templates( $integration_config_id, $bisu_token );
	}

	/**
	 * Creates or Updates WhatsApp Utility Event Configs
	 *
	 * @internal
	 *
	 * @since 1.10.0
	 */
	public function whatsapp_upsert_event_config() {
		facebook_for_woocommerce()->log( 'Calling POST API to upsert whatsapp utility event' );
		if ( ! check_ajax_referer( 'facebook-for-wc-whatsapp-events-nonce', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid security token sent.' );
		}
		// Get BISU token
		$bisu_token = get_option( 'wc_facebook_wa_integration_bisu_access_token', null );
		if ( empty( $bisu_token ) ) {
			wp_send_json_error( 'Missing access token for Event Configs POST API call' );
		}
		// Get Integration Config id
		$integration_config_id = get_option( 'wc_facebook_wa_integration_config_id', null );
		if ( empty( $integration_config_id ) ) {
			wp_send_json_error( 'Missing Integration Config for Event Configs POST API call' );
		}
		// Get POST parameters from the request
		$event    = isset( $_POST['event'] ) ? wc_clean( wp_unslash( $_POST['event'] ) ) : '';
		$language = isset( $_POST['language'] ) ? wc_clean( wp_unslash( $_POST['language'] ) ) : '';
		$status   = isset( $_POST['status'] ) ? wc_clean( wp_unslash( $_POST['status'] ) ) : '';
		if ( empty( $event ) || empty( $language ) || empty( $status ) ) {
			wp_send_json_error( 'Missing request parameters for Event Configs POST API call' );
		}
		WhatsAppUtilityConnection::post_whatsapp_utility_messages_event_configs_call( $event, $integration_config_id, $language, $status, $bisu_token );
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
