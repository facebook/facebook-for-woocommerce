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
 * Base Automatic advanced matching settings object
 */
class AAMSettings {

	/** @var bool is enable automatic matching enabled for this pixel */
	private $enable_automatic_matching;

	/** @var string[] advanced matching fields to extract when $enable_automatic_matching is true*/
	private $enabled_automatic_matching_fields;

	/** @var string pixel id associated with this settings*/
	private $pixel_id;

	const SIGNALS_JSON_CONFIG_PATH = 'signals/config/json';

	const CONNECT_FACEBOOK_DOMAIN = 'https://connect.facebook.net/';

	/**
	 * AAMSettings constructor
	 *
	 * @since 2.0.3
	 *
	 * @param array $data
	 */
	public function __construct( $data = array() ) {
		$this->enable_automatic_matching         = isset( $data['enableAutomaticMatching'] ) ? $data['enableAutomaticMatching'] : null;
		$this->enabled_automatic_matching_fields = isset( $data['enabledAutomaticMatchingFields'] ) ? $data['enabledAutomaticMatchingFields'] : null;
		$this->pixel_id                          = isset( $data['pixelId'] ) ? $data['pixelId'] : null;
	}


	/**
	 * Gets the URL used to retrieve the advanced matching settings for the given pixel ID.
	 *
	 * @since 2.0.3
	 *
	 * @param string $pixel_id pixel ID
	 * @return string
	 */
	public static function get_url( $pixel_id ) {

		return self::CONNECT_FACEBOOK_DOMAIN . self::SIGNALS_JSON_CONFIG_PATH . '/' . $pixel_id;
	}

	/**
	 * Factory method that builds an AAMSettings object given a pixel id
	 * by sending a request to connect.facebook.net domain
	 *
	 * @since 2.0.3
	 *
	 * @param string $pixel_id
	 */
	public static function build_from_pixel_id( $pixel_id ) {
		$url      = self::get_url( $pixel_id );
		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $response_body ) || array_key_exists( 'errorMessage', $response_body ) || ! isset( $response_body['matchingConfig'] ) ) {
			return null;
		}

		$response_body['matchingConfig']['pixelId'] = $pixel_id;
		return new AAMSettings( $response_body['matchingConfig'] );
	}

	/**
	 * Gets enable automatic matching flag
	 *
	 * @since 2.0.3
	 *
	 * @return bool
	 */
	public function get_enable_automatic_matching() {
		return $this->enable_automatic_matching;
	}

	/**
	 * Gets enabled automatic matching fields array
	 *
	 * @since 2.0.3
	 *
	 * @return string[]
	 */
	public function get_enabled_automatic_matching_fields() {
		return $this->enabled_automatic_matching_fields;
	}

	/**
	 * Gets the pixel id
	 *
	 * @since 2.0.3
	 *
	 * @return string
	 */
	public function get_pixel_id() {
		return $this->pixel_id;
	}

	/**
	 * Sets the enable automatic matching flag
	 *
	 * @since 2.0.3
	 * @param bool $enable_automatic_matching Whether automatic matching is enabled
	 * @return AAMSettings
	 */
	public function set_enable_automatic_matching( $enable_automatic_matching ) {
		$this->enable_automatic_matching = $enable_automatic_matching;
		return $this;
	}

	/**
	 * Sets the enabled automatic matching fields flag
	 *
	 * @since 2.0.3
	 * @param string[] $enabled_automatic_matching_fields Array of enabled matching fields
	 * @return AAMSettings
	 */
	public function set_enabled_automatic_matching_fields( $enabled_automatic_matching_fields ) {
		$this->enabled_automatic_matching_fields = $enabled_automatic_matching_fields;
		return $this;
	}

	/**
	 * Sets the pixel id
	 *
	 * @since 2.0.3
	 * @param string $pixel_id The Facebook pixel ID
	 * @return AAMSettings
	 */
	public function set_pixel_id( $pixel_id ) {
		$this->pixel_id = $pixel_id;
		return $this;
	}

	/**
	 * Returns the json string representing this object
	 *
	 * @since 2.0.3
	 *
	 * @return string
	 */
	public function __toString() {
		return wp_json_encode(
			array(
				'enableAutomaticMatching'        => $this->enable_automatic_matching,
				'enabledAutomaticMatchingFields' => $this->enabled_automatic_matching_fields,
				'pixelId'                        => $this->pixel_id,
			)
		);
	}
}
