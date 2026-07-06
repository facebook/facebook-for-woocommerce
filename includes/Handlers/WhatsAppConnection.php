<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\Handlers;

use WooCommerce\Facebook\Framework\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * The whatsapp utility connection handler.
 *
 * @since 2.0.0
 */
class WhatsAppConnection {

	/** @var string the system user access token option name */
	const OPTION_WA_UTILITY_ACCESS_TOKEN = 'wc_facebook_wa_utility_access_token';
	/** @var string the whatsapp external id option name */
	const OPTION_WA_EXTERNAL_BUSINESS_ID = 'wc_facebook_wa_external_business_id';
	/** @var string the whatsapp business id option name */
	const OPTION_WA_BUSINESS_ID = 'wc_facebook_wa_business_id';
	/** @var string the whatsapp business account id option name */
	const OPTION_WA_WABA_ID = 'wc_facebook_wa_waba_id';
	/** @var string the whatsapp phone number id option name */
	const OPTION_WA_PHONE_NUMBER_ID = 'wc_facebook_wa_phone_number_id';
	/** @var string the whatsapp installation id option name */
	const OPTION_WA_INSTALLATION_ID = 'wc_facebook_wa_installation_id';
	/** @var string the whatsapp integration config id option name */
	const OPTION_WA_INTEGRATION_CONFIG_ID = 'wc_facebook_wa_integration_config_id';
	/** @var string the whatsapp onboarding completion flag option name */
	const OPTION_WA_ONBOARDING_COMPLETE = 'wc_facebook_wa_onboarding_complete';

	/** @var string onboarding state: onboarding finished (integration config exists) */
	const ONBOARDING_STATE_COMPLETE = 'yes';
	/** @var string onboarding state: known not finished (no integration config) */
	const ONBOARDING_STATE_INCOMPLETE = 'no';
	/** @var string onboarding state: not yet determined */
	const ONBOARDING_STATE_UNKNOWN = 'unknown';



	/** @var \WC_Facebookcommerce */
	private $plugin;

	/** @var string|null the generated external whatsapp ID */
	private $wa_external_id;


	/**
	 * Constructs a new WA Utility Connection.
	 *
	 * @since 2.0.0
	 *
	 * @param \WC_Facebookcommerce $plugin
	 */
	public function __construct( \WC_Facebookcommerce $plugin ) {

		$this->plugin = $plugin;
	}


	/**
	 * Gets the system user access token.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_access_token() {
		$access_token = get_option( self::OPTION_WA_UTILITY_ACCESS_TOKEN, '' );
		return $access_token;
	}

	/**
	 * Gets the WA installation ID.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_wa_installation_id() {
		$wa_installation_id = get_option( self::OPTION_WA_INSTALLATION_ID, '' );
		return $wa_installation_id;
	}


	/**
	 * Determines whether the site is integrated whatsapp utility.
	 *
	 * A site is connected if there is an access token stored.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_connected() {
		return (bool) $this->get_access_token();
	}

	/**
	 * Gets the stored WhatsApp onboarding completion state.
	 *
	 * Onboarding is "complete" once an integration config exists on the Meta
	 * side. The state is tri-valued so the customer_events gate can fail open
	 * while it is still unknown (see WhatsAppExtension):
	 *  - COMPLETE   : a Meta integration config exists for this installation
	 *  - INCOMPLETE : Meta confirmed no integration config exists
	 *  - UNKNOWN    : not yet determined (never signalled / backfilled)
	 *
	 * The state is written by the onboarding signal handlers (push listener and
	 * upgrade backfill) added in a follow-up change; until then it is UNKNOWN
	 * for every install, so the gate fails open and behavior is unchanged.
	 *
	 * @since 3.7.5
	 *
	 * @return string one of the ONBOARDING_STATE_* constants
	 */
	public function get_onboarding_state(): string {
		$state = get_option( self::OPTION_WA_ONBOARDING_COMPLETE, self::ONBOARDING_STATE_UNKNOWN );
		if ( self::ONBOARDING_STATE_COMPLETE === $state || self::ONBOARDING_STATE_INCOMPLETE === $state ) {
			return $state;
		}
		return self::ONBOARDING_STATE_UNKNOWN;
	}

	/**
	 * Stores the WhatsApp onboarding completion state.
	 *
	 * Written by the onboarding signal handlers: the WA_CONNECT postMessage
	 * listener (push, new connects) and the upgrade-hook HEAD backfill (pull,
	 * existing installs). Maps the boolean to the tri-state option the
	 * customer_events gate reads via get_onboarding_state():
	 *  - true  → COMPLETE   ('yes')
	 *  - false → INCOMPLETE  ('no')
	 *
	 * @since 3.7.5
	 *
	 * @param bool $complete whether Meta has confirmed onboarding is complete
	 */
	public function set_onboarding_complete( bool $complete ): void {
		update_option(
			self::OPTION_WA_ONBOARDING_COMPLETE,
			$complete ? self::ONBOARDING_STATE_COMPLETE : self::ONBOARDING_STATE_INCOMPLETE
		);
	}

	/**
	 * Determines whether WhatsApp onboarding is known to be complete.
	 *
	 * Only COMPLETE counts as complete; UNKNOWN returns false so callers do not
	 * treat a not-yet-determined install as onboarded.
	 *
	 * @since 3.7.5
	 *
	 * @return bool
	 */
	public function is_onboarding_complete(): bool {
		return self::ONBOARDING_STATE_COMPLETE === $this->get_onboarding_state();
	}

	/**
	 * Gets the stored whatsapp external ID.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_whatsapp_external_id() {
		if ( ! is_string( $this->wa_external_id ) ) {
			$external_id = get_option( self::OPTION_WA_EXTERNAL_BUSINESS_ID );
			if ( ! is_string( $external_id ) || empty( $external_id ) ) {
				/**
				 * Filters the whatsapp external ID.
				 *
				 * This is passed to Meta when Onboarding.
				 * Should be non-empty and without special characters, otherwise the ID will be obtained from the site URL as fallback.
				 *
				 * @since 2.0.0
				 *
				 * @param string $external_id the whatsapp external ID
				 */
				$external_id_prefix = sanitize_key( (string) get_bloginfo( 'name' ) );
				if ( empty( $external_id_prefix ) ) {
					$external_id_prefix = sanitize_key( str_replace( array( 'http', 'https', 'www' ), '', get_bloginfo( 'url' ) ) );
				}
				$external_id = uniqid( sprintf( '%s-', $external_id_prefix ), false );
				$this->update_whatsapp_external_business_id( $external_id );
			}
			$this->wa_external_id = $external_id;
		}

		return $external_id;
	}

	/**
	 * Stores the given wa external id.
	 *
	 * @since 2.6.13
	 *
	 * @param string $value external business id
	 */
	public function update_whatsapp_external_business_id( $value ) {
		update_option( self::OPTION_WA_EXTERNAL_BUSINESS_ID, is_string( $value ) ? $value : '' );
	}
}
