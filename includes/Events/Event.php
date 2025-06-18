<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Event object.
 *
 * @since 2.0.0
 */
class Event {


	/**
	 * @var array data specific to this event instance with the same structure as the event's payload
	 *
	 * @see https://developers.facebook.com/docs/marketing-api/server-side-api/payload-helper
	 */
	protected $data = array();


	/**
	 * Gets version information for pixel events.
	 *
	 * @return array {
	 *     @type string source 'woocommerce'
	 *     @type string version WooCommerce's version
	 *     @type string pluginVersion Facebook for WooCommerce's version
	 * }
	 */
	public static function get_version_info() {
		return array(
			'source'        => 'woocommerce',
			'version'       => WC()->version,
			'pluginVersion' => facebook_for_woocommerce()->get_version(),
		);
	}


	/**
	 * Gets the agent string for pixel events.
	 *
	 * @return string
	 */
	public static function get_platform_identifier() {
		$info = self::get_version_info();
		return "{$info['source']}-{$info['version']}-{$info['pluginVersion']}";
	}


	/**
	 * Constructor.
	 *
	 * @see https://developers.facebook.com/docs/marketing-api/server-side-api/parameters
	 *
	 * @since 2.0.0
	 *
	 * @param array $data event data
	 */
	public function __construct( $data = array() ) {
		$this->prepare_data( $data );
	}


	/**
	 * Provides defaults for properties if not already defined.
	 *
	 * @see https://developers.facebook.com/docs/marketing-api/server-side-api/parameters/server-event
	 * @see https://developers.facebook.com/docs/marketing-api/server-side-api/parameters/custom-data
	 *
	 * @since 2.0.0
	 *
	 * @param array $data event data
	 */
	protected function prepare_data( $data ) {
		$this->data = wp_parse_args(
			$data,
			array(
				'action_source'    => 'website',
				'event_time'       => time(),
				'event_id'         => $this->generate_event_id(),
				'event_source_url' => $this->get_current_url(),
				'custom_data'      => array(),
				'user_data'        => array(),
			)
		);

		$this->prepare_user_data( $this->data['user_data'] );
	}


	/**
	 * Provides defaults for user properties if not already defined.
	 *
	 * @see https://developers.facebook.com/docs/marketing-api/server-side-api/parameters/user-data
	 *
	 * @since 2.0.0
	 *
	 * @param array $data user data
	 */
	protected function prepare_user_data( $data ) {
		$this->data['user_data'] = wp_parse_args(
			$data,
			array(
				'client_ip_address' => $this->get_client_ip(),
				'client_user_agent' => $this->get_client_user_agent(),
				'click_id'          => $this->get_click_id(),
				'browser_id'        => $this->get_browser_id(),
			)
		);
		// Country key is not the same in pixel and CAPI events, see:
		// https://developers.facebook.com/docs/facebook-pixel/advanced/advanced-matching
		// https://developers.facebook.com/docs/marketing-api/conversions-api/parameters
		if ( array_key_exists( 'cn', $this->data['user_data'] ) ) {
			$country                            = $this->data['user_data']['cn'];
			$this->data['user_data']['country'] = $country;
			unset( $this->data['user_data']['cn'] );
		}
		$this->data['user_data'] = Normalizer::normalize_array( $this->data['user_data'], false );
		$this->data['user_data'] = $this->hash_pii_data( $this->data['user_data'] );
	}

	/**
	 * Hashes the user data
	 *
	 * @see https://developers.facebook.com/docs/marketing-api/server-side-api/parameters/user-data
	 *
	 * @since 2.0.3
	 *
	 * @param array $user_data user data
	 *
	 * @return array
	 */
	protected function hash_pii_data( $user_data ) {
		$keys_to_hash = array( 'em', 'fn', 'ln', 'ph', 'ct', 'st', 'zp', 'country' );
		foreach ( $keys_to_hash as $key ) {
			if ( array_key_exists( $key, $user_data ) ) {
				$user_data[ $key ] = hash( 'sha256', $user_data[ $key ], false );
			}
		}
		if ( isset( $user_data['external_id'] ) ) {
			if ( is_array( $user_data['external_id'] ) ) {
				$external_ids = array();
				foreach ( $user_data['external_id'] as $id ) {
					if ( $id ) {
						$external_ids[] = hash( 'sha256', $id, false );
					}
				}
				$user_data['external_id'] = $external_ids;
			} else {
				$user_data['external_id'] = hash( 'sha256', $user_data['external_id'], false );
			}
		}
		return $user_data;
	}

	/**
	 * Generates a UUIDv4 unique ID for the event.
	 *
	 * @see https://stackoverflow.com/a/15875555
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
// phpcs:disable
	protected function generate_event_id() {
		try {
			$data = random_bytes( 16 );
			$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 ); // set version to 0100
			$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 ); // set bits 6-7 to 10
			return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
		} catch ( \Exception $e ) {
			// fall back to mt_rand if random_bytes is unavailable
			return sprintf(
				'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
				// 32 bits for "time_low"
				mt_rand( 0, 0xffff ),
				mt_rand( 0, 0xffff ),
				// 16 bits for "time_mid"
				mt_rand( 0, 0xffff ),
				// 16 bits for "time_hi_and_version",
				// four most significant bits holds version number 4
				mt_rand( 0, 0x0fff ) | 0x4000,
				// 16 bits, 8 bits for "clk_seq_hi_res",
				// 8 bits for "clk_seq_low",
				// two most significant bits holds zero and one for variant DCE1.1
				mt_rand( 0, 0x3fff ) | 0x8000,
				// 48 bits for "node"
				mt_rand( 0, 0xffff ),
				mt_rand( 0, 0xffff ),
				mt_rand( 0, 0xffff )
			);
		}
	}
// phpcs:enable


	/**
	 * Gets the current URL.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function get_current_url() {
		if ( wp_doing_ajax() ) {
			$url = wp_get_raw_referer();
		} else {
			/**
			 * Instead of relying on the HTTP_HOST server var, we use home_url(),
			 * so that we get the host configured in site options.
			 * Additionally, this automatically uses the correct domain when
			 * using Forward with the WooCommerce Dev Helper plugin.
			 */
			$url = home_url();
			if ( isset( $_SERVER['REQUEST_URI'] ) ) {
				$url .= wc_clean( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			}
		}
		return $url;
	}


	/**
	 * Gets the client IP address.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function get_client_ip() {
		return \WC_Geolocation::get_ip_address();
	}


	/**
	 * Gets the client user agent.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function get_client_user_agent() {
		return ! empty( $_SERVER['HTTP_USER_AGENT'] ) ? wc_clean( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	}


	/**
	 * Gets the click ID from the cookie or the query parameter.
	 *
	 * @see https://developers.facebook.com/docs/marketing-api/server-side-api/parameters/fbp-and-fbc#fbp-and-fbc-parameters
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function get_click_id() {
		$fbc = '';
		if ( isset( $_GET['fbclid'] ) ) {
			$fbclid   = sanitize_text_field( wp_unslash( $_GET['fbclid'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$cur_time = (int) ( microtime( true ) * 1000 );
			$fbc      = 'fb.1.' . $cur_time . '.' . rawurldecode( $fbclid );
		} elseif ( ! empty( $_COOKIE['_fbc'] ) ) {
			$fbc = wc_clean( wp_unslash( $_COOKIE['_fbc'] ) );
		} elseif ( isset( $_SESSION['_fbc'] ) ) {
			$fbc = $_SESSION['_fbc']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}
		if ( $fbc ) {
			$_SESSION['_fbc'] = $fbc;
		}
		return $fbc;
	}


	/**
	 * Gets the browser ID from the cookie.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function get_browser_id() {
		$fbp = ! empty( $_COOKIE['_fbp'] ) ? wc_clean( wp_unslash( $_COOKIE['_fbp'] ) ) : '';
		if ( $fbp ) {
			$_SESSION['_fbp'] = $fbp;
		} elseif ( isset( $_SESSION['_fbp'] ) ) {
			$fbp = $_SESSION['_fbp']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}
		return $fbp;
	}


	/**
	 * Gets the data.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_data() {
		return $this->data;
	}


	/**
	 * Gets the event ID.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_id() {
		return ! empty( $this->data['event_id'] ) ? $this->data['event_id'] : '';
	}


	/**
	 * Gets the event name.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_name() {
		return ! empty( $this->data['event_name'] ) ? $this->data['event_name'] : '';
	}


	/**
	 * Gets the user data.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_user_data() {
		return ! empty( $this->data['user_data'] ) ? $this->data['user_data'] : array();
	}


	/**
	 * Gets the event custom data.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_custom_data() {
		return ! empty( $this->data['custom_data'] ) ? $this->data['custom_data'] : array();
	}
}
