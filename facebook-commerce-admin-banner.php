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
 * Outputs WhatApp utility messaging recruitment admin banner
 *
 * @since x.x.x
 */

/**
 * WhatsApp Admin Banner class for Facebook for WooCommerce.
 */
class WC_Facebookcommerce_Admin_Banner {
	const BANNER_ID = 'wc_facebook_admin_banner';

	/**
	 * Output the banner HTML if it should be shown.
	 */
	public function render_banner() {

		$banner_html  = '<div class="fb-wa-banner">';
		$banner_html .= '<img src="' . esc_url( plugins_url( 'assets/images/ico-whatsapp.png', __FILE__ ) ) . '" width="36" height="36" alt="WhatsApp Logo" />';
		$banner_html .= '<h2>Sign up to test WhatsApp’s new integration with WooCommerce</h2>';
		$banner_html .= '<p>We’re launching a brand new WhatsApp integration for WooCommerce allowing businesses to send order tracking notifications on WhatsApp. Sign up for a chance to join our testing program and get early access to this new feature. As a thank you, participants who complete testing will receive a $500 ad credit.</p>';
		$banner_html .= '<a class="wa-cta-button" href="https://facebookpso.qualtrics.com/jfe/form/SV_0SVseus9UADOhhQ">Sign Up</a>';
		$banner_html .= '<a class="wa-close-button" title="Close banner" href="#"><img src="' . esc_url( plugins_url( 'assets/images/ico-close.svg', __FILE__ ) ) . '" width="16" height="16" alt="Close button" /></a>';
		$banner_html .= '</div>';

		echo wp_kses_post( $banner_html );
	}
}
