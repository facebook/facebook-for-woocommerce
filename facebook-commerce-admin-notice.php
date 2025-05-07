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
 * @since x.x.x
 */
class WC_Facebookcommerce_Admin_Notice {
	const NOTICE_ID = 'wc_facebook_admin_notice';

	/**
	 * Hooks into WordPress.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'show_notice' ) );
	}
	public function show_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		?>

		<div class="notice notice-info is-dismissible wc-facebook-global-notice">
			<p>
				<?php
				printf(
					wp_kses(
						// translators: %s: URL to the WhatsApp order tracking testing program sign-up page.
						__(
							"WhatsApp order tracking is now available for testing. <a href='%s'>Sign up our testing program</a> and get early access now!",
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
