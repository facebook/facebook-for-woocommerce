<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\Framework\Api\Exception as ApiException;

require_once __DIR__ . '/fbutils.php';

/**
 * FB Info Banner class
 */
class WC_Facebookcommerce_Info_Banner {

	const FB_NO_TIP_EXISTS           = 'No Tip Exist!';
	const DEFAULT_TIP_IMG_URL_PREFIX = 'https://www.facebook.com';
	const CHANNEL_ID                 = 2087541767986590;

	/** @var object Class instance */
	private static $instance;

	/** @var string External Merchant Settings ID */
	private $external_merchant_settings_id;

	/** @var bool if the banner should query tip */
	private $should_query_tip;

	/**
	 * Gets the class instance.
	 *
	 * @param string $external_merchant_settings_id
	 * @param bool   $should_query_tip
	 * @return \WC_Facebookcommerce_Info_Banner
	 */
	public static function get_instance( $external_merchant_settings_id, $should_query_tip = false ) {
		if ( null === self::$instance ) {
			self::$instance = new self( $external_merchant_settings_id, $should_query_tip );
		}

		return self::$instance;
	}

	/**
	 * Constructor for Info Banner class.
	 *
	 * @param string $external_merchant_settings_id
	 * @param bool   $should_query_tip
	 */
	public function __construct( $external_merchant_settings_id, $should_query_tip = false ) {
		$this->should_query_tip              = $should_query_tip;
		$this->external_merchant_settings_id = $external_merchant_settings_id;

		add_action( 'wp_ajax_ajax_woo_infobanner_post_click', array( $this, 'ajax_woo_infobanner_post_click' ) );
		add_action( 'wp_ajax_ajax_woo_infobanner_post_xout', array( $this, 'ajax_woo_infobanner_post_xout' ) );
		add_action( 'admin_notices', array( $this, 'banner' ) );
		add_action( 'admin_init', array( $this, 'dismiss_banner' ) );
	}

	/**
	 * Post click event when hit primary button.
	 */
	public function ajax_woo_infobanner_post_click() {
		WC_Facebookcommerce_Utils::check_woo_ajax_permissions(
			'post tip click event',
			true
		);

		check_ajax_referer( 'wc_facebook_infobanner_jsx' );
		$tip_info = WC_Facebookcommerce_Utils::get_cached_best_tip();
		$tip_id   = isset( $tip_info->tip_id )
		? $tip_info->tip_id
		: null;

		if ( null === $tip_id ) {
			WC_Facebookcommerce_Utils::fblog(
				'Do not have tip id when click, sth went wrong',
				array( 'tip_info' => $tip_info ),
				true
			);
		} else {
			WC_Facebookcommerce_Utils::tip_events_log(
				$tip_id,
				self::CHANNEL_ID,
				'click'
			);
		}
	}

	/**
	 * Post xout event when hit dismiss button.
	 */
	public function ajax_woo_infobanner_post_xout() {
		WC_Facebookcommerce_Utils::check_woo_ajax_permissions(
			'post tip xout event',
			true
		);

		check_ajax_referer( 'wc_facebook_infobanner_jsx' );
		$tip_info = WC_Facebookcommerce_Utils::get_cached_best_tip();
		$tip_id   = isset( $tip_info->tip_id )
		? $tip_info->tip_id
		: null;
		// Delete cached tip if xout.
		update_option( 'fb_info_banner_last_best_tip', '' );

		if ( null === $tip_id ) {
			WC_Facebookcommerce_Utils::fblog(
				'Do not have tip id when xout, sth went wrong',
				array( 'tip_info' => $tip_info ),
				true
			);
		} else {
			WC_Facebookcommerce_Utils::tip_events_log(
				$tip_id,
				self::CHANNEL_ID,
				'xout'
			);
		}
	}

	/**
	 * Display a info banner on Woocommerce pages.
	 */
	public function banner() {
		$screen = get_current_screen();
		if ( ! in_array(
			$screen->base,
			array(
				'woocommerce_page_wc-reports',
				'woocommerce_page_wc-settings',
				'woocommerce_page_wc-status',
			)
		) ||
		$screen->is_network || $screen->action ) {
			return;
		}

		$tip_info = null;
		if ( ! $this->should_query_tip ) {
			// If last query is less than 1 day, either has last best tip or default
			// tip pass time cap.
			$tip_info = WC_Facebookcommerce_Utils::get_cached_best_tip();
		} else {

			try {
				$tip_info = facebook_for_woocommerce()->get_api()->get_tip_info( $this->external_merchant_settings_id );
				update_option( 'fb_info_banner_last_query_time', current_time( 'mysql' ) );
			} catch ( ApiException $exception ) {
				// always log this error, regardless of debug setting
				facebook_for_woocommerce()->log( 'Could not retrieve tip. ' . $exception->getMessage() );
			}
		}

		// Not render if no cached best tip, or no best tip returned from FB.
		if ( ! $tip_info || ( self::FB_NO_TIP_EXISTS === $tip_info ) ) {
			// Delete cached tip if should query and get no tip.
			delete_option( 'fb_info_banner_last_best_tip' );
			return;
		} else {
			// Get tip creatives via API
			$tip_title       = $tip_info->get_tip_title_html() ?? null;
			$tip_body        = $tip_info->get_tip_body_html() ?? null;
			$tip_action_link = $tip_info->tip_action_link ?? null;
			$tip_action      = $tip_info->get_tip_action_html() ?? null;
			$tip_img_url     = isset( $tip_info->tip_img_url )
				? self::DEFAULT_TIP_IMG_URL_PREFIX . $tip_info->tip_img_url
				: null;

			if ( null === $tip_title || null === $tip_body || null === $tip_action_link || null === $tip_action || null === $tip_action ) {
				WC_Facebookcommerce_Utils::fblog(
					'Unexpected response from FB for tip info.',
					array( 'tip_info' => $tip_info ),
					true
				);
				return;
			}

			update_option(
				'fb_info_banner_last_best_tip',
				is_object( $tip_info ) || is_array( $tip_info )
				? json_encode( $tip_info ) : $tip_info
			);
		}

		$dismiss_url = $this->dismiss_url();

		echo '<div class="updated fade">';
		echo '<div id="fbinfobanner">';
		echo '<div><img src="' . esc_url( $tip_img_url ) . '" class="iconDetails"></div>';
		echo '<p class = "tipTitle"><strong>' . esc_html( $tip_title ) . "</strong></p>\n";
		echo '<p class = "tipContent">' . esc_html( $tip_body ) . '</p>';
		echo '<p class = "tipButton">';
		echo '<a href="' . esc_url( $tip_action_link ) . '" class = "btn" onclick="fb_woo_infobanner_post_click(); return true;" title="' . esc_attr__( 'Click and redirect.', 'facebook-for-woocommerce' ) . '"> ' . esc_html( $tip_action ) . '</a>';
		echo '<a href="' . esc_url( $dismiss_url ) . '" class = "btn dismiss grey" onclick="fb_woo_infobanner_post_xout(); return true;" title="' . esc_attr__( 'Dismiss this notice.', 'facebook-for-woocommerce' ) . '"> ' . esc_html__( 'Dismiss', 'facebook-for-woocommerce' ) . '</a>';
		echo '</p></div></div>';
	}

	/**
	 * Returns the url that the user clicks to remove the info banner
	 *
	 * @return string
	 */
	private function dismiss_url() {
		$url = admin_url( 'admin.php' );

		$url = add_query_arg(
			array(
				'page'      => 'wc-settings',
				'tab'       => 'integration',
				'wc-notice' => 'dismiss-fb-info-banner',
			),
			$url
		);

		return wp_nonce_url( $url, 'woocommerce_info_banner_dismiss' );
	}


	/**
	 * Handles the action that dismisses the info banner.
	 *
	 * The banner will remain dismissed for at least one day and until a new info tip can be retrieved.
	 *
	 * @see \WC_Facebookcommerce_Integration::FB_TIP_QUERY
	 */
	public function dismiss_banner() {

		if ( ! isset( $_GET['wc-notice'] ) ) {
			return;
		}

		if ( 'dismiss-fb-info-banner' !== $_GET['wc-notice'] ) {
			return;
		}

		if ( ! check_admin_referer( 'woocommerce_info_banner_dismiss' ) ) {
			return;
		}

		// Delete cached tip if xout.
		delete_option( 'fb_info_banner_last_best_tip' );
		if ( wp_get_referer() ) {
			wp_safe_redirect( wp_get_referer() );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=integration' ) );
		}
	}
}
