<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

/**
 * Class WC_Facebook_Admin_Notice
 *
 * Adds a dismissible global admin notice for Facebook for WooCommerce.
 *
 * @since 3.5.2
 */
class WC_Facebookcommerce_Admin_Notice {
	const NOTICE_ID = 'wc_facebook_admin_notice';

	/**
	 * Hooks into WordPress.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'show_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_notice_script' ) );
		add_action( 'wp_ajax_wc_facebook_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );
	}

	public function enqueue_notice_script() {
		wp_enqueue_script(
			'whatsapp-admin-notice',
			plugins_url( 'assets/js/admin/whatsapp-admin-notice.js', __FILE__ ),
			array( 'jquery' ),
			'1.0',
			true
		);
		wp_localize_script(
			'whatsapp-admin-notice',
			'WCFBAdminNotice',
			array(
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( self::NOTICE_ID ),
				'notice_id' => self::NOTICE_ID,
			)
		);
	}

	/**
	 * Handles the AJAX request to dismiss the notice.
	 */
	public function ajax_dismiss_notice() {
		check_ajax_referer( self::NOTICE_ID, 'nonce' );
		update_user_meta( get_current_user_id(), self::NOTICE_ID, 1 );
		wp_send_json_success();
	}

	/**
	 * Displays the admin notice if not dismissed.
	 */
	public function show_notice() {
		if ( strtotime( 'now' ) > strtotime( '2025-06-16 23:59:59' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( get_user_meta( get_current_user_id(), self::NOTICE_ID, true ) ) {
			return;
		}

		$dismiss_url = add_query_arg(
			array(
				self::NOTICE_ID => '1',
				'_wpnonce'      => wp_create_nonce( self::NOTICE_ID ),
			)
		);

		?>

		<div class="notice notice-info is-dismissible wc-facebook-global-notice">
			<p>
				<?php
				printf(
					wp_kses(
						// translators: %s: URL to the WhatsApp order tracking testing program sign-up page.
						__(
							"WhatsApp order tracking is now available for testing. <a href='%s'>Sign up for our testing program</a> and get early access now!",
							'facebook-for-woocommerce'
						),
						array(
							'a' => array(
								'href' => array(),
							),
						)
					),
					'https://facebookpso.qualtrics.com/jfe/form/SV_0SVseus9UADOhhQ'
				);
				?>
			</p>
		</div>
		<?php
	}
}
