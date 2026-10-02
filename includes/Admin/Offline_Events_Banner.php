<?php
/**
 * Copyright (c) Meta, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\Admin;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\Admin\Settings_Screens\Configuration;
use WooCommerce\Facebook\Events\POS\POS_Integration_Registry;
use WooCommerce\Facebook\Framework\Helper;

/**
 * Introduces offline (physical store) events to merchants who could use them.
 *
 * Shown on the plugin's admin page to store admins when all of these hold:
 *
 * - the store is connected to Meta, so the Configuration tab exists and events
 *   have somewhere to go;
 * - a supported point-of-sale plugin is active, so the setting can actually be
 *   switched on — new integrations qualify automatically through the registry;
 * - the store has never opted in, so merchants who tried the feature and turned
 *   it off are not pitched it again as new;
 * - this admin has not dismissed it. Dismissal is per user.
 *
 * These conditions are described in docs/offline-events.md ("Turning it on"), and
 * the banner links to that doc. Keep it in step when changing when the banner shows.
 */
class Offline_Events_Banner {

	/** @var string the admin notice ID, which also keys its per-user dismissal */
	const NOTICE_ID = 'wc_facebook_offline_events_introduction';

	/**
	 * Offline events banner constructor.
	 */
	public function __construct() {
		// Before the notice handler renders its notices, at priority 15.
		add_action( 'admin_notices', array( $this, 'maybe_add_notice' ), 10 );
	}

	/**
	 * Adds the banner when it applies to the current admin.
	 *
	 * @internal
	 */
	public function maybe_add_notice() {
		if ( ! $this->should_show() ) {
			return;
		}

		facebook_for_woocommerce()->get_admin_notice_handler()->add_admin_notice(
			$this->get_message(),
			self::NOTICE_ID,
			array(
				'dismissible'             => true,
				// The handler would otherwise show it on the plugin's own pages even
				// after it is dismissed, and those are the only pages it appears on.
				'always_show_on_settings' => false,
				'notice_class'            => 'notice-info',
			)
		);
	}

	/**
	 * Determines whether the banner applies on this request.
	 *
	 * Dismissal is left to the notice handler, which checks it per user.
	 *
	 * @return bool
	 */
	public function should_show(): bool {
		if ( ! is_admin() || Enhanced_Settings::PAGE_ID !== Helper::get_requested_value( 'page' ) ) {
			return false;
		}

		// Until the store is connected to Meta there is no Configuration tab to send the
		// merchant to, and no connection to send offline events through.
		if ( ! facebook_for_woocommerce()->get_connection_handler()->is_connected() ) {
			return false;
		}

		$integration = facebook_for_woocommerce()->get_integration();

		return $integration->can_enable_offline_purchase_events()
			&& ! $integration->has_ever_opted_in_to_offline_purchase_events();
	}

	/**
	 * Builds the banner message.
	 *
	 * @return string HTML.
	 */
	public function get_message(): string {
		$configuration_url = add_query_arg(
			array(
				'page' => Enhanced_Settings::PAGE_ID,
				'tab'  => Configuration::ID,
			),
			admin_url( 'admin.php' )
		);

		return sprintf(
			'<p><strong>%1$s</strong> %2$s</p><p><a class="button button-primary" href="%3$s">%4$s</a> <a href="%5$s" target="_blank" rel="noopener noreferrer">%6$s</a></p>',
			esc_html__( 'New: your in-store sales can now power your Meta ads.', 'facebook-for-woocommerce' ),
			esc_html(
				sprintf(
					/* translators: %s: names of the detected point of sale plugins, e.g. "WCPOS". */
					__( 'Every purchase rung up at your point of sale — through %s, with more point-of-sale systems on the way — can now reach Meta, so you can measure and optimize omni-channel campaigns on what customers actually buy, online and in your store. Setup takes one click.', 'facebook-for-woocommerce' ),
					$this->get_pos_names()
				)
			),
			esc_url( $configuration_url ),
			esc_html__( 'Enable Offline Events', 'facebook-for-woocommerce' ),
			esc_url( Configuration::OFFLINE_EVENTS_DOC_URL ),
			esc_html__( 'Learn how it works', 'facebook-for-woocommerce' )
		);
	}

	/**
	 * Gets the names of the active point-of-sale plugins, for the message.
	 *
	 * @return string
	 */
	private function get_pos_names(): string {
		$names = array_map(
			static function ( $integration ) {
				return $integration->get_name();
			},
			( new POS_Integration_Registry() )->get_supported_integrations()
		);

		return implode( ', ', $names );
	}
}
